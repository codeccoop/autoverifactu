<?php
namespace josemmo\Verifactu\Models\Records;

class InvoiceType
{
    /** Factura (Art. 6, 7.2 y 7.3 del R.D. 1619/2012) */
    const FACTURA = 'F1';

    /** Factura simplificada y facturas sin identificación del destinatario (Art. 6.1.D del R.D. 1619/2012) */
    const SIMPLIFICADA = 'F2';

    /** Factura emitida en sustitución de facturas simplificadas facturadas y declaradas */
    const SUSTITUTIVA = 'F3';

    /** Factura rectificativa (Art 80.1 y 80.2 y error fundado en derecho) */
    const R1 = 'R1';

    /** Factura rectificativa (Art. 80.3) */
    const R2 = 'R2';

    /** Factura rectificativa (Art. 80.4) */
    const R3 = 'R3';

    /** Factura rectificativa (Resto) */
    const R4 = 'R4';

    /** Factura rectificativa en facturas simplificadas */
    const R5 = 'R5';
}
