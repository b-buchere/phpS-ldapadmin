<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
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
use utilphp\util;
use App\Form\LdapUserbulkType;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
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
            'activeMenu'=>"user_group_update",
            'title'=>"userGroupImport"
        ]);
    }
    
    /**
     * @Route("/userbulk", name="userbulk")
     */
    public function bulkUser(Request $request, LoggerInterface $logger, TranslatorInterface $tsl): Response
    {
        $this->initHtmlHead();
        $this->headerExt->headScript->appendFile('/js/ldapbulkuser.js');
        $form = $this->createForm(LdapUserbulkType::class, null, ['help_message'=>$tsl->trans("fileimportVerifyProgress")]);
        
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $logger->info($tsl->trans("bulUserProcessStart"));
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
            $lineError = [];
            $userExists = [];
            
            $csvHeader =$csv->getHeader();
            $userTotal = $csv->count();
            $userAdded = 0;
            $userError = 0;
            
            if(count($csvHeader)<5){
                $this->addFlash("danger", "fileError");
                $logger->error($tsl->trans("fileError"));
            }else{
                $logger->info($tsl->trans("userTotal").$userTotal);
                $session = $request->getSession();
                $session->set('importProgress', 0);
                $aSessionMessages=[];
                foreach ($records as $ligne => $record) {
                    $session->set('importProgress', floor($userTotal/$ligne*100));
                    
                    if( empty($record['Prenom'] || empty($record['Nom'])) ){
                        $lineError[] = $ligne+1;
                        $logger->error($tsl->trans("userAddError").implode(", ".$record));
                        $userError++;
                        $aSessionMessages[] = ['type'=>'danger','icon'=>'fa-sharp fa-solid fa-xmark','message'=>$tsl->trans("userLineNotAdded", ['line'=>$ligne])];
                        continue;
                    }
                    
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
                            $userAdded++;
                            $logger->info($tsl->trans("userAdded").$userAdded);
                        } catch (\LdapRecord\LdapRecordException $e) {
                            // Failed saving user.
                            $userError++;
                            $logger->error($tsl->trans("userAddError").implode(", ".$record));
                            $aSessionMessages[] = ['type'=>'danger','icon'=>'fa-sharp fa-solid fa-xmark','message'=>$tsl->trans("userNotAdded", ['name'=>$record['Prenom'].' '.$record['Nom']])];
                        }
                    }else{
                        $userExists[]= $record['Prenom'].' '.$record['Nom'];
                        $aSessionMessages[] = ['type'=>'danger','icon'=>'fa-sharp fa-solid fa-xmark','message'=>$tsl->trans("userNotAdded", ['name'=>$record['Prenom'].' '.$record['Nom']])];
                                               
                    }                    
    
                }                
                
                $session->set('importProgress', 100);
                
                $userExists = array_unique($userExists);
                $countUserExists = count($userExists);
                if($countUserExists > 0){
                    $logger->error($tsl->trans("userAlreadyExists" , ["count"=>$countUserExists]).implode(', ', $userExists));
                    //$this->addFlash("danger", "userAlreadyExists,".$countUserExists.",". implode('<br/>', $userExists));
                }
                
                $countLineError = count($lineError);
                if($countLineError > 0){
                    $logger->error($tsl->trans("fileLineError").implode(', ', $lineError));
                    //$this->addFlash("danger", "fileLineError,".$countLineError.",". implode('<br/>', $lineError));
                }
                
            }
            unlink('../uploads/user.csv');
            
            $logger->info($tsl->trans("userAdded").$userAdded);
            $logger->info($tsl->trans("userBulkProcessEnd"));
            //$this->addFlash('info', "validatedUsersCreated");
            $aSessionMessages[] = ['type'=>'success','icon'=>'fa-sharp fa-solid fa-check','message'=>$tsl->trans("validatedUsersCreated")];
            $aSessionMessages[] = ['type'=>'success','icon'=>'fa-sharp fa-solid fa-check','message'=>$tsl->trans("userTotal", ["nombre"=>$userTotal])];
            $aSessionMessages[] = ['type'=>'success','icon'=>'fa-sharp fa-solid fa-check','message'=>$tsl->trans("userAdded", ["nombre"=>$userAdded])];
            if(!empty($userError )){
                $aSessionMessages[] = ['type'=>'danger','icon'=>'fa-sharp fa-solid fa-xmark','message'=>$tsl->trans("userCountNotAdded", ["nombre"=>$userError])];
            }
            
            $session->set('reportImport', $aSessionMessages);
        }
        
        
        return $this->render('ldap/admin/userbulk.html.twig', [
            'form'=>$form->createView(),
            'activeMenu' => "user_import",
            'title'=>"userImport"
        ]);
    }

    /**
     * @Route("/userbulk/verifyfile", name="bulkUserVerifyFile")
     */
    public function bulkUserVerifyFile(Request $request, LoggerInterface $logger, TranslatorInterface $tsl): JsonResponse
    {

        $form = $this->createForm(LdapUserbulkType::class, null);
        
        $form->handleRequest($request);
        dump($form->getErrors(true));
        $response = new JsonResponse();
        if ($form->isSubmitted() && $form->isValid()) {
            $logger->info($tsl->trans("bulkUserVerifyFile"));
            $data = $form->getData();
            /**
             * @var UploadedFile $file
             */
            $file = $data['fileimport'];
            dump($file);
            $file->move('../uploads', 'user.csv');

            $csv = Reader::createFromPath('../uploads/user.csv', 'r');
            $csv->setDelimiter(";");
            $csv->setHeaderOffset(0); //set the CSV header offset
            
            $csvHeader =$csv->getHeader();

            $response->setData(['type'=>"success", "message"=>$tsl->trans("fileVerified")]);
            if(count($csvHeader)<5){
                $this->addFlash("danger", "fileError");
                $logger->error($tsl->trans("fileError"));
                $response->setData(['type'=>"danger", "message"=>$tsl->trans("fileError")]);
                return $response;
            }
            unlink('../uploads/user.csv');

            
            return $response;
        }        
        
        $response->setData(['type'=>"danger", "message"=>$tsl->trans("fileError")]);
        return $response;
    }
    
    /**
     * @Route("/userbulk/progress", name="bulkUserProgress")
     */
    public function bulkUserProgress(Request $request, LoggerInterface $logger, TranslatorInterface $tsl): JsonResponse
    {
        $response = new JsonResponse();
            
        $session = $request->getSession();
        $importProgress = $session->get('importProgress');
        dump($session->get('reportImport'));
        $response->setData(
            [
                'progress'=>$importProgress,
                'dataRender'=>$this->renderView('ldap/admin/reportImport.html.twig',
                [
                    'messages'=>$session->get('reportImport')
                ])
            ]);
        
        if($importProgress >= 100){
            $session->remove('importProgress');
        }
        
        return $response;
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
        
        $form = $this->createForm(LdapUserCreateType::class, null, [
            'regions'=>$aRegion,
            'ldap_connection'=>$connection
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
                    $this->addFlash("success", "userCreated");
                } catch (\LdapRecord\LdapRecordException $e) {
                    // Failed saving user.
                }
            }else{
                $this->addFlash("error", "userAlreadyExists");
            }

        }
        
        return $this->render('ldap/admin/usercreate.html.twig', [
            'form'=>$form->createView(),
            "activeMenu" =>"user_create",
            'title'=>"createUser"
        ]);
    }
}
