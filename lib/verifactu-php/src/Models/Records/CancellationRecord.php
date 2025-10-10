<?php
namespace josemmo\Verifactu\Models\Records;

/**
 * Registro de anulación de una factura
 *
 * @field RegistroAnulacion
 */
class CancellationRecord extends Record
{
    /**
     * @inheritDoc
     */
    public function calculateHash()
    {
        // NOTE: Values should NOT be escaped as that what the AEAT says ¯\_(ツ)_/¯
        $payload  = 'IDEmisorFacturaAnulada=' . $this->invoiceId->issuerId;
        $payload .= '&NumSerieFacturaAnulada=' . $this->invoiceId->invoiceNumber;
        $payload .= '&FechaExpedicionFacturaAnulada=' . $this->invoiceId->issueDate->format('d-m-Y');
        $payload .= '&Huella=' . ($this->previousHash ?? '');
        $payload .= '&FechaHoraHusoGenRegistro=' . $this->hashedAt->format('c');
        return strtoupper(hash('sha256', $payload));
    }

    final public function validateEnforcePreviousInvoice($context)
    {
        if ($this->previousInvoiceId === null) {
            $context->buildViolation('Previous invoice ID is required for all cancellation records')
                ->atPath('previousInvoiceId')
                ->addViolation();
        }
        if ($this->previousHash === null) {
            $context->buildViolation('Previous hash is required for all cancellation records')
                ->atPath('previousHash')
                ->addViolation();
        }
    }

    /**
     * Add cancellation record properties
     *
     * @param UXML $record Element to fill
     *
     * @return UXML
     */
    public function addRecordProperties($record)
    {
        $idFacturaElement = $record->add('sum1:IDFactura');
        $idFacturaElement->add('sum1:IDEmisorFacturaAnulada', $this->invoiceId->issuerId);
        $idFacturaElement->add('sum1:NumSerieFacturaAnulada', $this->invoiceId->invoiceNumber);
        $idFacturaElement->add('sum1:FechaExpedicionFacturaAnulada', $this->invoiceId->issueDate->format('d-m-Y'));

        return $record;
    }
}
