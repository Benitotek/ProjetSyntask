<?php

namespace App\Entity;

use App\Enum\TaskListColor;
use App\Enum\TaskStatut;
use App\Repository\TaskListRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\DBAL\Types\Types;


#[ORM\Entity(repositoryClass: TaskListRepository::class)]
class TaskList
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 30)]
    private ?string $nom = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $description = null;

    // Position dans l'ordre des colonnes
    #[ORM\Column(nullable: true)]
    private ?int $position = null;

    #[ORM\Column(type: Types::INTEGER)]
    private ?int $positionColumn = null;

    #[ORM\ManyToOne(targetEntity: Project::class, inversedBy: 'taskLists')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Project $project = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateTime = null;

    #[ORM\ManyToOne(inversedBy: 'taskLists')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $createdBy = null;

    #[ORM\Column(enumType: TaskListColor::class)]
    private ?TaskListColor $couleur = null;


    /**
     * @var Collection<int, Task>
     */
    #[ORM\OneToMany(mappedBy: 'taskList', targetEntity: Task::class, cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $tasks;

    public function __construct()
    {
        $this->tasks = new ArrayCollection();
        $this->dateTime = new \DateTime();
        $this->couleur = TaskListColor::VERT; // Couleur par défaut
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }



    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(?int $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function getPositionColumn(): ?int
    {
        return $this->positionColumn;
    }

    public function setPositionColumn(int $positionColumn): self
    {
        $this->positionColumn = $positionColumn;
        return $this;
    }
    public function getDateTime(): ?\DateTimeInterface
    {
        return $this->dateTime;
    }

    public function setDateTime(\DateTimeInterface $dateTime): static
    {
        $this->dateTime = $dateTime;
        return $this;
    }
    // Enum exposée en API publique
    public function getCouleur(): ?TaskListColor
    {
        return $this->couleur; // TaskListColor::from($this->couleur) : null;
    }

    public function setCouleur(?TaskListColor $couleur): self
    {
        $this->couleur = $couleur; // $couleur?->value;
        return $this;
    }

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): self
    {
        $this->project = $project;

        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }
    public function setCreatedBy(?User $createdBy): self
    {
        $this->createdBy = $createdBy;

        return $this;
    }


    /**
     * @return Collection<int, Task>
     */
    public function getTasks(): Collection
    {
        return $this->tasks;
    }

    public function addTask(Task $task): self
    {
        if (!$this->tasks->contains($task)) {
            $this->tasks->add($task);
            $task->setTaskList($this);
        }

        return $this;
    }

    public function removeTask(Task $task): self
    {
        if ($this->tasks->removeElement($task)) {

            if ($task->getTaskList() === $this) {
                $task->setTaskList(null);
            }
        }

        return $this;
    }
    // ==================== MÉTHODES MÉTIER ====================

    /**
     * Calcule automatiquement la couleur basée sur les retards des tâches
     */

    public function calculateAutoColor(): TaskListColor
    {
        $tasks = $this->getTasks();
        if ($tasks->isEmpty()) return TaskListColor::VERT;

        $maxDelay = 0;
        $now = new \DateTime();
        foreach ($tasks as $task) {
            if ($task->getDateButoir() && $task->getStatut()->value !== 'TERMINER') {
                $diff = $now->diff($task->getDateButoir());
                $days = $diff->invert ? $diff->days : 0;
                $maxDelay = max($maxDelay, (int)$days);
            }
        }
        return TaskListColor::calculateByDelay($maxDelay);
    }

    /**
     * Met à jour automatiquement la couleur
     */
    public function updateAutoColor(): void
    {
        $this->setCouleur($this->calculateAutoColor());
    }

    /**
     * Calcule la progression des tâches dans cette colonne
     */
    public function getProgression(): array
    {
        $tasks = $this->getTasks();
        $total = $tasks->count();

        if ($total === 0) {
            return [
                'total' => 0,
                'completed' => 0,
                'in_progress' => 0,
                'pending' => 0,
                'percentage' => 0
            ];
        }

        $completed = 0;
        $inProgress = 0;
        $pending = 0;

        foreach ($tasks as $task) {
            switch ($task->getStatut()) {
                case TaskStatut::TERMINER:
                    $completed++;
                    break;
                case TaskStatut::EN_COURS:
                    $inProgress++;
                    break;
                default:
                    $pending++;
                    break;
            }
        }

        return [
            'total' => $total,
            'completed' => $completed,
            'in_progress' => $inProgress,
            'pending' => $pending,
            'percentage' => round(($completed / $total) * 100, 1)
        ];
    }

    /**
     * Retourne les tâches en retard dans cette colonne
     */
    public function getOverdueTasks(): array
    {
        $overdueTasks = [];
        $now = new \DateTime();

        foreach ($this->getTasks() as $task) {
            if (
                $task->getDateButoir() &&
                $task->getStatut() !== TaskStatut::TERMINER &&
                $task->getDateButoir() < $now
            ) {
                $overdueTasks[] = $task;
            }
        }

        return $overdueTasks;
    }

    /**
     * Retourne le nombre de tâches en retard
     */
    public function getOverdueCount(): int
    {
        return count($this->getOverdueTasks());
    }

    /**
     * Vérifie si cette colonne a des tâches en retard
     */
    public function hasOverdueTasks(): bool
    {
        return $this->getOverdueCount() > 0;
    }

    /**
     * Retourne la tâche avec le plus grand retard
     */
    public function getMostOverdueTask(): ?Task
    {
        $overdueTasks = $this->getOverdueTasks();

        if (empty($overdueTasks)) {
            return null;
        }

        $mostOverdue = $overdueTasks[0];
        foreach ($overdueTasks as $task) {
            if ($task->getDateButoir() < $mostOverdue->getDateButoir()) {
                $mostOverdue = $task;
            }
        }

        return $mostOverdue;
    }

    /**
     * Retourne les statistiques de délais pour cette colonne
     */
    public function getDelayStats(): array
    {
        $now = new \DateTime();
        $delays = [
            'on_time' => 0,
            'slight_delay' => 0,    // 1-7 jours
            'medium_delay' => 0,    // 8-30 jours
            'major_delay' => 0      // >30 jours
        ];

        foreach ($this->getTasks() as $task) {
            if (!$task->getDateButoir() || $task->getStatut() === TaskStatut::TERMINER) {
                $delays['on_time']++;
                continue;
            }

            $diff = $now->diff($task->getDateButoir());
            $delayDays = $diff->invert ? $diff->days : 0;

            if ($delayDays === 0) {
                $delays['on_time']++;
            } elseif ($delayDays <= 7) {
                $delays['slight_delay']++;
            } elseif ($delayDays <= 30) {
                $delays['medium_delay']++;
            } else {
                $delays['major_delay']++;
            }
        }

        return $delays;
    }
}
