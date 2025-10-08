# Auto Verifactu

Auto Verifactu es un módulo de Dolibarr sencillo que permite generar registros de facturación según el sistema [VERI\*FACTU](https://sede.agenciatributaria.gob.es/Sede/iva/sistemas-informaticos-facturacion-verifactu.html) y su envio telemático a la Agencia Tributaria (AEAT), integrado con el sistema de facturación de Dolibarr.

## Funcionamiento

Una vez instalado y activado, el módulo bloquea la edición de facturas validadas.

En el instante de validación, estas serán comunicadas a los endpoints del sistema verifactu con su respectiva huella digital. El sistema guardará una copia inmutable del documento XML generado.

A su vez, el módulo Auto Verifactu requiere del módulo de **Archivos Inalterables** para el registro de eventos de creación y validación de facturas.

Por último, el módulo se encara de añadir el código QR correspondiente a las facturas generadas en formato PDF.

> Auto Verifactu no soporta la modalidad \*NO Verifactu\*.

## Declaración de responsabilidad

Este módulo se proporciona sin una declaración responsable firmada por Còdec. El código, sujeto a una licencia GPL está abierto a reutilización, cópia y modificación por parte del público, por lo que Códec no puede hacerse responsable del uso que otros hagan del mismo.

El requerimiento de la declaración de responsabilidad que emana del [Real Decreto 1007/2023](https://www.boe.es/buscar/act.php?id=BOE-A-2023-24840) atenta contra los principios del código libre y abierto:

- Desincentiva el desarollo de soluciones abiertas para la integración con el sistema Veri\*Factu debido a la responsabilidad legal por uso que se hace descansar en el propietario intelectual.
- Imposibilita la adopción de soluciones abiertas por parte de las empresas y autónomos ya que sólo podrán hacer uso de programarios respaldados por un proveedor a través de un vínculo comercial.
- Desincentiva a los desarrolladores a abrir su código por el riesgo que supone emitir una declaración de responsabilidad sobre un código que puede ser modificado por terceros.

Ante esta situación, lo que propone este módulo es la generación de una _auto declaración de responsabilidad_ en la que el propio titular de la instancia Dolibarr en la que se instale el módulo, y haciendo uso de su certificado electrónico, firme su propia declaración de responsabilidad.
