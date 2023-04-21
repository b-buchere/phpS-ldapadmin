<?php

namespace App\Form;

use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use App\Entity\Groupes;
use App\Entity\Utilisateurs;
use App\Repository\UtilisateursRepository;

class LdapGroupcreateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {

        $builder
            ->add(
                'nom',
                TextType::class,
                [
                    'label'=>'groupName',
                    'attr'=>[
                        'placeholder'=>'groupName',
                        'class'=>'form-control',
                    ],
                    'row_attr'=>[
                        'class'=>"mb-2"
                    ]
                ]
            )->add(
                'membres',
                EntityType::class,
                [
                    'class'=>Utilisateurs::class,
                    'required'=>false,
                    'multiple'=>true,
                    'choice_label'=>function (Utilisateurs $user) {
                        return $user->getNom().' '.$user->getPrenom() ;
                    },
                    'query_builder' => function(UtilisateursRepository $gr){
                        return $gr->findAllForDatatable();
                    },
                    'attr'=>[
                        'class'=>"border border-dark rounded p-2 w-100 mb-2"
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
                        "class"=>"btn btn-outline-dark mb-3 col-12"
                    ]
                ]
            );
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'=>Groupes::class,
            'csrf_field_name' => '_csrf_token'

        ]);
    }
}
