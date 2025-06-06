<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UserTypeForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEdit = $options['is_edit'] ?? false;
        
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom',
                'attr' => ['placeholder' => 'Entrez votre nom']
            ])
            ->add('prenom', TextType::class, [
                'label' => 'Prénom',
                'attr' => ['placeholder' => 'Entrez votre prénom']
            ])
            ->add('statut')
            ->add('role', ChoiceType::class,[
                // 'attr' => 'form-select',
                // 'class' => User::class,
                // 'choices' => $options['roles']
                'choices' => [
                    'ROLE_USER' => 'ROLE_USER',
                    'ROLE_ADMIN' => 'ROLE_ADMIN',
                    'ROLE_DIRECTEUR' => 'ROLE_DIRECTEUR',
                    'ROLE_CHEF_PROJET' => 'ROLE_CHEF_PROJET',
                    'ROLE_EMPLOYE' => 'ROLE_EMPLOYE'
                ],
                'expanded' => false,
                'multiple' => true,
                'label' => 'Rôle',
                
            ])
            ->add('email', TextType::class, ['label' => "Email"])
            ->add('mdp', PasswordType::class, [
                // 'hash_property_path' => 'password',
                'mapped' => true, 
                'label' => "Mot de passe"])
            ->add('estActif', CheckboxType::class, ['label' => "Actif"])
            ->add('dateCreation')
            ->add('dateMaj')
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
