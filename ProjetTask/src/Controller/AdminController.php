<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class AdminController extends AbstractController
{
    #[Route('/admin', name: 'app_admin')]
    public function index(): Response
    {
        // Simuler des données utilisateurs pour l'affichage
        $users = [
            [
                'id' => 1,
                'nom' => 'Bernard Martin',
                'email' => 'bernard.martin@free.fr ',
                'role' => 'Directeur',
                'status' => 'Actif',
                'avatar' => null
            ],
            [
                'id' => 2,
                'nom' => 'Clara Lefèvre',
                'email' => 'clara.lefevre@orange.fr',
                'role' => 'Chefs de Projet ',
                'status' => 'Actif',
                'avatar' => null
            ],
            [
                'id' => 3,
                'nom' => 'David  Moreau',
                'email' => 'david.moreau@orange.fr',
                'role' => 'Chefs de Projet',
                'status' => 'Actif',
                'avatar' => null
            ],
            [
                'id' => 4,
                'nom' => 'François Girard',
                'email' => 'francois.girard@gmail.com',
                'role' => 'Employés',
                'status' => 'Actif',
                'avatar' => null
            ],
            [
                'id' => 5,
                'nom' => 'Hélène Bernard',
                'email' => 'helene.bernard@gmail.com',
                'role' => 'Employés',
                'status' => 'Actif',
                'avatar' => null
            ],
            [
                'id' => 6,
                'nom' => 'Julien Fontaine',
                'email' => 'julien.fontaine@example.com',
                'role' => 'Employés',
                'status' => 'Actif',
                'avatar' => null
            ],
            [
                'id' => 7,
                'nom' => 'Karine Roche',
                'email' => 'karine.roche@gmail.com',
                'role' => 'Employés',
                'status' => 'Inactif',
                'avatar' => null
            ]
        ];

        return $this->render('admin/index.html.twig', [
            'controller_name' => 'AdminController',
            'users' => $users,
            'current_user' => [
                'nom' => 'admin',
                'role' => 'Administrateur'
            ]
        ]);
    }

    #[Route('/admin/user/add', name: 'app_admin_user_add', methods: ['GET', 'POST'])]
    public function addUser(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            // Ici vous ajouteriez la logique de création d'utilisateur
            // Pour l'exemple, on redirige vers la page admin
            $this->addFlash('success', 'Utilisateur ajouté avec succès !');
            return $this->redirectToRoute('app_admin');
        }

        return $this->render('admin/add_user.html.twig');
    }

    #[Route('/admin/logout', name: 'app_admin_logout')]
    public function logout(): Response
    {
        // La logique de déconnexion sera gérée par Symfony Security
        throw new \LogicException('Cette méthode peut être vide - elle sera interceptée par la clé de déconnexion de votre pare-feu.');
    }
    #[IsGranted('ROLE_ADMIN')]


    #[Route('/admin/dashboard', name: 'app_admin_dashboard')]
    public function dashboard(): Response
    {
        // Logique spécifique pour le dashboard admin

        return $this->render('admin/dashboard.html.twig', [
            'controller_name' => 'AdminDashboardController',
            // Autres variables
        ]);
    }
}
