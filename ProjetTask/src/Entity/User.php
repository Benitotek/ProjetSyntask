<?php

namespace App\Entity;

use App\Enum\Userstatut;
use App\Enum\UserRole;
use App\Repository\UserRepository;
use App\Entity\ResetPasswordRequest;
use App\Entity\Project;
use App\Entity\Task;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Table(name: 'user')]
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[UniqueEntity(fields: ['email'], message: 'Un compte existe déjà avec cette adresse email')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Assert\NotBlank(message: "Le nom est obligatoire")]
    #[Assert\Length(max: 50)]
    #[ORM\Column(length: 50)]
    private ?string $nom = null;

    #[Assert\NotBlank(message: "Le prénom est obligatoire")]
    #[Assert\Length(max: 50)]
    #[ORM\Column(length: 50)]
    private ?string $prenom = null;

    #[Assert\NotBlank(message: "Le statut est obligatoire")]
    #[ORM\Column(enumType: Userstatut::class)]
    private ?Userstatut $statut = null;

    #[ORM\Column(enumType: UserRole::class)]
    private ?UserRole $role = null;

    #[Assert\NotBlank(message: "L'email est obligatoire")]
    #[Assert\Length(max: 180)]
    #[Assert\Email]
    #[ORM\Column(type: Types::STRING, length: 180, unique: true)]
    private ?string $email = null;

    #[Assert\NotBlank(message: "Le mot de passe est obligatoire")]
    #[Assert\Length(min: 6)]
    #[ORM\Column(type: Types::STRING, length: 255)]
    private ?string $Mdp = null;

    #[ORM\Column]
    private ?bool $estActif = true;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateCreation = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateMaj = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isVerified = false;

    /**
     * @ManyToOne(targetEntity=User::class, inversedBy="resetPasswordRequests")
     */
    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'resetPasswordRequests')]
    #[ORM\JoinColumn(nullable: false)]
    private $user;
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: ResetPasswordRequest::class, cascade: ['persist'])]
    #[ORM\OrderBy(['requestedAt' => 'DESC'])]
    private Collection $resetPasswordRequests;

    #[ORM\OneToMany(targetEntity: Project::class, mappedBy: 'Chef_Projet')]
    private Collection $projetsGeres;

    #[ORM\ManyToMany(targetEntity: Project::class, mappedBy: 'membres')]
    private Collection $projetsAssignes;

    #[ORM\OneToMany(mappedBy: 'assignedUser', targetEntity: Task::class)]
    private Collection $tachesAssignees;

    public function __construct()
    {
        $this->resetPasswordRequests = new ArrayCollection();
        $this->projetsGeres = new ArrayCollection();
        $this->projetsAssignes = new ArrayCollection();
        $this->tachesAssignees = new ArrayCollection();
        $this->dateCreation = new \DateTime();
        $this->dateMaj = new \DateTime();
        $this->estActif = true;
        $this->isVerified = false;
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

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): static
    {
        $this->prenom = $prenom;
        return $this;
    }

    public function getstatut(): ?Userstatut
    {
        return $this->statut;
    }

    public function setstatut(Userstatut $statut): static
    {
        $this->statut = $statut;
        return $this;
    }

    public function getRole(): ?UserRole
    {
        return $this->role;
    }

    public function setRole(UserRole $role): static
    {
        $this->role = $role;
        return $this;
    }

    public function getRoles(): array
    {
        return [$this->role?->value ?? 'ROLE_EMPLOYE'];
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->getRoles(), true);
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->Mdp;
    }

    public function setMdp(string $Mdp): self
    {
        $this->Mdp = $Mdp;
        return $this;
    }

    public function getMdp(): ?string
    {
        return $this->Mdp;
    }

    public function eraseCredentials(): void
    {
        // Clear sensitive data here (if applicable)
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function getUsername(): string
    {
        return (string) $this->email;
    }

    public function isEstActif(): ?bool
    {
        return $this->estActif;
    }

    public function setEstActif(bool $estActif): static
    {
        $this->estActif = $estActif;
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

    public function getFullName(): string
    {
        return $this->prenom . ' ' . $this->nom;
    }

    public function getProjetsGeres(): Collection
    {
        return $this->projetsGeres;
    }

    public function getProjetsAssignes(): Collection
    {
        return $this->projetsAssignes;
    }

    public function getTachesAssignees(): Collection
    {
        return $this->tachesAssignees;
    }

    public function addTacheAssignee(Task $task): static
    {
        if (!$this->tachesAssignees->contains($task)) {
            $this->tachesAssignees->add($task);
            $task->setAssignedUser($this);
        }
        return $this;
    }

    public function removeTacheAssignee(Task $task): static
    {
        if ($this->tachesAssignees->removeElement($task) && $task->getAssignedUser() === $this) {
            $task->setAssignedUser(null);
        }
        return $this;
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): static
    {
        $this->isVerified = $isVerified;
        return $this;
    }
}
