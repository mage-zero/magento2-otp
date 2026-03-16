<?php

declare(strict_types=1);

namespace MageZero\Otp\Model\ResourceModel\Challenge;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use MageZero\Otp\Model\Challenge as ChallengeModel;
use MageZero\Otp\Model\ResourceModel\Challenge as ChallengeResource;

class Collection extends AbstractCollection
{
    protected function _construct(): void
    {
        $this->_init(ChallengeModel::class, ChallengeResource::class);
    }
}
