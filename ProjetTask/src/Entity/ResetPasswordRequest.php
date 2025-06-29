<?php

namespace App\Entity;

use App\Repository\ResetPasswordRequestRepository;
use Doctrine\ORM\Mapping as ORM;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordRequestInterface;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordRequestTrait;

#[ORM\Entity(repositoryClass: ResetPasswordRequestRepository::class)]
class ResetPasswordRequest implements ResetPasswordRequestInterface
{
    // Utilise le trait qui définit déjà les propriétés requestedAt et expiresAt
    use ResetPasswordRequestTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

   /**
 * @ManyToOne(targetEntity=User::class, inversedBy="resetPasswordRequests")
 */
    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'resetPasswordRequests')]
    #[ORM\JoinColumn(nullable: false)]
private $user;

    // NE PAS définir à nouveau les propriétés requestedAt et expiresAt ici
    // SUPPRIMER ces propriétés si elles sont présentes dans votre classe

    public function __construct(object $user, \DateTimeInterface $expiresAt, string $selector, string $hashedToken)
    {
        $this->user = $user;

        // Initialise les propriétés du trait
        $this->initialize($expiresAt, $selector, $hashedToken);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): object
    {
        return $this->user;
    }
}
