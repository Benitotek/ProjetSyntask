<?php

namespace App\Controller;

use App\Entity\Project;
use App\Form\ProjectType;
use App\Repository\ProjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Entity\Task;
use App\Entity\TaskList;
use App\Entity\User;
use App\Form\ProjectTypeForm;
use App\Repository\TaskListRepository;
use App\Repository\TaskRepository;
use App\Repository\UserRepository;

#[Route('/project')]
class ProjectController extends AbstractController
{
    private $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }
    //VERSION AVEC 2 BOUTONS (tableau de bord et stats )?
    /**
     * Affiche les projects de l'utilisateur connecté
     */
    #[Route('/mes-projects', name: 'app_mes_projects', methods: ['GET'])]
    public function mesProjects(Request $request, ProjectRepository $projectRepository): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            throw $this->createAccessDeniedException('Vous devez être connecté pour accéder à cette page');
        }

        // Récupérer le statut sélectionné depuis la requête
        $current_statut = $request->query->get('statut', 'tous');

        // Récupérer les projects selon le statut sélectionné
        if ($current_statut !== 'tous') {
            // Si un statut spécifique est demandé
            $projectsAsManager = $projectRepository->findBy([
                'chefproject' => $user,  // CORRECTION: chefproject au lieu de Chefproject
                'statut' => $current_statut
            ]);

            // Récupérer les projects où l'utilisateur est membre avec le statut spécifié
            $projectsAsMember = $projectRepository->findProjectsAsMemberBystatut($user, $current_statut);
        } else {
            // Tous les projects
            $projectsAsManager = $projectRepository->findBy(['chefproject' => $user]); // CORRECTION: chefproject
            $projectsAsMember = $projectRepository->findProjectsAsMember($user);
        }

        // Fusionner les deux collections de projects
        $projects = array_merge($projectsAsManager, $projectsAsMember);

        // Éliminer les doublons potentiels
        $projects = array_unique($projects, SORT_REGULAR);

        return $this->render('project/mes_projects.html.twig', [
            'projects' => $projects,
            'current_statut' => $current_statut,
            'user' => $user,
        ]);
    }
    // Test Version 2-3 date 02/07/2025
    /**
     * Liste de tous les projects (avec filtres selon les permissions)
     */
    #[Route('/', name: 'app_project_index', methods: ['GET'])]
    public function index(ProjectRepository $projectRepository): Response
    {
        $user = $this->getUser();

        if ($this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_DIRECTEUR')) {
            // Afficher tous les projects pour les administrateurs et les directeurs
            $projects = $projectRepository->findAll();
        } elseif ($this->isGranted('ROLE_CHEF_PROJET')) {
            // Afficher uniquement les projects dont l'utilisateur est chef
            $projects = $projectRepository->findByChefDeproject($user);
        } else {
            // Afficher uniquement les projects dont l'utilisateur est membre
            $projects = $projectRepository->findByMembre($user);
        }

        return $this->render('project/index.html.twig', [
            'projects' => $projects,
            'current_statut' => null,
        ]);
    }

    /**
     * Création d'un nouveau project
     */
    #[Route('/new', name: 'app_project_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_CHEF_PROJET')]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $project = new Project();
        $project->setChefproject($this->getUser());
        $project->setDateCreation(new \DateTime());

        $form = $this->createForm(ProjectTypeForm::class, $project);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Créer les colonnes par défaut
            $this->createDefaultTaskLists($project, $entityManager);

            $entityManager->persist($project);
            $entityManager->flush();

            $this->addFlash('success', 'project créé avec succès');
            return $this->redirectToRoute('app_project_index');
        }

        return $this->render('project/new.html.twig', [
            'project' => $project,
            'form' => $form,
        ]);
    }

    /**
     * Affichage des détails d'un project
     */
    #[Route('/{id}', name: 'app_project_show', methods: ['GET'])]
    public function show(Project $project): Response
    {
        // Vérifier que l'utilisateur a le droit de voir ce project
        $this->denyAccessUnlessGranted('PROJECT_VIEW', $project);

        return $this->render('project/show.html.twig', [
            'project' => $project,
        ]);
    }

    /**
     * Modification d'un project
     */
    #[Route('/{id}/edit', name: 'app_project_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Project $project, EntityManagerInterface $entityManager): Response
    {
        // Vérifier que l'utilisateur a le droit de modifier ce project
        $this->denyAccessUnlessGranted('PROJECT_EDIT', $project);

        $form = $this->createForm(ProjectTypeForm::class, $project);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'project modifié avec succès');
            return $this->redirectToRoute('app_project_index');
        }

        return $this->render('project/edit.html.twig', [
            'project' => $project,
            'form' => $form,
        ]);
    }

    /**
     * Suppression d'un project
     */
    #[Route('/{id}/delete', name: 'app_project_delete', methods: ['POST'])]
    public function delete(Request $request, Project $project, EntityManagerInterface $entityManager): Response
    {
        // Vérifier que l'utilisateur a le droit de supprimer ce project
        $this->denyAccessUnlessGranted('PROJECT_DELETE', $project);

        if ($this->isCsrfTokenValid('delete' . $project->getId(), $request->request->get('_token'))) {
            $entityManager->remove($project);
            $entityManager->flush();

            $this->addFlash('success', 'project supprimé avec succès');
        }

        return $this->redirectToRoute('app_project_index');
    }

    /**
     * Affichage du kanban d'un project
     */
    #[Route('/{id}/kanban', name: 'app_project_kanban', methods: ['GET'])]
    public function kanban(Project $project, TaskListRepository $taskListRepository, UserRepository $userRepository): Response
    {
        // Vérifier que l'utilisateur a le droit de voir ce project
        // Utilisation du voter 10/07/2025
        if (!$this->isGranted('PROJECT_VIEW', $project)) {
            throw $this->createAccessDeniedException('Vous n\'avez pas les droits pour voir ce projet');
        }

        // Récupérer les colonnes avec leurs tâches
        $taskLists = $taskListRepository->findByProjectWithTasks($project);

        // Récupérer les utilisateurs pouvant être assignés aux tâches (membres du project)
        $availableUsers = $project->getMembres()->toArray();

        // Ajouter le chef de project s'il n'est pas déjà membre
        if (!in_array($project->getChefproject(), $availableUsers)) {
            $availableUsers[] = $project->getChefproject();
        }

        return $this->render('project/kanban.html.twig', [
            'project' => $project,
            'taskLists' => $taskLists,
            'availableUsers' => $availableUsers
        ]);
    }

    /**
     * Gestion des membres d'un project
     */
    #[Route('/{id}/members', name: 'app_project_members', methods: ['GET', 'POST'])]
    public function manageMembers(
        Request $request,
        Project $project,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager
    ): Response {
        // Vérifier que l'utilisateur a le droit de modifier ce project
        $this->denyAccessUnlessGranted('EDIT', $project);

        if ($request->isMethod('POST')) {
            $memberId = $request->request->get('member_id');
            $action = $request->request->get('action');

            if ($memberId && $action) {
                $user = $userRepository->find($memberId);

                if ($user) {
                    if ($action === 'add' && !$project->getMembres()->contains($user)) {
                        $project->addMembre($user);
                        $this->addFlash('success', $user->getFullName() . ' ajouté au project avec succès');
                    } elseif ($action === 'remove' && $project->getMembres()->contains($user)) {
                        // Vérifier qu'il n'est pas le chef de project
                        if ($project->getChefproject() === $user) {
                            $this->addFlash('error', 'Vous ne pouvez pas retirer le chef de project');
                        } else {
                            $project->removeMembre($user);
                            $this->addFlash('success', $user->getFullName() . ' retiré du project avec succès');
                        }
                    }

                    $entityManager->flush();
                }
            }

            // Si AJAX, retourner une réponse JSON
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['success' => true]);
            }
        }

        // Récupérer tous les utilisateurs qui pourraient être ajoutés au project
        $availableUsers = $userRepository->findUserNotInProject($project);

        return $this->render('project/members.html.twig', [
            'project' => $project,
            'available_users' => $availableUsers,
        ]);
    }

    /**
     * Assigner un chef de project
     */
    #[Route('/{id}/assign-manager/{userId}', name: 'app_project_assign_manager', methods: ['POST'])]
    #[IsGranted('ROLE_DIRECTEUR')]
    public function assignManager(
        Project $project,
        int $userId,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        Request $request
    ): Response {
        $user = $userRepository->find($userId);

        if (!$user) {
            $this->addFlash('error', 'Utilisateur non trouvé');
            return $this->redirectToRoute('app_project_members', ['id' => $project->getId()]);
        }

        // Vérifier que l'utilisateur a le rôle CHEF_project
        if (!in_array('ROLE_CHEF_PROJET', $user->getrole())) {
            $this->addFlash('error', 'L\'utilisateur doit avoir le rôle CHEF_PROJET pour être assigné comme chef de project');
            return $this->redirectToRoute('app_project_members', ['id' => $project->getId()]);
        }

        $project->setChefproject($user);

        // Ajouter automatiquement le chef de project aux membres s'il n'y est pas déjà
        if (!$project->getMembres()->contains($user)) {
            $project->addMembre($user);
        }

        $entityManager->flush();

        $this->addFlash('success', $user->getFullName() . ' a été assigné comme chef de project');

        // Si AJAX, retourner une réponse JSON
        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(['success' => true]);
        }

        return $this->redirectToRoute('app_project_members', ['id' => $project->getId()]);
    }

    /**
     * Crée les colonnes par défaut pour un nouveau project
     */
    private function createDefaultTaskLists(Project $project, EntityManagerInterface $entityManager): void
    {
        $defaultColumns = [
            ['nom' => 'À faire', 'color' => '#007bff'],   // Blue
            ['nom' => 'En cours', 'color' => '#fd7e14'],  // Orange
            ['nom' => 'Terminé', 'color' => '#28a745']    // Green
        ];

        $position = 1;

        foreach ($defaultColumns as $column) {
            $taskList = new TaskList();
            $taskList->setNom($column['nom']);
            // Convert string color to TaskListColor enum
            $taskList->setCouleur(\App\Enum\TaskListColor::from($column['color']));
            $taskList->setProject($project);
            $taskList->setPositionColumn($position++);

            $entityManager->persist($taskList);
        }
    }

    /**
     * Méthode pour vérifier si l'utilisateur a le droit de voir ou modifier un project
     */
    private function canViewProject(Project $project): bool
    {
        // Toujours vérifier si l'utilisateur existe
        $user = $this->getUser();
        if (!$user) {
            return false;
        }

        // Vérification explicite du rôle admin/directeur
        if ($this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_DIRECTEUR')) {
            return true;
        }

        // Vérification du chef de projet 
        if ($project->getChefproject() && $project->getChefproject()->getId() === $user->getUserIdentifier()) {
            return true;
        }

        // Vérification de l'appartenance comme membre
        foreach ($project->getMembres() as $membre) {
            if ($membre->getId() === $user->getUserIdentifier()) {
                return true;
            }
        }

        return false;
    }
}
