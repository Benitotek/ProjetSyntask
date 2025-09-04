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
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api')]
class ApiKanbanController extends AbstractController
{
    // Propriétés
    private EntityManagerInterface $entityManager;
    private ProjectRepository $projectRepository;
    private TaskListRepository $taskListRepository;
    private TaskRepository $taskRepository;
    private ValidatorInterface $validator;

    public function __construct(
        EntityManagerInterface $entityManager,
        ProjectRepository $projectRepository,
        TaskListRepository $taskListRepository,
        TaskRepository $taskRepository,
        ValidatorInterface $validator
    ) {
        $this->entityManager = $entityManager;
        $this->projectRepository = $projectRepository;
        $this->taskListRepository = $taskListRepository;
        $this->taskRepository = $taskRepository;
        $this->validator = $validator;
    }
    private EntityManagerInterface $em;

    #[Route('/project/{id}/tasklists/new', name: 'api_tasklist_new', methods: ['POST'])]
    public function newColumn(#[MapEntity] Project $project, Request $request, TaskListRepository $tlRepo): JsonResponse
    {
        $this->denyAccessUnlessGranted('EDIT', $project);

        $payload = json_decode($request->getContent(), true) ?? [];
        $name = trim((string)($payload['name'] ?? ''));
        $colorHex = (string)($payload['color'] ?? TaskListColor::BLEU->value);

        if ($name === '') {
            return $this->json(['success' => false, 'error' => 'Nom requis'], 400);
        }

        $maxPos = $tlRepo->findMaxPositionByProject($project);

        $list = (new TaskList())
            ->setProject($project)
            ->setNom($name)
            ->setPositionColumn($maxPos + 1);

        // Si l'entité est typée enum TaskListColor:
        if (method_exists($list, 'getCouleur') && $list->getCouleur() instanceof TaskListColor || (new \ReflectionProperty($list, 'couleur'))->getType()?->getName() === TaskListColor::class) {
            $list->setCouleur(TaskListColor::fromHex($colorHex));
        } else {
            // sinon, stock string hex
            $list->setCouleur(TaskListColor::tryFrom($colorHex) ?? $colorHex);
        }

        $this->em->persist($list);
        $this->em->flush();

        $color = $list->getCouleur();
        $colorOut = $color instanceof TaskListColor ? $color->css() : (string)$color;

        return $this->json([
            'success' => true,
            'column' => [
                'id' => $list->getId(),
                'nom' => $list->getNom(),
                'position' => $list->getPositionColumn(),
                'color' => $colorOut,
            ],
        ]);
    }

    #[Route('/project/{id}/tasklists/reorder', name: 'api_tasklists_reorder', methods: ['POST'])]
    public function reorderColumns(#[MapEntity] Project $project, Request $request, TaskListRepository $tlRepo): JsonResponse
    {
        $this->denyAccessUnlessGranted('EDIT', $project);

        $payload = json_decode($request->getContent(), true) ?? [];
        $columns = $payload['columns'] ?? null;
        if (!is_array($columns)) {
            return $this->json(['success' => false, 'error' => 'Format columns invalide'], 400);
        }

        $position = 0;
        foreach ($columns as $colId) {
            $tl = $tlRepo->find((int)$colId);
            if ($tl && $tl->getProject()->getId() === $project->getId()) {
                $tl->setPositionColumn($position++);
            }
        }

        $this->em->flush();

        return $this->json(['success' => true]);
    }

    #[Route('/task/{id}/move', name: 'api_task_move', methods: ['POST'])]
    public function moveTask(Task $task, Request $request, TaskListRepository $tlRepo, TaskRepository $tRepo): JsonResponse
    {
        $project = $task->getProject();
        $this->denyAccessUnlessGranted('EDIT', $project);

        $payload = json_decode($request->getContent(), true) ?? [];
        $columnId = (int)($payload['columnId'] ?? 0);
        $position = (int)($payload['position'] ?? 0);

        $target = $tlRepo->find($columnId);
        if (!$target || $target->getProject()->getId() !== $project->getId()) {
            return $this->json(['success' => false, 'error' => 'Colonne invalide'], 400);
        }

        // Règle métier: Terminé -> statut TERMINER + date réelle si manquante
        $nameNorm = mb_strtolower($target->getNom() ?? '');
        if (in_array($nameNorm, ['terminer', 'terminé', 'done', 'finished'], true)) {
            if (method_exists($task, 'setStatut')) {
                $task->setStatut(TaskStatut::TERMINER);
            }
            if (method_exists($task, 'getDateReelle') && method_exists($task, 'setDateReelle') && !$task->getDateReelle()) {
                $task->setDateReelle(new \DateTime());
            }
        }

        if (method_exists($tRepo, 'moveTaskToColumn')) {
            $tRepo->moveTaskToColumn($task, $target, $position);
        } else {
            $task->setTaskList($target);
            if (method_exists($task, 'setPosition')) {
                $task->setPosition($position);
            }
            $this->em->flush();
        }

        return $this->json(['success' => true]);
    }



    // apikanbancontroller.php avant modification du 20/08/2025
    // #[Route('/api')]
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
                    'statut' => $project->getStatut(),
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

    #[Route('/task-lists/{id}/delete', name: 'delete_column', methods: ['DELETE'])]
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
            'statut' => $task->getStatut()->value,
            'statut_label' => $task->getStatut()->label(),
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

    private function updateTaskstatutByColumn(Task $task, TaskList $taskList): void
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
                $task->setStatut(TaskStatut::EN_COURS);
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
