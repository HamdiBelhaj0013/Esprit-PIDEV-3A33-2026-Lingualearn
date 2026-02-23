<?php

namespace App\Module\InternationalTests\Form;

use App\Module\InternationalTests\Entity\MockTest;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MockTestType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('platformLanguageId', IntegerType::class, [
                'label' => 'Platform Language ID',
                'attr' => ['class' => 'form-control']
            ])
            ->add('title', TextType::class, [
                'label' => 'Title',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Enter test title']
            ])
            ->add('testType', ChoiceType::class, [
                'label' => 'Test Type',
                'choices' => [
                    'TOEFL' => 'TOEFL',
                    'IELTS' => 'IELTS',
                    'SAT' => 'SAT',
                    'GRE' => 'GRE',
                    'GMAT' => 'GMAT',
                    'Other' => 'Other',
                ],
                'attr' => ['class' => 'form-select']
            ])
            ->add('durationMinutes', IntegerType::class, [
                'label' => 'Duration (Minutes)',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Enter duration in minutes']
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Active',
                'required' => false,
                'attr' => ['class' => 'form-check-input']
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => MockTest::class,
        ]);
    }
}