<?php

namespace App\Entity;

use App\Repository\TagRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Table(name: 'tags')]
#[ORM\Entity(repositoryClass: TagRepository::class)]
class Tag
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;
    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: "Le nom du tag est obligatoire")]
    #[Assert\Length(max: 50, maxMessage: "Le nom du tag ne peut pas dépasser 50 caractères")]
    private ?string $nom = null;

    #[ORM\Column(length: 7)]
    #[Assert\Regex(
        pattern: '/^#[0-9A-Fa-f]{6}$/',
        message: 'La couleur doit être au format hexadécimal (ex: #FF5733)'
    )]
    private ?string $couleur = '#3498db';

    #[ORM\ManyToMany(targetEntity: Task::class, mappedBy: 'tags')]
    private Collection $tasks;

    #[ORM\ManyToOne(targetEntity: Project::class, inversedBy: 'tags')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Project $project = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'tags')]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $user = null;

    #[ORM\ManyToMany(targetEntity: TaskList::class, mappedBy: 'tags')]
    private Collection $taskLists;

    public function __construct()
    {
        $this->tasks = new ArrayCollection();
        $this->couleur = '#' . substr(md5(rand()), 0, 6); // Couleur aléatoire par défaut
        $this->taskLists = new ArrayCollection();
        $this->user = null; // Par défaut, le tag n'est pas associé à un utilisateur
        $this->project = null; // Par défaut, le tag n'est pas associé à un projet
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

    public function getCouleur(): ?string
    {
        return $this->couleur;
    }

    public function setCouleur(string $couleur): static
    {
        $this->couleur = $couleur;
        return $this;
    }

    /**
     * @return Collection<int, Task>
     */
    public function getTasks(): Collection
    {
        return $this->tasks;
    }

    public function addTask(Task $task): static
    {
        if (!$this->tasks->contains($task)) {
            $this->tasks->add($task);
            $task->addTag($this);
        }

        return $this;
    }

    public function removeTask(Task $task): static
    {
        if ($this->tasks->removeElement($task)) {
            $task->removeTag($this);
        }

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

    /**
     * Génère un style CSS pour ce tag
     */
    public function getStyle(): string
    {
        // DéTERMINERr si la couleur est claire ou foncée pour le contraste du texte
        $hex = ltrim($this->couleur, '#');
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;

        $textColor = $luminance > 0.5 ? '#000000' : '#FFFFFF';

        return "background-color: {$this->couleur}; color: {$textColor};";
    }
    /**
     * Indique si le tag est global (non associé à un projet spécifique)
     */
    public function isGlobal(): bool
    {
        return $this->project === null;
    }
    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }
    /**
     * @return Collection<int, TaskList>
     */
    public function getTaskLists(): Collection
    {
        return $this->taskLists;
    }
    // public function addTaskList(TaskList $taskList): static
    // {
    //     if (!$this->taskLists->contains($taskList)) {
    //         $this->taskLists->add($taskList);
    //         $taskList->addTag($this);
    //     }

    //     return $this;
    // }

    // public function removeTaskList(TaskList $taskList): static
    // {
    //     if ($this->taskLists->removeElement($taskList)) {
    //         $taskList->removeTag($this);
    //     }

    //     return $this;
    // }

}
