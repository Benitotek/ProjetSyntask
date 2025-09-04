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
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\PasswordStrength;

class UserTypeForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEditMode = $options['data']->getId() !== null;
        $canChooseRole = $options['can_choose_role'] ?? false;

        $builder
            ->add('email', EmailType::class, [
                'label' => '📧 Email Professionnel',
                'attr' => [
                    'class' => 'form-control form-control-lg',
                    'readonly' => $isEditMode,
                    'placeholder' => 'utilisateur@entreprise.com'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'L\'email est obligatoire']),
                    new Email([
                        'message' => 'Veuillez saisir un email valide',
                        'mode' => Email::VALIDATION_MODE_STRICT
                    ]),
                ],
                'help' => $isEditMode ? '⚠️ L\'email ne peut pas être modifié après création' : '💡 L\'email servira d\'identifiant de connexion'
            ])
            ->add('nom', TextType::class, [
                'label' => '👤 Nom de Famille',
                'attr' => [
                    'class' => 'form-control form-control-lg',
                    'placeholder' => 'Nom de famille'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Le nom de famille est obligatoire']),
                    new Length([
                        'min' => 2,
                        'max' => 50,
                        'minMessage' => 'Le nom doit contenir au moins {{ limit }} caractères',
                        'maxMessage' => 'Le nom ne peut pas dépasser {{ limit }} caractères',
                    ]),
                    new Regex([
                        'pattern' => '/^[a-zA-ZÀ-ÿ\s\-\']+$/u',
                        'message' => 'Le nom ne peut contenir que des lettres, espaces, tirets et apostrophes'
                    ])
                ]
            ])
            ->add('prenom', TextType::class, [
                'label' => '👤 Prénom',
                'attr' => [
                    'class' => 'form-control form-control-lg',
                    'placeholder' => 'Prénom'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Le prénom est obligatoire']),
                    new Length([
                        'min' => 2,
                        'max' => 50,
                        'minMessage' => 'Le prénom doit contenir au moins {{ limit }} caractères',
                        'maxMessage' => 'Le prénom ne peut pas dépasser {{ limit }} caractères',
                    ]),
                    new Regex([
                        'pattern' => '/^[a-zA-ZÀ-ÿ\s\-\']+$/u',
                        'message' => 'Le prénom ne peut contenir que des lettres, espaces, tirets et apostrophes'
                    ])
                ]
            ]);

        // Gestion des rôles selon les permissions
        if ($canChooseRole) {
            $builder->add('roles', ChoiceType::class, [
                'label' => '🎭 Rôles et Permissions',
                'choices' => [
                    '👤 Employé / Collaborateur' => ['ROLE_EMPLOYE'],
                    '👨‍💼 Chef de Projet' => ['ROLE_CHEF_PROJET'],
                    '🎯 Directeur / Manager' => ['ROLE_DIRECTEUR'],
                    '👑 Administrateur' => ['ROLE_ADMIN'],
                ],
                'multiple' => false,
                'expanded' => true,
                'data' => ['ROLE_EMPLOYE'], // Par défaut employé
                'attr' => ['class' => 'admin-role-selection'],
                'help' => '⚡ Définit les permissions et accès de l\'utilisateur dans l\'application',
                'choice_attr' => function ($choice, $key, $value) {
                    return [
                        'class' => 'form-check-input admin-role-radio',
                        'data-role' => is_array($value) ? ($value[0] ?? '') : $value
                    ];
                }
            ]);
        }

        $builder
            ->add('statut', ChoiceType::class, [
                'label' => '📊 Statut Organisationnel',
                'choices' => [
                    '✅ Actif' => Userstatut::ACTIF,
                    '❌ Inactif' => Userstatut::INACTIF,
                    '🏖️ En Congé' => Userstatut::EN_CONGE,
                    '📵 Absent' => Userstatut::ABSENT,
                ],
                'attr' => [
                    'class' => 'form-select form-select-lg'
                ],
                'help' => '📋 Indique l\'état actuel de l\'employé dans l\'organisation'
            ])
            ->add('isVerified', CheckboxType::class, [
                'label' => '✅ Compte Email Vérifié',
                'required' => false,
                'attr' => ['class' => 'form-check-input'],
                'help' => '📧 Indique si l\'utilisateur a confirmé son adresse email',
                'data' => $isEditMode ? null : false // Par défaut non vérifié pour nouveau compte
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => '🔓 Compte Actif (Peut se connecter)',
                'required' => false,
                'attr' => ['class' => 'form-check-input'],
                'help' => '🛡️ Désactiver empêche la connexion sans supprimer le compte',
                'data' => $isEditMode ? null : true // Par défaut actif
            ]);

        // Mot de passe - obligatoire uniquement en création
        $builder->add('plainPassword', RepeatedType::class, [
            'type' => PasswordType::class,
            'mapped' => false,
            'required' => !$isEditMode,
            'invalid_message' => '❌ Les mots de passe ne correspondent pas',
            'options' => [
                'attr' => [
                    'class' => 'form-control form-control-lg',
                    'autocomplete' => 'new-password'
                ]
            ],
            'first_options' => [
                'label' => $isEditMode ? '🔄 Nouveau Mot de Passe (optionnel)' : '🔒 Mot de Passe Initial',
                'attr' => [
                    'placeholder' => $isEditMode ? 'Laisser vide pour ne pas changer' : 'Minimum 12 caractères sécurisés'
                ],
                'constraints' => $isEditMode ? [] : [
                    new NotBlank(['message' => 'Le mot de passe est obligatoire pour un nouveau compte']),
                    new Length([
                        'min' => 12,
                        'minMessage' => 'Le mot de passe doit contenir au moins {{ limit }} caractères',
                        'max' => 4096,
                    ]),
                ]
            ],
            'second_options' => [
                'label' => '🔒 Confirmer le Mot de Passe',
                'attr' => [
                    'placeholder' => 'Répétez le mot de passe exactement'
                ]
            ]
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'can_choose_role' => false,
            'attr' => ['class' => 'admin-user-form', 'novalidate' => true]
        ]);

        $resolver->setAllowedTypes('can_choose_role', 'bool');
    }
}




    // class UserTypeForm extends AbstractType
    // {
    //   public function buildForm(FormBuilderInterface $builder, array $options): void
    //         $canChooseRole = $options['can_choose_role'] ?? false;

    //         $builder->add('roles', ChoiceType::class, [
    //             'label' => '👨‍💼 Rôle',
    //             'choices' => array_flip(UserRole::cases()),
    //             'expanded' => true,
    //             'multiple' => false,
    //             'disabled' => $isEditMode,
    //             'help' => '📋 Rôle de l\'employé dans l\'entreprise
    //             <br>
    //             <span class="text-muted">⚠️ Le rôle ne peut pas changer une fois l\'employé créé.</span>',
    //             'help_attr' => ['class' => 'form-text text-muted']
    //         ]);


    //     public function buildForm(FormBuilderInterface $builder, array $options): void
    //     {
    //         $isEditMode = $options['data']->getId() !== null;
    //         $builder
    //             ->add('email', EmailType::class, [
    //                 'label' => '📧 Email Professionnel',
    //                 'attr' => [
    //                     'class' => 'form-control form-control-lg',
    //                     'readonly' => $isEditMode,
    //                     'placeholder' => 'utilisateur@entreprise.com'
    //                 ],
    //                 'constraints' => [
    //                     new NotBlank(['message' => 'L\'email est obligatoire']),
    //                     new Email([
    //                         'message' => 'Veuillez saisir un email valide',
    //                         'mode' => Email::VALIDATION_MODE_STRICT
    //                     ]),
    //                 ],
    //                 'help' => $isEditMode ? '⚠️ L\'email ne peut pas être modifié après création' : '💡 L\'email servira d\'identifiant de connexion'
    //             ])
    //             ->add('nom', TextType::class, [
    //                 'label' => '👤 Nom de Famille',
    //                 'attr' => [
    //                     'class' => 'form-control form-control-lg',
    //                     'placeholder' => 'Nom de famille'
    //                 ],
    //                 'constraints' => [
    //                     new NotBlank(['message' => 'Le nom de famille est obligatoire']),
    //                     new Length([
    //                         'min' => 2,
    //                         'max' => 50,
    //                         'minMessage' => 'Le nom doit contenir au moins {{ limit }} caractères',
    //                         'maxMessage' => 'Le nom ne peut pas dépasser {{ limit }} caractères',
    //                     ]),
    //                     new Regex([
    //                         'pattern' => '/^[a-zA-ZÀ-ÿ\s\-\']+$/u',
    //                         'message' => 'Le nom ne peut contenir que des lettres, espaces, tirets et apostrophes'
    //                     ])
    //                 ]
    //             ])

    //             ->add('prenom', TextType::class, [
    //                 'label' => '👤 Prénom',
    //                 'attr' => [
    //                     'class' => 'form-control form-control-lg',
    //                     'placeholder' => 'Prénom'
    //                 ],
    //                 'constraints' => [
    //                     new NotBlank(['message' => 'Le prénom est obligatoire']),
    //                     new Length([
    //                         'min' => 2,
    //                         'max' => 50,
    //                         'minMessage' => 'Le prénom doit contenir au moins {{ limit }} caractères',
    //                         'maxMessage' => 'Le prénom ne peut pas dépasser {{ limit }} caractères',
    //                     ]),
    //                     new Regex([
    //                         'pattern' => '/^[a-zA-ZÀ-ÿ\s\-\']+$/u',
    //                         'message' => 'Le prénom ne peut contenir que des lettres, espaces, tirets et apostrophes'
    //                     ])
    //                 ]
    //             ]);

    //         // Gestion des rôles selon les permissions
    //         if ($canChooseRole) {
    //             $builder->add('roles', ChoiceType::class, [
    //                 'label' => '🎭 Rôles et Permissions',
    //                 'choices' => [
    //                     '👤 Employé / Collaborateur' => ['ROLE_EMPLOYE'],
    //                     '👨‍💼 Chef de Projet' => ['ROLE_CHEF_PROJET'],
    //                     '🎯 Directeur / Manager' => ['ROLE_DIRECTEUR'],
    //                     '👑 Administrateur' => ['ROLE_ADMIN'],
    //                 ],
    //                 'multiple' => false,
    //                 'expanded' => true,
    //                 'data' => ['ROLE_EMPLOYE'], // Par défaut employé
    //                 'attr' => ['class' => 'admin-role-selection'],
    //                 'help' => '⚡ Définit les permissions et accès de l\'utilisateur dans l\'application',
    //                 'choice_attr' => function($choice, $key, $value) {
    //                     return [
    //                         'class' => 'form-check-input admin-role-radio',
    //                         'data-role' => is_array($value) ? ($value[0] ?? '') : $value
    //                     ];
    //                 }
    //             ]);
    //         }

    //         $builder
    //             ->add('statut', ChoiceType::class, [
    //                 'label' => '📊 Statut Organisationnel',
    //                 'choices' => [
    //                     '✅ Actif' => Userstatut::ACTIF,
    //                     '❌ Inactif' => Userstatut::INACTIF,
    //                     '🏖️ En Congé' => Userstatut::EN_CONGE,
    //                     '📵 Absent' => Userstatut::ABSENT,
    //                 ],
    //                 'attr' => [
    //                     'class' => 'form-select form-select-lg'
    //                 ],
    //                 'help' => '📋 Indique l\'état actuel de l\'employé dans l\'organisation'
    //             ])
    //             ->add('isVerified', CheckboxType::class, [
    //                 'label' => '✅ Compte Email Vérifié',
    //                 'required' => false,
    //                 'attr' => ['class' => 'form-check-input'],
    //                 'help' => '📧 Indique si l\'utilisateur a confirmé son adresse email',
    //                 'data' => $isEditMode ? null : false // Par défaut non vérifié pour nouveau compte
    //             ])
    //             ->add('isActive', CheckboxType::class, [
    //                 'label' => '🔓 Compte Actif (Peut se connecter)',
    //                 'required' => false,
    //                 'attr' => ['class' => 'form-check-input'],
    //                 'help' => '🛡️ Désactiver empêche la connexion sans supprimer le compte',
    //                 'data' => $isEditMode ? null : true // Par défaut actif
    //             ]);

    //         // Mot de passe - obligatoire uniquement en création
    //         $builder->add('plainPassword', RepeatedType::class, [
    //             'type' => PasswordType::class,
    //             'mapped' => false,
    //             'required' => !$isEditMode,
    //             'invalid_message' => '❌ Les mots de passe ne correspondent pas',
    //             'options' => [
    //                 'attr' => [
    //                     'class' => 'form-control form-control-lg',
    //                     'autocomplete' => 'new-password'
    //                 ]
    //             ],
    //             'first_options' => [
    //                 'label' => $isEditMode ? '🔄 Nouveau Mot de Passe (optionnel)' : '🔒 Mot de Passe Initial',
    //                 'attr' => [
    //                     'placeholder' => $isEditMode ? 'Laisser vide pour ne pas changer' : 'Minimum 12 caractères sécurisés'
    //                 ],
    //                 'constraints' => $isEditMode ? [
    //                     new Length([
    //                         'min' => 12,
    //                         'minMessage' => 'Le mot de passe doit contenir au moins {{ limit }} caractères',
    //                         'max' => 4096,
    //                     ]),
    //                     new PasswordStrength([
    //                         'minScore' => PasswordStrength::STRENGTH_MEDIUM,
    //                         'message' => '🛡️ Le mot de passe doit être plus fort',
    //                     ])
    //                 ] : [
    //                     new NotBlank(['message' => 'Le mot de passe est obligatoire pour un nouveau compte']),
    //                     new Length([
    //                         'min' => 12,
    //                         'minMessage' => 'Le mot de passe doit contenir au moins {{ limit }} caractères',
    //                         'max' => 4096,
    //                     ]),
    //                     new PasswordStrength([
    //                         'minScore' => PasswordStrength::STRENGTH_MEDIUM,
    //                         'message' => '🛡️ Utilisez un mélange de majuscules, minuscules, chiffres et symboles',
    //                     ])
    //                 ],
    //                 'help' => $isEditMode ? '💡 Laisser vide pour conserver le mot de passe actuel' : '🔐 Sera envoyé à l\'utilisateur par email sécurisé'
    //             ],
    //             'second_options' => [
    //                 'label' => '🔒 Confirmer le Mot de Passe',
    //                 'attr' => [
    //                     'placeholder' => 'Répétez le mot de passe exactement'
    //                 ]
    //             ]
    //         ]);
    //     }



    //     public function configureOptions(OptionsResolver $resolver): void
    //     {
    //         $resolver->setDefaults([
    //             'data_class' => User::class,
    //             'can_choose_role' => false,
    //             'is_edit' => false,
    //             'attr' => ['class' => 'admin-user-form', 'novalidate' => true]
    //         ]);

    //         $resolver->setAllowedTypes('can_choose_role', 'bool');
    //         $resolver->setAllowedTypes('is_edit', 'bool');
    //     }


//     public function buildForm(FormBuilderInterface $builder, array $options): void
//     {
//         $isEdit = $options['is_edit'] ?? false;

//         $builder
//             ->add('nom', TextType::class, [
//                 'label' => 'Nom',
//                 'attr' => ['class' => 'form-control'],
//                 'constraints' => [
//                     new NotBlank(['message' => 'Le nom est requis']),
//                     new Length(['max' => 30])
//                 ]
//             ])
//             ->add('prenom', TextType::class, [
//                 'label' => 'Prénom',
//                 'attr' => ['class' => 'form-control'],
//                 'constraints' => [
//                     new NotBlank(['message' => 'Le prénom est requis']),
//                     new Length(['max' => 20])
//                 ]
//             ])
//             ->add('email', EmailType::class, [
//                 'label' => 'Email',
//                 'attr' => ['class' => 'form-control'],
//                 'constraints' => [
//                     new NotBlank(['message' => 'L\'email est requis']),
//                     new Length(['max' => 50])
//                 ]
//             ]);

//         // Ajout conditionnel du champ de choix du role
//         if (
//             isset($options['can_choose_role'])
//             && $options['can_choose_role'] === true
//         ) {
//             $builder->add('role', ChoiceType::class, [
//                 'choices' => [
//                     'Employé' => UserRole::EMPLOYE,
//                     'Admin' => UserRole::ADMIN,
//                     'Chef de project' => UserRole::CHEF_PROJET,
//                     'Directeur' => UserRole::DIRECTEUR,
//                 ],
//                 'attr' => ['class' => 'form-check-input'],
//                 'multiple' => false,
//                 'expanded' => true,
//                 'label' => 'Rôle'
//             ]);
//         }

//         $builder
//             ->add('statut', ChoiceType::class, [
//                 'label' => 'Statut',
//                 'choices' => [
//                     'Actif' => Userstatut::ACTIF,
//                     'Inactif' => Userstatut::INACTIF,
//                     'En congé' => Userstatut::EN_CONGE,
//                     'Absent' => Userstatut::ABSENT,
//                 ],
//                 'attr' => ['class' => 'form-select']
//             ])
//             // NOUVEAU: Utilisation de RepeatedType pour gérer automatiquement la validation
//           ->add('plainPassword', RepeatedType::class, [
//     'type' => PasswordType::class,
//     'mapped' => false,
//     'required' => !$isEdit, // obligatoire seulement en création
//     'first_options' => [
//         'label' => 'Mot de passe',
//         'attr' => ['class' => 'form-control']
//     ],
//     'second_options' => [
//         'label' => 'Confirmer le mot de passe',
//         'attr' => ['class' => 'form-control']
//     ],
//     'invalid_message' => 'Les mots de passe ne correspondent pas.',
//     'constraints' => $isEdit
//         ? [new Length([
//             'min' => 6,
//             'minMessage' => 'Le mot de passe doit contenir au moins {{ limit }} caractères',
//             'max' => 4096,
//         ])]
//         : [
//             new NotBlank(['message' => 'Le mot de passe est obligatoire']),
//             new Length([
//                 'min' => 6,
//                 'minMessage' => 'Le mot de passe doit contenir au moins {{ limit }} caractères',
//                 'max' => 4096,
//             ]),
//         ],
//     ]);
//     }

//     public function configureOptions(OptionsResolver $resolver): void
//     {
//         $resolver->setDefaults([
//             'data_class' => User::class,
//             'can_choose_role' => false,
//             'is_edit' => false,
//         ]);
//     }
// }