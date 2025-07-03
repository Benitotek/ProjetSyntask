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
     * Affiche les projets de l'utilisateur connecté
     */
    #[Route('/mes-projets', name: 'app_mes_projets', methods: ['GET'])]
    public function mesProjects(Request $request, ProjectRepository $projectRepository): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            throw $this->createAccessDeniedException('Vous devez être connecté pour accéder à cette page');
        }

        // Récupérer le statut sélectionné depuis la requête
        $current_statut = $request->query->get('statut', 'tous');
        // Récupérer les projets selon le rôle de l'utilisateur et le statut demandé
        $projects = $projectRepository->findProjectsByUser($user, $current_statut);

        return $this->render('project/mes_projets.html.twig', [
            'projects' => $projects,
            'current_statut' => $current_statut,
            'user' => $user,
        ]);

        // Récupérer les projets selon le statut sélectionné
        if ($current_statut !== 'tous') {
            // Si un statut spécifique est demandé
            $projectsAsManager = $projectRepository->findBy([
                'Chef_Projet' => $user,
                'statut' => $current_statut
            ]);

            // Récupérer les projets où l'utilisateur est membre avec le statut spécifié
            $projectsAsMember = $projectRepository->findProjectsAsMemberBystatut($user, $current_statut);
        } else {
            // Tous les projets
            $projectsAsManager = $projectRepository->findBy(['Chef_Projet' => $user]);
            $projectsAsMember = $projectRepository->findProjectsAsMember($user);
        }

        // Fusionner les deux collections de projets
        $projects = array_merge($projectsAsManager, $projectsAsMember);

        // Éliminer les doublons potentiels
        $projects = array_unique($projects, SORT_REGULAR);

        return $this->render('project/mes_projets.html.twig', [
            'projects' => $projects,
            'current_statut' => $current_statut,
        ]);
    }
    // Test Version 2-3 date 02/07/2025
    /**
     * Liste de tous les projets (avec filtres selon les permissions)
     */
    #[Route('/', name: 'app_projet_index', methods: ['GET'])]
    public function index(ProjectRepository $projectRepository): Response
    {
        $user = $this->getUser();

        if ($this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_DIRECTEUR')) {
            // Afficher tous les projets pour les administrateurs et les directeurs
            $projects = $projectRepository->findAll();
        } elseif ($this->isGranted('ROLE_CHEF_PROJET')) {
            // Afficher uniquement les projets dont l'utilisateur est chef
            $projects = $projectRepository->findByChefDeProjet($user);
        } else {
            // Afficher uniquement les projets dont l'utilisateur est membre
            $projects = $projectRepository->findByMembre($user);
        }

        return $this->render('project/index.html.twig', [
            'projects' => $projects,
        ]);
    }

    /**
     * Création d'un nouveau projet
     */
    #[Route('/new', name: 'app_projet_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_CHEF_PROJET')]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $project = new Project();
        $project->setChef_Projet($this->getUser());
        $project->setDateCreation(new \DateTime());

        $form = $this->createForm(ProjectTypeForm::class, $project);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Créer les colonnes par défaut
            $this->createDefaultTaskLists($project, $entityManager);

            $entityManager->persist($project);
            $entityManager->flush();

            $this->addFlash('success', 'Projet créé avec succès');
            return $this->redirectToRoute('app_projet_index');
        }

        return $this->render('projet/new.html.twig', [
            'project' => $project,
            'form' => $form,
        ]);
    }

    /**
     * Affichage des détails d'un projet
     */
    #[Route('/{id}', name: 'app_projet_show', methods: ['GET'])]
    public function show(Project $project): Response
    {
        // Vérifier que l'utilisateur a le droit de voir ce projet
        $this->denyAccessUnlessGranted('VIEW', $project);

        return $this->render('projet/show.html.twig', [
            'project' => $project,
        ]);
    }

    /**
     * Modification d'un projet
     */
    #[Route('/{id}/edit', name: 'app_project_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Project $project, EntityManagerInterface $entityManager): Response
    {
        // Vérifier que l'utilisateur a le droit de modifier ce projet
        $this->denyAccessUnlessGranted('EDIT', $project);

        $form = $this->createForm(ProjectTypeForm::class, $project);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Projet modifié avec succès');
            return $this->redirectToRoute('app_projet_index');
        }

        return $this->render('projet/edit.html.twig', [
            'project' => $project,
            'form' => $form,
        ]);
    }

    /**
     * Suppression d'un projet
     */
    #[Route('/{id}', name: 'app_projet_delete', methods: ['POST'])]
    public function delete(Request $request, Project $project, EntityManagerInterface $entityManager): Response
    {
        // Vérifier que l'utilisateur a le droit de supprimer ce projet
        $this->denyAccessUnlessGranted('DELETE', $project);

        if ($this->isCsrfTokenValid('delete' . $project->getId(), $request->request->get('_token'))) {
            $entityManager->remove($project);
            $entityManager->flush();

            $this->addFlash('success', 'Projet supprimé avec succès');
        }

        return $this->redirectToRoute('app_projet_index');
    }

    /**
     * Affichage du kanban d'un projet
     */
    #[Route('/{id}/kanban', name: 'app_projet_kanban', methods: ['GET'])]
    public function kanban(Project $project, TaskListRepository $taskListRepository, UserRepository $userRepository): Response
    {
        // Vérifier que l'utilisateur a le droit de voir ce projet
        $this->denyAccessUnlessGranted('VIEW', $project);

        // Récupérer les colonnes avec leurs tâches
        $taskLists = $taskListRepository->findByProjectWithTasks($project);

        // Récupérer les utilisateurs pouvant être assignés aux tâches (membres du projet)
        $availableUsers = $project->getMembres()->toArray();

        // Ajouter le chef de projet s'il n'est pas déjà membre
        if (!in_array($project->getChef_Projet(), $availableUsers)) {
            $availableUsers[] = $project->getChef_Projet();
        }

        return $this->render('projet/kanban.html.twig', [
            'project' => $project,
            'taskLists' => $taskLists,
            'availableUsers' => $availableUsers
        ]);
    }

    /**
     * Gestion des membres d'un projet
     */
    #[Route('/{id}/members', name: 'app_projet_members', methods: ['GET', 'POST'])]
    public function manageMembers(
        Request $request,
        Project $project,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager
    ): Response {
        // Vérifier que l'utilisateur a le droit de modifier ce projet
        $this->denyAccessUnlessGranted('EDIT', $project);

        if ($request->isMethod('POST')) {
            $memberId = $request->request->get('member_id');
            $action = $request->request->get('action');

            if ($memberId && $action) {
                $user = $userRepository->find($memberId);

                if ($user) {
                    if ($action === 'add' && !$project->getMembres()->contains($user)) {
                        $project->addMembre($user);
                        $this->addFlash('success', $user->getFullName() . ' ajouté au projet avec succès');
                    } elseif ($action === 'remove' && $project->getMembres()->contains($user)) {
                        // Vérifier qu'il n'est pas le chef de projet
                        if ($project->getChef_Projet() === $user) {
                            $this->addFlash('error', 'Vous ne pouvez pas retirer le chef de projet');
                        } else {
                            $project->removeMembre($user);
                            $this->addFlash('success', $user->getFullName() . ' retiré du projet avec succès');
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

        // Récupérer tous les utilisateurs qui pourraient être ajoutés au projet
        $availableUsers = $userRepository->findUserNotInProject($project);

        return $this->render('projet/members.html.twig', [
            'project' => $project,
            'available_users' => $availableUsers,
        ]);
    }

    /**
     * Assigner un chef de projet
     */
    #[Route('/{id}/assign-manager/{userId}', name: 'app_projet_assign_manager', methods: ['POST'])]
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
            return $this->redirectToRoute('app_projet_members', ['id' => $project->getId()]);
        }

        // Vérifier que l'utilisateur a le rôle CHEF_PROJET
        if (!in_array('ROLE_CHEF_PROJET', $user->getRoles())) {
            $this->addFlash('error', 'L\'utilisateur doit avoir le rôle CHEF_PROJET pour être assigné comme chef de projet');
            return $this->redirectToRoute('app_projet_members', ['id' => $project->getId()]);
        }

        $project->setChef_Projet($user);

        // Ajouter automatiquement le chef de projet aux membres s'il n'y est pas déjà
        if (!$project->getMembres()->contains($user)) {
            $project->addMembre($user);
        }

        $entityManager->flush();

        $this->addFlash('success', $user->getFullName() . ' a été assigné comme chef de projet');

        // Si AJAX, retourner une réponse JSON
        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(['success' => true]);
        }

        return $this->redirectToRoute('app_projet_members', ['id' => $project->getId()]);
    }

    /**
     * Crée les colonnes par défaut pour un nouveau projet
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
     * Méthode pour vérifier si l'utilisateur a le droit de voir ou modifier un projet
     */
    private function canAccessProject(Project $project): bool
    {
        $user = $this->getUser();

        if (!$user) {
            return false;
        }

        // Les administrateurs et directeurs peuvent tout voir
        if ($this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_DIRECTEUR')) {
            return true;
        }

        // Les chefs de projet peuvent voir les projets qu'ils dirigent
        if ($this->isGranted('ROLE_CHEF_PROJET') && $project->getChef_Projet() === $user) {
            return true;
        }

        // Les membres du projet peuvent voir le projet
        return $project->getMembres()->contains($user);
    }
}

// Version1 avec  les bouton marche pas pour stats et le Tableau de bord Test New Version

    // CETTE PARTIE MARCHE MAIS N'EST PAS COMPATIBLE AVEC 2 BOUTONS(tableau de bord et stats )
    // #[Route('/mes-projets', name: 'app_mes_projets', methods: ['GET'])]
    // #[IsGranted('ROLE_CHEF_PROJET')]
    // public function myProjects(ProjectRepository $projectRepository, Request $request): Response
    // {
    //     /** @var User $user */
    //     $user = $this->getUser();

    //     if (!$user) {
    //         throw $this->createAccessDeniedException();
    //     }
    //     // Récupérer le statut courant depuis la requête, ou utiliser une valeur par défaut
    //     $current_statut = $request->query->get('statut', 'tous');
    //     $projects = $projectRepository->findProjectsByUser($user, $current_statut,);

    //     return $this->render('project/mes_projets.html.twig', [
    //         'projects' => $projects,
    //         'current_statut' => $current_statut,
    //         'user' => $user,
    //     ]);
    // }

    // #[Route('/', name: 'app_projet_index', methods: ['GET'])]
    // public function index(ProjectRepository $projectRepository): Response
    // {
    //     $user = $this->getUser();

    //     if ($this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_DIRECTEUR')) {
    //         // Afficher tous les projets pour les administrateurs et les directeurs
    //         $projects = $projectRepository->findAll();
    //     } elseif ($this->isGranted('ROLE_CHEF_PROJET')) {
    //         // Afficher uniquement les projets dont l'utilisateur est chef
    //         $projects = $projectRepository->findByChef_Projet($user, []);
    //     } else {
    //         // Afficher uniquement les projets dont l'utilisateur est membre
    //         $projects = $projectRepository->findByMembre($user);
    //     }

    //     return $this->render('project/index.html.twig', [
    //         'projects' => $projects,
    //     ]);
    // }

    // #[Route('/new', name: 'app_projet_new', methods: ['GET', 'POST'])]
    // #[IsGranted('ROLE_CHEF_PROJET')]
    // public function new(Request $request, EntityManagerInterface $entityManager): Response
    // {
    //     $project = new Project();
    //     $project->setChef_Projet($this->getUser());
    //     $project->setDateCreation(new \DateTime());

    //     $form = $this->createForm(ProjectTypeForm::class, $project);
    //     $form->handleRequest($request);

    //     if ($form->isSubmitted() && $form->isValid()) {
    //         // Créer les colonnes par défaut
    //         $this->createDefaultTaskLists($project, $entityManager);

    //         $entityManager->persist($project);
    //         $entityManager->flush();

    //         return $this->redirectToRoute('app_projet_index', [], Response::HTTP_SEE_OTHER);
    //     }

    //     return $this->render('projet/new.html.twig', [
    //         'project' => $project,
    //         'form' => $form,
    //     ]);
    // }

    // #[Route('/{id}', name: 'app_projet_show', methods: ['GET'])]
    // public function show(Project $project): Response
    // {
    //     // Vérifier que l'utilisateur a le droit de voir ce projet
    //     $this->denyAccessUnlessGranted('VIEW', $project);

    //     return $this->render('projet/show.html.twig', [
    //         'project' => $project,
    //     ]);
    // }

//     #[Route('/{id}/edit', name: 'app_project_edit', methods: ['GET', 'POST'])]
//     public function edit(Request $request, Project $project, EntityManagerInterface $entityManager): Response
//     {
//         // Vérifier que l'utilisateur a le droit de modifier ce projet
//         $this->denyAccessUnlessGranted('EDIT', $project);

//         $form = $this->createForm(ProjectTypeForm::class, $project);
//         $form->handleRequest($request);

//         if ($form->isSubmitted() && $form->isValid()) {
//             $entityManager->flush();

//             return $this->redirectToRoute('app_projet_index', [], Response::HTTP_SEE_OTHER);
//         }

//         return $this->render('projet/edit.html.twig', [
//             'project' => $project,
//             'form' => $form,
//         ]);
//     }

//     #[Route('/{id}', name: 'app_projet_delete', methods: ['POST'])]
//     public function delete(Request $request, Project $project, EntityManagerInterface $entityManager): Response
//     {
//         // Vérifier que l'utilisateur a le droit de supprimer ce projet
//         $this->denyAccessUnlessGranted('DELETE', $project);

//         if ($this->isCsrfTokenValid('delete' . $project->getId(), $request->request->get('_token'))) {
//             $entityManager->remove($project);
//             $entityManager->flush();
//         }

//         return $this->redirectToRoute('app_projet_index', [], Response::HTTP_SEE_OTHER);
//     }

//     private function createDefaultTaskLists(Project $project, EntityManagerInterface $entityManager): void
//     {
//         $defaultLists = [
//             ['nom' => 'À faire', 'position' => 1],
//             ['nom' => 'En cours', 'position' => 2],
//             ['nom' => 'Terminé', 'position' => 3],
//         ];

//         foreach ($defaultLists as $listData) {
//             $taskList = new TaskList();
//             $taskList->setNom($listData['nom']);
//             $taskList->setPositionColumn($listData['position']);
//             $taskList->setProject($project);
//             $entityManager->persist($taskList);
//         }
//     }
// }

// #[Route('/projet/view')]
// // #[IsGranted('ROLE_EMPLOYE')]
// class ProjectViewController extends AbstractController
// {
//     /**
//      * Vue Kanban d'un projet
//      */
//     #[Route('/{id}/kanban', name: 'app_projet_view_kanban', methods: ['GET'])]
//     public function ProjectKanban(
//         Project $project,
//         TaskListRepository $taskListRepository
//     ): Response {
//         // Vérifier les permissions (à remplacer par un voter plus tard)
//         $this->denyAccessUnlessGranted('VIEW', $project);

//         // Récupérer les colonnes avec leurs tâches
//         $taskLists = $taskListRepository->findByProjectWithTasks($project);

//         // Mettre à jour automatiquement les couleurs
//         $taskListRepository->updateAutoColorsForProject($project);

//         return $this->render('projet/view/kanban.html.twig', [
//             'project' => $project,
//             'taskLists' => $taskLists,
//         ]);
//     }

//     /**
//      * Vue globale des tâches d'un projet
//      */
//     #[Route('/{id}/tasks', name: 'app_projet_view_tasks', methods: ['GET'])]
//     public function allTasks(
//         Project $project,
//         Request $request,
//         TaskRepository $taskRepository
//     ): Response {
//         // Vérifier les permissions
//         $this->denyAccessUnlessGranted('VIEW', $project);

//         // Filtres
//         $statut = $request->query->get('statut');
//         $priority = $request->query->get('priority');
//         $assignee = $request->query->get('assignee');

//         // Récupérer toutes les tâches du projet
//         $tasks = $taskRepository->findByProject($project);

//         // Appliquer les filtres
//         if ($statut) {
//             $tasks = array_filter($tasks, function ($task) use ($statut) {
//                 return $task->getStatut() === $statut;
//             });
//         }

//         if ($priority) {
//             $tasks = array_filter($tasks, function ($task) use ($priority) {
//                 return $task->getPriorite() === $priority;
//             });
//         }

//         if ($assignee) {
//             $tasks = array_filter($tasks, function ($task) use ($assignee) {
//                 return $task->getAssignedUser() && $task->getAssignedUser()->getId() == $assignee;
//             });
//         }

//         return $this->render('projet/view/all_tasks.html.twig', [
//             'project' => $project,
//             'tasks' => $tasks,
//             'filters' => [
//                 'statut' => $statut,
//                 'priority' => $priority,
//                 'assignee' => $assignee,
//             ],
//         ]);
//     }

//     /**
//      * API pour réorganiser les tâches (AJAX)
//      */
//     #[Route('/{id}/reorder-tasks', name: 'app_projet_reorder_tasks', methods: ['POST'])]
//     public function reorderTasks(
//         Project $project,
//         Request $request,
//         TaskRepository $taskRepository,
//         EntityManagerInterface $entityManager
//     ): Response {
//         // Vérifier les permissions
//         $this->denyAccessUnlessGranted('EDIT', $project);

//         $data = json_decode($request->getContent(), true);

//         if (isset($data['taskId'], $data['newColumnId'], $data['newPosition'])) {
//             $task = $taskRepository->find($data['taskId']);
//             $column = $entityManager->getRepository(TaskList::class)->find($data['newColumnId']);

//             if ($task && $column && $task->getProject() === $project && $column->getProject() === $project) {
//                 $taskRepository->moveTaskToColumn($task, $column, $data['newPosition']);

//                 return $this->json(['success' => true]);
//             }
//         }

//         return $this->json(['success' => false, 'message' => 'Données invalides'], 400);
//     }

//     /**
//      * Filtre les tâches par statut
//      */
//     #[Route('/{id}/filter-by-statut/{statut}', name: 'app_projet_filter_by_statut')]
//     public function filterBystatut(
//         Project $project,
//         string $statut,
//         TaskRepository $taskRepository
//     ): Response {
//         // Vérifier les permissions
//         $this->denyAccessUnlessGranted('VIEW', $project);

//         $tasks = $taskRepository->findByProject($project);
//         $filteredTasks = array_filter($tasks, function ($task) use ($statut) {
//             return $task->getStatut() === $statut;
//         });

//         return $this->render('projet/view/all_tasks.html.twig', [
//             'project' => $project,
//             'tasks' => $filteredTasks,
//             'filters' => [
//                 'statut' => $statut,
//             ],
//         ]);
//     }

//     /**
//      * Filtre les tâches par priorité
//      */
//     #[Route('/{id}/filter-by-priority/{priority}', name: 'app_project_filter_by_priority')]
//     public function filterByPriority(
//         Project $project,
//         string $priority,
//         TaskRepository $taskRepository
//     ): Response {
//         // Vérifier les permissions
//         $this->denyAccessUnlessGranted('VIEW', $project);

//         $tasks = $taskRepository->findByProject($project);
//         $filteredTasks = array_filter($tasks, function ($task) use ($priority) {
//             return $task->getPriorite() === $priority;
//         });

//         return $this->render('project/view/all_tasks.html.twig', [
//             'project' => $project,
//             'tasks' => $filteredTasks,
//             'filters' => [
//                 'priority' => $priority,
//             ],
//         ]);
//     }

//     /**
//      * Filtre les tâches par assigné
//      */
//     #[Route('/{id}/filter-by-user/{userId}', name: 'app_project_filter_by_user')]
//     public function filterByUser(
//         Project $project,
//         int $userId,
//         TaskRepository $taskRepository,
//         EntityManagerInterface $entityManager
//     ): Response {
//         // Vérifier les permissions
//         $this->denyAccessUnlessGranted('VIEW', $project);

//         $user = $entityManager->getRepository(User::class)->find($userId);

//         if (!$user) {
//             throw $this->createNotFoundException('Utilisateur non trouvé');
//         }

//         $tasks = $taskRepository->findByProject($project);
//         $filteredTasks = array_filter($tasks, function ($task) use ($user) {
//             return $task->getAssignedUser() === $user;
//         });

//         return $this->render('project/view/all_tasks.html.twig', [
//             'project' => $project,
//             'tasks' => $filteredTasks,
//             'filters' => [
//                 'assignee' => $userId,
//             ],
//         ]);
//     }
// }
