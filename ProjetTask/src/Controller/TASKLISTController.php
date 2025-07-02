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
use App\Form\TaskListType;
use App\Repository\TaskListRepository;
use Doctrine\Migrations\Version\Version;

// Test Version 2-3 a voir fait le 02/07/2025
#[Route('/tasklist')]
class TaskListController extends AbstractController
{
    /**
     * Création d'une nouvelle colonne dans le kanban
     */
    #[Route('/new/{projectId}', name: 'app_tasklist_new', methods: ['GET', 'POST'])]
    public function new(
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
    public function reorderColumns(
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



// Version 1 VS mais modif et ajout a faire 01/07/2025
// #[Route('/project/{projectId}/task-list')]
// #[IsGranted('ROLE_EMPLOYE')]
// class TaskListController extends AbstractController
// {
//     /**
//      * Créer une nouvelle colonne
//      */
//     #[Route('/new', name: 'app_task_list_new', methods: ['GET', 'POST'])]
//     public function new(
//         int $projectId,
//         Request $request,
//         EntityManagerInterface $entityManager,
//         TaskListRepository $taskListRepository
//     ): Response {
//         $project = $entityManager->getRepository(Project::class)->find($projectId);
        
//         if (!$project) {
//             throw $this->createNotFoundException('Projet non trouvé');
//         }
        
//         // Vérifier les permissions
//         $this->denyAccessUnlessGranted('EDIT', $project);
        
//         $taskList = new TaskList();
//         $taskList->setProject($project);
        
//         // Déterminer la prochaine position
//         $nextPosition = $taskListRepository->findMaxPositionByProject($project) + 1;
//         $taskList->setPositionColumn($nextPosition);
        
//         $form = $this->createForm(TaskListType::class, $taskList);
//         $form->handleRequest($request);
        
//         if ($form->isSubmitted() && $form->isValid()) {
//             $entityManager->persist($taskList);
//             $entityManager->flush();
            
//             $this->addFlash('success', 'Colonne créée avec succès');
//             return $this->redirectToRoute('app_project_view_kanban', ['id' => $projectId]);
//         }
        
//         return $this->render('task_list/new.html.twig', [
//             'project' => $project,
//             'form' => $form->createView(),
//         ]);
//     }
    
//     /**
//      * Modifier une colonne
//      */
//     #[Route('/{id}/edit', name: 'app_task_list_edit', methods: ['GET', 'POST'])]
//     public function edit(
//         int $projectId,
//         TaskList $taskList,
//         Request $request,
//         EntityManagerInterface $entityManager
//     ): Response {
//         $project = $entityManager->getRepository(Project::class)->find($projectId);
        
//         if (!$project || $taskList->getProject() !== $project) {
//             throw $this->createNotFoundException('Colonne ou projet non trouvé');
//         }
        
//         // Vérifier les permissions
//         $this->denyAccessUnlessGranted('EDIT', $project);
        
//         $form = $this->createForm(TaskListType::class, $taskList);
//         $form->handleRequest($request);
        
//         if ($form->isSubmitted() && $form->isValid()) {
//             $entityManager->flush();
            
//             $this->addFlash('success', 'Colonne modifiée avec succès');
//             return $this->redirectToRoute('app_project_view_kanban', ['id' => $projectId]);
//         }
        
//         return $this->render('task_list/edit.html.twig', [
//             'project' => $project,
//             'task_list' => $taskList,
//             'form' => $form->createView(),
//         ]);
//     }
    
//     /**
//      * Supprimer une colonne
//      */
//     #[Route('/{id}/delete', name: 'app_task_list_delete', methods: ['POST'])]
//     public function delete(
//         int $projectId,
//         TaskList $taskList,
//         Request $request,
//         EntityManagerInterface $entityManager,
//         TaskListRepository $taskListRepository
//     ): Response {
//         $project = $entityManager->getRepository(Project::class)->find($projectId);
        
//         if (!$project || $taskList->getProject() !== $project) {
//             throw $this->createNotFoundException('Colonne ou projet non trouvé');
//         }
        
//         // Vérifier les permissions
//         $this->denyAccessUnlessGranted('EDIT', $project);
        
//         if ($this->isCsrfTokenValid('delete'.$taskList->getId(), $request->request->get('_token'))) {
//             // Vérifier si la colonne contient des tâches
//             if (!$taskList->getTasks()->isEmpty()) {
//                 $this->addFlash('error', 'Impossible de supprimer une colonne contenant des tâches');
//                 return $this->redirectToRoute('app_project_view_kanban', ['id' => $projectId]);
//             }
            
//             $entityManager->remove($taskList);
//             $entityManager->flush();
            
//             // Réorganiser les positions des colonnes restantes
//             $taskListRepository->reorganizePositions($project);
            
//             $this->addFlash('success', 'Colonne supprimée avec succès');
//         }
        
//         return $this->redirectToRoute('app_project_view_kanban', ['id' => $projectId]);
//     }
    
//     /**
//      * Réordonner les colonnes (AJAX)
//      */
//     #[Route('/reorder', name: 'app_task_list_reorder', methods: ['POST'])]
//     public function reorder(
//         int $projectId,
//         Request $request,
//         EntityManagerInterface $entityManager,
//         TaskListRepository $taskListRepository
//     ): Response {
//         $project = $entityManager->getRepository(Project::class)->find($projectId);
        
//         if (!$project) {
//             return $this->json(['success' => false, 'message' => 'Projet non trouvé'], 404);
//         }
        
//         // Vérifier les permissions
//         if (!$this->isGranted('EDIT', $project)) {
//             return $this->json(['success' => false, 'message' => 'Permission refusée'], 403);
//         }
        
//         $data = json_decode($request->getContent(), true);
        
//         if (isset($data['columns']) && is_array($data['columns'])) {
//             $taskListRepository->reorderColumns($project, $data['columns']);
//             return $this->json(['success' => true]);
//         }
        
//         return $this->json(['success' => false, 'message' => 'Données invalides'], 400);
//     }
// }
