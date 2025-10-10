<?php
namespace josemmo\Verifactu\Models\Records;

use DateTimeImmutable;
use josemmo\Verifactu\Models\Model;

/**
 * Identificador de factura
 */
class InvoiceIdentifier extends Model
{
    /**
     * Class constructor
     *
     * @param string|null            $issuerId      Issuer ID
     * @param string|null            $invoiceNumber Invoice number
     * @param DateTimeImmutable|null $issueDate     Issue date
     */
    public function __construct($issuerId = null, $invoiceNumber = null, $issueDate = null)
    {
        if ($issuerId !== null) {
            $this->issuerId = $issuerId;
        }
        if ($invoiceNumber !== null) {
            $this->invoiceNumber = $invoiceNumber;
        }
        if ($issueDate !== null) {
            $this->issueDate = $issueDate;
        }
    }

    /**
     * @var string Professional ID of the issuer.
     */
    public $issuerId;

    /**
     * @var string Invoice number.
     */
    public $invoiceNumber;

    /**
     * @var DateTimeImmutable Invoice validation date.
     *
     * NOTE: Time part will be ignored.
     */
    public $issueDate;
}
