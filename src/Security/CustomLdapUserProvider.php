<?php
namespace App\Security;

use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Ldap\Security\LdapUser;
use Symfony\Component\Ldap\Exception\ConnectionException;
use Symfony\Component\Ldap\Exception\ExceptionInterface;
use Symfony\Component\Ldap\LdapInterface;
use Symfony\Component\Security\Core\Exception\InvalidArgumentException;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use LdapRecord\Container;
use LdapRecord\Ldap;
use LdapRecord\Connection;
use LdapRecord\Models\Entry;
use LdapRecord\Models\ActiveDirectory\User;
/**
 *
 * @author cheat
 *        
 */
class CustomLdapUserProvider implements UserProviderInterface, PasswordUpgraderInterface
{
    
    private $host;
    private $baseDn;
    private $searchDn;
    private $searchPassword;
    private $defaultRoles;
    private $uidKey;
    private $defaultSearch;
    private $passwordAttribute;
    private $extraFields;
    
    public function __construct(string $host, string $baseDn, string $searchDn = null, string $searchPassword = null, array $defaultRoles = [], string $uidKey = null, string $filter = null)
    {
        if (null === $uidKey) {
            $uidKey = 'sAMAccountName';
        }
        
        if (null === $filter) {
            $filter = '({uid_key}={user_identifier})';
        }
        
        $this->host = $host;
        $this->baseDn = $baseDn;
        $this->searchDn = $searchDn;
        $this->searchPassword = $searchPassword;
        $this->defaultRoles = $defaultRoles;
        $this->uidKey = $uidKey;
        $this->defaultSearch = str_replace('{uid_key}', $uidKey, $filter);
    }
    
    public function setPasswordAttribute(string $passwordAttibute)
    {
        $this->passwordAttribute = $passwordAttibute;
    }
    
    /**
     * {@inheritdoc}
     */
    public function loadUserByUsername(string $username)
    {
        trigger_deprecation('symfony/ldap', '5.3', 'Method "%s()" is deprecated, use loadUserByIdentifier() instead.', __METHOD__);
        
        return $this->loadUserByIdentifier($username);
    }
    
    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $connection = new Connection([
            'hosts' => [$this->host],
            'port' => 389,
            'base_dn' => $this->baseDn,
            'username' => $this->searchDn,
            'password' => $this->searchPassword
        ]);
        $credentials = explode(',',$identifier);
        // Add the connection into the container:
        Container::addConnection($connection);
        try {            
            $query = str_replace(['{username}', '{user_identifier}'], $credentials[0], $this->defaultSearch);
            $queryConnection = $connection->query();
            $search = $queryConnection->select()->rawFilter($query);
        } catch (ConnectionException $e) {
            $e = new UserNotFoundException(sprintf('User "%s" not found.', $credentials[0]), 0, $e);
            $e->setUserIdentifier($credentials[0]);
            
            throw $e;
        }
        
        $entries = $search->get();
        $count = \count($entries);
        
        if (!$count) {
            $e = new UserNotFoundException(sprintf('User "%s" not found.', $credentials[0]));
            $e->setUserIdentifier($credentials[0]);
            
            throw $e;
        }
        
        if ($count > 1) {
            $e = new UserNotFoundException('More than one user found.');
            $e->setUserIdentifier($credentials[0]);
            
            throw $e;
        }        

        $queryConnection->clearFilters();
        $entry = User::find($entries[0]['dn']);
        
        try {
            if (null !== $this->uidKey) {}
                $identifier = $entry->getAttributeValue($this->uidKey)[0];
            
        } catch (InvalidArgumentException $e) {
        }
        return new LdapUserFromLdapRecord($identifier, $entry);
    }
    
    public function refreshUser(UserInterface $user)
    {
        if (!$user instanceof LdapUserFromLdapRecord) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', get_debug_type($user)));
        }
        
        return new LdapUserFromLdapRecord($user->getUserIdentifier(), $user->getEntry());
    }
    
    /**
     * {@inheritdoc}
     *
     * @final
     */
    public function upgradePassword($user, string $newHashedPassword): void
    {
        /*if (!$user instanceof LdapUser) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', get_debug_type($user)));
        }
        
        if (null === $this->passwordAttribute) {
            return;
        }
        
        try {
            $user->getEntry()->setAttribute($this->passwordAttribute, [$newHashedPassword]);
            $this->ldap->getEntryManager()->update($user->getEntry());
            $user->setPassword($newHashedPassword);
        } catch (ExceptionInterface $e) {
            // ignore failed password upgrades
        }*/
    }
    
    /**
     * {@inheritdoc}
     */
    public function supportsClass(string $class)
    {
        return LdapUserFromLdapRecord::class === $class;
    }
    
    /**
     * Loads a user from an LDAP entry.
     *
     * @return UserInterface
     */
    protected function loadUser(string $identifier, Entry $entry)
    {        
        return new LdapUser($entry, $identifier, null, $this->defaultRoles, null);
    }
    
    private function getAttributeValue(Entry $entry, string $attribute)
    {
        if (!$entry->hasAttribute($attribute)) {
            throw new InvalidArgumentException(sprintf('Missing attribute "%s" for user "%s".', $attribute, $entry->getDn()));
        }
        
        $values = $entry->getAttribute($attribute);
        
        if (1 !== \count($values)) {
            throw new InvalidArgumentException(sprintf('Attribute "%s" has multiple values.', $attribute));
        }
        
        return $values[0];
    }
}

