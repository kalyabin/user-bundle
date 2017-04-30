<?php

namespace UserBundle\Utils;

use UserBundle\Entity\UserCheckerEntity;
use UserBundle\Entity\UserEntity;
use Symfony\Component\Templating\EngineInterface;
use UserBundle\Event\UserChangedPasswordEvent;
use UserBundle\Event\UserChangeEmailEvent;
use UserBundle\Event\UserRegistrationEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use UserBundle\Event\UserRememberPasswordEvent;

/**
 * Мейлер для системных уведомлений пользователю:
 * - подтверждение емейла, восстановление пароля и тому подобное.
 *
 * @package UserBundle\Utils
 */
class UserSystemMailManager implements EventSubscriberInterface
{
    /**
     * @var \Swift_Mailer Мейлер для отправки почты
     */
    protected $mailer;

    /**
     * @var EngineInterface Шаблонизатор
     */
    protected $templating;

    /**
     * @var string Ящик для отправки писем по умолчанию (заголовок From:)
     */
    protected $from;

    /**
     * @var \Swift_Message
     */
    protected $lastMessage;

    /**
     * Конструктор
     *
     * @param \Swift_Mailer $mailer Мейлер для отправки почты
     * @param EngineInterface $templating Шаблонизатор писем
     * @param string $from Отправитель писем (по умолчанию - без отправителя)
     */
    public function __construct(\Swift_Mailer $mailer, EngineInterface $templating, $from = null)
    {
        $this->mailer = $mailer;
        $this->templating = $templating;
        $this->from = $from;
    }

    /**
     * Установить отправителя
     *
     * @param string $from
     */
    public function setFrom($from)
    {
        $this->from = $from;
    }

    /**
     * Подписка на события
     *
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            UserRegistrationEvent::NAME => 'onUserRegistration',
            UserRememberPasswordEvent::NAME => 'onUserRememberPassword',
            UserChangedPasswordEvent::NAME => 'onUserChangePassword',
            UserChangeEmailEvent::NAME => 'onUserChangeEmail',
        ];
    }

    /**
     * Отправка уже сформированного письма
     *
     * @param \Swift_Message $message Сообщение с сабжектом и телом
     * @param string $email E-mail, на который надо отправить письмо
     *
     * @return int
     */
    protected function sendMessage(\Swift_Message $message, $email)
    {
        $message
            ->setFrom($this->from)
            ->setTo($email);

        $this->lastMessage = $message;

        return $this->mailer->send($message);
    }

    /**
     * Отправить письмо об активации аккаунта пользователю
     *
     * @param UserEntity $user Модель зарегистрированного пользователя
     * @param UserCheckerEntity $checker Модель кода подтверждения
     *
     * @return integer
     */
    public function sendActivationEmail(UserEntity $user, UserCheckerEntity $checker)
    {
        $message = \Swift_Message::newInstance()
            ->setSubject('Активация аккаунта')
            ->setBody(
                $this->templating->render('@user_emails/registration.html.twig', [
                    'user' => $user,
                    'checker' => $checker,
                ]),
                'text/html'
            );

        return $this->sendMessage($message, $user->getEmail());
    }

    /**
     * Отправить запрос на изменение e-mail в аккаунте.
     *
     * @param UserEntity $user Модель пользователя, для которого меняется e-mail
     * @param UserCheckerEntity $checker Модель кода подтверждения
     * @param string $newEmail Новый e-mail, на который отправить код подтверждения
     *
     * @return int
     */
    public function sendChangeEmailConfirmation(UserEntity $user, UserCheckerEntity $checker, $newEmail)
    {
        $message = \Swift_Message::newInstance()
            ->setSubject('Смена e-mail')
            ->setBody(
                $this->templating->render('@user_emails/change_email.html.twig', [
                    'user' => $user,
                    'checker' => $checker,
                    'newEmail' => $newEmail,
                ]),
                'text/html'
            );

        return $this->sendMessage($message, $newEmail);
    }

    /**
     * Отправить письмо о восстановлении пароля
     *
     * @param UserEntity        $user Модель пользователя
     * @param UserCheckerEntity $checker Модель кода подтверждения
     *
     * @return int
     */
    public function sendRememberPasswordEmail(UserEntity $user, UserCheckerEntity $checker)
    {
        $message = \Swift_Message::newInstance()
            ->setSubject('Восстановление пароля')
            ->setBody(
                $this->templating->render('@user_emails/remember_password.html.twig', [
                    'user' => $user,
                    'checker' => $checker,
                ]),
                'text/html'
            );

        return $this->sendMessage($message, $user->getEmail());
    }

    /**
     * Отправить письмо о том, что установлен новый пароль
     *
     * @param UserEntity $user Модель пользователя
     * @param string $newPassword Новый установленный пароль
     *
     * @return int
     */
    public function sendNewPassword(UserEntity $user, $newPassword)
    {
        $message = \Swift_Message::newInstance()
            ->setSubject('Установлен новый пароль')
            ->setBody(
                $this->templating->render('@user_emails/set_new_password.html.twig', [
                    'user' => $user,
                    'newPassword' => $newPassword
                ]),
                'text/html'
            );

        return $this->sendMessage($message, $user->getEmail());
    }

    /**
     * Получить последнее сообщение
     *
     * @return \Swift_Message
     */
    public function getLastMessage()
    {
        return $this->lastMessage;
    }

    /**
     * Стереть последнее сообщение
     */
    public function clearLastMessage()
    {
        $this->lastMessage = null;
    }

    /**
     * Подписка на событие о регистрации пользователя
     *
     * @param UserRegistrationEvent $event
     */
    public function onUserRegistration(UserRegistrationEvent $event)
    {
        $this->sendActivationEmail($event->getUser(), $event->getChecker());
    }

    /**
     * Подписка на событие о восстановлении пароля
     *
     * @param UserRememberPasswordEvent $event
     */
    public function onUserRememberPassword(UserRememberPasswordEvent $event)
    {
        $this->sendRememberPasswordEmail($event->getUser(), $event->getChecker());
    }

    /**
     * Подписка на событие об изменении пароля
     *
     * @param UserChangedPasswordEvent $event
     */
    public function onUserChangePassword(UserChangedPasswordEvent $event)
    {
        $this->sendNewPassword($event->getUser(), $event->getNewPassword());
    }

    /**
     * Подписка на событие об изменении e-mail
     *
     * @param UserChangeEmailEvent $event
     */
    public function onUserChangeEmail(UserChangeEmailEvent $event)
    {
        $this->sendChangeEmailConfirmation($event->getUser(), $event->getChecker(), $event->getNewEmail());
    }
}
