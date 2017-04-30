<?php

namespace UserBundle\Event;

use Symfony\Component\EventDispatcher\Event;
use UserBundle\Entity\UserCheckerEntity;
use UserBundle\Entity\UserEntity;

/**
 * Базовое событие, срабатываемое при генерации кода подтверждения.
 *
 * Например, сразу же после регистрации пользователя, либо сразу же после запроса восстановления пароля.
 *
 * @package UserBundle\Event
 */
abstract class UserCheckerEvent extends Event
{

    /**
     * @var UserEntity Модель пользователя
     */
    protected $user;

    /**
     * @var UserCheckerEntity Модель кода подтверждения
     */
    protected $checker;

    /**
     * Конструктор
     *
     * @param UserEntity $user Модель зарегистрированного пользователя
     * @param UserCheckerEntity $checker Модель кода подтверждения
     */
    public function __construct(UserEntity $user, UserCheckerEntity $checker)
    {
        $this->user = $user;
        $this->checker = $checker;
    }

    /**
     * Получить модель пользователя
     *
     * @return UserEntity
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Получить модель кода подтверждения
     *
     * @return UserCheckerEntity
     */
    public function getChecker()
    {
        return $this->checker;
    }
}
