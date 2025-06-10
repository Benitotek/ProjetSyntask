<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/home', name: 'home',methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('home/index.html.twig', [
            'message' => 'Bienvenue sur la page Home de mon Projet SYNTASK!',
        ]);
    }
}
