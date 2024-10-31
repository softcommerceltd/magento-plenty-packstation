<?php

declare(strict_types=1);

namespace SoftCommerce\PlentyPackstation\Plugin;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\SerializerInterface;
use Netresearch\ShippingCore\Model\ShippingSettings\ShippingOption\Codes;
use Netresearch\ShippingCore\Model\ShippingSettings\ShippingOption\Selection\OrderSelectionManager;
use SoftCommerce\PlentyCustomerRestApi\Model\AddressInterface as HttpClient;
use SoftCommerce\PlentyOrderProfile\Model\OrderExportService\Generator\OrderAddress;

/**
 * Class OrderAddressExportGeneratorPlugin used to
 * intercept order address generation process
 * in order to provide PackStation data.
 */
class OrderAddressExportGeneratorPlugin
{
    /**#@+
     * PackStation metadata
     */
    private const PACKSTATION_TAG = 'packstation';
    private const POSTFILIALE_TAG = 'postfiliale';

    /**#@+
     * Config path
     */
    private const XML_PATH_FIELD_MAPPING = 'plenty_order_export/packstation_config/pack_station_field_mapping';

    /**
     * @var OrderSelectionManager
     */
    private OrderSelectionManager $orderSelectionManager;

    /**
     * @var SerializerInterface
     */
    private SerializerInterface $serializer;

    /**
     * @param SerializerInterface $serializer
     */
    public function __construct(
        OrderSelectionManager $orderSelectionManager,
        SerializerInterface $serializer
    ) {
        $this->orderSelectionManager = $orderSelectionManager;
        $this->serializer = $serializer;
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

        if (!$addressLines) {
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

        if ($addressLines['type'] === 'postoffice') {
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
