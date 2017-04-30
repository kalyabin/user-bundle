<?php

namespace UserBunde\Tests\Utils;

use Doctrine\Common\Persistence\ObjectManager;
use UserBundle\Event\UserChangedPasswordEvent;
use UserBundle\Event\UserChangeEmailEvent;
use UserBundle\Utils\UserSystemMailManager;
use Doctrine\Common\DataFixtures\ReferenceRepository;
use Liip\FunctionalTestBundle\Test\WebTestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use UserBundle\Tests\DataFixtures\ORM\UserTestFixture;
use UserBundle\Entity\UserCheckerEntity;
use UserBundle\Entity\UserEntity;
use UserBundle\Event\UserRegistrationEvent;
use UserBundle\Event\UserRememberPasswordEvent;

/**
 * Тестирование класса UserSystemMailManager
 *
 * @package UserBundle\Tests\Utils
 */
class UserSystemMailManagerTest extends WebTestCase
{
    /**
     * @var UserSystemMailManager
     */
    protected $manager;

    /**
     * @var ReferenceRepository
     */
    protected $fixtures;

    /**
     * @var ObjectManager
     */
    protected $em;

    protected function setUp()
    {
        parent::setUp();

        static::bootKernel();

        $container = $this->getContainer();

        $this->fixtures = $this->loadFixtures([
            UserTestFixture::class
        ])->getReferenceRepository();

        $this->manager = new UserSystemMailManager($container->get('mailer'), $container->get('templating'), 'testing@test.ru');

        $this->em = $this->getContainer()->get('doctrine.orm.entity_manager');
    }

    /**
     * Проверить, что последнее письмо ушло от указанного емаила на указанный ящик
     *
     * @param string $to
     * @param string $from
     */
    protected function assertLastMessageEmails($to, $from)
    {
        $lastMessage = $this->manager->getLastMessage();

        $this->assertInstanceOf(\Swift_Message::class, $lastMessage);
        $this->assertArrayHasKey($to, $lastMessage->getTo());
        $this->assertArrayHasKey($from, $lastMessage->getFrom());
    }

    /**
     * Проверить, что последнее письмо содержит указанную строку
     *
     * @param string $string
     */
    protected function assertLastMessageContains($string)
    {
        $lastMessage = $this->manager->getLastMessage();

        $this->assertInstanceOf(\Swift_Message::class, $lastMessage);

        $this->assertContains($string, $lastMessage->getBody());
    }

    /**
     * Тестирование отправки писем об изменении пароля
     *
     * @covers UserSystemMailManager::sendChangeEmailConfirmation()
     */
    public function testSendChangeEmailConfirmation()
    {
        /** @var UserEntity $user */
        $user = $this->fixtures->getReference('active-user');

        $newEmail = 'newemail@test.ru';

        // создать код подтверждения
        $checker = new UserCheckerEntity();

        $checker
            ->setUser($user)
            ->setType(UserCheckerEntity::TYPE_CHANGE_EMAIL)
            ->setJsonData(['newEmail' => $newEmail])
            ->generateCode();

        $user->addChecker($checker);

        $this->em->persist($checker);
        $this->em->persist($user);

        $this->em->flush();

        $result = $this->manager->sendChangeEmailConfirmation($user, $checker, $newEmail);

        $this->assertInternalType('integer', $result);
        $this->assertGreaterThan(0, $result);

        $this->assertLastMessageEmails($newEmail, 'testing@test.ru');

        $url = $this->getUrl('user.change_email_confirmation', [
            'checkerId' => $checker->getId(),
            'code' => $checker->getCode(),
        ]);

        $this->assertLastMessageContains($url);
    }

    /**
     * Тестирование подписки на событие о запросе изменения e-mail
     *
     * @covers UserSystemMailManager::sendChangeEmailConfirmation()
     * @covers UserSystemMailManager::onUserChangeEmail()
     */
    public function testSendChangeEmailConfirmationSubscriber()
    {
        /** @var UserEntity $user */
        $user = $this->fixtures->getReference('active-user');

        $newEmail = 'newemail@test.ru';

        // создать код подтверждения
        $checker = new UserCheckerEntity();

        $checker
            ->setUser($user)
            ->setType(UserCheckerEntity::TYPE_CHANGE_EMAIL)
            ->setJsonData(['newEmail' => $newEmail])
            ->generateCode();

        $user->addChecker($checker);

        $this->em->persist($checker);
        $this->em->persist($user);

        $this->em->flush();

        $event = new UserChangeEmailEvent($user, $checker);
        $event->setNewEmail($newEmail);

        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber($this->manager);
        $dispatcher->dispatch(UserChangeEmailEvent::NAME, $event);

        $this->assertLastMessageEmails($newEmail, 'testing@test.ru');

        $url = $this->getUrl('user.change_email_confirmation', [
            'checkerId' => $checker->getId(),
            'code' => $checker->getCode(),
        ]);

        $this->assertLastMessageContains($url);
    }

    /**
     * Тестирование отправки письма пользователю о регистрации
     *
     * @covers UserSystemMailManager::sendActivationEmail()
     * @covers UserSystemMailManager::setFrom()
     */
    public function testUserRegistration()
    {
        /** @var UserEntity $user */
        $user = $this->fixtures->getReference('active-user');

        $checker = new UserCheckerEntity();
        $checker
            ->setType(UserCheckerEntity::TYPE_ACTIVATION_CODE)
            ->setUser($user)
            ->generateCode();

        $user->addChecker($checker);

        $this->em->persist($user);

        $this->em->flush();

        $result = $this->manager->sendActivationEmail($user, $checker);

        $this->assertInternalType('integer', $result);
        $this->assertGreaterThan(0, $result);

        $this->assertLastMessageEmails($user->getEmail(), 'testing@test.ru');

        $url = $this->getUrl('registration.activate', [
            'checkerId' => $checker->getId(),
            'code' => $checker->getCode()
        ]);

        $this->assertLastMessageContains($url);
    }

    /**
     * Тестирование подписчиков на события
     *
     * @covers UserSystemMailManager::getSubscribedEvents()
     */
    public function testUserRegistrationSubscriber()
    {
        /** @var UserEntity $user */
        $user = $this->fixtures->getReference('active-user');

        $checker = new UserCheckerEntity();
        $checker
            ->setType(UserCheckerEntity::TYPE_ACTIVATION_CODE)
            ->setUser($user)
            ->generateCode();

        $user->addChecker($checker);

        $this->em->persist($user);

        $this->em->flush();

        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber($this->manager);
        $dispatcher->dispatch(UserRegistrationEvent::NAME, new UserRegistrationEvent($user, $checker));

        $this->assertLastMessageEmails($user->getEmail(), 'testing@test.ru');

        $url = $this->getUrl('registration.activate', [
            'checkerId' => $checker->getId(),
            'code' => $checker->getCode()
        ]);

        $this->assertLastMessageContains($url);
    }

    /**
     * Тестирование отправки письма о восстановлении пароля
     *
     * @covers UserSystemMailManager::sendRememberPasswordEmail()
     */
    public function testUserRememberPassword()
    {
        /** @var UserEntity $user */
        $user = $this->fixtures->getReference('active-user');

        $checker = new UserCheckerEntity();
        $checker
            ->setType(UserCheckerEntity::TYPE_REMEMBER_PASSWORD)
            ->setUser($user)
            ->generateCode();

        $user->addChecker($checker);

        $this->em->persist($user);

        $this->em->flush();

        $result = $this->manager->sendRememberPasswordEmail($user, $checker);

        $this->assertInternalType('integer', $result);
        $this->assertGreaterThan(0, $result);

        $this->assertLastMessageEmails($user->getEmail(), 'testing@test.ru');

        $url = $this->getUrl('remember_password.change_password_form', [
            'checkerId' => $checker->getId(),
            'code' => $checker->getCode(),
        ]);

        $this->assertLastMessageContains($url);
    }

    /**
     * Тестирование подписчиков на события
     *
     * @covers UserSystemMailManager::getSubscribedEvents()
     */
    public function testUserRememberPasswordSubscriber()
    {
        /** @var UserEntity $user */
        $user = $this->fixtures->getReference('active-user');

        $checker = new UserCheckerEntity();
        $checker
            ->setType(UserCheckerEntity::TYPE_REMEMBER_PASSWORD)
            ->setUser($user)
            ->generateCode();

        $user->addChecker($checker);

        $this->em->persist($user);

        $this->em->flush();

        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber($this->manager);
        $dispatcher->dispatch(UserRememberPasswordEvent::NAME, new UserRememberPasswordEvent($user, $checker));

        $this->assertLastMessageEmails($user->getEmail(), 'testing@test.ru');

        $url = $this->getUrl('remember_password.change_password_form', [
            'checkerId' => $checker->getId(),
            'code' => $checker->getCode(),
        ]);

        $this->assertLastMessageContains($url);
    }

    /**
     * Тестирование отправки письма об изменении пароля
     *
     * @covers UserSystemMailManager::sendNewPassword()
     */
    public function testSendNewPassword()
    {
        /** @var UserEntity $user */
        $user = $this->fixtures->getReference('active-user');
        $newPassword = 'newtestpassword';

        $result = $this->manager->sendNewPassword($user, $newPassword);

        $this->assertInternalType('integer', $result);
        $this->assertGreaterThan(0, $result);

        $this->assertLastMessageEmails($user->getEmail(), 'testing@test.ru');

        $this->assertLastMessageContains($newPassword);
    }

    /**
     * Тестирование подписчика на отправку письма об изменении пароля
     *
     * @covers UserSystemMailManager::getSubscribedEvents()
     */
    public function testSendNewPasswordSubscriber()
    {
        /** @var UserEntity $user */
        $user = $this->fixtures->getReference('active-user');
        $newPassword = 'newtestpassword';

        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber($this->manager);
        $dispatcher->dispatch(UserChangedPasswordEvent::NAME, new UserChangedPasswordEvent($user, $newPassword));

        $this->assertLastMessageEmails($user->getEmail(), 'testing@test.ru');

        $this->assertLastMessageContains($newPassword);
    }
}
