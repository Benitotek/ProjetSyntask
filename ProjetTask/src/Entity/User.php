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
    // #[ORM\Column(type: Types::JSON)]
    // #[ORM\Column(type: Types::JSON, nullable: true)]
    // #[Assert\Choice(
    //     choices: [self::ROLE_ADMIN, self::ROLE_DIRECTEUR, self::ROLE_CHEF_PROJET, self::ROLE_EMPLOYE],
    //     multiple: true
    // )]
    // private array $roles = [];
    #[ORM\Column(name: "role", type: "string", length: 255)]
    private ?string $role = null;

    #[Assert\NotBlank(message: "L'email' est obligatoire")]
    #[Assert\Length(max: 180, maxMessage: "L'email doit contenir au plus 180 caractères")]
    #[ORM\Column(length: 180, unique: true)]
    private ?string $email = null;

    // #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    // private ?string $role = null;

    #[Assert\NotBlank(message: "Le mot de passe est obligatoire")]
    #[Assert\Length(min: 6, minMessage: "Le mot de passe doit contenir au moins 6 caractères")]
    #[ORM\Column(length: 255)]
    private ?string $mdp = null;
    // Utiliser le nom exact de la colonne dans la base de données
    // #[ORM\Column(name: "mdp")]
    // private ?string $password = null;
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

    // Removed duplicate constructor; initialization is handled in the unified constructor below.



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

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    // Méthodes pour la compatibilité avec le champ mdp
    // public function getMdp(): ?string
    // {
    //     return $this->password;
    // }

    // public function setMdp(string $password): static
    // {
    //     $this->password = $password;
    //     return $this;
    // }
    public function getMdp(): ?string
    {
        return $this->mdp;
    }

    public function setMdp(string $mdp): self
    {
        $this->mdp = $mdp;
        return $this;
    }
    /**
     * @see UserInterface
     */

    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }
    // public function getRoles(): array
    // {
    //     // Garantir que chaque utilisateur a au moins ROLE_USER
    //     if (empty($this->roles)) {
    //         $this->roles[] = 'ROLE_USER';
    //     }

    //     return array_unique($this->roles);
    // }

    /**
     * Cette méthode n'est pas directement mappée à la base de données
     * Elle est utilisée pour compatibilité avec Symfony Security
     */
    public function getRoles(): array
    {
        // Cette méthode doit retourner un tableau de rôles
        if ($this->role) {
            return [$this->role];
        }

        // Assurer qu'un utilisateur a au moins ROLE_USER
        return ['ROLE_USER'];
    }

    /**
     * Setter pour la compatibilité avec certaines parties de Symfony
     */
    public function setRoles(array $roles): static
    {
        // Si des rôles sont fournis, prendre le premier comme rôle principal
        if (!empty($roles)) {
            $this->role = $roles[0];
        }

        return $this;
    }

    /**
     * Getter pour la colonne 'role'
     */
    public function getRole(): ?string
    {
        return $this->role;
    }

    /**
     * Setter pour la colonne 'role'
     */
    public function setRole(?string $role): static
    {
        $this->role = $role;
        return $this;
    }
    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;

        // Synchroniser le rôle avec le statut
        switch ($statut) {
            case self::STATUT_ACTIF:
                $this->role = self::ROLE_EMPLOYE;
                break;
            case self::STATUT_INACTIF:
                $this->role = self::ROLE_EMPLOYE;
                break;
            case self::STATUT_EN_CONGE:
                $this->role = self::ROLE_EMPLOYE;
                break;
            case self::STATUT_ABSENT:
                $this->role = self::ROLE_EMPLOYE;
                break;
            default:
                $this->role = 'ROLE_USER';
        }

        return $this;
    }
    // public function getRoles(): array
    // {
    //     $roles = $this->roles;
    //     // guarantee every user at least has ROLE_USER
    //     $roles[] = 'ROLE_USER';
    //     return array_unique($roles);
    // }

    // public function setRoles(array $roles): static
    // {

    //     $this->roles = $roles;
    //     return $this;
    // }
    /**
     * Version 1 bug sur les roles test avec version 2 (mise en place de ROLES) dans methode 1 commenter a la suite
     * 
     * 
     * Returns the roles granted to the user.
     */
    // public function getRole(): array
    // {
    //     // guarantee every user at least has ROLE_USER
    //     $role = $this->role;
    //     $role[] = 'ROLE_USER';

    //     return array_unique($role);
    // }

    /**
     * Returns the roles granted to the user.
     */
    // public function getRoles(): array
    // {
    //     return $this->getRole();
    // }

    // public function setRole(array $role): static
    // {
    //     $this->role = $role;
    //     return $this;
    // }
    // Version 1.5 a revoir la methode getRoles() et setRoles() pour utiliser getRole() et setRole()
    // Assurez-vous que la méthode getRoles() est correcte pour l'interface UserInterface
    // public function getRoles(): array
    // {
    //     // Votre code actuel transforme le rôle unique en tableau
    //     return [$this->getRole()];
    // }

    // // ... vos autres méthodes

    // // Si vous avez une méthode setRoles quelque part, elle doit être adaptée pour utiliser setRole
    // public function setRoles(array $roles): self
    // {
    //     // Si vous devez implémenter setRoles pour compatibilité
    //     if (count($roles) > 0) {
    //         $this->setRole($roles[0]);
    //     }
    //     return $this;
    // }


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
    // public function eraseCredentials(): void
    // {
    //     // If you store any temporary, sensitive data on the user, clear it here
    //     // $this->plainPassword = null;
    // }

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
