<?php

declare(strict_types=1);

namespace MageZero\Otp\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Challenge extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('mz_customer_otp_challenge', 'entity_id');
    }
}
