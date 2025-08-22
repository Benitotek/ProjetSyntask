<?php

namespace App\Entity;

use App\Enum\ActivityType;
use App\Repository\ActivityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Twig\TwigFunction;

#[ORM\Entity(repositoryClass: ActivityRepository::class)]
#[ORM\Table(name: 'activity')]
class Activity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: "activities")]
    #[ORM\JoinColumn(name: "user_id", referencedColumnName: "id", nullable: false)]
    private User $user;

    #[ORM\Column(enumType: ActivityType::class)]
    private ?ActivityType $type = null;

    #[ORM\Column(length: 255)]
    private ?string $action = null;

    #[ORM\Column(length: 255)]
    private ?string $target = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $targetUrl = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateCreation = null;


    #[ORM\ManyToOne(targetEntity: Project::class, inversedBy: "activities")]
    private ?Project $project = null;


    public function __construct()
    {
        $this->dateCreation = new \DateTime();
    }
    public function getProject(): ?Project
    {
        return $this->project;
    }
    public function setProject(?Project $project): self
    {
        $this->project = $project;
        return $this;
    }
    public function getFunctions(): array
    {
        return [
            new TwigFunction('getActivityIcon', [$this, 'getActivityIcon']),
        ];
    }

    public function getActivityIcon(string $type): string
    {
        // Convertir le type string en enum ActivityType si possible
        try {
            $enumType = ActivityType::from($type);
            return $enumType->icon();
        } catch (\ValueError $e) {
            // Fallback pour les anciens types qui ne correspondent pas à l'enum
            return match ($type) {
                'project_create', 'project_update', 'project_delete' => 'folder',
                'task_create', 'task_update', 'task_delete', 'task_statut_change', 'task_assign' => 'tasks',
                'user_login', 'user_register' => 'user',
                'comment_create' => 'comment',
                default => 'info-circle',
            };
        }
    }
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getType(): ?ActivityType
    {
        return $this->type;
    }

    public function setType(ActivityType $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function setAction(string $action): static
    {
        $this->action = $action;
        return $this;
    }
    public function setDescription(string $description): static
    {
        $this->action = $description;
        return $this;
    }
    public function getTarget(): ?string
    {
        return $this->target;
    }

    public function setTarget(string $target): static
    {
        $this->target = $target;
        return $this;
    }

    public function getTargetUrl(): ?string
    {
        return $this->targetUrl;
    }

    public function setTargetUrl(?string $targetUrl): static
    {
        $this->targetUrl = $targetUrl;
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

    /**
     * Génère l'URL cible en fonction du type d'activité et de la cible
     */
    public function generateTargetUrl(): ?string
    {
        if ($this->targetUrl) {
            return $this->targetUrl;
        }

        // Logique pour construire l'URL en fonction du type et de la cible
        return match ($this->type) {
            ActivityType::PROJECT_CREATE,
            ActivityType::PROJECT_UPDATE,
            ActivityType::PROJECT_DELETE => '/project/' . $this->target,

            ActivityType::TASK_CREATE,
            ActivityType::TASK_UPDATE,
            ActivityType::TASK_DELETE,
            ActivityType::TASK_statut_CHANGE,
            ActivityType::TASK_ASSIGN => '/task/' . $this->target,

            ActivityType::USER_LOGIN,
            ActivityType::USER_REGISTER => '/user/' . $this->target,

            ActivityType::COMMENT_CREATE => '/comment/' . $this->target,

            default => null,
        };
    }
    public function getDescription(): ?string
    {
        return $this->action;
    }
}
