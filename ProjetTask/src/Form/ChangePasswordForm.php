<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotCompromisedPassword;
use Symfony\Component\Validator\Constraints\PasswordStrength;

class ChangePasswordForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'invalid_message' => '❌ Les mots de passe ne correspondent pas.',
                'options' => [
                    'attr' => [
                        'autocomplete' => 'new-password',
                        'class' => 'form-control form-control-lg',
                        'data-validation' => 'password'
                    ],
                ],
                'first_options' => [
                    'constraints' => [
                        new NotBlank([
                            'message' => 'Veuillez entrer un nouveau mot de passe',
                        ]),
                        new Length([
                            'min' => 12,
                            'minMessage' => 'Votre mot de passe doit contenir au moins {{ limit }} caractères pour votre sécurité',
                            'max' => 4096,
                        ]),
                        new PasswordStrength([
                            'minScore' => PasswordStrength::STRENGTH_MEDIUM,
                            'message' => '🛡️ Le mot de passe doit être plus fort : utilisez un mélange de majuscules, minuscules, chiffres et symboles.',
                        ]),
                        new NotCompromisedPassword([
                            'message' => '⚠️ Ce mot de passe a été compromis dans une fuite de données. Choisissez-en un autre.',
                        ]),
                    ],
                    'label' => '🔒 Nouveau Mot de Passe Sécurisé',
                    'attr' => [
                        'placeholder' => 'Minimum 12 caractères avec majuscules, minuscules, chiffres et symboles',
                        'data-toggle' => 'password-strength',
                        'autofocus' => true
                    ],
                    'help' => '🔐 Choisissez un mot de passe fort que vous n\'utilisez nulle part ailleurs',
                    'help_attr' => ['class' => 'form-text text-info']
                ],
                'second_options' => [
                    'label' => '🔒 Confirmer le Nouveau Mot de Passe',
                    'attr' => [
                        'placeholder' => 'Répétez exactement le nouveau mot de passe',
                        'data-validation' => 'password-confirm'
                    ],
                    'help' => '🔄 Saisissez à nouveau le mot de passe pour confirmation',
                    'help_attr' => ['class' => 'form-text text-muted']
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'attr' => ['class' => 'change-password-form', 'novalidate' => true]
        ]);
    }
}
//     public function buildForm(FormBuilderInterface $builder, array $options): void
//     {
//         $builder
//             ->add('plainPassword', RepeatedType::class, [
//                 'type' => PasswordType::class,
//                 'options' => [
//                     'attr' => [
//                         'autocomplete' => 'new-password',
//                     ],
//                 ],
//                 'first_options' => [
//                     'constraints' => [
//                         new NotBlank([
//                             'message' => 'Please enter a password',
//                         ]),
//                         new Length([
//                             'min' => 12,
//                             'minMessage' => 'Your password should be at least {{ limit }} characters',
//                             // max length allowed by Symfony for security reasons
//                             'max' => 4096,
//                         ]),
//                         new PasswordStrength(),
//                         new NotCompromisedPassword(),
//                     ],
//                     'label' => 'New password',
//                 ],
//                 'second_options' => [
//                     'label' => 'Repeat Password',
//                 ],
//                 'invalid_message' => 'The password fields must match.',
//                 // Instead of being set onto the object directly,
//                 // this is read and encoded in the controller
//                 'mapped' => false,
//             ])
//         ;
//     }

//     public function configureOptions(OptionsResolver $resolver): void
//     {
//         $resolver->setDefaults([]);
//     }
// }
