<?php

declare(strict_types=1);

namespace SoftCommerce\PlentyPackstation\Plugin;

use SoftCommerce\PlentyPackstation\Model\OrderImportService\Processor\InvoiceExternal;
use SoftCommerce\PlentyOrder\Api\Data\OrderInterface;
use SoftCommerce\PlentyOrderRestApi\Model\OrderInterface as HttpClient;

/**
 * Class OrderEntityPlugin used to obtain invoice document
 * by type ID: "invoice_external".
 */
class OrderEntityPlugin
{
    /**
     * @param OrderInterface $subject
     * @param string|null $typeId
     * @return array|null
     */
    public function beforeGetDocuments(OrderInterface $subject, ?string $typeId = null)
    {
        if ($typeId === HttpClient::DOCUMENTS_INVOICE) {
            return [InvoiceExternal::ASSETS_ATTRIBUTE];
        }

        return null;
    }
}
