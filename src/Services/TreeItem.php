<?php
namespace App\Services;

class TreeItem
{

    # This entry's DN
    protected $name;

    protected $sanitizedName;
    
    protected $displayName;

    # The objectclasses in LDAP, used to deterimine the icon and template
    protected $objectclasses = array();

    # Is this a base entry?
    private bool $baseEntry = false;

    # Array of dn - the children
    private $children = array();

    # An icon file path
    protected $icon;

    private $parent;
    
    # Is the entry a leaf?
    private $leaf = false;

    # Is the node open?
    private $open = false;

    # Is the size of children limited?
    private $size_limited = true;

    # Last template used to edit this entry
    private $template = null;

    # Do we need to sort the children
    private $childsort = true;

    # Are we reading the children
    private $reading_children = false;

    public function __construct($name)
    {
        $this->name = $name;
    }

    /**
     * Get the DN of this tree item.
     *
     * @return DN The DN of this item.
     */
    public function getName()
    {
        return $this->name;
    }

    public function getNameEncode()
    {
        return urlencode(preg_replace('/%([0-9a-fA-F]+)/', "%25\\1", $this->name));
    }

    /**
     * Set this item as a LDAP base DN item.
     */
    public function setBase()
    {
        $this->baseEntry = true;
    }

    /**
     * Return if this item is a base DN item.
     */
    public function isBaseEntry():bool
    {
        return $this->baseEntry;
    }

    /**
     * Returns null if the parent have never be defined
     * or an array of the dn of the children
     */
    public function getParent():string
    {
        if( is_null($this->parent) ){
            return '';
        }
        return $this->parent;
    }
    
    /**
     * Returns null if the children have never be defined
     * or an array of the dn of the children
     */
    public function getChildren()
    {
        if ($this->childsort && ! $this->reading_children) {
            usort($this->children, [
                LdapCustomFunctions::class,
                'compareDns'
            ]);
            $this->childsort = false;
        }
        return $this->children;
    }

    public function readingChildren($bool)
    {
        $this->reading_children = $bool;
    }

    /**
     * Do the children require resorting
     */
    public function isChildSorted()
    {
        return $this->childsort;
    }

    /**
     * Mark the children as sorted
     */
    public function childSorted()
    {
        $this->childsort = false;
    }

    /**
     * Add a child to this DN entry.
     *
     * @param
     *            DN The DN to add.
     */
    public function addChild($node): void
    {
        if (in_array($node, $this->children)) {
            return;
        }

        array_push($this->children, $node);
        $this->childsort = true;
    }

    /**
     * Delete a child from this DN entry.
     *
     * @param
     *            DN The DN to add.
     */
    public function delChild($name)
    {
        if ($this->children) {
            # If the parent hasnt been opened in the tree, then there wont be any children.
            $index = array_search($name, $this->children);

            if ($index !== false)
                unset($this->children[$index]);
        }
    }

    /**
     *
     * @param
     *            DN The DN to rename to.
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * Return if this item has been opened.7
     */
    public function isOpened()
    {
        return $this->open;
    }

    /**
     * Mark this node as closed.
     */
    public function close()
    {
        $this->open = false;
    }

    /**
     * Opens the node ; the children of the node must have been defined
     */
    public function open()
    {
        $this->open = true;
    }

    /**
     * Mark this node as a leaf.
     */
    public function setLeaf(bool $leaf = true)
    {
        $this->leaf = $leaf;
    }

    /**
     * Return if this node is a leaf.
     */
    public function isLeaf(): Bool
    {
        return $this->leaf;
    }

    /**
     * Returns the path of the icon file used to represent this node ;
     * If the icon hasnt been set, it will call get_icon()
     */
    public function getIcon()
    {
        if (! $this->icon)
            $this->icon = get_icon($this->server_id, $this->name, $this->objectclasses);

        return $this->icon;
    }

    /**
     * Mark this node as a size limited (it wont have all its children).
     */
    public function setSizeLimited($sizeLimited = true)
    {
        $this->size_limited = $sizeLimited;
    }

    /**
     * Clear the size limited flag.
     */
    public function unsetSizeLimited()
    {
        $this->setSizeLimited(false);
    }

    /**
     * Return if this node has hit an LDAP size limit (and thus doesnt have all its children).
     */
    public function isSizeLimited(): Bool
    {
        return $this->size_limited;
    }

    public function setTemplate($template)
    {
        $this->template = $template;
    }

    public function getTemplate()
    {
        return $this->template;
    }
    
    public function getSanitizedName(){
        return $this->sanitizedName;
    }
    
    public function setSanitizedName($name) {
        $this->sanitizedName = $name;
    }
    
    public function getDisplayName(){
        return $this->displayName;
    }
    
    public function setDisplayName(string $name) {
        $this->displayName = $name;
    }
    
    public function setParent($name) {
        $this->parent = $name;
    }
}
?>
