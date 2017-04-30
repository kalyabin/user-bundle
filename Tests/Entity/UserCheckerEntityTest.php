<?php

namespace UserBunde\Tests\Entity;

use Liip\FunctionalTestBundle\Test\WebTestCase;
use UserBundle\Tests\DataFixtures\ORM\UserTestFixture;
use UserBundle\Entity\UserCheckerEntity;
use UserBundle\Entity\UserEntity;

/**
 * Тестирование класса UserCheckerEntity
 */
class UserCheckerEntityTest extends WebTestCase
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
     * Тестирование сохранения пользователя, включая его проверочную модель
     *
     * @covers UserCheckerEntity::setCode()
     * @covers UserCheckerEntity::setType()
     * @covers UserCheckerEntity::setUser()
     * @covers UserCheckerEntity::setData()
     * @covers UserCheckerEntity::setJsonData()
     * @covers UserCheckerEntity::getUser()
     * @covers UserCheckerEntity::getCode()
     * @covers UserCheckerEntity::getType()
     * @covers UserCheckerEntity::getId()
     * @covers UserCheckerEntity::generateCode()
     * @covers UserCheckerEntity::getData()
     * @covers UserCheckerEntity::getJsonData()
     *
     * @covers UserEntity::getChecker()
     * @covers UserEntity::addChecker()
     * @covers UserEntity::removeCheckerByType()
     * @covers UserEntity::removeChecker()
     */
    public function testMe()
    {
        /** @var UserEntity $user */
        $user = $this->loadFixtures([UserTestFixture::class])->getReferenceRepository()->getReference('active-user');

        $checker = new UserCheckerEntity();

        $checker
            ->setUser($user)
            ->setType(UserCheckerEntity::TYPE_ACTIVATION_CODE)
            ->generateCode();

        // проверить установку данных
        $this->assertNull($checker->getData());
        $checker->setData('testRawData');
        $this->assertEquals('testRawData', $checker->getData());
        $checker->setJsonData(['testKey' => 'testValue']);
        $data = $checker->getJsonData();
        $this->assertInternalType('array', $data);
        $this->assertArrayHasKey('testKey', $data);
        $this->assertEquals('testValue', $data['testKey']);

        $this->assertEquals(UserCheckerEntity::TYPE_ACTIVATION_CODE, $checker->getType());
        $this->assertInstanceOf(UserEntity::class, $checker->getUser());
        $this->assertEquals($user->getId(), $checker->getUser()->getId());

        $activationCode = $checker->getCode();

        $this->assertNotEmpty($activationCode);

        $this->assertEmpty($user->getChecker());
        $user->addChecker($checker);
        $this->assertNotEmpty($user->getChecker());

        $user->removeChecker($checker);
        $this->assertEmpty($user->getChecker());

        $user->addChecker($checker);

        $this->assertNotEmpty($user->getChecker());
        $user->removeCheckerByType(UserCheckerEntity::TYPE_ACTIVATION_CODE);
        $this->assertEmpty($user->getChecker());

        $user->addChecker($checker);

        $expectedChecker = $user->getCheckerByType(UserCheckerEntity::TYPE_ACTIVATION_CODE);
        $this->assertEquals($expectedChecker->getCode(), $checker->getCode());

        $this->em->persist($checker);

        $this->em->flush();

        $this->greaterThan(0, $checker->getId());
    }
}
