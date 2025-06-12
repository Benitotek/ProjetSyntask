<?php

namespace App\Form;

use App\Entity\Project;
use App\Entity\TaskList;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TaskListTypeForm extends AbstractType
{
     public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom de la colonne',
                'attr' => [
                    'class' => 'form-control',
                    'maxlength' => 50
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TaskList::class,
        ]);
    }
}
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

