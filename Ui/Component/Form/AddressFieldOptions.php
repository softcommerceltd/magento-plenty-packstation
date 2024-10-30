<?php

declare(strict_types=1);

namespace SoftCommerce\PlentyPackstation\Ui\Component\Form;

use Magento\Framework\Data\OptionSourceInterface;
use SoftCommerce\PlentyCustomerRestApi\Model\AddressInterface;

/**
 * @inheritDoc
 */
class AddressFieldOptions implements OptionSourceInterface
{
    /**
     * @var array
     */
    private $addressFields = [
        AddressInterface::ADDRESS1,
        AddressInterface::ADDRESS2,
        AddressInterface::ADDRESS3,
        AddressInterface::ADDRESS4,
        AddressInterface::NAME1,
        AddressInterface::NAME2,
        AddressInterface::NAME3,
        AddressInterface::NAME4
    ];

    /**
     * @inheritDoc
     */
    public function toOptionArray(): array
    {
        $options = [];
        foreach ($this->addressFields as $field) {
            $options[] = [
                'value' => $field,
                'label' => $field
            ];
        }

        return $options;
    }
}
