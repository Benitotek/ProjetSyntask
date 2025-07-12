<?php

namespace App\Repository;

use App\Entity\Project;
use App\Entity\Tag;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\Response;

/**
 * @extends ServiceEntityRepository<Tag>
 */
class TagRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tag::class);
    }
    /**
     * Trouve les tags associés à un projet spécifique
     */
    public function findByProject(int|Project $project): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.project = :project OR t.project IS NULL')
            ->setParameter('project', $project instanceof Project ? $project->getId() : $project)
            ->orderBy('t.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les tags par leur nom (recherche partielle)
     */
    public function findByNameLike(string $term): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.nom LIKE :term')
            ->setParameter('term', '%' . $term . '%')
            ->orderBy('t.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les tags globaux (non associés à un projet spécifique)
     */
    public function findGlobalTags(): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.project IS NULL')
            ->orderBy('t.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les tags les plus utilisés
     */
    public function findMostUsedTags(int $limit = 10): array
    {
        return $this->createQueryBuilder('t')
            ->select('t', 'COUNT(task.id) as taskCount')
            ->leftJoin('t.tasks', 'task')
            ->groupBy('t.id')
            ->orderBy('taskCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }


    public function apiList(TagRepository $tagRepository, ?int $projectId = null): Response
    {
        // Optionally handle pagination or filtering here
        $tags = $projectId ? $tagRepository->findByProject($projectId) : $tagRepository->findAll();

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
}
    //    /**
    //     * @return Tag[] Returns an array of Tag objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('t')
    //            ->andWhere('t.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('t.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Tag
    //    {
    //        return $this->createQueryBuilder('t')
    //            ->andWhere('t.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
