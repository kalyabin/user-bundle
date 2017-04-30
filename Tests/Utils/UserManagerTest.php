<?php

namespace UserBunde\Tests\Utils;

use Doctrine\Common\DataFixtures\ReferenceRepository;
use Doctrine\Common\Persistence\ObjectManager;
use Liip\FunctionalTestBundle\Test\WebTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use UserBundle\Tests\DataFixtures\ORM\UserTestFixture;
use UserBundle\Entity\UserCheckerEntity;
use UserBundle\Entity\UserEntity;
use UserBundle\Event\UserActivationEvent;
use UserBundle\Event\UserChangedPasswordEvent;
use UserBundle\Event\UserChangeEmailEvent;
use UserBundle\Event\UserRegistrationEvent;
use UserBundle\Event\UserRememberPasswordEvent;
use UserBundle\Entity\Repository\UserCheckerRepository;
use UserBundle\Entity\Repository\UserRepository;
use UserBundle\Utils\UserManager;

/**
 * Тестирование сервиса для работы с пользователями
 *
 * @package UserBundle\Tests\Service
 */
class UserManagerTest extends WebTestCase
{
    /**
     * @var UserManager
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

        $container = $this->getContainer();

        $this->em = $container->get('doctrine.orm.entity_manager');

        $this->loadFixtures([]);
        $this->fixtures = $this->loadFixtures([UserTestFixture::class])->getReferenceRepository();

        $this->manager = new UserManager($container->get('security.encoder_factory'), $container->get('doctrine.orm.entity_manager'), $container->get('event_dispatcher'));
    }

    /**
     * Тестирование метода encodeUserPassword
     *
     * @covers UserManager::encodeUserPassword()
     */
    public function testEncodeUserPassword()
    {
        $user = new UserEntity();

        $this->assertEmpty($user->getPassword());

        $this->manager->encodeUserPassword($user, 'test password');

        $this->assertNotEmpty($user->getPassword());
        $this->assertNotEquals('test password', $user->getPassword());
    }

    /**
     * Проверка метода registerUser
     *
     * @covers UserManager::registerUser()
     * @covers UserEntity::isActive()
     * @covers UserEntity::isLocked()
     * @covers UserEntity::isNeedActivation()
     *
     * @depends testEncodeUserPassword
     */
    public function testRegisterUser()
    {
        $user = new UserEntity();

        $user
            ->setName('Tester')
            ->setEmail('test@test.ru')
            ->setPassword('testpassword')
            ->setStatus(UserEntity::STATUS_ACTIVE);

        $this->assertTrue($user->isActive());
        $this->assertFalse($user->isLocked());
        $this->assertFalse($user->isNeedActivation());

        // должно создаться событие
        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = $this->getContainer()->get('event_dispatcher');
        $testCase = $this;
        $dispatcher->addListener(UserRegistrationEvent::NAME, function(UserRegistrationEvent $event) use ($user, $testCase, &$eventTriggered) {
            $testCase->assertInstanceOf(UserEntity::class, $event->getUser());
            $testCase->assertInstanceOf(UserCheckerEntity::class, $event->getChecker());
            $testCase->assertNotEmpty($event->getChecker()->getCode());
            $testCase->assertEquals($event->getChecker()->getType(), UserCheckerEntity::TYPE_ACTIVATION_CODE);
            $testCase->assertEquals($user->getId(), $event->getUser()->getId());
            $eventTriggered = true;
        });

        $this->assertInstanceOf(UserEntity::class, $this->manager->registerUser($user));
        $this->assertGreaterThan(0, $user->getId());

        // событие было создано
        $this->assertTrue($eventTriggered);

        // сменился статус на "Требует активации"
        $this->assertFalse($user->isActive());
        $this->assertFalse($user->isLocked());
        $this->assertTrue($user->isNeedActivation());

        // должна была создаться модель активации
        $checker = $user->getCheckerByType(UserCheckerEntity::TYPE_ACTIVATION_CODE);
        $this->assertInstanceOf(UserCheckerEntity::class, $checker);
        $this->assertNotEmpty($checker->getCode());

        // пароль должен быть закодирован
        $this->assertNotEquals('testpassword', $user->getPassword());
    }

    /**
     * Тестирование запроса на смену e-mail
     *
     * @covers UserManager::changeUserEmail()
     */
    public function testChangeEmail()
    {
        /** @var UserEntity $user */
        $user = $this->fixtures->getReference('active-user');

        $this->assertEmpty($user->getCheckerByType(UserCheckerEntity::TYPE_CHANGE_EMAIL));

        $newEmail = 'newemail@test.ru';

        // должно создаться событие
        $eventTriggered = false;
        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = $this->getContainer()->get('event_dispatcher');
        $testCase = $this;
        $dispatcher->addListener(UserChangeEmailEvent::NAME, function(UserChangeEmailEvent $event) use ($newEmail, $user, $testCase, &$eventTriggered) {
            $testCase->assertInstanceOf(UserEntity::class, $event->getUser());
            $testCase->assertEquals($event->getUser()->getId(), $user->getId());
            $testCase->assertInstanceOf(UserCheckerEntity::class, $event->getChecker());
            $testCase->assertNotEmpty($event->getChecker()->getCode());
            $testCase->assertEquals($event->getChecker()->getType(), UserCheckerEntity::TYPE_CHANGE_EMAIL);
            $testCase->assertEquals($newEmail, $event->getNewEmail());
            $eventTriggered = true;
        });

        $this->manager->changeUserEmail($user, $newEmail);

        $checker = $user->getCheckerByType(UserCheckerEntity::TYPE_CHANGE_EMAIL);
        $this->assertInstanceOf(UserCheckerEntity::class, $checker);
        $this->assertNotEmpty($checker->getCode());

        // событие было создано
        $this->assertTrue($eventTriggered);
    }

    /**
     * Тестирование обновления e-mail на основе кода проверки
     *
     * @covers UserManager::updateUserEmailFromChecker()
     */
    public function testUpdateUserEmailFromChecker()
    {
        /** @var UserEntity $user */
        $user = $this->fixtures->getReference('active-user');

        $this->assertEmpty($user->getCheckerByType(UserCheckerEntity::TYPE_CHANGE_EMAIL));

        $newEmail = 'newemail@test.ru';

        $this->assertNotEquals($newEmail, $user->getEmail());

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

        // изменяем e-mail
        $this->assertTrue($this->manager->updateUserEmailFromChecker($user));
        $this->assertEquals($newEmail, $user->getEmail());
        $this->assertEmpty($user->getCheckerByType(UserCheckerEntity::TYPE_CHANGE_EMAIL));

        // дальнейший вызов метод ничего не меняет
        $this->assertFalse($this->manager->updateUserEmailFromChecker($user));
    }

    /**
     * Тестирование напоминания пароля
     *
     * @covers UserManager::rememberPassword()
     */
    public function testRememberPassword()
    {
        /** @var UserEntity $user */
        $user = $this->fixtures->getReference('active-user');

        $this->assertEmpty($user->getChecker());

        // должно создаться событие
        $eventTriggered = false;
        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = $this->getContainer()->get('event_dispatcher');
        $testCase = $this;
        $dispatcher->addListener(UserRememberPasswordEvent::NAME, function(UserRememberPasswordEvent $event) use ($user, $testCase, &$eventTriggered) {
            $testCase->assertInstanceOf(UserEntity::class, $event->getUser());
            $testCase->assertInstanceOf(UserCheckerEntity::class, $event->getChecker());
            $testCase->assertNotEmpty($event->getChecker()->getCode());
            $testCase->assertEquals($event->getChecker()->getType(), UserCheckerEntity::TYPE_REMEMBER_PASSWORD);
            $testCase->assertEquals($user->getId(), $event->getUser()->getId());
            $eventTriggered = true;
        });

        $this->manager->rememberPassword($user);

        $checker = $user->getCheckerByType(UserCheckerEntity::TYPE_REMEMBER_PASSWORD);
        $this->assertInstanceOf(UserCheckerEntity::class, $checker);
        $this->assertNotEmpty($checker->getCode());

        // событие было создано
        $this->assertTrue($eventTriggered);
    }

    /**
     * Изменение пароля
     *
     * @covers UserManager::changePassword()
     *
     * @depends testEncodeUserPassword
     */
    public function testChangePassword()
    {
        /** @var UserEntity $user */
        $user = $this->fixtures->getReference('active-user');

        $currentPassword = $user->getPassword();

        // создать чекер, чтобы убедиться что он удалится
        $checker = new UserCheckerEntity();

        $checker
            ->setType(UserCheckerEntity::TYPE_REMEMBER_PASSWORD)
            ->setUser($user)
            ->generateCode();

        $user->addChecker($checker);

        $this->em->persist($user);
        $this->em->flush();

        $newPassword = 'newtestpassword';

        // должно создаться событие
        $eventTriggered = false;
        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = $this->getContainer()->get('event_dispatcher');
        $testCase = $this;
        $dispatcher->addListener(UserChangedPasswordEvent::NAME, function(UserChangedPasswordEvent $event) use ($user, $testCase, &$eventTriggered, $newPassword) {
            $testCase->assertInstanceOf(UserEntity::class, $event->getUser());
            $this->assertEquals($event->getNewPassword(), $newPassword);
            $testCase->assertEquals($user->getId(), $event->getUser()->getId());
            $eventTriggered = true;
        });

        $this->manager->changePassword($user, $newPassword);

        // чекер удалился
        $this->assertEmpty($user->getCheckerByType(UserCheckerEntity::TYPE_REMEMBER_PASSWORD));
        // новый пароль установился
        $this->assertNotEquals($newPassword, $currentPassword);

        $this->assertTrue($eventTriggered);
    }

    /**
     * Активация пользователя
     *
     * @covers UserManager::activateUser()
     */
    public function testActivateUser()
    {
        /** @var UserEntity $user */
        $user = $this->fixtures->getReference('inactive-user');

        $this->assertFalse($user->isActive());
        $this->assertNotEmpty($user->getChecker());
        $this->assertInstanceOf(UserCheckerEntity::class, $user->getCheckerByType(UserCheckerEntity::TYPE_ACTIVATION_CODE));

        // должно создаться событие
        $eventTriggered = false;
        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = $this->getContainer()->get('event_dispatcher');
        $testCase = $this;
        $dispatcher->addListener(UserActivationEvent::NAME, function(UserActivationEvent $event) use ($user, $testCase, &$eventTriggered) {
            $testCase->assertInstanceOf(UserEntity::class, $event->getUser());
            $testCase->assertEquals($user->getId(), $event->getUser()->getId());
            $eventTriggered = true;
        });

        $this->assertInstanceOf(UserEntity::class, $this->manager->activateUser($user));

        $this->assertTrue($eventTriggered);

        /** @var UserRepository $repository */
        $repository = $this->em->getRepository(UserEntity::class);

        $expectedUser = $repository->findOneById($user->getId());

        $this->assertInstanceOf(UserEntity::class, $expectedUser);
        $this->assertEquals($expectedUser->getId(), $user->getId());
        $this->assertTrue($expectedUser->isActive());
        $this->assertEmpty($expectedUser->getChecker());
        $this->assertEmpty($user->getCheckerByType(UserCheckerEntity::TYPE_ACTIVATION_CODE));
    }

    /**
     * Тестирование проверки кода подтверждения
     *
     * @covers UserManager::confirmChecker()
     */
    public function testConfirmChecker()
    {
        /** @var UserEntity $user */
        $user = $this->fixtures->getReference('inactive-user');

        $checker = $user->getCheckerByType(UserCheckerEntity::TYPE_ACTIVATION_CODE);

        $this->assertInstanceOf(UserCheckerEntity::class, $checker);

        $expectedUser = $this->manager->confirmChecker($checker, $checker->getType(), $checker->getCode());

        $this->assertInstanceOf(UserEntity::class, $expectedUser);
        $this->assertEquals($expectedUser->getId(), $user->getId());
        $this->assertEquals(0, $checker->getAttempts());
    }

    /**
     * Тестирование удаления кода подтверждения после всех попыток
     *
     * @covers UserManager::confirmChecker()
     */
    public function testConfirmCheckerRemove()
    {
        /** @var UserEntity $user */
        $user = $this->fixtures->getReference('inactive-user');

        $checker = $user->getCheckerByType(UserCheckerEntity::TYPE_ACTIVATION_CODE);

        $this->assertInstanceOf(UserCheckerEntity::class, $checker);

        // сначала пробуем MAX_ATTEMPTS раз получить модель и проверить неправильным кодом
        // модель должна быть в конце концов быть удалена
        /** @var \UserBundle\Entity\Repository\UserCheckerRepository $repository */
        $repository = $this->em->getRepository('UserBundle:UserCheckerEntity');

        $checkerId = $checker->getId();

        for ($x = 0; $x < UserCheckerEntity::MAX_ATTEMPTS; $x++) {
            $checker = $repository->findOneById($checkerId);
            $this->assertInstanceOf(UserCheckerEntity::class, $checker);
            $this->assertEquals($x, $checker->getAttempts());

            $result = $this->manager->confirmChecker($checker, 'wrong type', 'wrong code');

            $this->assertNull($result);
            $this->assertEquals($x + 1, $checker->getAttempts());

            $this->em->clear();
        }

        // последний раз вместо проверки должны получить null
        $checker = $repository->findOneById($checkerId);
        $this->assertNull($checker);
    }
}
