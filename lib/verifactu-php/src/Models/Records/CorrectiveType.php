<?php
namespace josemmo\Verifactu\Models\Records;

/**
 * Tipo de factura rectificativa
 */
class CorrectiveType
{
    /**
     * Por sustitución
     *
     * Se emite una factura rectificativa que sustituye completamente a la factura original.
     * La factura original queda anulada.
     */
    const SUBSTITUTION = 'S';

    /**
     * Por diferencias
     *
     * Se emite una factura rectificativa que complementa la factura original, corrigiendo únicamente las diferencias en
     * importes o datos específicos.
     * La factura original sigue siendo válida.
     */
    const DIFFERENCES = 'I';
}
