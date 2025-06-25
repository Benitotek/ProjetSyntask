<?php

namespace App\Controller;

use App\Entity\Project;
use App\Entity\TaskList;
use App\Entity\User;
use App\Form\ProjectTypeForm;
use App\Repository\ProjectRepository;
use App\Repository\UserRepository;
use App\Repository\TaskListRepository;
use App\Repository\TaskRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/project/view')]
#[IsGranted('ROLE_EMPLOYE')]
class ProjectViewController extends AbstractController
{
    /**
     * Vue Kanban d'un projet
     */
    #[Route('/{id}/kanban', name: 'app_project_view_kanban', methods: ['GET'])]
    public function kanban(
        Project $project,
        TaskListRepository $taskListRepository
    ): Response {
        // Vérifier les permissions (à remplacer par un voter plus tard)
        $this->denyAccessUnlessGranted('VIEW', $project);

        // Récupérer les colonnes avec leurs tâches
        $taskLists = $taskListRepository->findByProjectWithTasks($project);

        // Mettre à jour automatiquement les couleurs
        $taskListRepository->updateAutoColorsForProject($project);

        return $this->render('project/view/kanban.html.twig', [
            'project' => $project,
            'taskLists' => $taskLists,
        ]);
    }

    /**
     * Vue globale des tâches d'un projet
     */
    #[Route('/{id}/tasks', name: 'app_project_view_tasks', methods: ['GET'])]
    public function allTasks(
        Project $project,
        Request $request,
        TaskRepository $taskRepository
    ): Response {
        // Vérifier les permissions
        $this->denyAccessUnlessGranted('VIEW', $project);

        // Filtres
        $status = $request->query->get('status');
        $priority = $request->query->get('priority');
        $assignee = $request->query->get('assignee');

        // Récupérer toutes les tâches du projet
        $tasks = $taskRepository->findByProject($project);

        // Appliquer les filtres
        if ($status) {
            $tasks = array_filter($tasks, function($task) use ($status) {
                return $task->getStatut() === $status;
            });
        }

        if ($priority) {
            $tasks = array_filter($tasks, function($task) use ($priority) {
                return $task->getPriorite() === $priority;
            });
        }

        if ($assignee) {
            $tasks = array_filter($tasks, function($task) use ($assignee) {
                return $task->getAssignedUser() && $task->getAssignedUser()->getId() == $assignee;
            });
        }

        return $this->render('project/view/all_tasks.html.twig', [
            'project' => $project,
            'tasks' => $tasks,
            'filters' => [
                'status' => $status,
                'priority' => $priority,
                'assignee' => $assignee,
            ],
        ]);
    }

    /**
     * API pour réorganiser les tâches (AJAX)
     */
    #[Route('/{id}/reorder-tasks', name: 'app_project_reorder_tasks', methods: ['POST'])]
    public function reorderTasks(
        Project $project,
        Request $request,
        TaskRepository $taskRepository,
        EntityManagerInterface $entityManager
    ): Response {
        // Vérifier les permissions
        $this->denyAccessUnlessGranted('EDIT', $project);

        $data = json_decode($request->getContent(), true);
        
        if (isset($data['taskId'], $data['newColumnId'], $data['newPosition'])) {
            $task = $taskRepository->find($data['taskId']);
            $column = $entityManager->getRepository(TaskList::class)->find($data['newColumnId']);
            
            if ($task && $column && $task->getProject() === $project && $column->getProject() === $project) {
                $taskRepository->moveTaskToColumn($task, $column, $data['newPosition']);
                
                return $this->json(['success' => true]);
            }
        }
        
        return $this->json(['success' => false, 'message' => 'Données invalides'], 400);
    }

    /**
     * Filtre les tâches par statut
     */
    #[Route('/{id}/filter-by-status/{status}', name: 'app_project_filter_by_status')]
    public function filterByStatus(
        Project $project, 
        string $status, 
        TaskRepository $taskRepository
    ): Response {
        // Vérifier les permissions
        $this->denyAccessUnlessGranted('VIEW', $project);

        $tasks = $taskRepository->findByProject($project);
        $filteredTasks = array_filter($tasks, function($task) use ($status) {
            return $task->getStatut() === $status;
        });

        return $this->render('project/view/all_tasks.html.twig', [
            'project' => $project,
            'tasks' => $filteredTasks,
            'filters' => [
                'status' => $status,
            ],
        ]);
    }

    /**
     * Filtre les tâches par priorité
     */
    #[Route('/{id}/filter-by-priority/{priority}', name: 'app_project_filter_by_priority')]
    public function filterByPriority(
        Project $project, 
        string $priority, 
        TaskRepository $taskRepository
    ): Response {
        // Vérifier les permissions
        $this->denyAccessUnlessGranted('VIEW', $project);

        $tasks = $taskRepository->findByProject($project);
        $filteredTasks = array_filter($tasks, function($task) use ($priority) {
            return $task->getPriorite() === $priority;
        });

        return $this->render('project/view/all_tasks.html.twig', [
            'project' => $project,
            'tasks' => $filteredTasks,
            'filters' => [
                'priority' => $priority,
            ],
        ]);
    }

    /**
     * Filtre les tâches par assigné
     */
    #[Route('/{id}/filter-by-user/{userId}', name: 'app_project_filter_by_user')]
    public function filterByUser(
        Project $project, 
        int $userId, 
        TaskRepository $taskRepository,
        EntityManagerInterface $entityManager
    ): Response {
        // Vérifier les permissions
        $this->denyAccessUnlessGranted('VIEW', $project);

        $user = $entityManager->getRepository(User::class)->find($userId);
        
        if (!$user) {
            throw $this->createNotFoundException('Utilisateur non trouvé');
        }

        $tasks = $taskRepository->findByProject($project);
        $filteredTasks = array_filter($tasks, function($task) use ($user) {
            return $task->getAssignedUser() === $user;
        });

        return $this->render('project/view/all_tasks.html.twig', [
            'project' => $project,
            'tasks' => $filteredTasks,
            'filters' => [
                'assignee' => $userId,
            ],
        ]);
    }
}
// #[Route('/projects')]
// #[IsGranted('ROLE_EMPLOYE')]
// class ProjectController extends AbstractController
// {
//     #[Route('/projects', name: 'app_project_index', methods: ['GET'])]
//     public function index(Request $request, ProjectRepository $projectRepository): Response
//     {
//         $user = $this->getUser();
//         $status = $request->query->get('status');

//         if ($this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_DIRECTEUR')) {
//             $projects = $status ? $projectRepository->findBy(['statut' => $status]) : $projectRepository->findAll();
//         } else {
//             if (!$user) {
//                 throw $this->createAccessDeniedException();
//             }

//             // Utilisez findBy avec des critères simples
//             $criteria = [];
//             if (method_exists($projectRepository, 'findByAssignedUser')) {
//                 $projects = $projectRepository->findByAssignedUser($user);
//             } else {
//                 // Fallback : récupérer les projets où l'utilisateur est chef de projet
//                 $projects = $projectRepository->findBy(['chefDeProjet' => $user]);
//             }
//         }

//         return $this->render('project/index.html.twig', [
//             'projects' => $projects,
//             'current_status' => $status,
//         ]);
//     }

//     #[Route('project/newproject', name: 'app_project_new', methods: ['GET', 'POST'])]
//     #[IsGranted('ROLE_DIRECTEUR')]
//     public function new(Request $request, EntityManagerInterface $entityManager): Response
//     {
//         $project = new Project();
//         $form = $this->createForm(ProjectTypeForm::class, $project);
//         $form->handleRequest($request);

//         if ($form->isSubmitted() && $form->isValid()) {
//             $entityManager->persist($project);

//             // Créer les colonnes par défaut
//             $defaultColumns = ['À faire', 'En cours', 'Terminé'];
//             foreach ($defaultColumns as $index => $columnName) {
//                 $taskList = new TaskList();
//                 $taskList->setNom($columnName);
//                 $taskList->setPositionColumn($index);
//                 $taskList->setProject($project);
//                 $entityManager->persist($taskList);
//             }

//             $entityManager->flush();

//             $this->addFlash('success', 'Projet créé avec succès');
//             return $this->redirectToRoute('projects');
//         }

//         return $this->render('project/new.html.twig', [
//             'project' => $project,
//             'form' => $form->createView(),
//         ]);
//     }

//     #[Route('/project/{id}', name: 'app_project_show', methods: ['GET'], requirements: ['id' => '\d+'])]
//     public function show(Project $project): Response
//     {
//         /** @var User|null $user */
//         $user = $this->getUser();
//         if (
//             !$this->isGranted('ROLE_ADMIN') &&
//             !$this->isGranted('ROLE_DIRECTEUR') &&
//             $project->getChef_Projet() !== $user &&
//             !$project->getMembres()->contains($user)
//         ) {
//             throw $this->createAccessDeniedException();
//         }

//         return $this->render('project/show.html.twig', [
//             'project' => $project,
//         ]);
//     }

//     #[Route('/project/{id}/save', name: 'app_project_save', methods: ['POST'])]
//     public function save(Request $request, Project $project, EntityManagerInterface $entityManager): Response
//     {
//         // Logique de sauvegarde du projet
//         $projectData = $request->request->get('project');

//         if ($projectData) {
//             if (isset($projectData['titre'])) {
//                 $project->settitre($projectData['titre']);
//             }
//             if (isset($projectData['status'])) {
//                 $project->setStatut($projectData['status']);
//             }
//             if (isset($projectData['description'])) {
//                 $project->setDescription($projectData['description']);
//             }

//             $entityManager->flush();
//             $this->addFlash('success', 'Projet sauvegardé avec succès');
//         }

//         return $this->redirectToRoute('project_show', ['id' => $project->getId()]);
//     }

//     #[Route('/project/{id}/update', name: 'app_project_update', methods: ['POST'])]
//     public function update(Request $request, Project $project, EntityManagerInterface $entityManager): Response
//     {
//         return $this->save($request, $project, $entityManager);
//     }

//     #[Route('/mes-projets', name: 'app_mes_projets', methods: ['GET'])]
//     public function mesProjets(ProjectRepository $projectRepository): Response
//     {
//         $user = $this->getUser();

//         if (method_exists($projectRepository, 'findByAssignedUser')) {
//             $projects = $projectRepository->findByAssignedUser($user);
//         } else {
//             $projects = $projectRepository->findBy(['chefDeProjet' => $user]);
//         }

//         return $this->render('project/mes_projets.html.twig', [
//             'projects' => $projects,
//         ]);
//     }
//     /**
//      * Vue Kanban d'un projet
//      */
//     #[Route('/project/{id}/kanban', name: 'app_project_kanban', methods: ['GET'], requirements: ['id' => '\d+'])]
//     public function kanban(
//         Project $project,
//         TaskListRepository $taskListRepository
//     ): Response {
//         // Vérifier les permissions
//         $this->denyAccessUnlessGranted('VIEW', $project);

//         // Récupérer les colonnes avec leurs tâches
//         $taskLists = $taskListRepository->findByProjectWithTasks($project);

//         // Mettre à jour automatiquement les couleurs
//         $taskListRepository->updateAutoColorsForProject($project);

//         return $this->render('project/kanban.html.twig', [
//             'project' => $project,
//             'taskLists' => $taskLists,
//         ]);
//     }
// }



// VersionTestProjets

// #[Route('/projects')]
// #[IsGranted('ROLE_EMPLOYE')]
// class ProjectController extends AbstractController
// {
//     #[Route('/project', name: 'app_project_index', methods: ['GET'])]
//     public function Projetindex(Request $request, ProjectRepository $projectRepository): Response
//     {
//         $user = $this->getUser();
//         $status = $request->query->get('status');
//         // Récupération des projets
//         // Pour tous les employés, afficher tous les projets
//         $projects = $projectRepository->findAll();

//         // OU si vous souhaitez filtrer les projets en fonction du rôle
//         // Par exemple, si les chefs de projet peuvent voir tous les projets
//         // mais les employés ordinaires ne voient que leurs projets

//         if ($this->isGranted('ROLE_CHEF_PROJET') || $this->isGranted('ROLE_DIRECTEUR') || $this->isGranted('ROLE_ADMIN')) {
//             $projects = $projectRepository->findAll();
//         } else {
//             // Supposons que vous avez une relation entre Project et User
//             $projects = $projectRepository->findByUser($this->getUser());
//         }


//         return $this->render('project/index.html.twig', [
//             'projects' => $projects,
//         ]);
//         if ($this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_DIRECTEUR')) {
//             // Utilise les méthodes qui existent vraiment
//             $projects = $status ? $projectRepository->findByStatus(is_array($status) ? $status : [$status]) : $projectRepository->findAll();
//         } else {
//             // Utilise les méthodes existantes
//             if (!$user) {
//                 throw $this->createAccessDeniedException();
//             }
//             // S'assurer que $user est bien du type User
//             $projects = $projectRepository->findByAssignedUser($user);
//         }

//         return $this->render('project/index.html.twig', [
//             'projects' => $projects,
//             'current_status' => $status,
//         ]);
//     }

//     #[Route('/newproject', name: 'project_new', methods: ['GET', 'POST'])]
//     #[IsGranted('ROLE_DIRECTEUR')]
//     public function new(Request $request, EntityManagerInterface $entityManager): Response
//     {
//         $project = new Project();
//         $form = $this->createForm(ProjectTypeForm::class, $project);
//         $form->handleRequest($request);

//         if ($form->isSubmitted() && $form->isValid()) {
//             $entityManager->persist($project);

//             // Créer les colonnes par défaut
//             $defaultColumns = ['À faire', 'En cours', 'Terminé'];
//             foreach ($defaultColumns as $index => $columnName) {
//                 $taskList = new TaskList();
//                 $taskList->setNom($columnName);
//                 $taskList->setPositionColumn($index);
//                 $taskList->setProject($project);
//                 $entityManager->persist($taskList);
//             }

//             $entityManager->flush();

//             $this->addFlash('success', 'Projet créé avec succès');
//             return $this->redirectToRoute('project_index');
//         }

//         return $this->render('project/new.html.twig', [
//             'project' => $project,
//             'form' => $form->createView(),
//         ]);
//     }

//     #[Route('/project/{id}', name: 'app_project_show', methods: ['GET'])]
//     public function show(Project $project): Response
//     {
//         // Vérification simple en attendant les voters
//         /** @var User|null $user */
//         $user = $this->getUser();
//         if (
//             !$this->isGranted('ROLE_ADMIN') &&
//             !$this->isGranted('ROLE_DIRECTEUR') &&
//             $project->getChefDeProjet() !== $user &&
//             !$project->getMembres()->contains($user)
//         ) {
//             throw $this->createAccessDeniedException();
//         }

//         return $this->render('project/show.html.twig', [
//             'project' => $project,
//         ]);
//     }
//     #[Route('/project/kanban', name: 'app_project_kanban', methods: ['GET'])]
//     public function kanban(Project $project): Response
//     {
//         // Même vérification que show
//         /** @var User|null $user */
//         $user = $this->getUser();
//         if (
//             !$this->isGranted('ROLE_ADMIN') &&
//             !$this->isGranted('ROLE_DIRECTEUR') &&
//             $project->getChefDeProjet() !== $user &&
//             !$project->getMembres()->contains($user)
//         ) {
//             throw $this->createAccessDeniedException();
//         }

//         return $this->render('project/kanban.html.twig', [
//             'project' => $project,
//         ]);
//     }

//     public function edit(Request $request, Project $project, EntityManagerInterface $entityManager): Response
//     {
//         // Vérification édition
//         /** @var User|null $user */
//         $user = $this->getUser();
//         if (
//             !$this->isGranted('ROLE_ADMIN') &&
//             !$this->isGranted('ROLE_DIRECTEUR') &&
//             $project->getChefDeProjet() !== $user
//         ) {
//             throw $this->createAccessDeniedException();
//         }

//         $form = $this->createForm(ProjectTypeForm::class, $project);
//         $form->handleRequest($request);

//         if ($form->isSubmitted() && $form->isValid()) {
//             $entityManager->flush();

//             $this->addFlash('success', 'Projet modifié avec succès');
//             return $this->redirectToRoute('project_show', ['id' => $project->getId()]);
//         }

//         return $this->render('project/edit.html.twig', [
//             'project' => $project,
//             'form' => $form->createView(),
//         ]);
//     }

//     public function archive(Request $request, Project $project, EntityManagerInterface $entityManager): Response
//     {
//         /** @var User|null $user */
//         $user = $this->getUser();
//         if (
//             !$this->isGranted('ROLE_ADMIN') &&
//             !$this->isGranted('ROLE_DIRECTEUR') &&
//             $project->getChefDeProjet() !== $user
//         ) {
//             throw $this->createAccessDeniedException();
//         }

//         if ($this->isCsrfTokenValid('archive' . $project->getId(), $request->request->get('_token'))) {
//             $project->setEstArchive(true);
//             $entityManager->flush();
//             $this->addFlash('success', 'Projet archivé');
//         }

//         return $this->redirectToRoute('project_index');
//     }

//     public function assignUser(Request $request, Project $project, UserRepository $userRepository, EntityManagerInterface $entityManager): Response
//     {
//         /** @var User|null $currentUser */
//         $currentUser = $this->getUser();
//         if (
//             !$this->isGranted('ROLE_ADMIN') &&
//             !$this->isGranted('ROLE_DIRECTEUR') &&
//             $project->getChefDeProjet() !== $currentUser
//         ) {
//             throw $this->createAccessDeniedException();
//         }

//         $userId = $request->request->get('user_id');
//         $assignedUser = $userRepository->find($userId);

//         if ($assignedUser && !$project->getMembres()->contains($assignedUser)) {
//             $project->addMembre($assignedUser);
//             $entityManager->flush();
//             $this->addFlash('success', 'Utilisateur assigné au projet');
//         }

//         return $this->redirectToRoute('project_show', ['id' => $project->getId()]);
//     }
// }
