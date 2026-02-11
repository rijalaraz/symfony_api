<?php

namespace App\Controller;

use App\Entity\Book;
use App\Repository\AuthorRepository;
use App\Repository\BookRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

final class BookController extends AbstractController
{
    #[Route('/api/books', name: 'all_books', methods: ['GET'])]
    public function getAllBooks(BookRepository $bookRepository, SerializerInterface $serializer, Request $request, PaginatorInterface $paginator, TagAwareCacheInterface $cache): JsonResponse
    {
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 10);

        $idCache = 'books_page_' . $page . '_limit_' . $limit;

        $pagination = $cache->get($idCache, function(ItemInterface $item) use ($bookRepository, $page, $limit, $paginator, $idCache) {
            echo "Cache miss for $idCache\n";
            $item->tag('books_cache');
            return $paginator->paginate(
                $bookRepository->findAll(),
                $page,
                $limit
            );
        });

        // $pagination = $paginator->paginate(
        //     $bookRepository->findAll(),
        //     $page,
        //     $limit
        // );

        // $jsonBooks = $serializer->serialize($books,'json');

        return $this->json([
            'books' => $pagination->getItems(),
            'pagination' => [
                'nextPage' => $this->generateUrl('all_books', ['page' => $page + 1, 'limit' => $limit], UrlGeneratorInterface::ABSOLUTE_URL),
                'previousPage' => $this->generateUrl('all_books', ['page' => max($page - 1, 1), 'limit' => $limit], UrlGeneratorInterface::ABSOLUTE_URL),
                'currentPage' => $pagination->getCurrentPageNumber(),
                'numberOfPages' => ceil($pagination->getTotalItemCount() / $pagination->getItemNumberPerPage()),
                'limit' => $pagination->getItemNumberPerPage(),
                'totalItems' => $pagination->getTotalItemCount(),
            ]
        ], context:[
            'groups' => 'book:view'
        ]);
    }

    #[Route('/api/books/{id}', name: 'detail_book', methods: ['GET'])]
    public function getDetailBook(Book $book, SerializerInterface $serializer): JsonResponse
    {
        // $jsonBook = $serializer->serialize($book,'json');

        return $this->json([
            'book' => $book,
        ], context:[
            'groups' => 'book:view'
        ]);
    }

    #[Route('/api/books/{id}', name: 'delete_book', methods: ['DELETE'])]
    public function deleteBook(Book $book, EntityManagerInterface $em): JsonResponse
    {
        $em->remove($book);
        $em->flush();

        return $this->json([
            'message' => 'Book deleted successfully',
        ]);
    }

    #[Route('/api/books', name: 'create_book', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN', message: 'Only admins can create books.')]
    public function createBook(Request $request, EntityManagerInterface $em, SerializerInterface $serializer, AuthorRepository $authorRepository, ValidatorInterface $validator): JsonResponse
    {
        $book = $serializer->deserialize($request->getContent(),
            Book::class,
            'json');

        $errors = $validator->validate($book);
        if (count($errors) > 0) {
            return $this->json([
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

        return $this->json([
            'message' => 'Book created successfully',
            'book' => $book,
        ], status: Response::HTTP_CREATED, headers: [
            'Location' => $location
        ], context:[
            'groups' => 'book:view'
        ]);
    }

    #[Route('/api/books/{id}', name: 'update_book', methods: ['PUT'])]
    public function updateBook(Book $book, Request $request, EntityManagerInterface $em, SerializerInterface $serializer, AuthorRepository $authorRepository): JsonResponse
    {
        // Désérialisation des données reçues
        $bookUpdated = $serializer->deserialize($request->getContent(),
            Book::class,
            'json',
            [AbstractNormalizer::OBJECT_TO_POPULATE => $book]);

        // Récupération de l'ensemble des données envoyées sous forme de tableau
        $content = $request->toArray();

        // Récupération de l'idAuthor. S'il n'est pas défini, alors on met -1 par défaut.
        $idAuthor = $content['author']['id'] ?? -1;

        // On cherche l'auteur qui correspond et on l'assigne au livre.
        // Si "find" ne trouve pas l'auteur, alors null sera retourné.
        $author = $authorRepository->find($idAuthor);
        $bookUpdated->setAuthor($author);

        $em->persist($bookUpdated);
        $em->flush();

        return $this->json([
            'message' => 'Book updated successfully',
            'book' => $bookUpdated,
        ], context:[
            'groups' => 'book:view'
        ]);
    }
}
