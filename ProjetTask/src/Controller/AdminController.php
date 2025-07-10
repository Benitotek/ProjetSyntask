<?php

namespace App\Controller;

use App\Service\UserRoleUpdater;
use App\Entity\User;
use App\Enum\Userstatut;
use App\Form\UserTypeForm;
use App\Repository\ProjectRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Security\EmailVerifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

// #[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    private EmailVerifier $emailVerifier;

    public function __construct(EmailVerifier $emailVerifier)
    {
        $this->emailVerifier = $emailVerifier;
    }
    #[IsGranted('ROLE_ADMIN')]

    #[Route('/user/new', name: 'app_admin_user_new', methods: ['GET', 'POST'])]
    public function newUser(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
        EntityManagerInterface $entityManager
    ): Response {
        $user = new User();
        $form = $this->createForm(UserTypeForm::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // encode the plain password
            $user->setMdp(
                $userPasswordHasher->hashPassword(
                    $user,
                    $form->get('plainPassword')->getData()
                )
            );

            // Set current time
            $user->setDateCreation(new \DateTimeImmutable());

            $entityManager->persist($user);
            $entityManager->flush();
        }

        return $this->render('admin/new_user.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/admin', name: 'app_admin_index')]
    public function index(UserRepository $userRepository): Response
    {
        $currentUser = $this->getUser(); // returns User object or null
        $users = $userRepository->findAll();

        return $this->render('admin/index.html.twig', [
            'current_user' => $this->getUser(),
            'users' => $users,
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
    public function Admindashboard(): Response
    {
        // Logique spécifique pour le dashboard admin

        return $this->render('admin/dashboard.html.twig', [
            'controller_name' => 'AdminDashboardController',
            // Autres variables
        ]);
    }
    #[Route('/admin', name: 'app_admin')]
    #[IsGranted('ROLE_ADMIN')]
    /**
     * Display the admin dashboard with a list of users.
     *
     * @param UserRepository $userRepository
     * @return Response
     */
    public function UserList(UserRepository $userRepository): Response
    {
        $users = $userRepository->findAll();
        $currentUser = $this->getUser();
        return $this->render('admin/index.html.twig', [
            'users' => $users,
            'current_user' => $currentUser,
            'activePage' => 'dashboard', // <--- ajouter cette ligne !
        ]);
    }

    #[Route('/admin/projects', name: 'app_admin_projects')]
    public function projects(ProjectRepository $projectRepository): Response
    {
        $projects = $projectRepository->findBy(['owner' => $this->getUser()]);
        return $this->render('admin/projects.html.twig', [
            'projects' => $projects,
            'activePage' => 'projects',
        ]);
    }
// project/view/kanban.html.twig test voir mes-projects? a la place de /admin/projects
 
}
