<?php

namespace App\Module\Forum\Form;

use App\Module\Forum\Entity\ForumPost;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Validator\Constraints as Assert;

class ForumPostType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Post Title',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Enter your post title...',
                    'maxlength' => 255
                ],
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Title cannot be empty'
                    ]),
                    new Assert\Length([
                        'min' => 5,
                        'max' => 255,
                        'minMessage' => 'Title must be at least {{ limit }} characters',
                        'maxMessage' => 'Title cannot exceed {{ limit }} characters'
                    ])
                ]
            ])
            ->add('content', TextareaType::class, [
                'label' => 'Content',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Write your message...',
                    'rows' => 8
                ],
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Content cannot be empty'
                    ]),
                    new Assert\Length([
                        'min' => 20,
                        'max' => 10000,
                        'minMessage' => 'Content must be at least {{ limit }} characters',
                        'maxMessage' => 'Content cannot exceed {{ limit }} characters'
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
            ->add('platformLanguageId', ChoiceType::class, [
                'label' => 'Platform Language',
                'required' => true,
                'choices' => [
                    'French' => 1,
                    'English' => 2,
                    'Arabic' => 3,
                    'Spanish' => 4,
                    'German' => 5,
                ],
                'attr' => [
                    'class' => 'form-select'
                ],
                'placeholder' => '— Select a language —',
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Please select a language'
                    ])
                ]
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Active Post?',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input'
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ForumPost::class,
        ]);
    }
}
