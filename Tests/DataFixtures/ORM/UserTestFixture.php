<?php

namespace UserBundle\Tests\DataFixtures\ORM;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use UserBundle\Entity\UserCheckerEntity;
use UserBundle\Entity\UserEntity;
use Symfony\Component\DependencyInjection\ContainerInterface;
use UserBundle\Utils\UserManager;

/**
 * Фикстуры пользователей для тестирования
 *
 * @package UserBundle\Tests\DataFixtures\ORM
 */
class UserTestFixture extends AbstractFixture implements ContainerAwareInterface
{
    /**
     * @var ContainerInterface
     */
    private $container;

    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * @inheritdoc
     */
    public function load(ObjectManager $manager)
    {
        /** @var UserManager $userService */
        $userService = $this->container->get('user.manager');

        // создать активного пользователя
        $activeUser = new UserEntity();

        $activeUser
            ->setStatus(UserEntity::STATUS_ACTIVE)
            ->setName('Testing user')
            ->setEmail('testing@test.ru')
            ->setPassword('testpassword')
            ->generateSalt();

        $userService->encodeUserPassword($activeUser, $activeUser->getPassword());

        // создать неактивного пользователя с кодом подтверждения
        $inactiveUser = new UserEntity();

        $inactiveUser
            ->setStatus(UserEntity::STATUS_NEED_ACTIVATION)
            ->setName('Need activation user')
            ->setEmail('inactive@test.ru')
            ->setPassword('testpassword')
            ->generateSalt();

        $userService->encodeUserPassword($inactiveUser, $inactiveUser->getPassword());

        $checker = new UserCheckerEntity();

        $checker
            ->setType(UserCheckerEntity::TYPE_ACTIVATION_CODE)
            ->setAttempts(0)
            ->setUser($inactiveUser)
            ->generateCode();

        $inactiveUser->addChecker($checker);

        // создать заблокированного пользователя
        $lockedUser = new UserEntity();

        $lockedUser
            ->setStatus(UserEntity::STATUS_LOCKED)
            ->setName('Locked user')
            ->setEmail('locked@test.ru')
            ->setPassword('testpassword')
            ->generateSalt();

        $userService->encodeUserPassword($lockedUser, $lockedUser->getPassword());

        $manager->persist($lockedUser);
        $manager->persist($activeUser);
        $manager->persist($inactiveUser);
        $manager->flush();

        $this->addReference('locked-user', $lockedUser);
        $this->addReference('inactive-user', $inactiveUser);
        $this->addReference('active-user', $activeUser);
    }
}
