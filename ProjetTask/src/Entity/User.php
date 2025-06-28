<?php

namespace App\Entity;

use App\Enum\UserStatus;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\NotBlank;
use App\Entity\ResetPasswordRequest;
use App\Entity\Project;
use App\Entity\Task;

#[ORM\Table(name: 'user')]
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[UniqueEntity(fields: ['email'], message: 'Un compte existe déjà avec cette adresse email')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    // Constantes pour les statuts
    public const STATUT_ACTIF = 'ACTIF';

    /**
     * @var string|null
     */
    private ?string $plainPassword = null;
    public const STATUT_INACTIF = 'INACTIF';
    public const STATUT_EN_CONGE = 'EN_CONGE';
    public const STATUT_ABSENT = 'ABSENT';

    public const ROLE_ADMIN = 'ROLE_ADMIN';
    public const ROLE_DIRECTEUR = 'ROLE_DIRECTEUR';
    public const ROLE_CHEF_PROJET = 'ROLE_CHEF_PROJET';
    public const ROLE_EMPLOYE = 'ROLE_EMPLOYE';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Assert\NotBlank(message: "Le nom est obligatoire")]
    #[Assert\Length(max: 50, maxMessage: "Le nom doit contenir au plus 50 caractères")]
    #[ORM\Column(length: 50)]
    private ?string $nom = null;

    #[Assert\NotBlank(message: "Le prénom est obligatoire")]
    #[Assert\Length(max: 50, maxMessage: "Le prénom doit contenir au plus 50 caractères")]
    #[ORM\Column(length: 50)]
    private ?string $prenom = null;

    #[Assert\NotBlank(message: "Le statut est obligatoire")]
    #[ORM\Column(type: Types::STRING, length: 20)]
    #[Assert\Choice(choices: [self::STATUT_ACTIF, self::STATUT_INACTIF, self::STATUT_EN_CONGE, self::STATUT_ABSENT])]
    private ?string $statut = self::STATUT_ACTIF;
    // #[ORM\Column(type: Types::JSON)]
    // private array $statut = [];

    #Notblank(message: "Le rôle est obligatoire")
    #[Assert\NotBlank(message: "Le rôle est obligatoire")]
    #[ORM\Column(type: Types::JSON)]
    private array $roles = [];

    #[Assert\NotBlank(message: "L'email est obligatoire")]
    #[Assert\Length(max: 180, maxMessage: "L'email doit contenir au plus 180 caractères")]
    #[Assert\Email(message: "L'email '{{ value }}' n'est pas un email valide")]
    #[ORM\Column(type: Types::STRING, length: 180, unique: true)]
    private ?string $email = null;

    // #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    // private ?string $role = null;

    #[Assert\NotBlank(message: "Le mot de passe est obligatoire")]
    #[Assert\Length(min: 6, minMessage: "Le mot de passe doit contenir au moins 6 caractères")]
    #[ORM\Column(type: Types::STRING, length: 255)]
    private ?string $Mdp = null;

    #[ORM\Column]
    private ?bool $estActif = true;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateCreation = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateMaj = null;

    /**
     * @var Collection<int, ResetPasswordRequest>
     */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: ResetPasswordRequest::class)]
    private Collection $resetPasswordRequests;





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

    // public function getStatut(): ?string
    // {
    //     return $this->statut;
    // }

    // public function setStatut(string $statut): static
    // {
    //     $this->statut = $statut;

    //     // Optionally update roles if needed
    //     // $this->roles = [$statut];

    //     return $this;
    // }

    // public function getStatut(): ?string
    // {
    //     return $this->statut;
    // }

    // public function setStatut(string $statut): static
    // {
    //     $this->statut = $statut;

    //     return $this;
    // }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }
    public function getRoles(): array
    {
        $roles = $this->roles ?? [];
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';
        return array_unique($roles);
    }
    public function setRoles(array $roles): static
    {
        $this->roles = $roles ?? [];
        return $this;
    }

    /**
     * Returns the password used to authenticate the user.
     *
     * @return string|null The password
     */

    public function getMdp(): ?string
    {
        return $this->Mdp;
    }

    /**
     * Returns the hashed password for authentication (required by PasswordAuthenticatedUserInterface).
     *
     * @return string|null
     */
    public function getPassword(): ?string
    {
        return $this->Mdp;
    }

    public function setMdp(string $Mdp): self
    {
        $this->Mdp = $Mdp;
        return $this;
    }
    /**
     * @see UserInterface
     */

    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        $this->plainPassword = null;
    }
    /**
     * Returns the status of the user.
     *
     * @return string|null The status
     */
    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;
        return $this;
    }

    /**
     * Returns the identifier for this user (e.g. email).
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }
    /**
     * Removes sensitive data from the user.
     */
    // Duplicate eraseCredentials removed to avoid redeclaration error.
    // $this->plainPassword = null;

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
    /**
     * @var Collection<int, Project>
     */
    // Projets gérés (chef de projet)
    #[ORM\OneToMany(targetEntity: Project::class, mappedBy: 'chef_Projet')]
    private Collection $projetsGeres;

    /**
     * @return Collection<int, Project>
     */
    public function getProjetsGeres(): Collection
    {
        return $this->projetsGeres;
    }

    /**
     * @var Collection<int, Project>
     */
    #[ORM\ManyToMany(targetEntity: Project::class, mappedBy: 'membres')]
    private Collection $projetsAssignes;

    /**
     * @return Collection<int, Project>
     */
    public function getProjetsAssignes(): Collection
    {
        return $this->projetsAssignes;
    }

    /**
     * @var Collection<int, Task>
     */
    // Tâches où ce user est assigné (OneToMany, inversé de assignedUser)
    // Tâches où ce user est assigné
    #[ORM\OneToMany(mappedBy: 'assignedUser', targetEntity: Task::class)]
    private Collection $tachesAssignees;

    /**
     * @return Collection<int, Task>
     */
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
        if ($this->tachesAssignees->removeElement($task)) {
            // set the owning side to null (unless already changed)
            if ($task->getAssignedUser() === $this) {
                $task->setAssignedUser(null);
            }
        }

        return $this;
    }
    // Méthode pour faciliter la vérification des rôles
    public function hasRole(string $role): bool
    {
        return in_array($role, $this->getRoles(), true);
    }

    #[ORM\Column(type: 'boolean')]
    private bool $isVerified = false;

    public function __construct()
    {
        // Initialize all collections and properties
        $this->resetPasswordRequests = new ArrayCollection();

        $this->projetsGeres = new ArrayCollection();
        $this->projetsAssignes = new ArrayCollection();
        $this->tachesAssignees = new ArrayCollection();
        $this->dateCreation = new \DateTime();
        $this->dateMaj = new \DateTime();
        $this->statut = self::STATUT_ACTIF;
        $this->estActif = true;
        $this->isVerified = false;
    }

    /**
     * Needed for Symfony < 5.3 compatibility.
     */
    public function getUsername(): string
    {
        return (string) $this->email;
    }

    public function setIsVerified(bool $isVerified): static
    {
        $this->isVerified = $isVerified;

        return $this;
    }
}
