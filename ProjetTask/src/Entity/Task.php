<?php

namespace App\Entity;

use App\Repository\TaskRepository;
use App\Enum\TaskStatut;
use App\Enum\TaskPriority;
use App\Entity\Project;
use App\Entity\User;
use App\Entity\TaskList;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\DBAL\Types\Types;
use Symfony\Component\Validator\Constraints as Assert;
use App\Entity\Tag;
use App\Entity\Comment;


#[ORM\Entity(repositoryClass: TaskRepository::class)]
#[ORM\Table(name: 'task')]
class Task
{

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    /**
     * @Symfony\Component\Validator\Constraints\NotBlank(message="Le titre est requis")
     * @Symfony\Component\Validator\Constraints\Length(
     *      max=100,
     *      maxMessage="Le titre ne peut pas dépasser 100 caractères"
     * )
     */
    private ?string $title = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $description = null;
    #[Assert\Choice(callback: [TaskStatut::class, 'cases'])]
    #[ORM\Column(enumType: TaskStatut::class)]
    private ?TaskStatut $statut = TaskStatut::EN_ATTENTE;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateCreation = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateButoir = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateReelle = null;

    #[ORM\Column(enumType: TaskPriority::class)]
    #[Assert\Choice(callback: [TaskPriority::class, 'cases'])]
    private ?TaskPriority $priorite = TaskPriority::NORMAL;

    #[ORM\Column]
    private int $position = 0;

    #[ORM\ManyToOne(inversedBy: 'tasks')]
    private ?TaskList $taskList = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: "created_by_id", referencedColumnName: "id", nullable: true)]
    private ?User $createdBy = null;
    /**
     * @var Collection<int, Comment>
     */
    #[ORM\OneToMany(mappedBy: 'task', targetEntity: Comment::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['dateCreation' => 'DESC'])]
    private Collection $comments;

    /**
     * @var Collection<int, Tag>
     */
    #[ORM\ManyToMany(targetEntity: Tag::class, inversedBy: 'tasks')]
    #[ORM\JoinTable(name: 'task_tag')]
    private Collection $tags;

    /**
     * Relation auto-référencée pour les sous-tâches
     * @var Collection<int, Task>
     */
    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'sousTask')]
    #[ORM\JoinColumn(name: 'parent_id', referencedColumnName: 'id', nullable: true)]
    private ?Task $parent = null;

    /**
     * @var Collection<int, Task>
     */
    #[ORM\OneToMany(mappedBy: 'parent', targetEntity: self::class, cascade: ['persist'])]
    private Collection $sousTask;

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): self
    {
        $this->createdBy = $createdBy;
        return $this;
    }

    #[ORM\ManyToOne(inversedBy: 'tasks')]
    private ?Project $project = null;


    // Ajout de cette propriété et ses méthodes associées pour pouvoir m'aider:
    //  -à suivre la date de complétion de la tâche
    //  -faire les vérifications pour le dashboardindex de base au niveau des TeamMember

    /**
     * @ORM\Column(type="DATETIME_MUTABLE", nullable=true)
     */
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateCompletion = null;

    public function getDateCompletion(): ?\DateTimeInterface
    {
        return $this->dateCompletion;
    }

    public function setDateCompletion(?\DateTimeInterface $dateCompletion): self
    {
        $this->dateCompletion = $dateCompletion;
        return $this;
    }

    /**
     * Vérifie si la tâche est en retard
     * 
     * @return bool true si la tâche est en retard, false sinon
     */
    public function isOverdue(): bool
    {
        // Si pas de date butoir, la tâche ne peut pas être en retard
        if (!$this->dateButoir) {
            return false;
        }

        // Une tâche est en retard si sa date butoir est dépassée et qu'elle n'est pas terminée
        return $this->dateButoir < new \DateTime() && $this->statut !== TaskStatut::TERMINE;
    }


    // #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'tachesAssignees')]
    // private ?User $assignedUser = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: "tachesAssignees")]
    #[ORM\JoinColumn(name: "assigned_user_id", referencedColumnName: "id", nullable: true)]
    private ?User $assignedUser = null;
    /**
     * @var Collection<int, User>
     */
    #[ORM\ManyToMany(targetEntity: User::class)]
    private Collection $assignedUsers;

    public function __construct()
    {
        $this->comments = new ArrayCollection();
        $this->tags = new ArrayCollection();
        $this->sousTask = new ArrayCollection();
        $this->dateCreation = new \DateTime();
        $this->assignedUsers = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getStatut(): TaskStatut
    {
        if ($this->statut instanceof TaskStatut) {
            return $this->statut;
        }
        if (is_string($this->statut) || is_int($this->statut)) {
            return TaskStatut::from($this->statut);
        }
        // Default fallback if statut is null or invalid
        return TaskStatut::EN_ATTENTE;
    }
    public function getStatutLabel(): string
    {
        return $this->getStatut()->label();
    }

    public function setStatut(TaskStatut $statut): static
    {
        $this->statut = $statut;
        return $this;
    }
    public function getPriorite(): TaskPriority
    {
        if ($this->priorite instanceof TaskPriority) {
            return $this->priorite;
        }
        if (is_string($this->priorite) || is_int($this->priorite)) {
            return TaskPriority::from($this->priorite);
        }
        // Default fallback if priorite is null or invalid
        return TaskPriority::NORMAL;
    }
    public function getPrioriteLabel(): string
    {
        return $this->getPriorite()->label();
    }
    public function setPriorite(TaskPriority $priorite): static
    {
        $this->priorite = $priorite;
        return $this;
    }
    public function getDateCreation(): ?\DateTime
    {
        return $this->dateCreation;
    }

    public function setDateCreation(\DateTime $dateCreation): static
    {
        $this->dateCreation = $dateCreation;
        return $this;
    }

    public function getDateButoir(): ?\DateTime
    {
        return $this->dateButoir;
    }

    public function setDateButoir(?\DateTime $dateButoir): static
    {
        $this->dateButoir = $dateButoir;
        return $this;
    }

    public function getDateReelle(): ?\DateTime
    {
        return $this->dateReelle;
    }

    public function setDateReelle(?\DateTime $dateReelle): static
    {
        $this->dateReelle = $dateReelle;
        return $this;
    }



    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;
        return $this;
    }

    public function getTaskList(): ?TaskList
    {
        return $this->taskList;
    }

    public function setTaskList(?TaskList $taskList): static
    {
        $this->taskList = $taskList;
        return $this;
    }

    public function getAssignedUser(): ?User
    {
        return $this->assignedUser;
    }

    public function setAssignedUser(?User $assignedUser): self
    {
        $this->assignedUser = $assignedUser;
        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getAssignedUsers(): Collection
    {
        return $this->assignedUsers;
    }

    public function addAssignedUser(User $assignedUser): static
    {
        if (!$this->assignedUsers->contains($assignedUser)) {
            $this->assignedUsers->add($assignedUser);
        }
        return $this;
    }

    public function removeAssignedUser(User $assignedUser): static
    {
        $this->assignedUsers->removeElement($assignedUser);
        return $this;
    }

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): static
    {
        $this->project = $project;
        return $this;
    }

    // Puis j'ajoute ces méthodes à la fin de la classe pour gérer les commentaires et les tags associés à la tâche:


    /**
     * @return Collection<int, Comment>
     */
    public function getComments(): Collection
    {
        return $this->comments;
    }

    public function addComment(Comment $comment): static
    {
        if (!$this->comments->contains($comment)) {
            $this->comments->add($comment);
            $comment->setTask($this);
        }

        return $this;
    }

    public function removeComment(Comment $comment): static
    {
        if ($this->comments->removeElement($comment)) {
            // set the owning side to null (unless already changed)
            if ($comment->getTask() === $this) {
                $comment->setTask(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Tag>
     */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    public function addTag(Tag $tag): static
    {
        if (!$this->tags->contains($tag)) {
            $this->tags->add($tag);
        }

        return $this;
    }

    public function removeTag(Tag $tag): static
    {
        $this->tags->removeElement($tag);
        return $this;
    }

    /**
     * @return Collection<int, Task>
     */
    public function getSousTask(): Collection
    {
        return $this->sousTask;
    }

    public function addSousTask(Task $sousTask): static
    {
        if (!$this->sousTask->contains($sousTask)) {
            $this->sousTask->add($sousTask);
            $sousTask->setParent($this);
        }

        return $this;
    }

    public function removeSousTask(Task $sousTask): static
    {
        if ($this->sousTask->removeElement($sousTask)) {
            // set the owning side to null (unless already changed)
            if ($sousTask->getParent() === $this) {
                $sousTask->setParent(null);
            }
        }

        return $this;
    }

    public function getParent(): ?self
    {
        return $this->parent instanceof self ? $this->parent : null;
    }

    public function setParent(?self $parent): static
    {
        $this->parent = $parent;
        return $this;
    }

    /**
     * Vérifie si cette tâche est une sous-tâche
     */
    public function isSousTask(): bool
    {
        return $this->parent !== null;
    }

    /**
     * Calcule le nombre de commentaires non lus
     */

    // public function getUnreadCommentsCount(User $user): int
    // {
    //     $count = 0;
    //     foreach ($this->comments as $comment) {
    //         if ($comment->getAuteur() !== $user && !$comment->isEstLue()) { {
    //                 $count++;
    //             }

    //             return $count;
    //         }
    //     }
    // }
}
