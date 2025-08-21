<?php

namespace App\Form;

use App\Entity\Project;
use App\Entity\TaskList;
use App\Enum\TaskListColor;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TaskListType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom de la colonne',
                'attr' => [
                    'placeholder' => 'Nom de la colonne',
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Description de la colonne',
                    'rows' => 3,
                ],
            ])
            ->add('couleur', ChoiceType::class, [
                'label' => 'Couleur',
                'choices' => [
                    'Vert' => TaskListColor::VERT->value,
                    'Jaune' => TaskListColor::JAUNE->value,
                    'Orange' => TaskListColor::ORANGE->value,
                    'Rouge' => TaskListColor::ROUGE->value,
                ],
                'placeholder' => 'Choisir une couleur',
                'required' => false,
                'help' => 'La couleur peut aussi être calculée automatiquement en fonction des échéances des tâches',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TaskList::class,
        ]);
    }
}

// class TaskListTypeForm extends AbstractType
// {
//      public function buildForm(FormBuilderInterface $builder, array $options): void
//     {
//         $builder
//             ->add('nom', TextType::class, [
//                 'label' => 'Nom de la colonne',
//                 'attr' => [
//                     'class' => 'form-control',
//                     'maxlength' => 50
//                 ]
//             ])
//         ;
//     }

//     public function configureOptions(OptionsResolver $resolver): void
//     {
//         $resolver->setDefaults([
//             'data_class' => TaskList::class,
//         ]);
//     }
// }
    // public function buildForm(FormBuilderInterface $builder, array $options): void
    // {
    //     $builder
    //         ->add('nom')
    //         ->add('description')
    //         ->add('position')
    //         ->add('project', EntityType::class, [
    //             'class' => Project::class,
    //             'choice_label' => 'id',
    //         ])
    //     ;
    // }

    // public function configureOptions(OptionsResolver $resolver): void
    // {
    //     $resolver->setDefaults([
    //         'data_class' => TaskList::class,
    //     ]);
    // }
