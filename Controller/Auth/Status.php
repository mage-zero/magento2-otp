<?php

declare(strict_types=1);

namespace MageZero\Otp\Controller\Auth;

use MageZero\Otp\Model\Challenge;
use MageZero\Otp\Model\Service\ChallengeService;
use MageZero\Otp\Model\Session\Context as SessionContext;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;

class Status extends AbstractSessionChallengeAction implements HttpGetActionInterface
{
    /** @var JsonFactory */
    private $resultJsonFactory;

    public function __construct(
        Context $context,
        SessionContext $sessionContext,
        ChallengeService $challengeService,
        JsonFactory $resultJsonFactory
    ) {
        parent::__construct($context, $sessionContext, $challengeService);
        $this->resultJsonFactory = $resultJsonFactory;
    }

    public function execute()
    {
        $payload = [
            'state' => 'missing',
            'redirect' => $this->_url->getUrl('customer/account/login'),
        ];

        $challenge = $this->getSessionChallenge();
        if ($challenge !== null) {
            if ($challenge->getStatus() === Challenge::STATUS_APPROVED) {
                $payload = [
                    'state' => 'approved',
                    'redirect' => $this->_url->getUrl('otp/auth/verify'),
                ];
            } elseif ($challenge->getStatus() === Challenge::STATUS_PENDING) {
                $payload = [
                    'state' => 'pending',
                ];
            } else {
                $payload = [
                    'state' => 'failed',
                    'redirect' => $this->_url->getUrl('customer/account/login'),
                ];
            }
        }

        return $this->resultJsonFactory->create()->setData($payload);
    }
}
