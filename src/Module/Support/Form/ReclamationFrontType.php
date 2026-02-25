<?php
// src/Module/Support/Form/ReclamationFrontType.php

namespace App\Module\Support\Form;

use App\Module\Support\Entity\Reclamation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Length;

class ReclamationFrontType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('subject', ChoiceType::class, [
                'label' => 'Sujet',
                'choices' => [
                    'Problème de paiement' => 'payment',
                    'Bug technique'        => 'technical',
                    'Erreur de contenu'    => 'content',
                    'Accès premium'        => 'premium',
                    'Problème de compte'   => 'account',
                    'Autre'                => 'other',
                ],
                'required'    => true,
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez sélectionner un sujet']),
                ],
                'attr' => ['class' => 'form-select'],
            ])
            ->add('messageBody', TextareaType::class, [
                'label'       => 'Description',
                'required'    => true,
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez décrire votre problème']),
                    new Length([
                        'min'        => 10,
                        'max'        => 5000,
                        'minMessage' => 'La description doit contenir au moins {{ limit }} caractères',
                        'maxMessage' => 'La description ne peut pas dépasser {{ limit }} caractères',
                    ]),
                ],
                'attr' => [
                    'rows'        => 6,
                    'class'       => 'form-control',
                    'placeholder' => 'Décrivez votre problème en détail...',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Reclamation::class,
        ]);
    }
}