<?php

namespace App\Controller;

use App\Entity\Project;
use App\Entity\Task;
use App\Entity\User;
use App\Enum\TaskStatut;
use App\Repository\ActivityRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\Attribute\Security;
use App\Repository\ProjectRepository;
use App\Repository\TaskRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security as SecurityBundleSecurity;
use Symfony\Component\Security\Core\Role\Role;
// use App\Repository\ActivityRepository; // Correction ici
// Assurez-vous que le namespace est correct pour votre project
// Si vous avez un service spécifique pour les activités, utilisez-le
use App\Service\ActivityService; // Si vous avez un service pour les activités
use Container1mDkSxn\getActivityRepositoryService;

class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_dashboard')]
    #[IsGranted('ROLE_USER')]
    public function index(
        ProjectRepository $projectRepository,
        TaskRepository $taskRepository,
        UserRepository $userRepository,
        ActivityRepository $activityRepository,
        EntityManagerInterface $entityManager
    ): Response {
        // Assurez-vous que l'utilisateur est connecté
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        // Récupérer les projects
        $projects = $projectRepository->findAll();

        // Récupérer les tâches par date de creation décroissante
        $tasks = $taskRepository->findBy([], ['dateCreation' => 'DESC'], 5);

        // Récupérer toutes les tâches
        $tasks = $taskRepository->findAll();

        // Récupérer tous les utilisateurs
        $users = $userRepository->findAll();

        // Récupérer les activités récentes
        $activities = $activityRepository->findRecent(10);

        // projects actifs (pour l'affichage dans le tableau des projects en cours)
        $activeProjects = $projectRepository->findBy(['statut' => Project::STATUT_EN_COURS], ['dateCreation' => 'DESC']);

        // Récupérer les tâches assignées à l'utilisateur actuel
        $tachesAssignees = $taskRepository->findBy(['assignedUser' => $user], ['dateCreation' => 'DESC'], 5);

        // Calculs pour les statistiques
        $allTasks = $taskRepository->findAll();

        $completedTasks = count(array_filter($allTasks, function ($task) {
            return $task->getStatut() === TaskStatut::TERMINE;
        }));

        $pendingTasks = count(array_filter($allTasks, function ($task) {
            return $task->getStatut() === TaskStatut::EN_ATTENTE;
        }));

        $inProgressTasks = count(array_filter($allTasks, function ($task) {
            return $task->getStatut() === TaskStatut::EN_COUR;
        }));

        // Calcul du taux de complétion
        $completionRate = count($allTasks) > 0 ? ($completedTasks / count($allTasks)) * 100 : 0;

        // Générer les échéances à venir
        $dueDates = [];

        // Ajouter les échéances des tâches
        foreach ($tasks as $task) {
            if ($task->getDateButoir()) {
                $dueDates[] = [
                    'title' => $task->getTitle(),
                    'date' => $task->getDateButoir(),
                    'type' => 'task',
                    'completed' => $task->getStatut() === TaskStatut::TERMINE,
                    'statut' => $task->getStatutLabel(),
                    'url' => $this->generateUrl('app_task_show', ['id' => $task->getId()])
                ];
            }
        }

        // Ajouter les échéances des projects
        foreach ($projects as $project) {
            if ($project->getDateButoir()) {
                $dueDates[] = [
                    'title' => $project->getTitre(),
                    'date' => $project->getDateButoir(),
                    'type' => 'project',
                    'completed' => $project->getStatut() === Project::STATUT_TERMINE,
                    'statut' => $project->getStatut(),
                    'url' => $this->generateUrl('app_project_show', ['id' => $project->getId()])
                ];
            }
        }

        // Trier les échéances par date
        usort($dueDates, function ($a, $b) {
            return $a['date'] <=> $b['date'];
        });

        // Limiter à 5 échéances
        $dueDates = array_slice($dueDates, 0, 5);

        // Performance de l'équipe (pour admin ou directeur ou chefs de project)
        $teamPerformance = [];

        if ($this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_DIRECTEUR') || $this->isGranted('ROLE_CHEF_PROJECT')) {
            foreach ($users as $teamMember) {
                $userTasks = $taskRepository->findBy(['assignedUser' => $teamMember]);
                $userCompletedTasks = count(array_filter($userTasks, function ($task) {
                    return $task->getStatut() === TaskStatut::TERMINE;
                }));

                $userOverdueTasks = count(array_filter($userTasks, function ($task) {
                    return $task->isOverdue();
                }));

                // Dernière activité de l'utilisateur
                $lastActivity = $activityRepository->findOneBy(
                    ['user' => $teamMember],
                    ['dateCreation' => 'DESC']
                );

                $teamPerformance[] = [
                    'user' => $teamMember,
                    'assignedTasks' => count($userTasks),
                    'completedTasks' => $userCompletedTasks,
                    'completionRate' => count($userTasks) > 0 ? ($userCompletedTasks / count($userTasks)) * 100 : 0,
                    'overdueTasks' => $userOverdueTasks,
                    'lastActivity' => $lastActivity ? $lastActivity->getDateCreation() : null
                ];
            }
        }

        return $this->render('dashboard/index.html.twig', [
            'projects' => $projects,
            'tasks' => $tasks,
            'users' => $users,
            'activities' => $activities,
            'activeProjects' => $activeProjects,
            'tachesAssignees' => $tachesAssignees,
            'dueDates' => $dueDates,
            'teamPerformance' => $teamPerformance,
            'current_statut' => 'dashboard',
            'stats' => [
                'totalProjects' => count($projects),
                'totalTasks' => count($allTasks),
                'completedTasks' => $completedTasks,
                'pendingTasks' => $pendingTasks,
                'inProgressTasks' => $inProgressTasks,
                'totalUsers' => count($users),
                'completionRate' => $completionRate,
            ],
        ]);
    }
}
