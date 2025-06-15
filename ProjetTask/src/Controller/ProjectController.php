<?php
namespace App\Controller;
use App\Entity\Project;
use App\Entity\TaskList;
use App\Entity\User;
use App\Form\ProjectTypeForm;
use App\Repository\ProjectRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/projects')]
// #[IsGranted('ROLE_USER')]
class ProjectController extends AbstractController
{
    #[Route('/', name: 'project_index', methods: ['GET'])]
    public function index(Request $request, ProjectRepository $projectRepository): Response
    {
        $user = $this->getUser();
        $status = $request->query->get('status');

        if ($this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_DIRECTEUR')) {
            // Utilise les méthodes qui existent vraiment
            $projects = $status ? $projectRepository->findByStatus(is_array($status) ? $status : [$status]) : $projectRepository->findAll();
        } else {
            // Utilise les méthodes existantes
            if (!$user) {
                throw $this->createAccessDeniedException();
            }
            // S'assurer que $user est bien du type User
            $projects = $projectRepository->findByAssignedUser($user);
        }

        return $this->render('project/index.html.twig', [
            'projects' => $projects,
            'current_status' => $status,
        ]);
    }

    #[Route('/new', name: 'project_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_DIRECTEUR')]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $project = new Project();
        $form = $this->createForm(ProjectTypeForm::class, $project);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($project);

            // Créer les colonnes par défaut
            $defaultColumns = ['À faire', 'En cours', 'Terminé'];
            foreach ($defaultColumns as $index => $columnName) {
                $taskList = new TaskList();
                $taskList->setNom($columnName);
                $taskList->setPositionColumn($index);
                $taskList->setProject($project);
                $entityManager->persist($taskList);
            }

            $entityManager->flush();

            $this->addFlash('success', 'Projet créé avec succès');
            return $this->redirectToRoute('project_index');
        }

        return $this->render('project/new.html.twig', [
            'project' => $project,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'project_show', methods: ['GET'])]
    public function show(Project $project): Response
    {
        // Vérification simple en attendant les voters
        /** @var User|null $user */
        $user = $this->getUser();
        if (
            !$this->isGranted('ROLE_ADMIN') &&
            !$this->isGranted('ROLE_DIRECTEUR') &&
            $project->getChefDeProjet() !== $user &&
            !$project->getMembres()->contains($user)
        ) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('project/show.html.twig', [
            'project' => $project,
        ]);
    }

    public function kanban(Project $project): Response
    {
        // Même vérification que show
        /** @var User|null $user */
        $user = $this->getUser();
        if (
            !$this->isGranted('ROLE_ADMIN') &&
            !$this->isGranted('ROLE_DIRECTEUR') &&
            $project->getChefDeProjet() !== $user &&
            !$project->getMembres()->contains($user)
        ) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('project/kanban.html.twig', [
            'project' => $project,
        ]);
    }

    public function edit(Request $request, Project $project, EntityManagerInterface $entityManager): Response
    {
        // Vérification édition
        /** @var User|null $user */
        $user = $this->getUser();
        if (
            !$this->isGranted('ROLE_ADMIN') &&
            !$this->isGranted('ROLE_DIRECTEUR') &&
            $project->getChefDeProjet() !== $user
        ) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(ProjectTypeForm::class, $project);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Projet modifié avec succès');
            return $this->redirectToRoute('project_show', ['id' => $project->getId()]);
        }

        return $this->render('project/edit.html.twig', [
            'project' => $project,
            'form' => $form->createView(),
        ]);
    }

    public function archive(Request $request, Project $project, EntityManagerInterface $entityManager): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (
            !$this->isGranted('ROLE_ADMIN') &&
            !$this->isGranted('ROLE_DIRECTEUR') &&
            $project->getChefDeProjet() !== $user
        ) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('archive' . $project->getId(), $request->request->get('_token'))) {
            $project->setEstArchive(true);
            $entityManager->flush();
            $this->addFlash('success', 'Projet archivé');
        }

        return $this->redirectToRoute('project_index');
    }

    public function assignUser(Request $request, Project $project, UserRepository $userRepository, EntityManagerInterface $entityManager): Response
    {
        /** @var User|null $currentUser */
        $currentUser = $this->getUser();
        if (
            !$this->isGranted('ROLE_ADMIN') &&
            !$this->isGranted('ROLE_DIRECTEUR') &&
            $project->getChefDeProjet() !== $currentUser
        ) {
            throw $this->createAccessDeniedException();
        }

        $userId = $request->request->get('user_id');
        $assignedUser = $userRepository->find($userId);

        if ($assignedUser && !$project->getMembres()->contains($assignedUser)) {
            $project->addMembre($assignedUser);
            $entityManager->flush();
            $this->addFlash('success', 'Utilisateur assigné au projet');
        }

        return $this->redirectToRoute('project_show', ['id' => $project->getId()]);
    }
}
