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
use App\Entity\Task;
use App\Entity\TaskList;
use App\Entity\User;
use App\Form\ProjectTypeForm;
use App\Repository\TaskListRepository;
use App\Repository\TaskRepository;

#[Route('/project')]
class ProjectController extends AbstractController
{
    private $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    #[Route('/', name: 'app_project_index', methods: ['GET'])]
    public function index(ProjectRepository $projectRepository): Response
    {
        $user = $this->getUser();

        if ($this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_DIRECTEUR')) {
            // Afficher tous les projets pour les administrateurs et les directeurs
            $projects = $projectRepository->findAll();
        } elseif ($this->isGranted('ROLE_CHEF_PROJET')) {
            // Afficher uniquement les projets dont l'utilisateur est chef
            $projects = $projectRepository->findByChef_Projet($user);
        } else {
            // Afficher uniquement les projets dont l'utilisateur est membre
            $projects = $projectRepository->findByMembre($user);
        }

        return $this->render('project/index.html.twig', [
            'projects' => $projects,
        ]);
    }

    #[Route('/new', name: 'app_project_new', methods: ['GET', 'POST'])]
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

            return $this->redirectToRoute('app_project_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('project/new.html.twig', [
            'project' => $project,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_project_show', methods: ['GET'])]
    public function show(Project $project): Response
    {
        // Vérifier que l'utilisateur a le droit de voir ce projet
        $this->denyAccessUnlessGranted('VIEW', $project);

        return $this->render('project/show.html.twig', [
            'project' => $project,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_project_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Project $project, EntityManagerInterface $entityManager): Response
    {
        // Vérifier que l'utilisateur a le droit de modifier ce projet
        $this->denyAccessUnlessGranted('EDIT', $project);

        $form = $this->createForm(ProjectTypeForm::class, $project);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_project_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('project/edit.html.twig', [
            'project' => $project,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_project_delete', methods: ['POST'])]
    public function delete(Request $request, Project $project, EntityManagerInterface $entityManager): Response
    {
        // Vérifier que l'utilisateur a le droit de supprimer ce projet
        $this->denyAccessUnlessGranted('DELETE', $project);

        if ($this->isCsrfTokenValid('delete' . $project->getId(), $request->request->get('_token'))) {
            $entityManager->remove($project);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_project_index', [], Response::HTTP_SEE_OTHER);
    }

    private function createDefaultTaskLists(Project $project, EntityManagerInterface $entityManager): void
    {
        $defaultLists = [
            ['nom' => 'À faire', 'position' => 1],
            ['nom' => 'En cours', 'position' => 2],
            ['nom' => 'Terminé', 'position' => 3],
        ];

        foreach ($defaultLists as $listData) {
            $taskList = new TaskList();
            $taskList->setNom($listData['nom']);
            $taskList->setPositionColumn($listData['position']);
            $taskList->setProject($project);
            $entityManager->persist($taskList);
        }
    }
}

// #[Route('/project/view')]
// #[IsGranted('ROLE_EMPLOYE')]
class ProjectViewController extends AbstractController
{
    /**
     * Vue Kanban d'un projet
     */
    #[Route('/{id}/kanban', name: 'app_project_view_kanban', methods: ['GET'])]
    public function ProjectKanban(
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
        $statut = $request->query->get('statut');
        $priority = $request->query->get('priority');
        $assignee = $request->query->get('assignee');

        // Récupérer toutes les tâches du projet
        $tasks = $taskRepository->findByProject($project);

        // Appliquer les filtres
        if ($statut) {
            $tasks = array_filter($tasks, function ($task) use ($statut) {
                return $task->getStatut() === $statut;
            });
        }

        if ($priority) {
            $tasks = array_filter($tasks, function ($task) use ($priority) {
                return $task->getPriorite() === $priority;
            });
        }

        if ($assignee) {
            $tasks = array_filter($tasks, function ($task) use ($assignee) {
                return $task->getAssignedUser() && $task->getAssignedUser()->getId() == $assignee;
            });
        }

        return $this->render('project/view/all_tasks.html.twig', [
            'project' => $project,
            'tasks' => $tasks,
            'filters' => [
                'statut' => $statut,
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
    #[Route('/{id}/filter-by-statut/{statut}', name: 'app_project_filter_by_statut')]
    public function filterBystatut(
        Project $project,
        string $statut,
        TaskRepository $taskRepository
    ): Response {
        // Vérifier les permissions
        $this->denyAccessUnlessGranted('VIEW', $project);

        $tasks = $taskRepository->findByProject($project);
        $filteredTasks = array_filter($tasks, function ($task) use ($statut) {
            return $task->getStatut() === $statut;
        });

        return $this->render('project/view/all_tasks.html.twig', [
            'project' => $project,
            'tasks' => $filteredTasks,
            'filters' => [
                'statut' => $statut,
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
        $filteredTasks = array_filter($tasks, function ($task) use ($priority) {
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
        $filteredTasks = array_filter($tasks, function ($task) use ($user) {
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
