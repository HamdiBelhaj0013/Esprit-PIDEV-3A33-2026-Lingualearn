<?php

namespace App\Module\InternationalTests\Form;

use App\Module\InternationalTests\Entity\TestQuestion;
use App\Module\InternationalTests\Entity\MockTest;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TestQuestionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('mockTest', EntityType::class, [
                'class' => MockTest::class,
                'choice_label' => 'title',
                'label' => 'Mock Test'
            ])
            ->add('sectionCategory', TextType::class, [
                'label' => 'Section Category'
            ])
            ->add('questionText', TextareaType::class, [
                'label' => 'Question Text'
            ])
            ->add('options', TextareaType::class, [
                'label' => 'Options (JSON format)',
                'help' => 'Enter options as JSON array',
                'mapped' => false
            ])
            ->add('correctAnswer', TextType::class, [
                'label' => 'Correct Answer'
            ])
            ->add('points', IntegerType::class, [
                'label' => 'Points'
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TestQuestion::class,
        ]);
    }
}