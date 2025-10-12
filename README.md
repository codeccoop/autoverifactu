# Auto Verifactu

Auto Veri\*Factu es un módulo de Dolibarr sencillo que permite generar registros de facturación según el sistema [VERI\*FACTU](https://sede.agenciatributaria.gob.es/Sede/iva/sistemas-informaticos-facturacion-verifactu.html) y su envio telemático a la Agencia Tributaria (AEAT), integrado con el sistema de facturación de Dolibarr.

## Funcionamiento

Una vez instalado y activado, el módulo bloquea la edición de facturas validadas.

En el instante de validación, estas serán comunicadas a los endpoints del sistema Veri\*Factu con su respectiva huella digital. El sistema guardará una copia inmutable del documento XML generado, la huella immutable de la firma y la fecha de validación.

A su vez, el módulo Auto Verifactu requiere del módulo de **Archivos Inalterables** para el registro de eventos de creación y validación de facturas. Este módulo sirve de respaldo contra el que validar la integridad de la información de las facturas.

Por último, el módulo se encara de añadir el código QR correspondiente a las facturas generadas en formato PDF.

> Auto Verifactu no soporta la modalidad \*NO Verifactu\*.

## Declaración de responsabilidad

Este módulo se proporciona sin una declaración responsable firmada por Còdec. El código, sujeto a una licencia GPL está abierto a reutilización, cópia y modificación por parte del público, por lo que Códec no puede hacerse responsable del uso que otros hagan del mismo.

El requerimiento de la declaración de responsabilidad que emana del [Real Decreto 1007/2023](https://www.boe.es/buscar/act.php?id=BOE-A-2023-24840) atenta contra los principios del código libre y abierto:

- Desincentiva el desarollo de soluciones abiertas para la integración con el sistema Veri\*Factu debido a la responsabilidad legal por uso que se hace descansar en el propietario intelectual.
- Imposibilita la adopción de soluciones abiertas por parte de las empresas y autónomos ya que sólo podrán hacer uso de programarios respaldados por un proveedor a través de un vínculo comercial.
- Desincentiva a los desarrolladores a abrir su código por el riesgo que supone emitir una declaración de responsabilidad sobre un código que puede ser modificado por terceros.

Ante esta situación, lo que propone este módulo es la generación de una _auto declaración de responsabilidad_ en la que el propio titular de la instancia Dolibarr en la que se instale el módulo, y haciendo uso de su certificado electrónico, firme su propia declaración de responsabilidad.

## Instalación y activación

Puedes descargarte la última versión del código desde el [listado de versiones disponibles](https://gitlab.com/codeccoop/dolibarr/auto-verifactu/-/releases).

Una vez obtenido el packete zip con el código, deberás subirlo a tu instancia de Dolibarr desde el menú `Inicio > Configuración > Módulos > Instalación de módulos externos`.

Una vez instalado, falta su activación. Para activar el Auto-Veri*Factu deberás cumplir los siguientes requisitos:

1. Tener informado una **Razón Social** y un **NIF** válido en la configuración de tu compañía.
2. Haber subido el fichero PKCS#12 con el certificato eletrónico de la compañía y su contraseña.
3. Haber generado una versión auto firmada de la declaración responsable.

Una vez cumplidos los requisitos, podrás activar el Auto-Veri*Factu. **Ten en cuenta que una vez activado, ciertas funciones de Dolibarr quedaran bloqueadas, como son la  edición de facturas validadas o la actualización de tus datos societarios**.
