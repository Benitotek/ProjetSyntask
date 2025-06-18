<?php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Entity\Project;
use App\Entity\TaskList;
use App\Form\TaskListTypeForm;


#[Route('/task_list')]
// #[Route('/column')]
#[IsGranted('ROLE_USER')]
class TaskListController extends AbstractController
{
    #[Route('task_list/{id}', name: 'app_tasklist_show', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function show(TaskList $taskList): Response
    {
        $this->denyAccessUnlessGranted('PROJECT_VIEW', $taskList->getProject());

        return $this->render('task_list/show.html.twig', [
            'taskList' => $taskList,
            'project' => $taskList->getProject(),
            'tasks' => $taskList->getTasks(),
        ]);
    }

    #[Route('task_list/{id}/tasks', name: 'app_tasklist_tasks', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function tasks(TaskList $taskList): Response
    {
        $this->denyAccessUnlessGranted('PROJECT_VIEW', $taskList->getProject());

        return $this->render('task_list/tasks.html.twig', [
            'taskList' => $taskList,
            'project' => $taskList->getProject(),
            'tasks' => $taskList->getTasks(),
        ]);
    }

    #[Route('task_list/new', name: 'app_column_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_CHEF_PROJET')]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $projectId = $request->request->get('project_id');
        $project = $entityManager->getRepository(Project::class)->find($projectId);

        if (!$project) {
            throw $this->createNotFoundException('Projet non trouvé');
        }

        $this->denyAccessUnlessGranted('PROJECT_EDIT', $project);

        $taskList = new TaskList();
        $form = $this->createForm(TaskListTypeForm::class, $taskList);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $taskList->setProject($project);
            $taskList->setPositionColumn($project->getTaskLists()->count());
            $taskList->setDateTime(new \DateTime());
            // Calculer la position
            $maxPosition = $entityManager->getRepository(TaskList::class)
                ->findMaxPositionByProject($project);
            $taskList->setPositionColumn($maxPosition + 1);
            $entityManager->persist($taskList);
            $entityManager->flush();

            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['success' => true, 'id' => $taskList->getId()]);
            }

            $this->addFlash('success', 'Colonne créée avec succès');
        }

        if ($form->isSubmitted() && $form->isValid()) {
            return $this->redirectToRoute('app_project_kanban', ['id' => $project->getId()]);
        }

        return $this->render('task_list/new.html.twig', [
            'task_list' => $taskList,
            'project' => $project,
            'form' => $form,
        ]);
    }

    #[Route('task_list/{id}/edit', name: 'app_column_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_CHEF_PROJET')]
    public function edit(Request $request, TaskList $taskList, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('PROJECT_EDIT', $taskList->getProject());

        $form = $this->createForm(TaskListTypeForm::class, $taskList);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['success' => true]);
            }

            $this->addFlash('success', 'Colonne modifiée avec succès');
            return $this->redirectToRoute('app_project_kanban', ['id' => $taskList->getProject()->getId()]);
        }

        return $this->render('task_list/edit.html.twig', [
            'task_list' => $taskList,
            'form' => $form,
        ]);
    }
    #[Route('task_list/{id}', name: 'app_task_list_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_CHEF_DE_PROJET')]
    public function delete(TaskList $taskList, EntityManagerInterface $entityManager): JsonResponse
    {
        $this->denyAccessUnlessGranted('edit', $taskList->getProject());

        // Vérifier qu'il n'y a pas de tâches dans cette colonne
        if ($taskList->getTasks()->count() > 0) {
            return new JsonResponse(['error' => 'Impossible de supprimer une colonne contenant des tâches'], 400);
        }

        $entityManager->remove($taskList);
        $entityManager->flush();
        return new JsonResponse(['success' => true]);
    }
    // #[Route('/{id}/delete', name: 'column_delete', methods: ['POST'])]
    // #[IsGranted('ROLE_CHEF_PROJET')]
    // public function delete(Request $request, TaskList $taskList, EntityManagerInterface $entityManager): Response
    // {
    //     $project = $taskList->getProject();
    //     $this->denyAccessUnlessGranted('PROJECT_EDIT', $project);

    //     if ($this->isCsrfTokenValid('delete'.$taskList->getId(), $request->request->get('_token'))) {
    //         $entityManager->remove($taskList);
    //         $entityManager->flush();

    //         if ($request->isXmlHttpRequest()) {
    //             return new JsonResponse(['success' => true]);
    //         }

    // $this->addFlash('success', 'Colonne supprimée');
    // return $this->redirectToRoute('project_kanban', ['id' => $project->getId()]);

}

        
    


    // #[Route('/t/a/s/k/l/i/s/t', name: 'app_t_a_s_k_l_i_s_t')]
    // public function index(): Response
    // {
    //     return $this->render('tasklist/index.html.twig', [
    //         'controller_name' => 'TaskListController',
    //     ]);
    // }
