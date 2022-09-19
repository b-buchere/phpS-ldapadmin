<?php
namespace App\Command;

use LdapRecord\Models\ActiveDirectory\User;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use LdapRecord\Connection;
use LdapRecord\Container;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\Cache\ItemInterface;
/**
 *
 * @author Arvyn
 *        
 */

#[AsCommand(name: 'cache:ldap:user')]
class LdapCacheCommand extends Command
{
    protected static $defaultName = 'cache:ldap:user';
    private $cacheDir;
    private $container;
    
    public function __construct(KernelInterface $kernel)
    {
        parent::__construct();
        $this->cacheDir = $kernel->getCacheDir();
        $this->container =$kernel->getContainer();
    }
    
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $server = $this->container->getParameter('ldap_server');
        $dn = $this->container->getParameter('ad_base_dn');
        $user_admin = $this->container->getParameter('ad_passwordchanger_user');
        $user_pwd = $this->container->getParameter('ad_passwordchanger_pwd');
        
        // Create a new connection:
        $connection = new Connection([
            'hosts' => [$server],
            'port' => 389,
            'base_dn' => $dn,
            'username' => $user_admin,
            'password' => $user_pwd
        ]);
        
        $connection->connect();
        
        Container::addConnection($connection);
        
        $query = $connection->query();
        
        $nodeList = $query->select(['dn','ou','namingContexts'])
        ->rawFilter('(objectCategory=user)')
        ->get();
        
        $cache = new PhpFilesAdapter('ldap', 0, $this->cacheDir.'/pools');
        
        // The callable will only be executed on a cache miss.
        $cache->get('users', function (ItemInterface $item) use ($nodeList){
            $item->expiresAfter(null);
            
            $aLdapUsers = [];
            foreach($nodeList as $userNode){
                $aLdapUsers[] = User::find($userNode['dn']);
            }
            
            return $aLdapUsers;
        });
        
        // this method must return an integer number with the "exit status code"
        // of the command. You can also use these constants to make code more readable
        
        // return this if there was no problem running the command
        // (it's equivalent to returning int(0))
        return Command::SUCCESS;
        
        // or return this if some error happened during the execution
        // (it's equivalent to returning int(1))
        // return Command::FAILURE;
        
        // or return this to indicate incorrect command usage; e.g. invalid options
        // or missing arguments (it's equivalent to returning int(2))
        // return Command::INVALID
    }
    
    protected function configure(): void
    {
        $this
        // the command help shown when running the command with the "--help" option
        ->setHelp('Cette commande permet de générer le cache concernant les utilisateurs venant du LDAP')
        ;
    }
}

