<?php

namespace App\Controller;

use App\Entity\Book;
use App\Repository\BookRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
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
        ], context:['groups' => 'book:show']);
    }

    #[Route('/api/books/{id}', name: 'detail_book', methods: ['GET'])]
    public function getDetailBook(Book $book, SerializerInterface $serializer): JsonResponse
    {
        // $jsonBook = $serializer->serialize($book,'json');

        return $this->json([
            'book' => $book,
        ], context:['groups' => 'book:show']);
    }
}
