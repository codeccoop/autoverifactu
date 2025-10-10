<?php
namespace josemmo\Verifactu\Models\Records;

class OperationType
{
    /** Operación sujeta y no exenta - Sin inversión del sujeto pasivo */
    const S1 = 'S1';

    /** Operación sujeta y no exenta - Con inversión del sujeto pasivo */
    const S2 = 'S2';

    /** Operación no sujeta - Artículos 7, 14 y otros */
    const N1 = 'N1';

    /** Operación no sujeta por reglas de localización */
    const N2 = 'N2';
}
