<?php

namespace App\Controller;

use App\Entity\Book;
use App\Repository\AuthorRepository;
use App\Repository\BookRepository;
use App\Service\VersioningService;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\DeserializationContext;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use OpenApi\Attributes as OA;

final class BookController extends AbstractController
{
    public function __construct(
        private readonly SerializerInterface $serializer,
    ) {}

    #[Route('/api/books', name: 'all_books', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Returns a list of books with pagination',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(
                    property: 'books',
                    type: 'array',
                    items: new OA\Items(ref: new Model(type: Book::class, groups: ['book:view']))
                ),
                new OA\Property(
                    property: 'pagination',
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'nextPage', type: 'string'),
                        new OA\Property(property: 'previousPage', type: 'string'),
                        new OA\Property(property: 'currentPage', type: 'integer'),
                        new OA\Property(property: 'numberOfPages', type: 'integer'),
                        new OA\Property(property: 'limit', type: 'integer'),
                        new OA\Property(property: 'totalItems', type: 'integer'),
                    ]
                ),
            ]
        )
    )]
    #[OA\Parameter(
        name: 'page',
        in: 'query',
        description: 'The page number to retrieve',
        required: false,
        schema: new OA\Schema(type: 'integer', default: 1)
    )]
    #[OA\Parameter(
        name: 'limit',
        in: 'query',
        description: 'The number of items per page',
        required: false,
        schema: new OA\Schema(type: 'integer', default: 10)
    )]
    #[OA\Tag(name: 'Books')]
    public function getAllBooks(BookRepository $bookRepository, Request $request, PaginatorInterface $paginator, TagAwareCacheInterface $cache, VersioningService $versioningService): JsonResponse
    {
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 10);

        // $idCache = 'books_page_' . $page . '_limit_' . $limit;

        // $books = $cache->get($idCache, function(ItemInterface $item) use ($bookRepository, $idCache) {
        //     echo "Cache-miss-for-$idCache\n";
        //     $item->tag('books_cache');
        //     $item->expiresAfter(3600);
        //     return $bookRepository->findAllWithEagerLoading();
        // });

        $books = $bookRepository->findAll();

        $pagination = $paginator->paginate(
            $books,
            $page,
            $limit
        );

        $version = $versioningService->getVersion();
        $context = SerializationContext::create()->setGroups(['book:view']);
        $context->setVersion($version);

        return $this->jms_json([
            'books' => $pagination->getItems(),
            'pagination' => [
                'nextPage' => $this->generateUrl('all_books', ['page' => $page + 1, 'limit' => $limit], UrlGeneratorInterface::ABSOLUTE_URL),
                'previousPage' => $this->generateUrl('all_books', ['page' => max($page - 1, 1), 'limit' => $limit], UrlGeneratorInterface::ABSOLUTE_URL),
                'currentPage' => $pagination->getCurrentPageNumber(),
                'numberOfPages' => (int) ceil($pagination->getTotalItemCount() / $pagination->getItemNumberPerPage()),
                'limit' => $pagination->getItemNumberPerPage(),
                'totalItems' => $pagination->getTotalItemCount(),
            ]
        ], context: $context);
    }

    #[Route('/api/books/{id}', name: 'detail_book', methods: ['GET'])]
    public function getDetailBook(Book $book, VersioningService $versioningService): JsonResponse
    {
        $version = $versioningService->getVersion();
        $context = SerializationContext::create()->setGroups(['book:view']);
        $context->setVersion($version);

        return $this->jms_json([
            'book' => $book,
        ], context: $context);
    }

    #[Route('/api/books/{id}', name: 'delete_book', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN', message: 'Only admins can delete books.')]
    public function deleteBook(Book $book, EntityManagerInterface $em, TagAwareCacheInterface $cache): JsonResponse
    {
        $cache->invalidateTags(['books_cache']);

        $em->remove($book);
        $em->flush();

        return $this->jms_json([
            'message' => 'Book deleted successfully',
        ]);
    }

    #[Route('/api/books', name: 'create_book', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN', message: 'Only admins can create books.')]
    public function createBook(Request $request, EntityManagerInterface $em, SerializerInterface $serializer, AuthorRepository $authorRepository, ValidatorInterface $validator): JsonResponse
    {
        $context = DeserializationContext::create()->setGroups(['book:create']);

        /**
         * @var Book
         */
        $book = $serializer->deserialize($request->getContent(),
            Book::class,
            'json',
            $context);

        $errors = $validator->validate($book);
        if ($errors->count() > 0) {
            return $this->jms_json([
                'errors' => $errors,
            ], status: Response::HTTP_BAD_REQUEST);
        }

        // Récupération de l'ensemble des données envoyées sous forme de tableau
        $content = $request->toArray();

        // Récupération de l'idAuthor. S'il n'est pas défini, alors on met null par défaut.
        $idAuthor = $content['author']['id'] ?? null;
        if ($idAuthor) {
            // On cherche l'auteur qui correspond et on l'assigne au livre.
            // Si "find" ne trouve pas l'auteur, alors null sera retourné.
            $author = $authorRepository->find($idAuthor);
            if ($author) {
                $book->setAuthor($author);
            }
        }

        $em->persist($book);
        $em->flush();

        $location = $this->generateUrl('detail_book',
            ['id' => $book->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL);

        $context = SerializationContext::create()->setGroups(['book:view']);

        return $this->jms_json([
            'message' => 'Book created successfully',
            'book' => $book,
        ], status: Response::HTTP_CREATED, headers: [
            'Location' => $location
        ], context: $context);
    }

    #[Route('/api/books/{id}', name: 'update_book', methods: ['PUT'])]
    public function updateBook(Book $currentBook, Request $request, EntityManagerInterface $em, SerializerInterface $serializer, AuthorRepository $authorRepository, ValidatorInterface $validator, TagAwareCacheInterface $cache): JsonResponse
    {
        $context = DeserializationContext::create()
            ->setAttribute(AbstractNormalizer::OBJECT_TO_POPULATE, $currentBook)
            ->setGroups(['book:update']);

        // Désérialisation des données reçues
        /**
         * @var Book
         */
        $bookUpdated = $serializer->deserialize($request->getContent(),
            Book::class,
            'json',
            $context);

        $errors = $validator->validate($bookUpdated);
        if ($errors->count() > 0) {
            return $this->jms_json([
                'errors' => $errors,
            ], status: Response::HTTP_BAD_REQUEST);
        }

        $currentBook->setTitle($bookUpdated->getTitle());
        $currentBook->setCoverText($bookUpdated->getCoverText());

        // Récupération de l'ensemble des données envoyées sous forme de tableau
        $content = $request->toArray();

        // Récupération de l'idAuthor. S'il n'est pas défini, alors on met -1 par défaut.
        $idAuthor = $content['author']['id'] ?? -1;

        // On cherche l'auteur qui correspond et on l'assigne au livre.
        // Si "find" ne trouve pas l'auteur, alors null sera retourné.
        $author = $authorRepository->find($idAuthor);
        $currentBook->setAuthor($author);

        $em->flush();

        // On vide le cache.
        $cache->invalidateTags(['books_cache']);

        $context = SerializationContext::create()->setGroups(['book:view']);

        return $this->jms_json([
            'message' => 'Book updated successfully',
            'book' => $currentBook,
        ], context: $context);
    }

    /**
     * Returns a JsonResponse that uses the serializer component if enabled, or json_encode.
     *
     * @param int $status The HTTP status code (200 "OK" by default)
     */
    protected function jms_json(mixed $data, int $status = 200, array $headers = [], SerializationContext $context = null): JsonResponse
    {
        if ($this->serializer instanceof SerializerInterface) {
            $json = $this->serializer->serialize($data, 'json', $context);

            return new JsonResponse($json, $status, $headers, true);
        }

        if (null === $data) {
            return new JsonResponse('null', $status, $headers, true);
        }

        return new JsonResponse($data, $status, $headers);
    }
}
