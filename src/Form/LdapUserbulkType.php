<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Validator\Constraints\File;

class LdapUserbulkType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {

        $builder
            ->add(
                'fileimport',
                FileType::class,
                [
                    'label'=>"Fichier à importer",
                    'attr'=>[
                        'placeholder'=>'file',
                        'class'=>'form-control',
                    ],
                    'row_attr'=>[
                        'class'=>""
                    ],
                    'constraints' => [
                        new File([
                            'mimeTypes' => [
                                'text/csv'
                            ],
                            'mimeTypesMessage' => 'Veuillez choisir un document csv',
                        ])
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
