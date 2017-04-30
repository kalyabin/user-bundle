<?php

namespace UserBundle\Listener;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Translation\IdentityTranslator;
use UserBundle\Controller\Response\SimpleAuthenticationJsonResponse;
use UserBundle\Security\Exception\SimpleAuthenticatorMessageException;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use UserBundle\Security\Token\SimpleAuthenticatorToken;

/**
 * Слушатель событий авторизации и ошибок авторизации.
 *
 * Если пользователь успешно авторизовался по простому логин-паролю - возвращает JSON-ответ.
 * Если произошла ошибка авторизации - возвращает JSON-ответ.
 *
 * Если у пользователя нет прав просмотра страницы - возвращает шаблон 403-й ошибки, если он уже авторизован, либо если он еще не авторизован - возвращает 401-й код ответа.
 *
 * @package UserBundle\Listener
 */
class AuthenticationListener implements AuthenticationSuccessHandlerInterface, AuthenticationFailureHandlerInterface
{
    /**
     * @var IdentityTranslator Переводчик
     */
    protected $translator;

    /**
     * @var TokenStorageInterface Хранилище авторизованных пользователей
     */
    protected $tokenStorage;

    /**
     * Конструктор
     *
     * @param IdentityTranslator $translator Переводчик для перевода сообщений
     */
    public function __construct(IdentityTranslator $translator, TokenStorageInterface $tokenStorage)
    {
        $this->tokenStorage = $tokenStorage;
        $this->translator = $translator;
    }

    /**
     * Обработать ошибку авторизации.
     *
     * Если произошла ошибка простой авторизации по логин-паролю - отправить JSON-ответ.
     *
     * @param Request $request
     * @param AuthenticationException $exception
     *
     * @return SimpleAuthenticationJsonResponse|null
     */
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception)
    {
        $response = new SimpleAuthenticationJsonResponse();

        if ($exception instanceof SimpleAuthenticatorMessageException) {
            $response->handleFailure($exception);
        } else {
            $response->handleFailRequest();
        }

        return $response;
    }

    /**
     * Обработать успешность авторизации
     *
     * Если пользователь успешно авторизовался по простому логин-паролю - отправить JSON-ответ.
     *
     * @param Request $request
     * @param TokenInterface $token
     *
     * @return SimpleAuthenticationJsonResponse
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token)
    {
        $response = new SimpleAuthenticationJsonResponse();

        if ($token instanceof SimpleAuthenticatorToken) {
            $response->handleSuccess($token);
            return $response;
        } else {
            $response->handleFailRequest();
        }

        return $response;
    }

    /**
     * Слушатель событий ошибок доступа.
     *
     * В случае недостаточности прав возвращает JSON 403, иначе - JSON 401
     *
     * @param GetResponseForExceptionEvent $event
     */
    public function onAccessDenied(GetResponseForExceptionEvent $event)
    {
        $exception = $event->getException();

        if ($exception instanceof AuthenticationCredentialsNotFoundException || $exception instanceof AccessDeniedException) {
            $isAuth = $this->tokenStorage->getToken()->getUser() instanceof UserInterface;

            $response = new JsonResponse([
                'title' => $isAuth ?
                    $this->translator->trans('Доступ запрещен') :
                    $this->translator->trans('Авторизация'),
                'message' => $isAuth ?
                    $this->translator->trans('У вас не достаточно прав для просмотра данной страницы.') :
                    $this->translator->trans('Для просмотра данной страницы необходимо авторизоваться.')
            ]);
            $response->setStatusCode($isAuth ? Response::HTTP_FORBIDDEN : Response::HTTP_UNAUTHORIZED);
            $event->setResponse($response);
        }
    }
}
