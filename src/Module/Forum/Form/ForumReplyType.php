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
                'label' => 'Your Reply',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Write your reply...',
                    'rows' => 6
                ],
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Content cannot be empty'
                    ]),
                    new Assert\Length([
                        'min' => 10,
                        'max' => 5000,
                        'minMessage' => 'Reply must be at least {{ limit }} characters',
                        'maxMessage' => 'Reply cannot exceed {{ limit }} characters'
                    ])
                ]
            ])
            ->add('post', EntityType::class, [
                'class' => ForumPost::class,
                'choice_label' => 'title',
                'label' => 'Parent Post',
                'attr' => [
                    'class' => 'form-select'
                ],
                'required' => true,
                'placeholder' => '— Select a post to reply to —',
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Please select a post to reply to'
                    ])
                ]
            ])
            ->add('authorId', IntegerType::class, [
                // Controller sets this automatically
                'required' => false,
                'mapped' => true,
                'attr' => [
                    'class' => 'd-none'
                ]
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Active Reply?',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input'
                ]
            ])
            ->add('isBestAnswer', CheckboxType::class, [
                'label' => 'Mark as Best Answer',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input'
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ForumReply::class,
        ]);
    }
}
