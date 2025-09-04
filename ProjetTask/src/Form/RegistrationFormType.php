<?php

namespace App\Form;

use App\Entity\User;
use App\Enum\Userstatut;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\NotCompromisedPassword;
use Symfony\Component\Validator\Constraints\PasswordStrength;

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => '📧 Adresse Email Professionnelle',
                'attr' => [
                    'class' => 'form-control form-control-lg',
                    'placeholder' => 'votre.nom@entreprise.com',
                    'autocomplete' => 'email',
                    'data-validation' => 'email',
                    'autofocus' => true
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'L\'adresse email est obligatoire',
                    ]),
                    new Email([
                        'message' => 'Veuillez saisir une adresse email valide',
                        'mode' => Email::VALIDATION_MODE_STRICT,
                    ]),
                ],
                'help' => '⚡ Un email de confirmation sera envoyé à cette adresse',
                'help_attr' => ['class' => 'form-text text-muted']
            ])
            ->add('nom', TextType::class, [
                'label' => '👤 Nom de Famille',
                'attr' => [
                    'class' => 'form-control form-control-lg',
                    'placeholder' => 'Votre nom de famille',
                    'autocomplete' => 'family-name',
                    'data-validation' => 'name'
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Le nom de famille est obligatoire',
                    ]),
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
                    'placeholder' => 'Votre prénom',
                    'autocomplete' => 'given-name',
                    'data-validation' => 'name'
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Le prénom est obligatoire',
                    ]),
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
            ])
            ->add('roles', ChoiceType::class, [
                'label' => '🎭 Votre Rôle dans l\'Organisation',
                'choices' => [
                    '👤 Employé / Collaborateur' => ['ROLE_EMPLOYE'],
                    '👨‍💼 Chef de Projet / Responsable d\'équipe' => ['ROLE_CHEF_PROJET'],
                    '🎯 Directeur / Manager' => ['ROLE_DIRECTEUR'],
                ],
                'multiple' => false,
                'expanded' => true,
                'data' => ['ROLE_EMPLOYE'], // Par défaut
                'attr' => ['class' => 'role-selection'],
                'help' => '💡 Votre rôle détermine vos permissions dans l\'application. Il peut être modifié par un administrateur après validation.',
                'choice_attr' => function ($choice, $key, $value) {
                    return [
                        'class' => 'form-check-input role-radio',
                        'data-role' => $value[0] ?? ''
                    ];
                },
                'label_attr' => ['class' => 'form-check-label role-label']
            ])
            ->add('statut', EnumType::class, [
                'class' => Userstatut::class,
                'label' => '📊 Statut d\'Emploi',
                'attr' => [
                    'class' => 'form-select form-select-lg',
                    'data-validation' => 'required'
                ],
                'help' => '🏢 Sélectionnez votre statut actuel dans l\'entreprise',
                'placeholder' => 'Choisissez votre statut...'
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'invalid_message' => '❌ Les mots de passe ne correspondent pas',
                'options' => [
                    'attr' => [
                        'class' => 'form-control form-control-lg',
                        'autocomplete' => 'new-password',
                        'data-validation' => 'password'
                    ]
                ],
                'first_options' => [
                    'label' => '🔒 Créer un Mot de Passe Sécurisé',
                    'attr' => [
                        'placeholder' => 'Minimum 12 caractères sécurisés',
                        'data-toggle' => 'password-strength'
                    ],
                    'constraints' => [
                        new NotBlank([
                            'message' => 'Le mot de passe est obligatoire',
                        ]),
                        new Length([
                            'min' => 12,
                            'minMessage' => 'Le mot de passe doit contenir au moins {{ limit }} caractères pour votre sécurité',
                            'max' => 4096,
                        ]),
                        new PasswordStrength([
                            'minScore' => PasswordStrength::STRENGTH_MEDIUM,
                            'message' => '🛡️ Le mot de passe est trop faible. Utilisez un mélange de lettres majuscules, minuscules, chiffres et symboles.',
                        ]),
                        new NotCompromisedPassword([
                            'message' => '⚠️ Ce mot de passe a été compromis dans une fuite de données. Choisissez-en un autre pour votre sécurité.',
                        ]),
                    ],
                    'help' => '🔐 Minimum 12 caractères avec majuscules, minuscules, chiffres et symboles spéciaux'
                ],
                'second_options' => [
                    'label' => '🔒 Confirmer le Mot de Passe',
                    'attr' => [
                        'placeholder' => 'Répétez le mot de passe exactement',
                        'data-validation' => 'password-confirm'
                    ]
                ]
            ])
            ->add('agreeTerms', CheckboxType::class, [
                'mapped' => false,
                'label' => '📋 J\'accepte les Conditions d\'Utilisation',
                'label_html' => true,
                'attr' => [
                    'class' => 'form-check-input terms-checkbox',
                    'data-validation' => 'required'
                ],
                'constraints' => [
                    new IsTrue([
                        'message' => 'Vous devez accepter les conditions d\'utilisation pour créer votre compte.',
                    ]),
                ],
                'help' => '📄 En cochant cette case, vous acceptez nos <a href="#" target="_blank">conditions d\'utilisation</a> et notre <a href="#" target="_blank">politique de confidentialité</a>',
                'help_html' => true,
                'help_attr' => ['class' => 'form-text text-muted']
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'attr' => ['class' => 'registration-form', 'novalidate' => true]
        ]);
    }
    
}
