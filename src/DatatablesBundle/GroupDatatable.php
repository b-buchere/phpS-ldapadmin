<?php
namespace App\DatatablesBundle;

use App\Entity\Groupes;
use Sg\DatatablesBundle\Datatable\AbstractDatatable;
use Sg\DatatablesBundle\Datatable\Column\ActionColumn;
use Sg\DatatablesBundle\Datatable\Column\BooleanColumn;
use Sg\DatatablesBundle\Datatable\Column\Column;
use Sg\DatatablesBundle\Datatable\Filter\SelectFilter;
use App\Services\BaseDatatable;

/**
 * Class PostDatatable
 *
 * @package AppBundle\Datatables
*/
class GroupDatatable extends BaseDatatable
{
    
    /**
     * {@inheritdoc}
     */
    public function getLineFormatter()
    {
        return function(Groupes $group) {
            $row = [];
            $row['id'] = $group->getId();
            $row['nom'] = $group->getNom();
            
            /*$userGroupes = $user->getGroupes();
            
            foreach( $userGroupes as $groupe){
                $row['groupes'] .= $groupe->getNom().', ';
            }
            if(!empty($userGroupes) && !empty($row['groupes'])){
                $row['groupes'] = substr($row['groupes'], 0, -2);
            }*/
            
            return $row;
        };

    }

    /**
     * {@inheritdoc}
     */
    public function buildDatatable(array $options = array())
    {
        $actions = [
            [
                'label' => ' ',
                'icon'  => '',
            ]
            
        ];
        
        if( $this->security->isGranted('create')){
            $actions[0] = ['route' => 'ldapadmin_groupedit',
                'label' => '',
                'icon'  => 'fas fa-edit',
                'route_parameters' => [
                    'id' => 'id'
                ],
                'attributes' => [
                    'rel' => 'tooltip',
                    'title' => 'Modifier',
                    'role' => 'button'
                ] ];
        }
        
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
            'dom' => '<"top">t<"bottom"il>rp<"clear">',
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
            ->add(null, ActionColumn::class, array(
                'title' => 'Actions',
                'start_html' => '<div class="btn-group w-100" role="group" aria-label="actions">',
                'end_html' => '</div>',
                'actions' => $actions,

            ));
            $this->language->set(array(
                'language_by_locale' => true
            ));
    }

    /**
     * {@inheritdoc}
     */
    public function getEntity()
    {
        return 'App\Entity\Groupes';
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'group_datatable';
    }
}