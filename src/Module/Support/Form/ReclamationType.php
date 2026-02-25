<?php
// src/Module/Support/Form/ReclamationType.php

namespace App\Module\Support\Form;

use App\Module\Support\Entity\Reclamation;
use App\Module\Support\Enum\TicketStatus;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Length;

class ReclamationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('subject', ChoiceType::class, [
                'label' => 'Sujet',
                'choices' => [
                    'Problème de paiement' => 'payment',
                    'Bug technique' => 'technical',
                    'Erreur de contenu' => 'content',
                    'Accès premium' => 'premium',
                    'Problème de compte' => 'account',
                    'Autre' => 'other',
                ],
                'required' => true,
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez sélectionner un sujet']),
                ],
                'attr' => [
                    'class' => 'form-select',
                    'data-controller' => 'faq-loader'
                ]
            ])
            ->add('messageBody', TextareaType::class, [
                'label' => 'Description',
                'required' => true,
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez décrire votre problème']),
                    new Length([
                        'min' => 10,
                        'max' => 5000,
                        'minMessage' => 'La description doit contenir au moins {{ limit }} caractères',
                        'maxMessage' => 'La description ne peut pas dépasser {{ limit }} caractères',
                    ]),
                ],
                'attr' => [
                    'rows' => 5,
                    'class' => 'form-control',
                    'placeholder' => 'Décrivez votre problème en détail...'
                ]
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'En attente' => TicketStatus::PENDING->value,
                    'En cours' => TicketStatus::IN_PROGRESS->value,
                    'Résolu' => TicketStatus::RESOLVED->value,
                    'Fermé' => TicketStatus::CLOSED->value,
                ],
                'required' => true,
                'attr' => [
                    'class' => 'form-select',
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Reclamation::class,
        ]);
    }
}