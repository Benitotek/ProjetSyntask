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

// Test Version 3 a voir fait le 10/07/2025
// #[Route('/tasklist')]
class TaskListController extends AbstractController
{
    /**
     * Affiche la vue Kanban d'un projet
     */
    #[Route('/project/{id}/kanban', name: 'app_project_kanban', methods: ['GET'])]
    public function kanban(
        Project $project,
        TaskListRepository $taskListRepository,
        EntityManagerInterface $entityManager
    ): Response {
        // Vérifier que l'utilisateur a le droit de voir ce projet
        if (!$this->canViewProject($project)) {
            throw $this->createAccessDeniedException('Vous n\'avez pas les droits pour voir ce projet');
        }

        // Récupérer les colonnes avec leurs tâches
        $taskLists = $taskListRepository->findByProjectWithTasks($project);

        // Si aucune colonne n'existe, en créer par défaut
        if (empty($taskLists)) {
            $this->createDefaultTaskLists($project, $entityManager);
            $taskLists = $taskListRepository->findByProjectWithTasks($project);
        }

        // Récupérer les membres du projet pour l'assignation des tâches
        $members = $project->getMembres()->toArray();
        if (!in_array($project->getChefProjet(), $members)) {
            $members[] = $project->getChefProjet();
        }

        return $this->render('tasklist/kanban.html.twig', [
            'project' => $project,
            'taskLists' => $taskLists,
            'members' => $members,
        ]);
    }

    /**
     * Crée les colonnes par défaut pour un projet
     */
    private function createDefaultTaskLists(Project $project, EntityManagerInterface $entityManager): void
    {
        $defaultLists = [
            ['nom' => 'À faire', 'couleur' => TaskListColor::ORANGE, 'position' => 1],
            ['nom' => 'En cours', 'couleur' => TaskListColor::JAUNE, 'position' => 2],
            ['nom' => 'Terminé', 'couleur' => TaskListColor::VERT, 'position' => 3],
            ['nom' => 'En retard', 'couleur' => TaskListColor::ROUGE, 'position' => 4]
        ];

        foreach ($defaultLists as $index => $listData) {
            $taskList = new TaskList();
            $taskList->setNom($listData['nom']);
            $taskList->setPositionColumn($listData['position']);
            $taskList->setProject($project);
            $taskList->setCouleur($listData['couleur']);

            $entityManager->persist($taskList);
        }

        $entityManager->flush();
    }

    /**
     * Vérifie si l'utilisateur peut voir un projet
     */
    private function canViewProject($project): bool
    {
        $user = $this->getUser();

        if (!$user) {
            return false;
        }

        // Les administrateurs et directeurs peuvent tout voir
        if ($this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_DIRECTEUR')) {
            return true;
        }

        // Les chefs de projet peuvent voir les projets qu'ils dirigent
        if ($project->getChefProjet() === $user) {
            return true;
        }

        // Les membres du projet peuvent voir le projet
        return $project->getMembres()->contains($user);
    }
    /**
     * Affiche le formulaire pour créer une nouvelle colonne
     */
    #[Route('/project/{projectId}/tasklist/new', name: 'app_tasklist_new', methods: ['GET', 'POST'])]
    public function ViewformColumn(
        Request $request,
        EntityManagerInterface $entityManager,
        int $projectId
    ): Response {
        $project = $entityManager->getRepository(Project::class)->find($projectId);

        if (!$project) {
            throw $this->createNotFoundException('Projet non trouvé');
        }

        // Vérifier que l'utilisateur a le droit de modifier ce projet
        if (!$this->isGranted('ROLE_ADMIN') && $project->getChefProjet() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous n\'avez pas les droits pour modifier ce projet');
        }

        $taskList = new TaskList();
        $taskList->setProject($project);

        // Déterminer la position de la nouvelle colonne
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

        // Vérifier que l'utilisateur a le droit de modifier ce projet
        if (!$this->isGranted('ROLE_ADMIN') && $project->getChefProjet() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous n\'avez pas les droits pour modifier ce projet');
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

        // Vérifier que l'utilisateur a le droit de modifier ce projet
        if (!$this->isGranted('ROLE_ADMIN') && $project->getChefProjet() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous n\'avez pas les droits pour modifier ce projet');
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
     * Réorganise les colonnes d'un projet
     */
    #[Route('/project/{projectId}/tasklists/reorder', name: 'app_tasklist_reorder', methods: ['POST'])]
    public function reorderColumns(
        Request $request,
        EntityManagerInterface $entityManager,
        int $projectId
    ): Response {
        $project = $entityManager->getRepository(Project::class)->find($projectId);

        if (!$project) {
            throw $this->createNotFoundException('Projet non trouvé');
        }

        // Vérifier que l'utilisateur a le droit de modifier ce projet
        if (!$this->isGranted('ROLE_ADMIN') && $project->getChefProjet() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous n\'avez pas les droits pour modifier ce projet');
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
    #[Route('/new/{projectId}', name: 'app_tasklist_new', methods: ['GET', 'POST'])]
    public function newColum(
        Request $request,
        int $projectId,
        EntityManagerInterface $entityManager,
        TaskListRepository $taskListRepository
    ): Response {
        $project = $entityManager->getRepository(Project::class)->find($projectId);

        if (!$project) {
            throw $this->createNotFoundException('Le projet n\'existe pas');
        }

        // Vérifier que l'utilisateur a le droit de modifier ce projet
        if (!$this->canModifyProject($project)) {
            throw $this->createAccessDeniedException('Vous n\'avez pas les droits pour ajouter une colonne à ce projet');
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
            return $this->redirectToRoute('app_projet_kanban', ['id' => $project->getId()]);
        }

        if ($request->isXmlHttpRequest()) {
            return $this->render('tasklist/_form_modal.html.twig', [
                'tasklist' => $taskList,
                'form' => $form,
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
    #[Route('/{id}/edit', name: 'app_tasklist_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, TaskList $taskList, EntityManagerInterface $entityManager): Response
    {
        $project = $taskList->getProject();

        // Vérifier que l'utilisateur a le droit de modifier ce projet
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
            return $this->redirectToRoute('app_projet_kanban', ['id' => $project->getId()]);
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
    #[Route('/{id}', name: 'app_tasklist_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        TaskList $taskList,
        EntityManagerInterface $entityManager,
        TaskListRepository $taskListRepository
    ): Response {
        $project = $taskList->getProject();

        // Vérifier que l'utilisateur a le droit de modifier ce projet
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
            return $this->redirectToRoute('app_projet_kanban', ['id' => $project->getId()]);
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

        return $this->redirectToRoute('app_projet_kanban', ['id' => $project->getId()]);
    }

    /**
     * Réordonner les colonnes (drag & drop)
     */
    #[Route('/reorder/{projectId}', name: 'app_tasklist_reorder', methods: ['POST'])]
    public function DragDropReorderColumns(
        Request $request,
        int $projectId,
        EntityManagerInterface $entityManager,
        TaskListRepository $taskListRepository
    ): JsonResponse {
        $project = $entityManager->getRepository(Project::class)->find($projectId);

        if (!$project) {
            return new JsonResponse(['error' => 'Projet non trouvé'], 404);
        }

        // Vérifier que l'utilisateur a le droit de modifier ce projet
        if (!$this->canModifyProject($project)) {
            return new JsonResponse(['error' => 'Vous n\'avez pas les droits pour réorganiser ces colonnes'], 403);
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['columns']) || !is_array($data['columns'])) {
            return new JsonResponse(['error' => 'Données invalides'], 400);
        }

        // Vérifier que toutes les colonnes appartiennent au projet
        foreach ($data['columns'] as $columnData) {
            if (!isset($columnData['id'])) {
                continue;
            }

            $taskList = $taskListRepository->find($columnData['id']);

            if (!$taskList || $taskList->getProject() !== $project) {
                return new JsonResponse(['error' => 'Une colonne n\'appartient pas à ce projet'], 400);
            }
        }

        // Réordonner les colonnes
        $taskListRepository->reorderColumns($project, $data['columns']);

        return new JsonResponse(['success' => true]);
    }

    /**
     * Vérifie si l'utilisateur peut modifier un projet
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

        // Les chefs de projet peuvent modifier les projets qu'ils dirigent
        return $project->getChef_Projet() === $user;
    }
}
