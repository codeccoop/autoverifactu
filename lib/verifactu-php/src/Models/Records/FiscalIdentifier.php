<?php
namespace josemmo\Verifactu\Models\Records;

use josemmo\Verifactu\Models\Model;

/**
 * Identificador fiscal
 *
 * @field Caberecera/ObligadoEmision
 * @field Caberecera/Representante
 */
class FiscalIdentifier extends Model
{
    /**
     * Class constructor
     *
     * @param string|null $name Name
     * @param string|null $nif  NIF
     */
    public function __construct(
        $name = null,
        $nif = null,
    ) {
        if ($name !== null) {
            $this->name = $name;
        }
        if ($nif !== null) {
            $this->nif = $nif;
        }
    }

    /**
     * Nombre-razón social
     *
     * @field NombreRazon
     */
    public $name;

    /**
     * @var string NIF.
     */
    public $nif;
}
