<?php
namespace App\Command;

use LdapRecord\Models\ActiveDirectory\OrganizationalUnit;
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
use utilphp\util;
use App\Entity\Groupes;
use App\Entity\Utilisateurs;
use LdapRecord\Models\ActiveDirectory\Group;
use App\Services\Webservice;
/**
 *
 * @author Arvyn
 *        
 */

#[AsCommand(name: 'ldap:user:sync')]
class LdapImporCRMCommand extends Command
{
    protected static $defaultName = 'ldap:import:crm';
    private $cacheDir;
    private $container;
    private $ws;
    
    public function __construct(KernelInterface $kernel, Webservice $ws)
    {
        parent::__construct();
        $this->cacheDir = $kernel->getCacheDir();
        $this->container =$kernel->getContainer();
        
        $this->ws = $ws;
    }
    
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $ws = $this->ws;
        $content = $ws->request();
        
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
            'password' => $user_pwd,
            'version'  => 3,
            'use_tls'  => true,
            'follow_referrals' => false,
        ]);
        
        $connection->connect();
        
        Container::addConnection($connection);
        
        $aOu = [];
        foreach( $content['results'] as $line ){
            
            $ouValue = str_pad($line['id'],3, "0", STR_PAD_LEFT)."-".$line["structureTypeLibelle"]."";
            if( substr($line["structureTypeLibelle"],-1) !==" " && $line["nomStructure"][0] !== " "){
                $ouValue .=  " ";
            }
            $ouValue .= $line["nomStructure"];
            if(!isset($line['nomRegion'])){
                $line['nomRegion'] = "";
            }
            
            $nomRegionLdapModel = ldap_escape(strtoupper( util::remove_accents($line['nomRegion']) ) );
            if(empty($nomRegionLdapModel)){
                $nomRegionLdapModel = "SANS REGION";
            }
            
            $aOu[$nomRegionLdapModel][] = ['name'=>ldap_escape($ouValue), 'anneeAdhesion'=>$line['anneeAdhesion'], 'contacts'=>$line['contacts']];
            
        }
        
        /*$ouTest = OrganizationalUnit::find("ou=Import Test,dc=ncunml,dc=ass");
        if( is_null($ouTest) ){
            $ouTest = new OrganizationalUnit();
            $ouTest->setDn("ou=Import Test,dc=ncunml,dc=ass");
            $ouTest->save();
        }*/
        
        foreach ($aOu as $ouRegion=>$aStructure){
            
            $dnRegion = "ou=".$ouRegion.",dc=ncunml,dc=ass";
            $regionLdap = OrganizationalUnit::find($dnRegion);
            if( is_null($regionLdap) ){
                $regionLdap = new OrganizationalUnit();
                $regionLdap->setDn($dnRegion);
                $regionLdap->save();
            }
            foreach( $aStructure as $structure ){
                $dnStructure = "ou=".$structure['name'].",".$dnRegion;
                $structureLdap = OrganizationalUnit::find($dnStructure);
                if( is_null($structureLdap) ){
                    $structureLdap = new OrganizationalUnit();
                    $structureLdap->setDn($dnStructure);
                    $structureLdap->save();
                }
                
                foreach($structure['contacts'] as $contact){
                    if( in_array($contact['contactTypeId'],[1,2]) ){
                        $unescapedStruct = preg_replace_callback(
                            "/\\\\[\da-z]{2}/",
                            function ($matches) {
                                $match = array_shift($matches);
                                return hex2bin(substr($match, 1));
                            },
                            $structure['name']
                            );
                        
                        $unescapedRegion = preg_replace_callback(
                            "/\\\\[\da-z]{2}/",
                            function ($matches) {
                                $match = array_shift($matches);
                                return hex2bin(substr($match, 1));
                            },
                            $ouRegion
                            );
                        if( empty($contact['prenom']) || empty($contact['nom'])){                            
                            file_put_contents('erreur_import.csv', $unescapedRegion.";".$unescapedStruct.";".$contact['prenom'].";".$contact['nom'].";".$contact['email']."\r\n", FILE_APPEND);
                            continue;
                        }
                        
                        $dnUser= "cn=".$contact['prenom'].' '.$contact['nom'].','.$dnStructure;

                        
                        echo strtolower(trim($contact['prenom'])[0]).strtolower(trim(util::sanitize_string($contact['nom'])))."\r\n";
                        $userLdap = User::where("samaccountname", "=", strtolower(trim($contact['prenom'])[0]).strtolower(trim(util::sanitize_string($contact['nom']))))->first();
                        
                        if( is_null($userLdap) ){
                            
                            $userLdap = new User();
                            $userLdap->setDn($dnUser);
                            $userLdap->samaccountname = strtolower(trim($contact['prenom'])[0]).strtolower(trim(util::sanitize_string($contact['nom'])));
                            
                            //dump($userLdap->samaccountname);
                            $userLdap->userAccountControl = 512;
                            $userLdap->pwdlastset = 0;
                            $userLdap->unicodePwd = '';
                            
                            $userLdap->givenName = util::remove_accents($contact['prenom']);
                            $userLdap->userPrincipalName=$userLdap->samaccountname[0]."@ncunml.ass";
                            
                            $userLdap->sn = util::remove_accents($contact['nom']);

                        }/*else{
                            file_put_contents('utilisateurs_existants.csv', $unescapedRegion.";".$unescapedStruct.";".$contact['prenom'].";".$contact['nom'].";".$contact['email']."\r\n", FILE_APPEND);
                        }*/
                        $userLdap->givenName = util::remove_accents($contact['prenom']);
                        if(!empty($contact['email'])){
                            $userLdap->mail = $contact['email'];
                        }
                        
                        $anneep1 = mktime(23,59,59,03,31,$structure['anneeAdhesion']+1);
                        $userLdap->accountExpires = ($anneep1+ 11644473600) * 10000000;
                        $userLdap->save();
                        
                        $dnGroup = 'cn=WB_ADHERENT,ou=Groups,ou=TRANSVERSE,'.$dn;
                        
                        $group = Group::find($dnGroup);
                        
                        if(is_null($group)){
                            $group = new Group();
                            $group->setDn($dnGroup);
                            $group->save();
                        }
                        
                        $userLdap->groups()->attach($group);
                        
                    }
                }
            }
        }
        
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
        ->setHelp('Cette commande importe les structure et les utilisateurs')
        ;
    }
}

