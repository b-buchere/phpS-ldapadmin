<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Form\LdapGetinfoType;
use App\Form\PasswordChangeRequestType;
use App\Twig\HeaderExtension;
use Symfony\Component\Ldap\Ldap;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mailer\Mailer;
//use Symfony\Component\Ldap\Entry;
use LdapRecord\Query\Filter\Parser;
use LdapRecord\Container;
use LdapRecord\Connection;
use LdapRecord\Models\Entry;
use LdapRecord\Models\ActiveDirectory\User;
use LdapRecord\Models\Attributes\DistinguishedName;
use LdapRecord\Utilities;
use LdapRecord\Models\ActiveDirectory\Group;
/**
 * @Route("/ldapgetinfo", name="ldapgetinfo_")
 */
class LdapGetinfoController extends BaseController
{
    /**
     * @var HeaderExtension $headerExt
     */  
    protected HeaderExtension $headerExt;
    
    public function __construct( HeaderExtension $headerExt ){
        $this->headerExt = $headerExt;
    }
    
    //public
    public function initHtmlHead(){
        parent::initHtmlHead();
        $this->headerExt->headScript->appendFile('https://code.jquery.com/jquery-3.6.0.min.js');
         $this->headerExt->headScript->appendFile('https://code.jquery.com/ui/1.12.1/jquery-ui.min.js');
         $this->headerExt->headScript->appendFile('/js/bootstrap.bundle.min.js');

        
        $this->headerExt->headLink->appendStylesheet("/css/bootstrap.min.css");
        
        $this->headerExt->headMeta->appendName('robots', 'noindex, nofollow');
    }
    
    /**
     * @Route("/", name="index")
     */
    public function index(Request $request): Response
    {       
        $this->initHtmlHead();
        $this->headerExt->headScript->appendFile("/bundles/datatables/datatables.min.js");
        $this->headerExt->headLink->appendStylesheet("/bundles/datatables/datatables.min.css");
        
        $form = $this->createForm(LdapGetinfoType::class, null);
        
        $form->handleRequest($request);
        
        $aParsedResult = [];
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $user = $data['User'];
            
            $server = $this->getParameter('ldap_server');
            $dn = $this->getParameter('ad_base_dn');
            $user_admin = $this->getParameter('ad_passwordchanger_user');
            $user_pwd = $this->getParameter('ad_passwordchanger_pwd');
            
            // Create a new connection:
            $connection = new Connection([
                'hosts' => [$server],
                'port' => 389,
                'base_dn' => $dn,
                'username' => $user_admin,
                'password' => $user_pwd,
            ]);
                        
            // Add the connection into the container:
            Container::addConnection($connection);
            // connexion � un compte pour la lecture de l'annuaire
            
            $query = $connection->query();

            $resultsGroup = $query->select()->rawFilter("(&(objectCategory=group)(cn=*$user*))")->get();
            $query->clearFilters();
            $resultsUser  = $query->select()->rawFilter("(&(objectCategory=user)(|(samaccountname=$user)(objectguid=$user)(cn=$user)))")->get();

            $aParsedResult = [];
            
            //Parsage resultat catégorie utilisateur
            if( !empty($resultsUser) )
            {                
                
                foreach($resultsUser as $result){
                    $user = User::find($result['dn']);
                    
                    if(!array_key_exists('memberof', $result)){
                        continue;
                    }

                    unset($result['memberof']['count']);
                    
                    foreach($result['memberof'] as $group){
                        $aParsedResultTemp = [];
                        $aParsedResultTemp['name']  = $user->getName();
                        $aParsedResultTemp['UID']   = $user->getConvertedGuid();
                        $aParsedResultTemp['group'] = DistinguishedName::make($group)->name();
                        $aParsedResult[] = $aParsedResultTemp;
                    }
                    
                }
            }else{ //Parsage résultat catégorie Groupe
                
                foreach($resultsGroup as $result){
                    $ou = Group::find($result['dn']);
                    unset($result['member']['count']);
                    
                    foreach($result['member'] as $member){
                        $aParsedResultTemp = [];
                        $user = User::find($member);
                        $aParsedResultTemp['name']  = DistinguishedName::make($member)->name();
                        $aParsedResultTemp['UID']   = $user->getConvertedGuid();
                        $aParsedResultTemp['group'] = $ou->getName();
                        $aParsedResult[] = $aParsedResultTemp;
                    }                    
                }
            }
        }
        return $this->render('ldap/index.html.twig', [
            'form'=>$form->createView(),
            'results'=> $aParsedResult
        ]);

    }
    
    /**
     * @Route("/getinfo", name="navbar")
     */
    public function navbar(Request $request): Response
    {
        $this->initHtmlHead();
        
        $this->headerExt->headLink->appendStylesheet("/css/sidebar.css");
        $this->headerExt->headLink->appendStylesheet("/css/all.min.css");
        $this->headerExt->headLink->appendStylesheet("/css/custom.css");
        
        $this->headerExt->headScript->appendFile("/bundles/datatables/datatables.min.js");
        $this->headerExt->headLink->appendStylesheet("/bundles/datatables/datatables.min.css");
        
        $form = $this->createForm(LdapGetinfoType::class, null);
        
        $form->handleRequest($request);
        
        $aParsedResult = [];
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $user = $data['User'];
            
            $server = $this->getParameter('ldap_server');
            $dn = $this->getParameter('ad_base_dn');
            $user_admin = $this->getParameter('ad_passwordchanger_user');
            $user_pwd = $this->getParameter('ad_passwordchanger_pwd');
            
            // Create a new connection:
            $connection = new Connection([
                'hosts' => [$server],
                'port' => 389,
                'base_dn' => $dn,
                'username' => $user_admin,
                'password' => $user_pwd,
            ]);
            
            // Add the connection into the container:
            Container::addConnection($connection);
            // connexion � un compte pour la lecture de l'annuaire
            
            $query = $connection->query();
            
            $resultsGroup = $query->select()->rawFilter("(&(objectCategory=group)(cn=*$user*))")->get();
            $query->clearFilters();
            $resultsUser  = $query->select()->rawFilter("(&(objectCategory=user)(|(samaccountname=$user)(objectguid=$user)(cn=$user)))")->get();
            
            $aParsedResult = [];
            
            //Parsage resultat catégorie utilisateur
            dump($resultsUser);
            if( !empty($resultsUser) )
            {
                
                foreach($resultsUser as $result){
                    $user = User::find($result['dn']);
                    
                    $aParsedResultTemp = [];
                    $aParsedResultTemp['name']  = $user->getName();
                    $aParsedResultTemp['UID']   = $user->getConvertedGuid();
                    $aParsedResultTemp['group'] = '';
                    
                    if(array_key_exists('memberof', $result)){
                        unset($result['memberof']['count']);
                        
                        foreach($result['memberof'] as $group){
                            
                            $aParsedResultTemp['group'] .= DistinguishedName::make($group)->name().', ';
                            
                        }
                        $aParsedResultTemp['group'] = substr($aParsedResultTemp['group'], 0, -2);
                    }
                    

                    $aParsedResult[] = $aParsedResultTemp;
                    dump($aParsedResult);
                }
                
            }else{ //Parsage résultat catégorie Groupe
                
                foreach($resultsGroup as $result){
                    $ou = Group::find($result['dn']);
                    unset($result['member']['count']);
                    
                    foreach($result['member'] as $member){
                        $aParsedResultTemp = [];
                        $user = User::find($member);
                        $aParsedResultTemp['name']  = DistinguishedName::make($member)->name();
                        $aParsedResultTemp['UID']   = $user->getConvertedGuid();
                        $aParsedResultTemp['group'] = $ou->getName();
                        $aParsedResult[] = $aParsedResultTemp;
                    }
                }
            }
        }
        dump($aParsedResult);
        return $this->render('ldap/navbar.html.twig', [
            'activeMenu'=>'',
            'results'=> $aParsedResult
        ]);
        
    }
}
