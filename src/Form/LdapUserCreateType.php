<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
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
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {
            $data = $event->getData();
            $form = $event->getForm();
            dump($event);
            dump($form->getConfig()->getOption('ldap_connection'));
            $requestedDn = $data['region'];
            $connection = $form->getConfig()->getOption('ldap_connection');
            $query = $connection->query();
            $nodeList = $query->select(['dn','ou','namingContexts'])
            ->in($requestedDn)
            ->rawFilter('(objectCategory=organizationalUnit)')
            ->listing()->get();
            
            $aStructure = [''=>''];
            foreach($nodeList as $node){
                $aStructure[$node['ou'][0]] = $node['dn'];
            }

            $form->remove('structure');
            $form->add(
                'structure',
                ChoiceType::class,
                [
                    'data'=>$data['structure'],
                    'choices'=>$aStructure,
                    'label'=>"structure",
                    "required"=>true,
                    'attr'=>[
                        "placeholder"=>"structure",
                        'class'=>'form-select'
                    ],
                    'row_attr'=>[
                        'class'=>"col-6"
                    ],
                ]
            );
        });

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
                        'class'=>'form-select'
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
                        'class'=>'form-select'
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
            'regions'=>[],
            'ldap_connection'=>null

        ]);
    }
}
