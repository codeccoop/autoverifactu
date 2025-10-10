<?php
namespace josemmo\Verifactu\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use josemmo\Verifactu\Models\Records\CancellationRecord;
use josemmo\Verifactu\Models\Records\FiscalIdentifier;
use josemmo\Verifactu\Models\Records\RegistrationRecord;
use UXML\UXML;

/**
 * Class to communicate with the AEAT web service endpoint for VERI*FACTU
 */
class AeatClient
{
    public const NS_SOAPENV = 'http://schemas.xmlsoap.org/soap/envelope/';
    public const NS_SUM = 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroLR.xsd';
    public const NS_SUM1 = 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroInformacion.xsd';

    private $client;
    private $isProduction = true;

    /**
     * Class constructor
     *
     * NOTE: The certificate path must have the ".p12" extension to be recognized as a PFX bundle.
     *
     * @param string           $certPath     Path to encrypted PEM certificate or PKCS#12 (PFX) bundle
     * @param string|null      $certPassword Certificate password or `null` for none
     */
    public function __construct(
        $certPath,
        $certPassword = null,
    ) {
        $this->client = new Client([
            'cert' => ($certPassword === null) ? $certPath : [$certPath, $certPassword],
            'headers' => [
                'User-Agent' => "Mozilla/5.0 (compatible; Módulo Auto-Veri*Factu de Dolibarr/0.0.1)",
            ],
        ]);
    }

    /**
     * Set production environment
     *
     * @param bool $production Pass `true` for production, `false` for testing
     *
     * @return $this This instance
     */
    public function setProduction(bool $production): static
    {
        $this->isProduction = $production;
        return $this;
    }

    /**
     * Send invoicing records
     *
     * @param RegistrationRecord|CancellationRecord $record Invoicing record
     * @param FiscalIdentifier|null $representative Representative details (party that sends the invoices)
     *
     * @return UXML XML response from web service
     *
     * @throws GuzzleException if request failed
     */
    public function send($record, $representative = null)
    {
        // Build initial request
        $xml = UXML::newInstance('soapenv:Envelope', null, [
            'xmlns:soapenv' => self::NS_SOAPENV,
            'xmlns:sum' => self::NS_SUM,
            'xmlns:sum1' => self::NS_SUM1,
        ]);

        $xml->add('soapenv:Header');
        $baseElement = $xml->add('soapenv:Body')->add('sum:RegFactuSistemaFacturacion');

        // Add header
        $cabeceraElement = $baseElement->add('sum:Cabecera');
        $obligadoEmisionElement = $cabeceraElement->add('sum1:ObligadoEmision');
        $obligadoEmisionElement->add('sum1:NombreRazon', $record->issuerName);
        $obligadoEmisionElement->add('sum1:NIF', $record->invoiceId->issuerId);

        if ($representative !== null) {
            $representanteElement = $cabeceraElement->add('sum1:Representante');
            $representanteElement->add('sum1:NombreRazon', $representative->name);
            $representanteElement->add('sum1:NIF', $representative->nif);
        }

        $uxml = $record->asUXML();
        $recordElement = $uxml->get('sum:RegistroFactura');
        $baseElement->add($recordElement);

        // Send request
        $response = $this->client->post('/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSOAP', [
            'base_uri' => $this->getBaseUri(),
            'headers' => [
                'Content-Type' => 'text/xml',
            ],
            'body' => $xml->asXML(),
        ]);

        return UXML::fromString($response->getBody()->getContents());
    }

    /**
     * Get base URI of web service
     *
     * @return string Base URI
     */
    private function getBaseUri(): string
    {
        return $this->isProduction ? 'https://www1.agenciatributaria.gob.es' : 'https://prewww1.aeat.es';
    }
}
