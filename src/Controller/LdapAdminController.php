<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Form\LdapGetinfoType;
use App\Form\LdapGroupcreateType;
use App\Form\LdapOucreateType;
use App\Form\LdapUserGroupUpdateType;
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
use LdapRecord\Models\ActiveDirectory\OrganizationalUnit;
use LdapRecord\Models\ActiveDirectory\User;
use LdapRecord\Models\Attributes\DistinguishedName;
use LdapRecord\Utilities;
use Symfony\Component\HttpClient\HttpClient;
use LdapRecord\Models\ActiveDirectory\Group;
use App\Services\HTMLTree;
use Symfony\Component\HttpFoundation\JsonResponse;
use PDO;
use App\Services\AJAXTree;
use LdapRecord\Auth\BindException;
use App\Services\TreeItem;
use App\Services\LdapCustomFunctions;
use App\Security\Voter\UserVoter;
use League\Csv\Reader;
use League\Csv\Statement;
use utilphp;
use utilphp\util;
use App\Form\LdapUserbulkType;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;

/**
 * @Route("/ldapadmin", name="ldapadmin_")
 */
class LdapAdminController extends BaseController
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
        $this->headerExt->headLink->appendStylesheet("/css/sidebar.css");
        $this->headerExt->headLink->appendStylesheet("/css/all.min.css");
        $this->headerExt->headLink->appendStylesheet("/css/custom.css");

        
        $this->headerExt->headMeta->appendName('robots', 'noindex, nofollow');
    }
    
    /**
     * @Route("/", name="index")
     */
    public function index(Request $request): Response
    {       

        $this->initHtmlHead();
        
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
        
        Container::addConnection($connection);
        
        $query = $connection->query();
        $resultsNode = $query->select()->rawFilter("(objectCategory=organizationalUnit)")->get();
        
        return $this->render('ldap/admin/index.html.twig', [
            'user' => $this->getUser(),
            'activeMenu'=>''
        ]);

    }
    
    /**
     * @Route("/tree", name="tree")
     */
    public function tree(Request $request, LdapCustomFunctions $ldapFunction): Response
    {
        $this->initHtmlHead();
        $server = $this->getParameter('ldap_server');
        $baseDn = $this->getParameter('ad_base_dn');
        $user_admin = $this->getParameter('ad_passwordchanger_user');
        $user_pwd = $this->getParameter('ad_passwordchanger_pwd');
        $requestedDn = $baseDn;
        if( !empty($request->query->get('dn')) ){
            $requestedDn = strtolower($request->query->get('dn'));
        }         
            
        $connection = new Connection([
            'hosts' => [$server],
            'port' => 389,
            'base_dn' => $baseDn,
            'username' => $user_admin,
            'password' => $user_pwd,
        ]);
        
        //Container::addConnection($connection);
        
        try {
            $connection->connect();
            
            Container::addConnection($connection);
            
            //echo "Successfully connected!";
        } catch (BindException $e) {
            $error = $e->getDetailedError();
            
            echo $error->getErrorCode();
            echo $error->getErrorMessage();
            echo $error->getDiagnosticMessage();
        }

        $tree = new AJAXTree($requestedDn);
        if (! $tree){
            die();
        }
        $query = $connection->query();
        $record = $query->find($requestedDn);
        if(!$record) {
            $response = new Response(
                "Ce Disntinguished Name (DN) n'existe pas",
                Response::HTTP_OK,
                ['content-type' => 'text/html']
            );
        }
        
        if ($requestedDn) {
            /**
             * @var TreeItem $entry
             */
            $entry = $tree->getEntry($requestedDn);
            
            if (! $entry) {
                
                $tree->addEntry($requestedDn);
                $entry = $tree->getEntry($requestedDn);
            }

            if ($baseDn == $requestedDn) {
                $entry->setBase();
            }
            
            $query->clearFilters();            

            if ($entry->isSizeLimited()) {
                
                $nodeList = $query->select(['dn','ou','namingContexts'])
                                  ->in($requestedDn)
                                  ->rawFilter('(|(objectCategory=user)(objectCategory=group)(objectCategory=organizationalUnit))')
                                  ->listing()->get();
                //$ldapFunction->LdapDIT($nodeList, $tree, $this->getUser()->getEntry());
                $ldapFunction->requestedDN($nodeList, $tree, $this->getUser()->getEntry());
                
            }

        }
        
        if ($requestedDn){
            //$dnTree = $entry->getChildren();
            $dnTree = $tree->getChildren($entry,0);
        } else {
            $tree->draw($request->query->get('noheader'));
        }
        
        return $this->render('ldap/admin/treeitem.html.twig', [
            'baseTree'=>$requestedDn,
            'tree'=>$dnTree,
            'user' => $this->getUser()
        ]);
    }
    
    /**
     * @Route("/oucreate", name="oucreate")
     */
    public function ouCreate(Request $request): Response
    {
        $form = $this->createForm(LdapOucreateType::class, null);
        
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            
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
            /*Container::addConnection($connection);
            // connexion � un compte pour la lecture de l'annuaire
            
            
            $transverseDn = "OU=Groups,OU=TRANSVERSE,DC=ncunml,DC=ass";
            /**
             * @var OrganizationalUnit $ou
             */
            
            /*$group = (new Group)->inside($transverseDn);
            $group->cn = 'GT_'.strtoupper( $data["groupName"] );
            $group->save();*/
        }
        return $this->render('ldap/admin/groupcreate.html.twig', [
            'form'=>$form->createView()
        ]);
    }
    
    /**
     * @Route("/getstructureoptions", methods={"POST"}, name="getstructureoptions")
     */
    public function getStructureOptions(Request $request): Response
    {
        $requestedDn = $request->request->get('dn');
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
            'use_tls'  => true
        ]);

        $connection->connect();
        
        Container::addConnection($connection);
        
        $query = $connection->query();
        $nodeList = $query->select(['dn','ou','namingContexts'])
                        ->in($requestedDn)
                        ->rawFilter('(objectCategory=organizationalUnit)')
                        ->listing()->get();
        
        $aStructure = [''=>''];
        foreach($nodeList as $node){
            $aStructure[$node['ou'][0]] = $node['dn'];
        }
        
        return $this->render('ldap/admin/structureoptions.html.twig', [
            "structures"=>$aStructure
        ]);
    }
}
