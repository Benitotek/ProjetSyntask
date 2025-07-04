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
