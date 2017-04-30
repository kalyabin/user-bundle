<?php

namespace UserBundle\Event;

use UserBundle\Event\UserCheckerEvent;
use UserBundle\Entity\UserEntity;

/**
 * Событие на смену e-mail пользователем
 *
 * @package UserBundle\Event
 */
class UserChangeEmailEvent extends UserCheckerEvent
{
    /**
     * Название события
     */
    const NAME = 'user.change_email';

    /**
     * @var string Новый e-mail
     */
    protected $newEmail;

    /**
     * Установить новый e-mail
     *
     * @param string $newEmail
     */
    public function setNewEmail($newEmail)
    {
        $this->newEmail = $newEmail;
    }

    /**
     * Получить новый e-mail
     *
     * @return string
     */
    public function getNewEmail()
    {
        return $this->newEmail;
    }
}
