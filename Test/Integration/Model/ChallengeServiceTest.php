<?php

declare(strict_types=1);

namespace MageZero\Otp\Test\Integration\Model;

use MageZero\Otp\Model\Challenge;
use MageZero\Otp\Model\Service\ChallengeService;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

class ChallengeServiceTest extends TestCase
{
    /** @var ChallengeService */
    private $challengeService;

    /** @var CustomerRepositoryInterface */
    private $customerRepository;

    protected function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->challengeService = $objectManager->get(ChallengeService::class);
        $this->customerRepository = $objectManager->get(CustomerRepositoryInterface::class);
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/Customer/_files/customer.php
     */
    public function testChallengeLifecycleCreateApproveVerifyAndComplete(): void
    {
        $customer = $this->customerRepository->get('customer@example.com');

        $startedChallenge = $this->challengeService->createChallenge(
            (int) $customer->getId(),
            (string) $customer->getEmail(),
            'integration-session',
            (int) $customer->getStoreId()
        );

        $this->assertGreaterThan(0, $startedChallenge->getChallengeId());
        $this->assertNotSame('', $startedChallenge->getToken());

        $challenge = $this->challengeService->getById($startedChallenge->getChallengeId());
        $this->assertInstanceOf(Challenge::class, $challenge);
        $this->assertSame(Challenge::STATUS_PENDING, $challenge->getStatus());

        $challengeByToken = $this->challengeService->getByToken($startedChallenge->getToken());
        $this->assertInstanceOf(Challenge::class, $challengeByToken);
        $this->assertSame($challenge->getChallengeId(), $challengeByToken->getChallengeId());

        $displayCode = $this->challengeService->getDisplayCode($challenge);
        $this->assertMatchesRegularExpression('/^[0-9]{6}$/', $displayCode);
        $this->assertTrue($this->challengeService->validateOtpCode($challenge, $displayCode));

        $wrongCode = $displayCode === '000000' ? '000001' : '000000';
        $this->assertFalse($this->challengeService->validateOtpCode($challenge, $wrongCode));

        $this->challengeService->approve($challenge);

        $challenge = $this->challengeService->getById($startedChallenge->getChallengeId());
        $this->assertInstanceOf(Challenge::class, $challenge);
        $this->assertSame(Challenge::STATUS_APPROVED, $challenge->getStatus());

        $attemptCount = $this->challengeService->incrementAttempts($challenge);
        $this->assertSame(1, $attemptCount);
        $this->assertTrue($this->challengeService->hasAttemptsRemaining($challenge));

        $this->challengeService->complete($challenge);

        $challenge = $this->challengeService->getById($startedChallenge->getChallengeId());
        $this->assertInstanceOf(Challenge::class, $challenge);
        $this->assertSame(Challenge::STATUS_COMPLETED, $challenge->getStatus());
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     */
    public function testUnknownTokenReturnsNull(): void
    {
        $this->assertNull($this->challengeService->getByToken('not-a-real-token'));
    }
}
