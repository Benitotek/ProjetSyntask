<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class RoleBasedKanbanController extends AbstractController
{
    #[Route('/role/based/kanban', name: 'app_role_based_kanban')]
    public function index(): Response
    {
        return $this->render('role_based_kanban/index.html.twig', [
            'controller_name' => 'RoleBasedKanbanController',
        ]);
    }
}
