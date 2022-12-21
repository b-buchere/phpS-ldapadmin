<?php
namespace App\Security\Voter;

use LdapRecord\Models\ActiveDirectory\Group;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use LdapRecord\Models\ActiveDirectory\User;
/**
 *
 * @author arvyn
 *        
 */
class UserVoter extends Voter
{
    const EDIT = 'edit';
    const CREATE = 'create';

    /**
     * (non-PHPdoc)
     *
     * @see \Symfony\Component\Security\Core\Authorization\Voter\Voter::supports()
     */
    protected function supports(string $attribute, $subject):bool
    {

        return in_array($attribute, [self::CREATE, self::EDIT]);
        
    }

    /**
     * (non-PHPdoc)
     *
     * @see \Symfony\Component\Security\Core\Authorization\Voter\Voter::voteOnAttribute()
     */
    protected function voteOnAttribute($attribute, $subject, TokenInterface $token)
    {
        
        $user = $token->getUser();
        // if the user is anonymous, do not grant access
        if (!$user instanceof UserInterface) {
            return false;
        }
        
        switch ($attribute) {
            case self::CREATE:
                $groupLdapAdmin = Group::find('CN=Administrators,CN=Builtin,DC=ncunml,DC=ass');
                $groupAppliAdmin = Group::find('CN=GG_ADMIN,OU=Groupes,OU=ALPHA-ORIONIS,DC=ncunml,DC=ass');
                $userEntry = $user->getEntry();

                return $userEntry->groups()->exists($groupLdapAdmin) || $userEntry->groups()->exists($groupAppliAdmin);
                break;
            case self::EDIT:
                break;
        }
        
    }
}

