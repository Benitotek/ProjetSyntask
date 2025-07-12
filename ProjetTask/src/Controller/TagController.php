<?php

namespace App\Controller;

use App\Entity\Tag;
use App\Form\TagTypeForm;
use App\Repository\TagRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/tag')]
#[IsGranted('ROLE_EMPLOYE')]
class TagController extends AbstractController
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    #[Route('', name: 'app_tag_index', methods: ['GET'])]
    public function index(TagRepository $tagRepository): Response
    {
        $tags = $tagRepository->findAll();

        return $this->render('tag/index.html.twig', [
            'tags' => $tags,
        ]);
    }

    #[Route('/new', name: 'app_tag_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $tag = new Tag();
        $form = $this->createForm(TagTypeForm::class, $tag);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($tag);
            $this->entityManager->flush();

            $this->addFlash('success', 'Tag créé avec succès.');

            return $this->redirectToRoute('app_tag_index');
        }

        return $this->render('tag/new.html.twig', [
            'tag' => $tag,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_tag_show', methods: ['GET'])]
    public function show(Tag $tag): Response
    {
        return $this->render('tag/show.html.twig', [
            'tag' => $tag,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_tag_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Tag $tag): Response
    {
        $form = $this->createForm(TagTypeForm::class, $tag);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            $this->addFlash('success', 'Tag modifié avec succès.');

            return $this->redirectToRoute('app_tag_index');
        }

        return $this->render('tag/edit.html.twig', [
            'tag' => $tag,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_tag_delete', methods: ['POST'])]
    public function delete(Request $request, Tag $tag): Response
    {
        if ($this->isCsrfTokenValid('delete' . $tag->getId(), $request->request->get('_token'))) {
            // Vérifier si le tag est utilisé par des tâches
            if ($tag->getTasks()->count() > 0) {
                $this->addFlash('error', 'Ce tag est utilisé par des tâches et ne peut pas être supprimé.');
                return $this->redirectToRoute('app_tag_index');
            }

            $this->entityManager->remove($tag);
            $this->entityManager->flush();

            $this->addFlash('success', 'Tag supprimé avec succès.');
        }

        return $this->redirectToRoute('app_tag_index');
    }

    #[Route('/api/list', name: 'api_tags_list')]
    public function apiList(TagRepository $tagRepository, Request $request): Response
    {
        // Optionally handle pagination or filtering here
        $tags = $tagRepository->findAll();
        // optionellement filtrer par projet
        $projectId = $request->query->get('project');

        if ($projectId) {
            $tags = $tagRepository->findByProject($projectId);
        } else {
            $tags = $tagRepository->findAll();
        }

        $formattedTags = array_map(function ($tag) {
            return [
                'id' => $tag->getId(),
                'nom' => $tag->getNom(),
                'couleur' => $tag->getCouleur(),
                'style' => $tag->getStyle(),
            ];
        }, $tags);

        return $this->json([
            'success' => true,
            'tags' => $formattedTags,
        ]);
    }

    #[Route('/api/create', name: 'api_tag_create', methods: ['POST'])]
    public function apiCreate(Request $request): Response
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['nom']) || !isset($data['couleur'])) {
            return $this->json([
                'success' => false,
                'message' => 'Données incomplètes. Veuillez fournir un nom et une couleur.'
            ], 400);
        }

        $tag = new Tag();
        $tag->setNom($data['nom']);
        $tag->setCouleur($data['couleur']);

        // Associer à un projet si fourni
        if (isset($data['projectId']) && $data['projectId']) {
            $project = $this->entityManager->getRepository('App:Project')->find($data['projectId']);
            if ($project) {
                $tag->setProject($project);
            }
        }

        $this->entityManager->persist($tag);
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'tag' => [
                'id' => $tag->getId(),
                'nom' => $tag->getNom(),
                'couleur' => $tag->getCouleur(),
                'style' => $tag->getStyle(),
            ]
        ]);
    }

    #[Route('/api/{id}', name: 'api_tag_show', methods: ['GET'])]
    public function apiShow(Tag $tag): Response
    {
        return $this->json([
            'id' => $tag->getId(),
            'nom' => $tag->getNom(),
            'couleur' => $tag->getCouleur(),
            'style' => $tag->getStyle(),
        ]);
    }

    #[Route('/api/{id}/delete', name: 'api_tag_delete', methods: ['DELETE'])]
    public function apiDelete(Request $request, Tag $tag): Response
    {
        if ($this->isCsrfTokenValid('delete' . $tag->getId(), $request->request->get('_token'))) {
            // Vérifier si le tag est utilisé par des tâches
            if ($tag->getTasks()->count() > 0) {
                return $this->json([
                    'success' => false,
                    'message' => 'Ce tag est utilisé par des tâches et ne peut pas être supprimé.'
                ], 400);
            }

            $this->entityManager->remove($tag);
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Tag supprimé avec succès.'
            ]);
        }

        return $this->json([
            'success' => false,
            'message' => 'Token CSRF invalide.'
        ], 400);
    }
}
