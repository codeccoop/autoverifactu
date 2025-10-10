<?php
namespace josemmo\Verifactu\Models;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Computer system
 *
 * @field SistemaInformatico
 */
class ComputerSystem extends Model
{
    /**
     * @var string Vendor legal name.
     */
    public $vendorName;

    /**
     * @var Vendor tax ID.
     */
    public $vendorNif;

    /**
     * @var string System name.
     */
    public $name;

    /**
     * @var string System ID as a two char code.
     */
    public $id;

    /**
     * @var string System version.
     */
    public $version;

    /**
     * @var string Installation number.
     */
    public $installationNumber;

    /**
     * @var bool True if the system only supports the live verifactu protocol.
     */
    public $onlySupportsVerifactu;

    /**
     * @var bool True if the system supports multiple taxpayers.
     */
    public bool $supportsMultipleTaxpayers;

    /**
     * @var bool The system handles the invoicing of more than one taxpayer.
     */
    public $hasMultipleTaxpayers;
}
