<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\Userstatut;
use App\Form\UserType;
use App\Form\UserTypeForm;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

#[Route('/admin/users')]
#[IsGranted('ROLE_ADMIN')]
class UserController extends AbstractController
{
    // Route pour afficher la liste des utilisateurs
    #[Route('/', name: 'app_user_index', methods: ['GET'])]
    public function index(UserRepository $userRepository): Response
    {
        $users = $userRepository->findAll();
        $userstatutes = Userstatut::cases();
        $userstatutLabels = array_map(fn($statut) => $statut->label(), $userstatutes);

        return $this->render('user/index.html.twig', [
            'users' => $users,
            'userstatutes' => $userstatutes,
            'userstatutLabels' => $userstatutLabels,
        ]);
    }
     // Route pour le profil de l'utilisateur connecté
    #[Route('/mon-profil', name: 'app_my_profile', methods: ['GET', 'POST'])]
    public function myProfile(Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        $form = $this->createForm(UserTypeForm::class, $user);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            // Ici, tu peux gérer l'upload d'avatar si nécessaire, ex :
            // $avatar = $form->get('avatar')->getData();
            $em->flush();
            $this->addFlash('success', 'Profil mis à jour !');
            // On reste sur la même page :
            return $this->redirectToRoute('app_my_profile');
        }

        return $this->render('user/profile.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
        ]);
    }
    

    // cette route permet de créer un nouvel utilisateur 
    // elle est accessible uniquement aux utilisateurs ayant le rôle ROLE_ADMIN
#[Route('/new', name: 'app_user_new', methods: ['GET', 'POST'])]
public function new(
    Request $request,
    AuthorizationCheckerInterface $authChecker,
    UserPasswordHasherInterface $passwordHasher,
    EntityManagerInterface $entityManager
): Response {
    $user = new User();

    $canChooseRole = $authChecker->isGranted('ROLE_ADMIN') || $authChecker->isGranted('ROLE_DIRECTEUR');

    $form = $this->createForm(UserTypeForm::class, $user, [
        'can_choose_role' => $canChooseRole,
    ]);

    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        $plainPassword = $form->get('password')->getData();
        if ($plainPassword) {
            $hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);
            $user->setMdp($hashedPassword);
        }
        $user->setDateCreation(new \DateTime());
        $user->setDateMaj(new \DateTime());
        $user->setEstActif(true);

        $entityManager->persist($user);
        $entityManager->flush();

        $this->addFlash('success', 'Utilisateur créé avec succès.');
        return $this->redirectToRoute('app_user_index');
    }

    return $this->render('user/new.html.twig', [
        'user' => $user,
        'form' => $form,
    ]);
}

   #[Route('/admin/users/{id}/show', name: 'app_user_show', methods: ['GET'])]
public function show(User $user): Response
{
    return $this->render('user/show.html.twig', [
        'user' => $user,
    ]);
}
    #[Route('/{id}/edit', name: 'app_user_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, User $user, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher): Response
    {
        $form = $this->createForm(UserTypeForm::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('password')->getData();
            if ($plainPassword) {
                $hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);
                $user->setMdp($hashedPassword);
            }
            $user->setDateMaj(new \DateTime());
            $entityManager->flush();

            $this->addFlash('success', 'Utilisateur modifié avec succès.');
            return $this->redirectToRoute('app_user_index');
        }

        return $this->render('user/edit.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }
// Attention a cette route car elle supprime l'utilisateur des que l'on mets l'ID dans l'URL
    #[Route('/{id}', name: 'app_user_delete', methods: ['POST', 'GET'])]
    public function delete(Request $request, User $user, EntityManagerInterface $entityManager): Response
    {
       
     // Temporairement désactiver la validation CSRF pour tester
    // if ($this->isCsrfTokenValid('delete' . $user->getId(), $request->request->get('_token'))) {
        $entityManager->remove($user);
        $entityManager->flush();
        $this->addFlash('success', 'Utilisateur supprimé avec succès.');
    // } else {
    //     $this->addFlash('danger', 'Jeton CSRF invalide. Suppression annulée.');
    // }
        return $this->redirectToRoute('app_user_index');
    }

    // #[Route('/{id}/toggle-statut', name: 'app_user_toggle_statut', methods: ['POST'])]
    // public function togglestatut(User $user, EntityManagerInterface $entityManager): Response
    // {
    //     $user->setEstActif(!$user->isEstActif());
    //     $user->setDateMaj(new \DateTime());
    //     $entityManager->flush();

    //     $statut = $user->isEstActif() ? 'activé' : 'désactivé';
    //     $this->addFlash('success', "Utilisateur {$statut} avec succès.");

    //     return $this->redirectToRoute('app_user_index');
    // }
}
