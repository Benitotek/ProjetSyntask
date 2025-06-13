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

#[ORM\Entity(repositoryClass: TaskRepository::class)]
#[ORM\Table(name: 'task')]
class Task
{
    public const PRIORITE_URGENT = 'urgent';
    public const PRIORITE_NORMAL = 'normal';
    public const PRIORITE_EN_ATTENTE = 'en_attente';

    public const STATUT_EN_ATTENTE = 'en_attente';
    public const STATUT_EN_COURS = 'en_cours';
    public const STATUT_TERMINE = 'termine';

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

    #[ORM\Column(type: 'string', length: 20)]
    private string $statut = 'en_attente';

    #[ORM\Column]
    private ?\DateTime $dateCreation = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $dateButoir = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $dateReelle = null;

    #[ORM\Column(type: 'string', length: 20)]
    private string $priorite = 'normal';

    #[ORM\Column]
    private int $position = 0;

    #[ORM\ManyToOne(inversedBy: 'tasks')]
    private ?TaskList $taskList = null;


    #[ORM\ManyToOne(inversedBy: 'tasks')]
    private ?Project $project = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'tachesAssignees')]
    private ?User $assignedUser = null;
    /**
     * @var Collection<int, User>
     */
    #[ORM\ManyToMany(targetEntity: User::class)]
    private Collection $assignedUsers;

    public function __construct()
    {
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
        return TaskStatut::from($this->statut);
    }

    public function setStatut(TaskStatut $statut): static
    {
        $this->statut = $statut->value;
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

    public function getPriorite(): TaskPriority
    {
        return TaskPriority::from($this->priorite);
    }

    public function setPriorite(TaskPriority $priorite): static
    {
        $this->priorite = $priorite->value;
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

    public function setAssignedUser(?User $assignedUser): static
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

    public function isOverdue(): bool
    {
        if (!$this->dateButoir || $this->statut === TaskStatut::TERMINE) {
            return false;
        }
        return $this->dateButoir < new \DateTime();
    }
}
