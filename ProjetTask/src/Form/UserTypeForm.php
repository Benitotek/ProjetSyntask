<?php

namespace App\Form;

use App\Entity\User;
use App\Enum\UserRole;
use App\Enum\Userstatut;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Role\Role;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class UserTypeForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEdit = $options['is_edit'] ?? false;

        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom',
                'attr' => ['class' => 'form-control'],
                'constraints' => [
                    new NotBlank(['message' => 'Le nom est requis']),
                    new Length(['max' => 30])
                ]
            ])
            ->add('prenom', TextType::class, [
                'label' => 'Prénom',
                'attr' => ['class' => 'form-control'],
                'constraints' => [
                    new NotBlank(['message' => 'Le prénom est requis']),
                    new Length(['max' => 20])
                ]
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'attr' => ['class' => 'form-control'],
                'constraints' => [
                    new NotBlank(['message' => 'L\'email est requis']),
                    new Length(['max' => 50])
                ]
            ]);

        // Ajout conditionnel du champ de choix du role
        if (
            isset($options['can_choose_role'])
            && $options['can_choose_role'] === true
        ) {
            $builder->add('role', ChoiceType::class, [
                'choices' => [
                    'Employé' => UserRole::EMPLOYE,
                    'Admin' => UserRole::ADMIN,
                    'Chef de project' => UserRole::CHEF_PROJET,
                    'Directeur' => UserRole::DIRECTEUR,
                ],
                'attr' => ['class' => 'form-check-input'],
                'multiple' => false,  // Un seul rôle principal
                'expanded' => true,   // Affiche comme des radio buttons
                'label' => 'Rôle'
            ]);
        
        }

        $builder
            ->add('statut', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'Actif' => Userstatut::ACTIF,
                    'Inactif' => Userstatut::INACTIF,
                    'En congé' => Userstatut::EN_CONGE,
                    'Absent' => Userstatut::ABSENT,
                ],
                'attr' => ['class' => 'form-select']
            ])
            // autres champs...
            ->add('mdp', PasswordType::class, [
                'label' => 'Mot de passe',
                'mapped' => false, // si vous ne souhaitez pas lier directement à l'entité
                'required' => true,
            ])
            ->add('confirmer_mdp', PasswordType::class, [
                'label' => 'Confirmer le mot de passe',
                'mapped' => false,
                'required' => true,
            ])
            ->add('estActif', CheckboxType::class, [
                'required' => false,
            ]);

        if (!$isEdit) {
            $builder->add('avatar', TextType::class, [
                'label' => 'Avatar (URL)',
                'required' => false,
                'attr' => ['class' => 'form-control'],
                'constraints' => [
                    new Length(['max' => 255]),
                ]
            ]);
        }
    }

    /**
     * Génère les choix pour les statuts à partir de l'enum
     */
    private function getStatutChoices(): array
    {
        $choices = [];
        foreach (Userstatut::cases() as $statut) {
            $choices[$statut->label()] = $statut;
        }
        return $choices;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'can_choose_role' => false,
            'is_edit' => false,
        ]);
    }
    
}
