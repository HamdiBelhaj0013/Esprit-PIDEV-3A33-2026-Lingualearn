<?php

namespace App\Module\UserManagement\Form;

use App\Module\UserManagement\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
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
        $isEdit = $options['is_edit'];

        // ── Personal info ─────────────────────────────────────
        $builder
            ->add('email', EmailType::class, [
                'label' => 'Email Address',
                'attr'  => ['placeholder' => 'user@example.com', 'class' => 'form-control'],
            ])
            ->add('firstName', TextType::class, [
                'label' => 'First Name',
                'attr'  => ['placeholder' => 'John', 'class' => 'form-control'],
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Last Name',
                'attr'  => ['placeholder' => 'Doe', 'class' => 'form-control'],
            ]);

        // ── Password ──────────────────────────────────────────
        // Always present so the template card always renders.
        // On CREATE  → required, must match, NotBlank enforced.
        // On EDIT    → optional, leave blank to keep current password.
        $constraints = [
            new Assert\Length([
                'min'        => 6,
                'minMessage' => 'Password must be at least {{ limit }} characters.',
                'max'        => 4096,
            ]),
            // Note: no regex constraint — admins can set any password >= 6 chars.
            // Users registering themselves go through a stricter registration form.
        ];

        if (!$isEdit) {
            array_unshift($constraints, new Assert\NotBlank(message: 'Password is required.'));
        }

        $builder->add('plainPassword', RepeatedType::class, [
            'type'            => PasswordType::class,
            'mapped'          => false,
            'required'        => !$isEdit,
            'first_options'   => [
                'label' => $isEdit ? 'New Password' : 'Password',
                'attr'  => [
                    'placeholder'  => $isEdit ? 'Leave blank to keep current' : 'Enter password',
                    'class'        => 'form-control',
                    'autocomplete' => 'new-password',
                ],
            ],
            'second_options'  => [
                'label' => 'Confirm Password',
                'attr'  => [
                    'placeholder'  => $isEdit ? 'Leave blank to keep current' : 'Confirm password',
                    'class'        => 'form-control',
                    'autocomplete' => 'new-password',
                ],
            ],
            'invalid_message' => 'The password fields must match.',
            'constraints'     => $constraints,
        ]);

        // ── Admin-only fields ─────────────────────────────────
        // subscriptionPlan / subscriptionExpiry intentionally excluded —
        // premium must go through StripeService to stay in sync with Stripe.
        if ($options['is_admin']) {
            $builder
                ->add('status', ChoiceType::class, [
                    'label'   => 'Status',
                    'choices' => [
                        'Active'    => 'active',
                        'Suspended' => 'suspended',
                        'Deleted'   => 'deleted',
                    ],
                    'attr' => ['class' => 'form-select', 'id' => 'status-select'],
                ])
                ->add('roles', ChoiceType::class, [
                    'label'        => 'Roles',
                    'choices'      => [
                        'User'    => 'ROLE_USER',
                        'Teacher' => 'ROLE_TEACHER',
                        'Admin'   => 'ROLE_ADMIN',
                    ],
                    'multiple'     => true,
                    'expanded'     => true,
                    'by_reference' => false,
                    'attr'         => ['class' => 'form-check'],
                    'constraints'  => [
                        new Assert\Count([
                            'min'        => 1,
                            'minMessage' => 'You must select at least one role.',
                        ]),
                    ],
                ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'is_edit'    => false,
            'is_admin'   => false,
            'attr'       => ['novalidate' => 'novalidate'],
        ]);
    }
}
