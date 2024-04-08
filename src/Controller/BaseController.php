<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class BaseController extends AbstractController
{
    
    public function __construct( ){
        

        
    }
    
    //public     
    public function initHtmlHead(){
        $this->headerExt->setWebroot($this->getParameter('site_base_url'));
        /*$this->headerExt->headScript->appendFile('https://code.jquery.com/jquery-3.6.0.min.js');
        $this->headerExt->headScript->appendFile('https://code.jquery.com/ui/1.12.1/jquery-ui.min.js');
        $this->headerExt->headScript->appendFile('/js/bootstrap.bundle.min.js');
        $this->headerExt->headScript->appendFile('/bundles/fosjsrouting/js/router.min.js');
        
        $fosjsrouterUrl = $this->generateUrl('fos_js_routing_js', array('callback' => 'fos.Router.setData'));
        $this->headerExt->headScript->appendFile($fosjsrouterUrl);*/

        //$this->headerExt->headLink->appendStylesheet("/css/bootstrap.css");
        //$this->headerExt->headLink->appendStylesheet('/fontawesome/css/all.css');
        //$this->headerExt->headLink->appendStylesheet('/css/global.css');
        //$this->headerExt->headLink->appendStylesheet('https://fonts.googleapis.com/css2?family=Oswald:wght@200;300;400;500;600;700');
        $this->headerExt->headLink->appendAlternate("/favicon.ico", 'image/vnd.microsoft.icon', null, ['rel'=>"shortcut icon"] );
        
        $this->headerExt->headMeta->appendName('robots', 'noindex, nofollow');
    }

}

