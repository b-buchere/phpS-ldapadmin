<?php
namespace App\Services;


use LdapRecord\Utilities;

abstract class Tree {
	# List of entries in the tree view cache
	public $entries = array();

	/**
	 * Displays the LDAP tree
	 */
	abstract public function draw();

	public function __construct($base) {
	    $tree = null;
	    if (! $tree) {
	            
            //$treeclass = $_SESSION[APPCONFIG]->getValue('appearance','tree');
            
            # If we are not logged in, just return the empty tree.
            //if ($base) {
                $this->addEntry($base);
                
                $baseEntry = $this->getEntry($base);
                $baseEntry->setBase();
                $baseEntry->open();
                //if ($server->getValue('appearance','open_tree')) {
                    //$baseEntry = $this->getEntry($base);
                    //$baseEntry->open();
                //}
            //}
	                
	    }
	    
	    return $this;
	}

	/**
	 * Get the entries that are BaseDN entries.
	 *
	 * @return array Base DN entries
	 */
	public function getBaseEntries() {

		$return = array();

		foreach ($this->entries as $details){
		    
		    if ($details->isBaseEntry()){
		        array_push($return,$details);
		    }				
		}
		return $return;
	}

	/**
	 * Get a tree entry
	 *
	 * @param dn DN to retrieve
	 * @return object Tree DN object
	 */
	public function getEntry($value) {

		$lower = strtolower($value);
		if (isset($this->entries[$lower])){
			return $this->entries[$lower];
		}
		
		return null;
	}

	/**
	 * Add an entry in the tree view ; the entry is added in the
	 * children array of its parent
	 *
	 * @param dn DN to add
	 * @param string $dn the dn of the entry to create
	 */
	public function addEntry($value) {

	    //$server = $this->getServer();
		$lower = strtolower($value);

		# @todo Temporarily removed, some non-ascii char DNs that do exist, fail here for some reason?
		#if (! ($server->dnExists($dn)))
		#	return;

		if (isset($this->entries[$lower]))
			debug_dump_backtrace('Calling add entry to an entry that ALREADY exists?',1);

		$tree_factory = new TreeItem($value);
		//$tree_factory->setObjectClasses($server->getDNAttrValue($dn,'objectClass'));
		
		/*$isleaf = $server->getDNAttrValue($dn,'hassubordinates');
		if ( ! strcasecmp($isleaf[0],'false')) {
			$tree_factory->setLeaf(true);
		}*/

		$this->entries[$lower] = $tree_factory;

		# Is this entry in a base entry?
		/*if (in_array_ignore_case($dn,$server->getBaseDN(null))) {
			$this->entries[$dnlower]->setBase();*/

		# If the parent entry is not in the tree, we add it. This routine will in itself
		# recall this method until we get to the top of the tree (the base).
		/*} else {
			$parent_dn = $server->getContainer($dn);

			if (DEBUG_ENABLED)
				debug_log('Parent DNs (%s)',64,0,__FILE__,__LINE__,__METHOD__,$parent_dn);

			if ($parent_dn) {
				$parent_entry = $this->getEntry($parent_dn);

				if (! $parent_entry) {
					$this->addEntry($parent_dn);
					$parent_entry = $this->getEntry($parent_dn);
				}

				# Update this DN's parent's children list as well.
				$parent_entry->addChild($dn);
			}
		}*/
	}

	/**
	 * Delete an entry from the tree view ; the entry is deleted from the
	 * children array of its parent
	 *
	 * @param dn DN to remote
	 */
	public function delEntry($name) {

		$dnlower = strtolower($name);
		
		$parentName = null;
		$entry = $this->entries[$dnlower];
		
		if (!is_null($entry)){
		    $parentName = $entry->getParent();
		    unset($this->entries[$dnlower]);
		}

		# Delete entry from parent's children as well.
		$parentEntry = $this->getEntry($parentName);

		if ($parentEntry){
		    /*if(!is_null($parentEntry->getParent()) ){
		        $this->delEntry($parentEntry->getParent());
		    }*/
		    $parentEntry->delChild($name);
		}
	}

	/**
	 * Rename an entry in the tree
	 *
	 * @param dn Old DN
	 * @param dn New DN
	 */
	/*public function renameEntry($old,$new) {
		if (DEBUG_ENABLED && (($fargs=func_get_args())||$fargs='NOARGS'))
			debug_log('Entered (%%)',33,0,__FILE__,__LINE__,__METHOD__,$fargs);

		$lowerOLD = $this->indexDN($old);
		$lowerNEW = $this->indexDN(new);

		$this->entries[$lowerNEW] = $this->entries[$lowerOLD];
		if ($lowerOLD != $lowerNEW)
			unset($this->entries[$lowerOLD]);
		$this->entries[$lowerNEW]->setName(new);

		# Update the parent's children
		$parentNEW = $server->getContainer(new);
		$parentOLD = $server->getContainer($old);

		$parent_entry = $this->getEntry($parentNEW);
		if ($parent_entry)
			$parent_entry->addChild($new);

		$parent_entry = $this->getEntry($parentOLD);
		if ($parent_entry)
			$parent_entry->delChild($OLD);
	}*/

	/**
	 * Read the children of a tree entry
	 *
	 * @param dn DN of the entry
	 * @param boolean LDAP Size Limit
	 */
	public function readChildren(string $nodeValue, Bool $nolimit=false) {

		if (! isset($this->entries[$nodeValue]))
		    debug_dump_backtrace('Reading children on an entry that isnt set? '.$nodeValue,true);

		//$ldap['child_limit'] = $nolimit ? 0 : $_SESSION[APPCONFIG]->getValue('search','size_limit');
/*		$ldap['filter'] = $_SESSION[APPCONFIG]->getValue('appearance','tree_filter');
		$ldap['deref'] = $_SESSION[APPCONFIG]->getValue('deref','tree');*/

		# Perform the query to get the children.
		$ldap['children'] = $server->getContainerContents($dn,null,$ldap['child_limit'],$ldap['filter'],$ldap['deref']);

		if (! count($ldap['children'])) {
		    $this->entries[$nodeValue]->unsetSizeLimited();

			return;
		}

		# Relax our execution time, it might take some time to load this
		if ($nolimit)
			@set_time_limit($_SESSION[APPCONFIG]->getValue('search','time_limit'));

			$this->entries[$nodeValue]->readingChildren(true);

		foreach ($ldap['children'] as $child) {
		    if (! in_array($child,$this->entries[$nodeValue]->getChildren()))
			    $this->entries[$nodeValue]->addChild($child);
		}

		$this->entries[$nodeValue]->readingChildren(false);

		if (count($this->entries[$nodeValue]->getChildren()) == $ldap['child_limit'])
		    $this->entries[$nodeValue]->setSizeLimited();
		else
		    $this->entries[$nodeValue]->unsetSizeLimited();
	}

	/**
	 * Return the number of children an entry has. Optionally autoread the child entry.
	 *
	 * @param dn DN of the entry
	 * @param boolean LDAP Size Limit
	 */
	protected function readChildrenNumber($dn,$nolimit=false) {
		if (DEBUG_ENABLED && (($fargs=func_get_args())||$fargs='NOARGS'))
			debug_log('Entered (%%)',33,0,__FILE__,__LINE__,__METHOD__,$fargs);

		$dnlower = $this->indexDN($dn);

		if (! isset($this->entries[$dnlower]))
			debug_dump_backtrace('Reading children on an entry that isnt set?',true);

		# Read the entry if we havent got it yet.
		if (! $this->entries[$dnlower]->isLeaf() && ! $this->entries[$dnlower]->getChildren())
			$this->readChildren($dn,$nolimit);

		return count($this->entries[$dnlower]->getChildren());
	}

}
?>
