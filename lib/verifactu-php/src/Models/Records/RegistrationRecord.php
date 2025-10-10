<?php
namespace josemmo\Verifactu\Models\Records;

use UXML\UXML;

/**
 * Registro de alta de una factura
 *
 * @field RegistroAlta
 */
class RegistrationRecord extends Record
{
    /**
     * @var string Issuer company name.
     */
    public $issuerName;

    /**
     * @var string Key of the invoice type from the InvoiceType class.
     */
    public $invoiceType;

    /**
     * @var string Invoice operation description.
     */
    public $description;

    /**
     * @var (FiscalIdentifier|ForeignFiscalIdentifier)[] Invoice receivers
     */
    public $recipients = [];

    /**
     * @var string|null Key of the corrective type from the CorrectiveType class.
     */
    public $correctiveType = null;

    /**
     * @var InvoiceIdentifier[] Fixed invoices
     */
    public $correctedInvoices = [];

    /**
     * @var string|null Fixed invoices base amount formatted, with two decimal precission.
     */
    public $correctedBaseAmount = null;

    /**
     * @var string|null Fixec invoices tax amount, formatted with two decimal precission.
     */
    public $correctedTaxAmount = null;

    /**
     * @var InvoiceIdentifier[] Replaced invoices.
     */
    public $replacedInvoices = [];

    /**
     * @var BreakdownDetails[] Invoice lines as breakdown details.
     */
    public $breakdown = [];

    /**
     * @var string Total tax amount, formatted with two decimal precission.
     */
    public $totalTaxAmount;

    /**
     * @var string Total amount, formatted with two decimal precission.
     */
    public $totalAmount;

    /**
     * @inheritDoc
     */
    public function calculateHash()
    {
        // NOTE: Values should NOT be escaped as that what the AEAT says ¯\_(ツ)_/¯
        $payload  = 'IDEmisorFactura=' . $this->invoiceId->issuerId;
        $payload .= '&NumSerieFactura=' . $this->invoiceId->invoiceNumber;
        $payload .= '&FechaExpedicionFactura=' . $this->invoiceId->issueDate->format('d-m-Y');
        $payload .= '&TipoFactura=' . $this->invoiceType;
        $payload .= '&CuotaTotal=' . $this->totalTaxAmount;
        $payload .= '&ImporteTotal=' . $this->totalAmount;
        $payload .= '&Huella=' . ($this->previousHash ?? '');
        $payload .= '&FechaHoraHusoGenRegistro=' . $this->hashedAt->format('c');
        return strtoupper(hash('sha256', $payload));
    }

    final public function validateTotals($context): void
    {
        if (!isset($this->breakdown) || !isset($this->totalTaxAmount) || !isset($this->totalAmount)) {
            return;
        }

        $expectedTotalTaxAmount = 0;
        $totalBaseAmount = 0;
        foreach ($this->breakdown as $details) {
            if (!isset($details->taxAmount) || !isset($details->baseAmount)) {
                return;
            }
            $expectedTotalTaxAmount += $details->taxAmount;
            $totalBaseAmount += $details->baseAmount;
        }

        $expectedTotalTaxAmount = number_format($expectedTotalTaxAmount, 2, '.', '');
        if ($this->totalTaxAmount !== $expectedTotalTaxAmount) {
            $context->buildViolation("Expected total tax amount of $expectedTotalTaxAmount, got {$this->totalTaxAmount}")
                ->atPath('totalTaxAmount')
                ->addViolation();
        }

        $validTotalAmount = false;
        $bestTotalAmount = $totalBaseAmount + $expectedTotalTaxAmount;
        foreach ([0, -0.01, 0.01, -0.02, 0.02] as $tolerance) {
            $expectedTotalAmount = number_format($bestTotalAmount + $tolerance, 2, '.', '');
            if ($this->totalAmount === $expectedTotalAmount) {
                $validTotalAmount = true;
                break;
            }
        }
        if (!$validTotalAmount) {
            $bestTotalAmount = number_format($bestTotalAmount, 2, '.', '');
            $context->buildViolation("Expected total amount of $bestTotalAmount, got {$this->totalAmount}")
                ->atPath('totalAmount')
                ->addViolation();
        }
    }

    /**
     * Add registration record properties
     *
     * @param UXML $record Element to fill.
     *
     * @return UXML
     */
    public function addRecordProperties($record)
    {
        $idFacturaElement = $record->add('sum1:IDFactura');
        $idFacturaElement->add('sum1:IDEmisorFactura', $this->invoiceId->issuerId);
        $idFacturaElement->add('sum1:NumSerieFactura', $this->invoiceId->invoiceNumber);
        $idFacturaElement->add('sum1:FechaExpedicionFactura', $this->invoiceId->issueDate->format('d-m-Y'));

        $record->add('sum1:NombreRazonEmisor', $this->issuerName);
        $record->add('sum1:TipoFactura', $this->invoiceType);

        if ($this->correctiveType !== null) {
            $record->add('sum1:TipoRectificativa', $this->correctiveType);
        }

        if (count($this->correctedInvoices) > 0) {
            $facturasRectificadasElement = $record->add('sum1:FacturasRectificadas');
            foreach ($this->correctedInvoices as $correctedInvoice) {
                $facturaRectificadaElement = $facturasRectificadasElement->add('sum1:IDFacturaRectificada');
                $facturaRectificadaElement->add('sum1:IDEmisorFactura', $correctedInvoice->issuerId);
                $facturaRectificadaElement->add('sum1:NumSerieFactura', $correctedInvoice->invoiceNumber);
                $facturaRectificadaElement->add('sum1:FechaExpedicionFactura', $correctedInvoice->issueDate->format('d-m-Y'));
            }
        }
        if (count($this->replacedInvoices) > 0) {
            $facturasSustituidasElement = $record->add('sum1:FacturasSustituidas');
            foreach ($this->replacedInvoices as $replacedInvoice) {
                $facturaSustituidaElement = $facturasSustituidasElement->add('sum1:IDFacturaSustituida');
                $facturaSustituidaElement->add('sum1:IDEmisorFactura', $replacedInvoice->issuerId);
                $facturaSustituidaElement->add('sum1:NumSerieFactura', $replacedInvoice->invoiceNumber);
                $facturaSustituidaElement->add('sum1:FechaExpedicionFactura', $replacedInvoice->issueDate->format('d-m-Y'));
            }
        }
        if ($this->correctedBaseAmount !== null && $this->correctedTaxAmount !== null) {
            $importeRectificacionElement = $record->add('sum1:ImporteRectificacion');
            $importeRectificacionElement->add('sum1:BaseRectificada', $this->correctedBaseAmount);
            $importeRectificacionElement->add('sum1:CuotaRectificada', $this->correctedTaxAmount);
        }

        $record->add('sum1:DescripcionOperacion', $this->description);

        if (count($this->recipients) > 0) {
            $destinatariosElement = $record->add('sum1:Destinatarios');
            foreach ($this->recipients as $recipient) {
                $destinatarioElement = $destinatariosElement->add('sum1:IDDestinatario');
                $destinatarioElement->add('sum1:NombreRazon', $recipient->name);
                if ($recipient instanceof FiscalIdentifier) {
                    $destinatarioElement->add('sum1:NIF', $recipient->nif);
                } else {
                    $idOtroElement = $destinatarioElement->add('sum1:IDOtro');
                    $idOtroElement->add('sum1:CodigoPais', $recipient->country);
                    $idOtroElement->add('sum1:IDType', $recipient->type);
                    $idOtroElement->add('sum1:ID', $recipient->value);
                }
            }
        }

        $desgloseElement = $record->add('sum1:Desglose');
        foreach ($this->breakdown as $breakdownDetails) {
            $detalleDesgloseElement = $desgloseElement->add('sum1:DetalleDesglose');
            $detalleDesgloseElement->add('sum1:Impuesto', $breakdownDetails->taxType);
            $detalleDesgloseElement->add('sum1:ClaveRegimen', $breakdownDetails->regimeType);
            $detalleDesgloseElement->add('sum1:CalificacionOperacion', $breakdownDetails->operationType);
            $detalleDesgloseElement->add('sum1:TipoImpositivo', $breakdownDetails->taxRate);
            $detalleDesgloseElement->add('sum1:BaseImponibleOimporteNoSujeto', $breakdownDetails->baseAmount);
            $detalleDesgloseElement->add('sum1:CuotaRepercutida', $breakdownDetails->taxAmount);
        }

        $record->add('sum1:CuotaTotal', $this->totalTaxAmount);
        $record->add('sum1:ImporteTotal', $this->totalAmount);

        return $record;
    }
}
