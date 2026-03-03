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
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ExerciceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('quiz', EntityType::class, [
                'class' => Quiz::class,
                'choice_label' => 'title',
                'label' => 'Quiz associé',
                'placeholder' => 'Sélectionnez un quiz',
                'required' => true,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('type', ChoiceType::class, [
                'choices' => [
                    'Choix multiple' => 'multiple_choice',
                    'Vrai/Faux' => 'true_false',
                    'Remplir les blancs' => 'fill_blanks',
                    'Traduction' => 'translation',
                ],
                'label' => 'Type d\'exercice',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('question', TextareaType::class, [
                'label' => 'Question / Énoncé',
                'attr' => [
                    'placeholder' => 'Entrez la question ici...',
                    'rows' => 3,
                    'class' => 'form-control',
                ],
            ])
            ->add('optionsText', TextareaType::class, [
                'mapped' => false,
                'label' => 'Options (une par ligne)',
                'help' => 'Obligatoire pour les choix multiples. Séparez chaque option par une nouvelle ligne.',
                'required' => false,
                'attr' => [
                    'placeholder' => "Option 1\nOption 2\nOption 3",
                    'rows' => 4,
                    'class' => 'form-control',
                ],
            ])
            ->add('correctAnswer', TextType::class, [
                'label' => 'Réponse correcte',
                'attr' => [
                    'placeholder' => 'La bonne réponse',
                    'class' => 'form-control',
                ],
            ])
            ->add('difficulty', ChoiceType::class, [
                'choices' => [
                    '1 - Très facile' => 1,
                    '2 - Facile' => 2,
                    '3 - Moyen' => 3,
                    '4 - Difficile' => 4,
                    '5 - Très difficile' => 5,
                ],
                'label' => 'Niveau de difficulté',
                'attr' => ['class' => 'form-control'],
                'help' => 'Utilisé pour l\'analyse des compétences et les recommandations.',
            ])
            ->add('skillCodesText', TextareaType::class, [
                'mapped' => false,
                'label' => 'Compétences (skills)',
                'help' => 'Une compétence par ligne ou séparées par des virgules (ex: grammar, vocab, listening). Utilisé pour l\'analyse des compétences.',
                'required' => false,
                'attr' => [
                    'placeholder' => "grammar\nvocab\nlistening",
                    'rows' => 3,
                    'class' => 'form-control',
                ],
            ])
            ->add('enabled', ChoiceType::class, [
                'choices' => [
                    'Non' => false,
                    'Oui' => true,
                ],
                'label' => 'Activé',
                'help' => 'Seuls les exercices activés apparaissent dans le quiz côté apprenant.',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('aiGenerated', ChoiceType::class, [
                'choices' => [
                    'Non' => false,
                    'Oui' => true,
                ],
                'label' => 'Généré par IA ?',
                'attr' => ['class' => 'form-control'],
            ]);

        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event): void {
            $entity = $event->getData();
            if (!$entity instanceof Exercice) {
                return;
            }
            $options = $entity->getOptions();
            $event->getForm()->get('optionsText')->setData(implode("\n", $options));
            $event->getForm()->get('skillCodesText')->setData(implode("\n", $entity->getSkillCodes()));
        });

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event): void {
            $data = $event->getData();
            if (!is_array($data)) {
                return;
            }
            $optionsText = $data['optionsText'] ?? '';
            $options = self::normalizeOptionsText($optionsText);
            $entity = $event->getForm()->getData();
            if ($entity instanceof Exercice) {
                $entity->setOptions($options);
                $skillText = $data['skillCodesText'] ?? '';
                $entity->setSkillCodes(self::normalizeSkillCodesText($skillText));
            }
            // Validation : quand des options sont fournies, la réponse correcte doit être parmi elles
            $correctAnswer = trim((string) ($data['correctAnswer'] ?? ''));
            if ($correctAnswer !== '' && $options !== []) {
                $normalizedAnswer = Exercice::normalizeOption($correctAnswer);
                if (!in_array($normalizedAnswer, $options, true)) {
                    $event->getForm()->get('correctAnswer')->addError(
                        new FormError('La réponse correcte doit correspondre à l\'une des options fournies.')
                    );
                }
            }
        });
    }

    /**
     * Convertit le textarea (une option par ligne) en tableau, normalise \r\n et espaces/caractères de format.
     */
    public static function normalizeOptionsText(string $text): array
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $lines = explode("\n", $text);
        $options = [];
        foreach ($lines as $line) {
            $option = Exercice::normalizeOption($line);
            if ($option !== '') {
                $options[] = $option;
            }
        }
        return array_values(array_unique($options));
    }

    /**
     * Convertit le textarea compétences (lignes ou virgules) en tableau de codes.
     *
     * @return string[]
     */
    public static function normalizeSkillCodesText(string $text): array
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = str_replace(',', "\n", $text);
        $lines = explode("\n", $text);
        $codes = [];
        foreach ($lines as $line) {
            $code = trim($line);
            if ($code !== '' && !in_array($code, $codes, true)) {
                $codes[] = $code;
            }
        }
        return array_values($codes);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Exercice::class,
        ]);
    }
}
