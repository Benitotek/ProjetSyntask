<?php

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\User;
use App\Entity\Task;
use App\Enum\ActivityType;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use App\Form\CommentTypeForm;
use App\Repository\CommentRepository;
use App\Service\ActivityLogger;
use Doctrine\ORM\EntityManagerInterface;
// use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use App\Service\NotificationService;
class CommentController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private ActivityLogger $activityLogger;
    private NotificationService $notificationService;

    public function __construct(
        EntityManagerInterface $entityManager,
        ActivityLogger $activityLogger,
        NotificationService $notificationService
    ) {
        $this->entityManager = $entityManager;
        $this->activityLogger = $activityLogger;
        $this->notificationService = $notificationService;
    }
#[Route('/task/{id}/comments', name: 'app_task_comments')]
#[IsGranted('ROLE_EMPLOYE')]
public function index(
    Task $task,
    CommentRepository $commentRepository,
    Request $request,
    EntityManagerInterface $em
): Response {
    $this->denyAccessUnlessGranted('VIEW', $task);

    $comments = $commentRepository->findByTask($task);

    $comment = new Comment();
    $comment->setTask($task); // lie le commentaire à la tâche
    $form = $this->createForm(CommentTypeForm::class, $comment);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        $comment->setAuteur($this->getUser()); // si tu as une relation avec User
        $em->persist($comment);
        $em->flush();

        return $this->redirectToRoute('app_task_comments', ['id' => $task->getId()]);
    }

    return $this->render('task/comments.html.twig', [
        'task' => $task,
        'comments' => $comments,
        'commentForm' => $form->createView(),
    ]);
}
    #[Route('/task/{id}/comment/add', name: 'app_task_comment_add', methods: ['POST'])]
    #[IsGranted('ROLE_EMPLOYE')]
    public function add(Task $task, Request $request): Response
    {
        $this->denyAccessUnlessGranted('VIEW', $task);

        $comment = new Comment();
        $comment->setTask($task)
            ->setAuteur($this->getUser());

        $form = $this->createForm(CommentTypeForm::class, $comment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($comment);
            $this->entityManager->flush();

            // Enregistrer l'activité
            $this->activityLogger->logActivity(
            $this->getUser(),
            'a commenté la tâche',
            $task->getTitle(),
            'task_comment',
            $task->getId()
            );
           
            $this->addFlash('success', 'Commentaire ajouté avec succès.');

            // Si la requête est en AJAX, retourner le commentaire en JSON
            if ($request->isXmlHttpRequest()) {
                return $this->json([
                    'success' => true,
                    'comment' => [
                        'id' => $comment->getId(),
                        'content' => $comment->getContenu(),
                        'author' => $comment->getAuteur()->getPrenom() . ' ' . $comment->getAuteur()->getNom(),
                        'date' => $comment->getDateCreation()->format('d/m/Y H:i'),
                    ],
                ]);
            }

            return $this->redirectToRoute('app_task_show', ['id' => $task->getId()]);
        }

        // Si le formulaire n'est pas valide et que c'est une requête AJAX
        if ($request->isXmlHttpRequest()) {
            return $this->json([
                'success' => false,
                'errors' => $this->getFormErrors($form),
            ], 400);
        }

        return $this->redirectToRoute('app_task_show', ['id' => $task->getId()]);
    }

    #[Route('/comment/{id}/edit', name: 'app_comment_edit', methods: ['POST'])]
    #[IsGranted('ROLE_EMPLOYE')]
    public function edit(Comment $comment, Request $request): Response
    {
        // Seul l'auteur ou un admin peut modifier un commentaire
        if ($comment->getAuteur() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à modifier ce commentaire.');
        }

        $form = $this->createForm(CommentTypeForm::class, $comment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $comment->setdateMaj(new \DateTime());
            $this->entityManager->flush();

            $this->addFlash('success', 'Commentaire modifié avec succès.');

            // Si la requête est en AJAX
            if ($request->isXmlHttpRequest()) {
                return $this->json([
                    'success' => true,
                    'comment' => [
                        'id' => $comment->getId(),
                        'content' => $comment->getContenu(),
                        'date' => $comment->getdateMaj()->format('d/m/Y H:i'),
                    ],
                ]);
            }

            return $this->redirectToRoute('app_task_show', ['id' => $comment->getTask()->getId()]);
        }

        // Si le formulaire n'est pas valide et que c'est une requête AJAX
        if ($request->isXmlHttpRequest()) {
            return $this->json([
                'success' => false,
                'errors' => $this->getFormErrors($form),
            ], 400);
        }

        return $this->redirectToRoute('app_task_show', ['id' => $comment->getTask()->getId()]);
    }

    #[Route('/comment/{id}/delete', name: 'app_comment_delete', methods: ['POST'])]
    #[IsGranted('ROLE_EMPLOYE')]
    public function delete(Comment $comment, Request $request): Response
    {
        // Vérifier le token CSRF
        if (!$this->isCsrfTokenValid('delete' . $comment->getId(), $request->request->get('_token'))) {
            return $this->json(['success' => false, 'message' => 'Token CSRF invalide'], 400);
        }

        // Seul l'auteur, un chef de projet ou un admin peut supprimer un commentaire
        if (
            $comment->getAuteur() !== $this->getUser()
            && !$this->isGranted('ROLE_CHEF_PROJET')
            && !$this->isGranted('ROLE_ADMIN')
        ) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à supprimer ce commentaire.');
        }
        $taskId = $comment->getTask()->getId();

        $this->entityManager->remove($comment);
        $this->entityManager->flush();

        $this->addFlash('success', 'Commentaire supprimé avec succès.');

        // Si la requête est en AJAX
        if ($request->isXmlHttpRequest()) {
            return $this->json([
                'success' => true,
                'message' => 'Commentaire supprimé avec succès.',
            ]);
        }

        return $this->redirectToRoute('app_task_show', ['id' => $taskId]);
    }

    /**
     * Utilitaire pour récupérer les erreurs du formulaire pour les requêtes AJAX
     */
    private function getFormErrors($form): array
    {
        $errors = [];
        foreach ($form->getErrors(true) as $error) {
            $errors[] = $error->getMessage();
        }
        return $errors;
    }
}

    // #[Route('/comment', name: 'app_comment')]
    // public function index(): Response
    // {
    //     return $this->render('comment/index.html.twig', [
    //         'controller_name' => 'CommentController',
    //     ]);
    // }
