<?php
namespace josemmo\Verifactu\Models\Records;

class ForeignIdType
{
    /** NIF-IVA */
    const VAT = '02';

    /** Pasaporte */
    const PASSPORT = '03';

    /** Documento oficial de identificación expedido por el país o territorio de residencia */
    const NATIONALID = '04';

    /** Certificado de residencia */
    const RESIDENCE = '05';

    /** Otro documento probatorio */
    const OTHER = '06';

    /** No censado */
    const UNREGISTERED = '07';
}
