<?php
// src/Module/Support/Form\SupportResponseType.php

namespace App\Module\Support\Form;

use App\Module\Support\Entity\SupportResponse;
use App\Module\Support\Enum\TicketStatus;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Length;

class SupportResponseType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('message', TextareaType::class, [
                'label' => 'Réponse',
                'required' => true,
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez saisir une réponse']),
                    new Length([
                        'min' => 5,
                        'max' => 3000,
                        'minMessage' => 'La réponse doit contenir au moins {{ limit }} caractères',
                        'maxMessage' => 'La réponse ne peut pas dépasser {{ limit }} caractères',
                    ]),
                ],
                'attr' => ['rows' => 5, 'class' => 'form-control']
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Mettre à jour le statut',
                'choices' => TicketStatus::cases(),
                'choice_label' => fn($choice) => $choice->value,
                'mapped' => false,
                'required' => false,
                'attr' => ['class' => 'form-select']
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SupportResponse::class,
        ]);
    }
}