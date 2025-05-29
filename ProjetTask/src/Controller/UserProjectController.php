<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class UserProjectController extends AbstractController
{
    #[Route('/user/project', name: 'app_user_project')]
    public function index(): Response
    {
        return $this->render('user_project/index.html.twig', [
            'controller_name' => 'UserProjectController',
        ]);
    }
}
