<?php

namespace App\Form;

use App\Entity\Project;
use App\Entity\Tag;
use App\Entity\Task;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ColorType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class TagTypeForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom',
                'attr' => [
                    'placeholder' => 'Nom du tag',
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Le nom du tag est obligatoire'
                    ])
                ]
            ])
            ->add('couleur', ColorType::class, [
                'label' => 'Couleur',
                'attr' => [
                    'class' => 'form-control form-control-color w-100'
                ],
                'constraints' => [
                    new Regex([
                        'pattern' => '/^#[0-9A-Fa-f]{6}$/',
                        'message' => 'La couleur doit être au format hexadécimal (ex: #FF5733)'
                    ])
                ]
            ])
        ;

        // Ajouter le champ project seulement si l'option est activée
        if ($options['with_project']) {
            $builder->add('project', EntityType::class, [
                'class' => Project::class,
                'choice_label' => 'titre',
                'label' => 'Projet associé',
                'required' => false,
                'placeholder' => 'Sélectionnez un projet (optionnel)',
                'attr' => [
                    'class' => 'form-select'
                ]
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Tag::class,
        ]);
    }
}
