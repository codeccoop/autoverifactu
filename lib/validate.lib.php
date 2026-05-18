<?php
/*
* Funciones auxiliares para validación de datos de factura.
*/

/**
* Valida si es alta o anulacion.
*
* @param  string $type facture.
*
* @return boolean 1 correct or 0 incorrect
*/
function autoverifactuValidateTypeInvoice($type){
    return ($type === 'alta' || $type=== 'anulacion');
}
/**
* Valida si es una fecha 
*
* @param  string $date 
* @param  boolean en caso de true tiene que ser una fecha en caso de false puede
* ser una fecha o estar "" o null
* @return boolean 1 correct or 0 incorrect
*/
function autoverifactuValidateDate($date,$require){
    if(!$require && $date===""){
        return 1;
    }
    $d = DateTime::createFromFormat('d-m-y', $date);
    return $d && $d->format('d-m-Y') === $date;
}
/**
* Valida el tipo de especificación de factura
*
* @param  string $type especificación de factura (L2).
*
* @return boolean 1 correct or 0 incorrect
*/
function autoverifactuValidateVerifactuInvoice($type){
    return (
        $type ==="F1" ||
        $type ==="F2" || 
        $type ==="F3" || 
        $type ==="R1" || 
        $type ==="R2" || 
        $type ==="R3" || 
        $type ==="R4" || 
        $type ==="R5" );
}

/**
* Valida el tipo de especificación de factura rectificativa
*
* @param  string $type especificación de factura (L2).
* @param  boolean obliagtorio.
*
* @return boolean 1 correct or 0 incorrect
*/
function  autoverifactuValidateVerifactuInvoiceRectificative ($type,$requerido){
    if(!$requerido && $type ===''){
        return 1;
    }
    return (
        $type ==="R1" || 
        $type ==="R2" || 
        $type ==="R3" || 
        $type ==="R4" || 
        $type ==="R5" );
}
/**
* Valida de tipo alfanumerico.
*
* @param  string cadena facture.
* @param  int  numero de caracteres
*
* @return boolean 1 correct or 0 incorrect
*/
function autoverifactuValidateAlphaNumber($string, $length) {
    $actualLength = mb_strlen($string, 'UTF-8');
    if ($actualLength > (int)$length || $actualLength === 0) {
        return 0;
    }
    $pattern = "/^[a-zA-Z0-9ñÑáéíóúÁÉÍÓÚüÜ ]+$/u";
    if (!preg_match($pattern, $string)) {
        return 0;
    }
    return 1;
}

/**
* Valida de tipo alfanumerico + guion para la ref.
*
* @param  string cadena facture.
* @param  int  numero de caracteres
*
* @return boolean 1 correct or 0 incorrect
*/
function autoverifactuValidateAlphaNumberScript($string, $length) {
    $actualLength = mb_strlen($string, 'UTF-8');
    if ($actualLength > (int)$length || $actualLength === 0) {
        return 0;
    }
    $pattern = "/^[a-zA-Z0-9ñÑáéíóúÁÉÍÓÚüÜ\- ]+$/u";
    if (!preg_match($pattern, $string)) {
        return 0;
    }
    return 1;
}

/**
* Valida de tipo number decimal (numberCount,numberDecimal).
*@param  float  number validate
* @param  int  numberCount  epresenta el número total de dígitos
*@param  int  numberDecimal Representa cuántos de esos numberCount dígitos están reservados para la parte decimal
* @return boolean 1 correct or 0 incorrect
*/
function autoverifactuValidateNumber($number,$numberCount,$numberDecimal){
    if (!is_numeric($number)) {
        return 0;
    }
    $absoluteNumber = ltrim($number, '-');
    $parts = explode('.', $absoluteNumber);
    $integers = $parts[0];
    $decimals = isset($parts[1]) ? $parts[1] : '';
    $maxIntegersAllowed = $numberCount - $numberDecimal;
    $actualIntegersCount = strlen($integers);
    $actualDecimalsCount = strlen($decimals);
    if ($actualIntegersCount > $maxIntegersAllowed) {
        return 0;
    }
    if ($actualDecimalsCount > $numberDecimal) {
        return 0;
    }
    if (($actualIntegersCount + $actualDecimalsCount) > $numberCount) {
        return 0;
    }
    return 1;
}

/** 
 * Verifica que el tipo de impuesto tiene un valor correcto
 * @param string tipo de impuesto
 * @param  boolean obligatorio.
 * @return boolean 1 correct or 0 incorrect
*/
function autoverifactuValidateTaxType($taxType){
    return ($taxType==='01' ||
            $taxType==='02' ||
            $taxType==='03' ||
            $taxType==='05' 
            ); 
}

/** 
 * Verifica que el tipo de regimen tiene un valor correcto
 * @param string tipo de regimen 
 * @return boolean 1 correct or 0 incorrect
*/
function autoverifactuValidateRegimeTypeIva($regimeType){
   return ($regimeType==='01' ||
            $regimeType==='02' ||
            $regimeType==='03' ||
            $regimeType==='04' ||
            $regimeType==='05' ||
            $regimeType==='06' ||
            $regimeType==='07' ||
            $regimeType==='08' ||
            $regimeType==='09' ||
            $regimeType==='10' ||
            $regimeType==='11' ||
            $regimeType==='14' ||
            $regimeType==='15' ||
            $regimeType==='17' ||
            $regimeType==='18' ||
            $regimeType==='19' ||
            $regimeType==='20' 
            ); 
}

/** 
 * Verifica que el tipo de regimen tiene un valor correcto
 * @param string tipo de regimen 
 * @return boolean 1 correct or 0 incorrect
*/
function autoverifactuValidateRegimeTypeOther($regimeType){
    return ($regimeType==='01' ||
            $regimeType==='02' ||
            $regimeType==='03' ||
            $regimeType==='04' ||
            $regimeType==='05' ||
            $regimeType==='06' ||
            $regimeType==='07' ||
            $regimeType==='08' ||
            $regimeType==='09' ||
            $regimeType==='10' ||
            $regimeType==='11' ||
            $regimeType==='14' ||
            $regimeType==='15' ||
            $regimeType==='17' ||
            $regimeType==='18' ||
            $regimeType==='19' 
            ); 
}

/** 
 * Verifica que el tipo de operacion tiene un valor correcto
 * @param string tipo de operacion 
 * @return boolean 1 correct or 0 incorrect
*/
function autoverifactuValidateOperationType($operationType){
     return ($operationType==='S1' ||
            $operationType==='S2' ||
            $operationType==='N1' ||
            $operationType==='N2' ||
            $operationType==='validate'
            );
}

/** 
 * Verifica que el codigo de error tiene un valor correcto
 * @param string tipo codigo de error 
 * @return boolean 1 correct or 0 incorrect
*/
function autoverifactuValidateExemptionCode($errorCode){
     return ($errorCode==='E1' ||
            $errorCode==='E2' ||
            $errorCode==='E3' ||
            $errorCode==='E4' ||
            $errorCode==='E5' ||
            $errorCode==='E6'|| 
            $errorCode==='0'
             );
}

/**
* Valida si el nif es correcto y corresponde con el nombre 
* atraves de la API de la Agencia Tributaria.
*
* @param  string $nif nif 
* @param  string $name nombre 
*
* @return boolean 1 correct or 0 incorrect
*/
function autoverifactuValidateNifName($nif,$name)
{
   
    $envelope = autoverifactuSoapEnvelopeNIF(
        $nif,
        $name
    );
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://www1.agenciatributaria.gob.es/wlpl/BURT-JDIT/ws/VNifV2SOAP');
                                   
                                 
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_FAILONERROR, 1);

    curl_setopt($ch, CURLOPT_SSLCERTTYPE, 'P12');
    $certPath = DOL_DATA_ROOT . '/' . getDolGlobalString('AUTOVERIFACTU_CERT');
    curl_setopt($ch, CURLOPT_SSLCERT, $certPath);
    $certPass = getDolGlobalString('AUTOVERIFACTU_PASSWORD');
    curl_setopt($ch, CURLOPT_SSLCERTPASSWD, $certPass);

    curl_setopt(
        $ch,
        CURLOPT_HTTPHEADER,
        array(
            'Content-Type: text/xml',
            'User-Agent: Mozilla/5.0 (compatible; Módulo Auto-Veri*Factu de Dolibarr/0.0.1',
        ),
    );

    // --- INSERTA ESTO PARA VER LA RESPUESTA ---
    curl_setopt($ch, CURLOPT_VERBOSE, true); // Activa modo detallado
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Asegura que devuelve la respuesta


    curl_setopt($ch, CURLOPT_POSTFIELDS, $envelope);

    $res = curl_exec($ch);

    error_log('# RESPUESTA VALIDACIÓN NIF Y NOMBRE');
    curl_close($ch); 
   // var_dump(htmlspecialchars($res));
    
    
    try {

        $xml = new SimpleXMLElement($res);
        $xml->registerXPathNamespace('valida', 'http://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/burt/jdit/ws/VNifV2Sal.xsd');
        $resultado = $xml->xpath('//valida:Resultado');
        
      
        if($resultado[0]=='IDENTIFICADO'){
            return 1;
        }else if($resultado[0]=='NO PROCESADO'){
            return 0;
            // return "Se excede del número de contribuyentes a identificar";
        }else if((string) $resultado[0]=='IDENTIFICADO-REVOCADO'){
            return 1;
            //return 0;
            //Si el contribuyente se identifica con el NIF aportado, y está en estado baja
        }else if((string)  $resultado[0][0]=='IDENTIFICADO-BAJA'){
            return 0;
            //Si el contribuyente se identifica con el NIF aportado y está en estado baja por revocación del NIF.
        }else{
            return 0;
            //return "El nombre del usuario no concuerda con el DNI";
        }
    
    } catch (Exception $e) {
        return "Error al procesar el XML: " . $e->getMessage();
    }
}

/**
* funcion "privada" que genera el xml de la validacion nif
*
* @param  string $nif nif 
* @param  string $name nombre 
*
* @return string xml de la validacion nif
*/
function autoverifactuSoapEnvelopeNIF($nif,$name){
   
   $xml = new DOMDocument('1.0', 'UTF-8');
    
    // 1. Creamos el Envelope
    $envelope = $xml->createElement('soapenv:Envelope');
    
    // 2. CORRECCIÓN: Definir cada namespace por separado
    $envelope->setAttribute('xmlns:soapenv', 'http://schemas.xmlsoap.org/soap/envelope/');
    $envelope->setAttribute('xmlns:vnif', 'http://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/burt/jdit/ws/VNifV2Ent.xsd');
    
    // 3. Estructura normal
    $headerEl = $xml->createElement('soapenv:Header');
    $envelope->appendChild($headerEl);
    
    $body = $xml->createElement('soapenv:Body');
    $root = $xml->createElement('vnif:VNifV1Ent');
    $regContriEl = $xml->createElement('vnif:Contribuyente');
    // Asegúrate de que $nif esté en mayúsculas
    $regNifEl = $xml->createElement('vnif:Nif', strtoupper($nif));
    $regContriEl->appendChild($regNifEl);
    
    $regNameEl = $xml->createElement('vnif:Nombre', $name);
    $regContriEl->appendChild($regNameEl);
    
    $root->appendChild($regContriEl);
    $body->appendChild($root);
    $envelope->appendChild($body);
    $xml->appendChild($envelope);
    
    // Usamos saveXML() sobre todo el documento para que incluya la cabecera XML si es necesario
    return $xml->saveXML();
}