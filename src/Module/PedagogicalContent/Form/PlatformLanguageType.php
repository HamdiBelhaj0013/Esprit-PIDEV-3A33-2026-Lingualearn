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
                'label' => 'Language Name',
                'constraints' => [
                    new Assert\NotBlank(message: 'Name is required.'),
                    new Assert\Length(['min' => 2, 'max' => 100]),
                ],
                'attr' => ['placeholder' => 'e.g. French', 'class' => 'form-control'],
            ])
            ->add('code', TextType::class, [
                'label' => 'Language Code',
                'constraints' => [
                    new Assert\NotBlank(message: 'Code is required.'),
                    new Assert\Length(['min' => 2, 'max' => 10]),
                ],
                'attr' => ['placeholder' => 'e.g. fr', 'class' => 'form-control'],
            ])
            ->add('flagUrl', UrlType::class, [
                'label' => 'Flag Image URL',
                'constraints' => [
                    new Assert\NotBlank(message: 'Flag URL is required.'),
                    new Assert\Url(message: 'Please enter a valid URL.'),
                ],
                'attr' => ['placeholder' => 'https://example.com/flag.png', 'class' => 'form-control'],
            ])
            ->add('isEnabled', CheckboxType::class, [
                'label'    => 'Enable this language (visible to users)',
                'required' => false,
                'attr'     => ['class' => 'form-check-input'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PlatformLanguage::class,
        ]);
    }
}
