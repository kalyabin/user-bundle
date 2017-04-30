<?php

namespace UserBundle\Controller\Response;


use Symfony\Component\HttpFoundation\JsonResponse;
use UserBundle\Security\Exception\SimpleAuthenticatorMessageException;
use UserBundle\Security\Token\SimpleAuthenticatorToken;

/**
 * JSON-ответ при авторизации пользователя
 *
 * В случае, если пользователь был заблокирован - отдает флаг isLocked.
 * В случае, если требуется активация - отдает флаг isNeedActivation и userId.
 * В случае любой другой ошибки отдает errorMessage.
 * Если успешно авторизован - отдает loggedIn.
 *
 * @package UserBundle\Controller\Response
 */
class SimpleAuthenticationJsonResponse extends JsonResponse
{
    /**
     * @var array JSON-данные для отдачи в браузер
     */
    protected $jsonData = [
        'loggedIn' => false,
        'isLocked' => false,
        'isNeedActivation' => false,
        'errorMessage' => '',
        'userId' => null,
    ];

    /**
     * Зафиксировать успешность авторизации
     *
     * @param SimpleAuthenticatorToken $token
     */
    public function handleSuccess(SimpleAuthenticatorToken $token)
    {
        $this->jsonData['loggedIn'] = true;
        $this->jsonData['userId'] = $token->getUser()->getId();
        $this->setStatusCode(self::HTTP_OK);
        $this->setData($this->jsonData);
    }

    /**
     * Зафиксировать ошибку авторизации
     *
     * @param SimpleAuthenticatorMessageException $exception
     */
    public function handleFailure(SimpleAuthenticatorMessageException $exception)
    {
        $this->jsonData['loggedIn'] = false;
        $this->jsonData['isLocked'] = $exception->getIsLocked();
        $this->jsonData['isNeedActivation'] = $exception->getIsNeedActivation();
        $this->jsonData['errorMessage'] = $exception->getMessage();
        $this->jsonData['userId'] = $exception->getUserId();
        $this->setStatusCode(self::HTTP_UNAUTHORIZED);
        $this->setData($this->jsonData);
    }

    /**
     * Зафиксировать ошибочный запрос
     */
    public function handleFailRequest()
    {
        $this->setStatusCode(self::HTTP_BAD_REQUEST);
        $this->setData($this->jsonData);
    }
}
