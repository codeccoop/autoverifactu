<?php
namespace josemmo\Verifactu\Models\Records;

/**
 * Claves de Régimen Especial o Trascendencia Adicional
 */
class RegimeType
{
    /** Operación de régimen general. */
    const C01 = '01';

    /** Exportación. */
    const C02 = '02';

    /** Operaciones a las que se aplique el régimen especial de bienes usados, objetos de arte, antigüedades y objetos de colección. */
    const C03 = '03';

    /** Régimen especial del oro de inversión. */
    const C04 = '04';

    /** Régimen especial de las agencias de viajes. */
    const C05 = '05';

    /** Régimen especial grupo de entidades en IVA (Nivel Avanzado) */
    const C06 = '06';

    /** Régimen especial del criterio de caja. */
    const C07 = '07';

    /** Operaciones sujetas al IPSI / IGIC (Impuesto sobre la Producción, los Servicios y la Importación / Impuesto General Indirecto Canario). */
    const C08 = '08';

    /** Facturación de las prestaciones de servicios de agencias de viaje que actúan como mediadoras en nombre y por cuenta ajena (D.A 4ª RD1619/2012) */
    const C09 = '09';

    /** Cobros por cuenta de terceros de honorarios profesionales o de derechos derivados de la propiedad industrial, de autor u otros por cuenta de sus socios, asociados o colegiados efectuados por sociedades, asociaciones, colegios profesionales u otras entidades que realicen estas funciones de cobro. */
    const C10 = '10';

    /** Operaciones de arrendamiento de local de negocio. */
    const C11 = '11';

    /** Factura con IVA pendiente de devengo en certificaciones de obra cuyo destinatario sea una Administración Pública. */
    const C14 = '14';

    /** Factura con IVA pendiente de devengo en operaciones de tracto sucesivo. */
    const C15 = '15';

    /** Operación acogida a alguno de los regímenes previstos en el Capítulo XI del Título IX (OSS e IOSS) */
    const C17 = '17';

    /** Recargo de equivalencia. */
    const C18 = '18';

    /** Operaciones de actividades incluidas en el Régimen Especial de Agricultura, Ganadería y Pesca (REAGYP) */
    const C19 = '19';

    /** Régimen simplificado */
    const C20 = '20';
}
