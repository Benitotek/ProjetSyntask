<?php

namespace App\Controller;

use App\Entity\Task;
use App\Entity\Project;
use App\Entity\User;
use App\Entity\TaskList;
use App\Entity\Tag;
use App\Entity\Comment;
use App\Repository\TaskRepository;
use App\Repository\ProjectRepository;
use App\Repository\UserRepository;
use App\Repository\TaskListRepository;
use App\Repository\TagRepository;
use App\Repository\CommentRepository;
use App\Enum\TaskStatut;
use App\Enum\TaskPriority;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/tasks', name: 'api_task_')]
#[IsGranted('ROLE_USER')]
final class ApiTaskController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TaskRepository $taskRepository,
        private ProjectRepository $projectRepository,
        private UserRepository $userRepository,
        private TaskListRepository $taskListRepository,
        private TagRepository $tagRepository,
        private CommentRepository $commentRepository,
        private SerializerInterface $serializer,
        private ValidatorInterface $validator
    ) {}

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $filters = [
            'project' => $request->query->get('project'),
            'statut' => $request->query->get('statut'),
            'priority' => $request->query->get('priority'),
            'assigned_user' => $request->query->get('assigned_user'),
            'tag' => $request->query->get('tag'),
            'overdue' => $request->query->getBoolean('overdue'),
            'limit' => $request->query->getInt('limit', 50),
            'offset' => $request->query->getInt('offset', 0)
        ];

        try {
            $tasks = $this->taskRepository->findTasksForUser($user, $filters);
            $total = $this->taskRepository->countTasksForUser($user, $filters);

            $tasksData = array_map(function (Task $task) {
                return $this->formatTaskData($task);
            }, $tasks);

            return $this->json([
                'success' => true,
                'data' => $tasksData,
                'total' => $total,
                'filters' => $filters
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des tâches',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $task = $this->taskRepository->find($id);

        if (!$task) {
            return $this->json([
                'success' => false,
                'message' => 'Tâche non trouvée'
            ], 404);
        }

        if (!$task->isMembre($this->getUser())) {
            return $this->json([
                'success' => false,
                'message' => 'Accès non autorisé'
            ], 403);
        }

        return $this->json([
            'success' => true,
            'data' => $this->formatTaskData($task, true)
        ]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json([
                'success' => false,
                'message' => 'Données JSON invalides'
            ], 400);
        }

        try {
            $task = new Task();
            $task->setTitle($data['title'] ?? '');
            $task->setDescription($data['description'] ?? null);
            $task->setCreatedBy($this->getUser());

            // Statut
            if (isset($data['statut'])) {
                $task->setStatut(TaskStatut::from($data['statut']));
            }

            // Priorité
            if (isset($data['priority'])) {
                $task->setPriorite(TaskPriority::from($data['priority']));
            }

            // Date butoir
            if (isset($data['due_date'])) {
                $task->setDateButoir(new \DateTime($data['due_date']));
            }

            // Projet
            if (isset($data['project_id'])) {
                $project = $this->projectRepository->find($data['project_id']);
                if (!$project || !$project->isMembre($this->getUser())) {
                    return $this->json([
                        'success' => false,
                        'message' => 'Projet non trouvé ou accès non autorisé'
                    ], 403);
                }
                $task->setProject($project);
            }

            // TaskList
            if (isset($data['task_list_id'])) {
                $taskList = $this->taskListRepository->find($data['task_list_id']);
                if ($taskList && $taskList->getProject()->isMembre($this->getUser())) {
                    $task->setTaskList($taskList);
                    $task->setPosition($this->getNextPosition($taskList));
                }
            }

            // Utilisateur assigné
            if (isset($data['assigned_user_id'])) {
                $assignedUser = $this->userRepository->find($data['assigned_user_id']);
                if ($assignedUser) {
                    $task->setAssignedUser($assignedUser);
                }
            }

            // Validation
            $errors = $this->validator->validate($task);
            if (count($errors) > 0) {
                return $this->json([
                    'success' => false,
                    'message' => 'Erreurs de validation',
                    'errors' => $this->formatValidationErrors($errors)
                ], 400);
            }

            $this->entityManager->persist($task);
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Tâche créée avec succès',
                'data' => $this->formatTaskData($task)
            ], 201);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la création de la tâche',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/{id}', name: 'update', methods: ['PUT', 'PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $task = $this->taskRepository->find($id);

        if (!$task) {
            return $this->json([
                'success' => false,
                'message' => 'Tâche non trouvée'
            ], 404);
        }

        if (!$task->isMembre($this->getUser())) {
            return $this->json([
                'success' => false,
                'message' => 'Accès non autorisé'
            ], 403);
        }

        $data = json_decode($request->getContent(), true);

        try {
            // Mise à jour des champs
            if (isset($data['title'])) {
                $task->setTitle($data['title']);
            }

            if (isset($data['description'])) {
                $task->setDescription($data['description']);
            }

            if (isset($data['statut'])) {
                $oldstatut = $task->getStatut();
                $newstatut = TaskStatut::from($data['statut']);
                $task->setStatut($newstatut);

                // Si la tâche est terminée, on met à jour la date de completion
                if ($newstatut === TaskStatut::TERMINER && $oldstatut !== TaskStatut::TERMINER) {
                    $task->setDateCompletion(new \DateTime());
                }
            }

            if (isset($data['priority'])) {
                $task->setPriorite(TaskPriority::from($data['priority']));
            }

            if (isset($data['due_date'])) {
                $task->setDateButoir($data['due_date'] ? new \DateTime($data['due_date']) : null);
            }

            if (isset($data['assigned_user_id'])) {
                $assignedUser = $data['assigned_user_id'] ? $this->userRepository->find($data['assigned_user_id']) : null;
                $task->setAssignedUser($assignedUser);
            }

            if (isset($data['position'])) {
                $task->setPosition($data['position']);
            }

            // Changement de TaskList (pour drag & drop Kanban)
            if (isset($data['task_list_id'])) {
                $newTaskList = $this->taskListRepository->find($data['task_list_id']);
                if ($newTaskList && $newTaskList->getProject()->isMembre($this->getUser())) {
                    $task->setTaskList($newTaskList);
                    if (!isset($data['position'])) {
                        $task->setPosition($this->getNextPosition($newTaskList));
                    }
                }
            }

            // Validation
            $errors = $this->validator->validate($task);
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
                'message' => 'Tâche mise à jour avec succès',
                'data' => $this->formatTaskData($task)
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $task = $this->taskRepository->find($id);

        if (!$task) {
            return $this->json([
                'success' => false,
                'message' => 'Tâche non trouvée'
            ], 404);
        }

        if (!$task->isMembre($this->getUser())) {
            return $this->json([
                'success' => false,
                'message' => 'Accès non autorisé'
            ], 403);
        }

        try {
            $this->entityManager->remove($task);
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Tâche supprimée avec succès'
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/{id}/comments', name: 'comments', methods: ['GET'])]
    public function getComments(int $id): JsonResponse
    {
        $task = $this->taskRepository->find($id);

        if (!$task || !$task->isMembre($this->getUser())) {
            return $this->json([
                'success' => false,
                'message' => 'Tâche non trouvée ou accès non autorisé'
            ], 404);
        }

        $comments = $task->getComments();
        $commentsData = [];

        foreach ($comments as $comment) {
            $commentsData[] = [
                'id' => $comment->getId(),
                'content' => $comment->getContent(),
                'created_at' => $comment->getDateCreation()->format('Y-m-d H:i:s'),
                'author' => [
                    'id' => $comment->getAuthor()->getId(),
                    'name' => $comment->getAuthor()->getPrenom() . ' ' . $comment->getAuthor()->getNom(),
                    'email' => $comment->getAuthor()->getEmail()
                ]
            ];
        }

        return $this->json([
            'success' => true,
            'data' => $commentsData
        ]);
    }

    #[Route('/{id}/comments', name: 'add_comment', methods: ['POST'])]
    public function addComment(int $id, Request $request): JsonResponse
    {
        $task = $this->taskRepository->find($id);

        if (!$task || !$task->isMembre($this->getUser())) {
            return $this->json([
                'success' => false,
                'message' => 'Tâche non trouvée ou accès non autorisé'
            ], 404);
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['content']) || empty(trim($data['content']))) {
            return $this->json([
                'success' => false,
                'message' => 'Le contenu du commentaire est requis'
            ], 400);
        }

        try {
            $comment = new Comment();
            $comment->setContenu($data['content']);
            $comment->setTask($task);
            $comment->setAuteur($this->getUser());

            $this->entityManager->persist($comment);
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Commentaire ajouté avec succès',
                'data' => [
                    'id' => $comment->getId(),
                    'content' => $comment->getContenu(),
                    'created_at' => $comment->getDateCreation()->format('Y-m-d H:i:s'),
                    'author' => [
                        'id' => $comment->getAuteur()->getId(),
                        'name' => $comment->getAuteur()->getPrenom() . ' ' . $comment->getAuteur()->getNom(),
                        'email' => $comment->getAuteur()->getEmail()
                    ]
                ]
            ], 201);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de l\'ajout du commentaire',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/{id}/tags', name: 'add_tag', methods: ['POST'])]
    public function addTag(int $id, Request $request): JsonResponse
    {
        $task = $this->taskRepository->find($id);

        if (!$task || !$task->isMembre($this->getUser())) {
            return $this->json([
                'success' => false,
                'message' => 'Tâche non trouvée ou accès non autorisé'
            ], 404);
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['tag_id'])) {
            return $this->json([
                'success' => false,
                'message' => 'L\'ID du tag est requis'
            ], 400);
        }

        $tag = $this->tagRepository->find($data['tag_id']);

        if (!$tag) {
            return $this->json([
                'success' => false,
                'message' => 'Tag non trouvé'
            ], 404);
        }

        try {
            $task->addTag($tag);
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Tag ajouté avec succès'
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de l\'ajout du tag',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/{id}/tags/{tagId}', name: 'remove_tag', methods: ['DELETE'])]
    public function removeTag(int $id, int $tagId): JsonResponse
    {
        $task = $this->taskRepository->find($id);

        if (!$task || !$task->isMembre($this->getUser())) {
            return $this->json([
                'success' => false,
                'message' => 'Tâche non trouvée ou accès non autorisé'
            ], 404);
        }

        $tag = $this->tagRepository->find($tagId);

        if (!$tag) {
            return $this->json([
                'success' => false,
                'message' => 'Tag non trouvé'
            ], 404);
        }

        try {
            $task->removeTag($tag);
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Tag supprimé avec succès'
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression du tag',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/{id}/complete', name: 'complete', methods: ['POST'])]
    private function formatTaskData(Task $task, bool $detailed = false): array
    {
        $data = [
            'id' => $task->getId(),
            'title' => $task->getTitle(),
            'description' => $task->getDescription(),
            'statut' => $task->getStatut()->value,
            'statut_label' => $task->getStatut()->label(),
            'priority' => $task->getPriorite()->value,
            'priority_label' => $task->getPriorite()->label(),
            'position' => $task->getPosition(),
            'created_at' => $task->getDateCreation()?->format('Y-m-d H:i:s'),
            'due_date' => $task->getDateButoir()?->format('Y-m-d H:i:s'),
            'completion_date' => $task->getDateCompletion()?->format('Y-m-d H:i:s'),
            'is_overdue' => $task->isOverdue(),
            'project' => $task->getProject() ? [
                'id' => $task->getProject()->getId(),
                'name' => $task->getProject()->getTitre()
            ] : null,
            'task_list' => $task->getTaskList() ? [
                'id' => $task->getTaskList()->getId(),
                'name' => $task->getTaskList()->getNom()
            ] : null,
            'assigned_user' => $task->getAssignedUser() ? [
                'id' => $task->getAssignedUser()->getId(),
                'name' => $task->getAssignedUser()->getPrenom() . ' ' . $task->getAssignedUser()->getNom(),
                'email' => $task->getAssignedUser()->getEmail()
            ] : null,
            'created_by' => $task->getCreatedBy() ? [
                'id' => $task->getCreatedBy()->getId(),
                'name' => $task->getCreatedBy()->getPrenom() . ' ' . $task->getCreatedBy()->getNom()
            ] : null
        ];

        if ($detailed) {
            $data['tags'] = [];
            foreach ($task->getTags() as $tag) {
                $data['tags'][] = [
                    'id' => $tag->getId(),
                    'name' => $tag->getNom(),
                    'color' => $tag->getCouleur()
                ];
            }

            $data['comments_count'] = $task->getComments()->count();
            $data['subtasks_count'] = $task->getSousTask()->count();
        }

        return $data;
    }

    #[Route('/{id}/complete', name: 'complete', methods: ['POST'])]
    private function getNextPosition(TaskList $taskList): int
    {
        $tasks = $taskList->getTasks();
        $maxPosition = 0;

        foreach ($tasks as $task) {
            $maxPosition = max($maxPosition, $task->getPosition());
        }

        return $maxPosition + 1;
    }

    private function formatValidationErrors($errors): array
    {
        $formattedErrors = [];
        foreach ($errors as $error) {
            $formattedErrors[] = [
                'property' => $error->getPropertyPath(),
                'message' => $error->getMessage()
            ];
        }
        return $formattedErrors;
    }
}