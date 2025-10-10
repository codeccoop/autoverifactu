<?php
namespace josemmo\Verifactu\Models\Records;

use DateTimeImmutable;
use josemmo\Verifactu\Models\ComputerSystem;
use josemmo\Verifactu\Models\Model;
use UXML\UXML;

/**
 * Base invoice record
 */
abstract class Record extends Model
{
    /**
     * @var InvoiceIdentifier Invoice identifier.
     */
    public $invoiceId;

    /**
     * @var InvoiceIdentifier|null Invoice identifier of the previous invoice.
     */
    public $previousInvoiceId;

    /**
     * @var ComputerSystem Information about the computer system.
     */
    public $system;

    /**
     * @var string|null First 64 characters of the previous invoice hash.
     */
    public $previousHash;

    /**
     * @var string Cryptographical hash of the invoice.
     */
    public $hash;

    /**
     * @var DateTimeImmutable Date at which the hash was computed.
     */
    public $hashedAt;

    /**
     * Calculate record hash
     *
     * @return string Expected record hash
     */
    abstract public function calculateHash();

    final public function validatePreviousInvoice($context)
    {
        if ($this->previousInvoiceId !== null && $this->previousHash === null) {
            $context->buildViolation('Previous hash is required if previous invoice ID is provided')
                ->atPath('previousHash')
                ->addViolation();
        } elseif ($this->previousHash !== null && $this->previousInvoiceId === null) {
            $context->buildViolation('Previous invoice ID is required if previous hash is provided')
                ->atPath('previousInvoiceId')
                ->addViolation();
        }
    }

    /**
     * Returns the record as a serialized UXML instance.
     *
     * @return UXML
     */
    public function asUXML()
    {
        $uxml = UXML::newInstance(
            'autoverifactu:Record',
            null,
            [
                'xmlns:sum' => self::NS_SUM,
                'xmlns:sum1' => self::NS_SUM1,
            ]
        );

        $isRegistrationRecord = $this instanceof RegistrationRecord;

        $recordElementName = $isRegistrationRecord
            ? 'RegistroAlta'
            : 'RegistroAnulacion';

        $recordElement = $uxml
            ->add('sum:RegistroFactura')
            ->add('sum1:' . $recordElementName);

        $recordElement->add('sum1:IDVersion', '1.0');

        $this->addRecordProperties($recordElement);

        $encadenamientoElement = $recordElement->add('sum1:Encadenamiento');
        if ($this->previousInvoiceId === null) {
            $encadenamientoElement->add('sum1:PrimerRegistro', 'S');
        } else {
            $registroAnteriorElement = $encadenamientoElement->add('sum1:RegistroAnterior');
            $registroAnteriorElement->add('sum1:IDEmisorFactura', $this->previousInvoiceId->issuerId);
            $registroAnteriorElement->add('sum1:NumSerieFactura', $this->previousInvoiceId->invoiceNumber);
            $registroAnteriorElement->add('sum1:FechaExpedicionFactura', $this->previousInvoiceId->issueDate->format('d-m-Y'));
            $registroAnteriorElement->add('sum1:Huella', $this->previousHash);
        }

        $sistemaInformaticoElement = $recordElement->add('sum1:SistemaInformatico');
        $sistemaInformaticoElement->add('sum1:NombreRazon', $this->system->vendorName);
        $sistemaInformaticoElement->add('sum1:NIF', $this->system->vendorNif);
        $sistemaInformaticoElement->add('sum1:NombreSistemaInformatico', $this->system->name);
        $sistemaInformaticoElement->add('sum1:IdSistemaInformatico', $this->system->id);
        $sistemaInformaticoElement->add('sum1:Version', $this->system->version);
        $sistemaInformaticoElement->add('sum1:NumeroInstalacion', $this->system->installationNumber);
        $sistemaInformaticoElement->add('sum1:TipoUsoPosibleSoloVerifactu', $this->system->onlySupportsVerifactu ? 'S' : 'N');
        $sistemaInformaticoElement->add('sum1:TipoUsoPosibleMultiOT', $this->system->supportsMultipleTaxpayers ? 'S' : 'N');
        $sistemaInformaticoElement->add('sum1:IndicadorMultiplesOT', $this->system->hasMultipleTaxpayers ? 'S' : 'N');

        $recordElement->add('sum1:FechaHoraHusoGenRegistro', $this->hashedAt->format('c'));
        $recordElement->add('sum1:TipoHuella', '01'); // SHA-256
        $recordElement->add('sum1:Huella', $this->hash);

        return $uxml;
    }

    /**
     * Add record properties based on its type.
     *
     * @param UXML $record Element to fill
     */
    abstract public function addRecordProperties($record);
}
