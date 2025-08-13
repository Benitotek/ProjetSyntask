<?php

namespace App\Controller; 


use App\Entity\Project;
use App\Entity\TaskList;
use App\Entity\Task;
use App\Repository\ProjectRepository;
use App\Repository\TaskListRepository;
use App\Repository\TaskRepository;
use App\Enum\TaskListColor;
use App\Enum\TaskStatut;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/kanban', name: 'api_kanban_')]
#[IsGranted('ROLE_USER')]
final class ApiKanbanController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ProjectRepository $projectRepository,
        private TaskListRepository $taskListRepository,
        private TaskRepository $taskRepository,
        private ValidatorInterface $validator
    ) {}

    #[Route('/projects/{projectId}', name: 'project_board', methods: ['GET'])]
    public function getProjectBoard(int $projectId): JsonResponse
    {
        $project = $this->projectRepository->find($projectId);

        if (!$project) {
            return $this->json([
                'success' => false,
                'message' => 'Projet non trouvé'
            ], 404);
        }

        if (!$project->isMembre($this->getUser())) {
            return $this->json([
                'success' => false,
                'message' => 'Accès non autorisé'
            ], 403);
        }

        try {
            $taskLists = $this->taskListRepository->findBy(
                ['project' => $project],
                ['positionColumn' => 'ASC']
            );

            $boardData = [
                'project' => [
                    'id' => $project->getId(),
                    'name' => $project->getTitre(),
                    'description' => $project->getDescription(),
                    'status' => $project->getStatut(),
                    'progress' => $project->getProgress()
                ],
                'columns' => []
            ];

            foreach ($taskLists as $taskList) {
                $columnData = $this->formatTaskListData($taskList);
                $boardData['columns'][] = $columnData;
            }

            return $this->json([
                'success' => true,
                'data' => $boardData
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors du chargement du tableau Kanban',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/task-lists', name: 'create_column', methods: ['POST'])]
    public function createColumn(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data || !isset($data['project_id'], $data['name'])) {
            return $this->json([
                'success' => false,
                'message' => 'Données manquantes (project_id, name requis)'
            ], 400);
        }

        $project = $this->projectRepository->find($data['project_id']);

        if (!$project || !$project->isMembre($this->getUser())) {
            return $this->json([
                'success' => false,
                'message' => 'Projet non trouvé ou accès non autorisé'
            ], 403);
        }

        try {
            $taskList = new TaskList();
            $taskList->setNom($data['name']);
            $taskList->setDescription($data['description'] ?? '');
            $taskList->setProject($project);
            $taskList->setPositionColumn($this->getNextColumnPosition($project));

            // Couleur
            if (isset($data['color'])) {
                $color = TaskListColor::tryFrom($data['color']);
                if ($color) {
                    $taskList->setCouleur($color);
                }
            }

            $errors = $this->validator->validate($taskList);
            if (count($errors) > 0) {
                return $this->json([
                    'success' => false,
                    'message' => 'Erreurs de validation',
                    'errors' => $this->formatValidationErrors($errors)
                ], 400);
            }

            $this->entityManager->persist($taskList);
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Colonne créée avec succès',
                'data' => $this->formatTaskListData($taskList)
            ], 201);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la création de la colonne',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/task-lists/{id}', name: 'update_column', methods: ['PUT', 'PATCH'])]
    public function updateColumn(int $id, Request $request): JsonResponse
    {
        $taskList = $this->taskListRepository->find($id);

        if (!$taskList) {
            return $this->json([
                'success' => false,
                'message' => 'Colonne non trouvée'
            ], 404);
        }

        if (!$taskList->getProject()->isMembre($this->getUser())) {
            return $this->json([
                'success' => false,
                'message' => 'Accès non autorisé'
            ], 403);
        }

        $data = json_decode($request->getContent(), true);

        try {
            if (isset($data['name'])) {
                $taskList->setNom($data['name']);
            }

            if (isset($data['description'])) {
                $taskList->setDescription($data['description']);
            }

            if (isset($data['color'])) {
                $color = TaskListColor::tryFrom($data['color']);
                if ($color) {
                    $taskList->setCouleur($color);
                }
            }

            if (isset($data['position'])) {
                $taskList->setPositionColumn($data['position']);
            }

            $errors = $this->validator->validate($taskList);
            if (count($errors) > 0) {
                return $this->json([
                    'success' => false,
                    'message' => 'Erreurs de validation',
                    'errors' => $this->formatValidationErrors($errors)
                ], 400);
            }

            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Colonne mise à jour avec succès',
                'data' => $this->formatTaskListData($taskList)
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour de la colonne',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/task-lists/{id}', name: 'delete_column', methods: ['DELETE'])]
    public function deleteColumn(int $id): JsonResponse
    {
        $taskList = $this->taskListRepository->find($id);

        if (!$taskList) {
            return $this->json([
                'success' => false,
                'message' => 'Colonne non trouvée'
            ], 404);
        }

        if (!$taskList->getProject()->isMembre($this->getUser())) {
            return $this->json([
                'success' => false,
                'message' => 'Accès non autorisé'
            ], 403);
        }

        try {
            // Vérifier s'il y a des tâches dans cette colonne
            $taskCount = $taskList->getTasks()->count();
            if ($taskCount > 0) {
                return $this->json([
                    'success' => false,
                    'message' => "Impossible de supprimer la colonne : elle contient {$taskCount} tâche(s)"
                ], 400);
            }

            $this->entityManager->remove($taskList);
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Colonne supprimée avec succès'
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression de la colonne',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/tasks/{id}/move', name: 'move_task', methods: ['PATCH'])]
    public function moveTask(int $id, Request $request): JsonResponse
    {
        $task = $this->taskRepository->find($id);

        if (!$task) {
            return $this->json([
                'success' => false,
                'message' => 'Tâche non trouvée'
            ], 404);
        }

        if (!$task->getProject()->isMembre($this->getUser())) {
            return $this->json([
                'success' => false,
                'message' => 'Accès non autorisé'
            ], 403);
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['task_list_id'])) {
            return $this->json([
                'success' => false,
                'message' => 'ID de la liste de tâches requis'
            ], 400);
        }

        try {
            $newTaskList = $this->taskListRepository->find($data['task_list_id']);

            if (!$newTaskList) {
                return $this->json([
                    'success' => false,
                    'message' => 'Liste de tâches non trouvée'
                ], 404);
            }

            // Vérifier que la nouvelle liste appartient au même projet
            if ($newTaskList->getProject() !== $task->getProject()) {
                return $this->json([
                    'success' => false,
                    'message' => 'La liste de tâches n\'appartient pas au même projet'
                ], 400);
            }

            // Déplacer la tâche
            $oldTaskList = $task->getTaskList();
            $task->setTaskList($newTaskList);

            // Mettre à jour la position si fournie
            if (isset($data['position'])) {
                $task->setPosition($data['position']);
            } else {
                // Si pas de position spécifiée, mettre à la fin
                $task->setPosition($this->getNextTaskPosition($newTaskList));
            }

            // Mettre à jour automatiquement le statut selon la colonne si configuré
            if (isset($data['update_status']) && $data['update_status']) {
                $this->updateTaskStatusByColumn($task, $newTaskList);
            }

            $this->entityManager->flush();

            // Mettre à jour les couleurs automatiques des colonnes
            if ($oldTaskList) {
                $oldTaskList->updateAutoColor();
            }
            $newTaskList->updateAutoColor();

            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Tâche déplacée avec succès',
                'data' => $this->formatTaskData($task)
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors du déplacement de la tâche',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/columns/{projectId}/reorder', name: 'reorder_columns', methods: ['PATCH'])]
    public function reorderColumns(int $projectId, Request $request): JsonResponse
    {
        $project = $this->projectRepository->find($projectId);

        if (!$project || !$project->isMembre($this->getUser())) {
            return $this->json([
                'success' => false,
                'message' => 'Projet non trouvé ou accès non autorisé'
            ], 403);
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['column_order']) || !is_array($data['column_order'])) {
            return $this->json([
                'success' => false,
                'message' => 'Ordre des colonnes requis'
            ], 400);
        }

        try {
            foreach ($data['column_order'] as $index => $columnId) {
                $taskList = $this->taskListRepository->find($columnId);
                if ($taskList && $taskList->getProject() === $project) {
                    $taskList->setPositionColumn($index);
                }
            }

            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Ordre des colonnes mis à jour avec succès'
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la réorganisation des colonnes',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/tasks/{taskListId}/reorder', name: 'reorder_tasks', methods: ['PATCH'])]
    public function reorderTasks(int $taskListId, Request $request): JsonResponse
    {
        $taskList = $this->taskListRepository->find($taskListId);

        if (!$taskList || !$taskList->getProject()->isMembre($this->getUser())) {
            return $this->json([
                'success' => false,
                'message' => 'Liste de tâches non trouvée ou accès non autorisé'
            ], 403);
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['task_order']) || !is_array($data['task_order'])) {
            return $this->json([
                'success' => false,
                'message' => 'Ordre des tâches requis'
            ], 400);
        }

        try {
            foreach ($data['task_order'] as $index => $taskId) {
                $task = $this->taskRepository->find($taskId);
                if ($task && $task->getTaskList() === $taskList) {
                    $task->setPosition($index);
                }
            }

            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Ordre des tâches mis à jour avec succès'
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la réorganisation des tâches',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ==================== MÉTHODES UTILITAIRES ====================

    private function formatTaskListData(TaskList $taskList): array
    {
        $tasks = [];
        foreach ($taskList->getTasks() as $task) {
            $tasks[] = $this->formatTaskData($task);
        }

        $progression = $taskList->getProgression();
        $delayStats = $taskList->getDelayStats();

        return [
            'id' => $taskList->getId(),
            'name' => $taskList->getNom(),
            'description' => $taskList->getDescription(),
            'position' => $taskList->getPositionColumn(),
            'color' => $taskList->getCouleur()?->value,
            'color_label' => $taskList->getCouleur()?->getLabel(),
            'tasks' => $tasks,
            'task_count' => count($tasks),
            'progression' => $progression,
            'delay_stats' => $delayStats,
            'overdue_count' => $taskList->getOverdueCount(),
            'has_overdue_tasks' => $taskList->hasOverdueTasks(),
            'created_at' => $taskList->getDateTime()?->format('Y-m-d H:i:s')
        ];
    }

    private function formatTaskData(Task $task): array
    {
        $assignedUsers = [];
        foreach ($task->getAssignedUsers() as $user) {
            $assignedUsers[] = [
                'id' => $user->getId(),
                'name' => $user->getNom(),
                'email' => $user->getEmail()
            ];
        }

        $tags = [];
        foreach ($task->getTags() as $tag) {
            $tags[] = [
                'id' => $tag->getId(),
                'name' => $tag->getNom(),
                'color' => $tag->getCouleur()
            ];
        }

        return [
            'id' => $task->getId(),
            'title' => $task->getTitle(),
            'description' => $task->getDescription(),
            'status' => $task->getStatut()->value,
            'status_label' => $task->getStatut()->label(),
            'priority' => $task->getPriorite()->value,
            'priority_label' => $task->getPriorite()->label(),
            'position' => $task->getPosition(),
            'date_creation' => $task->getDateCreation()?->format('Y-m-d H:i:s'),
            'date_butoir' => $task->getDateButoir()?->format('Y-m-d H:i:s'),
            'date_reelle' => $task->getDateReelle()?->format('Y-m-d H:i:s'),
            'date_completion' => $task->getDateCompletion()?->format('Y-m-d H:i:s'),
            'is_overdue' => $task->isOverdue(),
            'is_coming_soon' => $task->isComingSoon(),
            'assigned_user' => $task->getAssignedUser() ? [
                'id' => $task->getAssignedUser()->getId(),
                'name' => $task->getAssignedUser()->getNom(),
                'email' => $task->getAssignedUser()->getEmail()
            ] : null,
            'assigned_users' => $assignedUsers,
            'tags' => $tags,
            'comments_count' => $task->getComments()->count(),
            'subtasks_count' => $task->getSousTask()->count(),
            'is_subtask' => $task->isSousTask(),
            'created_by' => $task->getCreatedBy() ? [
                'id' => $task->getCreatedBy()->getId(),
                'name' => $task->getCreatedBy()->getNom()
            ] : null
        ];
    }

    private function getNextColumnPosition(Project $project): int
    {
        $maxPosition = $this->taskListRepository->createQueryBuilder('tl')
            ->select('MAX(tl.positionColumn)')
            ->where('tl.project = :project')
            ->setParameter('project', $project)
            ->getQuery()
            ->getSingleScalarResult();

        return ($maxPosition ?? 0) + 1;
    }

    private function getNextTaskPosition(TaskList $taskList): int
    {
        $maxPosition = $this->taskRepository->createQueryBuilder('t')
            ->select('MAX(t.position)')
            ->where('t.taskList = :taskList')
            ->setParameter('taskList', $taskList)
            ->getQuery()
            ->getSingleScalarResult();

        return ($maxPosition ?? 0) + 1;
    }

    private function updateTaskStatusByColumn(Task $task, TaskList $taskList): void
    {
        // Logique pour mettre à jour automatiquement le statut selon la colonne
        // Cette logique peut être personnalisée selon vos besoins
        $columnName = strtolower($taskList->getNom());

        switch ($columnName) {
            case 'à faire':
            case 'todo':
            case 'backlog':
                $task->setStatut(TaskStatut::EN_ATTENTE);
                break;
            case 'en cours':
            case 'in progress':
            case 'doing':
                $task->setStatut(TaskStatut::EN_COUR);
                break;
            case 'terminé':
            case 'done':
            case 'completed':
                $task->setStatut(TaskStatut::TERMINER);
                $task->setDateCompletion(new \DateTime());
                break;
            case 'test':
            case 'review':
            case 'en test':
                $task->setStatut(TaskStatut::EN_REPRISE);
                break;
        }
    }

    private function formatValidationErrors($errors): array
    {
        $formattedErrors = [];
        foreach ($errors as $error) {
            $formattedErrors[] = [
                'field' => $error->getPropertyPath(),
                'message' => $error->getMessage()
            ];
        }
        return $formattedErrors;
    }
}