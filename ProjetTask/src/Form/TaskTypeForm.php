<?php

namespace App\Form;

use App\Entity\Project;
use App\Entity\Tag;
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
use Doctrine\DBAL\Types\DateType;
use Doctrine\ORM\EntityRepository;
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
                'choices' => $project ? $project->getMembres() : [],
            ]);
        // Ajout du champ TaskList seulement s'il y a des listes disponibles
        if ($project && $project->getTaskLists()->count() > 0) {
            $builder->add('taskList', EntityType::class, [
                'class' => TaskList::class,
                'choices' => $project->getTaskLists(),
                'choice_label' => 'titre',
                'label' => 'Liste',
                'placeholder' => 'Choisir une liste...',
                'required' => true,
                'attr' => ['class' => 'form-select']
            ]);
        }

        $builder
            ->add('dateButoir', DateType::class, [
                'label' => 'Date limite',
                'required' => false,
                'widget' => 'single_text',
                'html5' => true,
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
            ->add('priority', TextType::class, [
                'label' => 'Priorité',
                'required' => true,
                'attr' => [
                    'class' => 'form-select priority-select'
                ],
                'empty_data' => 'MOYENNE'
            ]);

        // Gestion du statut selon les droits
        if ($options['edit_mode'] && $project && $project->getMembres()->contains($options['user'])) {
            $builder->add('status', TextType::class, [
                'label' => 'Statut',
                'required' => true,
                'attr' => [
                    'class' => 'form-select status-select'
                ]
            ]);
        }
        // Ajout du champ Tags
        if ($project && $project->getTags()->count() > 0) {
            $builder->add('tags', ChoiceType::class, [
                'label' => 'Tags',
                'choices' => $project->getTags()->toArray(),
                'attr' => [
                    'class' => 'form-check-input',
                ],
                'choice_label' => function ($tag) {
                    return $tag->getNom();
                },
                'expanded' => true,
                'multiple' => true,
            ]);
        } elseif ($project) {

            // Si le projet a des tags, on utilise EntityType pour les afficher
            $builder->add('tags', EntityType::class, [
                'class' => Tag::class,
                'choice_label' => 'nom',
                'query_builder' => function (EntityRepository $er) use ($project) {
                    return $er->createQueryBuilder('t')
                        ->where('t.project = :project')
                        ->setParameter('project', $project)
                        ->orderBy('t.nom', 'ASC');
                },
                'label' => 'Tags',
                'required' => false,
                'multiple' => true,
                'expanded' => true,
                'attr' => [
                    'class' => 'form-check-input',
                ],
            ]);
            // Si le projet n'a pas de tags, on utilise ChoiceType pour les afficher
        } elseif ($project) {

            $builder->add('tags', ChoiceType::class, [
                'label' => 'Tags',
                'choices' => [],
                'attr' => [
                    'class' => 'form-check-input',
                ],
                'expanded' => true,
                'multiple' => true,
            ]);
        }

        // Ajout du champ TaskList seulement s'il y a des membres disponibles
        if ($project && $project->getMembres()->count() > 0) {
            // Si le projet n'a pas de membres, on utilise ChoiceType pour les afficher
            $builder->add('assignedUser', ChoiceType::class, [
                'label' => 'Assigné à',
                'choices' => [],
                'attr' => [
                    'class' => 'form-select',
                ],
                'placeholder' => 'Choisir un utilisateur',
            ]);
            // Si le projet n'a pas de listes, on utilise ChoiceType pour les afficher
            $builder->add('taskList', ChoiceType::class, [
                'label' => 'Liste',
                'choices' => [],
                'attr' => [
                    'class' => 'form-select',
                ],
                'placeholder' => 'Choisir une liste...',
            ]);
            // Si le projet n'a pas de tags et de listes, on utilise ChoiceType pour les afficher
            $builder->add('tags', ChoiceType::class, [
                'label' => 'Tags',
                'choices' => [],
                'attr' => [
                    'class' => 'form-check-input',
                ],
                'expanded' => true,
                'multiple' => true,
            ]);
        }
    }

}
