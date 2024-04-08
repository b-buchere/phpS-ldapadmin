<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;

class PasswordChangePromptType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        
        $builder
            ->add(
                'User',
                HiddenType::class
            )->add(
                'NewPassword',
                PasswordType::class,
                [
                    'label_attr'=>['class'=>"hidden-visually"],
                    'row_attr'=>array('class'=>"grouptop"),
                ]
            )->add(
                'NewPasswordCnfm',
                PasswordType::class,
                [
                    'label_attr'=>['class'=>"hidden-visually"],
                    'row_attr'=>array('class'=>"groupbottom"),
                ]
            );
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => true,
            // the name of the hidden HTML field that stores the token
            'csrf_field_name' => 'csrf_token',
            'csrf_token_id'   => 'passwordchangerequest',
        ]);
    }
}
