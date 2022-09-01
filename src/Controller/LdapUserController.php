<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Form\LdapGetinfoType;
use App\Form\LdapGroupcreateType;
use App\Form\LdapOucreateType;
use App\Form\LdapUserCreateType;
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
use Translation\Extractor\Visitor\Php\Symfony\FlashMessage;
/**
 * @Route("/ldapadmin", name="ldapadmin_")
 */
class LdapUserController extends BaseController
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
        //$this->headerExt->headLink->appendStylesheet('/fontawesome/css/all.css');
        //$this->headerExt->headLink->appendStylesheet('/css/global.css');
        //$this->headerExt->headLink->appendStylesheet('https://fonts.googleapis.com/css2?family=Oswald:wght@200;300;400;500;600;700');
        
        $this->headerExt->headMeta->appendName('robots', 'noindex, nofollow');
    }
    
    /**
     * @Route("/", name="user")
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

        dump($this->getUser());
        return $this->render('ldap/admin/users.html.twig', [
            'user' => $this->getUser()
        ]);

    }

    /**
     * @Route("/usergroupupdate", name="usergroupupdate")
     */
    public function userGroupUpdate(Request $request): Response
    {
        $this->initHtmlHead();
        
        $form = $this->createForm(LdapUserGroupUpdateType::class, null);
        
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            /**
             * @var UploadedFile $file
             */
            $file = $data['fileimport'];
            $file->move('../uploads', 'usergroup.csv');
            
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
            
            $csv = Reader::createFromPath('../uploads/usergroup.csv', 'r');
            $csv->setDelimiter(";");
            $csv->setHeaderOffset(0); //set the CSV header offset
            
            $records = $csv->getRecords();
            $utilphp = new util();
            $noUser = [];
            $error = false;
            foreach ($records as $record) {
                
                $user = User::findBy('samaccountname', strtolower($record['Prenom'][0]).strtolower($utilphp->sanitize_string($record['Nom'])));
                $group = Group::find('cn='.$record['Groupe'].',ou=Groups,ou=TRANSVERSE,dc=ncunml,dc=ass');
                
                if(!is_null($user)){                    
                    try {
                        if(is_null($group)){
                            $group = (new Group)->inside('ou=Groups,ou=TRANSVERSE,dc=ncunml,dc=ass');
                            $group->cn = $record['Groupe'];
                            $group->save();
                        }
                        if ($user->groups()->attach($group)) {
                            // Successfully added the group to the user.
                        }
                        
                        $user->save();
                    } catch (\LdapRecord\LdapRecordException $e) {
                        // Failed saving user.
                    }
                }else{
                    $error =true;
                    $noUser[] = $record['Prenom'].' '.$record['Nom'];                    
                }
            }
            
            if(!empty($noUser)){
                $this->addFlash('danger', "Le ou les utilisateur.s suivant.s n'existe.nt pas : <br/>". implode('<br/>',$noUser));
                
            }
            unlink('../uploads/usergroup.csv');
            
            $this->addFlash('info', "Les utilisateurs ont été mis à jour");
            if(!$error){
                return new Response(
                    ["type"=>"success"],
                    Response::HTTP_OK,
                    ['content-type' => 'text/html']
                );
            }
        }
        
        return $this->render('ldap/admin/userbulk.html.twig', [
            'form'=>$form->createView(),
            'title'=>"userGroupImport"
        ]);
    }
    
    /**
     * @Route("/userbulk", name="userbulk")
     */
    public function bulkUser(Request $request): Response
    {
        $this->initHtmlHead();
        
        $form = $this->createForm(LdapUserbulkType::class, null);
        
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            /**
             * @var UploadedFile $file
             */
            $file = $data['fileimport'];
            $file->move('../uploads', 'user.csv');

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
            
            $csv = Reader::createFromPath('../uploads/user.csv', 'r');
            $csv->setDelimiter(";");
            $csv->setHeaderOffset(0); //set the CSV header offset
            
            $records = $csv->getRecords();
            $utilphp = new util();
            foreach ($records as $record) {
                
                $user = User::findBy('samaccountname', strtolower($record['Prenom'][0]).strtolower($utilphp->sanitize_string($record['Nom'])));

                if(is_null($user)){
                    $user = (new User)->inside('ou='.$record['Structure'].',ou='.$record['Region'].','.$dn);
                    $user->cn = $record['Prenom'].' '.$record['Nom'];
                    $user->unicodePwd = '';
                    $user->samaccountname = strtolower($record['Prenom'][0]).strtolower($utilphp->sanitize_string($record['Nom']));
                    $user->mail = $record['email'];
                    $user->userAccountControl = 512;
                    $user->pwdlastset = 0;
                    
                    try {
                        $user->save();
                    } catch (\LdapRecord\LdapRecordException $e) {
                        // Failed saving user.
                    }
                }else{
                    $this->addFlash("error", "userAlreadyExists");
                }
                
            }
            
            unlink('../uploads/user.csv');
        }
        
        
        
        return $this->render('ldap/admin/userbulk.html.twig', [
            'form'=>$form->createView(),
            'title'=>"userImport"
        ]);
    }
    
    /**
     * @Route("/usercreate", name="usercreate")
     */
    public function create(Request $request): Response
    {
        $this->initHtmlHead();        
        $this->headerExt->headScript->appendFile('/js/ldapcreateuser.js');
        
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
                        ->in($dn)
                        ->rawFilter('(objectCategory=organizationalUnit)')
                        ->listing()->get();
    
        $aRegion = [''=>''];
        foreach($nodeList as $node){
            $aRegion[$node['ou'][0]] = $node['dn'];
        }
        dump($aRegion);
        
        $form = $this->createForm(LdapUserCreateType::class, null, [
            'regions'=>$aRegion
        ]);
        
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $utilphp = new util();

            $user = User::findBy('samaccountname', strtolower($form->get('firstname')->getData()[0]).strtolower($utilphp->sanitize_string($form->get('lastname')->getData())));
            
            if(is_null($user)){
                $user = (new User)->inside($form->get('structure')->getData());
                $user->cn = $form->get('firstname')->getData().' '.$form->get('lastname')->getData();
                $user->unicodePwd = '';
                $user->samaccountname = strtolower($form->get('firstname')->getData()[0]).strtolower($utilphp->sanitize_string($form->get('lastname')->getData()));
                $user->mail = $form->get('mail')->getData();
                $user->userAccountControl = 512;
                $user->pwdlastset = 0;
                
                try {
                    $user->save();
                } catch (\LdapRecord\LdapRecordException $e) {
                    // Failed saving user.
                }
            }else{
                $this->addFlash("error", "userAlreadyExists");
            }

        }
        
        
        
        return $this->render('ldap/admin/usercreate.html.twig', [
            'form'=>$form->createView(),
            'title'=>"userImport"
        ]);
    }
}
