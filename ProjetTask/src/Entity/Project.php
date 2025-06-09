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

    /**
     * @var Collection<int, TaskLIST>
     */
    #[ORM\OneToMany(targetEntity: TaskLIST::class, mappedBy: 'project')]
    private Collection $taskLISTs;

    /**
     * @var Collection<int, UserProject>
     */
    #[ORM\OneToMany(targetEntity: UserProject::class, mappedBy: 'project')]
    private Collection $userProjects;

    public function __construct()
    {
        $this->managers = new ArrayCollection();
        $this->taskLISTs = new ArrayCollection();
        $this->userProjects = new ArrayCollection();
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

    /**
     * @return Collection<int, TaskLIST>
     */
    public function getTaskLISTs(): Collection
    {
        return $this->taskLISTs;
    }

    public function addTaskLIST(TaskLIST $taskLIST): static
    {
        if (!$this->taskLISTs->contains($taskLIST)) {
            $this->taskLISTs->add($taskLIST);
            $taskLIST->setProject($this);
        }

        return $this;
    }

    public function removeTaskLIST(TaskLIST $taskLIST): static
    {
        if ($this->taskLISTs->removeElement($taskLIST)) {
            // set the owning side to null (unless already changed)
            if ($taskLIST->getProject() === $this) {
                $taskLIST->setProject(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, UserProject>
     */
    public function getUserProjects(): Collection
    {
        return $this->userProjects;
    }

    public function addUserProject(UserProject $userProject): static
    {
        if (!$this->userProjects->contains($userProject)) {
            $this->userProjects->add($userProject);
            $userProject->setProject($this);
        }

        return $this;
    }

    public function removeUserProject(UserProject $userProject): static
    {
        if ($this->userProjects->removeElement($userProject)) {
            // set the owning side to null (unless already changed)
            if ($userProject->getProject() === $this) {
                $userProject->setProject(null);
            }
        }

        return $this;
    }
}
