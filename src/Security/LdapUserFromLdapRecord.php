<?php
namespace App\Security;

use LdapRecord\Models\Entry;
use Symfony\Component\Ldap\Security\LdapUser;
use Symfony\Component\Security\Core\User\EquatableInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 *
 * @author cheat
 *        
 */
class LdapUserFromLdapRecord implements UserInterface, PasswordAuthenticatedUserInterface, EquatableInterface
{
    private $entry;
    private $username;
    private $password;
    private $roles;
    private $extraFields;
    
    public function __construct(string $username, $password, Entry $entry)
    {
        if (!$username) {
            throw new \InvalidArgumentException('The username cannot be empty.');
        }
        
        $this->entry = $entry;
        $this->username = $username;
        $this->password = $password;
        $this->roles = ['ROLE_ALLOWED_TO_SWITCH'];
        $this->extraFields = [];
    }
    
    public function getEntry(): Entry
    {
        return $this->entry;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getRoles(): array
    {
        return $this->roles;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getSalt(): ?string
    {
        return null;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getUsername(): string
    {
        trigger_deprecation('symfony/ldap', '5.3', 'Method "%s()" is deprecated and will be removed in 6.0, use getUserIdentifier() instead.', __METHOD__);
        
        return $this->username;
    }
    
    public function getUserIdentifier(): string
    {
        return $this->username;
    }
    
    /**
     * {@inheritdoc}
     */
    public function eraseCredentials()
    {
        $this->password = null;
    }
    
    public function getExtraFields(): array
    {
        return $this->extraFields;
    }
    
    public function setPassword(string $password)
    {
        $this->password = $password;
    }
    
    /**
     * {@inheritdoc}
     */
    public function isEqualTo(UserInterface $user): bool
    {
        if (!$user instanceof self) {
            return false;
        }
        
        if ($this->getPassword() !== $user->getPassword()) {
            return false;
        }
        
        if ($this->getSalt() !== $user->getSalt()) {
            return false;
        }
        
        if ($this->getUserIdentifier() !== $user->getUserIdentifier()) {
            return false;
        }
        
        return true;
    }
}

