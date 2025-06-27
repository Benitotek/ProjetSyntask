<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\UserStatus;
use App\Form\UserType;
use App\Form\UserTypeForm;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[Route('/admin/users')]
#[IsGranted('ROLE_ADMIN')]
class UserController extends AbstractController
{
    /**
     * Convertit un statut en chaîne de caractères en objet UserStatus
     * Si la chaîne ne correspond pas à une valeur valide, renvoie null
     */
    private function stringToUserStatus(?string $statusString): ?UserStatus
    {
        if (empty($statusString)) {
            return null;
        }

        try {
            // Essayer de convertir directement si c'est une constante d'énumération
            return UserStatus::from($statusString);
        } catch (\ValueError $e) {
            // Si ce n'est pas une constante d'énumération valide,
            // essayer de trouver la correspondance par nom
            foreach (UserStatus::cases() as $case) {
                if (strtoupper($case->name) === strtoupper($statusString)) {
                    return $case;
                }
            }

            // Si c'est un nombre, essayer une conversion numérique
            if (is_numeric($statusString)) {
                $intStatus = (int)$statusString;
                foreach (UserStatus::cases() as $case) {
                    // Les enums peuvent être convertis en entiers
                    if ($case->value === $intStatus) {
                        return $case;
                    }
                }
            }
        }

        // Aucune correspondance trouvée
        return null;
    }

    /**
     * Corrige la méthode mapStatusToRole pour gérer des chaînes de caractères
     */
    private function mapStatusToRole($statut): string
    {
        // Si c'est déjà un UserStatus, l'utiliser directement
        if ($statut instanceof UserStatus) {
            $statusEnum = $statut;
        } else {
            // Sinon, essayer de le convertir
            $statusEnum = $this->stringToUserStatus($statut);

            // Si la conversion échoue, utiliser un rôle par défaut
            if ($statusEnum === null) {
                return 'ROLE_USER';
            }
        }

        // Maintenant que nous avons un UserStatus, on peut faire le mapping
        return match ($statusEnum) {
            UserStatus::ADMIN => 'ROLE_ADMIN',
            UserStatus::DIRECTEUR => 'ROLE_DIRECTEUR',
            UserStatus::CHEF_PROJET => 'ROLE_CHEF_DE_PROJET',
            UserStatus::EMPLOYE => 'ROLE_EMPLOYE',
            default => 'ROLE_USER',
        };
    }

    // Commentez temporairement cette ligne pour permettre l'accès
    // #[IsGranted('ROLE_ADMIN')]


    #[Route('/admin/fix-users', name: 'app_admin_fix_users')]
    public function fixUsers(EntityManagerInterface $entityManager): Response
    {
        $userRepository = $entityManager->getRepository(User::class);
        $users = $userRepository->findAll();
        $count = 0;

        foreach ($users as $user) {
            $statut = $user->getStatut();
            if ($statut) {
                $role = $this->mapStatusToRole($statut);
                if ($user->getRole() !== $role) {
                    $user->setRole($role);
                    $entityManager->persist($user);
                    $count++;
                }
            }
        }

        $entityManager->flush();

        $this->addFlash('success', "{$count} utilisateurs ont été mis à jour avec succès.");

        // Redirigez vers une page accessible
        return $this->redirectToRoute('app_login');
    }

    // private function mapStatusToRole(UserStatus $statut): string
    // {
    //     return match ($statut) {
    //         UserStatus::ADMIN => 'ROLE_ADMIN',
    //         UserStatus::DIRECTEUR => 'ROLE_DIRECTEUR',
    //         UserStatus::CHEF_PROJET => 'ROLE_CHEF_DE_PROJET',
    //         UserStatus::EMPLOYE => 'ROLE_EMPLOYE',
    //         default => 'ROLE_USER',
    //     };
    // }

    // Ajoutez une route plus simple qui n'exige pas de redirection
    #[Route('/fix-roles-simple', name: 'app_fix_roles_simple')]
    public function fixRolesSimple(EntityManagerInterface $entityManager): Response
    {
        $userRepository = $entityManager->getRepository(User::class);
        $users = $userRepository->findAll();
        $updatedUsers = [];

        foreach ($users as $user) {
            $statut = $user->getStatut();
            if ($statut) {
                $role = $this->mapStatusToRole($statut);
                $oldRole = $user->getRole();

                if ($oldRole !== $role) {
                    $user->setRole($role);
                    $entityManager->persist($user);
                    $updatedUsers[] = [
                        'id' => $user->getId(),
                        'email' => $user->getEmail(),
                        'old_role' => $oldRole,
                        'new_role' => $role
                    ];
                }
            }
        }

        $entityManager->flush();

        // Afficher un rapport simple
        $output = "<h1>Utilisateurs mis à jour: " . count($updatedUsers) . "</h1>";

        if (!empty($updatedUsers)) {
            $output .= "<table border='1'>";
            $output .= "<tr><th>ID</th><th>Email</th><th>Ancien rôle</th><th>Nouveau rôle</th></tr>";

            foreach ($updatedUsers as $user) {
                $output .= "<tr>";
                $output .= "<td>" . $user['id'] . "</td>";
                $output .= "<td>" . $user['email'] . "</td>";
                $output .= "<td>" . ($user['old_role'] ?: 'Non défini') . "</td>";
                $output .= "<td>" . $user['new_role'] . "</td>";
                $output .= "</tr>";
            }

            $output .= "</table>";
        }

        $output .= "<p><a href='/login'>Se connecter</a></p>";

        return new Response($output);
    }

    #[Route('/new', name: 'app_user_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher): Response
    {
        $user = new User();
        $form = $this->createForm(UserTypeForm::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('password')->getData();
            if ($plainPassword) {
                $hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);
                $user->setMdp($hashedPassword);
            }
            $user->setDateCreation(new \DateTime());
            $user->setDateMaj(new \DateTime());
            $user->setEstActif(true);

            $entityManager->persist($user);
            $entityManager->flush();

            $this->addFlash('success', 'Utilisateur créé avec succès.');
            return $this->redirectToRoute('app_user_index');
        }

        return $this->render('user/new.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_user_show', methods: ['GET'])]
    public function show(User $user): Response
    {
        return $this->render('user/show.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_user_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, User $user, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher): Response
    {
        $form = $this->createForm(UserTypeForm::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('password')->getData();
            if ($plainPassword) {
                $hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);
                $user->setMdp($hashedPassword);
            }
            $user->setDateMaj(new \DateTime());
            $entityManager->flush();

            $this->addFlash('success', 'Utilisateur modifié avec succès.');
            return $this->redirectToRoute('app_user_index');
        }

        return $this->render('user/edit.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/toggle-status', name: 'app_user_toggle_status', methods: ['POST'])]
    public function toggleStatus(User $user, EntityManagerInterface $entityManager): Response
    {
        $user->setEstActif(!$user->isEstActif());
        $user->setDateMaj(new \DateTime());
        $entityManager->flush();

        $status = $user->isEstActif() ? 'activé' : 'désactivé';
        $this->addFlash('success', "Utilisateur {$status} avec succès.");

        return $this->redirectToRoute('app_user_index');
    }
}
