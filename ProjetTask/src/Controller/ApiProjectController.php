<?php

namespace App\Controller;

use App\Entity\Project;
use App\Repository\ProjectRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/project')]
final class ApiProjectController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ProjectRepository $projectRepository,
        private UserRepository $userRepository,
        private ValidatorInterface $validator
    ) {}

    /**
     * Liste tous les projets accessibles à l'utilisateur
     */
    #[Route('/projects', name: 'api_projects_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 10);
        $search = $request->query->get('search', '');

        try {
            // Récupération des projets avec pagination
            $statut = $request->query->get('statut', '');
            $projects = $this->projectRepository->findByUserWithPagination($user, $page, $limit, $search);
            $totalProjects = $this->projectRepository->countByUser($user, $search);

            $data = [];
            foreach ($projects as $project) {
                $data[] = $this->serializeProject($project);
            }

            return $this->json([
                'success' => true,
                'data' => $data,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $totalProjects,
                    'pages' => ceil($totalProjects / $limit)
                ]
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des projets'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    #[Route('/projects/view', name: 'api_projects_view', methods: ['GET'])]
    public function indexView(ProjectRepository $projectRepository): Response
    {
        $user = $this->getUser();
        $projects = $projectRepository->findBy(['user' => $user]);

        return $this->render('api_project/index.html.twig', [
            'projects' => $projects,
        ]);
    }
    /**
     * Récupère un projet spécifique
     */
    #[Route('/{id}', name: 'api_projects_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): JsonResponse
    {
        try {
            $project = $this->projectRepository->find($id);

            if (!$project) {
                return $this->json([
                    'success' => false,
                    'message' => 'Projet non trouvé'
                ], Response::HTTP_NOT_FOUND);
            }

            // Vérification des permissions
            if (!$this->canAccessProject($project)) {
                return $this->json([
                    'success' => false,
                    'message' => 'Accès refusé'
                ], Response::HTTP_FORBIDDEN);
            }

            return $this->json([
                'success' => true,
                'data' => $this->serializeProject($project, true) // Détail complet
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération du projet'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Crée un nouveau projet
     */
    #[Route('', name: 'api_projects_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (!$data) {
                return $this->json([
                    'success' => false,
                    'message' => 'Données JSON invalides'
                ], Response::HTTP_BAD_REQUEST);
            }

            $project = new Project();
            $project->setTitre($data['nom'] ?? '');
            $project->setDescription($data['description'] ?? '');
            $project->setDateCreation(
                isset($data['dateDebut']) ? new \DateTime($data['dateDebut']) : new \DateTime()
            );
            $project->setDateButoir(
                isset($data['dateFin']) ? new \DateTime($data['dateFin']) : null
            );
            $project->setCreatedBy($this->getUser());
            $project->setDateCreation(new \DateTime());

            // Validation
            $errors = $this->validator->validate($project);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }
                return $this->json([
                    'success' => false,
                    'message' => 'Erreurs de validation',
                    'errors' => $errorMessages
                ], Response::HTTP_BAD_REQUEST);
            }

            $this->entityManager->persist($project);
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Projet créé avec succès',
                'data' => $this->serializeProject($project)
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la création du projet'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Met à jour un projet existant
     */
    #[Route('/{id}', name: 'api_projects_update', methods: ['PUT', 'PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        try {
            $project = $this->projectRepository->find($id);

            if (!$project) {
                return $this->json([
                    'success' => false,
                    'message' => 'Projet non trouvé'
                ], Response::HTTP_NOT_FOUND);
            }

            // Vérification des permissions
            if (!$this->canEditProject($project)) {
                return $this->json([
                    'success' => false,
                    'message' => 'Accès refusé'
                ], Response::HTTP_FORBIDDEN);
            }

            $data = json_decode($request->getContent(), true);

            if (!$data) {
                return $this->json([
                    'success' => false,
                    'message' => 'Données JSON invalides'
                ], Response::HTTP_BAD_REQUEST);
            }

            // Mise à jour des champs
            if (isset($data['nom'])) {
                $project->setTitre($data['nom']);
            }
            if (isset($data['description'])) {
                $project->setDescription($data['description']);
            }
            if (isset($data['dateDebut'])) {
                $project->setDateCreation(new \DateTime($data['dateDebut']));
            }
            if (isset($data['dateFin'])) {
                $project->setDateButoir(new \DateTime($data['dateFin']));
            }

            // Validation
            $errors = $this->validator->validate($project);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }
                return $this->json([
                    'success' => false,
                    'message' => 'Erreurs de validation',
                    'errors' => $errorMessages
                ], Response::HTTP_BAD_REQUEST);
            }

            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Projet mis à jour avec succès',
                'data' => $this->serializeProject($project)
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du projet'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Supprime un projet
     */
    #[Route('/{id}', name: 'api_projects_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        try {
            $project = $this->projectRepository->find($id);

            if (!$project) {
                return $this->json([
                    'success' => false,
                    'message' => 'Projet non trouvé'
                ], Response::HTTP_NOT_FOUND);
            }

            // Vérification des permissions
            if (!$this->canDeleteProject($project)) {
                return $this->json([
                    'success' => false,
                    'message' => 'Accès refusé'
                ], Response::HTTP_FORBIDDEN);
            }

            $this->entityManager->remove($project);
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Projet supprimé avec succès'
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression du projet'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Récupère les membres d'un projet
     */
    #[Route('/{id}/members', name: 'api_projects_members', methods: ['GET'])]
    public function members(int $id): JsonResponse
    {
        try {
            $project = $this->projectRepository->find($id);

            if (!$project) {
                return $this->json([
                    'success' => false,
                    'message' => 'Projet non trouvé'
                ], Response::HTTP_NOT_FOUND);
            }

            if (!$this->canAccessProject($project)) {
                return $this->json([
                    'success' => false,
                    'message' => 'Accès refusé'
                ], Response::HTTP_FORBIDDEN);
            }

            $members = [];
            foreach ($project->getMembres() as $member) {
                $members[] = [
                    'id' => $member->getId(),
                    'nom' => $member->getNom(),
                    'prenom' => $member->getPrenom(),
                    'email' => $member->getEmail(),
                ];
            }

            return $this->json([
                'success' => true,
                'data' => $members
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des membres'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Ajoute un membre au projet
     */
    #[Route('/{id}/members', name: 'api_projects_add_member', methods: ['POST'])]
    public function addMember(int $id, Request $request): JsonResponse
    {
        try {
            $project = $this->projectRepository->find($id);

            if (!$project) {
                return $this->json([
                    'success' => false,
                    'message' => 'Projet non trouvé'
                ], Response::HTTP_NOT_FOUND);
            }

            if (!$this->canEditProject($project)) {
                return $this->json([
                    'success' => false,
                    'message' => 'Accès refusé'
                ], Response::HTTP_FORBIDDEN);
            }

            $data = json_decode($request->getContent(), true);
            $userId = $data['userId'] ?? null;

            if (!$userId) {
                return $this->json([
                    'success' => false,
                    'message' => 'ID utilisateur requis'
                ], Response::HTTP_BAD_REQUEST);
            }

            $user = $this->userRepository->find($userId);
            if (!$user) {
                return $this->json([
                    'success' => false,
                    'message' => 'Utilisateur non trouvé'
                ], Response::HTTP_NOT_FOUND);
            }

            $project->addMembre($user);
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Membre ajouté avec succès'
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de l\'ajout du membre'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Sérialise un projet pour l'API
     */
    private function serializeProject(Project $project, bool $detailed = false): array
    {
        $data = [
            'id' => $project->getId(),
            'nom' => $project->getTitre(),
            'description' => $project->getDescription(),
            'dateDebut' => $project->getDateCreation()?->format('Y-m-d'),
            'dateFin' => $project->getDateButoir()?->format('Y-m-d'),
            'dateCreation' => $project->getDateCreation()?->format('Y-m-d H:i:s'),
            'createdBy' => [
                'id' => $project->getCreatedBy()?->getId(),
                'nom' => $project->getCreatedBy()?->getNom(),
                'prenom' => $project->getCreatedBy()?->getPrenom(),
            ]
        ];

        if ($detailed) {
            $data['tasks'] = [];
            foreach ($project->getTasks() as $task) {
                $data['tasks'][] = [
                    'id' => $task->getId(),
                    'titre' => $task->getTitle(),
                    'status' => $task->getStatut(),
                    'dueDate' => $task->getDateButoir()?->format('Y-m-d'),
                ];
            }

            $data['members'] = [];
            foreach ($project->getMembres() as $member) {
                $data['members'][] = [
                    'id' => $member->getId(),
                    'nom' => $member->getNom(),
                    'prenom' => $member->getPrenom(),
                ];
            }
        }

        return $data;
    }

    /**
     * Vérifie si l'utilisateur peut accéder au projet
     */
    private function canAccessProject(Project $project): bool
    {
        $user = $this->getUser();

        // Le créateur peut toujours accéder
        if ($project->getCreatedBy() === $user) {
            return true;
        }

        // Les membres peuvent accéder
        if ($project->getMembres()->contains($user)) {
            return true;
        }

        // Les admins peuvent accéder à tous les projets
        if ($this->isGranted('ROLE_ADMIN')) {
            return true;
        }

        return false;
    }

    /**
     * Vérifie si l'utilisateur peut éditer le projet
     */
    private function canEditProject(Project $project): bool
    {
        $user = $this->getUser();

        // Le créateur peut toujours éditer
        if ($project->getCreatedBy() === $user) {
            return true;
        }

        // Les admins peuvent éditer tous les projets
        if ($this->isGranted('ROLE_ADMIN')) {
            return true;
        }

        return false;
    }

    /**
     * Vérifie si l'utilisateur peut supprimer le projet
     */
    private function canDeleteProject(Project $project): bool
    {
        $user = $this->getUser();

        // Le créateur peut toujours supprimer
        if ($project->getCreatedBy() === $user) {
            return true;
        }

        // Les admins peuvent supprimer tous les projets
        if ($this->isGranted('ROLE_ADMIN')) {
            return true;
        }

        return false;
    }

    #[Route('/api/project', name: 'app_api_project')]
    public function index(): Response
    {
        return $this->render('api_project/index.html.twig', [
            'controller_name' => 'ApiProjectController',
        ]);
    }
}
