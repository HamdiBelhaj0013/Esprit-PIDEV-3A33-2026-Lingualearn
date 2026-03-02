<?php

namespace App\Module\InternationalTests\Form;

use App\Module\InternationalTests\Entity\MockTest;
use App\Module\PedagogicalContent\Entity\PlatformLanguage;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MockTestType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Title',
                'attr'  => ['class' => 'form-control', 'placeholder' => 'Enter test title']
            ])
            ->add('testType', ChoiceType::class, [
                'label'   => 'Test Type',
                'choices' => [
                    'TOEFL' => 'TOEFL',
                    'IELTS' => 'IELTS',
                    'SAT'   => 'SAT',
                    'GRE'   => 'GRE',
                    'GMAT'  => 'GMAT',
                    'Other' => 'Other',
                ],
                'attr' => ['class' => 'form-select']
            ])
            // ─── NOUVEAU : champ Test Category ───
            ->add('testCategory', ChoiceType::class, [
                'label'   => 'Test Category',
                'choices' => [
                    'QCM'       => MockTest::TYPE_QCM,
                    'Writing'   => MockTest::TYPE_WRITING,
                    'Speaking'  => MockTest::TYPE_SPEAKING,
                    'Listening' => MockTest::TYPE_LISTENING,
                ],
                'attr' => ['class' => 'form-select'],
                'help' => 'Select the overall category of this test'
            ])
            // ─── NOUVEAU : champ Level ───
            ->add('level', ChoiceType::class, [
                'label'   => 'Level',
                'choices' => [
                    'Beginner'     => MockTest::LEVEL_BEGINNER,
                    'Intermediate' => MockTest::LEVEL_INTERMEDIATE,
                    'Advanced'     => MockTest::LEVEL_ADVANCED,
                ],
                'attr' => ['class' => 'form-select']
            ])
            ->add('durationMinutes', IntegerType::class, [
                'label' => 'Duration (Minutes)',
                'attr'  => ['class' => 'form-control', 'placeholder' => 'Enter duration in minutes']
            ])
            ->add('platformLanguage', EntityType::class, [
                'class'         => PlatformLanguage::class,
                'choice_label'  => 'name',
                'label'         => 'Platform Language',
                'placeholder'   => '-- Select a language --',
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('l')
                        ->where('l.isEnabled = :enabled')
                        ->setParameter('enabled', true)
                        ->orderBy('l.name', 'ASC');
                },
                'attr' => ['class' => 'form-select']
            ])
            ->add('isActive', CheckboxType::class, [
                'label'    => 'Active',
                'required' => false,
                'attr'     => ['class' => 'form-check-input']
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => MockTest::class,
        ]);
    }
}
