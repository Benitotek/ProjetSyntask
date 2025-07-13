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
    private ?UserRole $role = UserRole::EMPLOYE;  // Valeur par défaut

    #[ORM\Column(type: 'json', nullable: true)]
    private array $roles = [];

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

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $avatar = null;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: ResetPasswordRequest::class, cascade: ['persist'])]
    #[ORM\OrderBy(['requestedAt' => 'DESC'])]
    private Collection $resetPasswordRequests;

    #[ORM\OneToMany(mappedBy: "chefproject", targetEntity: Project::class)]
    private Collection $projectsGeres;

    #[ORM\ManyToMany(targetEntity: Project::class, mappedBy: 'membres')]
    private Collection $projectsAssignes;

    #[ORM\OneToMany(mappedBy: "assignedUser", targetEntity: Task::class)]
    private Collection $tachesAssignees;


    /**
     * @var Collection<int, Activity>
     */
    #[ORM\OneToMany(mappedBy: "user", targetEntity: Activity::class)]
    private Collection $activities;

    public function __construct()
    {

        $this->projectsGeres = new ArrayCollection();
        $this->projectsAssignes = new ArrayCollection();
        $this->tachesAssignees = new ArrayCollection();
        $this->dateCreation = new \DateTime();
        $this->dateMaj = new \DateTime();
        $this->estActif = true;
        $this->isVerified = false;
        $this->activities = new ArrayCollection();
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
        // Si la propriété roles est null ou vide, utiliser un tableau vide
        $roles = $this->roles ?: [];

        // Convertir l'enum en rôles Symfony si nécessaire
        if ($this->role) {
            switch ($this->role) {
                case UserRole::ADMIN:
                    $roles[] = 'ROLE_ADMIN';
                    $roles[] = 'ROLE_DIRECTEUR';
                    $roles[] = 'ROLE_CHEF_PROJET';
                    $roles[] = 'ROLE_EMPLOYE';
                    break;
                case UserRole::DIRECTEUR:
                    $roles[] = 'ROLE_DIRECTEUR';
                    $roles[] = 'ROLE_CHEF_PROJET';
                    $roles[] = 'ROLE_EMPLOYE';
                    break;
                case UserRole::CHEF_PROJET:  // Correction ici
                    $roles[] = 'ROLE_CHEF_PROJET';
                    $roles[] = 'ROLE_EMPLOYE';
                    break;
                case UserRole::MEMBRE:
                case UserRole::EMPLOYE:
                    $roles[] = 'ROLE_EMPLOYE';
                    break;
            }
        }

        // Garantir que ROLE_USER est toujours présent
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;
        return $this;
    }
    /**
     * Returns an array of roles as expected by Symfony.
     */
    // public function getRoles(): array
    // {
    //     return [$this->role?->value ?? UserRole::EMPLOYE->value];
    // }

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

    public function getEstActif(): ?bool
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

    public function getprojectsGeres(): Collection
    {
        return $this->projectsGeres;
    }
    public function addprojectGere(Project $project): self
    {
        if (!$this->projectsGeres->contains($project)) {
            $this->projectsGeres->add($project);
            $project->setChefproject($this);
        }

        return $this;
    }

    public function removeprojectGere(Project $project): self
    {
        if ($this->projectsGeres->removeElement($project)) {
            // set the owning side to null (unless already changed)
            if ($project->getChefproject() === $this) {
                $project->setChefproject(null);
            }
        }

        return $this;
    }
    /**
     * @return Collection<int, Task>
     */
    public function getTachesAssignees(): Collection
    {
        return $this->tachesAssignees;
    }

    public function addTachesAssignee(Task $tache): self
    {
        if (!$this->tachesAssignees->contains($tache)) {
            $this->tachesAssignees->add($tache);
            $tache->setAssignedUser($this);
        }

        return $this;
    }

    public function removeTachesAssignee(Task $tache): self
    {
        if ($this->tachesAssignees->removeElement($tache)) {
            // set the owning side to null (unless already changed)
            if ($tache->getAssignedUser() === $this) {
                $tache->setAssignedUser(null);
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

    public function getAvatar(): ?string
    {
        return $this->avatar;
    }

    public function setAvatar(?string $avatar): static
    {
        $this->avatar = $avatar;
        return $this;
    }

    /**
     * @return Collection<int, Activity>
     */
    public function getActivities(): Collection
    {
        return $this->activities;
    }

    public function addActivity(Activity $activity): self
    {
        if (!$this->activities->contains($activity)) {
            $this->activities->add($activity);
            $activity->setUser($this);
        }

        return $this;
    }

    public function removeActivity(Activity $activity): self
    {
        if ($this->activities->removeElement($activity)) {
            // set the owning side to null (unless already changed)
            if ($activity->getUser() === $this) {
                $activity->setUser(null);
            }
        }

        return $this;
    }
    /**
     * Retourne les initiales de l'utilisateur
     */
    public function getInitials(): string
    {
        return mb_substr($this->prenom, 0, 1) . mb_substr($this->nom, 0, 1);
    }

    /**
     * Retourne les tâches non terminées assignées à l'utilisateur
     */
    public function getOpenTasks(): array
    {
        return $this->tachesAssignees->filter(function (Task $task) {
            return $task->getStatut() !== 'terminée' && !$task->isOverdue();
        })->toArray();
    }

    /**
     * Retourne les tâches en retard assignées à l'utilisateur
     */
    public function getOverdueTasks(): array
    {
        return $this->tachesAssignees->filter(function (Task $task) {
            return $task->isOverdue();
        })->toArray();
    }

    /**
     * Vérifie si l'utilisateur est membre d'un projet spécifique
     */
    public function isProjectMember(Project $project): bool
    {
        // Si l'utilisateur est le créateur du projet
        if ($project->getChefproject() === $this) {
            return true;
        }

        // Si l'utilisateur est dans la liste des membres
        if ($project->getMembres()->contains($this)) {
            return true;
        }

        return false;
    }

    /**
     * Vérifie si l'utilisateur est assigné à une tâche spécifique
     */
    public function isTaskAssignee(Task $task): bool
    {
        return $task->getAssignedUser() === $this;
    }

    /**
     * Vérifie si l'utilisateur a le rôle de chef de projet pour un projet spécifique
     * Vérifie si l'utilisateur est chef de projet (rôle) ET membre du projet
     */
    public function isChefProjetOf(Project $project): bool
    {
        return $this->hasRole('ROLE_CHEF_PROJET') && $project->getMembres()->contains($this);
    }
}
