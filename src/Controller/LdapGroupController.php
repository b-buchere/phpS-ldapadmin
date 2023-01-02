<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Form\LdapGroupcreateType;
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
use App\DatatablesBundle\GroupDatatable;
use App\DatatablesBundle\UserDatatable;
use App\Entity\Utilisateurs;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Groupes;
/**
 * @Route("/ldapadmin/group", name="ldapadmin_group")
 */
class LdapGroupController extends BaseController
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
     * @Route("/bulk", name="bulk")
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
            
            /**
             * On ne traite que les csv avec au moins
             */
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
            unlink('../uploads/user.csv');
            
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
     * @Route("/bulk/verifyfile", name="bulkVerifyFile")
     */
    public function bulkVerifyFile(Request $request, LoggerInterface $logger, TranslatorInterface $tsl): JsonResponse
    {

        $form = $this->createForm(LdapUserGroupUpdateType::class, null);
        
        $form->handleRequest($request);
        $response = new JsonResponse();
        if ($form->isSubmitted() && $form->isValid()) {
            $logger->info($tsl->trans("bulkVerifyFile"));
            $data = $form->getData();
            /**
             * @var UploadedFile $file
             */
            $file = $data['fileimport'];
            $file->move('../uploads', 'usergroup.csv');

            $csv = Reader::createFromPath('../uploads/usergroup.csv', 'r');
            $csv->setDelimiter(";");
            $csv->setHeaderOffset(0); //set the CSV header offset
            
            $csvHeader =$csv->getHeader();

            $response->setData(['type'=>"success", "message"=>$tsl->trans("fileVerified")]);
            if(count($csvHeader)<3){
                $this->addFlash("danger", "fileError");
                $logger->error($tsl->trans("fileError"));
                $response->setData(['type'=>"danger", "message"=>$tsl->trans("fileError")]);
                return $response;
            }
            unlink('../uploads/usergroup.csv');
            
            return $response;
        }        
        
        $response->setData(['type'=>"dangertest", "message"=>$tsl->trans("fileError")]);
        return $response;
    }
    
    /**
     * @Route("/progress", name="bulkProgress")
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
     * @Route("/bulk/report", name="bulk_report")
     */
    public function bulkReport(Request $request, LoggerInterface $logger, TranslatorInterface $tsl): BinaryFileResponse
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
     * @Route("/create", name="create")
     */
    public function create(Request $request): Response
    {
        $this->initHtmlHead();        
        //$this->headerExt->headScript->appendFile('/js/ldapcreategroup.js');

        $form = $this->createForm(LdapGroupcreateType::class, null);
        
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
        ]);
        
        // Add the connection into the container:
        Container::addConnection($connection);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            // connexion � un compte pour la lecture de l'annuaire
            
            
            $transverseDn = "OU=Groups,OU=TRANSVERSE,DC=ncunml,DC=ass";
            /**
             * @var OrganizationalUnit $ou
             */
            
            $group = (new Group)->inside($transverseDn);
            $group->cn = 'GT_'.strtoupper( $data["groupName"] );
            $group->save();
        }
        return $this->render('ldap/admin/groupcreate.html.twig', [
            'user'=>$this->getUser(),
            'form'=>$form->createView(),
            "activeMenu"=>"group_create",
            'title'=>"groupCreate"
        ]);
    }
    
    /**
     * @Route("/list", name="list")
     */
    public function list(Request $request, GroupDatatable $datatable, Ssp $responseService): Response
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
        ]);
        
        // Add the connection into the container:
        Container::addConnection($connection);
        
        $isAjax = $request->isXmlHttpRequest();
        
        $ajaxUrl = $this->generateUrl('ldapadmin_grouplist');
        $datatable->buildDatatable(['ajaxUrl'=>$ajaxUrl]);
        
        if ($isAjax) {
            
            $responseService->setQb($this->em->getRepository(Groupes::class)->findAllForDatatable());
            
            $responseService->setDatatable($datatable);
            return $responseService->getResponse();
        }    
        
        return $this->render('ldap/admin/grouplist.html.twig', [
            'user'=>$this->getUser(),
            "activeMenu" =>"group_list",
            'title'=>"AllGroup",
            'datatable'=>$datatable
        ]);
    }
    
}
