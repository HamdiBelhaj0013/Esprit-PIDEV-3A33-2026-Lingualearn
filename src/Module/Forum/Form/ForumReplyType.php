<?php

namespace App\Module\Forum\Form;

use App\Module\Forum\Entity\ForumReply;
use App\Module\Forum\Entity\ForumPost;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Validator\Constraints as Assert;

class ForumReplyType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('content', TextareaType::class, [
                'label' => 'Votre réponse',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Écrivez votre réponse...',
                    'rows' => 6
                ],
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Le contenu ne peut pas être vide'
                    ]),
                    new Assert\Length([
                        'min' => 10,
                        'max' => 5000,
                        'minMessage' => 'La réponse doit contenir au moins {{ limit }} caractères',
                        'maxMessage' => 'La réponse ne peut pas dépasser {{ limit }} caractères'
                    ])
                ]
            ])
            ->add('post', EntityType::class, [
                'class' => ForumPost::class,
                'choice_label' => 'title',
                'label' => 'Post parent',
                'attr' => [
                    'class' => 'form-select'
                ],
                'required' => true,
                'disabled' => true  // Empêche la modification du post parent
            ])
            ->add('authorId', IntegerType::class, [
                'label' => 'ID Auteur',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'ID de l\'auteur',
                    'readonly' => true  // L'auteur ne devrait pas être modifiable
                ],
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'L\'ID de l\'auteur est requis'
                    ]),
                    new Assert\Positive([
                        'message' => 'L\'ID de l\'auteur doit être un nombre positif'
                    ])
                ]
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Réponse active ?',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input'
                ],
                'help' => 'Décochez pour masquer cette réponse'
            ])
            ->add('isBestAnswer', CheckboxType::class, [
                'label' => 'Marquer comme meilleure réponse',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input'
                ],
                'help' => 'Une seule réponse peut être marquée comme meilleure réponse'
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ForumReply::class,
        ]);
    }
}
