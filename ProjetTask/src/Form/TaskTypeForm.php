<?php

namespace App\Form;

use App\Entity\Project;
use App\Entity\Task;
use App\Entity\TaskList;
use App\Entity\User;
use App\Enum\TaskPriority;
use App\Enum\TaskStatut;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use App\Repository\TaskListRepository;
use App\Repository\UserRepository;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Length;

class TaskType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Project $project */
        $project = $options['project'];

        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre',
                'attr' => [
                    'placeholder' => 'Titre de la tâche',
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Description de la tâche',
                    'rows' => 5,
                ],
            ])
            ->add('statut', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'En attente' => TaskStatut::EN_ATTENTE->value,
                    'En cours' => TaskStatut::EN_COUR->value,
                    'Terminé' => TaskStatut::TERMINE->value,
                ],
                'placeholder' => 'Choisir un statut',
            ])
            ->add('priorite', ChoiceType::class, [
                'label' => 'Priorité',
                'choices' => [
                    'Urgent' => TaskPriority::URGENT->value,
                    'Normal' => TaskPriority::NORMAL->value,
                    'En attente' => TaskPriority::EN_ATTENTE->value,
                ],
                'placeholder' => 'Choisir une priorité',
            ])
            ->add('dateButoir', DateTimeType::class, [
                'label' => 'Date butoir',
                'required' => false,
                'widget' => 'single_text',
                'html5' => true,
            ])
            ->add('assignedUser', EntityType::class, [
                'class' => User::class,
                'label' => 'Assigné à',
                'required' => false,
                'choice_label' => function (User $user) {
                    return $user->getFullName();
                },
                'placeholder' => 'Choisir un utilisateur',
                'choices' => $this->getProjectMembers($project),
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Task::class,
        ]);

        $resolver->setRequired('project');
        $resolver->setAllowedTypes('project', Project::class);
    }

    private function getProjectMembers(Project $project): array
    {
        $members = $project->getMembres()->toArray();
        $chefproject = $project->getChef_project();

        if ($chefproject && !in_array($chefproject, $members, true)) {
            $members[] = $chefproject;
        }

        return $members;
    }
}

/**
 * TaskTypeForm is a form type for creating and editing Task entities.
 * It includes fields for task attributes and relationships with other entities.
 */

// class TaskType extends AbstractType
// {
//     public function buildForm(FormBuilderInterface $builder, array $options): void
//     {
//         $builder
//             ->add('titre', TextType::class, [
//                 'label' => 'Titre de la tâche',
//                 'attr' => ['class' => 'form-control'],
//                 'constraints' => [
//                     new NotBlank(['message' => 'Le titre est requis']),
//                     new Length(['max' => 30])
//                 ]
//             ])
//             ->add('description', TextareaType::class, [
//                 'label' => 'Description',
//                 'required' => false,
//                 'attr' => [
//                     'class' => 'form-control',
//                     'rows' => 3
//                 ]
//             ])
//             ->add('priorite', ChoiceType::class, [
//                 'label' => 'Priorité',
//                 'choices' => [
//                     'Urgent' => Task::PRIORITE_URGENT,
//                     'Normal' => Task::PRIORITE_NORMAL,
//                     'En attente' => Task::PRIORITE_EN_ATTENTE,
//                 ],
//                 'attr' => ['class' => 'form-select']
//             ])
//             ->add('statut', ChoiceType::class, [
//                 'label' => 'Statut',
//                 'choices' => [
//                     'En attente' => Task::STATUT_EN_ATTENTE,
//                     'En cours' => Task::STATUT_EN_COURS,
//                     'Terminé' => Task::STATUT_TERMINE,
//                 ],
//                 'attr' => ['class' => 'form-select']
//             ])
//             ->add('dateDeFin', DateTimeType::class, [
//                 'label' => 'Date d\'échéance',
//                 'required' => false,
//                 'widget' => 'single_text',
//                 'attr' => ['class' => 'form-control']
//             ])
//             ->add('taskList', EntityType::class, [
//                 'label' => 'Colonne',
//                 'class' => TaskList::class,
//                 'choice_label' => 'name',
//                 'query_builder' => function (TaskListRepository $repo) use ($options) {
//                     $qb = $repo->createQueryBuilder('tl')
//                         ->orderBy('tl.positionColumn', 'ASC');

//                     if (isset($options['project']) && $options['project']) {
//                         $qb->where('tl.project = :project')
//                             ->setParameter('project', $options['project']);
//                     }

//                     return $qb;
//                 },
//                 'required' => false,
//                 'placeholder' => 'Sélectionner une colonne',
//                 'attr' => ['class' => 'form-select']
//             ])
//             ->add('assignedUsers', EntityType::class, [
//                 'label' => 'Assigné à',
//                 'class' => User::class,
//                 'choice_label' => 'fullName',
//                 'query_builder' => function (UserRepository $repo) use ($options) {
//                     $qb = $repo->createQueryBuilder('u')
//                         ->where('u.estActif = true')
//                         ->orderBy('u.nom', 'ASC');

//                     if (isset($options['project_members']) && $options['project_members']) {
//                         $qb->andWhere('u IN (:members)')
//                             ->setParameter('members', $options['project_members']);
//                     }

//                     return $qb;
//                 },
//                 'multiple' => true,
//                 'required' => false,
//                 'attr' => [
//                     'class' => 'form-select',
//                     'data-live-search' => 'true'
//                 ]
//             ]);
//     }

//     public function configureOptions(OptionsResolver $resolver): void
//     {
//         $resolver->setDefaults([
//             'data_class' => Task::class,
//             'project' => null,
//             'project_members' => null,
//         ]);
//     }
// }
