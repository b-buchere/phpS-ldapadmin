<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Form\PasswordChangePromptType;
use App\Form\PasswordChangeRequestType;
use App\Twig\HeaderExtension;
use Symfony\Component\Ldap\Ldap;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mailer\Mailer;
use LdapRecord\Connection;
use LdapRecord\Container;
use LdapRecord\LdapRecordException;
use LdapRecord\Models\ActiveDirectory\User;
use LdapRecord\Models\Events\Saving;
use LdapRecord\DetailedError;
use Doctrine\DBAL\Exception\ConstraintViolationException;

/**
 * @Route("/password", name="password_")
 */
class PasswordController extends BaseController
{
    /**
     * @var HeaderExtension $headerExt
     */  
    protected HeaderExtension $headerExt;
    
    public function __construct( HeaderExtension $headerExt ){
        $this->headerExt = $headerExt;
    }
    
    /**
     * @Route("/changerequest", name="changerequest")
     */
    public function index(Request $request): Response
    {       
        $this->initHtmlHead();
        $this->headerExt->headLink->appendStylesheet("/css/reset.css");
        
        $form = $this->createForm(PasswordChangeRequestType::class, null);
        
        $form->handleRequest($request);
        $message = [];
        
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            
            $server = $this->getParameter('ldap_server');
            //$dn = "OU=Utilisateurs,OU=ALPHA-ORIONIS,DC=ncunml,DC=ass";
            $dn = $this->getParameter('ad_base_dn');
            //ouverture de la communication avec le serveur ldap ("socket")
            $ldap = Ldap::create('ext_ldap', [
               'connection_string'=>$server
            ]);
            
            // connexion � un compte pour la lecture de l'annuaire
            $user_admin = $this->getParameter('ad_passwordchanger_user');
            $user_pwd = $this->getParameter('ad_passwordchanger_pwd');
            $ldap->bind($user_admin, $user_pwd);
            
            $user = $data["User"];
            //R�cup�ration des infos user par nom de compte
            $user_search = $ldap->query($dn, "(|(samaccountname=$user))");
            $user_get = $user_search->execute()->toArray();
            $user_def = $user_get[0]->getAttributes();
            
            $mailUser = $user_def["mail"][0];
            //Create an instance; passing `true` enables exceptions
            if(!is_null($mailUser) && !empty($mailUser)){
                try {
                    
                    $urlchangePassword = "https://".$request->getHttpHost().$this->generateUrl('password_changeprompt').'?d=';
                    $dataMail = [
                        'user'=>$user,
                        'time'=>time()
                    ];
                    $dataMailImplode = http_build_query($dataMail);
                    $dataMailImplode = urlencode($dataMailImplode);
                    $urlchangePassword .= base64_encode($dataMailImplode);
                    $subject = 'Réintilisation de mot de passe';
                    $body    = '<a href="'.$urlchangePassword.'">Réinitialiser le mot de passe</a>';
                    
                    $dsn = $this->getParameter('dsn');
                    $transport = Transport::fromDsn($dsn);
                    $mailer = new Mailer($transport);
                    
                    $mail = (new Email())
                        ->from('ncunml@unml.info')
                        ->to($mailUser)
                        ->subject($subject)
                        ->html($body, 'UTF-8');
                    //$mail->AltBody = 'This is the body in plain text for non-HTML mail clients';
                    
                    if ( $mailer->send($mail) === false ){
                        $message["error"] = ["La réinitialisation ou la modification du mot de passe est impossible"];
                    } else {
                        $message["success"] = ["Un message a été envoyé à votre adresse de courriel"];
                    }
                } catch (TransportExceptionInterface  $e) {

                    $message["error"] = ["Envoi de courriel impossible. Erreur du Mailer"];
                }
            }

        }
        
        return $this->render('password/changerequest.html.twig', [
            'form'=>$form->createView(),
            'messages'=>$message,
            'helpHtml'=>'password/request.html.twig'
        ]);

    }
    
    /**
     * @Route("/prompt", name="changeprompt")
     */
    public function prompt(Request $request): Response
    {
        $this->initHtmlHead();
        $this->headerExt->headLink->appendStylesheet("/css/reset.css");
        
        $dataDecoded = urldecode(base64_decode($request->query->get('d')) );
        $data =[];
        parse_str($dataDecoded, $data);
        $userData = $data['user'];
        
        $form = $this->createForm(PasswordChangePromptType::class, null);
        $form->get('User')->setData($userData);
        
        $form->handleRequest($request);
        $message = [];
        
        if ($form->isSubmitted() && $form->isValid()) {
            $dataForm = $form->getData();
            $newPassword = $dataForm['NewPassword'];
            
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
                'version'  => 3,
                'use_tls'  => true,
                'follow_referrals' => false,
            ]);
            
            // Add the connection into the container:
            Container::addConnection($connection);
            // connexion � un compte pour la lecture de l'annuaire
            
            /**
             * @var User $user
             */
            $user = User::where('samaccountname', '=', $userData)->first();

            $passwordRetryCount = $user->getAttributeValue("badpwdcount")[0];
            $message["error"] = [];
            // Règles de sécurité des mots de passe
            if ( $passwordRetryCount >= 3 ) {
                $message["error"][] = "Votre compte est bloqué !";
            }
            
            //Confirmation du nouveau mot de passe
            if ($newPassword != $dataForm['NewPasswordCnfm'] ) {
                $message["error"][] = "La confirmation du mot de passe de correspond pas!";
            }
            
            
            //longueur du mot de passe
            if (strlen($newPassword) < 8 ) {
                $message["error"][] = "Votre mot de passe est trop court.<br/>Le mot de passe doit faire au moins 8 caractères.";
            }
            //chiffre nécessaire
            if (!preg_match("/[0-9]/",$newPassword)) {
                $message["error"][] = "Le mot de passe doit contenir au moins un chiffre.";
            }
            //Lettre nécessaire
            if (!preg_match("/[a-zA-Z]/",$newPassword)) {
                $message["error"][] = "Error E105 - Le mot de passe doit contenir au moins une lettre.";
            }
            //Majuscule nécessaire
            if (!preg_match("/[A-Z]/",$newPassword)) {
                $message["error"][] = "Le mot de passe doit contenir au moins une majuscule.";
            }
            
            //Pb de compte (différent d'un verrouilage de compte)
            if (is_null($user) ) {
                $message["error"][] = "Connexion au serveur impossible, merci de réessayé ultérieurement.";
            }
            
            
            if ( empty($message["error"]) ){
                try{
                    $user->unicodepwd = $newPassword;
                    $user->save();
                
                    $message["success"] = ["Le mot de passe pour ".$user->getName." a été modifié."];
                }
                catch (ConstraintViolationException $ex) {
                    // The users new password does not abide
                    // by the domains password policy.
                } catch (LdapRecordException $ex) {
                    // Failed changing password. Get the last LDAP
                    // error to determine the cause of failure.
                    /**
                     *  @var DetailedError $error 
                     */
                    $error = $ex->getDetailedError();
                    
                    $errno = $error->getErrorCode();
                    
                    $message["error"] = [
                        "impossible de changer le mot de passe, veuillez contacter l'administrateur.",
                        $errno." - ".$error->getDiagnosticMessage(),
                        $error->getErrorMessage()
                    ];
                }
                
                
            }
        }
        
        return $this->render('password/changerequest.html.twig', [
            'form'=>$form->createView(),
            'messages'=>$message,
            'helpHtml'=>'password/prompt.html.twig'
        ]);
        
    }
}
