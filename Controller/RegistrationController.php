<?php

namespace UserBundle\Controller;

use HttpHelperBundle\Annotation\DisableCsrfProtection;
use HttpHelperBundle\Response\FormValidationJsonResponse;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use UserBundle\Entity\UserCheckerEntity;
use UserBundle\Entity\UserEntity;
use UserBundle\Form\Type\RegistrationType;
use UserBundle\Entity\Repository\UserCheckerRepository;
use UserBundle\Utils\UserManager;
use UserBundle\Utils\UserSystemMailManager;

/**
 * Контроллер для регистрации и подтверждения аккаунта.
 *
 * @Route(service="user.registration_controller")
 *
 * @package UserBundle\Controller
 */
class RegistrationController extends Controller
{
    /**
     * @var UserManager
     */
    protected $userManager;

    /**
     * @var UserSystemMailManager
     */
    protected $mailManager;

    /**
     * Конструктор
     *
     * @param UserManager $userManager Сервис для работы с пользователями
     * @param UserSystemMailManager $mailManager Сервис для отправки писем пользователям
     */
    public function __construct(UserManager $userManager, UserSystemMailManager $mailManager)
    {
        $this->userManager = $userManager;
        $this->mailManager = $mailManager;
    }

    /**
     * Переотправить письмо подтверждения e-mail
     *
     * @Method({"POST"})
     * @Route("/registration/resend_code/{userId}", options={"expose" : true}, name="registration.resend_code", requirements={"userId" : "\d+"})
     *
     * @param integer $userId
     *
     * @return JsonResponse
     */
    public function resendActivationCodeAction($userId)
    {
        $result = [
            'success' => false,
        ];

        /** @var \UserBundle\Entity\Repository\UserRepository $repository */
        $repository = $this->userManager->getEntityManager()->getRepository(UserEntity::class);

        /** @var UserEntity $user */
        $user = $repository->findOneById($userId);

        if (!$user instanceof UserEntity) {
            return new JsonResponse($result, Response::HTTP_NOT_FOUND);
        }

        if (!$user->isNeedActivation()) {
            return new JsonResponse($result, Response::HTTP_BAD_REQUEST);
        }

        $sent = $this->mailManager->sendActivationEmail(
            $user,
            $user->getCheckerByType(UserCheckerEntity::TYPE_ACTIVATION_CODE)
        );

        $result['success'] = $sent > 0;

        return new JsonResponse($result);
    }

    /**
     * Активация пользователя по коду проверки e-mail
     *
     * @DisableCsrfProtection()
     * @Route(
     *     "/registration/activation/{checkerId}/{code}",
     *     name="registration.activate",
     *     options={"expose" : true},
     *     requirements={
     *      "checkerId" : "\d+",
     *      "code" : "\w+"
     *     }
     * )
     * @param integer $checkerId Идентификатор кода проверки
     * @param string $code Код проверки
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function activationAction($checkerId, $code)
    {
        /** @var UserCheckerRepository $repository */
        $repository = $this->userManager->getEntityManager()->getRepository(UserCheckerEntity::class);

        $checker = $repository->findOneById($checkerId);

        if (!$checker instanceof UserCheckerEntity) {
            return $this->redirect('/#/activation-error');
        }

        $user = $this->userManager->confirmChecker($checker, UserCheckerEntity::TYPE_ACTIVATION_CODE, $code);
        if (!$user instanceof UserEntity) {
            return $this->redirect('/#/activation-error');
        }

        $this->userManager->activateUser($user);

        return $this->redirect('/#/activated');
    }

    /**
     * Субмит регистрации
     *
     * @Method({"POST"})
     * @Route("/registration/register", options={"expose" : true}, name="registration.submit")
     *
     * @param Request $request
     *
     * @return FormValidationJsonResponse
     */
    public function registerAction(Request $request)
    {
        $user = new UserEntity();

        $form = $this->createForm(RegistrationType::class, $user);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->userManager->registerUser($user);
        }

        $response = new FormValidationJsonResponse();
        $response->jsonData = [
            'userId' => $user->getId(),
            'registered' => $user->getId() > 0,
        ];
        $response->handleForm($form);
        return $response;
    }

    /**
     * Индексная страница регистрации - показать форму
     *
     * @Route("/registration", options={"expose" : true}, name="registration.form")
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function indexAction()
    {
        return $this->redirect('/#/sign-in');
    }
}
