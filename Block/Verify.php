<?php

declare(strict_types=1);

namespace MageZero\Otp\Block;

use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\View\Element\Template;

class Verify extends Template
{
    /** @var FormKey */
    private $formKey;

    public function __construct(
        Template\Context $context,
        FormKey $formKey,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->formKey = $formKey;
    }

    public function getSubmitUrl(): string
    {
        return $this->getUrl('otp/auth/submit');
    }

    public function getFormKey(): string
    {
        return $this->formKey->getFormKey();
    }
}
