<?php
namespace App\Services;

use phpDocumentor\Reflection\Types\Mixed_;
use LdapRecord\Models\Attributes\DistinguishedName;
use LdapRecord\Models\Collection;
use LdapRecord\Models\ActiveDirectory\OrganizationalUnit;
use LdapRecord\Models\ActiveDirectory\Entry;
use LdapRecord\Models\ActiveDirectory\Group;
use LdapRecord\Models\ActiveDirectory\User;
use LdapRecord\Utilities;
use utilphp\util;

/**
 *
 * @author cheat
 *        
 */
class LdapCustomFunctions
{
    /**
     * Compare 2 DNs. S'ils sont équivalent, retourne 0, otherwise,
     * returns their sorting order (similar to strcmp()):
     *      Returns < 0 if dn1 is less than dn2.
     *      Returns > 0 if dn1 is greater than dn2.
     *
     * The comparison is performed starting with the top-most element
     * of the DN. Thus, the following list:
     *    <code>
     *       ou=people,dc=example,dc=com
     *       cn=Admin,ou=People,dc=example,dc=com
     *       cn=Joe,ou=people,dc=example,dc=com
     *       dc=example,dc=com
     *       cn=Fred,ou=people,dc=example,dc=org
     *       cn=Dave,ou=people,dc=example,dc=org
     *    </code>
     * Will be sorted thus using usort( $list, "pla_compare_dns" ):
     *    <code>
     *       dc=com
     *       dc=example,dc=com
     *       ou=people,dc=example,dc=com
     *       cn=Admin,ou=People,dc=example,dc=com
     *       cn=Joe,ou=people,dc=example,dc=com
     *       cn=Dave,ou=people,dc=example,dc=org
     *       cn=Fred,ou=people,dc=example,dc=org
     *    </code>
     *
     * @param string The first of two DNs to compare
     * @param string The second of two DNs to compare
     * @return int
     */
    public function compareDns($dn1, $dn2)
    {
        # If pla_compare_dns is passed via a tree, then we'll just get the DN part.
        if (is_array($dn1)){
            $dn1temp = implode('+',$dn1);
            if (isset($dn1['dn'])){
                $dn1temp = $dn1['dn'];
            }
            $dn1 = $dn1temp;
        }
            
        if (is_array($dn2)){
            $dn2temp = implode('+',$dn2);
            if (isset($dn2['dn'])){
                $dn2 = $dn2['dn'];
            }
            $dn2 = $dn2temp;
        }
                                
        # If they are obviously the same, return immediately
        if (! strcasecmp($dn1,$dn2)){
            return 0;
        }
        
        $explodeDn1 = self::explodeDnWithAttr($dn1);
        $explodeDn2 = self::explodeDnWithAttr($dn2);
        $dn1_parts = array_reverse($explodeDn1);
        $dn2_parts = array_reverse($explodeDn2);
        assert(is_array($dn1_parts));
        assert(is_array($dn2_parts));
        
        # Foreach of the "parts" of the smaller DN
        for ($i=0; $i < count($dn1_parts) && $i < count($dn2_parts); $i++) {
            /* dnX_part is of the form: "cn=joe" or "cn = joe" or "dc=example"
             ie, one part of a multi-part DN. */
            [$dn1SubPartAttr, $dn1SubPartVal] = DistinguishedName::explodeRdn($dn1_parts[$i]);
            [$dn2SubPartAttr, $dn2SubPartVal] = DistinguishedName::explodeRdn($dn2_parts[$i]);
            
            $cmpAttr = strcasecmp($dn1SubPartAttr, $dn2SubPartAttr);
            if (0 != $cmpAttr ){
                $result = $cmpAttr;
                break;
            }

            $cmpVal = strcasecmp($dn1SubPartVal, $dn2SubPartVal);
            if (0 != $cmpVal){
                $result = $cmpVal;
            }
        }
                                    
        /* If we iterated through all entries in the smaller of the two DNs
         (ie, the one with fewer parts), and the entries are different sized,
         then, the smaller of the two must be "less than" than the larger. */
        if (count($dn1_parts) > count($dn2_parts)) {
            $result = 1;            
        } elseif (count($dn2_parts) > count($dn1_parts)) {
            $result = -1;            
        }
        
        return $result;

    }
    
    /**
     * Explode a DN into an array of its RDN parts.
     *
     * NOTE: When a multivalue RDN is passed to ldap_explode_dn, the results returns with 'value + value';
     *
     * <code>
     *  Array (
     *    [0] => uid=ppratt
     *    [1] => ou=People
     *    [2] => dc=example
     *    [3] => dc=com
     *  )
     * </code>
     *
     * @param string The DN to explode.
     * @param int (optional) Whether to include attribute names (see http://php.net/ldap_explode_dn for details)
     * @return array An array of RDN parts of this format:
     */
    protected function explodeDnWithAttr(string $dn, int $with_attributes=0):array
    {      
        $dn = addcslashes($dn,'<>+";');
        $dn = DistinguishedName::make($dn);    
        
        # split the dn
        if($with_attributes){
            return DistinguishedName::values($dn->get());
        }
        return DistinguishedName::explode($dn->get());
    }
    
    private function isUserAuthorized(string $dn, Collection $oUserGroups, User$user):bool {
        $exists = false;
        
        foreach ($oUserGroups as $group){
            dump($group->getDn());
            $exists = stripos($group->getDn(), $dn ) !== false;
            if($exists){
                break;
            }
        }
        
        //Si le dn dde l'OU est présent dans celui de l'utilisateur 
        if(!$exists){
            $exists = stripos( strtolower($user->getDn()), $dn ) !== false;
        }
        return $exists;
    }
    
    /**
     * 
     * @param array|\LdapRecord\Models\Entry $nodelist ensemble des noeuds (OU, User, gorups) à parse
     * @param Tree $tree Arbre hiérarchique
     * @param User $user Utilisateur connecté
     */
    public function LdapDIT($nodelist, Tree $tree, User $user){
        $userGroups = $user->groups();
        $oUserGroups = $userGroups->get();

        foreach($nodelist as $node){

            if($node instanceof Entry){
                $dn = strtolower($node->getDn());
            }else {
                $dn = strtolower($node['dn']);
                /*$isAuthorized = $this->isUserAuthorized($dn, $oUserGroups, $user);
                
                if(!$isAuthorized){
                    continue;
                }*/
                $entry = $tree->getBaseEntries()[0];
                $entry->addChild($dn);
            }
            

            if(!$tree->getEntry($dn)){
                $tree->addEntry($dn);
            }
            
            $ou = OrganizationalUnit::find($dn);
            $descendantsOu = $ou->descendants()->get();
            $users = User::in($dn)->get();
            $groups = Group::in($dn)->get();
            
            /**
             * @var OrganizationalUnit $node 
             */

        //if($nodelist){
        /*    dump($tree);
        dump($node->getDn());*/
            //dump($dn);
            /**
             * @var TreeItem $entry
             */
            $entry = $tree->getEntry($dn);
            $entry->setParent( $tree->getBaseEntries()[0]->getName() );
            $ldapOuSanitized = util::sanitize_string($ou->getName());
            $entry->setSanitizedName($ldapOuSanitized);
            $entry->setDisplayName($ou->getName());
            //dump($ou->getName());
            $childCount = count($descendantsOu) + count($users) + count($groups);
            if($childCount){
                
                foreach($descendantsOu as $child){
                    
                    $entry->addChild(strtolower($child->getDn()));
                    if(!$tree->getEntry($child->getDn())){
                        $tree->addEntry($child->getDn());
                    }
                    
                    $groups = Group::in($child)->get();
                    $childCount += count($groups);
                    if(count($groups)){
                        
                        $entryDnGroup = $tree->getEntry($child->getDn());
                        foreach($groups as $group){
                            $entryDnGroup->addChild(strtolower($group->getDn()));
                            if(!$tree->getEntry($group->getDn())){
                                $tree->addEntry($group->getDn());
                            }
                            $entryGroup = $tree->getEntry($group->getDn());
                            $entryGroup->setDisplayName($group->getName());
                            $entryGroup->setLeaf();
                        } 
                        
                    }
                    $users = User::in($child)->get();
                    $childCount += count($users);
                    if(count($users)){
                        
                        $entryDnUser = $tree->getEntry($child->getDn());
                        foreach($users as $user){
                            $entryDnUser->addChild(strtolower($user->getDn()));
                            if(!$tree->getEntry($user->getDn())){
                                $tree->addEntry($user->getDn());
                            }
                            $entryUser = $tree->getEntry($user->getDn());
                            $entryUser->setDisplayName($user->getName());
                            $entryUser->setLeaf();
                        }
                        
                    }
                }
                
                $this->LdapDIT($descendantsOu, $tree, $user);
            }
        }
        
        
    }
    /**
     *
     * @param array|\LdapRecord\Models\Entry $nodelist ensemble des noeuds (OU, User, gorups) à parse
     * @param Tree $tree Arbre hiérarchique
     * @param User $user Utilisateur connecté
     */
    public function requestedDN($nodelist, Tree $tree, User $user){
        $userGroups = $user->groups();
        $oUserGroups = $userGroups->get();
        
        foreach($nodelist as $node){
            
            if($node instanceof Entry){
                $dn = strtolower($node->getDn());
            }else {
                $dn = strtolower($node['dn']);
                /*$isAuthorized = $this->isUserAuthorized($dn, $oUserGroups, $user);
                
                if(!$isAuthorized){
                continue;
                }*/
                $entry = $tree->getBaseEntries()[0];
                $entry->addChild($dn);
            }
            
            
            if(!$tree->getEntry($dn)){
                $tree->addEntry($dn);
            }
            
            $ou = OrganizationalUnit::find($dn);
            $descendantsOu = $ou->descendants()->get();
            $users = User::in($dn)->get();
            $groups = Group::in($dn)->get();
            
            /**
             * @var OrganizationalUnit $node
             */
            
            //if($nodelist){
            /*    dump($tree);
             dump($node->getDn());*/
            //dump($dn);
            /**
             * @var TreeItem $entry
             */
            $entry = $tree->getEntry($dn);
            $entry->setParent( $tree->getBaseEntries()[0]->getName() );
            $ldapOuSanitized = util::sanitize_string($ou->getName());
            $entry->setSanitizedName($ldapOuSanitized);
            $entry->setDisplayName($ou->getName());
            //dump($ou->getName());
            $childCount = count($descendantsOu) + count($users) + count($groups);
            if($childCount){
                
                foreach($descendantsOu as $child){
                    
                    $entry->addChild(strtolower($child->getDn()));
                    if(!$tree->getEntry($child->getDn())){
                        $tree->addEntry($child->getDn());
                    }
                    
                    $groups = Group::in($child)->get();
                    $childCount += count($groups);
                    if(count($groups)){
                        
                        $entryDnGroup = $tree->getEntry($child->getDn());
                        foreach($groups as $group){
                            $entryDnGroup->addChild(strtolower($group->getDn()));
                            if(!$tree->getEntry($group->getDn())){
                                $tree->addEntry($group->getDn());
                            }
                            $entryGroup = $tree->getEntry($group->getDn());
                            $entryGroup->setDisplayName($group->getName());
                            $entryGroup->setLeaf();
                        }
                        
                    }
                    $users = User::in($child)->get();
                    $childCount += count($users);
                    if(count($users)){
                        
                        $entryDnUser = $tree->getEntry($child->getDn());
                        foreach($users as $user){
                            $entryDnUser->addChild(strtolower($user->getDn()));
                            if(!$tree->getEntry($user->getDn())){
                                $tree->addEntry($user->getDn());
                            }
                            $entryUser = $tree->getEntry($user->getDn());
                            $entryUser->setDisplayName($user->getName());
                            $entryUser->setLeaf();
                        }
                        
                    }
                }
                
                $this->LdapDIT($descendantsOu, $tree, $user);
            }
        }
        
        
    }
}

