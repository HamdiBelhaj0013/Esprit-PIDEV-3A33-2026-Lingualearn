<?php

declare(strict_types=1);

namespace App\Module\PedagogicalContent\Form;

use App\Module\PedagogicalContent\Entity\Course;
use App\Module\PedagogicalContent\Entity\PlatformLanguage;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CourseType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre du cours',
            ])
            ->add('platformLanguage', EntityType::class, [
                'label' => 'Langue',
                'class' => PlatformLanguage::class,
                'choice_label' => 'name',
                'placeholder' => '-- Sélectionner une langue --',
            ])
            ->add('level', ChoiceType::class, [
                'label' => 'Niveau',
                'choices' => [
                    'Débutant' => 'beginner',
                    'Intermédiaire' => 'intermediate',
                    'Avancé' => 'advanced',
                ],
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'Brouillon' => 'draft',
                    'Publié' => 'published',
                    'Archivé' => 'archived',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Course::class,
        ]);
    }
}
