<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class TaskListController extends AbstractController
{
    #[Route('/t/a/s/k/l/i/s/t', name: 'app_t_a_s_k_l_i_s_t')]
    public function index(): Response
    {
        return $this->render('tasklist/index.html.twig', [
            'controller_name' => 'TASKLISTController',
        ]);
    }
}
