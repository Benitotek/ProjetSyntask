<?php

namespace App\Controller;

use App\Entity\Notification;
use App\Repository\NotificationRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/notifications')]

class NotificationController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private NotificationService $notificationService;

    public function __construct(
        EntityManagerInterface $entityManager,
        NotificationService $notificationService
    ) {
        $this->entityManager = $entityManager;
        $this->notificationService = $notificationService;
    }

    #[Route('/', name: 'app_notifications')]
    #[IsGranted('ROLE_EMPLOYE')]
    public function index(NotificationRepository $notificationRepository): Response
    {
        $user = $this->getUser();
        $notifications = $notificationRepository->findBy(
            ['user' => $user],
            ['dateCreation' => 'DESC']
        );

        return $this->render('notification/index.html.twig', [
            'notifications' => $notifications,
        ]);
    }

    #[Route('/mark-read/{id}', name: 'app_notification_mark_read', methods: ['POST'])]
    public function markAsRead(Notification $notification, Request $request): Response
    {
        // Vérifier que la notification appartient à l'utilisateur connecté
        if ($notification->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à accéder à cette notification.');
        }

        // Vérifier le token CSRF
        if (!$this->isCsrfTokenValid('mark_read' . $notification->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_notifications');
        }

        $notification->setEstLue(true);
        $this->entityManager->flush();

        $this->addFlash('success', 'Notification marquée comme lue.');

        // Si la requête est en AJAX
        if ($request->isXmlHttpRequest()) {
            return $this->json(['success' => true]);
        }
        // Rediriger vers la page précédente ou vers la liste des notifications
        $referer = $request->headers->get('referer');
        return $referer ? $this->redirect($referer) : $this->redirectToRoute('app_notifications');
    }

    #[Route('/mark-all-read', name: 'app_notifications_mark_all_read', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]

    public function markAllAsRead(Request $request): Response
    {
        // Vérifier le token CSRF
        if (!$this->isCsrfTokenValid('mark_all_read', $request->request->get('_token'))) {
            return $this->json(['success' => false, 'message' => 'Token CSRF invalide'], 400);
        }

        $this->notificationService->markAllAsRead($this->getUser());

        // Si la requête est en AJAX
        if ($request->isXmlHttpRequest()) {
            return $this->json(['success' => true]);
        }

        $this->addFlash('success', 'Toutes les notifications ont été marquées comme lues.');
        return $this->redirectToRoute('app_notifications');
    }

    #[Route('/delete/{id}', name: 'app_notification_delete', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function delete(Notification $notification, Request $request): Response
    {
        // Vérifier que la notification appartient à l'utilisateur connecté
        if ($notification->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à accéder à cette notification.');
        }

        // Vérifier le token CSRF
        if (!$this->isCsrfTokenValid('delete' . $notification->getId(), $request->request->get('_token'))) {
            return $this->json(['success' => false, 'message' => 'Token CSRF invalide'], 400);
        }

        $this->entityManager->remove($notification);
        $this->entityManager->flush();

        // Si la requête est en AJAX
        if ($request->isXmlHttpRequest()) {
            return $this->json(['success' => true]);
        }

        $this->addFlash('success', 'Notification supprimée.');
        return $this->redirectToRoute('app_notifications');
    }

    #[Route('/delete-all', name: 'app_notifications_delete_all', methods: ['POST'])]
    public function deleteAll(Request $request, NotificationRepository $notificationRepository): Response
    {
        // Vérifier le token CSRF
        if (!$this->isCsrfTokenValid('delete_all_notifications', $request->request->get('_token'))) {
            return $this->json(['success' => false, 'message' => 'Token CSRF invalide'], 400);
        }

        $user = $this->getUser();
        $notifications = $notificationRepository->findBy(['user' => $user]);

        foreach ($notifications as $notification) {
            $this->entityManager->remove($notification);
        }

        $this->entityManager->flush();

        // Si la requête est en AJAX
        if ($request->isXmlHttpRequest()) {
            return $this->json(['success' => true]);
        }

        $this->addFlash('success', 'Toutes les notifications ont été supprimées.');
        return $this->redirectToRoute('app_notifications');
    }

    #[Route('/api/unread-count', name: 'api_notifications_unread_count')]
    public function getUnreadCount(NotificationRepository $notificationRepository): Response
    {
        $count = $notificationRepository->countUnreadByUser($this->getUser());

        return $this->json([
            'count' => $count
        ]);
    }
    #[Route('/api/recent', name: 'api_notifications_recent')]
    #[IsGranted('ROLE_USER')]
    public function getRecent(NotificationRepository $notificationRepository): Response
    {
        $notifications = $notificationRepository->findRecentByUser($this->getUser(), 5);

        $formattedNotifications = array_map(function ($notification) {
            return [
                'id' => $notification->getId(),
                'titre' => $notification->getTitre(),
                'message' => $notification->getMessage(),
                'lien' => $notification->getLien(),
                'dateCreation' => $notification->getDateCreation()->format('d/m/Y H:i'),
                'estLue' => $notification->isEstLue(),
                'type' => $notification->getType(),
                'iconClass' => $notification->getIconClass()
            ];
        }, $notifications);

        return $this->json([
            'notifications' => $formattedNotifications
        ]);
    }
}
