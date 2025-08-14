<?php

namespace App\Form;

use App\Entity\Project;
use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class ProjectTypeForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titre', TextType::class, [
                'label' => 'Titre du project',
                'attr' => ['class' => 'form-control'],
                'constraints' => [
                    new NotBlank(['message' => 'Le titre est requis']),
                    new Length(['max' => 30])
                ]
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 4
                ]
            ])
            ->add('reference', TextType::class, [
                'label' => 'Référence',
                'required' => false,
                'attr' => ['class' => 'form-control'],
                'constraints' => [
                    new Length(['max' => 30])
                ]
            ])
            ->add('budget', MoneyType::class, [
                'label' => 'Budget',
                'required' => false,
                'currency' => 'EUR',
                'attr' => ['class' => 'form-control']
            ])
            ->add('statut', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'En attente' => Project::STATUT_EN_ATTENTE,
                    'En cours' => Project::STATUT_EN_COURS,
                    'En pause' => Project::STATUT_EN_PAUSE,
                    'Terminé' => Project::STATUT_TERMINER,
                    'Arrêté' => Project::STATUT_ARRETER,
                ],
                'attr' => ['class' => 'form-select']
            ])
                 ->add('Chefproject', EntityType::class, [
            'label' => 'Chef de projet',
            'class' => User::class,
            'choice_label' => 'fullName',
            'choices' => array_filter(
                $options['userRepository'] ? $options['userRepository']->findBy(['estActif' => true]) : [],
                fn(User $u) => in_array('ROLE_CHEF_PROJET', $u->getRoles())
            ),
            'required' => false,
            'placeholder' => 'Sélectionner un chef de projet',
            'attr' => ['class' => 'form-select']
        ])
            ->add('membres', EntityType::class, [
                'label' => 'Membres de l\'équipe',
                'class' => User::class,
                'choice_label' => 'fullName',
                'query_builder' => function (UserRepository $repo) {
                    return $repo->createQueryBuilder('u')
                        ->where('u.estActif = true')
                        ->orderBy('u.nom', 'ASC');
                },
                'multiple' => true,
                'required' => false,
                'attr' => [
                    'class' => 'form-select',
                    'data-live-search' => 'true'
                ]
            ]);
    }
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Project::class,
            'userRepository' => null
        ]);
    }
}
