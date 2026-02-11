<?php

namespace App\Module\ExercisesQuizzes\Form;

use App\Module\ExercisesQuizzes\Entity\Exercice;
use App\Module\ExercisesQuizzes\Entity\Quiz;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Length;

class ExerciceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('quiz', EntityType::class, [
                'class' => Quiz::class,
                'choice_label' => 'title',
                'label' => 'Quiz associé',
                'placeholder' => 'Sélectionnez un quiz (optionnel)',
                'required' => false,
                'attr' => ['class' => 'form-control']
            ])
            ->add('type', ChoiceType::class, [
                'choices' => [
                    'Choix multiple' => 'multiple_choice',
                    'Vrai/Faux' => 'true_false',
                    'Remplir les blancs' => 'fill_blanks',
                    'Traduction' => 'translation',
                ],
                'label' => 'Type d\'exercice',
                'attr' => ['class' => 'form-control']
            ])
            ->add('question', TextareaType::class, [
                'label' => 'Question / Énoncé',
                'attr' => [
                    'placeholder' => 'Entrez la question ici...',
                    'rows' => 3,
                    'class' => 'form-control'
                ]
            ])
            ->add('options', TextareaType::class, [
                'mapped' => false,
                'label' => 'Options (une par ligne)',
                'help' => 'Obligatoire pour les choix multiples. Séparez chaque option par une nouvelle ligne.',
                'required' => false,
                'attr' => [
                    'placeholder' => "Option 1\nOption 2\nOption 3",
                    'rows' => 4,
                    'class' => 'form-control'
                ],
                'constraints' => [
                    // On pourrait ajouter un Callback pour valider que c'est rempli si type = multiple_choice
                ]
            ])
            ->add('correctAnswer', TextType::class, [
                'label' => 'Réponse correcte',
                'attr' => [
                    'placeholder' => 'La bonne réponse',
                    'class' => 'form-control'
                ]
            ])
            ->add('aiGenerated', ChoiceType::class, [
                'choices' => [
                    'Non' => false,
                    'Oui' => true,
                ],
                'label' => 'Généré par IA ?',
                'attr' => ['class' => 'form-control']
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Exercice::class,
        ]);
    }
}
