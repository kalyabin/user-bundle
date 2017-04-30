<?php

namespace UserBunde\Tests\Controller;

use Doctrine\Common\Persistence\ObjectManager;
use Liip\FunctionalTestBundle\Test\WebTestCase;
use UserBundle\Entity\UserCheckerEntity;
use UserBundle\Entity\UserEntity;
use Tests\JsonResponseTestTrait;
use UserBundle\Tests\DataFixtures\ORM\UserTestFixture;
use UserBundle\Controller\RegistrationController;
use UserBundle\Entity\Repository\UserRepository;

/**
 * Тестирование контроллера регистрации и активации
 *
 * @package UserBundle\Tests\Controller
 */
class RegistrationControllerTest extends WebTestCase
{
    use JsonResponseTestTrait;

    /**
     * @var ObjectManager
     */
    protected $em;

    protected function setUp()
    {
        parent::setUp();

        $this->em = $this->getContainer()->get('doctrine.orm.entity_manager');
    }

    /**
     * @covers RegistrationController::resendActivationCodeAction()
     */
    public function testResendActivationCodeAction()
    {
        $fixtures = $this->loadFixtures([UserTestFixture::class])->getReferenceRepository();

        /** @var UserEntity $user */
        $user = $fixtures->getReference('inactive-user');
        /** @var UserEntity $activeUser */
        $activeUser = $fixtures->getReference('active-user');

        $this->assertTrue($user->isNeedActivation());

        $client = $this->createClient();

        // GET-запрос недопустим
        $client->request('GET', $this->getUrl('registration.resend_code', [
            'userId' => $user->getId(),
        ]));

        $this->assertStatusCode(405, $client);

        // сначала попробовать получить несуществующего пользователя
        $client->request('POST', $this->getUrl('registration.resend_code', [
            'userId' => 0,
        ]));

        $this->assertStatusCode(404, $client);
        $jsonData = $this->assertIsValidJsonResponse($client->getResponse());
        $this->assertArrayHasKey('success', $jsonData);
        $this->assertFalse($jsonData['success']);

        // потом попробовать отправить письмо уже активному пользователю
        $client->request('POST', $this->getUrl('registration.resend_code', [
            'userId' => $activeUser->getId(),
        ]));

        $this->assertStatusCode(400, $client);
        $jsonData = $this->assertIsValidJsonResponse($client->getResponse());
        $this->assertArrayHasKey('success', $jsonData);
        $this->assertFalse($jsonData['success']);

        // потом отправить письмо для неактивного пользователя
        $client->request('POST', $this->getUrl('registration.resend_code', [
            'userId' => $user->getId(),
        ]));

        $this->assertStatusCode(200, $client);
        $jsonData = $this->assertIsValidJsonResponse($client->getResponse());
        $this->assertArrayHasKey('success', $jsonData);
        $this->assertTrue($jsonData['success']);
    }

    /**
     * @covers RegistrationController::activationAction()
     */
    public function testActivationAction()
    {
        /** @var UserEntity $user */
        $user = $this->loadFixtures([UserTestFixture::class])->getReferenceRepository()->getReference('inactive-user');

        $this->assertTrue($user->isNeedActivation());

        $client = $this->createClient();

        // сначала попробовать получить несуществующий checker
        $client->request('GET', $this->getUrl('registration.activate', [
            'checkerId' => 0,
            'code' => 'wrongtestcode',
        ]));

        $this->assertEquals('/#/activation-error', $client->getResponse()->headers->get('location'));

        // потом попробовать получить существующий checker и сгенерированный 400 из-за неправильного кода
        $client->request('GET', $this->getUrl('registration.activate', [
            'checkerId' => $user->getCheckerByType(UserCheckerEntity::TYPE_ACTIVATION_CODE)->getId(),
            'code' => 'wrongtestcode',
        ]));

        $this->assertEquals('/#/activation-error', $client->getResponse()->headers->get('location'));

        // потом попробовать успешно активировать пользователя
        $client->request('GET', $this->getUrl('registration.activate', [
            'checkerId' => $user->getCheckerByType(UserCheckerEntity::TYPE_ACTIVATION_CODE)->getId(),
            'code' => $user->getCheckerByType(UserCheckerEntity::TYPE_ACTIVATION_CODE)->getCode(),
        ]));

        $this->assertEquals('/#/activated', $client->getResponse()->headers->get('location'));

        // проверить что пользователь успешно активирован
        /** @var \UserBundle\Entity\Repository\UserRepository $repository */
        $this->em->clear();

        $repository = $this->em->getRepository(UserEntity::class);

        $user = $repository->findOneById($user->getId());

        $this->assertInstanceOf(UserEntity::class, $user);
        $this->assertFalse($user->isNeedActivation());
        $this->assertTrue($user->isActive());
    }

    /**
     * @covers RegistrationController::registerAction()
     */
    public function testRegisterAction()
    {
        $this->loadFixtures([]);

        $client = $this->createClient();

        $url = $this->getUrl('registration.submit');

        // GET-ом сюда стучаться нельзя
        $client->request('GET', $url);
        $this->assertEquals(405, $client->getResponse()->getStatusCode());

        // пустой POST
        $client->request('POST', $url);
        $response = $client->getResponse();
        $jsonData = $this->assertIsValidJsonResponse($response);

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertFalse($jsonData['registered']);
        $this->assertFalse($jsonData['valid']);
        $this->assertEmpty($jsonData['validationErrors']);
        $this->assertNull($jsonData['userId']);
        $this->assertFalse($jsonData['registered']);

        // отправляем нормальный POST
        $requestData = [
            'registration' => [
                'name' => 'Test',
                'email' => 'tester@test.ru',
                'password' => [
                    'first' => 'testpassword',
                    'second' => 'testpassword',
                ],
            ],
        ];
        $client->request('POST', $url, $requestData);
        $response = $client->getResponse();
        $jsonData = $this->assertIsValidJsonResponse($response);
        $this->assertTrue($jsonData['registered']);
        $this->assertTrue($jsonData['valid']);
        $this->assertEmpty($jsonData['validationErrors']);
        $this->assertGreaterThan(0, $jsonData['userId']);
        $this->assertTrue($jsonData['registered']);

        // проверить, что пользователь создался и его статус равен "требует активации"
        /** @var \UserBundle\Entity\Repository\UserRepository $repository */
        $repository = $this->em->getRepository(UserEntity::class);
        $user = $repository->findOneById($jsonData['userId']);
        $this->assertInstanceOf(UserEntity::class, $user);
        $this->assertTrue($user->isNeedActivation());
        $this->assertInstanceOf(UserCheckerEntity::class, $user->getCheckerByType(UserCheckerEntity::TYPE_ACTIVATION_CODE));
    }
}
