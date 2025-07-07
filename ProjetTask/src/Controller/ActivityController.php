<?php

namespace App\Controller;

use App\Repository\ActivityRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class ActivityController extends AbstractController
{

    /**
     * @param ActivityRepository $activityRepository
     * @return Response
     */
#[Route('/activities', name: 'app_activities')]
    #[IsGranted('ROLE_USER')]
    public function index(ActivityRepository $activityRepository): Response
    {
        // Récupérer l'utilisateur connecté
        $user = $this->getUser();
        
        // Récupérer les activités récentes de l'utilisateur
        $activities = $activityRepository->findByUser($user, 20);
        
        return $this->render('activity/index.html.twig', [
            'activities' => $activities,
        ]);
    }

    #[Route('/activities/all', name: 'app_activities_all')]
    #[IsGranted('ROLE_ADMIN')]
    public function all(ActivityRepository $activityRepository): Response
    {
        // Récupérer toutes les activités récentes (limité aux 50 dernières)
        $activities = $activityRepository->findRecent(50);
        
        return $this->render('activity/all.html.twig', [
            'activities' => $activities,
        ]);
    }

    #[Route('/project/{id}/activities', name: 'app_project_activities')]
    public function projectActivities(string $id, ActivityRepository $activityRepository): Response
    {
        // Récupérer les activités liées à ce projet
        $activities = $activityRepository->findByProject($id, 20);
        
        return $this->render('activity/project.html.twig', [
            'activities' => $activities,
            'projectId' => $id,
        ]);
    }
    // Ajoutez cette route à votre contrôleur ActivityController
#[Route('/', name: 'app_activity_index')]
public function activityIndex(ActivityRepository $activityRepository): Response
{
    // Récupérer toutes les activités récentes (limité aux 20 dernières)
    $activities = $activityRepository->findRecent(20);
    
    return $this->render('activity/index.html.twig', [
        'activities' => $activities,
    ]);
}
}

// {
//     #[Route('/activity', name: 'app_activity')]
//     public function index(): Response
//     {
//         return $this->render('activity/index.html.twig', [
//             'controller_name' => 'ActivityController',
//         ]);
//     }
// }
