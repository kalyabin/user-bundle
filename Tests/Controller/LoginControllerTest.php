<?php

namespace UserBundle\Tests\Controller;


use Liip\FunctionalTestBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Tests\JsonResponseTestTrait;
use UserBundle\Tests\DataFixtures\ORM\UserTestFixture;
use UserBundle\Controller\LoginController;
use UserBundle\Entity\UserEntity;


/**
 * Тестирование класса LoginController
 *
 * @package UserBundle\Tests\Controller
 */
class LoginControllerTest extends WebTestCase
{
    use JsonResponseTestTrait;

    /**
     * Тестирование авторизации по логин-паролю
     */
    public function testSimpleLoginAction()
    {
        // загрузить пользователей
        $fixtures = $this->loadFixtures([UserTestFixture::class])->getReferenceRepository();

        /** @var UserEntity $activeUser */
        $activeUser = $fixtures->getReference('active-user');
        /** @var UserEntity $inactiveUser */
        $inactiveUser = $fixtures->getReference('inactive-user');
        /** @var UserEntity $lockedUser */
        $lockedUser = $fixtures->getReference('locked-user');

        // пароль для всех
        $password = 'testpassword';

        $url = $this->getUrl('login.simple_check');

        $client = $this->createClient();
        $session = $client->getContainer()->get('session');
        $cookie = new Cookie($session->getName(), $session->getId());
        $client->getCookieJar()->set($cookie);

        // отправить пустой POST
        $client->request('POST', $url);
        $this->assertStatusCode(401, $client);
        $jsonData = $this->assertIsValidJsonResponse($client->getResponse());
        $this->assertArrayHasKey('loggedIn', $jsonData);
        $this->assertArrayHasKey('isLocked', $jsonData);
        $this->assertArrayHasKey('isNeedActivation', $jsonData);
        $this->assertArrayHasKey('errorMessage', $jsonData);
        $this->assertArrayHasKey('userId', $jsonData);
        $this->assertFalse($jsonData['loggedIn']);
        $this->assertFalse($jsonData['isLocked']);
        $this->assertFalse($jsonData['isNeedActivation']);
        $this->assertEquals('Неверный логин или пароль', $jsonData['errorMessage']);
        $this->assertNull($jsonData['userId']);

        // отправить неверный логин или пароль
        $client->request('POST', $url, [
            '_username' => 'non-existent@email.ru',
            '_password' => 'wrongpassword',
        ]);
        $this->assertStatusCode(401, $client);
        $jsonData = $this->assertIsValidJsonResponse($client->getResponse());
        $this->assertFalse($jsonData['loggedIn']);
        $this->assertFalse($jsonData['isLocked']);
        $this->assertFalse($jsonData['isNeedActivation']);
        $this->assertEquals('Неверный логин или пароль', $jsonData['errorMessage']);
        $this->assertNull($jsonData['userId']);

        // авторизоваться под неактивным пользователем
        $client->request('POST', $url, [
            '_username' => $inactiveUser->getUsername(),
            '_password' => $password,
        ]);
        $jsonData = $this->assertIsValidJsonResponse($client->getResponse());
        $this->assertFalse($jsonData['loggedIn']);
        $this->assertFalse($jsonData['isLocked']);
        $this->assertTrue($jsonData['isNeedActivation']);
        $this->assertEquals('Требуется активация', $jsonData['errorMessage']);
        $this->assertEquals($inactiveUser->getId(), $jsonData['userId']);

        // авторизоваться под заблокированным пользователем
        $client->request('POST', $url, [
            '_username' => $lockedUser->getUsername(),
            '_password' => $password,
        ]);
        $jsonData = $this->assertIsValidJsonResponse($client->getResponse());
        $this->assertFalse($jsonData['loggedIn']);
        $this->assertTrue($jsonData['isLocked']);
        $this->assertFalse($jsonData['isNeedActivation']);
        $this->assertEquals('Ваш аккаунт заблокирован', $jsonData['errorMessage']);
        $this->assertNull($jsonData['userId']);

        // авторизоваться под активным пользователем
        $client->request('POST', $url, [
            '_username' => $activeUser->getUsername(),
            '_password' => $password,
        ]);
        $jsonData = $this->assertIsValidJsonResponse($client->getResponse());
        $this->assertTrue($jsonData['loggedIn']);
        $this->assertFalse($jsonData['isLocked']);
        $this->assertFalse($jsonData['isNeedActivation']);
        $this->assertEquals('', $jsonData['errorMessage']);
        $this->assertEquals($activeUser->getId(), $jsonData['userId']);
    }
}
