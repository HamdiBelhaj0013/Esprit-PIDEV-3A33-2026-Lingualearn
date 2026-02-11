<?php

declare(strict_types=1);

namespace App\Module\PedagogicalContent\Form;

use App\Module\PedagogicalContent\Entity\PlatformLanguage;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class PlatformLanguageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom de la langue',
                'constraints' => [
                    new Assert\NotBlank(message: 'Le nom est obligatoire.'),
                    new Assert\Length([
                        'min' => 2,
                        'max' => 100,
                        'minMessage' => 'Le nom doit contenir au moins {{ limit }} caractères.',
                        'maxMessage' => 'Le nom ne peut pas dépasser {{ limit }} caractères.',
                    ]),
                ],
                'attr' => [
                    'placeholder' => 'Ex: Français',
                    'class' => 'form-control'
                ]
            ])
            ->add('code', TextType::class, [
                'label' => 'Code de la langue',
                'constraints' => [
                    new Assert\NotBlank(message: 'Le code est obligatoire.'),
                    new Assert\Length([
                        'min' => 2,
                        'max' => 10,
                        'minMessage' => 'Le code doit contenir au moins {{ limit }} caractères.',
                        'maxMessage' => 'Le code ne peut pas dépasser {{ limit }} caractères.',
                    ]),
                ],
                'attr' => [
                    'placeholder' => 'Ex: fr',
                    'class' => 'form-control'
                ]
            ])
            ->add('flagUrl', UrlType::class, [
                'label' => 'URL du drapeau',
                'constraints' => [
                    new Assert\NotBlank(message: "L'URL du drapeau est obligatoire."),
                    new Assert\Url(message: "L'URL du drapeau n'est pas valide."),
                ],
                'attr' => [
                    'placeholder' => 'https://example.com/flag.png',
                    'class' => 'form-control'
                ]
            ])
            ->add('isEnabled', CheckboxType::class, [
                'label' => 'Langue activée',
                'required' => false,
                'constraints' => [
                    new Assert\Type(type: 'bool', message: 'Le statut doit être un booléen.'),
                ],
                'attr' => [
                    'class' => 'form-check-input'
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PlatformLanguage::class,
        ]);
    }
}