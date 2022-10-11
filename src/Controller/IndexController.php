<?php
namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Twig\HeaderExtension;
use Symfony\Component\Ldap\Ldap;
use LdapRecord\Query\Filter\Parser;
use LdapRecord\Container;
use LdapRecord\Connection;
use LdapRecord\Models\Entry;
use LdapRecord\Models\ActiveDirectory\User;
use LdapRecord\Models\Attributes\DistinguishedName;
use LdapRecord\Utilities;
use LdapRecord\Models\ActiveDirectory\Group;
use App\Form\LdapGetinfoType;

class IndexController extends BaseController
{
    
    public function __construct( HeaderExtension $headerExt ){
        $this->headerExt = $headerExt;
    }
    
    //public     
    public function initHtmlHead(){
		
        parent::initHtmlHead();
        $this->headerExt->setWebroot($this->getParameter('site_base_url'));
        //$this->headerExt->headLink->appendAlternate("/favicon.ico", 'image/vnd.microsoft.icon', null, ['rel'=>"shortcut icon"] );
		$this->headerExt->headScript->appendFile('https://code.jquery.com/jquery-3.6.0.min.js');
        $this->headerExt->headScript->appendFile('https://code.jquery.com/ui/1.12.1/jquery-ui.min.js');
        $this->headerExt->headScript->appendFile('/js/bootstrap.bundle.min.js');
		
		$this->headerExt->headLink->appendStylesheet("/css/bootstrap.min.css");
        $this->headerExt->headMeta->appendName('robots', 'noindex, nofollow');
    }
 
    /**
     * @Route("/", name="index", condition="not (context.getHost() matches '/search\.unml\.info/')")
     */
    public function index(): Response
    {      
        return $this->render('index.html.twig', [
        ]);
    }
	
	/**
     * @Route("/", name="indexSearch", condition="context.getHost() matches '/search\.unml\.info/'")
     */
    public function indexSearch(Request $request): Response
    {      
        $this->initHtmlHead();
        $this->headerExt->headScript->appendFile("/bundles/datatables/datatables.min.js");
        $this->headerExt->headLink->appendStylesheet("/bundles/datatables/datatables.min.css");
        
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        
        $form = $this->createForm(LdapGetinfoType::class, null);
        
        $form->handleRequest($request);

        $aParsedResult = [];
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $user = $data['User'];
            
            $server = $this->getParameter('ldap_server');
            //$dn = "OU=Utilisateurs,OU=ALPHA-ORIONIS,DC=ncunml,DC=ass";
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

            
            $query = $connection->query();

            $resultsGroup = $query->select()->rawFilter("(&(objectCategory=group)(cn=*$user*))")->get();

            $query->clearFilters();
            $resultsUser  = $query->select()->rawFilter("(&(objectCategory=user)(|(samaccountname=$user)(objectguid=$user)(cn=*$user*)))")->get();

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
}

