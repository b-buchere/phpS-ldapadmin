<?php
namespace App\DatatablesBundle;

use App\Entity\Utilisateurs;
use Sg\DatatablesBundle\Datatable\AbstractDatatable;
use Sg\DatatablesBundle\Datatable\Column\ActionColumn;
use Sg\DatatablesBundle\Datatable\Column\BooleanColumn;
use Sg\DatatablesBundle\Datatable\Column\Column;
use Sg\DatatablesBundle\Datatable\Filter\SelectFilter;
use App\Services\BaseDatatable;
use App\Entity\Groupes;
/**
 * Class PostDatatable
 *
 * @package AppBundle\Datatables
*/
class UserDatatable extends BaseDatatable
{
    
    /**
     * {@inheritdoc}
     */
    public function getLineFormatter()
    {
        return function(Utilisateurs $user) {
            $row = [];
            $row['id'] = $user->getId();
            $row['nom'] = $user->getNom().' '.$user->getPrenom();
            $row['identifiant'] = $user->getIdentifiant();
            $row['courriel'] = $user->getCourriel();
            $row['groupes'] = '';
            
            $userGroupes = $user->getGroupes();
            
            foreach( $userGroupes as $groupe){
                /**
                 * @var Groupes $groupe
                 */
                $row['groupes'] .= $groupe->getNom().', ';
            }
            if(!empty($userGroupes) && !empty($row['groupes'])){
                $row['groupes'] = substr($row['groupes'], 0, -2);
            }
            
            return $row;
        };

    }

    /**
     * {@inheritdoc}
     */
    public function buildDatatable(array $options = array())
    {
        
        $this->ajax->setMethod("POST");
        $this->ajax->setUrl($options['ajaxUrl']);
        
        $this->features->setAutoWidth(false);

        $this->options->set(array(
            'classes' => 'table table-borderless table-striped table-hover dataTable dtr-inline',
            'individual_filtering' => false,
            'individual_filtering_position' => 'head',
            'order' => array(array(0, 'asc')),
            'order_cells_top' => true,
            //'global_search_type' => 'gt',
            'search_in_non_visible_columns' => false,
            'dom' => '<"top">ft<"bottom"il>rp<"clear">',
        ));
        $this->columnBuilder
            ->add('id', Column::class, array(
                'title' => 'Id',
                'searchable' => false,
                'orderable' => true,
                'visible'=>false
            ))
            ->add('nom', Column::class, array(
                'title' => 'Nom',
                'searchable' => true,
                'orderable' => true,
            ))
            ->add('identifiant', Column::class, array(
                'title' => 'identifiant',
                'searchable' => true,
                'orderable' => true
            ))
            ->add('courriel', Column::class, array(
                'title' => 'courriel',
                'searchable' => false,
                'orderable' => true,
            ))
            ->add('groupes', Column::class, [
                'searchable' => true,
                'orderable' => false,
                'title'=>'Groupes',
                'width'=>'400',
                'class_name'=>'text-truncate groupe_col'
            ])
/*            ->add('enabled', BooleanColumn::class, [
                'searchable' => true,
                'title'=>'Actif',
                'true_icon'=> 'badge badge-pill bg-success',
                'false_icon' => 'badge rounded-pill bg-danger',
                'filter' => array(SelectFilter::class, array(
                    'search_type' => 'eq',
                    'select_options' => array(
                        '' => 'Tout',
                        '1' => 'Oui',
                        '0' => 'Non'
                    ),
                )),
            ])*/
            ->add(null, ActionColumn::class, array(
                'title' => 'Actions',
                'start_html' => '<div class="btn-group w-100" role="group" aria-label="actions">',
                'end_html' => '</div>',
                'actions' => [
                    [
                        'route' => 'ldapadmin_useredit',
                        'label' => '',
                        'icon'  => 'fas fa-edit',
                        'route_parameters' => [
                            'id' => 'id'
                        ],
                        'attributes' => [
                            'rel' => 'tooltip',
                            'title' => 'Modifier',
                            'role' => 'button'
                        ]
                    ]/*,
                    [
                        'route' => 'admin_product_delete',
                        'label' => '',
                        'icon'  => 'fas fa-trash',
                        'route_parameters' => [
                            'id' => 'id',
                        ],
                        'attributes' => [
                            'rel' => 'tooltip',
                            'title' => 'Supprimer',
                            'class' => 'btn btn-danger btn-xs',
                            'role' => 'button'
                        ]
                    ]*/
                ]
            ));
            /*->add(null, ActionColumn::class, array(
                'title' => 'Actions',
                'start_html' => '<div class="btn-group w-100" role="group" aria-label="actions">',
                'end_html' => '</div>',
                'actions' => [
                    [
                        'route' => 'admin_product_edit',
                        'label' => '',
                        'icon'  => 'fas fa-edit',
                        'route_parameters' => [
                            'id' => 'id'
                        ],
                        'attributes' => [
                            'rel' => 'tooltip',
                            'title' => 'Modifier',
                            'class' => 'btn btn-primary btn-xs',
                            'role' => 'button'
                        ]
                    ],
                    [
                        'route' => 'admin_product_delete',
                        'label' => '',
                        'icon'  => 'fas fa-trash',
                        'route_parameters' => [
                            'id' => 'id',
                        ],
                        'attributes' => [
                            'rel' => 'tooltip',
                            'title' => 'Supprimer',
                            'class' => 'btn btn-danger btn-xs',
                            'role' => 'button'
                        ]
                    ],
                    [
                        'route' => 'admin_product_edit',
                        'label' => '',
                        'icon'  => 'fas fa-cubes',
                        'route_parameters' => [
                            'id' => 'id',
                            '_fragment' => 'variants'
                        ],
                        'attributes' => [
                            'rel' => 'tooltip',
                            'title' => 'Liste des variantes',
                            'class' => 'btn btn-primary btn-xs',
                            'role' => 'button'
                        ],
                        'render_if'=>function (Array $formattedRow) {
                            
                            return $formattedRow['variantCount']>1;
                        }
                    ]
                ]
            ));*/
        
            $this->language->set(array(
                'language_by_locale' => true
            ));
    }

    /**
     * {@inheritdoc}
     */
    public function getEntity()
    {
        return 'App\Entity\Utilisateurs';
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'user_datatable';
    }
}