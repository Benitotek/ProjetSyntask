<?php

namespace App\Controller;

use App\Entity\Project;
use App\Entity\User;
use App\Repository\ProjectRepository;
use App\Service\TaskCalendarService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/calendar')]
class CalendarController extends AbstractController
{
    // private SecurityBundle $security;
    // private EntityManagerInterface $entityManager;
    private TaskCalendarService $calendarService;

  
    public function __construct(TaskCalendarService $calendarService)
    {
        $this->calendarService = $calendarService;
    }

    #[Route('/', name: 'app_calendar')]
    #[IsGranted('ROLE_EMPLOYEE')]
    public function index(ProjectRepository $projectRepository): Response
    {
        // Récupérer les projets auxquels l'utilisateur participe
        $projects = $projectRepository->findProjectsByUser($this->getUser());

        return $this->render('calendar/index.html.twig', [
            'projects' => $projects,
        ]);
    }

    #[Route('/tasks', name: 'app_calendar_user_tasks', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getUserTasks(): Response
    {
        $tasks = $this->calendarService->getUserCalendarTasks();

        return $this->json($tasks);
    }

    #[Route('/project/{id}/tasks', name: 'app_calendar_project_tasks', methods: ['GET'])]
    public function getProjectTasks(Project $project): Response
    {
        // Vérifier si l'utilisateur a accès au projet
        $this->denyAccessUnlessGranted('VIEW', $project);

        $tasks = $this->calendarService->getProjectCalendarTasks($project->getId());

        return $this->json($tasks);
    }
}
