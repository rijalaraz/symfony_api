<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ExternalApiController extends AbstractController
{
   
    /**
     * Fetches data from the Symfony documentation GitHub API
     */
    #[Route('/api/external/getSfDoc', name: 'external_api', methods: ['GET'])]
    public function index(HttpClientInterface $httpClient): JsonResponse
    {
        $response = $httpClient->request(
            'GET',
            'https://api.github.com/repos/symfony/symfony-docs'
        );

        return $this->json($response->toArray(), $response->getStatusCode());
    }
}
