<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;

class LoginType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {

        $builder
            ->add(
                '_username',
                TextType::class,
                [
                    'label'=>'user',
                    'label_attr'=>['class'=>"visually-hidden"],
                    'attr'=>[
                        'placeholder'=>'user',
                        'class'=>'form-control',
                    ],
                    'row_attr'=>[
                        'class'=>""
                    ]
                ]
            )->add(
                '_password',
                PasswordType::class,
                [
                    'label' => 'Password',
                    'label_attr'=>['class'=>"visually-hidden"],
                    'attr'=>[
                        'placeholder'=>'Password',
                        'class'=>'form-control'
                    ],
                    'row_attr'=>array('class'=>"mt-2"),
                ]
            )->add(
                'login',
                SubmitType::class,
                [
                    'row_attr'=>[
                        'class'=>""
                    ],
                    'attr'=>[
                        "class"=>"btn btn-primary mb-3 col-12 mt-2"
                    ]
                ]
            );
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_field_name' => '_csrf_token'

        ]);
    }
}
