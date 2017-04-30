<?php

namespace UserBundle\Tests\Validator\Constraints;

use Doctrine\Common\Persistence\ObjectManager;
use Liip\FunctionalTestBundle\Test\WebTestCase;
use UserBundle\Tests\DataFixtures\ORM\UserTestFixture;
use UserBundle\Entity\UserEntity;
use UserBundle\Validator\Constraints\UserEmail;
use UserBundle\Validator\Constraints\UserEmailValidator;
use PHPUnit_Framework_MockObject_MockObject;

/**
 * Тестирование валидатора по e-mail
 *
 * @package UserBundle\Tests\Validator\Constraints
 */
class UserEmailValidatorTest extends WebTestCase
{
    /**
     * @var ObjectManager
     */
    protected $em;

    /**
     * @inheritdoc
     */
    protected function setUp()
    {
        parent::setUp();

        $this->em = $this->getContainer()->get('doctrine')->getManager();
    }

    /**
     * Создать пользователя с e-mailом
     *
     * @param string $email
     *
     * @return UserEntity
     */
    protected function createUser($email)
    {
        $user = new UserEntity();

        $user
            ->setEmail($email)
            ->setName('Test name')
            ->setPassword('test password')
            ->generateSalt()
            ->setStatus(UserEntity::STATUS_ACTIVE);

        $this->em->persist($user);

        $this->em->flush();

        $this->assertGreaterThan(0, $user->getId());

        return $user;
    }

    /**
     * Проверка существования емаила
     */
    public function testIsEmailExists()
    {
        $fixtures = $this->loadFixtures([UserTestFixture::class])->getReferenceRepository();

        $email = $fixtures->getReference('active-user')->getEmail();
        $failEmail = 'non-existent@email.ru';

        /**
         * Проверка на существование емаила: констрейнт не должен сработать
         */
        $constraint = new UserEmail();
        $constraint->needExists = true;
        $constraint->message = 'Пользователь с таким e-mail не существует';

        $validator = new UserEmailValidator($this->em);

        /** @var PHPUnit_Framework_MockObject_MockObject $context */
        $context = $this->createMock('Symfony\Component\Validator\Context\ExecutionContext');

        $validator->initialize($context);

        $context->expects($this->never())
            ->method('addViolation');

        $validator->validate($email, $constraint);

        /**
         * Проверка на существование емаила: констрейнт должен сработать
         */
        $constraint = new UserEmail();
        $constraint->needExists = true;
        $constraint->message = 'Пользователь с таким e-mail не существует';

        $validator = new UserEmailValidator($this->em);

        /** @var PHPUnit_Framework_MockObject_MockObject $context */
        $context = $this->createMock('Symfony\Component\Validator\Context\ExecutionContext');

        $validator->initialize($context);

        $context->expects($this->once())
            ->method('addViolation')
            ->with($this->equalTo($constraint->message), $this->equalTo(['%string%' => $failEmail]));

        $validator->validate($failEmail, $constraint);
    }

    /**
     * Проверка отсутствия емаила
     */
    public function testIsEmailNotExists()
    {
        $fixtures = $this->loadFixtures([UserTestFixture::class])->getReferenceRepository();

        $failEmail = $fixtures->getReference('active-user')->getEmail();
        $email = 'non-existent@email.ru';

        /**
         * Проверка на отсутствие емаила: констрейнт не должен сработать
         */
        $constraint = new UserEmail();
        $constraint->needExists = false;
        $constraint->message = 'Пользователь с таким e-mail не существует';

        $validator = new UserEmailValidator($this->em);

        /** @var PHPUnit_Framework_MockObject_MockObject $context */
        $context = $this->createMock('Symfony\Component\Validator\Context\ExecutionContext');

        $validator->initialize($context);

        $context->expects($this->never())
            ->method('addViolation');

        $validator->validate($email, $constraint);

        /**
         * Проверка на отсутствие емаила: констрейнт должен сработать
         */
        $constraint = new UserEmail();
        $constraint->needExists = false;
        $constraint->message = 'Пользователь с таким e-mail не существует';

        $validator = new UserEmailValidator($this->em);

        /** @var PHPUnit_Framework_MockObject_MockObject $context */
        $context = $this->createMock('Symfony\Component\Validator\Context\ExecutionContext');

        $validator->initialize($context);

        $context->expects($this->once())
            ->method('addViolation')
            ->with($this->equalTo($constraint->message), $this->equalTo(['%string%' => $failEmail]));

        $validator->validate($failEmail, $constraint);
    }

    /**
     * Проверка на отсутствие емаила с текущим объектом
     */
    public function testWithContextObject()
    {
        $fixtures = $this->loadFixtures([UserTestFixture::class])->getReferenceRepository();

        /** @var UserEntity $user */
        $user = $fixtures->getReference('active-user');

        $email = $user->getEmail();

        /**
         * Проверка на отсутствие емаила: констрейнт не должен сработать, если ему подсунуть текущего пользователя
         */
        $constraint = new UserEmail();
        $constraint->needExists = false;
        $constraint->message = 'Пользователь с таким e-mail существует';

        $validator = new UserEmailValidator($this->em);

        /** @var PHPUnit_Framework_MockObject_MockObject $context */
        $context = $this->createMock('Symfony\Component\Validator\Context\ExecutionContext');

        $validator->initialize($context);

        $context->expects($this->never())
            ->method('addViolation');

        $context->method('getObject')
            ->willReturn($user);

        $validator->validate($email, $constraint);
    }
}
