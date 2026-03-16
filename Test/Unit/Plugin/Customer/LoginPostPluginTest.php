<?php

declare(strict_types=1);

namespace MageZero\Otp\Test\Unit\Plugin\Customer;

use MageZero\Otp\Model\Challenge;
use MageZero\Otp\Model\Config;
use MageZero\Otp\Model\Email\OtpEmailSender;
use MageZero\Otp\Model\Service\ChallengeService;
use MageZero\Otp\Model\Service\StartedChallenge;
use MageZero\Otp\Model\Session\Context as SessionContext;
use MageZero\Otp\Plugin\Customer\LoginPostPlugin;
use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Controller\Account\LoginPost;
use Magento\Customer\Model\Session;
use Magento\Customer\Model\Url as CustomerUrl;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\UrlInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class LoginPostPluginTest extends TestCase
{
    /** @var Session&MockObject */
    private $customerSession;

    /** @var FormKeyValidator&MockObject */
    private $formKeyValidator;

    /** @var AccountManagementInterface&MockObject */
    private $accountManagement;

    /** @var RedirectFactory&MockObject */
    private $redirectFactory;

    /** @var ManagerInterface&MockObject */
    private $messageManager;

    /** @var CustomerUrl&MockObject */
    private $customerUrl;

    /** @var ChallengeService&MockObject */
    private $challengeService;

    /** @var SessionContext&MockObject */
    private $sessionContext;

    /** @var OtpEmailSender&MockObject */
    private $emailSender;

    /** @var UrlInterface&MockObject */
    private $urlBuilder;

    /** @var Config&MockObject */
    private $config;

    /** @var LoginPostPlugin */
    private $plugin;

    protected function setUp(): void
    {
        $this->customerSession = $this->createMock(Session::class);
        $this->formKeyValidator = $this->createMock(FormKeyValidator::class);
        $this->accountManagement = $this->createMock(AccountManagementInterface::class);
        $this->redirectFactory = $this->createMock(RedirectFactory::class);
        $this->messageManager = $this->createMock(ManagerInterface::class);
        $this->customerUrl = $this->createMock(CustomerUrl::class);
        $this->challengeService = $this->createMock(ChallengeService::class);
        $this->sessionContext = $this->createMock(SessionContext::class);
        $this->emailSender = $this->createMock(OtpEmailSender::class);
        $this->urlBuilder = $this->createMock(UrlInterface::class);
        $this->config = $this->createMock(Config::class);

        $this->plugin = new LoginPostPlugin(
            $this->customerSession,
            $this->formKeyValidator,
            $this->accountManagement,
            $this->redirectFactory,
            $this->messageManager,
            $this->customerUrl,
            $this->challengeService,
            $this->sessionContext,
            $this->emailSender,
            $this->urlBuilder,
            $this->config
        );
    }

    public function testDisabledConfigDelegatesToCoreExecute(): void
    {
        $subject = $this->createMock(LoginPost::class);

        $this->config->expects($this->once())->method('isEnabled')->willReturn(false);

        $expectedResult = new \stdClass();
        $result = $this->plugin->aroundExecute($subject, static function () use ($expectedResult) {
            return $expectedResult;
        });

        $this->assertSame($expectedResult, $result);
    }

    public function testSuccessfulPasswordStartsChallengeAndRedirectsPending(): void
    {
        $subject = $this->createMock(LoginPost::class);
        $request = $this->createMock(RequestInterface::class);
        $redirect = $this->createMock(Redirect::class);
        $customer = $this->createMock(CustomerInterface::class);

        $subject->expects($this->once())->method('getRequest')->willReturn($request);

        $this->config->expects($this->once())->method('isEnabled')->willReturn(true);
        $this->customerSession->expects($this->once())->method('isLoggedIn')->willReturn(false);
        $this->formKeyValidator->expects($this->once())->method('validate')->with($request)->willReturn(true);
        $request->expects($this->once())->method('isPost')->willReturn(true);
        $request->expects($this->once())->method('getPost')->with('login')->willReturn([
            'username' => 'customer@example.com',
            'password' => 'secret',
        ]);

        $this->accountManagement->expects($this->once())
            ->method('authenticate')
            ->with('customer@example.com', 'secret')
            ->willReturn($customer);

        $customer->expects($this->once())->method('getId')->willReturn(42);
        $customer->expects($this->once())->method('getStoreId')->willReturn(1);
        $customer->expects($this->once())->method('getEmail')->willReturn('customer@example.com');

        $this->sessionContext->expects($this->once())->method('getChallengeId')->willReturn(null);
        $this->customerSession->expects($this->once())->method('getSessionId')->willReturn('session-123');

        $this->challengeService->expects($this->once())
            ->method('createChallenge')
            ->with(42, 'customer@example.com', 'session-123', 1)
            ->willReturn(new StartedChallenge(99, 'token-abc'));

        $this->sessionContext->expects($this->once())->method('setChallengeId')->with(99);

        $this->urlBuilder->expects($this->once())
            ->method('getUrl')
            ->with(
                'otp/auth/approve',
                $this->callback(static function (array $params): bool {
                    return isset($params['_nosid'], $params['token'])
                        && $params['_nosid'] === true
                        && $params['token'] === 'token-abc';
                })
            )
            ->willReturn('https://example.com/otp/auth/approve?token=token-abc');

        $this->emailSender->expects($this->once())
            ->method('send')
            ->with($customer, 'https://example.com/otp/auth/approve?token=token-abc');

        $this->customerSession->expects($this->once())->method('setUsername')->with('customer@example.com');

        $this->redirectFactory->expects($this->once())->method('create')->willReturn($redirect);
        $redirect->expects($this->once())->method('setPath')->with('otp/auth/pending')->willReturnSelf();

        $result = $this->plugin->aroundExecute($subject, static function () {
            throw new \RuntimeException('Proceed should not be called for OTP-managed login');
        });

        $this->assertSame($redirect, $result);
    }

    public function testApprovedChallengeSkipsEmailAndRedirectsVerify(): void
    {
        $subject = $this->createMock(LoginPost::class);
        $request = $this->createMock(RequestInterface::class);
        $redirect = $this->createMock(Redirect::class);
        $customer = $this->createMock(CustomerInterface::class);
        $challenge = $this->createMock(Challenge::class);

        $subject->expects($this->once())->method('getRequest')->willReturn($request);

        $this->config->expects($this->once())->method('isEnabled')->willReturn(true);
        $this->customerSession->expects($this->once())->method('isLoggedIn')->willReturn(false);
        $this->formKeyValidator->expects($this->once())->method('validate')->with($request)->willReturn(true);
        $request->expects($this->once())->method('isPost')->willReturn(true);
        $request->expects($this->once())->method('getPost')->with('login')->willReturn([
            'username' => 'customer@example.com',
            'password' => 'secret',
        ]);

        $this->accountManagement->expects($this->once())
            ->method('authenticate')
            ->with('customer@example.com', 'secret')
            ->willReturn($customer);

        $customer->expects($this->once())->method('getId')->willReturn(42);

        $this->sessionContext->expects($this->once())->method('getChallengeId')->willReturn(99);
        $this->challengeService->expects($this->once())->method('getById')->with(99)->willReturn($challenge);

        $challenge->expects($this->once())->method('getCustomerId')->willReturn(42);
        $this->challengeService->expects($this->once())->method('hasExpired')->with($challenge)->willReturn(false);
        $this->challengeService->expects($this->once())->method('hasAttemptsRemaining')->with($challenge)->willReturn(true);
        $challenge->expects($this->once())->method('getStatus')->willReturn(Challenge::STATUS_APPROVED);

        $this->emailSender->expects($this->never())->method('send');
        $this->challengeService->expects($this->never())->method('createChallenge');

        $this->redirectFactory->expects($this->once())->method('create')->willReturn($redirect);
        $redirect->expects($this->once())->method('setPath')->with('otp/auth/verify')->willReturnSelf();

        $result = $this->plugin->aroundExecute($subject, static function () {
            throw new \RuntimeException('Proceed should not be called for OTP-managed login');
        });

        $this->assertSame($redirect, $result);
    }
}
