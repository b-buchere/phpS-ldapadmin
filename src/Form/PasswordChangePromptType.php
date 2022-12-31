<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
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
                    'label' => "newPassword",
                    'label_attr'=>['class'=>"col-4 col-form-label"],
                    'attr'=>[
                        'placeholder'=>'newPassword',
                        'class'=>"form-control form-control-sm"
                        
                    ],
                    'row_attr'=>array('class'=>"grouptop"),
                ]
            )->add(
                'NewPasswordCnfm',
                PasswordType::class,
                [
                    'label' => "newPasswordCnfm",
                    'label_attr'=>['class'=>"col-4 col-form-label"],
                    'attr'=>[
                        'placeholder'=>'newPasswordCnfm',
                        'class'=>"form-control form-control-sm"
                        
                    ],
                    'row_attr'=>array('class'=>"groupbottom"),
                ]
            )->add(
                'submit',
                SubmitType::class,
                [
                    'label'=>"send",
                    'row_attr'=>[
                        'class'=>""
                    ],
                    'attr'=>[
                        "class"=>"btn btn-outline-dark inverted col-12 p-0"
                    ]
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
