# Auto Verifactu

[![Package Versión](https://img.shields.io/badge/version-v1.0.0-f68243)](composer.json)
[![Versión de PHP](https://img.shields.io/badge/php-%3E%3D8.2-8892BF)](composer.json)

Auto Veri\*Factu es un módulo de Dolibarr sencillo que permite generar registros de facturación según el sistema [VERI\*FACTU](https://sede.agenciatributaria.gob.es/Sede/iva/sistemas-informaticos-facturacion-verifactu.html) y su envio telemático a la Agencia Tributaria (AEAT), integrado con el sistema de facturación de Dolibarr.

## Funcionamiento

Una vez instalado y activado, el módulo bloquea la edición de facturas validadas.

En el instante de validación, estas serán comunicadas a los endpoints del sistema Veri\*Factu con su respectiva huella digital. El sistema guardará una copia inmutable del documento XML generado, la huella immutable de la firma y la fecha de validación.

A su vez, el módulo Auto Verifactu requiere del módulo de **Archivos Inalterables** para el registro de eventos de creación y validación de facturas. Este módulo sirve de respaldo contra el que validar la integridad de la información de las facturas.

Por último, el módulo se encara de añadir el código QR correspondiente a las facturas generadas en formato PDF.

> Auto Verifactu no soporta la modalidad \*NO Verifactu\*.

## Declaración de responsabilidad

Este módulo se proporciona sin una declaración responsable firmada por Còdec. El código, sujeto a una licencia GPL, está abierto a reutilización, cópia y modificación por parte del público, por lo que Códec no puede hacerse responsable del uso que otros hagan del mismo.

El requerimiento de la declaración responsable que emana del [Real Decreto 1007/2023](https://www.boe.es/buscar/act.php?id=BOE-A-2023-24840) atenta contra los principios del código libre y abierto: El principio de descargo de responsabilidad del autor es imprescindible para la libre circulación del código bajo licencias abiertas.

La no observación del principio de descargo de responsabilidad promueve medidas que limiten los derechos y libertades fundamentales del movimiento del codigo abierto: la libertad de uso, distribución, copia y modificación.

Ante esta situación, lo que propone este módulo es la _auto declaración responsable_, un mecanismo incluido en el propio módulo a través del cual el titular de la instancia Dolibarr en la que este se instale puede firmar su propia declaración responsable. La libertad de copiar y modificar el programa conlleva la responsabilidad sobre el uso que de él se haga.

Auto-Veri\*Factu solo podrá activarse previa generación de la auto declaración responsable.

## Instalación y activación

Puedes descargarte la última versión del código desde el [listado de versiones disponibles](https://gitlab.com/codeccoop/dolibarr/autoverifactu/-/releases).

Una vez obtenido el paquete zip con el código, deberás subirlo a tu instancia de Dolibarr desde el menú `Inicio > Configuración > Módulos > Instalación de módulos externos`.

Una vez instalado, falta su activación. Para activar el Auto-Veri\*Factu deberás cumplir los siguientes requisitos:

1. Tener informado una **Razón Social** y un **NIF** válido en la configuración de tu compañía.
2. Haber subido el fichero PKCS#12 con el certificato eletrónico de la compañía/persona física y su contraseña a través del formulario de configuración del módulo.
3. Haber generado una versión auto firmada de la declaración responsable usando la plantill que se ofrece en el panel de adminsitración del módulo.

Una vez cumplidos los requisitos, podrás activar el Auto-Veri\*Factu. **Ten en cuenta que una vez activado, ciertas funciones de Dolibarr quedaran bloqueadas, como son la edición de facturas validadas o la actualización de tus datos societarios**.

## Hoja de ruta

1. Soporte para el módulo _Multi Company_. Por ahora, **Auto Veri\*Factu no permite su uso en entornos multi compañía**.
2. Las facturas se vuelven inmutables una vez validadas, pero en ocasiones, la API de Veri\*Factu acepta facturas con errores. En estos casos, se debería poder subsanar (modificar la factura).
3. ¿Las donaciones se han de incluir en el sistema?
