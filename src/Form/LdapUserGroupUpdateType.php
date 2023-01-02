<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;

class LdapUserGroupUpdateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {

        $builder
            ->add(
                'fileimport',
                FileType::class,
                [
                    'label'=>"fileimport",
                    'attr'=>[
                        'placeholder'=>'file',
                        'class'=>'form-control',
                    ],
                    'row_attr'=>[
                        'class'=>""
                    ],
                    'help'=>'<span class="spinner-border spinner-border-sm" role="status"></span> <span>'.$options['help_message'].'</span>',
                    'help_attr'=>['class'=>"m-0 mb-1 invisible"],
                    "help_html"=>true,
                    'constraints' => [
                        new File([
                            'mimeTypes' => [
                                'text/plain',
                                'text/csv'
                            ],
                            'mimeTypesMessage' => 'Veuillez choisir un document csv',
                        ])
                    ]
                ]
                )->add(
                    'verifyUrl',
                    HiddenType::class,
                    [
                        'data'=> '/ldapadmin/group/bulk/verifyfile'
                    ]
                )->add(
                    'progressUrl',
                    HiddenType::class,
                    [
                        'data'=> '/ldapadmin/group/bulk/progress'
                    ]
                )->add(
                'valid',
                SubmitType::class,
                [
                    'label'=>'import',
                    'row_attr'=>[
                        'class'=>""
                    ],
                    'attr'=>[
                        "class"=>"btn btn-outline-dark mb-3 col-12"
                    ]
                ]
                );
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_field_name' => '_csrf_token',
            'help_message'=>""
        ]);
    }
}
