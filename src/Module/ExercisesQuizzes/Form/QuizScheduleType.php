<?php

declare(strict_types=1);

namespace App\Module\ExercisesQuizzes\Form;

use App\Module\ExercisesQuizzes\Entity\QuizSchedule;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThan;

class QuizScheduleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('scheduledAt', DateTimeType::class, [
                'label' => 'Date et heure',
                'widget' => 'single_text',
                'html5' => true,
                'attr' => ['class' => 'form-control', 'min' => (new \DateTime())->format('Y-m-d\TH:i')],
                'constraints' => [new GreaterThan('now', message: 'La date doit être dans le futur.')],
            ])
            ->add('note', TextareaType::class, [
                'label' => 'Note (optionnel)',
                'required' => false,
                'attr' => ['class' => 'form-control', 'rows' => 3, 'placeholder' => 'Rappel, objectif...'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => QuizSchedule::class,
        ]);
    }
}
