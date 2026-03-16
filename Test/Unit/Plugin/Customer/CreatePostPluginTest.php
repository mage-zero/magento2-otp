<?php

declare(strict_types=1);

namespace MageZero\Otp\Test\Unit\Plugin\Customer;

use MageZero\Otp\Model\Config;
use MageZero\Otp\Model\Email\OtpEmailSender;
use MageZero\Otp\Model\Service\ChallengeService;
use MageZero\Otp\Model\Service\StartedChallenge;
use MageZero\Otp\Model\Session\Context as SessionContext;
use MageZero\Otp\Plugin\Customer\CreatePostPlugin;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Controller\Account\CreatePost;
use Magento\Customer\Model\Session;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\UrlInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class CreatePostPluginTest extends TestCase
{
    /** @var Session&MockObject */
    private $customerSession;

    /** @var Config&MockObject */
    private $config;

    /** @var ChallengeService&MockObject */
    private $challengeService;

    /** @var SessionContext&MockObject */
    private $sessionContext;

    /** @var OtpEmailSender&MockObject */
    private $emailSender;

    /** @var UrlInterface&MockObject */
    private $urlBuilder;

    /** @var RedirectFactory&MockObject */
    private $redirectFactory;

    /** @var ManagerInterface&MockObject */
    private $messageManager;

    /** @var CreatePostPlugin */
    private $plugin;

    protected function setUp(): void
    {
        $this->customerSession = $this->createMock(Session::class);
        $this->config = $this->createMock(Config::class);
        $this->challengeService = $this->createMock(ChallengeService::class);
        $this->sessionContext = $this->createMock(SessionContext::class);
        $this->emailSender = $this->createMock(OtpEmailSender::class);
        $this->urlBuilder = $this->createMock(UrlInterface::class);
        $this->redirectFactory = $this->createMock(RedirectFactory::class);
        $this->messageManager = $this->createMock(ManagerInterface::class);

        $this->plugin = new CreatePostPlugin(
            $this->customerSession,
            $this->config,
            $this->challengeService,
            $this->sessionContext,
            $this->emailSender,
            $this->urlBuilder,
            $this->redirectFactory,
            $this->messageManager
        );
    }

    public function testReturnsOriginalResultWhenDisabled(): void
    {
        $subject = $this->createMock(CreatePost::class);
        $originalResult = new \stdClass();

        $this->config->expects($this->once())->method('isEnabled')->willReturn(false);

        $this->assertSame($originalResult, $this->plugin->afterExecute($subject, $originalResult));
    }

    public function testRegistrationAutoLoginTransitionsToOtpPending(): void
    {
        $subject = $this->createMock(CreatePost::class);
        $originalResult = new \stdClass();
        $customer = $this->createMock(CustomerInterface::class);
        $redirect = $this->createMock(Redirect::class);

        $this->config->expects($this->once())->method('isEnabled')->willReturn(true);
        $this->customerSession->expects($this->exactly(2))->method('isLoggedIn')->willReturnOnConsecutiveCalls(true, true);

        $this->customerSession->expects($this->once())->method('getCustomerDataObject')->willReturn($customer);
        $customer->expects($this->once())->method('getId')->willReturn(50);
        $customer->expects($this->once())->method('getEmail')->willReturn('new@example.com');
        $customer->expects($this->once())->method('getStoreId')->willReturn(1);

        $this->sessionContext->expects($this->once())->method('getChallengeId')->willReturn(null);
        $this->customerSession->expects($this->once())->method('getSessionId')->willReturn('session-reg-1');

        $this->challengeService->expects($this->once())
            ->method('createChallenge')
            ->with(50, 'new@example.com', 'session-reg-1', 1)
            ->willReturn(new StartedChallenge(501, 'tok-501'));

        $this->urlBuilder->expects($this->once())
            ->method('getUrl')
            ->with(
                'otp/auth/approve',
                $this->callback(static function (array $params): bool {
                    return isset($params['_nosid'], $params['token'])
                        && $params['_nosid'] === true
                        && $params['token'] === 'tok-501';
                })
            )
            ->willReturn('https://example.com/otp/auth/approve?token=tok-501');

        $this->emailSender->expects($this->once())
            ->method('send')
            ->with($customer, 'https://example.com/otp/auth/approve?token=tok-501');

        $this->customerSession->expects($this->once())->method('logout');
        $this->sessionContext->expects($this->once())->method('setChallengeId')->with(501);
        $this->customerSession->expects($this->once())->method('setUsername')->with('new@example.com');

        $this->messageManager->expects($this->once())->method('addSuccessMessage');

        $this->redirectFactory->expects($this->once())->method('create')->willReturn($redirect);
        $redirect->expects($this->once())->method('setPath')->with('otp/auth/pending')->willReturnSelf();

        $result = $this->plugin->afterExecute($subject, $originalResult);

        $this->assertSame($redirect, $result);
    }
}
