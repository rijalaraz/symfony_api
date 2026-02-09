<?php

namespace App\Controller;

use App\Entity\Book;
use App\Repository\AuthorRepository;
use App\Repository\BookRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;

final class BookController extends AbstractController
{
    #[Route('/api/books', name: 'all_books', methods: ['GET'])]
    public function getAllBooks(BookRepository $bookRepository, SerializerInterface $serializer): JsonResponse
    {
        $books = $bookRepository->findAll();

        // $jsonBooks = $serializer->serialize($books,'json');

        return $this->json([
            'books' => $books,
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
    public function createBook(Request $request, EntityManagerInterface $em, SerializerInterface $serializer, AuthorRepository $authorRepository): JsonResponse
    {
        $book = $serializer->deserialize($request->getContent(),
            Book::class,
            'json');

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
