<?php

declare(strict_types=1);

namespace Aanwik\IbexaCleanBlogThemeBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class ContactFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Name',
                'attr' => ['placeholder' => 'Enter your name...'],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Please enter your name.']),
                    new Assert\Length(['min' => 2, 'max' => 100]),
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email Address',
                'attr' => ['placeholder' => 'Enter your email address...'],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Please enter your email.']),
                    new Assert\Email(['message' => 'Please enter a valid email address.']),
                ],
            ])
            ->add('phone', TextType::class, [
                'label' => 'Phone Number',
                'required' => false,
                'attr' => ['placeholder' => 'Enter your phone number...'],
            ])
            ->add('subject', TextType::class, [
                'label' => 'Subject',
                'attr' => ['placeholder' => 'What is this regarding?'],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Please enter a subject.']),
                ],
            ])
            ->add('message', TextareaType::class, [
                'label' => 'Message',
                'required' => false,
                'attr' => ['placeholder' => 'Enter your message here...', 'rows' => 7],
                'constraints' => [
                    new Assert\Length([
                        'max' => 5000,
                        'maxMessage' => 'Your message cannot be longer than {{ limit }} characters.',
                    ]),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'attr' => ['novalidate' => 'novalidate'],
        ]);
    }
}
