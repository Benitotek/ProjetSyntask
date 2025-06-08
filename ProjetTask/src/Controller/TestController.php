<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class TestController extends AbstractController
{
    // Route racine
    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        return $this->render('home/index.html.twig');
    }

    // Route catch-all pour les IDs numériques
    #[Route('/{id}', name: 'app_item_show', requirements: ['id' => '\d+'], priority: -1)]
    public function showItem(int $id): Response
    {
        // Logique pour afficher un élément par ID
        return $this->render('item/show.html.twig', ['id' => $id]);
    }
}