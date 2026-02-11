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

class ForumPostType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre du post',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Entrez le titre de votre post...',
                    'maxlength' => 255
                ]
            ])
            ->add('content', TextareaType::class, [
                'label' => 'Contenu',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Écrivez votre message...',
                    'rows' => 8
                ]
            ])
            ->add('authorId', IntegerType::class, [
                'label' => 'ID Auteur',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'ID de l\'auteur'
                ]
            ])
            ->add('platformLanguageId', ChoiceType::class, [
                'label' => 'Langue de la plateforme',
                'required' => true,
                'choices' => [
                    'Français' => 1,
                    'Anglais' => 2,
                    'Arabe' => 3,
                    'Espagnol' => 4,
                    'Allemand' => 5,
                ],
                'attr' => [
                    'class' => 'form-select'
                ]
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Post actif ?',
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