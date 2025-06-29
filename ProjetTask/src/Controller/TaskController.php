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
use App\Repository\TaskRepository;

#[Route('/project/{projectId}/task')]
#[IsGranted('ROLE_EMPLOYE')]
class TaskController extends AbstractController
{
    /**
     * Créer une nouvelle tâche
     */
    #[Route('/new/{taskListId}', name: 'app_task_new', methods: ['GET', 'POST'])]
    public function new(
        int $projectId,
        int $taskListId,
        Request $request,
        EntityManagerInterface $entityManager,
        TaskRepository $taskRepository
    ): Response {
        $project = $entityManager->getRepository(Project::class)->find($projectId);
        $taskList = $entityManager->getRepository(TaskList::class)->find($taskListId);
        
        if (!$project || !$taskList || $taskList->getProject() !== $project) {
            throw $this->createNotFoundException('Projet ou colonne non trouvé');
        }
        
        // Vérifier les permissions
        $this->denyAccessUnlessGranted('EDIT', $project);
        
        $task = new Task();
        $task->setProject($project);
        $task->setTaskList($taskList);
        
        // Définir la position à la fin de la colonne
        $nextPosition = $taskRepository->findNextPositionInColumn($taskList);
        $task->setPosition($nextPosition);
        
        $form = $this->createForm(TaskType::class, $task, [
            'project' => $project,
        ]);
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $task->setDateCreation(new \DateTime());
            
            $entityManager->persist($task);
            $entityManager->flush();
            
            $this->addFlash('success', 'Tâche créée avec succès');
            return $this->redirectToRoute('app_project_view_kanban', ['id' => $projectId]);
        }
        
        return $this->render('task/new.html.twig', [
            'project' => $project,
            'task_list' => $taskList,
            'form' => $form->createView(),
        ]);
    }
    
    /**
     * Modifier une tâche
     */
    #[Route('/{id}/edit', name: 'app_task_edit', methods: ['GET', 'POST'])]
    public function edit(
        int $projectId,
        Task $task,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        $project = $entityManager->getRepository(Project::class)->find($projectId);
        
        if (!$project || $task->getProject() !== $project) {
            throw $this->createNotFoundException('Tâche ou projet non trouvé');
        }
        
        // Vérifier les permissions
        $this->denyAccessUnlessGranted('EDIT', $project);
        
        $form = $this->createForm(TaskType::class, $task, [
            'project' => $project,
        ]);
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            
            $this->addFlash('success', 'Tâche modifiée avec succès');
            
            // Rediriger vers la vue précédente (kanban ou vue globale)
            $referer = $request->headers->get('referer');
            if ($referer && strpos($referer, 'tasks') !== false) {
                return $this->redirectToRoute('app_project_view_tasks', ['id' => $projectId]);
            }
            
            return $this->redirectToRoute('app_project_view_kanban', ['id' => $projectId]);
        }
        
        return $this->render('task/edit.html.twig', [
            'project' => $project,
            'task' => $task,
            'form' => $form->createView(),
        ]);
    }
    
    /**
     * Supprimer une tâche
     */
    #[Route('/{id}/delete', name: 'app_task_delete', methods: ['POST'])]
    public function delete(
        int $projectId,
        Task $task,
        Request $request,
        EntityManagerInterface $entityManager,
        TaskRepository $taskRepository
    ): Response {
        $project = $entityManager->getRepository(Project::class)->find($projectId);
        
        if (!$project || $task->getProject() !== $project) {
            throw $this->createNotFoundException('Tâche ou projet non trouvé');
        }
        
        // Vérifier les permissions
        $this->denyAccessUnlessGranted('EDIT', $project);
        
        if ($this->isCsrfTokenValid('delete'.$task->getId(), $request->request->get('_token'))) {
            $taskList = $task->getTaskList();
            $position = $task->getPosition();
            
            $entityManager->remove($task);
            $entityManager->flush();
            
            // Réorganiser les positions des tâches restantes
            if ($taskList) {
                $taskRepository->reorganizePositionsInColumn($taskList, $position);
            }
            
            $this->addFlash('success', 'Tâche supprimée avec succès');
        }
        
        // Rediriger vers la vue précédente (kanban ou vue globale)
        $referer = $request->headers->get('referer');
        if ($referer && strpos($referer, 'tasks') !== false) {
            return $this->redirectToRoute('app_project_view_tasks', ['id' => $projectId]);
        }
        
        return $this->redirectToRoute('app_project_view_kanban', ['id' => $projectId]);
    }
    
    /**
     * Assigner une tâche à un utilisateur
     */
    #[Route('/{id}/assign/{userId}', name: 'app_task_assign', methods: ['POST'])]
    public function assign(
        int $projectId,
        Task $task,
        int $userId,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        $project = $entityManager->getRepository(Project::class)->find($projectId);
        $user = $entityManager->getRepository(User::class)->find($userId);
        
        if (!$project || $task->getProject() !== $project || !$user) {
            throw $this->createNotFoundException('Tâche, projet ou utilisateur non trouvé');
        }
        
        // Vérifier les permissions
        $this->denyAccessUnlessGranted('EDIT', $project);
        
        // Vérifier que l'utilisateur est bien membre du projet
        if (!$project->getMembres()->contains($user) && $project->getChef_Projet() !== $user) {
            throw $this->createAccessDeniedException('Cet utilisateur n\'est pas membre du projet');
        }
        
        $task->setAssignedUser($user);
        $entityManager->flush();
        
        $this->addFlash('success', 'Tâche assignée avec succès');
        
        // Rediriger vers la vue précédente
        $referer = $request->headers->get('referer');
        return $referer ? $this->redirect($referer) : $this->redirectToRoute('app_project_view_kanban', ['id' => $projectId]);
    }
    
    /**
     * Changer le statut d'une tâche
     */
    #[Route('/{id}/statut/{statut}', name: 'app_task_statut', methods: ['POST'])]
    public function changestatut(
        int $projectId,
        Task $task,
        string $statut,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        $project = $entityManager->getRepository(Project::class)->find($projectId);
        
        if (!$project || $task->getProject() !== $project) {
            throw $this->createNotFoundException('Tâche ou projet non trouvé');
        }
        
        // Vérifier les permissions
        $this->denyAccessUnlessGranted('EDIT', $project);
        
        // Vérifier que le statut est valide
        try {
            $statutEnum = TaskStatut::from($statut);
            $task->setStatut($statutEnum);
            
            // Si la tâche est terminée, définir la date réelle
            if ($statutEnum === TaskStatut::TERMINE) {
                $task->setDateReelle(new \DateTime());
            }
            
            $entityManager->flush();
            $this->addFlash('success', 'Statut de la tâche modifié avec succès');
        } catch (\ValueError $e) {
            $this->addFlash('error', 'Statut invalide');
        }
        
        // Rediriger vers la vue précédente
        $referer = $request->headers->get('referer');
        return $referer ? $this->redirect($referer) : $this->redirectToRoute('app_project_view_kanban', ['id' => $projectId]);
    }
    
    /**
     * Définir la priorité d'une tâche
     */
    #[Route('/{id}/priority/{priority}', name: 'app_task_priority', methods: ['POST'])]
    public function setPriority(
        int $projectId,
        Task $task,
        string $priority,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        $project = $entityManager->getRepository(Project::class)->find($projectId);
        
        if (!$project || $task->getProject() !== $project) {
            throw $this->createNotFoundException('Tâche ou projet non trouvé');
        }
        
        // Vérifier les permissions
        $this->denyAccessUnlessGranted('EDIT', $project);
        
        // Vérifier que la priorité est valide
        try {
            $priorityEnum = TaskPriority::from($priority);
            $task->setPriorite($priorityEnum);
            
            $entityManager->flush();
            $this->addFlash('success', 'Priorité de la tâche modifiée avec succès');
        } catch (\ValueError $e) {
            $this->addFlash('error', 'Priorité invalide');
        }
        
        // Rediriger vers la vue précédente
        $referer = $request->headers->get('referer');
        return $referer ? $this->redirect($referer) : $this->redirectToRoute('app_project_view_kanban', ['id' => $projectId]);
    }
}

// #[Route('/tasks')]
// class TaskController extends AbstractController
// {
//     #[Route('/new/{projectId}', name: 'app_task_new', methods: ['GET', 'POST'])]
//     #[IsGranted('ROLE_CHEF_PROJET')]
//     public function new(Request $request, int $projectId, EntityManagerInterface $entityManager): Response
//     {
//         $project = $entityManager->getRepository(Project::class)->find($projectId);
//         $this->denyAccessUnlessGranted('edit', $project);

//         $task = new Task();
//         $task->setProject($project);
        
//         $form = $this->createForm(TaskType::class, $task);
//         $form->handleRequest($request);

//         if ($form->isSubmitted() && $form->isValid()) {
//             $task->setDateCreation(new \DateTime());
//             $task->setStatut(\App\Enum\TaskStatut::EN_ATTENTE);

//             $entityManager->persist($task);
//             $entityManager->flush();

//             $this->addFlash('success', 'Tâche créée avec succès.');
//             return $this->redirectToRoute('app_project_kanban', ['id' => $projectId]);
//         }

//         return $this->render('task/new.html.twig', [
//             'task' => $task,
//             'project' => $project,
//             'form' => $form,
//         ]);
//     }

//     #[Route('/{id}/edit', name: 'app_task_edit', methods: ['GET', 'POST'])]
//     #[IsGranted('ROLE_CHEF_DE_PROJET')]
//     public function edit(Request $request, Task $task, EntityManagerInterface $entityManager): Response
//     {
//         $this->denyAccessUnlessGranted('edit', $task->getProject());

//         $form = $this->createForm(TaskType::class, $task);
//         $form->handleRequest($request);

//         if ($form->isSubmitted() && $form->isValid()) {
//             $entityManager->flush();

//             $this->addFlash('success', 'Tâche modifiée avec succès.');
//             return $this->redirectToRoute('app_project_kanban', ['id' => $task->getProject()->getId()]);
//         }

//         return $this->render('task/edit.html.twig', [
//             'task' => $task,
//             'form' => $form,
//         ]);
//     }

//     #[Route('/{id}/update-statut', name: 'app_task_update_statut', methods: ['POST'])]
//     #[IsGranted('ROLE_EMPLOYE')]
//     public function updatestatut(Request $request, Task $task, EntityManagerInterface $entityManager): JsonResponse
//     {
//         $statut = $request->request->get('statut');
//         $validstatutes = ['EN-ATTENTE', 'EN-COURS', 'TERMINE'];

//         if (!in_array($statut, $validstatutes)) {
//             return new JsonResponse(['error' => 'Statut invalide'], 400);
//         }

//         $enumstatut = match ($statut) {
//             'EN-ATTENTE' => \App\Enum\TaskStatut::EN_ATTENTE,
//             'EN-COURS' => \App\Enum\TaskStatut::EN_COUR, // Use the correct constant name as defined in TaskStatut
//             'TERMINE' => \App\Enum\TaskStatut::TERMINE,
//             default => null,
//         };

//         if ($enumstatut === null) {
//             return new JsonResponse(['error' => 'Statut invalide'], 400);
//         }

//         $task->setStatut($enumstatut);
//         if ($enumstatut === \App\Enum\TaskStatut::TERMINE) {
//             $task->setDateReelle(new \DateTime());
//         }

//         $entityManager->flush();

//         return new JsonResponse(['success' => true]);
//     }

//     #[Route('/{id}/move', name: 'app_task_move', methods: ['POST'])]
//     #[IsGranted('ROLE_EMPLOYE')]
//     public function move(Request $request, Task $task, EntityManagerInterface $entityManager): JsonResponse
//     {
//         $data = json_decode($request->getContent(), true);
//         $newColumnId = $data['columnId'];
//         $newPosition = $data['position'];

//         $taskList = $entityManager->getRepository(TaskList::class)->find($newColumnId);
        
//         if (!$taskList || $taskList->getProject() !== $task->getProject()) {
//             return new JsonResponse(['error' => 'Colonne invalide'], 400);
//         }

//         $task->setTaskList($taskList);
//         $task->setPosition($newPosition);

//         $entityManager->flush();

//         return new JsonResponse(['success' => true]);
//     }
//     #[IsGranted('ROLE_EMPLOYE')]


//     #[Route('/task', name: 'app_task_index', methods: ['GET'])]
//     public function Task(TaskRepository $taskRepository): Response
//     {
//         // Récupérer toutes les tâches ou filtrer par utilisateur connecté
//         $tasks = $taskRepository->findBy(['assignedTo' => $this->getUser()]);
//         // Ou pour toutes les tâches : $tasks = $taskRepository->findAll();

//         return $this->render('task/index.html.twig', [
//             'tasks' => $tasks,
//         ]);
//     }
// }


// #[Route('/task')]
// #[IsGranted('ROLE_USER')]
// class TaskController extends AbstractController
// {
//     #[Route('/new', name: 'task_new', methods: ['POST'])]
//     public function new(Request $request, EntityManagerInterface $entityManager): Response
//     {
//         $taskListId = $request->request->get('task_list_id');
//         $taskList = $entityManager->getRepository(TaskList::class)->find($taskListId);
        
//         if (!$taskList) {
//             throw $this->createNotFoundException('Liste de tâches non trouvée');
//         }

//         $this->denyAccessUnlessGranted('PROJECT_EDIT', $taskList->getProject());

//         $task = new Task();
//         $form = $this->createForm(TaskType::class, $task);
//         $form->handleRequest($request);

//         if ($form->isSubmitted() && $form->isValid()) {
//             $task->setTaskList($taskList);
//             $task->setPosition($taskList->getTasks()->count());
            
//             $entityManager->persist($task);
//             $entityManager->flush();

//             if ($request->isXmlHttpRequest()) {
//                 return new JsonResponse([
//                     'success' => true, 
//                     'id' => $task->getId(),
//                     'html' => $this->renderView('task/_card.html.twig', ['task' => $task])
//                 ]);
//             }

//             $this->addFlash('success', 'Tâche créée avec succès');
//         }

//         return $this->redirectToRoute('project_kanban', ['id' => $taskList->getProject()->getId()]);
//     }

//     #[Route('/{id}/edit', name: 'task_edit', methods: ['GET', 'POST'])]
//     public function edit(Request $request, Task $task, EntityManagerInterface $entityManager): Response
//     {
//         $this->denyAccessUnlessGranted('TASK_EDIT', $task);

//         $form = $this->createForm(TaskType::class, $task);
//         $form->handleRequest($request);

//         if ($form->isSubmitted() && $form->isValid()) {
//             $entityManager->flush();

//             if ($request->isXmlHttpRequest()) {
//                 return new JsonResponse([
//                     'success' => true,
//                     'html' => $this->renderView('task/_card.html.twig', ['task' => $task])
//                 ]);
//             }

//             $this->addFlash('success', 'Tâche modifiée avec succès');
//             return $this->redirectToRoute('project_kanban', ['id' => $task->getTaskList()->getProject()->getId()]);
//         }

//         if ($request->isXmlHttpRequest()) {
//             return new JsonResponse([
//                 'success' => false,
//                 'html' => $this->renderView('task/_form.html.twig', ['form' => $form, 'task' => $task])
//             ]);
//         }

//         return $this->render('task/edit.html.twig', [
//             'task' => $task,
//             'form' => $form,
//         ]);
//     }

//     #[Route('/{id}/delete', name: 'task_delete', methods: ['POST'])]
//     public function delete(Request $request, Task $task, EntityManagerInterface $entityManager): Response
//     {
//         $this->denyAccessUnlessGranted('TASK_EDIT', $task);
//         $project = $task->getTaskList()->getProject();

//         if ($this->isCsrfTokenValid('delete'.$task->getId(), $request->request->get('_token'))) {
//             $entityManager->remove($task);
//             $entityManager->flush();

//             if ($request->isXmlHttpRequest()) {
//                 return new JsonResponse(['success' => true]);
//             }

//             $this->addFlash('success', 'Tâche supprimée');
//         }

//         return $this->redirectToRoute('project_kanban', ['id' => $project->getId()]);
//     }

//     #[Route('/{id}/move', name: 'task_move', methods: ['POST'])]
//     public function move(Request $request, Task $task, EntityManagerInterface $entityManager): Response
//     {
//         $this->denyAccessUnlessGranted('TASK_EDIT', $task);

//         $newTaskListId = $request->request->get('task_list_id');
//         $newPosition = $request->request->get('position');

//         $newTaskList = $entityManager->getRepository(TaskList::class)->find($newTaskListId);
        
//         if ($newTaskList && $newTaskList->getProject() === $task->getTaskList()->getProject()) {
//             $task->setTaskList($newTaskList);
//             $task->setPosition((int)$newPosition);
//             $entityManager->flush();

//             return new JsonResponse(['success' => true]);
//         }

//         return new JsonResponse(['success' => false], 400);
//     }

//     #[Route('/{id}/assign-user', name: 'task_assign_user', methods: ['POST'])]
//     public function assignUser(Request $request, Task $task, UserRepository $userRepository, EntityManagerInterface $entityManager): Response
//     {
//         $this->denyAccessUnlessGranted('TASK_EDIT', $task);

//         $userId = $request->request->get('user_id');
//         $user = $userId ? $userRepository->find($userId) : null;
        
//         $task->setAssignedUser($user);
//         $entityManager->flush();

//         if ($request->isXmlHttpRequest()) {
//             return new JsonResponse([
//                 'success' => true,
//                 'html' => $this->renderView('task/_card.html.twig', ['task' => $task])
//             ]);
//         }

//         $this->addFlash('success', 'Tâche assignée avec succès');
//         return $this->redirectToRoute('project_kanban', ['id' => $task->getTaskList()->getProject()->getId()]);
//     }

//     #[Route('/{id}/statut', name: 'task_statut', methods: ['POST'])]
//     public function updatestatut(Request $request, Task $task, EntityManagerInterface $entityManager): Response
//     {
//         $this->denyAccessUnlessGranted('TASK_EDIT', $task);

//         $statut = $request->request->get('statut');
//         $task->setStatut(\App\Entity\Taskstatut::from($statut));
        
//         if ($statut === 'TERMINE' && !$task->getDateDeFinReelle()) {
//             $task->setDateDeFinReelle(new \DateTime());
//         }
        
//         $entityManager->flush();

//         if ($request->isXmlHttpRequest()) {
//             return new JsonResponse(['success' => true]);
//         }

//         $this->addFlash('success', 'Statut de la tâche mis à jour');
//         return $this->redirectToRoute('project_kanban', ['id' => $task->getTaskList()->getProject()->getId()]);
//     }
// final class TaskController extends AbstractController
// {
//     #[Route('/task', name: 'app_task')]
//     public function index(): Response
//     {
//         return $this->render('task/index.html.twig', [
//             'controller_name' => 'TaskController',
//         ]);
//     }
// }
