<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class LdapUserCreateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {

        $builder->add(
                'lastname',
                TextType::class,
                [
                    'label'=>"lastname",
                    "required"=>true,
                    'attr'=>[
                        'placeholder'=>'lastname',
                        'class'=>'form-control',
                    ],
                    'row_attr'=>[
                        'class'=>"col-6"
                    ],
                ]
            )->add(
                'firstname',
                TextType::class,
                [
                    'label'=>"firstname",
                    "required"=>true,
                    'attr'=>[
                        'placeholder'=>'firstname',
                        'class'=>'form-control',
                    ],
                    'row_attr'=>[
                        'class'=>"col-6"
                    ],
                ]
            )->add(
                'fullname',
                TextType::class,
                [
                    'label'=>"fullname",
                    'attr'=>[
                        'class'=>'form-control',
                        "readonly"=>"readonly"
                    ],
                    'row_attr'=>[
                        'class'=>"col-6"
                    ],
                ]
            )->add(
                'username',
                TextType::class,
                [
                    'label'=>"username",
                    'attr'=>[
                        'class'=>'form-control',
                        "readonly"=>"readonly"
                    ],
                    'row_attr'=>[
                        'class'=>"col-6"
                    ],
                ]
            )->add(
                'mail',
                TextType::class,
                [
                    'label'=>"mail",
                    'attr'=>[
                        "placeholder"=>"mail",
                        'class'=>'form-control'
                    ],
                    'row_attr'=>[
                        'class'=>"col-6"
                    ],
                ]
            )->add(
                'structure',
                ChoiceType::class,
                [
                    'label'=>"structure",
                    "required"=>true,
                    'attr'=>[
                        "placeholder"=>"structure",
                        'class'=>'form-control'
                    ],
                    'row_attr'=>[
                        'class'=>"col-6"
                    ],
                ]
            )->add(
                'region',
                ChoiceType::class,
                [
                    'choices'=>$options['regions'],
                    'label'=>"region",
                    "required"=>true,
                    'attr'=>[
                        "placeholder"=>"region",
                        'class'=>'form-control'
                    ],
                    'row_attr'=>[
                        'class'=>"col-6"
                    ],
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
            'csrf_field_name' => '_csrf_token',
            'regions'=>[]

        ]);
    }
}
