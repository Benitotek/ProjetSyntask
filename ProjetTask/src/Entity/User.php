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
use App\Entity\Notification;
use App\Entity\Activity;
use App\Entity\TaskList;
use App\Entity\Tag;

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
    private ?bool $isActive = true;


    #[ORM\Column(name: 'is_deleted', type: 'boolean', options: ['default' => 0])]
    private ?bool $isDeleted = false;

    #[ORM\Column(type: 'boolean')]
    private ?bool $EstActif = true;

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

    #[ORM\OneToMany(mappedBy: "user", targetEntity: Notification::class, cascade: ["persist", "remove"], orphanRemoval: true)]
    private Collection $notifications;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $lastLoginAt = null;

    #[ORM\OneToMany(mappedBy: "user", targetEntity: Activity::class)]
    private Collection $activities;

    #[ORM\OneToMany(mappedBy: 'createdBy', targetEntity: TaskList::class)]
    private Collection $taskLists;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Tag::class)]
    private Collection $tags;


    public function __construct()
    {

        $this->resetPasswordRequests = new ArrayCollection();
        $this->notifications = new \Doctrine\Common\Collections\ArrayCollection();
        $this->projectsGeres = new ArrayCollection();
        $this->projectsAssignes = new ArrayCollection();
        $this->tachesAssignees = new ArrayCollection();
        $this->dateCreation = new \DateTime();
        $this->dateMaj = new \DateTime();
        $this->isActive = true;
        $this->isVerified = false;
        $this->isDeleted = false;
        $this->EstActif = true;
        $this->activities = new ArrayCollection();
        $this->taskLists = new ArrayCollection();
        $this->tags = new ArrayCollection();
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
        $roles = $this->roles ?: [];
        if ($this->role) {
            switch ($this->role) {
                case UserRole::ADMIN:
                    $roles = array_merge($roles, ['ROLE_ADMIN', 'ROLE_DIRECTEUR', 'ROLE_CHEF_PROJET', 'ROLE_EMPLOYE']);
                    break;
                case UserRole::DIRECTEUR:
                    $roles = array_merge($roles, ['ROLE_DIRECTEUR', 'ROLE_CHEF_PROJET', 'ROLE_EMPLOYE']);
                    break;
                case UserRole::CHEF_PROJET:
                    $roles = array_merge($roles, ['ROLE_CHEF_PROJET', 'ROLE_EMPLOYE']);
                    break;
                case UserRole::MEMBRE:
                case UserRole::EMPLOYE:
                    $roles[] = 'ROLE_EMPLOYE';
                case UserRole::SYSTEM:
                    $roles[] = 'ROLE_SYSTEM';
                    break;
            }
        }
        $roles[] = 'ROLE_USER';
        return array_unique($roles);
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;
        return $this;
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
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function getUsername(): string
    {
        return (string) $this->email;
    }

    public function getIsActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(?bool $isActive): self
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getEstActif(): ?bool
    {
        return $this->EstActif;
    }

    public function setEstActif(?bool $EstActif): self
    {
        $this->EstActif = $EstActif;
        return $this;
    }
    public function is_Deleted(): bool
    {
        return $this->isDeleted;
    }
    public function setIsDeleted(bool $deleted): self
    {
        $this->isDeleted = $deleted;
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

    public function getLastLoginAt(): ?\DateTimeInterface
    {
        return $this->lastLoginAt;
    }

    public function setLastLoginAt(?\DateTimeInterface $lastLoginAt): self
    {
        $this->lastLoginAt = $lastLoginAt;
        return $this;
    }

    public function getProjectsGeres(): Collection
    {
        return $this->projectsGeres;
    }
    public function addProjectGere(Project $project): self
    {
        if (!$this->projectsGeres->contains($project)) {
            $this->projectsGeres->add($project);
            $project->setChefproject($this);
        }
        return $this;
    }
    public function removeProjectGere(Project $project): self
    {
        if ($this->projectsGeres->removeElement($project) && $project->getChefproject() === $this) {
            $project->setChefproject(null);
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
        if ($this->tachesAssignees->removeElement($tache) && $tache->getAssignedUser() === $this) {
            $tache->setAssignedUser(null);
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
    public function getNotifications(): Collection
    {
        return $this->notifications;
    }
    public function addNotification(Notification $notification): self
    {
        if (!$this->notifications->contains($notification)) {
            $this->notifications->add($notification);
        }
        return $this;
    }
    public function removeNotification(Notification $notification): self
    {
        if ($this->notifications->removeElement($notification) && $notification->getUser() === $this) {
            $notification->setUser(null);
        }
        return $this;
    }

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
        if ($this->activities->removeElement($activity) && $activity->getUser() === $this) {
            $activity->setUser(null);
        }
        return $this;
    }

    public function getInitials(): string
    {
        return mb_strtoupper(mb_substr((string)$this->prenom, 0, 1) . mb_substr((string)$this->nom, 0, 1));
    }

    public function getOpenTasks(): array
    {
        return $this->tachesAssignees->filter(fn(Task $t) => $t->getStatut() !== \App\Enum\TaskStatut::TERMINER && !$t->isOverdue())->toArray();
    }

    public function getOverdueTasks(): array
    {
        return $this->tachesAssignees->filter(fn(Task $t) => $t->isOverdue())->toArray();
    }

    public function isProjectMember(Project $project): bool
    {
        if ($project->getChefproject() === $this) return true;
        return $project->getMembres()->contains($this);
    }

    public function isTaskAssignee(Task $task): bool
    {
        return $task->getAssignedUser() === $this;
    }

    public function isChefProjetOf(Project $project): bool
    {
        return $this->hasRole('ROLE_CHEF_PROJET') && $project->getMembres()->contains($this);
    }

    public function getChefProjetOf(Project $project): ?User
    {
        return $this->hasRole('ROLE_CHEF_PROJET') && $project->getMembres()->contains($this) ? $this : null;
    }

    public function getChefProjet(): ?User
    {
        return $this->hasRole('ROLE_CHEF_PROJET') ? $this : null;
    }

    public function getProjects(): Collection
    {
        return $this->projectsGeres;
    }

    public function getProjectsAssignes(): Collection
    {
        return $this->projectsAssignes;
    }
    public function addProjectsAssigne(Project $projectsAssigne): self
    {
        if (!$this->projectsAssignes->contains($projectsAssigne)) {
            $this->projectsAssignes->add($projectsAssigne);
            $projectsAssigne->addMembre($this);
        }
        return $this;
    }

    public function removeProjectsAssigne(Project $projectsAssigne): self
    {
        if ($this->projectsAssignes->removeElement($projectsAssigne) && $projectsAssigne->getMembres()->removeElement($this)) {
            $projectsAssigne->removeMembre($this);
        }
        return $this;
    }
    public function getResetPasswordRequests(): Collection
    {
        return $this->resetPasswordRequests;
    }

    public function addResetPasswordRequest(ResetPasswordRequest $resetPasswordRequest): self
    {
        if (!$this->resetPasswordRequests->contains($resetPasswordRequest)) {
            $this->resetPasswordRequests->add($resetPasswordRequest);
            $resetPasswordRequest->setUser($this);
        }
        return $this;
    }

    public function __toString(): string
    {
        return $this->nom . ' ' . $this->prenom;
    }
    public function getTaskLists(): Collection
    {
        return $this->taskLists;
    }

    public function getTags(): Collection
    {
        return $this->tags;
    }
}
