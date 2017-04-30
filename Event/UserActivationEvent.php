<?php

namespace UserBundle\Event;

use Symfony\Component\EventDispatcher\Event;
use UserBundle\Entity\UserEntity;

/**
 * Событие на активацию аккаунта.
 *
 * Срабатывает сразу же, как только пользователь подтвердил свой e-mail и его статус переведен в "Активен".
 *
 * На данном этапе не требуется никаких подтверждений.
 *
 * @package UserBundle\Event
 */
class UserActivationEvent extends Event
{
    /**
     * Название события
     */
    const NAME = 'user.activation';

    /**
     * @var UserEntity Модель пользователя
     */
    protected $user;

    /**
     * Конструктор
     *
     * @param UserEntity $user
     */
    public function __construct(UserEntity $user)
    {
        $this->user = $user;
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
}
