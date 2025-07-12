<?php

namespace App\Form;

use App\Entity\Comment;
use App\Entity\Task;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class CommentTypeForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('contenu', TextareaType::class, [
                'label' => 'Commentaire',
                'attr' => [
                    'placeholder' => 'Écrivez votre commentaire...',
                    'rows' => 3,
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Le contenu du commentaire ne peut pas être vide'
                    ])
                ]
            ])
            ->add('dateCreation', null, [
                'label' => 'Date de création',
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('dateMaj', null, [
                'label' => 'Date de mise à jour',
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('auteur', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'id',
                'label' => 'Auteur',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('task', EntityType::class, [
                'class' => Task::class,
                'choice_label' => 'id',
                'label' => 'Tâche associée',
                'attr' => ['class' => 'form-control'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Comment::class,
        ]);
    }
}

    //     $builder
    //         ->add('contenu')
    //         ->add('dateCreation')
    //         ->add('dateMaj')
    //         ->add('auteur', EntityType::class, [
    //             'class' => User::class,
    //             'choice_label' => 'id',
    //         ])
    //         ->add('task', EntityType::class, [
    //             'class' => Task::class,
    //             'choice_label' => 'id',
    //         ])
    //     ;
    // }

//     public function configureOptions(OptionsResolver $resolver): void
//     {
//         $resolver->setDefaults([
//             'data_class' => Comment::class,
//         ]);
//     }
// }
