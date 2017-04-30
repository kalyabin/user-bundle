<?php

namespace UserBundle\Security;

use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Http\Authentication\SimpleFormAuthenticatorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Serializer\Encoder\JsonDecode;
use Symfony\Component\Serializer\Encoder\JsonEncode;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use UserBundle\Entity\UserEntity;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use UserBundle\Security\Exception\SimpleAuthenticatorMessageException;
use UserBundle\Security\Token\SimpleAuthenticatorToken;

/**
 * Сервис для авторизации через простую форму логина
 *
 * @package UserBundle\Security
 */
class SimpleAuthenticator implements SimpleFormAuthenticatorInterface
{
    /**
     * @var UserPasswordEncoderInterface
     */
    protected $encoder;

    /**
     * Конструктор
     *
     * @param UserPasswordEncoderInterface $encoder Энкодер паролей для пользователей (используется тот же, что и в UserManager)
     */
    public function __construct(UserPasswordEncoderInterface $encoder)
    {
        $this->encoder = $encoder;
    }

    /**
     * Проверка авторизации пользователя по токену
     *
     * @param TokenInterface $token
     * @param UserProviderInterface $userProvider
     * @param string $providerKey
     *
     * @return SimpleAuthenticatorToken
     */
    public function authenticateToken(TokenInterface $token, UserProviderInterface $userProvider, $providerKey)
    {
        $authError = new SimpleAuthenticatorMessageException('Неверный логин или пароль');

        try {
            $user = $userProvider->loadUserByUsername($token->getUsername());
        } catch (UsernameNotFoundException $e) {
            throw $authError;
        }

        if (!$this->encoder->isPasswordValid($user, $token->getCredentials())) {
            throw $authError;
        }

        // проверить, что пользователь активен и может быть авторизован
        /** @var UserEntity $user */
        if ($user instanceof UserEntity && $user->isLocked()) {
            // аккаунт заблокирован
            $authError = new SimpleAuthenticatorMessageException('Ваш аккаунт заблокирован');
            $authError->setIsLocked();
            throw $authError;
        } elseif ($user instanceof UserEntity && $user->isNeedActivation()) {
            // аккаунт не активирован
            $authError = new SimpleAuthenticatorMessageException('Требуется активация');
            $authError->setIsNeedActivation();
            $authError->setUserId($user->getId());
            throw $authError;
        } elseif ($user instanceof UserEntity && $user->isActive()) {
            // пользователь активен, можем авторизовать
            return new SimpleAuthenticatorToken(
                $user,
                $user->getPassword(),
                $providerKey,
                $user->getRoles()
            );
        }

        // во всех остальных случаях неверный логин или пароль
        throw $authError;
    }

    /**
     * Проверяет, поддерживает ли указанный токен
     *
     * @param TokenInterface $token
     * @param string $providerKey
     *
     * @return bool
     */
    public function supportsToken(TokenInterface $token, $providerKey)
    {
        return $token instanceof SimpleAuthenticatorToken && $token->getProviderKey() == $providerKey;
    }

    /**
     * Создать токен на основе запроса пользователя
     *
     * @param Request $request
     * @param string $username Логин пользователя
     * @param string $password Пароль пользователя
     * @param string $providerKey Ключ провайдера пользователей
     *
     * @return SimpleAuthenticatorToken
     */
    public function createToken(Request $request, $username, $password, $providerKey)
    {
        // если пришел JSON-запрос и пустые username и password,
        // то нужно их получить из тела запроса,
        // т.к. триггеры системы безопасности запускаются раньше, чем обработчики запроса
        if ($request->getContentType() == 'json' && empty($username) && empty($password)) {
            // доставать будем только из параметров _username и _password, так проще реализуется
            $decoder = new JsonDecode(true);
            $jsonData = $decoder->decode($request->getContent(), JsonEncoder::FORMAT);
            $username = isset($jsonData['_username']) ? $jsonData['_username'] : '';
            $password = isset($jsonData['_password']) ? $jsonData['_password'] : '';
        }
        return new SimpleAuthenticatorToken($username, $password, $providerKey);
    }
}
