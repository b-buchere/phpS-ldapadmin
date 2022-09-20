<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LdapGetinfoType extends AbstractType implements FormTypeInterface
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        
        $builder
            ->add(
                'User',
                TextType::class,
                [
                    'label'=>'user',
                    'label_attr'=>['class'=>"visually-hidden"],
                    'attr'=>[
                        'placeholder'=>'user',
                        'class'=>'form-control'
                    ],
                    'row_attr'=>[
                        'class'=>""
                    ]
                ]
            )->add(
                'search',
                SubmitType::class,
                [
                    'row_attr'=>[
                        'class'=>""
                    ],
                    'attr'=>[
                        "class"=>"btn btn-primary mb-3 col-12"
                    ]
                ]
            );
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => false,
            // the name of the hidden HTML field that stores the token
            'attr'=>['class'=>'row g-3'],
        ]);
    }
}
