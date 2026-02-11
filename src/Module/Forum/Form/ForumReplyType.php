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
                'disabled' => true  // ✅ DÉSACTIVER LE CHAMP
            ])
            ->add('authorId', IntegerType::class, [
                'label' => 'ID Auteur',
                'required' => true,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'ID de l\'auteur'
                ]
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Réponse active ?',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input'
                ]
            ])
            ->add('isBestAnswer', CheckboxType::class, [
                'label' => 'Marquer comme meilleure réponse',
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