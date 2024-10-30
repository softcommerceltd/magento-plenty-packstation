<?php

declare(strict_types=1);

namespace SoftCommerce\PlentyPackstation\Plugin;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\SerializerInterface;
use SoftCommerce\PlentyOrderProfile\Model\OrderExportService\Generator\OrderAddress;
use SoftCommerce\PlentyOrderProfile\Model\OrderExportServiceInterface;

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
    private const XML_PATH_FIELD_MAPPING = 'plenty_order_export/haka_config/pack_station_field_mapping';

    /**
     * @var array
     */
    private array $dataInMemory = [];

    /**
     * @var OrderAddress|null
     */
    private ?OrderAddress $subject = null;

    /**
     * @var SerializerInterface
     */
    private SerializerInterface $serializer;

    /**
     * @param SerializerInterface $serializer
     */
    public function __construct(SerializerInterface $serializer)
    {
        $this->serializer = $serializer;
    }

    /**
     * @param OrderAddress $subject
     * @param $result
     * @return mixed
     */
    public function afterInitialize(OrderAddress $subject, $result)
    {
        $this->dataInMemory = [];
        return $result;
    }

    /**
     * @param OrderAddress $subject
     * @param array $request
     * @return array
     * @throws LocalizedException
     */
    public function afterBuildRequiredData(OrderAddress $subject, array $request): array
    {
        $this->subject = $subject;

        if ($request) {
            $request = $this->generatePackStationData($request);
        }

        return $request;
    }

    /**
     * @param array $request
     * @return array
     * @throws LocalizedException
     */
    private function generatePackStationData(array $request): array
    {
        $orderAddress = $this->subject->getSubject();

        if (isset($this->dataInMemory[$orderAddress->getId()])) {
            return $this->dataInMemory[$orderAddress->getId()];
        }

        $addressFieldMapping = $this->getPackStationFieldMapping();
        if (!$addressFieldMapping || !$this->canGenerate()) {
            $this->dataInMemory[$orderAddress->getId()] = $request;
            return $request;
        }

        foreach ($addressFieldMapping as $map) {
            if (!isset($map['line_no'], $map['address_field'])
                || !$addressLine = $orderAddress->getStreetLine($map['line_no'])
            ) {
                continue;
            }

            if (!empty($map['prefix'])) {
                $addressLine = "{$map['prefix']} $addressLine";
            }

            $request[$map['address_field']] = trim($addressLine);

            if (!empty($request["address{$map['line_no']}"])
                && "address{$map['line_no']}" !== $map['address_field']
            ) {
                $request["address{$map['line_no']}"] = '';
            }
        }

        $this->dataInMemory[$orderAddress->getId()] = $request;

        return $request;
    }

    /**
     * @return array
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    private function getPackStationFieldMapping(): array
    {
        /** @var OrderExportServiceInterface $context */
        $context = $this->subject->getContext();

        if (!$mapping = $context->orderConfig()->getConfig(self::XML_PATH_FIELD_MAPPING)) {
            return [];
        }

        try {
            $mapping = $this->serializer->unserialize($mapping);
        } catch (\InvalidArgumentException $e) {
            $mapping = [];
        }

        return $mapping;
    }

    /**
     * @return bool
     * @throws LocalizedException
     */
    private function canGenerate(): bool
    {
        $arg1 = self::PACKSTATION_TAG;
        $arg2 = self::POSTFILIALE_TAG;
        $streetData = $this->subject->getSubject()->getStreet();
        return !!array_filter($streetData, function ($item) use ($arg1, $arg2) {
            return str_contains(strtolower($item), $arg1) || str_contains(strtolower($item), $arg2);
        });
    }
}
