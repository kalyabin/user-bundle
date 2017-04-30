<?php

namespace UserBundle\Controller;

use HttpHelperBundle\Annotation\DisableCsrfProtection;
use HttpHelperBundle\Response\FormValidationJsonResponse;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use UserBundle\Entity\UserCheckerEntity;
use UserBundle\Entity\UserEntity;
use UserBundle\Form\Type\ChangePasswordType;
use UserBundle\Form\Type\RememberPasswordType;
use UserBundle\Entity\Repository\UserRepository;
use UserBundle\Utils\UserManager;

/**
 * Напоминание, восстановление пароля
 *
 * @Route(service="user.remember_password_controller")
 *
 * @package UserBundle\Controller
 */
class RememberPasswordController extends Controller
{
    /**
     * @var UserManager
     */
    protected $userManager;

    /**
     * Конструктор
     *
     * @param UserManager $userManager
     */
    public function __construct(UserManager $userManager)
    {
        $this->userManager = $userManager;
    }

    /**
     * Проверить код подтверждения и получить пользователя по коду.
     *
     * Если код подтверждения не найден, либо он неверный - генерирует 404.
     *
     * @param integer $checkerId Идентификатор кода подтверждения
     * @param string $code Код подтверждения
     *
     * @throws NotFoundHttpException
     *
     * @return null|UserEntity
     */
    protected function confirmRememberCode($checkerId, $code)
    {
        /** @var \UserBundle\Entity\Repository\UserCheckerRepository $repository */
        $repository = $this->userManager->getEntityManager()->getRepository(UserCheckerEntity::class);

        $checker = $repository->findOneById($checkerId);

        if (!$checker instanceof UserCheckerEntity) {
            throw $this->createNotFoundException('Код подтверждения не найден');
        }

        $user = $this->userManager->confirmChecker($checker, UserCheckerEntity::TYPE_REMEMBER_PASSWORD, $code);
        if (!$user instanceof UserEntity) {
            throw $this->createNotFoundException('Неверный код подтверждения');
        }

        return $user;
    }

    /**
     * Изменение пароля
     *
     * @Method({"POST"})
     * @Route(
     *     "/change_password/{checkerId}/{code}",
     *     options={"expose" : true},
     *     name="remember_password.change_password",
     *     requirements={"checkerId" : "\d+", "code" : "\w+"}
     * )
     *
     * @param integer $checkerId Идентификатор кода подтверждения
     * @param string $code Код подтверждения
     * @param Request $request
     *
     * @return FormValidationJsonResponse
     */
    public function changePasswordAction($checkerId, $code, Request $request)
    {
        $user = $this->confirmRememberCode($checkerId, $code);

        $response = new FormValidationJsonResponse();
        $response->jsonData = [
            'success' => false,
        ];

        $form = $this->createForm(ChangePasswordType::class, $user);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->userManager->changePassword($user, $user->getPassword());

            $response->jsonData['success'] = true;
        }

        $response->handleForm($form);
        return $response;
    }

    /**
     * Показать форму изменения пароля
     *
     * @DisableCsrfProtection()
     * @Method({"GET"})
     * @Route(
     *     "/change_password/{checkerId}/{code}",
     *     name="remember_password.change_password_form",
     *     options={"expose" : true},
     *     requirements={"checkerId" : "\d+", "code" : "\w+"}
     * )
     *
     * @param integer $checkerId Идентификатор кода подтверждения из письма
     * @param string $code Код подтверждения из письма
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function changePasswordFormAction($checkerId, $code)
    {
        $this->confirmRememberCode($checkerId, $code);
        return $this->redirect('/#/change-password/' . $checkerId . '/' . $code);
    }

    /**
     * Субмит формы напоминания пароля
     *
     * @Method({"POST"})
     * @Route("/remember_password/remember", options={"expose" : true}, name="remember_password.remember")
     *
     * @param Request $request
     *
     * @return FormValidationJsonResponse
     */
    public function rememberAction(Request $request)
    {
        $response = new FormValidationJsonResponse();
        $response->jsonData['success'] = false;
        $response->jsonData['email'] = null;

        $rememberPassword = new RememberPasswordType();

        $form = $this->createForm(RememberPasswordType::class, $rememberPassword);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UserRepository $repository */
            $repository = $this->userManager->getEntityManager()->getRepository(UserEntity::class);
            $user = $repository->findOneByEmail($rememberPassword->email);
            $this->userManager->rememberPassword($user);
            $response->jsonData['email'] = $rememberPassword->email;
            $response->jsonData['success'] = true;
        }

        $response->handleForm($form);

        return $response;
    }
}
