<?php


namespace UserBundle\Security\Token;

use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * Токен для авторизации по простому логин-паролю
 *
 * @package UserBundle\Security\Token
 */
class SimpleAuthenticatorToken extends UsernamePasswordToken
{

}
