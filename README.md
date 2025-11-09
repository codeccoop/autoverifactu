# Auto Veri\*Factu

[![Package Versión](https://img.shields.io/github/v/release/codeccoop/autoverifactu)](composer.json)
[![Versión de PHP](https://img.shields.io/badge/php-%3E%3D8.2-8892BF)](composer.json)
[![Versión de Dolibarr](https://img.shields.io/badge/dolibarr-%3E%3D20.0-263c5c)](composer.json)

> Este proyecto se encuentra en fase de pruebas, por lo que no se recomienda su uso en un entorno productivo 🙀. Por el mismo motivo, el proyecto está abierto a contruibuciones y aportaciones, que serán gratamente bienvenidas 🫰.

**Auto Veri\*Factu** es un módulo de Dolibarr sencillo que permite generar registros de facturación según el sistema Veri\*Factu.

Una vez instalado y activado, el módulo bloquea la edición de facturas validadas.

En el instante de validación, estas serán comunicadas a los _endpoints_ del sistema Veri\*Factu con su respectiva huella digital. El sistema guardará una copia inmutable del documento XML generado, la huella immutable de la firma y la fecha de validación.

A su vez, el módulo Auto Veri\*Factu requiere del módulo de **Archivos Inalterables** para el registro de eventos de creación y validación de facturas. Este módulo sirve de respaldo contra el que validar la integridad de la información de las facturas.

Por último, el módulo se encarga de añadir el código QR correspondiente a las facturas generadas en formato PDF.

> Auto-Veri\*Factu no soporta la modalidad _«NO Veri\*Factu»_.

## Declaración responsable

Este módulo se proporciona sin una declaración responsable firmada por Còdec. El código, sujeto a una [licencia GPL](https://github.com/codeccoop/autoverifactu/blob/main/LICENSE), está abierto a reutilización, cópia y modificación por parte del público, por lo que Códec no puede hacerse responsable del uso que otros hagan del mismo.

El requerimiento de la declaración responsable que emana del [Real Decreto 1007/2023](https://www.boe.es/buscar/act.php?id=BOE-A-2023-24840) atenta contra los principios del código libre y abierto: El principio de descargo de responsabilidad del autor es imprescindible para la libre circulación del código bajo licencias abiertas. La misma licencia bajo la que se distribuye Dolibarr recoge lo siguiente: _«This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE»_.

La no observación del principio de descargo de responsabilidad incentiva al propietario intelectual del código a tomar medidas que limiten los derechos y libertades fundamentales del movimiento del codigo abierto: la libertad de uso, distribución, copia y modificación.

Ante esta situación, lo que propone este módulo es la _auto declaración responsable_, un mecanismo incluido en el propio módulo a través del cual el titular de la instancia Dolibarr en la que este se instale puede firmar su propia declaración responsable. De esta forma, se consigue la homologación del mòdulo como SIF segun lo expuesto en la normativa Veri*Factu, y el descargo de responsabilidad desde el autor al usuario y/o proveedor. **La libertad de copiar y modificar el programa conlleva la responsabilidad sobre el uso que de él se haga**.

> Auto-Veri\*Factu solo podrá activarse previa generación de la auto declaración responsable.

## Instalación y activación

Puedes descargarte la última versión del código desde el listado de versiones disponibles en [GitHub 🐱](https://github.com/codeccoop/autoverifactu/releases).

Una vez obtenido el paquete zip con el código, deberás subirlo a tu instancia de Dolibarr desde el menú `Inicio > Configuración > Módulos > Instalación de módulos externos`.

Una vez instalado, falta su activación. Para activar el módulo Auto-Veri\*Factu deberás cumplir los siguientes requisitos:

1. Tener informado una **Razón Social** y un **NIF** válido en la configuración de tu compañía.
2. Haber subido el fichero PKCS#12 con el certificato eletrónico de la compañía/persona física y su contraseña a través del formulario de configuración del módulo.
3. Haber generado una versión auto firmada de la declaración responsable usando la plantill que se ofrece en el panel de adminsitración del módulo.
4. En el panel de configuración del módulo, haber seleccionado el tipo de impuesto al que está sometida tu actividad económica y el régimen fiscal.
5. Disponer del módulo **Archivos Inalterable** activado y de la opción de _"Fuerza la fecha de factura a la fecha de valicación"_ de la configuración del módulo de facturas marcada (automático).

Una vez cumplidos los requisitos, podrás activar Auto-Veri\*Factu. **Ten en cuenta que una vez activado, ciertas funciones de Dolibarr quedaran bloqueadas, como son la edición de facturas validadas o la actualización de tus datos societarios**.

## Desarrollo

Des de el panel de configuración de **Auto-Veri*Factu** se puede activar el **modo de pruebas**. En este modo, los registros de facturación seran enviados al entorno de pruebas de la AEAT sin generar ningún tipo de obligación fiscal ante hacienda.

El módulo, testeado en la versión 20.0 de Dolibarr, se distribuye sin dependencias. Sin embargo, se requiere de [composer](https://getcomposer.org/) para instalar los paquetes necesarios para preparar el entorno de desarrollo. En concreto, se hace uso de [PHP CodeSniffers](https://github.com/PHPCSStandards/PHP_CodeSniffer/) como formateador y validador de código.

> FYI: En Còdec desarrollamos con Docker haciendo uso de las imagenes [Dolibarr](https://hub.docker.com/r/dolibarr/dolibarr) y [MariaDB](https://hub.docker.com/_/mariadb).

## Hoja de ruta

1. Soporte para el módulo _Multi Company_. Por ahora, **Auto Veri\*Factu no permite su uso en entornos multi compañía**.
2. Las facturas se vuelven inmutables una vez validadas, pero en ocasiones, la API de Veri\*Factu acepta facturas con errores. En estos casos, se debería poder subsanar (modificar la factura).
3. ¿Las donaciones se han de incluir en el sistema?
