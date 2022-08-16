<?php

namespace App\Security;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\PassportInterface;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\CustomCredentials;
use LdapRecord\Container;
use LdapRecord\Auth\Events\Failed;
use LdapRecord\Models\ActiveDirectory\User;
use LdapRecord\Connection;
use Symfony\Component\HttpFoundation\ParameterBag;

class LoginFormAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'login';

    private UrlGeneratorInterface $urlGenerator;

    public function __construct(UserProviderInterface $userProvider, UrlGeneratorInterface $urlGenerator, ParameterBagInterface $params )
    {
        $this->urlGenerator = $urlGenerator;
        $this->userProvider = $userProvider;
        $this->params = $params;
    }

    public function authenticate(Request $request): PassportInterface
    {
        $param = $this->params;
        $credentials = $request->request->get('login');

        $request->getSession()->set(Security::LAST_USERNAME, $credentials['_username']);

        $passwordCredentials = new PasswordCredentials($credentials['_password']);
        return new Passport(
            new UserBadge(implode(',',$credentials), [$this->userProvider, 'loadUserByIdentifier']),
            new CustomCredentials(function($credentials, LdapUserFromLdapRecord $user) use ($param){

                $server = $param->get('ldap_server');
                $dn = $param->get('ad_base_dn');
                $user_admin = $param->get('ad_passwordchanger_user');
                $user_pwd = $param->get('ad_passwordchanger_pwd');
                
                $connection = new Connection([
                    'hosts' => [$server],
                    'port'  => 389,
                    'base_dn' => $dn,
                    'username' => $user_admin,
                    'password' => $user_pwd
                ]);

                return $connection->auth()->attempt($user->getEntry()->getDn(), $credentials);
                }, $credentials['_password']),
            [
                new CsrfTokenBadge('login', $credentials['_csrf_token']),
            ]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
            return new RedirectResponse($targetPath);
        }

        // For example:
        return new RedirectResponse($this->urlGenerator->generate('home'));
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }
}
