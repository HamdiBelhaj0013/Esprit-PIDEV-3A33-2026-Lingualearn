<?php

namespace App\Module\InternationalTests\Form;

use App\Module\InternationalTests\Entity\TestResult;
use App\Module\InternationalTests\Entity\MockTest;
use App\Module\UserManagement\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TestResultType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('mockTest', EntityType::class, [
                'class' => MockTest::class,
                'choice_label' => 'title',
                'label' => 'Mock Test'
            ])
            ->add('user', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'email',
                'label' => 'User'
            ])
            ->add('overallScore', NumberType::class, [
                'label' => 'Overall Score',
                'scale' => 2
            ])
            ->add('aiPredictedScore', NumberType::class, [
                'label' => 'AI Predicted Score',
                'required' => false,
                'scale' => 2
            ])
            ->add('aiWeaknessReport', TextareaType::class, [
                'label' => 'AI Weakness Report',
                'help' => 'Enter weakness report as JSON',
                'mapped' => false,
                'required' => false
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TestResult::class,
        ]);
    }
}

