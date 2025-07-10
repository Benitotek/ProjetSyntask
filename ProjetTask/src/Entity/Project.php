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
    public const STATUT_EN_ATTENTE = 'EN-ATTENTE';
    public const STATUT_EN_COURS = 'EN-COURS';
    public const STATUT_TERMINE = 'TERMINE';
    public const STATUT_EN_PAUSE = 'EN_PAUSE';
    public const STATUT_ARRETE = 'ARRETER';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $titre = null;

    #[ORM\Column(type: Types::STRING, length: 20)]
    #[Assert\Choice(choices: [self::STATUT_EN_ATTENTE, self::STATUT_EN_COURS, self::STATUT_TERMINE, self::STATUT_EN_PAUSE, self::STATUT_ARRETE])]
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

    // CORRECTION : Propriété estArchive correctement mappée
    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $estArchive = false;

    // Chef de project : Un User peut gérer plusieurs projects
    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: "projectsGeres")]
    #[ORM\JoinColumn(name: "chef_project_id", referencedColumnName: "id", nullable: true)]
    private ?User $chefproject = null;

    // Membres : ManyToMany
    #[ORM\ManyToMany(targetEntity: User::class, inversedBy: 'projectsAssignes')]
    private Collection $membres;

    #[ORM\OneToMany(mappedBy: 'project', targetEntity: TaskList::class, cascade: ['persist', 'remove'])]
    private Collection $taskLists;

    #[ORM\OneToMany(mappedBy: 'project', targetEntity: Task::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $tasks;

    public function __construct()
    {
        $this->membres = new ArrayCollection();
        $this->taskLists = new ArrayCollection();
        $this->tasks = new ArrayCollection();
        $this->dateCreation = new \DateTime();
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
        }
        return $this;
    }

    public function removeMembre(User $membre): static
    {
        $this->membres->removeElement($membre);
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
            'EN-ATTENTE' => array_filter($tasks, fn($t) => $t->getStatut() === 'EN-ATTENTE'),
            'EN-COURS' => array_filter($tasks, fn($t) => $t->getStatut() === 'EN-COURS'),
            'TERMINE' => array_filter($tasks, fn($t) => $t->getStatut() === 'TERMINE'),
        ];
    }

    public function getProgress(): float
    {
        $totalTasks = $this->tasks->count();
        if ($totalTasks === 0) {
            return 0;
        }

        $completedTasks = $this->tasks->filter(fn($task) => $task->getStatut() === 'TERMINE')->count();
        return ($completedTasks / $totalTasks) * 100;
    }

    public function addTask(Task $task): static
    {
        if (!$this->tasks->contains($task)) {
            $this->tasks->add($task);
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

    public function __toString(): string
    {
        return $this->titre ?: 'Nouveau project';
    }

    public function isEstArchive(): bool
    {
        return $this->estArchive;
    }

    public function setEstArchive(bool $estArchive): self
    {
        $this->estArchive = $estArchive;
        return $this;
    }
}