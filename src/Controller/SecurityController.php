<?php

namespace App\Controller;

use App\Form\LoginType;
use App\Twig\HeaderExtension;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends BaseController
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
     * @Route("/login", name="login")
     */
    public function index(AuthenticationUtils $authenticationUtils): Response
    {
        $this->initHtmlHead();
        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        
        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();
        
        $form = $this->createForm(LoginType::class, null, []);
        
        return $this->render('login/index.html.twig', [
            'form'          => $form->createView(),
            'last_username' => $lastUsername,
            'error'         => $error,
        ]);
    }
    
    /**
     * @Route("/logout", name="logout")
     */
    public function logout(AuthenticationUtils $authenticationUtils): Response
    {
        $this->initHtmlHead();
        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        
        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();
        
        $form = $this->createForm(LoginType::class, null, []);
        
        return $this->render('login/index.html.twig', [
            'form'          => $form->createView(),
            'last_username' => $lastUsername,
            'error'         => $error,
        ]);
    }
    
}
