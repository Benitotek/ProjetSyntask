<?php

namespace App\Controller;

use App\Entity\Project;
use App\Entity\User;
use App\Repository\ProjectRepository;
use App\Service\TaskCalendarService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/calendar')]
// #[IsGranted('ROLE_EMPLOYEE')]
class CalendarController extends AbstractController
{
    // private SecurityBundle $security;
    // private EntityManagerInterface $entityManager;
    private TaskCalendarService $calendarService;


    public function __construct(TaskCalendarService $calendarService)
    {
        $this->calendarService = $calendarService;
    }
    // #[Route('/calendar/all/tasks', name: 'app_calendar_all_employee_tasks', methods: ['GET'])]
    // #[IsGranted('ROLE_ADMIN')]
    // public function allEmployeeTasks(TaskCalendarService $taskCalendarService): JsonResponse
    // {
    //     $calendarTasks = $taskCalendarService->getAllEmployeeCalendarTasks();
    //     return $this->json($calendarTasks);
    // }

    #[Route('/all/tasks/api', name: 'app_calendar_all_tasks_api', methods: ['GET'])]
    public function allTasksCalendarAdmin(TaskCalendarService $calendarService): JsonResponse
    {
        // Vérification access : doit être ADMIN/DIRECTEUR/CHEF_PROJECT etc
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $calendarTasks = $calendarService->getAllCalendarTasks();
        return $this->json($calendarTasks);
    }
    // different route pour callender version API JSON au dessus et HTML.TWIG en suivante
    #[Route('/all/tasks/', name: 'app_calendar_all_tasks', methods: ['GET'])]
    public function allTasksCalendar(): Response
    {
        // Vérification access : doit être ADMIN/DIRECTEUR/CHEF_PROJECT etc
        $this->denyAccessUnlessGranted('ROLE_ADMIN');


        return $this->render('calendar/calendar_admin.html.twig');
    }

    #[Route('/user/tasks', name: 'app_calendar_user_tasks', methods: ['GET'])]
    public function userTasks(TaskCalendarService $calendarService): JsonResponse
    {
        // On récupère les tâches du user courant (toutes)
        $calendarTasks = $calendarService->getUserCalendarTasks($this->getUser());
        return $this->json($calendarTasks);
    }

    #[Route('/project/{id}/tasks', name: 'app_calendar_project_tasks', methods: ['GET'])]
    public function projectTasks(int $id, TaskCalendarService $calendarService): JsonResponse
    {
        $calendarTasks = $calendarService->getProjectCalendarTasks($this->getUser(), $id);
        return $this->json($calendarTasks);
    }

    #[Route('/', name: 'app_calendar')]

    public function index(ProjectRepository $projectRepository): Response
    {
        // Récupérer les projets auxquels l'utilisateur participe
        $projects = $projectRepository->findProjectsByUser($this->getUser());

        return $this->render('calendar/index.html.twig', [
            'projects' => $projects,
        ]);
    }

    #[Route('/tasks', name: 'app_calendar_user_tasks', methods: ['GET'])]

    public function getUserTasks(): Response
    {
        $tasks = $this->calendarService->getUserCalendarTasks($this->getUser());

        return $this->json($tasks);
    }

    #[Route('/project/{id}/tasks', name: 'app_calendar_project_tasks', methods: ['GET'])]
    public function getProjectTasks(Project $project): Response
    {
        // Vérifier si l'utilisateur a accès au projet
        $this->denyAccessUnlessGranted('VIEW', $project);

        $tasks = $this->calendarService->getProjectCalendarTasks($this->getUser(), $project->getId());

        return $this->json($tasks);
    }
}
