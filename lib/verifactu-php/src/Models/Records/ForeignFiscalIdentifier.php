<?php
namespace josemmo\Verifactu\Models\Records;

use josemmo\Verifactu\Models\Model;

/**
 * Identificador fiscal de fuera de España
 */
class ForeignFiscalIdentifier extends Model
{
    /**
     * @var string Legal name.
     */
    public $name;

    /**
     * @var string Country code in ISO 3166-1 alpha-2 standard.
     */
    public $country;

    /**
     * @var string Key of the foreign ID type based on the ForeignIdType class.
     */
    public $type;

    /**
     * @var string ID number.
     */
    public $value;
}
