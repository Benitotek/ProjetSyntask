<?php

namespace App\EventListener;

use App\Entity\User;
use App\Enum\UserRole;
use App\Security\RoleConverter;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;

#[AsDoctrineListener(Events::prePersist)]
#[AsDoctrineListener(Events::preUpdate)]
class UserRoleSynchronizer
{
    private RoleConverter $roleConverter;

    public function __construct(RoleConverter $roleConverter)
    {
        $this->roleConverter = $roleConverter;
    }

    public function prePersist(LifecycleEventArgs $args): void
    {
        $this->synchronizeRoles($args->getObject());
    }

    public function preUpdate(LifecycleEventArgs $args): void
    {
        $this->synchronizeRoles($args->getObject());
    }

    private function synchronizeRoles($entity): void
    {
        if (!$entity instanceof User) {
            return;
        }

        // Si l'enum role est défini, synchroniser avec les roles[]
        if ($entity->getRole() !== null) {
            $entity->setRoles($this->roleConverter->convertEnumToRoles($entity->getRole()));
        }
    }
}
