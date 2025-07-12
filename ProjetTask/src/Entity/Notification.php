<?php

namespace App\Entity;

use App\Repository\NotificationRepository;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NotificationRepository::class)]
class Notification
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $titre = null;

    #[ORM\Column(length: 255)]
    private ?string $message = null;

    #[ORM\Column(length: 255)]
    private ?string $lien = null;

    #[ORM\Column]
    private ?bool $estLue = false;

     #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $dateCreation = null;
    
    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'notifications')]
    #[ORM\JoinColumn(name: "user_id", referencedColumnName: "id", nullable: false)]
    private ?User $user = null;
    
    #[ORM\Column(length: 30)]
    private ?string $type = 'info'; // info, success, warning, error
    
    public function __construct()
    {
        $this->dateCreation = new \DateTime();
        $this->estLue = false;
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

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(string $message): static
    {
        $this->message = $message;

        return $this;
    }

    public function getLien(): ?string
    {
        return $this->lien;
    }

    public function setLien(string $lien): static
    {
        $this->lien = $lien;

        return $this;
    }

    public function isEstLue(): ?bool
    {
        return $this->estLue;
    }

    public function setEstLue(bool $estLue): static
    {
        $this->estLue = $estLue;

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

    public function getUser(): ?self
    {
        return $this->user;
    }

    public function setUser(?self $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }
     /**
     * Marque la notification comme lue
     */
    public function markAsRead(): static
    {
        $this->estLue = true;
        return $this;
    }
    
    /**
     * Génère l'icône CSS appropriée en fonction du type
     */
    public function getIconClass(): string
    {
        return match($this->type) {
            'success' => 'fa-check-circle',
            'warning' => 'fa-exclamation-triangle',
            'error' => 'fa-times-circle',
            default => 'fa-info-circle'
        };
    }
}
