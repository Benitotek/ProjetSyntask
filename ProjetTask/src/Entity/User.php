<?php

namespace App\Entity;

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

/**
 * @ORM\Table(name="user")
 */
#[ORM\Table(name: 'user')]
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[UniqueEntity(fields: ['email'], message: 'There is already an account with this email')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    public const STATUT_ACTIF = 'ACTIF';
    public const STATUT_INACTIF = 'INACTIF';
    public const STATUT_EN_CONGE = 'EN_CONGE';
    public const STATUT_ABSENT = 'ABSENT';

    public const ROLE_ADMIN = 'ROLE_ADMIN';
    public const ROLE_DIRECTEUR = 'ROLE_DIRECTEUR';
    public const ROLE_CHEF_DE_PROJET = 'ROLE_CHEF_DE_PROJET';
    public const ROLE_EMPLOYE = 'ROLE_EMPLOYE';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[NotBlank(message: "Le nom est obligatoire")]
    #[Assert\Length(max: 50, maxMessage: "Le nom doit contenir au plus 50 caractères")]
    #[ORM\Column(length: 50)]
    private ?string $nom = null;

    #[NotBlank(message: "Le prénom est obligatoire")]
    #[Assert\Length(max: 50, maxMessage: "Le prénom doit contenir au plus 50 caractères")]
    #[ORM\Column(length: 50)]
    private ?string $prenom = null;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: null)]
    #[Assert\Choice(choices: [self::STATUT_ACTIF, self::STATUT_INACTIF, self::STATUT_EN_CONGE, self::STATUT_ABSENT])]
    private ?string $statut = self::STATUT_ACTIF;
    // #[ORM\Column(type: Types::JSON)]
    // private array $statut = [];

    #Notblank(message: "Le rôle est obligatoire")
    #[Assert\NotBlank(message: "Le rôle est obligatoire")]
    #[ORM\Column(type: Types::JSON)]
    private array $role = [];

    #[NotBlank(message: "L'email' est obligatoire")]
    #[Assert\Length(max: 180, maxMessage: "L'email doit contenir au plus 180 caractères")]
    #[ORM\Column(length: 180)]
    private ?string $email = null;

    #[Assert\NotBlank(message: "Le mot de passe est obligatoire")]
    #[Assert\Length(min: 6, minMessage: "Le mot de passe doit contenir au moins 6 caractères")]
    #[ORM\Column(length: 255)]
    private ?string $mdp = null;

    #[ORM\Column]
    private ?bool $estActif = true;

    #[ORM\Column]
    private ?\DateTime $dateCreation = null;

    #[ORM\Column]
    private ?\DateTime $dateMaj = null;

    /**
     * @var Collection<int, UserProject>
     */
    #[ORM\OneToMany(targetEntity: UserProject::class, mappedBy: 'user')]
    private Collection $userProjects;

    #[ORM\Column]
    private bool $isVerified = true;

    // Removed duplicate constructor

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

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    // Removed duplicate getRole() and setRole() methods above; see unified version below.

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getMdp(): ?string
    {
        return $this->mdp;
    }

    public function setMdp(string $mdp): static
    {
        $this->mdp = $mdp;

        return $this;
    }

    /**
     * Returns the roles granted to the user.
     */
    public function getRole(): array
    {
        // guarantee every user at least has ROLE_USER
        $role = $this->role;
        $role[] = 'ROLE_USER';

        return array_unique($role);
    }

    /**
     * Returns the roles granted to the user.
     */
    public function getRoles(): array
    {
        return $this->getRole();
    }

    public function setRole(array $role): static
    {
        $this->role = $role;
        return $this;
    }
    /**
     * Returns the password used to authenticate the user.
     */
    public function getPassword(): ?string
    {
        return $this->mdp;
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
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
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
    /**
     * @var Collection<int, Project>
     */
    #[ORM\OneToMany(targetEntity: Project::class, mappedBy: 'gerant')]
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
    #[ORM\OneToMany(targetEntity: Project::class, mappedBy: 'assigne')]
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
    #[ORM\OneToMany(targetEntity: Task::class, mappedBy: 'assignee')]
    private Collection $tachesAssignees;

    public function __construct()
    {
        $this->userProjects = new ArrayCollection();
        $this->projetsGeres = new ArrayCollection();
        $this->projetsAssignes = new ArrayCollection();
        $this->tachesAssignees = new ArrayCollection();
        $this->dateCreation = new \DateTime();
        $this->dateMaj = new \DateTime();
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
            $userProject->setUser($this);
        }

        return $this;
    }

    public function removeUserProject(UserProject $userProject): static
    {
        if ($this->userProjects->removeElement($userProject)) {
            // set the owning side to null (unless already changed)
            if ($userProject->getUser() === $this) {
                $userProject->setUser(null);
            }
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
