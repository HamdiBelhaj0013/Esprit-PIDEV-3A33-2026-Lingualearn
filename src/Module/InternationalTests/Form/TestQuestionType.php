<?php

namespace App\Module\InternationalTests\Form;

use App\Module\InternationalTests\Entity\TestQuestion;
use App\Module\InternationalTests\Entity\MockTest;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TestQuestionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('mockTest', EntityType::class, [
                'class'        => MockTest::class,
                'choice_label' => 'title',
                'label'        => 'Mock Test',
                'placeholder'  => '-- Sélectionner un test --',
                'attr'         => ['class' => 'form-select', 'id' => 'mocktest_select'],
                'help'         => 'Select the mock test this question belongs to',
            ])
            ->add('questionText', TextareaType::class, [
                'label' => 'Question',
                'attr'  => [
                    'class'       => 'form-control',
                    'rows'        => 4,
                    'placeholder' => 'Entrez la question ici...',
                    'id'          => 'question_text',
                ],
            ])
            // QCM : options (unmapped, handled in controller)
            ->add('options', TextareaType::class, [
                'label'    => 'Propositions de réponse',
                'mapped'   => false,
                'required' => false,
                'attr'     => [
                    'class' => 'form-control',
                    'rows'  => 5,
                    'id'    => 'options_textarea',
                    'placeholder' => "Format JSON:\n[\"Option A\", \"Option B\", \"Option C\", \"Option D\"]\n\nOU une option par ligne:\nOption A\nOption B\nOption C\nOption D",
                ],
                'help' => 'Entrez les options au format JSON array ou une option par ligne. Elles seront automatiquement converties en format A, B, C, D.',
            ])
            // QCM : correct answer (mapped, filled by JS)
            ->add('correctAnswer', TextareaType::class, [
                'label'    => 'Réponse(s) correcte(s)',
                'required' => false,
                'attr'     => [
                    'class'       => 'form-control',
                    'rows'        => 1,
                    'id'          => 'correct_answer_raw',
                    'placeholder' => 'Rempli automatiquement',
                    'readonly'    => true,
                ],
                'help' => 'Sélectionnez la/les bonne(s) réponse(s) parmi les propositions.',
            ])
            // Reading passage
            ->add('readingPassage', TextareaType::class, [
                'label'    => 'Texte de lecture',
                'required' => false,
                'attr'     => [
                    'class'       => 'form-control',
                    'rows'        => 8,
                    'placeholder' => 'Entrez le texte que l\'étudiant devra lire avant de répondre...',
                    'id'          => 'reading_passage',
                ],
                'help' => 'Ce texte sera affiché au-dessus de la question lors du test.',
            ])
            // Listening : audio text
            ->add('audioText', TextareaType::class, [
                'label'    => 'Texte audio',
                'required' => false,
                'attr'     => [
                    'class'       => 'form-control',
                    'rows'        => 6,
                    'placeholder' => 'Ce texte sera lu à voix haute par ElevenLabs...',
                    'id'          => 'audio_text',
                ],
                'help' => 'ElevenLabs convertira ce texte en audio pour l\'étudiant.',
            ])
            // Writing / Speaking : subject
            ->add('writingSubject', TextareaType::class, [
                'label'    => 'Sujet / Consigne',
                'required' => false,
                'attr'     => [
                    'class'       => 'form-control',
                    'rows'        => 5,
                    'placeholder' => 'Entrez le sujet ou la consigne donnée à l\'étudiant...',
                    'id'          => 'writing_subject',
                ],
                'help' => 'Writing : sujet de rédaction. Speaking : sujet de conversation orale.',
            ])
            ->add('points', IntegerType::class, [
                'label' => 'Points',
                'attr'  => [
                    'class' => 'form-control',
                    'id'    => 'points_field',
                    'min'   => 1,
                ],
                'data' => 2,
            ])
            ->add('isActive', CheckboxType::class, [
                'label'    => 'Active',
                'required' => false,
                'attr'     => ['class' => 'form-check-input'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TestQuestion::class,
        ]);
    }
}
