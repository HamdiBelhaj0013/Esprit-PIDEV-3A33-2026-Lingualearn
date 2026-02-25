<?php

declare(strict_types=1);

namespace App\Module\PedagogicalContent\Form;

use App\Module\PedagogicalContent\Entity\Course;
use App\Module\PedagogicalContent\Entity\Lesson;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class LessonType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre de la leçon',
            ])
            ->add('content', TextareaType::class, [
                'label' => 'Contenu',
            ])
            ->add('vocabularyData', TextareaType::class, [
                'label' => 'Données de vocabulaire (JSON)',
                'required' => false,
                'mapped' => false,
            ])
            ->add('grammarData', TextareaType::class, [
                'label' => 'Données de grammaire (JSON)',
                'required' => false,
                'mapped' => false,
            ])
            ->add('xpReward', IntegerType::class, [
                'label' => 'Récompense en XP',
            ])
            ->add('course', EntityType::class, [
                'label' => 'Cours associé',
                'class' => Course::class,
                'choice_label' => function(Course $course) {
                    return $course->getTitle() . ' (' . $course->getLevel() . ')';
                },
                'placeholder' => '-- Sélectionner un cours --',
            ])

            /**
             * ============================
             * VICH - CHAMPS UPLOAD
             * ============================
             */
            ->add('videoFile', FileType::class, [
                'label' => 'Vidéo (mp4/webm)',
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '200M',
                        'mimeTypes' => ['video/mp4', 'video/webm'],
                        'mimeTypesMessage' => 'Vidéo invalide (mp4/webm).',
                    ]),
                ],
            ])
            ->add('thumbFile', FileType::class, [
                'label' => 'Thumbnail (image)',
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '3M',
                        'mimeTypes' => ['image/jpeg', 'image/png', 'image/webp'],
                        'mimeTypesMessage' => 'Thumbnail invalide (jpg/png/webp).',
                    ]),
                ],
            ])
            ->add('resourceFile', FileType::class, [
                'label' => 'Ressource de leçon (PDF/PPTX)',
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '20M',
                        'mimeTypes' => [
                            'application/pdf',
                            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                        ],
                        'mimeTypesMessage' => 'Ressource invalide (PDF ou PPTX).',
                    ]),
                ],
            ]);

        // PRE_SET_DATA
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            $lesson = $event->getData();
            $form = $event->getForm();

            if (!$lesson) {
                return;
            }

            $vocabularyData = $lesson->getVocabularyData();
            $form->get('vocabularyData')->setData(
                !empty($vocabularyData) ? json_encode($vocabularyData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : ''
            );

            $grammarData = $lesson->getGrammarData();
            $form->get('grammarData')->setData(
                !empty($grammarData) ? json_encode($grammarData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : ''
            );
        });

        // POST_SUBMIT
        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {
            $lesson = $event->getData();
            $form = $event->getForm();

            if (!$lesson) {
                return;
            }

            $vocabularyJson = $form->get('vocabularyData')->getData();
            $lesson->setVocabularyData(
                json_decode($vocabularyJson, true) ?? []
            );

            $grammarJson = $form->get('grammarData')->getData();
            $lesson->setGrammarData(
                json_decode($grammarJson, true) ?? []
            );
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Lesson::class,
        ]);
    }
}