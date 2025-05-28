<?php

namespace App\Entity;

use App\Repository\ProjectRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\User;

#[ORM\Entity(repositoryClass: ProjectRepository::class)]
class Project
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 30)]
    private ?string $titre = null;

    #[ORM\Column(type: Types::JSON)]
    private array $statut = [];

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $dateCreation = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $dateMaj = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $dateButoir = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $dateReelle = null;

    #[ORM\Column(length: 255)]
    private ?string $description = null;

    #[ORM\Column(length: 50)]
    private ?string $reference = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2)]
    private ?string $budget = null;

    #[ORM\ManyToMany(targetEntity: User::class)]
    #[ORM\JoinTable(name: 'project_managers')]
    private Collection $managers;

    public function __construct()
    {
        $this->managers = new ArrayCollection();
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

    public function getStatut(): array
    {
        return $this->statut;
    }

    public function setStatut(array $statut): static
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

    /**
     * @return Collection<int, User>
     */
    public function getManagers(): Collection
    {
        return $this->managers;
    }

    public function addManager(User $manager): static
    {
        if (!$this->managers->contains($manager)) {
            $this->managers[] = $manager;
        }

        return $this;
    }

    public function removeManager(User $manager): static
    {
        $this->managers->removeElement($manager);

        return $this;
    }

    /**
     * Remplace tous les managers (à utiliser avec précaution)
     */
    public function setManagers(iterable $managers): static
    {
        $this->managers = new ArrayCollection();
        foreach ($managers as $manager) {
            $this->addManager($manager);
        }

        return $this;
    }
}
