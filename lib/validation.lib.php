<?php
/* Copyright (C) 2025       Lucas García            <lucas@codeccoop.org>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    htdocs/custom/autoverifactu/lib/validation.lib.php
 * \ingroup autoverifactu
 * \brief   Library files with functions to interface with the Veri*Factu API
 */

/**
 * Prepare admin pages header
 *
 * @return array<array{string,string,string}>
 */

require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT . '/blockedlog/class/blockedlog.class.php';
require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';

require_once __DIR__ . '/verifactu.lib.php';





/**
 * Compare the invoice record hash with the hash of the record from the immutable log.
 *
 * @param  Facture $invoice Target invoice.
 *
 * @retur int               <0 if KO, 0 id not found, >0 on OK.
 */
function autoverifactuIntegrityCheck($invoice)
{


   

    $blockedlog = autoverifactuFetchBlockedLog($invoice);


    if (!$blockedlog) {
        return 0;
    }

    $signatrueCheck = $blockedlog->checkSignature();
    if (!$signatrueCheck) {
        return -1;
    }



    $record = autoverifactuInvoiceToRecord($invoice);

    
    $immutable = autoverifactuRecordFromLog($blockedlog);


    if (!$record || !$immutable) {
        return -1;
    }

    $error = $record->hash !== $immutable->hash;
    if ($error) {
        return -1;
    }

    return 1;
}

/**
 * Get the blocked log of the invoice at its validation.
 *
 * @param Facture $invoice Target invoice instance.
 *
 * @return BlockedLog|null
 */
function autoverifactuFetchBlockedLog($invoice)
{
    global $db;

    $sql = 'SELECT rowid FROM ' . $db->prefix() . 'blockedlog';
    $sql .= ' WHERE element = \'facture\'';
    // $sql .= ' AND entity = ' . $confg->entity;
    $sql .= ' AND action = \'BILL_VALIDATE\'';
    $sql .= ' AND fk_object = ' . $invoice->id;

    $resql = $db->query($sql);
    if ($resql && $db->num_rows($resql)) {
        $obj = $db->fetch_object($resql);
        $blockedlog = new BlockedLog($db);
        $blockedlog->fetch($obj->rowid);
        return $blockedlog;
    }
}

/**
 * Check and regenerate invoice XML record files.
 *
 * @param Facture $invoice Target invoice.
 * @param string  $type    Record type, could be 'alta' or 'anulacion'.
 *
 * @return int             <0 if KO, 0 if noop, 1 if OK.
 */
function autoverifactuCheckInvoiceImmutableXML($invoice, $type = 'alta')
{
    global $mysoc;

    $result = 0;

    if (!in_array($type, array('alta', 'anulacion'), true)) {
        return $result;
    }

    list($file, $hidden) = autoverifactuInvoiceImmutableXMLPath($invoice);

    if (!is_file($hidden)) {
        $blockedlog = autoverifactuFetchBlockedLog($invoice);

        if (!$blockedlog) {
            dol_syslog('Immutable log not found for invoice #' . $invoice->id, LOG_ERR);
            return -1;
        }

        $record = autoverifactuRecordFromLog($blockedlog);

        $xml = autoverifactuSoapEnvelope(
            $record,
            array(
                'name' => $mysoc->nom,
                'idprof1' => $mysoc->idprof1,
            ),
        );

        $bytes = file_put_contents($hidden, $xml);

        $result = intval($bytes > 0);

        if (!$result) {
            dol_syslog('Empty XML regeneration for invoice #' . $invoice->id, LOG_ERR);
            return -1;
        }
    }

    if (!is_file($file)) {
        $bytes = file_put_contents($file, file_get_contents($hidden));

        $result = $result + intval($bytes > 0);

        if (!$result) {
            dol_syslog('Empty XML regeneration for for invoice #' . $invoice->id, LOG_ERR);
            return -1;
        }
    }

    return $result;
}

/**
 * Builds the path to the immutable XML files of an invoice.
 *
 * @param Facture $invoice Target invice instance.
 * @param string  $type    Record type. Can be 'alta' or 'anulacion'.
 *
 * @return [string, string] Tuple with the filepath in its first possition and the
 *                          path to its hidden backup file.
 */
function autoverifactuInvoiceImmutableXMLPath($invoice, $type = 'alta')
{
    global $conf;

    $invoiceref = dol_sanitizeFileName($invoice->ref);
    $dir = $conf->facture->multidir_output[$invoice->entity ?? $conf->entity] . '/' . $invoiceref;

    $file = $dir . '/' . $invoiceref . '-' . $type . '.xml';
    $hidden = $dir . '/.verifactu-' . $type . '.xml';

    return [$file, $hidden];
}

/**
 * Gets the source invoice from the fk_facture_source value.
 *
 * @param Facture $invoice Invoice object.
 * @param int $tms Validation process start timestamp.
 *
 * @return Facture|null
 */
function autoverifactuGetPreviousValidInvoice($invoice, $tms = null)
{
    global $db;

    $timestamp = $invoice->array_options['options_verifactu_tms'] ?: $tms ?: time();

    $sql = 'SELECT f.rowid FROM ' . $db->prefix() . 'facture f';
    $sql .= ' LEFT JOIN ' . $db->prefix() . 'facture_extrafields fx';
    $sql .= ' ON f.rowid = fx.fk_object';
    $sql .= ' WHERE f.fk_statut > 0 AND f.type <= 3';
    $sql .= ' AND fx.verifactu_hash IS NOT null';
    $sql .= ' AND fx.verifactu_tms < ' . $timestamp;
    $sql .= ' AND fx.fk_object != ' . $invoice->id;
    $sql .= ' ORDER BY fx.verifactu_tms DESC';

    $result = $db->query($sql);

    if ($result && $db->num_rows($result)) {
        $obj = $db->fetch_object($result);
        $invoice = new Facture($db);
        $invoice->fetch($obj->rowid);
        return $invoice;
    }
}

/**
 * Gets the source invoice from the fk_facture_source value.
 *
 * @param Facture Invoice object.
 *
 * @return Facture|null
 */
function autoverifactuGetSourceInvoice($invoice)
{
    $prev_id = $invoice->fk_facture_source;
    if (!$prev_id) {
        return;
    }

    global $db;
    $invoice = new Facture($db);
    $found = $invoice->fetch($prev_id);

    if (!$found) {
        return;
    }

    return $invoice;
}

/**
 * Recreate the original Veri*Factu invoice record from a blockedlog entry.
 *
 * @param  BlockedLog $blocedlog   BlockedLog instance with the immutable data of the invoice validation.
 * @param  string     $recorddType Record type. Can be 'alta' or 'anulacion'.
 *
 * @return stdClass                Recreated invoice record.
 */
function autoverifactuRecordFromLog($blockedlog, $recordType = 'alta')
{
    global $db;
        
    $objectdata = $blockedlog->object_data;

    $blocked = new Facture($db);
    $blocked->fetch($blockedlog->fk_object);



    $blocked->status = 1;
    $blocked->type = $objectdata->type;
    $blocked->ref = $objectdata->ref;

    $lines = array();
    
    $extrafields = new ExtraFields($db);
    $extralabels = $extrafields->fetch_name_optionals_label('facturedet');
 



    foreach ($objectdata->invoiceline as $indice => $linedata) {
        $line = new FactureLigne($db);
        $line->tva_tx = $linedata->tva_tx;
        $line->total_ht = $linedata->total_ht;
        $line->total_tva = $linedata->total_tva;
        $line->localtax1_tx = $linedata->localtax1_tx;
        $line->total_localtax1 = $linedata->total_localtax1;
        $line->array_options["options_verifactu_Tax_Type"] = "validate";
        $lines[] = $line;
    }

    $blocked->lines = $lines;


    if (isset($objectdata->thirdparty) && $objectdata->thirdparty) {
        $blocked->thirdparty = new Societe($db);
        $blocked->thirdparty->nom = $objectdata->thirdparty->name;
        $blocked->thirdparty->idprof1 = $objectdata->thirdparty->idprof1u ?? null;
        $blocked->thirdparty->country_code = $objectdata->thirdparty->country_code ?? null;
        $blocked->thirdparty->code_client = $objectdata->thirdparty->code_client;
    } else {
        $blocked->thirdparty = null;
    }

    return autoverifactuInvoiceToRecord($blocked, $recordType);
}

/**
* Checks if the PKCS12 certificate is present and try to decrypt it with the password.
*
* @param string $certpath Path to the cert file. Should end with .(p12|pfx).
* @param string $password Certificate password.
*
* @return int 1 if OK, 0 if KO.
*/
function autoverifactuPkcs12Check($certpath, $password)
{
    if (!is_file($certpath)) {
        return 0;
    }

    $ext = strtolower(pathinfo($certpath)['extension'] ?? '');
    if (!in_array($ext, array('p12', 'pfx'), true)) {
        return 0;
    }

    $password = getDolGlobalString('AUTOVERIFACTU_PASSWORD');
    if (!$password) {
        return 0;
    }

    $content = file_get_contents($certpath);
    return (int) openssl_pkcs12_read($content, $_, $password);
}

/**
* Performs a verifactu system requirements check.
*
* @return int 1 if OK, 0 if KO.
*/
function autoverifactuSystemCheck()
{
    global $conf, $mysoc;

    if (!function_exists('isValidTinForES')) {
        require_once DOL_DOCUMENT_ROOT . '/core/lib/profid.lib.php';
    }

    $certpath = DOL_DATA_ROOT . '/' . (getDolGlobalString('AUTOVERIFACTU_CERT') ?: 'nofile');
    if (!is_file($certpath)) {
        return 0;
    }

    return intval(
        $mysoc->nom && $mysoc->idprof1
        && isValidTinForES($mysoc->idprof1)
        && !empty($conf->modules['blockedlog'])
        && getDolGlobalInt('FAC_FORCE_DATE_VALIDATION')
        && getDolGlobalString('AUTOVERIFACTU_RESPONSABILITY')
        && autoverifactuPkcs12Check($certpath, getDolGlobalString('AUTOVERIFACTU_PASSWORD'))
    );
}

/**
 * Check if autoverifactu is enabled.
 *
 * @return bool
 */
function autoverifactuEnabled()
{
    $check = autoverifactuSystemCheck();
    $enabled = getDolGlobalInt('AUTOVERIFACTU_ENABLED');

    if (!$check && $enabled) {
        autoverifactu_set_const('AUTOVERIFACTU_ENABLED', false);
    }

    return $check && $enabled;
}

/**
 * Performs record data validation.
 *
 * @param  stdClass $record Target record.
 *
 * @return int              0 if validatio fail, 1 if succeed
 */
function autoverifactuValidateRecord($record)
{

    //validacion de todos los datos de la factura
    $isCorrect=autoverifactuValidateValuesRecord($record);

    if(!$isCorrect){
        return 0;
    }
    //validamos que el total de la factura coincida con el total calculado
    if($record->factureTotalAmount!==$record->factureTtc){
        return 0;
    }

    if (!isset($record->breakdown, $record->totalTaxAmount, $record->totalAmount)) {
        return 0;
    }

    if (
        in_array($record->invoiceType, array('F2', 'R5'), true)
        && count($record->recipients)
    ) {
        // If is simplified, it should not have recipients.
        return 0;
    }

    $isCorrective = preg_match('/R[0-5]/', $record->invoiceType);
    if ($isCorrective && !$record->correctiveType) {
        return 0;
    } elseif (!$isCorrective && $record->correctiveType) {
        return 0;
    } elseif (!$isCorrective && count($record->correctedInvoices)) {
        return 0;
    }

    if ($record->correctiveType === 'S') {
        // If its corrective by diferrence it should have base and tax amounts.
        if (!$record->correctedBaseAmount || !$record->correctedTaxAmount) {
            return 0;
        }
    } else {
        // If is corrective by substitution, it shouldn't.
        if ($record->correctedBaseAmount || $record->correctedTaxAmount) {
            return 0;
        }
    }

    if ($record->invoiceType === 'F3' && count($record->replacedInvoices)) {
        return 0;
    } elseif ($record->invoiceType !== 'F3' && count($record->replacedInvoices)) {
        return 0;
    }

    $expectedTax = 0;
    $expectedBase = 0;
    foreach ($record->breakdown as $details) {
        if (!isset($details->taxAmount, $details->baseAmount, $details->taxRate)) {
            return 0;
        }

        $validTaxAmount = false;
        $expectedLineTax = $details->baseAmount * $details->taxRate / 100;
        for ($t = -0.02; $t <= 0.02; $t += 0.01) {
            $taxAmount = number_format($expectedLineTax + $t, 2, '.', '');
            if ($details->taxAmount === $taxAmount) {
                $validTaxAmount = true;
                break;
            }
        }

        if (!$validTaxAmount) {
            return 0;
        }

        $expectedTax += $details->taxAmount;
        $expectedBase += $details->baseAmount;
    }

    $expectedTax = number_format($expectedTax, 2, '.', '');
    $expectedBase = number_format($expectedBase, 2, '.', '');
    $expectedTotal = number_format($expectedTax + $expectedBase, 2, '.', '');

    $isTotalValid = false;
    for ($t = -0.02; $t <= 0.02; $t += 0.01) {
        $total = number_format($expectedTotal + $t, 2, '.', '');
        if ($record->totalAmount === $total) {
            $isTotalValid = true;
            break;
        }
    }

    return (int) $isTotalValid;
}

/**
 * Checks if the invoice has already been recorded as a Veri*Factu record.
 *
 * @param Facture $invoice Target invoice instance.
 *
 * @return bool
 */
function autoverifactuIsInvoiceRecorded($invoice)
{
    $invoice->fetch_optionals();
    return !!($invoice->array_options['options_verifactu_hash'] ?? false);
}

/**
 * Checks if an invoices is a POS invoice, or a derived invoice from a POS invoice.
 *
 * @param Facture $invoice Target invoice.
 *
 * @return bool
 */
function autoverifactuIsPosInvoice($invoice)
{
    $is_derived = in_array(
        $invoice->type,
        array(
            Facture::TYPE_REPLACEMENT,
            Facture::TYPE_CREDIT_NOTE
        )
    );

    if ($is_derived && $invoice->fk_facture_source) {
        global $db;
        $source = new Facture($db);
        $source->fetch($invoice->fk_facture_source);

        return $source->module_source === 'takepos';
    }

    return $invoice->module_source === 'takepos';
}

/* Validates the values ​​of an invoice record
 *
 * @param  stdClass $record Target record.
 *
 * @return int              0 if validatio fail, 1 if succeed
 */
function autoverifactuValidateValuesRecord($record){

    $isValidType =autoverifactuValidateTypeInvoice($record->type);
    if(!$isValidType){
        return 0;
    }
    $isValidDateOperation=autoverifactuValidateDate($record->dateOperation,false);
    
    if(!$isValidDateOperation){
        return 0;
    }


    $isValidVerifactuInvoice=autoverifactuValidateVerifactuInvoice($record->invoiceType);
      
    if(!$isValidVerifactuInvoice){
        return 0;
    }

    $isValidDescription=autoverifactuValidateAlphaNumber($record->description,500);
    if(!$isValidDescription){
        return 0;
    }
  

    $isValidRef=autoverifactuValidateAlphaNumberScript($record->invoiceId->invoiceNumber,60);
    if(!$isValidRef){
        return 0;
    }

    $isValidTotalAmount=autoverifactuValidateNumber($record->factureTtc,12,2);
    if(!$isValidTotalAmount){
        return 0;
    }

    $isValidTtc=autoverifactuValidateNumber($record->factureTtc,12,2);
    if(!$isValidTtc){
        return 0;
    }



  
    if($record->correctiveType){
        $isValidCorrectiveType=autoverifactuValidateVerifactuInvoiceRectificative($record->correctiveType,false);
        if(!$isValidCorrectiveType){
            return 0;
        } 
    }

    for ($i=0; $i < count($record->correctedInvoices); $i++) { 
        # añadir logica
    }

    if($record->correctedBaseAmount){
        $isValidCorrectedBaseAmount=autoverifactuValidateNumber($record->correctedBaseAmount,12,2);
        if(!$isValidCorrectedBaseAmount){
            return 0;
        } 
    }
    if($record->correctedTaxAmount){
        $isValidCorrectedTaxAmount=autoverifactuValidateNumber($record->correctedTaxAmount,12,2);
        if(!$isValidCorrectedTaxAmount){
            return 0;
        } 
    }

    for ($i=0; $i < count($record->replacedInvoices); $i++) { 
        # añadir logica
    }

    for ($i=0; $i < count($record->breakdown); $i++) {



        $isValidTotalTaxType=autoverifactuValidateTaxType($record->breakdown[0]->taxType);
        if(!$isValidTotalTaxType){
            return 0;
        } 
        if($record->breakdown[0]->taxType === '01'){
            $isValidRegimeType=autoverifactuValidateRegimeTypeIva($record->breakdown[0]->regimeType);
            if(!$isValidRegimeType){
                return 0;
            } 
        }else{
            $isValidRegimeType=autoverifactuValidateRegimeTypeOther($record->breakdown[0]->regimeType);
            if(!$isValidRegimeType){
                return 0;
            } 
        }

        $isValidOperationType=autoverifactuValidateOperationType($record->breakdown[0]->operationType);
        if(!$isValidOperationType){
            return 0;
        } 

        $isValidTotalTaxRate=autoverifactuValidateNumber($record->breakdown[0]->taxRate,4,2);
        if(!$isValidTotalTaxRate){
            return 0;
        } 

        $isValidBaseAmount=autoverifactuValidateNumber($record->breakdown[0]->baseAmount,12,2);
        if(!$isValidBaseAmount){
            return 0;
        } 

        $isValidTaxAmount=autoverifactuValidateNumber($record->breakdown[0]->taxAmount,12,2);
        if(!$isValidTaxAmount){
            return 0;
        } 

        if(isset($record->breakdown[0]->exemptionCode)){
            $isValidTexemptionCode=autoverifactuValidateExemptionCode($record->breakdown[0]->exemptionCode,12,2);
        }
        
        return 1;

        if(!$isValidTexemptionCode){
            return 0;
        } 
  
    }


    $isValidTotalTaxAmount=autoverifactuValidateNumber($record->totalTaxAmount,12,2);
    if(!$isValidTotalTaxAmount){
        return 0;
    }

    $isValidTotalAmount=autoverifactuValidateNumber($record->totalAmount,12,2);
    if(!$isValidTotalAmount){
        return 0;
    } 
  

    return 1;
}


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
            $errorCode==='0' ||//en caso de que no haya exención
            $errorCode==null//en caso de que no haya exención
            );
}



