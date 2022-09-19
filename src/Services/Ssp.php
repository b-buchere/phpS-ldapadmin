<?php
namespace App\Services;

use Doctrine\DBAL\Exception as DBALException;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Sg\DatatablesBundle\Datatable\DatatableInterface;
use Sg\DatatablesBundle\Datatable\Options;
use Sg\DatatablesBundle\Datatable\Column\ColumnInterface;
use Sg\DatatablesBundle\Datatable\Filter\AbstractFilter;
use Sg\DatatablesBundle\Datatable\Filter\FilterInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use PDO;
use Exception;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\MappingException;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Sg\DatatablesBundle\Response\DatatableQueryBuilder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Doctrine\DBAL\Schema\Column;

/**
 *
 * @author Arvyn
 *        
 */
class Ssp extends DatatableQueryBuilder
{
    /**
     * The current Request.
     *
     * @var Request
     */
    private $request;
    
    /**
     * This class generates a Query by given Columns.
     * Default: null.
     *
     * @var QueryBuilder|null
     */
    private $qb;
    
    /**
     * A DatatableInterface instance.
     * Default: null.
     *
     * @var DatatableInterface|null
     */
    private $datatable;
    
    /**
     * The output array.
     *
     * @var array
     */
    private $output;
    
    /**
     * The PropertyAccessor.
     * Provides functions to read and write from/to an object or array using a simple string notation.
     *
     * @var PropertyAccessor
     */
    private $accessor;
    
    /**
     * The Datatable Options instance.
     *
     * @var Options
     */
    private $options;

    /**
     * Flag indicating state of query cache for records counting. This value is passed to Query object when it is
     * created. Default value is false.
     *
     * @var bool
     */
    private $useCountQueryCache = false;

    /**
     * Arguments to pass when configuring result cache on query for counting records. Those arguments are used when
     * calling useResultCache method on Query object when one is created.
     *
     * @var array
     */
    private $useCountResultCacheArgs = [false];
    
    public function __construct(RequestStack $requestStack)
    {
        $this->request = $requestStack->getCurrentRequest();
        $this->datatableQueryBuilder = null;
        
        $this->accessor = PropertyAccess::createPropertyAccessor();  
    }

    /**
     * @throws Exception
     *
     * @return $this
     */
    public function setDatatable(DatatableInterface $datatable)
    {
       
        $this->datatable = $datatable;
        
        $this->em = $datatable->getEntityManager();
        $this->entityName = $datatable->getEntity();
        
        $this->metadata = $this->getMetadata($this->entityName);
        $this->entityShortName = $this->getSafeName(strtolower($this->metadata->getReflectionClass()->getShortName()));
        
        $this->rootEntityIdentifier = $this->getIdentifier($this->metadata);
        
        $this->columns = $datatable->getColumnBuilder()->getColumns();
        $this->columnNames = $datatable->getColumnBuilder()->getColumnNames();
        
        $this->options = $datatable->getOptions();
        
        $this->initColumnArrays();
        
        return $this;
    }

    /**
     * @param QueryBuilder $qb
     *
     * @return $this
     */
    public function setQb($qb)
    {
        $this->qb = $qb;
        
        return $this;
    }
    
    /**
     * Get the built qb.
     *
     * @return QueryBuilder
     */
    public function getBuiltQb()
    {
        $qbc = clone $this->qb;
        
        $this->filter($qbc);
        $this->order($qbc);
        $this->limit($qbc);

        return $qbc;
    }
    
    /**
     * Create the data output array for the DataTables rows
     *
     *  @param  array $columns Column information array
     *  @param  array $data    Data from the SQL get
     *  @return array          Formatted data in a row based format
     */
    private function formatData (  $oResult )
    {
        $lineFormatter = $this->datatable->getLineFormatter();
        $columns = $this->datatable->getColumnBuilder()->getColumns();
        foreach ($oResult as $key=>$row) {           
            // 2. Call the the lineFormatter to format row items
            if (null !== $lineFormatter && \is_callable($lineFormatter)) {
                $row = \call_user_func($this->datatable->getLineFormatter(), $oResult[$key]);
            }
            
            /** @var ColumnInterface $column */
            foreach ($columns as $column) {
                // 3. Add some special data to the output array. For example, the visibility of actions.
                $column->addDataToOutputArray($row);
                // 4. Call Columns renderContent method to format row items (e.g. for images or boolean values)
                $column->renderCellContent($row);
            }

            foreach ($columns as $column) {
                if (! $column->getSentInResponse()) {
                    unset($row[$column->getDql()]);
                }
            }
            
            $this->output[] = $row;
        }
    }
    
    /**
     * Paging
     *
     * Construct the LIMIT clause for server-side processing SQL query
     *
     *  @param  array $request Data sent to server by DataTables
     *  @param  array $columns Column information array
     *  @return string SQL limit clause
     */
    private function limit ( QueryBuilder $qb) : Ssp
    {        
        if ( !is_null($this->request->get('start')) && $this->request->get('length') != -1 ) {
            $qb->setFirstResult($this->request->get('start'))->setMaxResults($this->request->get('length'));
        }
        
        return $this;
    }
    
    
    /**
     * Ordering
     *
     * Construct the ORDER BY clause for server-side processing SQL query
     *
     *  @param  array $request Data sent to server by DataTables
     *  @param  array $columns Column information array
     *  @return string SQL order by clause
     */
    private function order ( QueryBuilder $qb)
    {
        $requestOrder = $this->request->get('order');

        if ( !is_null($requestOrder) && count($requestOrder) ) {
            
            for ( $i=0; $i<count($requestOrder); ++$i ) {
                // Convert the column index into the column data property
                $columnIdx = intval($requestOrder[$i]['column']);
                
                $requestColumn = $this->request->get('columns')[$columnIdx];
                
                if ('true' === $requestColumn['orderable']) {
                    $columnNames = (array) $this->orderColumns[$columnIdx];
                    $orderDirection = $requestOrder[$i]['dir'];
                    
                    foreach ($columnNames as $columnName) {
                        $qb->addOrderBy($columnName, $orderDirection);
                    }
                }
            }
        }
        return $this;
    }
    
    
    /**
     * Searching / Filtering
     *
     * Construct the WHERE clause for server-side processing SQL query.
     *
     * NOTE this does not match the built-in DataTables filtering which does it
     * word by word on any field. It's possible to do here performance on large
     * databases would be very poor
     *
     *  @param  array $request Data sent to server by DataTables
     *  @param  array $columns Column information array
     *  @param  array $bindings Array of values for PDO bindings, used in the
     *    sql_exec() function
     *  @return string SQL where clause
     */
    private function filter ( QueryBuilder $qb )
    {
        
        $requestSearch = $this->request->get('search');
        $requestColumns = $this->request->get('columns');
        
        if ( !is_null($requestSearch) && !empty($requestSearch['value']) ) {
            $orExpr = $qb->expr()->orX();
            $globalSearch = $requestSearch['value'];
            $globalSearchType = $this->options->getGlobalSearchType();
            
            //for ( $i=0, $ien=count($requestColumns) ; $i<$ien ; $i++ ) {
            foreach ($this->columns as $key => $column) {
                if (true === $this->isSearchableColumn($column)) {
                    /** @var AbstractFilter $filter */
                    $filter = $this->accessor->getValue($column, 'filter');
                    $searchType = $globalSearchType;
                    $searchFields = (array) $this->searchColumns[$key];
                    $searchValue = $globalSearch;
                    $searchTypeOfField = $column->getTypeOfField();
                    foreach ($searchFields as $searchField) {
                        $orExpr = $filter->addOrExpression($orExpr, $qb, $searchType, $searchField, $searchValue, $searchTypeOfField, $key);
                    }
                }
            }
            if ($orExpr->count() > 0) {
                $qb->andWhere($orExpr);
            }
        }
        
        // individual filtering
        if (true === $this->accessor->getValue($this->options, 'individualFiltering')) {
            $andExpr = $qb->expr()->andX();
            
            $parameterCounter = self::INIT_PARAMETER_COUNTER;
            
            foreach ($this->columns as $key => $column) {
                if (true === $this->isSearchableColumn($column)) {
                    if (false === \array_key_exists($key, $this->request->get('columns'))) {
                        continue;
                    }
                    
                    $searchValue = $requestColumns[$key]['search']['value'];
                    
                    if ('' !== $searchValue && null !== $searchValue) {
                        /** @var FilterInterface $filter */
                        $filter = $this->accessor->getValue($column, 'filter');
                        $searchField = $this->searchColumns[$key];
                        $searchTypeOfField = $column->getTypeOfField();
                        $andExpr = $filter->addAndExpression($andExpr, $qb, $searchField, $searchValue, $searchTypeOfField, $parameterCounter);
                    }
                }
            }
            
            if ($andExpr->count() > 0) {
                $qb->andWhere($andExpr);
            }
        }
        
        return $this;
    }
    
    
    /**
     * Perform the SQL queries needed for an server-side processing requested,
     * utilising the helper functions of this class, limit(), order() and
     * filter() among others. The returned array is ready to be encoded as JSON
     * in response to an SSP request, or can be modified if needed before
     * sending back to the client.
     *
     *  @param  array $request Data sent to server by DataTables
     *  @param  array|PDO $conn PDO connection resource or connection parameters array
     *  @param  string $table SQL table to query
     *  @param  string $primaryKey Primary key of the table
     *  @param  array $columns Column information array
     *  @return array          Server-side processing response array
     */
    public function getResponse (  )
    {   
        $countAllresult = $this->getCountAllResults();
        $allResult = $this->qb->getQuery()->getResult();
        
        $query = $this->execute();        
        $oResult = $query->getResult();
        $this->formatData( $oResult );

        /*
         * Output
         */
        $json = array(
            "draw"            => !is_null ( $this->request->get('draw') ) ?intval( $this->request->get('draw') ) : 0,
            "recordsTotal"    => count( $allResult ),
            "recordsFiltered" => $countAllresult,
            "data"            => $this->output
        );

        return new JsonResponse($json);
    }

    /**
     * Query results before filtering.
     *
     * @return int
     */
    public function getCountAllResults()
    {
        $qbc = clone $this->qb;
        $qbc->select('count(distinct '.$qbc->getRootAlias().'.'.$this->rootEntityIdentifier.')');
        $this->filter($qbc);
        $qbc->setFirstResult(null);
        $qbc->setMaxResults(null);
        $qbc->resetDQLPart('orderBy');

        $query = $qbc->getQuery();
        $query->useQueryCache($this->useCountQueryCache);
        \call_user_func_array([$query, 'useResultCache'], $this->useCountResultCacheArgs);
        
        return ! $qbc->getDQLPart('groupBy')
        ? (int) $query->getSingleScalarResult()
        : \count($query->getResult());
    }

    /**
     * Init column arrays for select, search, order and joins.
     *
     * @return $this
     */
    private function initColumnArrays()
    {
        foreach ($this->columns as $key => $column) {
            $dql = $this->accessor->getValue($column, 'dql');
            $data = $this->accessor->getValue($column, 'data');
            
            $currentPart = is_null($this->qb->getRootAlias())?$this->entityShortName:$this->qb->getRootAlias();
            $currentAlias = $currentPart;
            $metadata = $this->metadata;
            if (true === $this->accessor->getValue($column, 'customDql')) {
                $columnAlias = str_replace('.', '_', $data);
                
                // Select
                $selectDql = preg_replace('/\{([\w]+)\}/', '$1', $dql);
                $this->addSelectColumn(null, $selectDql.' '.$columnAlias);
                // Order on alias column name
                $this->addOrderColumn($column, null, $columnAlias);
                // Fix subqueries alias duplication
                $searchDql = preg_replace('/\{([\w]+)\}/', '$1_search', $dql);
                $this->addSearchColumn($column, null, $searchDql);
            } elseif (true === $this->accessor->getValue($column, 'selectColumn')) {

                $this->addSelectColumn($currentAlias, $this->getIdentifier($metadata));
                if($data !== $dql){
                    $currentAlias = null;
                }
                $this->addSelectColumn($currentAlias, $data);
                $this->addSearchOrderColumn($column, $currentAlias, $data);
            } else {
                // Add Order-Field for VirtualColumn
                if ($this->accessor->isReadable($column, 'orderColumn') && true === $this->accessor->getValue($column, 'orderable')) {
                    $orderColumns = (array) $this->accessor->getValue($column, 'orderColumn');
                    foreach ($orderColumns as $orderColumn) {
                        $orderParts = explode('.', $orderColumn);
                        if (\count($orderParts) < 2 && (!isset($this->columnNames[$orderColumn]) || null == $this->accessor->getValue($this->columns[$this->columnNames[$orderColumn]], 'customDql')) ) {
                                $orderColumn = $this->entityShortName.'.'.$orderColumn;
                        }

                        $this->orderColumns[$key][] = $orderColumn;
                    }
                } else {
                    $this->orderColumns[] = null;
                }
                
                // Add Search-Field for VirtualColumn
                if ($this->accessor->isReadable($column, 'searchColumn') && true === $this->accessor->getValue($column, 'searchable')) {
                    $searchColumns = (array) $this->accessor->getValue($column, 'searchColumn');
                    foreach ($searchColumns as $searchColumn) {
                        $searchParts = explode('.', $searchColumn);
                        if (\count($searchParts) < 2) {
                            $searchColumn = $this->entityShortName . '.' . $searchColumn;
                        }
                        $this->searchColumns[$key][] = $searchColumn;
                    }
                } else {
                    $this->searchColumns[] = null;
                }
            }
        }
        
        return $this;
    }
    
    //-------------------------------------------------
    // Private - Helper
    //-------------------------------------------------
    
    /**
     * Add select column.
     *
     * @param string $columnTableName
     * @param string $data
     *
     * @return $this
     */
    private function addSelectColumn($columnTableName, $data)
    {
        if (isset($this->selectColumns[$columnTableName])) {
            if (! \in_array($data, $this->selectColumns[$columnTableName], true)) {
                $this->selectColumns[$columnTableName][] = $data;
            }
        } else {
            $this->selectColumns[$columnTableName][] = $data;
        }
        
        return $this;
    }
    
    /**
     * Add order column.
     *
     * @param object $column
     * @param string $columnTableName
     * @param string $data
     *
     * @return $this
     */
    private function addOrderColumn($column, $columnTableName, $data)
    {
        $columnTableName = ($columnTableName ? $columnTableName.'.' : '');
        true === $this->accessor->getValue($column, 'orderable') ? $this->orderColumns[] = $columnTableName.$data : $this->orderColumns[] = null;
        
        return $this;
    }
    
    /**
     * Add search column.
     *
     * @param object $column
     * @param string $columnTableName
     * @param string $data
     *
     * @return $this
     */
    private function addSearchColumn($column, $columnTableName, $data)
    {
        $columnTableName = ($columnTableName ? $columnTableName.'.' : '');
        true === $this->accessor->getValue($column, 'searchable') ? $this->searchColumns[] = $columnTableName.$data : $this->searchColumns[] = null;
        
        return $this;
    }
    
    /**
     * Add search/order column.
     *
     * @param object $column
     * @param string $columnTableName
     * @param string $data
     *
     * @return $this
     */
    private function addSearchOrderColumn($column, $columnTableName, $data)
    {
        $this->addOrderColumn($column, $columnTableName, $data);
        $this->addSearchColumn($column, $columnTableName, $data);
        
        return $this;
    }

    
    /**
     * @param string $entityName
     *
     * @throws Exception
     *
     * @return ClassMetadata
     */
    private function getMetadata($entityName)
    {
        try {
            $metadata = $this->em->getMetadataFactory()->getMetadataFor($entityName);
        } catch (MappingException $e) {
            throw new Exception('DatatableQueryBuilder::getMetadata(): Given object '.$entityName.' is not a Doctrine Entity.');
        }
        
        return $metadata;
    }
    
    /**
     * Get safe name.
     *
     * @param $name
     *
     * @return string
     */
    private function getSafeName($name)
    {
        try {
            $reservedKeywordsList = $this->em->getConnection()->getDatabasePlatform()->getReservedKeywordsList();
            $isReservedKeyword = $reservedKeywordsList->isKeyword($name);
        } catch (DBALException $exception) {
            $isReservedKeyword = false;
        }
        
        return $isReservedKeyword ? "_{$name}" : $name;
    }
    
    private function getIdentifier(ClassMetadata $metadata)
    {
        $identifiers = $metadata->getIdentifierFieldNames();
        
        return array_shift($identifiers);
    }
    
    /**
     * Is searchable column.
     *
     * @return bool
     */
    private function isSearchableColumn(ColumnInterface $column)
    {
        $searchColumn = null !== $this->accessor->getValue($column, 'dql') && true === $this->accessor->getValue($column, 'searchable');
        
        if (false === $this->options->isSearchInNonVisibleColumns()) {
            return $searchColumn && true === $this->accessor->getValue($column, 'visible');
        }
        
        return $searchColumn;
    }
}

