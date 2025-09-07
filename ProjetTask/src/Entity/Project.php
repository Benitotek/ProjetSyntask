<?php

namespace App\Entity;

use App\Repository\ProjectRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\User;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProjectRepository::class)]
class Project
{
    public const STATUT_EN_ATTENTE = 'EN_ATTENTE';
    public const STATUT_EN_COURS = 'EN_COURS';
    public const STATUT_TERMINER = 'TERMINER';
    public const STATUT_EN_PAUSE = 'EN_PAUSE';
    public const STATUT_ARRETER = 'ARRETER';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Le titre du projet est obligatoire")]
    #[Assert\Length(
        min: 3,
        max: 255,
        minMessage: "Le titre doit comporter au moins {{ limit }} caractères",
        maxMessage: "Le titre ne peut pas dépasser {{ limit }} caractères"
    )]
    private ?string $titre = null;

    #[ORM\Column(type: Types::STRING, length: 20)]
    #[Assert\Choice(choices: [self::STATUT_EN_ATTENTE, self::STATUT_EN_COURS, self::STATUT_TERMINER, self::STATUT_EN_PAUSE, self::STATUT_ARRETER])]
    private ?string $statut = self::STATUT_EN_ATTENTE;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateCreation = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateMaj = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateButoir = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateReelle = null;

    #[ORM\Column(length: 255)]
    private ?string $description = null;

    #[ORM\Column(length: 50)]
    private ?string $reference = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2)]
    private ?string $budget = null;

    //  Propriété isArchived correctement mappée
    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isArchived = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Assert\DateTime]
    private ?\DateTimeImmutable $dateArchived = null;

    // Chef de project : Un User peut gérer plusieurs projects
    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: "projectsGeres")]
    #[ORM\JoinColumn(name: "CHEF_PROJECT_id", referencedColumnName: "id", nullable: true)]
    private ?User $chefproject = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $createdBy = null;

    // Membres : ManyToMany
    #[ORM\ManyToMany(targetEntity: User::class, inversedBy: 'projectsAssignes')]
    #[ORM\JoinTable(name: "project_members")]
    private Collection $membres;

    #[ORM\OneToMany(mappedBy: 'project', targetEntity: Activity::class, orphanRemoval: true)]
    #[ORM\OrderBy(["dateCreation" => "DESC"])]
    private Collection $activities;

    #[ORM\OneToMany(mappedBy: 'project', targetEntity: TaskList::class, cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(["position" => "ASC"])]
    private Collection $taskLists;

    #[ORM\OneToMany(mappedBy: 'project', targetEntity: Task::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $tasks;

    #[ORM\OneToMany(mappedBy: 'project', targetEntity: Tag::class, orphanRemoval: true)]
    private Collection $tags;

    #[ORM\OneToMany(mappedBy: 'project', targetEntity: Comment::class, orphanRemoval: true)]
    private Collection $comments;

    public function __construct()
    {
        $this->activities = new ArrayCollection();
        $this->membres = new ArrayCollection();
        $this->tags = new ArrayCollection();
        $this->taskLists = new ArrayCollection();
        $this->statut = self::STATUT_EN_COURS;
        $this->tasks = new ArrayCollection();
        $this->dateCreation = new \DateTime();
        $this->dateMaj = new \DateTime();
    }
    #[ORM\PreUpdate]
    public function setUpdatedValue(): void
    {
        $this->dateMaj = new \DateTime();
    }
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitre(): ?string
    {
        return $this->titre;
    }

    public function setTitre(string $titre): static
    {
        $this->titre = $titre;
        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;
        return $this;
    }

    public function getDateCreation(): ?\DateTimeInterface
    {
        return $this->dateCreation;
    }

    public function setDateCreation(\DateTimeInterface $dateCreation): static
    {
        $this->dateCreation = $dateCreation;
        return $this;
    }

    public function getDateMaj(): ?\DateTimeInterface
    {
        return $this->dateMaj;
    }

    public function setDateMaj(\DateTimeInterface $dateMaj): static
    {
        $this->dateMaj = $dateMaj;
        return $this;
    }

    public function getDateButoir(): ?\DateTimeInterface
    {
        return $this->dateButoir;
    }

    public function setDateButoir(\DateTimeInterface $dateButoir): static
    {
        $this->dateButoir = $dateButoir;
        return $this;
    }

    public function getDateReelle(): ?\DateTimeInterface
    {
        return $this->dateReelle;
    }

    public function setDateReelle(\DateTimeInterface $dateReelle): static
    {
        $this->dateReelle = $dateReelle;
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

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(string $reference): static
    {
        $this->reference = $reference;
        return $this;
    }

    public function getBudget(): ?string
    {
        return $this->budget;
    }

    public function setBudget(string $budget): static
    {
        $this->budget = $budget;
        return $this;
    }

    public function getChefproject(): ?User
    {
        return $this->chefproject;
    }

    public function setChefproject(?User $chefproject): self
    {
        $this->chefproject = $chefproject;
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
     * @return Collection<int, User>
     */
    public function getMembres(): Collection
    {
        return $this->membres;
    }

    public function addMembre(User $membre): static
    {
        if (!$this->membres->contains($membre)) {
            $this->membres->add($membre);
            // Ajouter également le créateur comme membre s'il ne l'est pas déjà
            if ($this->getChefproject() && !$this->membres->contains($this->getChefproject())) {
                $this->membres->add($this->getChefproject());
            }
        }

        return $this;
    }

    public function removeMembre(User $membre): static
    {
        // Ne pas retirer le créateur du projet
        if ($membre !== $this->getChefproject()) {
            $this->membres->removeElement($membre);
        }
        return $this;
    }
    /**
     * Vérifie si un utilisateur est membre du projet
     */
    public function isMembre(User $user): bool
    {
        return $this->membres->contains($user);
    }

    /**
     * Obtenir les chefs de projet parmi les membres
     */
    public function getChefsProjets(): array
    {
        return $this->membres->filter(function (User $user) {
            return $user->hasRole('ROLE_CHEF_PROJET');
        })->toArray();
    }

    /**
     * Obtenir les employés parmi les membres
     */
    public function getEmployes(): array
    {
        return $this->membres->filter(function (User $user) {
            return $user->hasRole('ROLE_EMPLOYE') && !$user->hasRole('ROLE_CHEF_PROJET');
        })->toArray();
    }

    /**
     * @return Collection<int, Tag>
     */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    public function addTag(Tag $tag): self
    {
        if (!$this->tags->contains($tag)) {
            $this->tags->add($tag);
            $tag->setProject($this);
        }

        return $this;
    }

    public function removeTag(Tag $tag): self
    {
        if ($this->tags->removeElement($tag)) {
            // set the owning side to null (unless already changed)
            if ($tag->getProject() === $this) {
                $tag->setProject(null);
            }
        }

        return $this;
    }
    /**
     * @return Collection<int, TaskList>
     */
    public function getTaskLists(): Collection
    {
        return $this->taskLists;
    }

    public function addTaskList(TaskList $taskList): static
    {
        if (!$this->taskLists->contains($taskList)) {
            $this->taskLists->add($taskList);
            $taskList->setProject($this);
        }
        return $this;
    }

    public function removeTaskList(TaskList $taskList): static
    {
        if ($this->taskLists->removeElement($taskList)) {
            if ($taskList->getProject() === $this) {
                $taskList->setProject(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Task>
     */
    public function getTasks(): Collection
    {
        return $this->tasks;
    }

    public function getTasksBystatut(): array
    {
        $tasks = $this->tasks->toArray();
        return [
            'EN_ATTENTE' => array_filter($tasks, fn(Task $t) => $t->getStatut() === \App\Enum\TaskStatut::EN_ATTENTE),
            'EN_COURS'   => array_filter($tasks, fn(Task $t) => $t->getStatut() === \App\Enum\TaskStatut::EN_COURS),
            'TERMINER'   => array_filter($tasks, fn(Task $t) => $t->getStatut() === \App\Enum\TaskStatut::TERMINER),
        ];
    }

    public function getProgress(): float
    {
        $totalTasks = $this->tasks->count();
        if ($totalTasks === 0) {
            return 0;
        }

        $completedTasks = $this->tasks->filter(fn($task) => $task->getStatut() === 'TERMINER')->count();
        return ($completedTasks / $totalTasks) * 100;
    }

    public function addTask(Task $task): static
    {
        if (!$this->tasks->contains($task)) {
            $this->tasks->add($task);
            $task->setProject($this);
        }
        return $this;
    }

    public function removeTask(Task $task): self
    {
        if ($this->tasks->removeElement($task)) {
            if ($task->getProject() === $this) {
                $task->setProject(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Activity>
     */
    public function getActivities(): Collection
    {
        return $this->activities;
    }

    public function addActivity(Activity $activity): self
    {
        if (!$this->activities->contains($activity)) {
            $this->activities->add($activity);
            $activity->setUser($this->getChefproject());
        }

        return $this;
    }

    public function removeActivity(Activity $activity): self
    {
        $this->activities->removeElement($activity);
        return $this;
    }

    public function __toString(): string
    {
        return $this->titre ?: 'Nouveau project';
    }

    public function isArchived(): bool
    {
        return $this->isArchived;
    }

    public function setisArchived(bool $isArchived): self
    {
        $this->isArchived = $isArchived;
        return $this;
    }
    public function getDateArchived(): ?\DateTimeImmutable
    {
        return $this->dateArchived;
    }

    public function setDateArchived(?\DateTimeImmutable $dateArchived): self
    {
        $this->dateArchived = $dateArchived;
        return $this;
    }

    public function getProgressColor(): string
    {
        if ($this->getProgress() >= 100) {
            return 'green';
        } elseif ($this->getProgress() >= 50) {
            return 'yellow';
        } else {
            return 'red';
        }
    }
    /**
     * @return Collection<int, Comment>
     */
    public function getComments(): Collection
    {
        return $this->comments;
    }

    public function addComment(Comment $comment): self
    {
        if (!$this->comments->contains($comment)) {
            $this->comments->add($comment);
        }

        return $this;
    }
    public function removeComment(Comment $comment): self
    {
        $this->comments->removeElement($comment);
        return $this;
    }
    
}
