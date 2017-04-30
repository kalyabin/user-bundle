<?php

namespace UserBundle\Security\Exception;

use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;

/**
 * Класс исключений для обычной регистрации по логин паролям
 *
 * @package UserBundle\Security\Exception
 */
class SimpleAuthenticatorMessageException extends CustomUserMessageAuthenticationException
{
    /**
     * @var bool Флаг заблокированности пользователя
     */
    protected $isLocked = false;

    /**
     * @var bool Флаг необходимости активации
     */
    protected $isNeedActivation = false;

    /**
     * @var integer Идентификатор найденного пользователя
     */
    protected $userId;

    /**
     * Установить флаг заблокированности пользователя
     */
    public function setIsLocked()
    {
        $this->isLocked = true;
    }

    /**
     * Установить флаг необходимости активации
     */
    public function setIsNeedActivation()
    {
        $this->isNeedActivation = true;
    }

    /**
     * Установить идентификатор найденного пользователя
     *
     * @param integer $userId
     */
    public function setUserId($userId)
    {
        $this->userId = $userId;
    }

    /**
     * Получить флаг заблокированности
     *
     * @return bool
     */
    public function getIsLocked()
    {
        return $this->isLocked;
    }

    /**
     * Получить флаг необходимости активации
     *
     * @return bool
     */
    public function getIsNeedActivation()
    {
        return $this->isNeedActivation;
    }

    /**
     * Получить идентификатор пользователя
     *
     * @return int
     */
    public function getUserId()
    {
        return $this->userId;
    }
}
