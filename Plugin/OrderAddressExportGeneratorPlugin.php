<?php
/**
 * Copyright © Soft Commerce Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace SoftCommerce\PlentyPackstation\Plugin;

use Magento\Framework\Exception\LocalizedException;
use Netresearch\ShippingCore\Model\ShippingSettings\ShippingOption\Codes;
use Netresearch\ShippingCore\Model\ShippingSettings\ShippingOption\Selection\OrderSelectionManager;
use SoftCommerce\PlentyCustomerRestApi\Model\AddressInterface as HttpClient;
use SoftCommerce\PlentyOrderProfile\Model\OrderExportService\Generator\OrderAddress;

/**
 * Class OrderAddressExportGeneratorPlugin used to
 * intercept order address generation process
 * in order to provide PackStation address.
 */
class OrderAddressExportGeneratorPlugin
{
    /**#@+
     * PackStation metadata
     */
    private const POSTOFFICE = 'postoffice';
    private const SERVICEPOINT = 'servicepoint';

    /**
     * @var OrderSelectionManager
     */
    private OrderSelectionManager $orderSelectionManager;

    /**
     * @param OrderSelectionManager $orderSelectionManager
     */
    public function __construct(OrderSelectionManager $orderSelectionManager)
    {
        $this->orderSelectionManager = $orderSelectionManager;
    }

    /**
     * @param OrderAddress $subject
     * @param array $result
     * @return array
     * @throws LocalizedException
     */
    public function afterGenerate(OrderAddress $subject, array $result): array
    {
        $orderAddress = $subject->getSubject();
        if ($orderAddress->getAddressType() !== 'shipping') {
            return $result;
        }

        $selections = $this->orderSelectionManager->load((int) $orderAddress->getId()) ?: [];

        $addressLines = [];
        foreach ($selections as $selection) {
            if ($selection->getShippingOptionCode() !== Codes::SERVICE_OPTION_DELIVERY_LOCATION) {
                continue;
            }

            $addressLines[$selection->getInputCode()] = $selection->getInputValue();
        }

        $typeId = $addressLines['type'] ?? null;
        if (!$addressLines || !$typeId) {
            return $result;
        }

        $commonFields = [
            HttpClient::POSTAL_CODE => $addressLines['postalCode'] ?? '',
            HttpClient::TOWN => $addressLines['city'] ?? '',
            HttpClient::STATE_ID => null
        ];

        if (isset($result[HttpClient::ADDRESS2])) {
            $result[HttpClient::ADDRESS2] = '';
        }

        if (isset($result[HttpClient::ADDRESS3])) {
            $result[HttpClient::ADDRESS3] = '';
        }

        $typeId = strtolower($typeId);
        if (in_array($typeId, [self::POSTOFFICE, self::SERVICEPOINT])) {
            $result[HttpClient::GENDER] = '';
            $result[HttpClient::TITLE] = '';
            $result[HttpClient::NAME1] = '';
            $result[HttpClient::ADDRESS1] = $addressLines['displayName'] ?? '';
        } else {
            $result[HttpClient::NAME1] = '';

            if ($postNumber = $addressLines['customerPostnumber'] ?? null) {
                $result[HttpClient::NAME4] = $postNumber;
                $result[HttpClient::ADDRESS1] = $addressLines['displayName'] ?? '';
            } else {
                $result[HttpClient::NAME1] = $addressLines['displayName'] ?? '';
            }
        }

        return array_merge($result, $commonFields);
    }
}
