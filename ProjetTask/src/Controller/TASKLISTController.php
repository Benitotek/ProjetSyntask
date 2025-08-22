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
use App\Enum\TaskListColor;
use App\Form\TaskListType;
use App\Repository\TaskListRepository;
use Doctrine\Migrations\Version\Version;

/**
 * Controller pour gérer les colonnes de tâches (TaskList) dans un project
 */

class TaskListController extends AbstractController
{

    /**
     * Affiche la liste des colonnes d'un project
     */
    #[Route('/tasklists/{id}', name: 'app_tasklist_show', methods: ['GET'])]
    public function show(TaskList $taskList): Response
    {
        return $this->render('task_list/show.html.twig', [
            'taskList' => $taskList,
        ]);
    }

    /**
     * Vérifie si l'utilisateur peut voir un project
     */
   
    private function canViewProject(Project $project): bool
    {
        // Toujours vérifier si l'utilisateur existe
        $user = $this->getUser();
        if (!$user) {
            return false;
        }

        // Vérification explicite du rôle admin/directeur
        if ($this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_DIRECTEUR')) {
            return true;
        }

        // Vérification du chef de projet 
        if ($project->getChefproject() && $project->getChefproject()->getId() === $user->getUserIdentifier()) {
            return true;
        }

        // Vérification de l'appartenance comme membre
        foreach ($project->getMembres() as $membre) {
            if ($membre->getId() === $user->getUserIdentifier()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Affiche le formulaire pour créer une nouvelle colonne
     */
    #[Route('/project/tasklist/new', name: 'app_tasklist_new', methods: ['GET', 'POST'])]
    public function ViewformColumn(
        Request $request,
        EntityManagerInterface $entityManager,
        int $projectId
    ): Response {
        $project = $entityManager->getRepository(Project::class)->find($projectId);

        if (!$project) {
            throw $this->createNotFoundException('project non trouvé');
        }

        // Vérifier que l'utilisateur a le droit de modifier ce project

        if (!$this->isGranted('ROLE_ADMIN') && $project->getChefproject() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous n\'avez pas les droits pour modifier ce project');
        }

        $taskList = new TaskList();
        $taskList->setProject($project);
        $taskList->setCouleur(TaskListColor::BLEU); // Couleur par défaut

        // DéTERMINERr la position de la nouvelle colonne
        $lastPosition = $entityManager->getRepository(TaskList::class)
            ->findLastPositionForProject($project);
        $taskList->setPositionColumn($lastPosition + 1);

        $form = $this->createForm(TaskListType::class, $taskList);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($taskList);
            $entityManager->flush();

            if ($request->isXmlHttpRequest()) {
                return $this->json(['success' => true]);
            }

            $this->addFlash('success', 'Colonne créée avec succès');
            return $this->redirectToRoute('app_project_kanban', ['id' => $project->getId()]);
        }

        if ($request->isXmlHttpRequest()) {
            return $this->render('tasklist/_form_modal.html.twig', [
                'form' => $form->createView(),
            ]);
        }

        return $this->render('tasklist/new.html.twig', [
            'tasklist' => $taskList,
            'form' => $form->createView(),
            'project' => $project,
        ]);
    }
    /**
     * Affiche le formulaire pour modifier une colonne
     */
    #[Route('/tasklist/{id}/edit', name: 'app_tasklist_edit', methods: ['GET', 'POST'])]
    public function EditformColumn(
        Request $request,
        TaskList $taskList,
        EntityManagerInterface $entityManager
    ): Response {
        $project = $taskList->getProject();

        // Vérifier que l'utilisateur a le droit de modifier ce project
        if (!$this->isGranted('ROLE_ADMIN') && $project->getChefproject() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous n\'avez pas les droits pour modifier ce project');
        }

        $form = $this->createForm(TaskListType::class, $taskList);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            if ($request->isXmlHttpRequest()) {
                return $this->json(['success' => true]);
            }

            $this->addFlash('success', 'Colonne modifiée avec succès');
            return $this->redirectToRoute('app_project_kanban', ['id' => $project->getId()]);
        }

        if ($request->isXmlHttpRequest()) {
            return $this->render('tasklist/_form_modal.html.twig', [
                'form' => $form->createView(),
            ]);
        }

        return $this->render('tasklist/edit.html.twig', [
            'tasklist' => $taskList,
            'form' => $form->createView(),
            'project' => $project,
        ]);
    }
    /**
     * Supprime une colonne
     */
    #[Route('/tasklist/{id}/delete', name: 'app_tasklist_delete', methods: ['POST'])]
    public function deleteColumn(
        Request $request,
        TaskList $taskList,
        EntityManagerInterface $entityManager
    ): Response {
        $project = $taskList->getProject();

        // Vérifier que l'utilisateur a le droit de modifier ce project
        if (!$this->isGranted('ROLE_ADMIN') && $project->getChefproject() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous n\'avez pas les droits pour modifier ce project');
        }

        // Vérifier le token CSRF
        if ($this->isCsrfTokenValid('delete' . $taskList->getId(), $request->request->get('_token'))) {
            $entityManager->remove($taskList);
            $entityManager->flush();
            $this->addFlash('success', 'Colonne supprimée avec succès');
        } else {
            $this->addFlash('error', 'Token CSRF invalide');
        }

        return $this->redirectToRoute('app_project_kanban', ['id' => $project->getId()]);
    }
    /**
     * Réorganise les colonnes d'un project
     */
    #[Route('/project/tasklists/reorder', name: 'app_tasklist_reorder', methods: ['POST'])]
    public function reorderColumns(
        Request $request,
        EntityManagerInterface $entityManager,
        int $projectId
    ): Response {
        $project = $entityManager->getRepository(Project::class)->find($projectId);

        if (!$project) {
            throw $this->createNotFoundException('project non trouvé');
        }

        // Vérifier que l'utilisateur a le droit de modifier ce project
        if (!$this->isGranted('ROLE_ADMIN') && $project->getChefproject() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous n\'avez pas les droits pour modifier ce project');
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['columns']) || !is_array($data['columns'])) {
            return $this->json(['success' => false, 'message' => 'Données invalides'], 400);
        }

        $columns = $data['columns'];

        // Mettre à jour les positions
        foreach ($columns as $columnData) {
            $taskList = $entityManager->getRepository(TaskList::class)->find($columnData['id']);

            if ($taskList && $taskList->getProject()->getId() === $project->getId()) {
                $taskList->setPositionColumn($columnData['position']);
            }
        }

        $entityManager->flush();

        return $this->json(['success' => true]);
    }



    /**
     * Création d'une nouvelle colonne dans le kanban
     */
    #[Route('/project/tasklists/new', name: 'app_tasklist_new', methods: ['GET', 'POST'])]
    public function newColum(
        Request $request,
        int $projectId,
        EntityManagerInterface $entityManager,
        TaskListRepository $taskListRepository
    ): Response {
        $project = $entityManager->getRepository(Project::class)->find($projectId);

        if (!$project) {
            throw $this->createNotFoundException('Le project n\'existe pas');
        }

        // Vérifier que l'utilisateur a le droit de modifier ce project
        if (!$this->canModifyProject($project)) {
            throw $this->createAccessDeniedException('Vous n\'avez pas les droits pour ajouter une colonne à ce project');
        }

        $taskList = new TaskList();
        $taskList->setProject($project);

        // Définir la position de la nouvelle colonne
        $maxPosition = $taskListRepository->findMaxPositionByProject($project);
        $taskList->setPositionColumn($maxPosition + 1);

        $form = $this->createForm(TaskListType::class, $taskList);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($taskList);
            $entityManager->flush();

            if ($request->isXmlHttpRequest()) {
                return new JsonResponse([
                    'success' => true,
                    'id' => $taskList->getId(),
                    'nom' => $taskList->getNom(),
                ]);
            }

            $this->addFlash('success', 'Colonne ajoutée avec succès');
            return $this->redirectToRoute('app_project_kanban', ['id' => $project->getId()]);
        }

        if ($request->isXmlHttpRequest()) {
            return $this->render('tasklist/_form_modal.html.twig', [
                'tasklist' => $taskList,
                'form' => $form->createView(),
            ]);
        }

        return $this->render('tasklist/new.html.twig', [
            'tasklist' => $taskList,
            'form' => $form,
            'project' => $project,
        ]);
    }

    /**
     * Modification d'une colonne
     */
    #[Route('/project/tasklists/{id}/edit', name: 'app_tasklist_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, TaskList $taskList, EntityManagerInterface $entityManager): Response
    {
        $project = $taskList->getProject();

        // Vérifier que l'utilisateur a le droit de modifier ce project
        if (!$this->canModifyProject($project)) {
            throw $this->createAccessDeniedException('Vous n\'avez pas les droits pour modifier cette colonne');
        }

        $form = $this->createForm(TaskListType::class, $taskList);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            if ($request->isXmlHttpRequest()) {
                return new JsonResponse([
                    'success' => true,
                    'id' => $taskList->getId(),
                    'nom' => $taskList->getNom(),
                ]);
            }

            $this->addFlash('success', 'Colonne modifiée avec succès');
            return $this->redirectToRoute('app_project_kanban', ['id' => $project->getId()]);
        }

        if ($request->isXmlHttpRequest()) {
            return $this->render('tasklist/_form_modal.html.twig', [
                'tasklist' => $taskList,
                'form' => $form,
            ]);
        }

        return $this->render('tasklist/edit.html.twig', [
            'tasklist' => $taskList,
            'form' => $form,
            'project' => $project,
        ]);
    }

    /**
     * Suppression d'une colonne
     */
    #[Route('/project/tasklists/{id}/delete', name: 'app_tasklist_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        TaskList $taskList,
        EntityManagerInterface $entityManager,
        TaskListRepository $taskListRepository
    ): Response {
        $project = $taskList->getProject();

        // Vérifier que l'utilisateur a le droit de modifier ce project
        if (!$this->canModifyProject($project)) {
            throw $this->createAccessDeniedException('Vous n\'avez pas les droits pour supprimer cette colonne');
        }

        // Vérifier si la colonne contient des tâches
        if (!$taskList->getTasks()->isEmpty()) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Cette colonne contient des tâches et ne peut pas être supprimée'
                ], 400);
            }

            $this->addFlash('error', 'Cette colonne contient des tâches et ne peut pas être supprimée');
            return $this->redirectToRoute('app_project_kanban', ['id' => $project->getId()]);
        }

        if ($this->isCsrfTokenValid('delete' . $taskList->getId(), $request->request->get('_token'))) {
            $entityManager->remove($taskList);
            $entityManager->flush();

            // Réorganiser les positions des colonnes
            $taskListRepository->reorganizePositions($project);

            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['success' => true]);
            }

            $this->addFlash('success', 'Colonne supprimée avec succès');
        }

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(['success' => false], 400);
        }

        return $this->redirectToRoute('app_project_kanban', ['id' => $project->getId()]);
    }

    /**
     * Réordonner les colonnes (drag & drop)
     */
    #[Route('/project/tasklists/drag/reorder', name: 'app_tasklist_reorder', methods: ['POST'])]
    public function DragDropReorderColumns(
        Request $request,
        int $projectId,
        EntityManagerInterface $entityManager,
        TaskListRepository $taskListRepository
    ): JsonResponse {
        $project = $entityManager->getRepository(Project::class)->find($projectId);

        if (!$project) {
            return new JsonResponse(['error' => 'project non trouvé'], 404);
        }

        // Vérifier que l'utilisateur a le droit de modifier ce project
        if (!$this->canModifyProject($project)) {
            return new JsonResponse(['error' => 'Vous n\'avez pas les droits pour réorganiser ces colonnes'], 403);
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['columns']) || !is_array($data['columns'])) {
            return new JsonResponse(['error' => 'Données invalides'], 400);
        }

        // Vérifier que toutes les colonnes appartiennent au project
        foreach ($data['columns'] as $columnData) {
            if (!isset($columnData['id'])) {
                continue;
            }

            $taskList = $taskListRepository->find($columnData['id']);

            if (!$taskList || $taskList->getProject() !== $project) {
                return new JsonResponse(['error' => 'Une colonne n\'appartient pas à ce project'], 400);
            }
        }

        // Réordonner les colonnes
        $taskListRepository->reorderColumns($project, $data['columns']);

        return new JsonResponse(['success' => true]);
    }

    /**
     * Vérifie si l'utilisateur peut modifier un project
     */
    private function canModifyProject($project): bool
    {
        $user = $this->getUser();

        if (!$user) {
            return false;
        }

        // Les administrateurs et directeurs peuvent tout modifier
        if ($this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_DIRECTEUR')) {
            return true;
        }

        // Les chefs de projet peuvent modifier les projet qu'ils dirigent
        // Corriger cette ligne qui utilise getCHEF_PROJECT au lieu de getChefproject
        return $project->getChefproject() === $user;
    }
}
