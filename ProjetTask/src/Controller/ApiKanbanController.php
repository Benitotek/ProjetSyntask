<?php

namespace App\Controller;

use App\Entity\Project;
use App\Entity\TaskList;
use App\Entity\Task;
use App\Repository\ProjectRepository;
use App\Repository\TaskListRepository;
use App\Repository\TaskRepository;
use App\Enum\TaskListColor;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/kanban', name: 'api_kanban_')]
#[IsGranted('ROLE_USER')]
class ApiKanbanController extends AbstractController
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
                    'description' => $project->getDescription()
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
    }
}