<?php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Entity\Project;
use App\Entity\Task;
use App\Entity\TaskList;
use App\Entity\User;
use App\Enum\TaskPriority;
use App\Enum\TaskStatut;
use App\Form\TaskType;
use App\Repository\TaskListRepository;
use App\Repository\TaskRepository;
use App\Repository\UserRepository;
use App\Service\ActivityLogger;
use Knp\Component\Pager\PaginatorInterface;

// Version 2-3 Test du 02/07/2025
// Debut version 3 09/07/2025

class TaskController extends AbstractController
{
    /**
     * Liste des tâches
     */
    #[Route('/task', name: 'app_task_index', methods: ['GET'])]
    public function index(TaskRepository $taskRepository, TaskListRepository $taskListRepository): Response
    {
        $user = $this->getUser();

        if ($this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_DIRECTEUR')) {
            $tasks = $taskRepository->findAll();
        } else {
            $tasks = $taskRepository->findByAssignedUser($user);
        }

        // Récupérer une liste de tâches par défaut (exemple : la première trouvée)
        $taskList = $taskListRepository->findOneBy([]);

        return $this->render('task/index.html.twig', [
            'tasks' => $tasks,
            'user' => $user,
            'taskList' => $taskList,
        ]);
    }
    //     #[Route('/task', name: 'app_task_index', methods: ['GET'])]
    //     public function index(TaskRepository $taskRepository): Response
    //     {
    //         // Pour admin/directeur, tout voir. Sinon, adapter la logique selon le rôle.
    //         $user = $this->getUser();
    //         if ($this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_DIRECTEUR')) {
    //             $tasks = $taskRepository->findAll();
    //         } else {
    //             $tasks = $taskRepository->findByAssignedUser($user);
    //         }
    //  // Ajoutez une tâche individuelle si nécessaire
    //  $task = $taskRepository->findOneBy(['statut' => 'En cours']);

    //         return $this->render('task/index.html.twig', [
    //             'tasks' => $tasks,
    //             'task' => $task,
    //             // Passer l'utilisateur pour les permissions
    //             'user' => $user,
    //             'task' => $task,
    //         ])->setStatusCode(Response::HTTP_OK);
    //     }
    /**
     * Liste des tâches d'un project
     */
    #[Route('/project/{id}/tasks', name: 'app_task_project_tasks', methods: ['GET'])]
    #[IsGranted('ROLE_EMPLOYE')]
    public function ProjetTasks(Project $project, TaskRepository $taskRepository): Response
    {
        // Vérifier que l'utilisateur a le droit de voir ce project
        if (!$this->canViewProject($project)) {
            throw $this->createAccessDeniedException('Vous n\'avez pas les droits pour voir ce project');
        }
        // Récupérer les tâches du project
        $tasks = $taskRepository->findBy(['project' => $project], ['position' => 'ASC']);
        // Ajoutez une tâche individuelle si nécessaire
        $task = $taskRepository->findOneBy(['someCondition' => 'value']);
        return $this->render('task/project_tasks.html.twig', [
            'project' => $project,
            'tasks' => $tasks,
            'task' => $task,
        ])->setStatusCode(Response::HTTP_OK);
    }

    /**
     * Liste des tâches assignées à l'utilisateur courant
     */
    #[Route('/mes-taches', name: 'app_task_my_tasks', methods: ['GET'])]
    #[IsGranted('ROLE_EMPLOYE')]
    public function myTasks(TaskRepository $taskRepository, EntityManagerInterface $entityManager, PaginatorInterface $paginator, Request $request): Response
    {
        $user = $this->getUser();

        $queryBuilder = $entityManager->createQueryBuilder()
            ->select('t', 'p')
            ->from(Task::class, 't')
            ->leftJoin('t.project', 'p')
            ->where('t.assignedUser = :user')
            ->setParameter('user', $user)
            ->orderBy('t.dateButoir', 'ASC');

        // Pagination
        $pagination = $paginator->paginate(
            $queryBuilder, // La requête ou le query builder
            $request->query->getInt('page', 1), // La page demandée
            10 // Nombre d'éléments par page
        );

        return $this->render('task/my_tasks.html.twig', [
            'pagination' => $pagination,
        ]);
    }

    #[Route('/task/{id}/complete', name: 'app_task_mark_completed')]
    public function markCompleted(Task $task, EntityManagerInterface $em, ActivityLogger $logger): Response
    {
        // Logique pour marquer la tâche comme terminée
        $task->setStatut(TaskStatut::TERMINER);
        $em->flush();

        // Optionnel : log de l'activité
        $logger->logActivity($this->getUser(), $task->getTitle(), $task->getId(), $task->getProject());

        return $this->redirectToRoute('app_task_show', ['id' => $task->getId()]);
    }
    /**
     * Création d'une nouvelle tâche
     */
    #[Route('/new/{taskListId}', name: 'app_task_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        int $taskListId,
        EntityManagerInterface $entityManager,
        ActivityLogger $activityLogger
    ): Response {
        $task = new Task();
        $form = $this->createForm(TaskType::class, $task);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($task);
            $entityManager->flush();

            // Enregistrer l'activité de création de tâche
            $activityLogger->logTaskCreation(
                $this->getUser(),
                $task->getTitle(),
                $task->getId(),
                $task->getProject()
            );

            return $this->redirectToRoute('app_task_show', ['id' => $task->getId()]);
        }
        return $this->render('task/new.html.twig', [
            'task' => $task,
            'form' => $form,
        ]);
    }
    #[Route('/task/{id}/status', name: 'app_task_status_change', methods: ['POST'])]
    public function changeStatus(
        Task $task,
        Request $request,
        EntityManagerInterface $entityManager,
        ActivityLogger $activityLogger
    ): Response {
        $oldStatus = $task->getStatut()->label();
        $newStatus = $request->request->get('status');

        // Convertir la valeur en enum TaskStatut
        try {
            $enumStatus = TaskStatut::from($newStatus);
        } catch (\ValueError $e) {
            throw $this->createNotFoundException('Statut de tâche invalide');
        }

        // Mettre à jour le statut
        $task->setStatut($enumStatus);
        $entityManager->flush();

        // Enregistrer l'activité de changement de statut
        $activityLogger->logTaskStatusChange(
            $this->getUser(),
            $task->getTitle(),
            $task->getId(),
            $oldStatus,
            $newStatus,
            $task->getProject()
        );

        return $this->redirectToRoute('app_task_show', ['id' => $task->getId()]);
    }


    /**
     * Afficher les détails d'une tâche
     */
    #[Route('/task/{id}', name: 'app_task_show', methods: ['GET'])]
    public function show(Task $task): Response
    {
        $project = $task->getProject();

        // Vérifier que l'utilisateur a le droit de voir ce project
        if (!$this->canViewProject($project)) {
            throw $this->createAccessDeniedException('Vous n\'avez pas les droits pour voir cette tâche');
        }

        return $this->render('task/show.html.twig', [
            'task' => $task,
        ]);
    }

    /**
     * Modification d'une tâche
     */
    #[Route('/{id}/edit', name: 'app_task_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Task $task, EntityManagerInterface $entityManager): Response
    {
        $project = $task->getProject();

        // Vérifier que l'utilisateur a le droit de modifier ce project
        if (!$this->canModifyProject($project)) {
            throw $this->createAccessDeniedException('Vous n\'avez pas les droits pour modifier cette tâche');
        }

        $form = $this->createForm(TaskType::class, $task, [
            'project' => $project,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            if ($request->isXmlHttpRequest()) {
                return new JsonResponse([
                    'success' => true,
                    'id' => $task->getId(),
                    'titre' => $task->getTitle(),
                ]);
            }

            $this->addFlash('success', 'Tâche modifiée avec succès');
            return $this->redirectToRoute('app_project_kanban', ['id' => $project->getId()]);
        }

        if ($request->isXmlHttpRequest()) {
            return $this->render('task/_form_modal.html.twig', [
                'task' => $task,
                'form' => $form,
                'project' => $project,
            ]);
        }

        return $this->render('task/edit.html.twig', [
            'task' => $task,
            'form' => $form,
            'project' => $project,
        ]);
    }

    /**
     * Suppression d'une tâche
     */
    #[Route('/task/{id}/delete', name: 'app_task_delete', methods: ['POST'])]
    public function delete(Request $request, Task $task, EntityManagerInterface $entityManager): Response
    {
        $project = $task->getProject();

        // Vérifier que l'utilisateur a le droit de modifier ce project
        if (!$this->canModifyProject($project)) {
            throw $this->createAccessDeniedException('Vous n\'avez pas les droits pour supprimer cette tâche');
        }

        if ($this->isCsrfTokenValid('delete' . $task->getId(), $request->request->get('_token'))) {
            // Récupérer la colonne et la position pour réorganiser plus tard
            $taskList = $task->getTaskList();
            $position = $task->getPosition();

            // Supprimer la tâche
            $entityManager->remove($task);
            $entityManager->flush();

            // Réorganiser les positions
            $taskRepository = $entityManager->getRepository(Task::class);
            $taskRepository->reorganizePositionsInColumn($taskList, $position);

            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['success' => true]);
            }

            $this->addFlash('success', 'Tâche supprimée avec succès');
        }

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(['success' => false], 400);
        }

        return $this->redirectToRoute('app_project_kanban', ['id' => $project->getId()]);
    }

    /**
     * Déplacer une tâche dans le kanban (drag & drop)
     */
    #[Route('/{id}/move', name: 'app_task_move', methods: ['POST'])]
    public function moveTask(
        Request $request,
        Task $task,
        EntityManagerInterface $entityManager,
        TaskRepository $taskRepository
    ): JsonResponse {
        $project = $task->getProject();

        // Vérifier que l'utilisateur a le droit de modifier ce project
        if (!$this->canModifyProject($project)) {
            return new JsonResponse(['error' => 'Vous n\'avez pas les droits pour déplacer cette tâche'], 403);
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['taskListId'], $data['position'])) {
            return new JsonResponse(['error' => 'Données incomplètes'], 400);
        }

        $taskListId = $data['taskListId'];
        $position = (int) $data['position'];

        $taskList = $entityManager->getRepository(TaskList::class)->find($taskListId);

        if (!$taskList || $taskList->getProject() !== $project) {
            return new JsonResponse(['error' => 'Colonne invalide'], 400);
        }

        // Déplacer la tâche
        $taskRepository->moveTaskToColumn($task, $taskList, $position);

        return new JsonResponse([
            'success' => true,
            'taskId' => $task->getId(),
            'taskListId' => $taskList->getId(),
            'position' => $position
        ]);
    }
 

    /**
     * Assigner une tâche à un utilisateur
     */

     #[Route('/{id}/assign/{userId}', name: 'app_task_assign_user', methods: ['POST'])]
     public function assignUser(
         Task $task,
         int $userId,
         UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        Request $request
    ): Response {
         $project = $task->getProject();

         // Vérifier les droits pour assigner des tâches
         if (!$this->canAssignTasks($project)) {
            throw $this->createAccessDeniedException('Vous n\'avez pas les droits pour assigner cette tâche');
         }

        $user = $userRepository->find($userId);

         if (!$user) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['error' => 'Utilisateur non trouvé'], 404);
             }

             $this->addFlash('error', 'Utilisateur non trouvé');
             return $this->redirectToRoute('app_project_kanban', ['id' => $project->getId()]);
         }

             // Vérifier que l'utilisateur est membre du project
             if (!$project->getMembres()->contains($user) && $project->getChefproject() !== $user) {
                 if ($request->isXmlHttpRequest()) {
                     return new JsonResponse(['error' => 'L\'utilisateur n\'est pas membre du project'], 400);
                 }
    
                 $this->addFlash('error', 'L\'utilisateur n\'est pas membre du project');
                 return $this->redirectToRoute('app_project_kanban', ['id' => $project->getId()]);
             }
    
             $task->setAssignedUser($user);
             $entityManager->flush();
    
             if ($request->isXmlHttpRequest()) {
                 return new JsonResponse([
                     'success' => true,
                     'userName' => $user->getFullName(),
                     'userId' => $user->getId()
                 ]);
            }
    
            $this->addFlash('success', 'Tâche assignée à ' . $user->getFullName());
             return $this->redirectToRoute('app_project_kanban', ['id' => $project->getId()]);
        }

    /**
     * Retirer l'assignation d'une tâche
     */
    #[Route('/{id}/unassign', name: 'app_task_unassign', methods: ['POST'])]
    public function unassignTask(
        Task $task,
        EntityManagerInterface $entityManager,
        Request $request
    ): Response {
        $project = $task->getProject();

        // Vérifier les droits pour assigner des tâches
        if (!$this->canAssignTasks($project)) {
            throw $this->createAccessDeniedException('Vous n\'avez pas les droits pour modifier cette tâche');
        }

        $task->setAssignedUser(null);
        $entityManager->flush();

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(['success' => true]);
        }

        $this->addFlash('success', 'Assignation de la tâche retirée');
        return $this->redirectToRoute('app_project_kanban', ['id' => $project->getId()]);
    }

    /**
     * Vérifie si l'utilisateur peut voir un project
     */
    /**
     * Vérifie si l'utilisateur peut voir un project
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

    /**
     * Vérifie si l'utilisateur peut modifier un project
     */
    private function canModifyProject($project): bool
    {
        $user = $this->getUser();

        if (!$user) {
            return false;
        }

        // Les administrateurs et directeurs peuvent tout modifier
        if ($this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_DIRECTEUR')) {
            return true;
        }

        // Les chefs de project peuvent modifier les projects qu'ils dirigent
        return $project->getChefproject() === $user;
    }

    /**
     * Vérifie si l'utilisateur peut assigner des tâches
     */
    private function canAssignTasks($project): bool
    {
        $user = $this->getUser();

        if (!$user) {
            return false;
        }

        // Les administrateurs et directeurs peuvent assigner des tâches
        if ($this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_DIRECTEUR')) {
            return true;
        }

        // Les chefs de project peuvent assigner des tâches dans leurs projects
        return $project->getCHEF_PROJECT() === $user;
    }
}


// Version 1 VS mais a rajouter ou modifier au 01/07/2025
// #[Route('/project/{projectId}/task')]
// #[IsGranted('ROLE_EMPLOYE')]
//     /**
//      * Créer une nouvelle tâche
//      */
//     #[Route('/new/{taskListId}', name: 'app_task_new', methods: ['GET', 'POST'])]
//     public function new(
//         int $projectId,
//         int $taskListId,
//         Request $request,
//         EntityManagerInterface $entityManager,
//         TaskRepository $taskRepository
//     ): Response {
//         $project = $entityManager->getRepository(Project::class)->find($projectId);
//         $taskList = $entityManager->getRepository(TaskList::class)->find($taskListId);
        
//         if (!$project || !$taskList || $taskList->getProject() !== $project) {
//             throw $this->createNotFoundException('project ou colonne non trouvé');
//         }
        
//         // Vérifier les permissions
//         $this->denyAccessUnlessGranted('EDIT', $project);
        
//         $task = new Task();
//         $task->setProject($project);
//         $task->setTaskList($taskList);
        
//         // Définir la position à la fin de la colonne
//         $nextPosition = $taskRepository->findNextPositionInColumn($taskList);
//         $task->setPosition($nextPosition);
        
//         $form = $this->createForm(TaskType::class, $task, [
//             'project' => $project,
//         ]);
//         $form->handleRequest($request);
        
//         if ($form->isSubmitted() && $form->isValid()) {
//             $task->setDateCreation(new \DateTime());
            
//             $entityManager->persist($task);
//             $entityManager->flush();
            
//             $this->addFlash('success', 'Tâche créée avec succès');
//             return $this->redirectToRoute('app_project_view_kanban', ['id' => $projectId]);
//         }
        
//         return $this->render('task/new.html.twig', [
//             'project' => $project,
//             'task_list' => $taskList,
//             'form' => $form->createView(),
//         ]);
//     }
    
//     /**
//      * Modifier une tâche
//      */
//     #[Route('/{id}/edit', name: 'app_task_edit', methods: ['GET', 'POST'])]
//     public function edit(
//         int $projectId,
//         Task $task,
//         Request $request,
//         EntityManagerInterface $entityManager
//     ): Response {
//         $project = $entityManager->getRepository(Project::class)->find($projectId);
        
//         if (!$project || $task->getProject() !== $project) {
//             throw $this->createNotFoundException('Tâche ou project non trouvé');
//         }
        
//         // Vérifier les permissions
//         $this->denyAccessUnlessGranted('EDIT', $project);
        
//         $form = $this->createForm(TaskType::class, $task, [
//             'project' => $project,
//         ]);
//         $form->handleRequest($request);
        
//         if ($form->isSubmitted() && $form->isValid()) {
//             $entityManager->flush();
            
//             $this->addFlash('success', 'Tâche modifiée avec succès');
            
//             // Rediriger vers la vue précédente (kanban ou vue globale)
//             $referer = $request->headers->get('referer');
//             if ($referer && strpos($referer, 'tasks') !== false) {
//                 return $this->redirectToRoute('app_project_view_tasks', ['id' => $projectId]);
//             }
            
//             return $this->redirectToRoute('app_project_view_kanban', ['id' => $projectId]);
//         }
        
//         return $this->render('task/edit.html.twig', [
//             'project' => $project,
//             'task' => $task,
//             'form' => $form->createView(),
//         ]);
//     }
    
//     /**
//      * Supprimer une tâche
//      */
//     #[Route('/{id}/delete', name: 'app_task_delete', methods: ['POST'])]
//     public function delete(
//         int $projectId,
//         Task $task,
//         Request $request,
//         EntityManagerInterface $entityManager,
//         TaskRepository $taskRepository
//     ): Response {
//         $project = $entityManager->getRepository(Project::class)->find($projectId);
        
//         if (!$project || $task->getProject() !== $project) {
//             throw $this->createNotFoundException('Tâche ou project non trouvé');
//         }
        
//         // Vérifier les permissions
//         $this->denyAccessUnlessGranted('EDIT', $project);
        
//         if ($this->isCsrfTokenValid('delete'.$task->getId(), $request->request->get('_token'))) {
//             $taskList = $task->getTaskList();
//             $position = $task->getPosition();
            
//             $entityManager->remove($task);
//             $entityManager->flush();
            
//             // Réorganiser les positions des tâches restantes
//             if ($taskList) {
//                 $taskRepository->reorganizePositionsInColumn($taskList, $position);
//             }
            
//             $this->addFlash('success', 'Tâche supprimée avec succès');
//         }
        
//         // Rediriger vers la vue précédente (kanban ou vue globale)
//         $referer = $request->headers->get('referer');
//         if ($referer && strpos($referer, 'tasks') !== false) {
//             return $this->redirectToRoute('app_project_view_tasks', ['id' => $projectId]);
//         }
        
//         return $this->redirectToRoute('app_project_view_kanban', ['id' => $projectId]);
//     }
    
//     /**
//      * Assigner une tâche à un utilisateur
//      */
//     #[Route('/{id}/assign/{userId}', name: 'app_task_assign', methods: ['POST'])]
//     public function assign(
//         int $projectId,
//         Task $task,
//         int $userId,
//         Request $request,
//         EntityManagerInterface $entityManager
//     ): Response {
//         $project = $entityManager->getRepository(Project::class)->find($projectId);
//         $user = $entityManager->getRepository(User::class)->find($userId);
        
//         if (!$project || $task->getProject() !== $project || !$user) {
//             throw $this->createNotFoundException('Tâche, project ou utilisateur non trouvé');
//         }
        
//         // Vérifier les permissions
//         $this->denyAccessUnlessGranted('EDIT', $project);
        
//         // Vérifier que l'utilisateur est bien membre du project
//         if (!$project->getMembres()->contains($user) && $project->getCHEF_PROJECT() !== $user) {
//             throw $this->createAccessDeniedException('Cet utilisateur n\'est pas membre du project');
//         }
        
//         $task->setAssignedUser($user);
//         $entityManager->flush();
        
//         $this->addFlash('success', 'Tâche assignée avec succès');
        
//         // Rediriger vers la vue précédente
//         $referer = $request->headers->get('referer');
//         return $referer ? $this->redirect($referer) : $this->redirectToRoute('app_project_view_kanban', ['id' => $projectId]);
//     }
    
//     /**
//      * Changer le statut d'une tâche
//      */
//     #[Route('/{id}/statut/{statut}', name: 'app_task_statut', methods: ['POST'])]
//     public function changestatut(
//         int $projectId,
//         Task $task,
//         string $statut,
//         Request $request,
//         EntityManagerInterface $entityManager
//     ): Response {
//         $project = $entityManager->getRepository(Project::class)->find($projectId);
        
//         if (!$project || $task->getProject() !== $project) {
//             throw $this->createNotFoundException('Tâche ou project non trouvé');
//         }
        
//         // Vérifier les permissions
//         $this->denyAccessUnlessGranted('EDIT', $project);
        
//         // Vérifier que le statut est valide
//         try {
//             $statutEnum = TaskStatut::from($statut);
//             $task->setStatut($statutEnum);
            
//             // Si la tâche est terminée, définir la date réelle
//             if ($statutEnum === TaskStatut::TERMINER) {
//                 $task->setDateReelle(new \DateTime());
//             }
            
//             $entityManager->flush();
//             $this->addFlash('success', 'Statut de la tâche modifié avec succès');
//         } catch (\ValueError $e) {
//             $this->addFlash('error', 'Statut invalide');
//         }
        
//         // Rediriger vers la vue précédente
//         $referer = $request->headers->get('referer');
//         return $referer ? $this->redirect($referer) : $this->redirectToRoute('app_project_view_kanban', ['id' => $projectId]);
//     }
    
//     /**
//      * Définir la priorité d'une tâche
//      */
//     #[Route('/{id}/priority/{priority}', name: 'app_task_priority', methods: ['POST'])]
//     public function setPriority(
//         int $projectId,
//         Task $task,
//         string $priority,
//         Request $request,
//         EntityManagerInterface $entityManager
//     ): Response {
//         $project = $entityManager->getRepository(Project::class)->find($projectId);
        
//         if (!$project || $task->getProject() !== $project) {
//             throw $this->createNotFoundException('Tâche ou project non trouvé');
//         }
        
//         // Vérifier les permissions
//         $this->denyAccessUnlessGranted('EDIT', $project);
        
//         // Vérifier que la priorité est valide
//         try {
//             $priorityEnum = TaskPriority::from($priority);
//             $task->setPriorite($priorityEnum);
            
//             $entityManager->flush();
//             $this->addFlash('success', 'Priorité de la tâche modifiée avec succès');
//         } catch (\ValueError $e) {
//             $this->addFlash('error', 'Priorité invalide');
//         }
        
//         // Rediriger vers la vue précédente
//         $referer = $request->headers->get('referer');
//         return $referer ? $this->redirect($referer) : $this->redirectToRoute('app_project_view_kanban', ['id' => $projectId]);
//     }
// }
