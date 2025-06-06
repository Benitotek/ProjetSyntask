<?php

namespace App\Controller;

use App\Entity\Project;
use App\Entity\User;
use App\Form\UserTypeForm;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class UserController extends AbstractController
{
    #[Route('/user', name: 'app_user')]
    public function index(): Response
    {
        return $this->render('user/index.html.twig', [
            'controller_name' => 'UserController',
        ]);
    }
    // Route qui va afficher le formulaire de création d'un utilisateur
    #[Route('/user/create', name: 'user_create', methods: ['GET', 'POST'])]
    public function create(EntityManagerInterface $entityManager, \Symfony\Component\HttpFoundation\Request $request): Response
    {
        $user = new User();
        $form = $this->createForm(UserTypeForm::class, $user);
        
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $user->setDateCreation(new \DateTime());
            $user->setDateMaj(new \DateTime());
            $entityManager->persist($user);
            $entityManager->flush();

            return $this->redirectToRoute('userlist');
        }

        return $this->render('user/create.html.twig', [
            'form' => $form->createView(),
        ]);
    }
    // Route qui va lister tous les utilisateurs
    #[Route('/userlist', name: 'userlist', methods: ['GET'])]
    public function userlist(EntityManagerInterface $entityManager): Response
    {
       $users = $entityManager->getRepository(User::class)->findBy([], ['nom' => 'ASC']);
       $projects = $entityManager->getRepository(Project::class)->findBy([], ['nom' => 'ASC']);
       return $this->render('user/userlist.html.twig', [
        'users' => $users, 
        'projects' => $projects,
        ]);
    }
}
