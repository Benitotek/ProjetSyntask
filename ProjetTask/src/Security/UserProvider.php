<?php

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class UserProvider implements UserProviderInterface
{
    private $userRepository;
    private $roleConverter;

    public function __construct(UserRepository $userRepository, RoleConverter $roleConverter)
    {
        $this->userRepository = $userRepository;
        $this->roleConverter = $roleConverter;
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $user = $this->userRepository->findOneBy(['email' => $identifier]);

        if (!$user) {
            throw new UserNotFoundException(sprintf('Utilisateur "%s" non trouvé.', $identifier));
        }

        // Synchroniser les rôles basés sur l'enum
        if ($user->getRole()) {
            $user->setRoles($this->roleConverter->convertEnumToRoles($user->getRole()));
        }

        return $user;
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof User) {
            throw new \InvalidArgumentException('Invalid user class');
        }

        // Recharger l'utilisateur avec le bon identifiant
        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return User::class === $class || is_subclass_of($class, User::class);
    }
}
