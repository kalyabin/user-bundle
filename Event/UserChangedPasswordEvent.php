<?php

namespace UserBundle\Event;


use Symfony\Component\EventDispatcher\Event;
use UserBundle\Entity\UserEntity;

/**
 * Событие при изменении пароля
 *
 * @package UserBundle\Event
 */
class UserChangedPasswordEvent extends Event
{
    /**
     * Название события
     */
    const NAME = 'user.changed_password';

    /**
     * @var UserEntity Модель пользователя, для которого был изменен пароль
     */
    protected $user;

    /**
     * @var string Новый установленный пароль
     */
    protected $newPassword;

    /**
     * Конструктор
     *
     * @param UserEntity $user Пользователь, для которого был изменен пароль
     * @param string $newPassword Новый пароль
     */
    public function __construct(UserEntity $user, $newPassword)
    {
        $this->user = $user;
        $this->newPassword = $newPassword;
    }

    /**
     * Получить пользователя
     *
     * @return UserEntity
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Получить новый пароль
     *
     * @return string
     */
    public function getNewPassword()
    {
        return $this->newPassword;
    }
}
