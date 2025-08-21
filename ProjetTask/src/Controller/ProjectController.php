<?php

namespace App\Controller;

use App\Entity\Project;
use App\Entity\TaskList;
use App\Entity\User;
use App\Form\ProjectTypeForm;
use App\Form\TaskListType;
use App\Repository\ProjectRepository;
use App\Repository\TaskListRepository;
use App\Repository\UserRepository;
use App\Security\Voter\ProjectVoter;
use App\Service\KanbanService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/project')]
#[IsGranted('ROLE_EMPLOYE')]
class ProjectController extends AbstractController
{
    public function __construct(
        private LoggerInterface $logger
    ) {}

    #[Route('/allProjects', name: 'app_project_index', methods: ['GET'])]
    public function index(ProjectRepository $projectRepository): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $projects = match (true) {
            $this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_DIRECTEUR')
            => $projectRepository->findAll(),
            $this->isGranted('ROLE_CHEF_PROJET')
            => $projectRepository->findByChefDeproject($user),
            default
            => $projectRepository->findByMembre($user)
        };

        return $this->render('project/index.html.twig', [
            'projects' => $projects,
            'current_statut' => null,
        ]);
    }

    #[Route('/mes-projects', name: 'app_mes_projects', methods: ['GET'])]
    public function mesProjects(Request $request, ProjectRepository $projectRepository): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $currentStatut = $request->query->get('statut', 'tous');

        $projectsAsManager = $currentStatut !== 'tous'
            ? $projectRepository->findBy(['chefproject' => $user, 'statut' => $currentStatut])
            : $projectRepository->findBy(['chefproject' => $user]);

        $projectsAsMember = $currentStatut !== 'tous'
            ? $projectRepository->findProjectsAsMemberBystatut($user, $currentStatut)
            : $projectRepository->findProjectsAsMember($user);

        $projects = array_unique([...$projectsAsManager, ...$projectsAsMember], SORT_REGULAR);

        return $this->render('project/mes_projects.html.twig', [
            'projects' => $projects,
            'current_statut' => $currentStatut,
            'user' => $user,
        ]);
    }

    #[Route('/new', name: 'app_project_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_CHEF_PROJET')]
    public function new(Request $request, EntityManagerInterface $entityManager, UserRepository $userRepository): Response
    {
        $project = new Project();
        $project->setChefproject($this->getUser())
            ->setDateCreation(new \DateTime())
            ->setCreatedBy($this->getUser());

        $form = $this->createForm(ProjectTypeForm::class, $project, [
            'userRepository' => $userRepository
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->createDefaultTaskLists($project, $entityManager);
            $entityManager->persist($project);
            $entityManager->flush();

            $this->addFlash('success', 'Projet créé avec succès');
            return $this->redirectToRoute('app_project_index');
        }

        return $this->render('project/new.html.twig', [
            'project' => $project,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_project_show', methods: ['GET'])]
    public function show(Project $project): Response
    {
        $this->denyAccessUnlessGranted(ProjectVoter::VIEW, $project);

        return $this->render('project/show.html.twig', [
            'project' => $project,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_project_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Project $project, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted(ProjectVoter::EDIT, $project);

        $form = $this->createForm(ProjectTypeForm::class, $project);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Projet modifié avec succès');
            return $this->redirectToRoute('app_project_index');
        }

        return $this->render('project/edit.html.twig', [
            'project' => $project,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_project_delete', methods: ['POST'])]
    public function delete(Request $request, Project $project, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted(ProjectVoter::DELETE, $project);

        if ($this->isCsrfTokenValid('delete' . $project->getId(), $request->getPayload()->get('_token'))) {
            $entityManager->remove($project);
            $entityManager->flush();
            $this->addFlash('success', 'Projet supprimé avec succès');
        }

        return $this->redirectToRoute('app_project_index');
    }

    /**
     *  SOLUTION POUR LE KANBAN - 
     */
    #[Route('/{id}/kanban', name: 'app_project_kanban', methods: ['GET',])]
    public function kanban(
        Project $project,
        TaskListRepository $taskListRepository,
        Request $request,
        EntityManagerInterface $entityManager,
        KanbanService $kanban
    ): Response {


        // Vérification d'accès avec le voter
        $this->denyAccessUnlessGranted(ProjectVoter::VIEW, $project);

        // Récupération des colonnes avec les tâches/
        //Charge colonnes + tâches en fetch-join (évite N+1 et LazyLoading en vue)
        
        $columns = $taskListRepository->findByProjectWithTasksOrdered($project);
        if (!$columns) {
            // Si aucune colonne n'existe, on en crée 3 par défaut
            $this->createDefaultTaskLists($project, $entityManager);
            $taskLists = $taskListRepository->findByProjectWithTasksOrdered($project);
        }
        // Vérification si le projet est archivé
        if ($project->isArchived() === true) {
            $this->addFlash('danger', 'Ce projet est archivé, vous ne pouvez pas le modifier.');
            return $this->redirectToRoute('app_project_show', ['id' => $project->getId()]);
        }
        $tasks = $taskListRepository->findTasksByProject($project); // Assuming this method retrieves tasks for the project
        $kpi = $kanban->computeKpis($project, $tasks); // Pass the correct type
        $kpi = ['percentDone' => 0, 'overdueCount' => 0, 'avgCycleTime' => '—'];
        if ($kpi) {
            $this->logger->info('KPI calculé pour le projet', [
                'project_id' => $project->getId(),
                'kpi' => $kpi
            ]);
        } else {
            $this->logger->warning('Aucun KPI disponible pour le projet', [
                'project_id' => $project->getId()
            ]);
        }

        // Préparer les membres
        $members = [...$project->getMembres()->toArray()];
        if ($project->getChefproject() && !in_array($project->getChefproject(), $members)) {
            $members[] = $project->getChefproject();
        }

        // --- Formulaire de création de colonne ---
        $taskList = new TaskList();
        $taskList->setProject($project);

        // Déterminer la position de la nouvelle colonne
        $lastPosition = $taskListRepository->findBy(['project' => $project], ['positionColumn' => 'DESC'], 1);
        $position = $lastPosition ? $lastPosition[0]->getPositionColumn() + 1 : 1;
        $taskList->setPositionColumn($position);

        $form = $this->createForm(TaskListType::class, $taskList);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($taskList);
            $entityManager->flush();

            $this->addFlash('success', 'Colonne créée avec succès');
            return $this->redirectToRoute('app_project_kanban', ['id' => $project->getId()]);
        }

        return $this->render('tasklist/kanban.html.twig', [
            'project' => $project,
            'taskLists' => $taskLists,
            'members' => $members,
            'form' => $form->createView(), // ✅ Formulaire disponible dans Twig
            'kpi' => $kpi, // ✅ KPI disponible dans Twig
            'kanbanService' => $kanban, // ✅ Service Kanban disponible dans Twig
            'taskList' => $taskList, // ✅ Colonne pour le formulaire
            'isArchived' => $project->isArchived(),
            'csrfToken' => $this->container->get('security.csrf.token_manager')->getToken('delete_tasklist')->getValue(),
        ]);
    }
    #[Route('/{id}/assign-manager/{userId}', name: 'app_project_assign_manager', methods: ['POST'])]
    #[IsGranted('ROLE_DIRECTEUR')]
    public function assignManager(
        Project $project,
        int $userId,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        Request $request
    ): Response {
        $user = $userRepository->find($userId);

        if (!$user || !in_array('ROLE_CHEF_PROJET', $user->getRoles())) {
            $this->addFlash('error', 'Utilisateur non valide ou n\'a pas le rôle requis');
            return $this->redirectToRoute('app_project_members', ['id' => $project->getId()]);
        }

        $project->setChefproject($user);

        if (!$project->getMembres()->contains($user)) {
            $project->addMembre($user);
        }

        $entityManager->flush();
        $this->addFlash('success', $user->getFullName() . ' assigné comme chef de projet');

        return $request->isXmlHttpRequest()
            ? new JsonResponse(['success' => true])
            : $this->redirectToRoute('app_project_members', ['id' => $project->getId()]);
    }

    //  Méthodes privées refactorisées et modernes
    private function createDefaultTaskLists(Project $project, EntityManagerInterface $entityManager): void
    {
        $defaultColumns = [
            ['nom' => 'À faire', 'color' => '#007bff'],
            ['nom' => 'En cours', 'color' => '#fd7e14'],
            ['nom' => 'Terminé', 'color' => '#28a745']
        ];

        foreach ($defaultColumns as $position => $column) {
            $taskList = new TaskList();
            $taskList->setNom($column['nom'])
                ->setCouleur(\App\Enum\TaskListColor::fromHexColor($column['color']))
                ->setProject($project)
                ->setPositionColumn($position + 1);

            $entityManager->persist($taskList);
        }
    }

    private function handleAddMember(Project $project, User $user, EntityManagerInterface $entityManager): void
    {
        if (!$project->getMembres()->contains($user)) {
            $project->addMembre($user);
            $entityManager->flush();
            $this->addFlash('success', $user->getFullName() . ' ajouté au projet');
        }
    }

    private function handleRemoveMember(Project $project, User $user, EntityManagerInterface $entityManager): void
    {
        if ($project->getChefproject() === $user) {
            $this->addFlash('error', 'Impossible de retirer le chef de projet');
            return;
        }

        if ($project->getMembres()->contains($user)) {
            $project->removeMembre($user);
            $entityManager->flush();
            $this->addFlash('success', $user->getFullName() . ' retiré du projet');
        }
    }
    #[Route('/{id}/archived', name: 'app_project_archived', methods: ['POST'])]
    public function archived(Request $request, Project $project, EntityManagerInterface $entityManager): Response
    {
        $project->setisArchived(true);
        $entityManager->flush();
        $this->addFlash('success', 'Projet archivé avec succès');
        return $this->redirectToRoute('app_project_index');
    }
    #[Route('/archived', name: 'app_project_archived', methods: ['GET'])]
    public function archivedIndex(ProjectRepository $projectRepository): Response
    {
        $projects = $projectRepository->findBy(['isArchived' => true]);
        return $this->render('project/index.html.twig', [
            'projects' => $projects,
        ]);
    }
    #[Route('/archived/{id}/', name: 'app_project_archive', methods: ['POST'])]
    public function archive(Project $project, ProjectRepository $repo)
    {
        $this->denyAccessUnlessGranted(ProjectVoter::ARCHIVE, $project);
        $project->setisArchived(true);
        $project->setDateArchived(new \DateTimeImmutable());
        $repo->save($project, true);
        $this->addFlash('success', 'Projet archivé');
        return $this->redirectToRoute('app_project_show', ['id' => $project->getId()]);
    }

    #[Route('/unarchived/{id}/', name: 'app_project_unarchive', methods: ['POST'])]
    public function unarchive(Project $project, ProjectRepository $repo)
    {
        $this->denyAccessUnlessGranted(ProjectVoter::ARCHIVE, $project);
        $project->setisArchived(false);
        $project->setDateArchived(null);
        $repo->save($project, true);
        $this->addFlash('success', 'Projet restauré');
        return $this->redirectToRoute('app_project_show', ['id' => $project->getId()]);
    }
}
