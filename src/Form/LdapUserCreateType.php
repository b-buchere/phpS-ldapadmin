<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use App\Entity\Utilisateurs;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use App\Entity\Groupes;
use Doctrine\ORM\EntityRepository;
use App\Repository\GroupesRepository;

class LdapUserCreateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {
            $data = $event->getData();
            $form = $event->getForm();
            
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
                    'mapped'=>false,
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
        
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            $data = $event->getData();
            $form = $event->getForm();
            
            //Eclatement du dn pour parser le dn uniquement avec l'OU de région et de structure de l'utilisateur
            $userDnExploded = explode(',',$data->getDn());
            
            array_shift($userDnExploded);
            $userDnStructure = implode(",", $userDnExploded);
            
            array_shift($userDnExploded);
            $userDnRegion = implode(",", $userDnExploded);

            $connection = $form->getConfig()->getOption('ldap_connection');
            $query = $connection->query();
            $nodeList = $query->select(['dn','ou','namingContexts'])
            ->in($userDnRegion)
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
                    'data'=>$userDnStructure,
                    'mapped'=>false,
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

        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {
            $data = $event->getData();
            $form = $event->getForm();
            
            //Eclatement du dn pour parser le dn uniquement avec l'OU de région et de structure de l'utilisateur
            $userDnExploded = explode(',',$data->getDn());
            
            array_shift($userDnExploded);
            
            array_shift($userDnExploded);
            $userDnRegion = implode(",", $userDnExploded);
            
            $form->get('fullname')->setData($data->getPrenom().' '.$data->getNom());
            $form->get('region')->setData($userDnRegion);
            
        });
        
        $builder->add(
                'nom',
                TextType::class,
                [
                    'label'=>"nom",
                    "required"=>true,
                    'attr'=>[
                        'placeholder'=>'nom',
                        'class'=>'form-control',
                    ],
                    'row_attr'=>[
                        'class'=>"col-6 mb-4"
                    ],
                ]
            )->add(
                'prenom',
                TextType::class,
                [
                    'label'=>"prenom",
                    "required"=>true,
                    'attr'=>[
                        'placeholder'=>'prenom',
                        'class'=>'form-control',
                    ],
                    'row_attr'=>[
                        'class'=>"col-6 mb-4"
                    ],
                ]
            )->add(
                'fullname',
                TextType::class,
                [
                    'mapped'=>false,
                    'label'=>"fullname",
                    'attr'=>[
                        'class'=>'form-control',
                        "readonly"=>"readonly"
                    ],
                    'row_attr'=>[
                        'class'=>"col-6 mb-4"
                    ],
                ]
            )->add(
                'courriel',
                TextType::class,
                [
                    'label'=>"courriel",
                    'attr'=>[
                        "placeholder"=>"courriel",
                        'class'=>'form-control'
                    ],
                    'row_attr'=>[
                        'class'=>"col-6 mb-3"
                    ],
                ]
            )->add(
                'structure',
                ChoiceType::class,
                [
                    'mapped'=>false,
                    'choices'=>array(),
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
                    'mapped'=>false,
                    'choices'=>$options['regions'],
                    'label'=>"region",
                    "required"=>true,
                    'attr'=>[
                        "placeholder"=>"region",
                        'class'=>'form-select'
                    ],
                    'row_attr'=>[
                        'class'=>"col-6 mb-3"
                    ],
                ]
            )->add(
                'Groupes',
                EntityType::class,
                [
                    'class'=>Groupes::class,
                    'required'=>false,
                    'multiple'=>true,
                    'choice_label'=>'nom',
                    'query_builder' => function(GroupesRepository $gr){
                        return $gr->findAllForDatatable();
                    },
                    'attr'=>[
                        'class'=>"border border-dark rounded p-2 w-75 mb-2"
                    ]
                ]
            )->add(
                'valid',
                SubmitType::class,
                [
                    'row_attr'=>[
                        'class'=>"col-3"
                    ],
                    'attr'=>[
                        "class"=>"btn btn-outline-dark mb-3 col-12"
                    ]
                ]
            )
            ->add(
                'cancel',
                ButtonType::class,
                [
                    'row_attr'=>[
                        'class'=>"col-3"
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
            'data_class'=>Utilisateurs::class,
            'csrf_field_name' => '_csrf_token',
            'regions'=>array(),
            'ldap_connection'=>null,
            'attr'=>["class"=>"container p-2"]

        ]);
    }

}
