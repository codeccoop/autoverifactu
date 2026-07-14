## [0.0.12]

### Novedades y Mejoras

* **Rectificación de facturas con error (alta y anulación):** Implementación de un control de estado interno para gestionar estas facturas. Asimismo, se han desarrollado acciones específicas accesibles mediante botones, tales como el reenvío tras un error y la visualización de mensajes para la corrección de fallos.
* **Control de tiempos de espera en la API:** Gestión de intervalos entre peticiones de la API. Se ha implementado la generación de una lista de altas pendientes (sin errores) para su envío masivo automático a través del crontab de Dolibarr una vez transcurrido el tiempo de espera.
* **Unión de líneas por tipo impositivo:** Agrupación de líneas con los mismos tipos impositivos. Esto permite tener un número ilimitado de líneas de detalle en la factura; el único límite (pendiente de validar) es un máximo de 12 tipos impositivos distintos.
* **Plantilla propia de Verifactu:** Creación de una plantilla personalizada que optimiza la ubicación del código QR e incluye los textos legales obligatorios cuando la situación lo requiera.
* **Identificación de terceros:** Integración de las diferentes casuísticas para la correcta identificación de clientes. (PASAPORTE,DOCUMENTO OFICIAL DE IDENTIFICACIÓN EXPEDIDO POR EL PAÍS, OTRO DOCUMENTO PROBATORIO o CERTIFICADO DE RESIDENCIA )