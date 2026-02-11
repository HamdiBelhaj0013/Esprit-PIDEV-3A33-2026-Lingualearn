<?php

namespace App\Module\ExercisesQuizzes\Form;

use App\Module\ExercisesQuizzes\Entity\UserLessonStatus;
use App\Module\UserManagement\Entity\User;
use App\Module\PedagogicalContent\Entity\Lesson;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UserLessonStatusType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('user', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'email', // ou 'username' selon votre entité User
                'label' => 'Utilisateur',
                'attr' => ['class' => 'form-control']
            ])
            ->add('lesson', EntityType::class, [
                'class' => Lesson::class,
                'choice_label' => 'title', // ou un autre champ identifiant de Lesson
                'label' => 'Leçon',
                'attr' => ['class' => 'form-control']
            ])
            ->add('isCompleted', CheckboxType::class, [
                'label' => 'Complété',
                'required' => false,
                'attr' => ['class' => 'form-check-input']
            ])
            ->add('bestQuizScore', IntegerType::class, [
                'label' => 'Meilleur Score',
                'attr' => [
                    'min' => 0,
                    'max' => 100,
                    'class' => 'form-control'
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => UserLessonStatus::class,
        ]);
    }
}
