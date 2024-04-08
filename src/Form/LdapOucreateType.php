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

class LdapOucreateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {

        $builder
            ->add(
                'ouName',
                TextType::class,
                [
                    'label'=>"Nom de l'OU",
                    'attr'=>[
                        'placeholder'=>'ouName',
                        'class'=>'form-control',
                    ],
                    'row_attr'=>[
                        'class'=>""
                    ]
                ]
                )->add(
                    'valid',
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
            'csrf_field_name' => '_csrf_token'

        ]);
    }
}
