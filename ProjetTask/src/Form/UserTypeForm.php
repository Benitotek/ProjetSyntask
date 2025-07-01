<?php

namespace App\Form;

use App\Entity\User;
use App\Enum\Userstatut;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
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
            ])
              ->add('roles', ChoiceType::class, [
                'label' => 'Rôles',
                'choices' => [
                    'Employé' => 'ROLE_EMPLOYE',
                    'Chef de projet' => 'ROLE_CHEF_PROJET',
                    'Directeur' => 'ROLE_DIRECTEUR',
                    'Administrateur' => 'ROLE_ADMIN',
                ],
                'multiple' => true,
                'expanded' => true,
                'attr' => ['class' => 'form-check-input']
            ])
            
            ->add('statut', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'Actif' => Userstatut::ACTIF,
                    'Inactif' => Userstatut::INACTIF,
                    'En congé' => Userstatut::EN_CONGE,
                    'Absent' => Userstatut::ABSENT,
                ],  'attr' => ['class' => 'form-select']
            ])
            ->add('mdp', PasswordType::class, [
                'label' => 'Mot de passe',
                'mapped' => false,          // IMPORTANT : ne pas mapper à l'entité User
                'required' => false,        // optionnel à la modification
                'attr' => ['class' => 'form-control'],
                'help' => 'Laissez vide pour ne pas changer le mot de passe',
                'constraints' => [
                    new Length([
                        'min' => 6,
                        'max' => 20,
                        'minMessage' => 'Le mot de passe doit contenir au moins {{ limit }} caractères',
                        'maxMessage' => 'Le mot de passe ne peut pas dépasser {{ limit }} caractères'
                    ]),
                    new NotBlank(['message' => 'Le mot de passe est requis'])
                ]
            ]);
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
        ]);
    }
}
