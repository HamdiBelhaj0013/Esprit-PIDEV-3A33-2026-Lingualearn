<?php

namespace App\Module\ExercisesQuizzes\Form;

use App\Module\ExercisesQuizzes\Entity\Quiz;
use App\Module\PedagogicalContent\Entity\Lesson;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class QuizType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('lesson', EntityType::class, [
                'class' => Lesson::class,
                'choice_label' => 'title',
                'label' => 'Leçon associée',
                'placeholder' => 'Sélectionnez une leçon',
                'required' => false,
                'attr' => ['class' => 'form-select'],
                'help' => 'Optionnel. Rattache ce quiz à une leçon du parcours.',
            ])
            ->add('title', TextType::class, [
                'label' => 'Titre du quiz',
                'attr' => [
                    'placeholder' => 'Ex: Quiz de vocabulaire débutant',
                    'class' => 'form-control',
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Décrivez brièvement le contenu du quiz...',
                    'rows' => 3,
                    'class' => 'form-control',
                ],
            ])
            ->add('passingScore', IntegerType::class, [
                'label' => 'Score de réussite (%)',
                'attr' => [
                    'min' => 0,
                    'max' => 100,
                    'class' => 'form-control',
                ],
                'help' => 'Score minimum requis pour valider le quiz (0-100).',
            ])
            ->add('questionCount', IntegerType::class, [
                'label' => 'Nombre de questions',
                'attr' => [
                    'min' => 1,
                    'class' => 'form-control',
                ],
                'help' => 'Nombre total de questions dans ce quiz.',
            ])
            ->add('enabled', CheckboxType::class, [
                'label' => 'Quiz activé',
                'required' => false,
                'attr' => ['class' => 'form-check-input'],
                'label_attr' => ['class' => 'form-check-label'],
                'row_attr' => ['class' => 'form-check form-switch mb-0'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Quiz::class,
        ]);
    }
}
