<?php

namespace App\Repository;

use App\Entity\Project;
use App\Entity\Task;
use App\Entity\User;
use App\Entity\TaskList;
use App\Enum\TaskStatut;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @extends ServiceEntityRepository<Task>
 */
class TaskRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Task::class);
    }
    /**
     * Retourne les tâches d'un projet triées par colonne puis position.
     *
     * @return Task[]
     */
    public function findByProjectOrdered(Project $project): array
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.taskList', 'tl')->addSelect('tl')
            ->andWhere('t.project = :p')->setParameter('p', $project)
            ->orderBy('tl.positionColumn', 'ASC')
            ->addOrderBy('t.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Déplace une tâche vers une colonne et met à jour sa position.
     * Cette implémentation simple fixe la position demandée.
     * Pour un re-order complet, adaptez en recalculant les positions des frères.
     */
    public function moveTaskToColumn(Task $task, TaskList $target, int $position = 0): void
    {
        $em = $this->getEntityManager();

        // Assigne nouvelle colonne et position
        $task->setTaskList($target);
        if (method_exists($task, 'setPosition')) {
            $task->setPosition($position);
        }

        $em->flush();
    }
    /**
     * Trouve les activités récentes
     */
    public function findRecent(int $limit = 10): array
    {
        return $this->createQueryBuilder('t')
            ->orderBy('t.dateCreation', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
    /**
     * Retourne toutes les tâches de chaque utilisateur qui possède le rôle ROLE_EMPLOYEE.
     * (Utilisable en mode admin pour le calendrier global.)
     *
     * @return Task[]
     */
    public function findAllEmployeeTasks(): array
    {
        return $this->createQueryBuilder('t')
            ->join('t.assignedUser', 'u')
            ->andWhere('u.roles LIKE :role')
            ->setParameter('role', '%ROLE_EMPLOYEE%')
            ->getQuery()
            ->getResult();
    }
    /**
     * Trouve toutes les tâches d'un project, triées par colonne puis position
     */
    public function findByProject(Project $project): array
    {
        return $this->createQueryBuilder('t')
            ->join('t.taskList', 'tl')
            ->where('t.project = :project')
            ->setParameter('project', $project)
            ->orderBy('tl.positionColumn', 'ASC')
            ->addOrderBy('t.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les tâches assignées à un utilisateur
     */
    public function findByAssignedUser(User $user): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.assignedUser = :user')
            ->setParameter('user', $user)
            ->orderBy('t.dateButoir', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve la prochaine position disponible dans une colonne
     * 
     * @param TaskList $taskList La colonne concernée
     * @return int La prochaine position
     */

    public function findNextPositionInColumn(TaskList $taskList): int
    {
        $result = $this->createQueryBuilder('t')
            ->select('MAX(t.position)')
            ->where('t.taskList = :taskList')
            ->setParameter('taskList', $taskList)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ? $result + 1 : 1;
    }

    /**
     * Trouve les tâches en retard
     */
    public function findOverdue(): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.dateButoir < :today')
            ->andWhere('t.statut != :statut')
            ->setParameter('today', new \DateTime())
            ->setParameter('statut', TaskStatut::TERMINER) // enum, pas string!Revoir autre au cas ou
            ->orderBy('t.dateButoir', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les tâches avec une échéance proche (dans les 3 jours)
     */
    public function findTasksWithDeadlineApproaching(): array
    {
        $today = new \DateTime();
        $threeDaysLater = new \DateTime('+3 days');

        return $this->createQueryBuilder('t')
            ->where('t.dateButoir BETWEEN :today AND :threeDaysLater')
            ->andWhere('t.statut != :statut')
            ->setParameter('today', $today)
            ->setParameter('threeDaysLater', $threeDaysLater)
            ->setParameter('statut',TaskStatut::TERMINER) // enum
            ->orderBy('t.dateButoir', 'ASC')
            ->getQuery()
            ->getResult();
    }


    /**
     * Réorganise les positions après suppression
     */
    public function reorganizePositionsInColumn(TaskList $column, int $deletedPosition): void
    {
        $entityManager = $this->getEntityManager();

        $tasksToUpdate = $this->createQueryBuilder('t')
            ->where('t.taskList = :column')
            ->andWhere('t.position > :pos')
            ->setParameter('column', $column)
            ->setParameter('pos', $deletedPosition)
            ->getQuery()
            ->getResult();

        foreach ($tasksToUpdate as $task) {
            $task->setPosition($task->getPosition() - 1);
        }

        $entityManager->flush();
    }

    /**
     * Déplace une tâche dans une colonne et ajuste les positions
     */
    public function moveTaskInToNewColumn(Task $task, TaskList $newColumn, int $newPosition): void
    {
        $em = $this->getEntityManager();
        $oldColumn = $task->getTaskList();
        $oldPosition = $task->getPosition();

        if ($oldColumn === $newColumn) {
            if ($oldPosition < $newPosition) {
                $tasks = $this->createQueryBuilder('t')
                    ->where('t.taskList = :column')
                    ->andWhere('t.position > :oldPos AND t.position <= :newPos')
                    ->andWhere('t != :task')
                    ->setParameter('column', $oldColumn)
                    ->setParameter('oldPos', $oldPosition)
                    ->setParameter('newPos', $newPosition)
                    ->setParameter('task', $task)
                    ->getQuery()->getResult();

                foreach ($tasks as $t) {
                    $t->setPosition($t->getPosition() - 1);
                }
            } elseif ($oldPosition > $newPosition) {
                $tasks = $this->createQueryBuilder('t')
                    ->where('t.taskList = :column')
                    ->andWhere('t.position >= :newPos AND t.position < :oldPos')
                    ->andWhere('t != :task')
                    ->setParameter('column', $oldColumn)
                    ->setParameter('oldPos', $oldPosition)
                    ->setParameter('newPos', $newPosition)
                    ->setParameter('task', $task)
                    ->getQuery()->getResult();

                foreach ($tasks as $t) {
                    $t->setPosition($t->getPosition() + 1);
                }
            }
        } else {
            $tasksOld = $this->createQueryBuilder('t')
                ->where('t.taskList = :oldColumn')
                ->andWhere('t.position > :oldPos')
                ->setParameter('oldColumn', $oldColumn)
                ->setParameter('oldPos', $oldPosition)
                ->getQuery()->getResult();

            foreach ($tasksOld as $t) {
                $t->setPosition($t->getPosition() - 1);
            }

            $tasksNew = $this->createQueryBuilder('t')
                ->where('t.taskList = :newColumn')
                ->andWhere('t.position >= :newPos')
                ->setParameter('newColumn', $newColumn)
                ->setParameter('newPos', $newPosition)
                ->getQuery()->getResult();

            foreach ($tasksNew as $t) {
                $t->setPosition($t->getPosition() + 1);
            }
        }

        $task->setTaskList($newColumn);
        $task->setPosition($newPosition);
        $em->flush();
    }

    /**
     * Trouve les tâches par statut
     */
    public function findByStatut(string $statut): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.statut = :statut')
            ->setParameter('statut', $statut)
            ->orderBy('t.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les tâches par priorité
     */
    public function findByPriority(string $priority): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.priorite = :priority')
            ->setParameter('priority', $priority)
            ->orderBy('t.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Dernières tâches créées
     */
    public function findRecentTasks(int $limit = 10): array
    {
        return $this->createQueryBuilder('t')
            ->orderBy('t.dateCreation', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }


    // Récupère les tâches à rendre dans les 7 jours pour un utilisateur

    public function findUpcomingDueDatesForUser(User $user): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.dateButoir > :now')
            ->setParameter('now', new \DateTime())
            ->orderBy('t.dateButoir', 'ASC')
            ->getQuery()
            ->getResult();
    }
    // Récupère les tâches assignées à un utilisateur
    // Retourne les tâches pour un user
    public function findAssignedToUser(UserInterface $user): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.assignedUser = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();
    }

    // Retourne les tâches par projet ET user
    public function findByProjectAndUser(int $projectId, UserInterface $user): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.project = :projectId')
            ->andWhere('t.assignedUser = :user')
            ->setParameter('projectId', $projectId)
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();
    }
    
}


