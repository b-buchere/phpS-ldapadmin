<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Form\LdapUserCreateType;
use App\Form\LdapUserGroupUpdateType;
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
use Symfony\Component\HttpFoundation\JsonResponse;
use PDO;
use LdapRecord\Auth\BindException;
use App\Services\Ssp;
use App\Services\LdapCustomFunctions;
use App\Security\Voter\UserVoter;
use League\Csv\Reader;
use utilphp\util;
use App\Form\LdapUserbulkType;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use App\DatatablesBundle\UserDatatable;
use App\Entity\Utilisateurs;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\UtilisateursRepository;
use App\Entity\Groupes;
/**
 * @Route("/ldapadmin", name="ldapadmin_")
 */
class LdapUserController extends BaseController
{
    /**
     * @var HeaderExtension $headerExt
     */  
    protected HeaderExtension $headerExt;
    
    /**
     * @var EntityManagerInterface $em
     */
    protected EntityManagerInterface $em;
    
    public function __construct( HeaderExtension $headerExt, EntityManagerInterface $em ){
        $this->headerExt = $headerExt;
        $this->em = $em;
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

        
        return $this->render('ldap/admin/users.html.twig', [
            'user' => $this->getUser()
        ]);

    }

    /**
     * @Route("/usergroupupdate", name="usergroupupdate")
     */
    public function GroupUpdate(Request $request): Response
    {
        $this->initHtmlHead();
        
        $form = $this->createForm(LdapUserGroupUpdateType::class, null);
        
        $form->handleRequest($request);
        
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
        
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            /**
             * @var UploadedFile $file
             */
            $file = $data['fileimport'];
            $file->move('../uploads', 'usergroup.csv');           
            
            $csv = Reader::createFromPath('../uploads/usergroup.csv', 'r');
            $csv->setDelimiter(";");
            $csv->setHeaderOffset(0); //set the CSV header offset
            
            $records = $csv->getRecords();
            
            $utilphp = new util();
            $noUser = [];
            $error = false;
            foreach ($records as $record) {
                /**
                 * @var User $user
                 */
                $user = User::findBy('samaccountname', strtolower($record['Prenom'][0]).strtolower($utilphp->sanitize_string($record['Nom'])));
                $group = Group::find('cn='.$record['Groupe'].',ou=Groups,ou=TRANSVERSE,dc=ncunml,dc=ass');
                
                if(!is_null($user)){                    
                    try {
                        if(is_null($group)){
                            $group = (new Group)->inside('ou=Groups,ou=TRANSVERSE,dc=ncunml,dc=ass');
                            $group->cn = $record['Groupe'];
                            $group->save();
                        }
                        /**
                         * @var 
                         */
                        $userGroup = $user->groups()->attachMany($models);
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
            'user'=>$this->getUser(),
            'form'=>$form->createView(),
            'activeMenu'=>"user_group_update",
            'title'=>"userGroupImport"
        ]);
    }
    
    /**
     * @Route("/userbulk", name="userbulk")
     */
    public function bulk(Request $request, LoggerInterface $logger, TranslatorInterface $tsl): Response
    {
        $this->initHtmlHead();
        $this->headerExt->headScript->appendFile('/js/ldapbulkuser.js');

        $form = $this->createForm(LdapUserbulkType::class, null, ['help_message'=>$tsl->trans("fileimportVerifyProgress")]);
        
        $form->handleRequest($request);
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
        if ($form->isSubmitted() && $form->isValid()) {
            $logger->info($tsl->trans("bulUserProcessStart"));
            $data = $form->getData();
            /**
             * @var UploadedFile $file
             */
            $file = $data['fileimport'];
            $file->move('../uploads', 'user.csv');
            
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
                    
                    $dnRegion = 'ou='.$record['Region'].','.$dn;
                    $dnStructure = "ou=".$record['Structure'].",".$dnRegion;
                    $structureLdap = OrganizationalUnit::find($dnStructure);
                    $regionLdap = OrganizationalUnit::find($dnRegion);
                    
                    if(is_null($user) && !is_null($structureLdap) && !is_null($regionLdap) && !empty($record['Region'])){
                        $user = (new User)->inside('ou='.$record['Structure'].',ou='.$record['Region'].','.$dn);
                        $user->setDn('cn='.$record['Prenom'].' '.$record['Nom'].',ou='.$record['Structure'].',ou='.$record['Region'].','.$dn);
                        //$user->cn = ldap_escape($record['Prenom'].' '.$record['Nom']);
                        $user->unicodePwd = '';
                        $user->samaccountname = strtolower($record['Prenom'][0]);
                        if(strpos($record['Prenom'], '-')){
                            $prenoms = explode('-', $record['Prenom']);                            
                            
                            $prenomusername = ''; 
                            foreach($prenoms as $prenom){
                                $prenomusername.= strtolower($prenom[0]);
                            }
                            $user->samaccountname = $prenomusername;
                        }
                        $user->samaccountname .= strtolower($utilphp->sanitize_string($record['Nom']));
                        $user->mail = $record['email'];
                        $user->userAccountControl = 512;
                        $user->pwdlastset = 0;
                        
                        try {
                            $user->save();
                            $userAdded++;
                            $logger->info($tsl->trans("userAdded").$userAdded);
                            
                        } catch (\LdapRecord\LdapRecordException $e) {
                            // Failed saving user.
                            /*$userError++;
                            $logger->error($tsl->trans("userAddError").implode(", ".$record));
                            $aSessionMessages[] = ['type'=>'danger','icon'=>'fa-sharp fa-solid fa-xmark','message'=>$tsl->trans("userNotAdded", ['name'=>$record['Prenom'].' '.$record['Nom']])];*/
                        }
                    }else{
                        $userExists[]= $record['Prenom'].' '.$record['Nom'];
                        $aSessionMessages[] = ['type'=>'danger','icon'=>'fa-sharp fa-solid fa-xmark','message'=>$tsl->trans("userNotAdded", ['name'=>$record['Prenom'].' '.$record['Nom']])];
                        $userError++;                       
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
            dump(realpath('../uploads/user.csv'));
            unlink(realpath('../uploads/user.csv'));
            
            $logger->info($tsl->trans("userAdded", ["nombre"=>$userAdded]));
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
            'user'=>$this->getUser(),
            'form'=>$form->createView(),
            'activeMenu' => "user_import",
            'title'=>"userImport"
        ]);
    }

    /**
     * @Route("/userbulk/verifyfile", name="bulkUserVerifyFile")
     */
    public function bulkVerifyFile(Request $request, LoggerInterface $logger, TranslatorInterface $tsl): JsonResponse
    {

        $form = $this->createForm(LdapUserbulkType::class, null);
        
        $form->handleRequest($request);
        $response = new JsonResponse();
        if ($form->isSubmitted() && $form->isValid()) {
            $logger->info($tsl->trans("bulkUserVerifyFile"));
            $data = $form->getData();
            /**
             * @var UploadedFile $file
             */
            $file = $data['fileimport'];
            $file->move('../uploads', 'user.csv');

            $csv = Reader::createFromPath('../uploads/user.csv', 'r');
            $csv->setDelimiter(";");
            $csv->setHeaderOffset(0); //set the CSV header offset
            
            $csvHeader =$csv->getHeader();            
            $userTotal = $csv->count();
            
            $session = $request->getSession();
            $session->set('importProgress', 0);

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
    public function bulkProgress(Request $request, LoggerInterface $logger, TranslatorInterface $tsl): JsonResponse
    {
        $response = new JsonResponse();
            
        $session = $request->getSession();
        $importProgress = $session->get('importProgress');
        
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
     * @Route("/userbulk/report", name="bulkUser_report")
     */
    public function bulkUserReport(Request $request, LoggerInterface $logger, TranslatorInterface $tsl): BinaryFileResponse
    {
        $session = $request->getSession();
        
        $aRows = [];
        foreach($session->get('reportImport') as $reportLine){
            unset($reportLine['icon']);
            $aRows[]=implode(',',$reportLine);
            
        }
        $content = implode("\n", $aRows);
        file_put_contents("report.csv", utf8_decode($content));
        
        $response = new BinaryFileResponse("report.csv");
        $response->setCharset('ISO-8859-2');
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="report.csv"');
        $response->prepare($request);
        unlink("report.csv");
        return $response;
    }
    
    /**
     * @Route("/usercreate", name="usercreate")
     */
    public function create(Request $request): Response
    {
        $this->initHtmlHead();
		$this->headerExt->headScript->appendFile('/js/select2.min.js');
        $this->headerExt->headScript->appendFile('/js/ldapcreateuser.js');        
		
		$this->headerExt->headLink->appendStylesheet('/css/select2.min.css');
        $this->headerExt->headLink->appendStylesheet('/css/select2-bootstrap-5-theme.min.css');
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

        //$connection->setCache(null);
        Container::addConnection($connection);
        
        $query = $connection->query()->cache(new \DateTime(), true);
        $nodeList = $query->select(['dn','ou','namingContexts'])
                        ->in($dn)
                        ->rawFilter('(objectCategory=organizationalUnit)')
                        ->listing()->get();
    
        $aRegion = [''=>''];
        foreach($nodeList as $node){
            $aRegion[$node['ou'][0]] = $node['dn'];
        }
        
        $userDb = new Utilisateurs();
        
        $form = $this->createForm(LdapUserCreateType::class, $userDb, [
            'regions'=>$aRegion,
            'ldap_connection'=>$connection
        ]);

        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $groupeFormData = $form->get('Groupes')->getData();
            $utilphp = new util();
            
            $user = User::findBy('samaccountname', strtolower($form->get('prenom')->getData()[0]).strtolower($utilphp->sanitize_string($form->get('nom')->getData())));
            
            if(is_null($user)){
                $user = (new User)->inside($form->get('structure')->getData());
                $user->cn = $form->get('prenom')->getData().' '.$form->get('nom')->getData();
                $user->unicodePwd = '';
                $user->samaccountname = ucfirst(strtolower($form->get('prenom')->getData()[0])).strtolower($utilphp->sanitize_string($form->get('nom')->getData()));
                $user->mail = $form->get('courriel')->getData();
                $user->userAccountControl = 512;
                $user->pwdlastset = 0;
                
                try {
                    $user->save();
                    
                    dump($user->getAttributes());
                    $userDb->setDn($user->getDn());
                    $userDb->setIdentifiant($user->getAttribute('samaccountname')[0]);
                    $userDb->setNom($form->get('nom')->getData());
                    $userDb->setCourriel($user->getAttribute('mail')[0]);
                    
                    foreach( $groupeFormData as $groupe ){
                        /**
                         * @var Groupes $groupe
                         */
                        
                        $groupe->addMembre($userDb);
                        $this->em->persist($groupe);
                        
                        $ldadGroupe = Group::find($groupe->getDn());
                        
                        $user->groups()->attach($ldadGroupe);
                        
                    }          
                    
                    $this->em->persist($userDb);
                    $this->em->flush();
                    
                    $this->addFlash("success", "userCreated");
                    return $this->redirectToRoute('ldapadmin_useredit', ['id'=>$userDb->getId()]);
                } catch (\LdapRecord\LdapRecordException $e) {
                    // Failed saving user.
                }
            }else{
                $this->addFlash("error", "userOneAlreadyExists");
            }

        }
        
        return $this->render('ldap/admin/usercreate.html.twig', [
            'user'=>$this->getUser(),
            'form'=>$form->createView(),
            "activeMenu" =>"user_create",
            'title'=>"createUser"
        ]);
    }
    
    /**
     * @Route("/useredit/{id}", name="useredit")
     */
    public function edit(int $id, Request $request): Response
    {
        $this->initHtmlHead();
        $this->headerExt->headScript->appendFile('/js/select2.min.js');
        $this->headerExt->headScript->appendFile('/js/ldapcreateuser.js');
        
        $this->headerExt->headLink->appendStylesheet('/css/select2.min.css');
        $this->headerExt->headLink->appendStylesheet('/css/select2-bootstrap-5-theme.min.css');
        
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
        
        /**
         * @var Utilisateurs $userDb
         */
        $userDb = $this->em->getRepository(Utilisateurs::class)->findOneById($id);
        if(is_null($userDb)){
            $userDb = new Utilisateurs();
        }
        
        
        $form = $this->createForm(LdapUserCreateType::class, $userDb, [
            'regions'=>$aRegion,
            'ldap_connection'=>$connection
        ]);

        
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $utilphp = new util();
            
            $user = User::findBy('samaccountname', strtolower($form->get('prenom')->getData()[0]).strtolower($utilphp->sanitize_string($form->get('nom')->getData())));            

            if(is_null($user)){
                $user = (new User)->inside($form->get('structure')->getData());
                $user->unicodePwd = '';
                $user->samaccountname = ucfirst(strtolower($form->get('prenom')->getData()[0])).strtolower($utilphp->sanitize_string($form->get('nom')->getData()));
                $user->userAccountControl = 512;
                $user->pwdlastset = 0;
            }

            $user->mail = $form->get('courriel')->getData();
            
            try {
                
                dump($userDb);
                $groupeFormData = $form->get('Groupes')->getData();
                //$userDb->getGroupes()->clear();
                $user->save();
                
                $user->groups()->detachAll();
                
                $userDb->setDn($user->getDn());
                $userDb->setIdentifiant($user->getAttribute('samaccountname')[0]);
                $userDb->setNom($user->getAttribute('sn')[0]);
                //$userDb->setCourriel($user->getAttribute('mail'));
                
                $this->em->persist($userDb);
                
                foreach( $groupeFormData as $groupe ){
                    /**
                     * @var Groupes $groupe
                     */
                    //$groupeDb = $this->em->getRepository(Groupes::class)->findOneById((int)$groupe);
                    
                    $groupe->addMembre($userDb);
                    $this->em->persist($groupe);
                        
                    $ldadGroupe = Group::find($groupe->getDn());
                    
                    $user->groups()->attach($ldadGroupe);
                    
                }                  

                $this->em->persist($userDb);
                $this->em->flush();
                
                $this->addFlash("success", "userModified");
            } catch (\LdapRecord\LdapRecordException $e) {
                // Failed saving user.
                dump($e);
            }
        }
        
        return $this->render('ldap/admin/usercreate.html.twig', [
            'user'=>$this->getUser(),
            'form'=>$form->createView(),
            "activeMenu" =>"user_create",
            'title'=>$userDb->getPrenom().' '.$userDb->getNom()
        ]);
    }
    
    /**
     * @Route("/user/list", name="userlist")
     */
    public function list(Request $request, UserDatatable $datatable, Ssp $responseService): Response
    {
        $this->initHtmlHead();
        
        $this->headerExt->headLink->appendStylesheet('https://cdn.datatables.net/1.11.3/css/dataTables.bootstrap5.min.css');
        $this->headerExt->headScript->appendFile('//cdn.datatables.net/1.11.3/js/jquery.dataTables.min.js');
        $this->headerExt->headScript->appendFile('https://cdn.datatables.net/1.11.3/js/dataTables.bootstrap5.min.js');
        $this->headerExt->headStyle->appendStyle('//cdn.datatables.net/1.11.3/css/jquery.dataTables.min.css');
        
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
        
        $isAjax = $request->isXmlHttpRequest();
        
        $ajaxUrl = $this->generateUrl('ldapadmin_userlist');
        $datatable->buildDatatable(['ajaxUrl'=>$ajaxUrl]);
        
        if ($isAjax) {
            
            $responseService->setQb($this->em->getRepository(Utilisateurs::class)->findAllForDatatableUserRight());
            
            $responseService->setDatatable($datatable);
            return $responseService->getResponse();
        }    
        
        return $this->render('ldap/admin/userlist.html.twig', [
            "user"=>$this->getUser(),
            "activeMenu" =>"user_list",
            'title'=>"AllUser",
            'datatable'=>$datatable
        ]);
    }
    
    /**
     * @Route("/user/resetpwd/{id}", name="user_resetpwd")
     */
    public function resetpwd(int $id, Request $request): Response
    {
        
    }
    
    /**
     * @Route("/user/groupadd/{id}", name="user_groupadd")
     */
    public function groupadd(int $id, Request $request): Response
    {

        $user = $this->em->getRepository(Utilisateurs::class)->findById($id);
        
        
        
    }
}
