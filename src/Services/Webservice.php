<?php
namespace App\Services;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 *
 * @author cheat
 *        
 */
class Webservice
{
    private $client;
    
    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client;
    }
    
    
    public function request(): array
    {
        $response = $this->client->request(
            'GET',
            'https://',
            [
                'auth_basic' => ['uBczy3SS5QFSsRZYjkFFRhq2LzG9HqDT', 'PTBFLXvTJp7$tcRbzkC!SsCL/rT3cX5tZu4LdWT!=zh^r^2WW7}*Esx{v}-z7MaYMK9mR/z7_fgP{JQH^pKa_U6Yyf9Xk{pcq6$vt_Y3xSHadE==Wk^NH86F_3FWDLeuEcn/Q8g^3TY_9Q8ME^mW!']
            ]
            );

        // $content = '{"id":521583, "name":"symfony-docs", ...}'
        $content = $response->toArray();
        $token = $content['object']['token'];

        
        $response = $this->client->request(
            'GET',
            'https://',
            [
                'auth_bearer' => $token
            ]
            );
        
        $content = $response->toArray();
        
        return $content;
    }
    
}

