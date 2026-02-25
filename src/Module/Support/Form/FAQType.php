<?php
// src/Module/Support/Form/FAQType.php

namespace App\Module\Support\Form;

use App\Module\Support\Entity\FAQ;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Length;

class FAQType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('question', TextType::class, [
                'label' => 'Question',
                'required' => true,
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez saisir une question']),
                    new Length([
                        'min' => 5,
                        'max' => 255,
                        'minMessage' => 'La question doit contenir au moins {{ limit }} caractères',
                        'maxMessage' => 'La question ne peut pas dépasser {{ limit }} caractères',
                    ]),
                ],
                'attr' => ['class' => 'form-control']
            ])
            ->add('answer', TextareaType::class, [
                'label' => 'Réponse',
                'required' => true,
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez saisir une réponse']),
                    new Length([
                        'min' => 10,
                        'max' => 3000,
                        'minMessage' => 'La réponse doit contenir au moins {{ limit }} caractères',
                        'maxMessage' => 'La réponse ne peut pas dépasser {{ limit }} caractères',
                    ]),
                ],
                'attr' => ['rows' => 4, 'class' => 'form-control']
            ])
            ->add('subject', ChoiceType::class, [
                'label' => 'Sujet associé',
                'choices' => [
                    'Paiement' => 'payment',
                    'Technique' => 'technical',
                    'Contenu' => 'content',
                    'Premium' => 'premium',
                    'Compte' => 'account',
                    'Autre' => 'other',
                ],
                'required' => false,
                'attr' => ['class' => 'form-select']
            ])
            ->add('category', ChoiceType::class, [
                'label' => 'Catégorie',
                'choices' => [
                    'Général' => 'general',
                    'Technique' => 'technical',
                    'Pédagogique' => 'pedagogical',
                    'Facturation' => 'billing',
                ],
                'required' => false,
                'attr' => ['class' => 'form-select']
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => FAQ::class,
        ]);
    }
}