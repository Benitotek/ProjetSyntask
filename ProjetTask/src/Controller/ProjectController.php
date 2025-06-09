<?php

namespace App\Controller;

use App\Entity\Project;
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
class ProjectController extends AbstractController
{
    #[Route('/', name: 'app_project_index', methods: ['GET'])]
    #[IsGranted('ROLE_EMPLOYE')]
    public function index(ProjectRepository $projectRepository): Response
    {
        $user = $this->getUser();
        $projects = [];

        if ($this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_DIRECTEUR')) {
            $projects = $projectRepository->findAll();
        } elseif ($this->isGranted('ROLE_CHEF_DE_PROJET')) {
            $projects = $projectRepository->findByChefDeProjet($user);
        } else {
            $projects = $projectRepository->findByAssignedUser($user);
        }

        return $this->render('project/index.html.twig', [
            'projects' => $projects,
        ]);
    }

    #[Route('/new', name: 'app_project_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_DIRECTEUR')]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $project = new Project();
        $form = $this->createForm(ProjectTypeForm::class, $project);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $project->setDateCreation(new \DateTime());
            $project->setDateMaj(new \DateTime());
            $project->setStatut('EN-ATTENTE');

            $entityManager->persist($project);
            $entityManager->flush();

            $this->addFlash('success', 'Projet créé avec succès.');
            return $this->redirectToRoute('app_project_index');
        }

        return $this->render('project/new.html.twig', [
            'project' => $project,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_project_show', methods: ['GET'])]
    #[IsGranted('ROLE_EMPLOYE')]
    public function show(Project $project): Response
    {
        $this->denyAccessUnlessGranted('view', $project);

        return $this->render('project/show.html.twig', [
            'project' => $project,
        ]);
    }

    #[Route('/{id}/kanban', name: 'app_project_kanban', methods: ['GET'])]
    #[IsGranted('ROLE_EMPLOYE')]
    public function kanban(Project $project): Response
    {
        $this->denyAccessUnlessGranted('view', $project);

        return $this->render('project/kanban.html.twig', [
            'project' => $project,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_project_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_CHEF_DE_PROJET')]
    public function edit(Request $request, Project $project, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('edit', $project);

        $form = $this->createForm(ProjectTypeForm::class, $project);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $project->setDateMaj(new \DateTime());
            $entityManager->flush();

            $this->addFlash('success', 'Projet modifié avec succès.');
            return $this->redirectToRoute('app_project_show', ['id' => $project->getId()]);
        }

        return $this->render('project/edit.html.twig', [
            'project' => $project,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/assign-chef', name: 'app_project_assign_chef', methods: ['POST'])]
    #[IsGranted('ROLE_DIRECTEUR')]
    public function assignChef(Request $request, Project $project, EntityManagerInterface $entityManager, UserRepository $userRepository): Response
    {
        $chefId = $request->request->get('chef_id');
        $chef = $userRepository->find($chefId);

        if ($chef && in_array('ROLE_CHEF_DE_PROJET', $chef->getRoles())) {
            $project->setChefDeProjet($chef);
            $project->setDateMaj(new \DateTime());
            $entityManager->flush();

            $this->addFlash('success', 'Chef de projet assigné avec succès.');
        } else {
            $this->addFlash('error', 'Chef de projet invalide.');
        }

        return $this->redirectToRoute('app_project_show', ['id' => $project->getId()]);
    }
    // #[Route('/project', name: 'app_project')]
    // public function index(): Response
    // {
    //     return $this->render('project/index.html.twig', [
    //         'controller_name' => 'ProjectController',
    //     ]);
    // }
}
