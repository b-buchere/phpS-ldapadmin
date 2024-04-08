<?php

namespace App\Controller;

use Symfony\Component\Routing\Annotation\Route;
use LdapRecord\Container;
use LdapRecord\Connection;
use LdapRecord\Models\ActiveDirectory\Group;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\StreamedResponse;
use League\Csv\Writer;
use Symfony\Component\HttpFoundation\HeaderUtils;
use LdapRecord\Models\ActiveDirectory\User;
use LdapRecord\Models\Attributes\DistinguishedName;
/**
 * @Route("/export", name="ldap_export_")
 */
class LdapExportController extends AbstractController
{
    
    /**
     * @Route("", name="index")
     */
    public function index( ): StreamedResponse
    {
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
        // connexion � un compte pour la lecture de l'annuaire

        //$ldap->bind($user_admin, $user_pwd);
        
        $query = $connection->query();

        $users = $query->select()->rawFilter("(&(objectCategory=person)(samaccountname=*))")->get();
        
        $csv = Writer::createFromString();
        $csv->setDelimiter(';');
        $csv->insertOne(['nom','prénom', 'adresse mail', 'Rôle', 'adhérent', 'OU']);
        foreach( $users as $user ){

                // Récupération du nom utilisateur
            $prenom = '';
            $nom = '';
            if(isset($user['givenname'])){
                $prenom = $user['givenname'][0];
                $nom = str_replace($user['givenname'][0], '', $user['displayname'][0]);
            }
            
            //Récupération des groupes de l'utilisateur
            $groupes = '';
            if(isset($user['memberof'])){
                unset($user['memberof']['count']);
                foreach($user['memberof'] as $groupe){
                    $ou = Group::find($groupe);
                    $groupes .= $ou->getName().", ";
                }
            }
            $groupes = substr( $groupes, 0, -2 );
            
            //Récupération des OU
            $dn = DistinguishedName::make($user['dn']);
            $implodeOu= '';
            $explodeAssoc = $dn->assoc();
            if(isset($explodeAssoc['ou'])){
                $implodeOu = implode(', ',  $explodeAssoc['ou']);
            }
            
            $adherent = "Non";
            if(isset($user['wwwhomepage'])){
                $adherent = $user['wwwhomepage'][0];
            }            
            
            //Récupération de l'adresse mail
            $mail = '';
            if(isset($user['mail'])){
                $mail = $user['mail'][0];
            }
            $csv->insertOne([$nom, $prenom, $mail, $groupes, $adherent, $implodeOu]);
        }

        $response = new StreamedResponse(function() use ($csv) {
            echo mb_convert_encoding($csv->toString(), 'UTF-16LE', 'UTF-8');
        });
        $response->headers->set('Content-Type', 'text/csv charset=UTF-8');
        $disposition = HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            'ldap_user_export.csv'
        );
        return $response;

    }
}
