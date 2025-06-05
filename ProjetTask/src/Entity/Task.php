<?php

namespace App\Entity;

use App\Repository\TaskRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TaskRepository::class)]
class Task
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $title = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 30)]
    private ?string $priorite = null;

    #[ORM\Column]
    private array $statut = [];

    #[ORM\Column]
    private ?\DateTime $dateCreation = null;

    #[ORM\Column]
    private ?\DateTime $dateButoir = null;

    #[ORM\Column]
    private ?\DateTime $dateReelle = null;

    #[ORM\ManyToOne(inversedBy: 'tasks')]
    private ?TaskLIST $taskLIST = null;

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

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getPriorite(): ?string
    {
        return $this->priorite;
    }

    public function setPriorite(string $priorite): static
    {
        $this->priorite = $priorite;

        return $this;
    }

    public function getStatut(): array
    {
        return $this->statut;
    }

    public function setStatut(array $statut): static
    {
        $this->statut = $statut;

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

    public function setDateButoir(\DateTime $dateButoir): static
    {
        $this->dateButoir = $dateButoir;

        return $this;
    }

    public function getDateReelle(): ?\DateTime
    {
        return $this->dateReelle;
    }

    public function setDateReelle(\DateTime $dateReelle): static
    {
        $this->dateReelle = $dateReelle;

        return $this;
    }

    public function getTaskLIST(): ?TaskLIST
    {
        return $this->taskLIST;
    }

    public function setTaskLIST(?TaskLIST $taskLIST): static
    {
        $this->taskLIST = $taskLIST;

        return $this;
    }
}
