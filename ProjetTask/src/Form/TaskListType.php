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
                'choices' => TaskListColor::cases(), // objets Enum
                'choice_value' => fn(?TaskListColor $case) => $case?->value, // string
                'choice_label' => fn(TaskListColor $case) => $case->label(), // label
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
