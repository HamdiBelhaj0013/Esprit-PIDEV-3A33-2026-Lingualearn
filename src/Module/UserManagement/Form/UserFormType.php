<?php

namespace App\Module\UserManagement\Form;

use App\Module\UserManagement\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class UserFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'Email Address',
                'attr' => [
                    'placeholder' => 'user@example.com',
                    'class' => 'form-control'
                ],
            ])
            ->add('firstName', TextType::class, [
                'label' => 'First Name',
                'attr' => [
                    'placeholder' => 'John',
                    'class' => 'form-control'
                ],
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Last Name',
                'attr' => [
                    'placeholder' => 'Doe',
                    'class' => 'form-control'
                ],
            ]);

        // Only add password field if creating new user
        if ($options['is_edit'] === false) {
            $builder->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'first_options' => [
                    'label' => 'Password',
                    'attr' => [
                        'placeholder' => 'Enter password',
                        'class' => 'form-control',
                        'autocomplete' => 'new-password'
                    ],
                ],
                'second_options' => [
                    'label' => 'Confirm Password',
                    'attr' => [
                        'placeholder' => 'Confirm password',
                        'class' => 'form-control',
                        'autocomplete' => 'new-password'
                    ],
                ],
                'invalid_message' => 'The password fields must match.',
                'constraints' => [
                    new Assert\NotBlank(message: 'Password is required.'),
                    new Assert\Length([
                        'min' => 6,
                        'minMessage' => 'Password must be at least {{ limit }} characters long.',
                        'max' => 4096,
                    ]),
                    new Assert\Regex([
                        'pattern' => '/^(?=.*[A-Za-z])(?=.*\d).{6,}$/',
                        'message' => 'Password must contain at least one letter and one number.'
                    ]),
                ],
            ]);
        }

        // Add admin-only fields
        if ($options['is_admin']) {
            $builder
                ->add('status', ChoiceType::class, [
                    'label' => 'Status',
                    'choices' => [
                        'Active' => 'active',
                        'Suspended' => 'suspended',
                        'Deleted' => 'deleted',
                    ],
                    // id is explicit so Twig templates can reference it via getElementById
                    'attr' => ['class' => 'form-select', 'id' => 'status-select'],
                ])
                ->add('roles', ChoiceType::class, [
                    'label' => 'Roles',
                    'choices' => [
                        'User' => 'ROLE_USER',
                        'Teacher' => 'ROLE_TEACHER',
                        'Admin' => 'ROLE_ADMIN',
                    ],
                    'multiple' => true,
                    'expanded' => true,
                    'by_reference' => false,
                    'attr' => ['class' => 'form-check'],
                    'constraints' => [
                        new Assert\Count([
                            'min' => 1,
                            'minMessage' => 'You must select at least one role.',
                        ]),
                    ],
                ])
                ->add('subscriptionPlan', ChoiceType::class, [
                    'label' => 'Subscription Plan',
                    'choices' => [
                        'Free' => 'FREE',
                        'Monthly Premium' => 'MONTHLY',
                        'Yearly Premium' => 'YEARLY',
                    ],
                    // id is explicit so Twig JS can find it via getElementById('plan-select')
                    'attr' => ['class' => 'form-select', 'id' => 'plan-select'],
                ])
                ->add('subscriptionExpiry', DateTimeType::class, [
                    'label' => 'Subscription Expires At',
                    'required' => false,
                    'widget' => 'single_text',
                    // html5 = true renders as <input type="datetime-local"> which
                    // correctly maps to PHP DateTime. id is explicit for JS.
                    'html5' => true,
                    'attr' => [
                        'class' => 'form-control',
                        'id' => 'subscription-expiry',
                    ],
                    'help' => 'Leave empty for FREE plan. Required for MONTHLY and YEARLY plans.',
                ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'is_edit' => false,
            'is_admin' => false,
            'attr' => ['novalidate' => 'novalidate'],
        ]);
    }
}
