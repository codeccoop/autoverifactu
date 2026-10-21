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
 * \file    htdocs/custom/autoverifactu/lib/verifactu.lib.php
 * \ingroup autoverifactu
 * \brief   Library files with functions to interface with the Veri*Factu API
 */

// Libraries
require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';

require_once __DIR__ . '/validation.lib.php';


/* Veri*Factu API URLs */
define('VERIFACTU_BASE_URL', 'https://www1.agenciatributaria.gob.es'); // Production API host
define('VERIFACTU_TEST_BASE_URL', 'https://prewww1.aeat.es'); // >Test API host
define('VERIFACTU_COLLATION_BASE_URL', 'https://www2.agenciatributaria.gob.es'); // Invoice collation host
define('VERIFACTU_TEST_COLLATION_BASE_URL', 'https://prewww2.aeat.es'); // Invoice collation test host

/* XML namespaces */
define('AUTOVERIFACTU_SOAPENV_NS', 'http://schemas.xmlsoap.org/soap/envelope/');
define(
	'AUTOVERIFACTU_SUM_NS',
	'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroLR.xsd',
);
define(
	'AUTOVERIFACTU_SUM1_NS',
	'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroInformacion.xsd',
);
define('AUTOVERIFACTU_XD_NS', 'http://www.w3.org/2000/09/xmldsig');

/**
 * Verifactu invoice record registration.
 *
 * @param Facture $invoice Target invoice. Invoice should not be validated, or
 *                         action should be BILL_CANCEL.
 * @param string  $action  Current action.
 *
 * @return int              Return <0 if KO, 0 if skipped, >0 if OK.
*/
function autoverifactuRegisterInvoice($invoice, $action)
{
	global $db, $conf, $hookmanager;

	if ($invoice->type > Facture::TYPE_DEPOSIT) {
		// Skip non recordable invoice types.
		return 0;
	}

	// TODO: Maybe include DON_VALIDATE and CASHCONTROL_VALIDATE.
	$validActions = array('BILL_VALIDATE');
	if ($invoice->status === Facture::STATUS_DRAFT && !in_array($action, $validActions, true)) {
		return 0;
	}

	$validActions = array('BILL_CANCEL', 'VERIFACTU_AMEND');
	if ($invoice->status === Facture::STATUS_VALIDATED && !in_array($action, $validActions, true)) {
		return 0;
	}

	if (!is_array($invoice->errors ?? null)) {
		$invoice->errors = array();
	}

	if (empty($conf->facture->multidir_output[$conf->entity])) {
		$invoice->errors[] = 'InvoiceDirError';
		return -1;
	}

	$invoice->fetch_thirdparty();
	$thirdparty = $invoice->thirdparty;
	$validId = $thirdparty->id_prof_check(1, $thirdparty);
	$isSimple = autoverifactuIsSimplifiedInvoice($invoice);

	if ($validId <= 0 && !$thirdparty->tva_intra && !$isSimple) {
		$invoice->errors[] = 'ThirdpartyIdError';
		return -1;
	}

	$invoice->fetch_lines();
	if (!count($invoice->lines)) {
		$invoice->errors[] = 'EmptyInvoiceError';
		return -1;
	}

	$invoiceref = dol_sanitizeFileName($invoice->ref);
	$dir = $conf->facture->multidir_output[$invoice->entity ?? $conf->entity] . '/' . $invoiceref;

	if ($action === 'BILL_VALIDATE') {
		$file = $dir . '/' . $invoiceref . '-alta.xml';
		$hidden = $dir . '/.verifactu-alta.xml';
	} elseif ($action === 'verifactuResend') {
		$file = $dir . '/' . $invoiceref . '-subsanacion.xml';
		$hidden = $dir . '/.verifactu-subsanacion.xml';
	} else {
		$file = $dir . '/' . $invoiceref . '-anulacion.xml';
		$hidden = $dir . '/.verifactu-anulacion.xml';
	}

	if (!file_exists($dir)) {
		if (dol_mkdir($dir) < 0) {
			$invoice->errors[] = 'MakeInvoiceDirError';
			return -1;
		}
	}

	if (!autoverifactuSystemCheck()) {
		dol_syslog('System does not complains with Auto-Veri*Factu requirements. Skip invoice registration.');
		return 0;
	}

	$enabled = getDolGlobalInt('AUTOVERIFACTU_ENABLED');
	if (!$enabled) {
		dol_syslog('Auto-Veri*Factu is not enabled. Skip invoice registration.');
		return 0;
	}

	$recordType = $action === 'BILL_VALIDATE' ||  $action === 'VERIFACTU_AMEND' ? 'alta' : 'anulacion';

	$isRecorded = autoverifactuIsInvoiceRecorded($invoice);
	$isAmendable = autoverifactuIsInvoiceAmendable($invoice);

	if ($recordType === 'alta' && $isRecorded && !$isAmendable) {
		dol_syslog(
			'Skip verifactu invoice registration because invoice #'
			. $invoice->id .
			'is already registered',
		);

		return 0;
	}

	if (!is_object($hookmanager)) {
		include_once DOL_DOCUMENT_ROOT . '/core/class/hookmanager.class.php';
		$hookmanager = new HookManager($db);
	}

	$hookmanager->initHooks(array('autoverifactu'));

	$parameters = array(
		'file' => $file,
		'invoice' => $invoice,
		'action' => $action,
		'recordType' => $recordType,
	);

	$reshook = $hookmanager->executeHooks(
		'beforeAutoverifactuRecord',
		$parameters,
		$invoice,
	);

	if ($reshook < 0) {
		dol_syslog('Skip verfiactu record registry for invoice #' . $invoice->id);
		return $reshook;
	}

	if ($reshook) {
		dol_syslog(
			'Verfiactu record registry interception on "beforeAutoverifactuRecord" for invoice #'
			. $invoice->id
		);

		return $reshook;
	}

	try {
		$record = autoverifactuSendInvoice($invoice, $recordType, $xml);

		// Skip document generation if send does not succeed.
		if (!$record) {
			return 0;
		}

		$result = file_put_contents($file, $xml);
		$result = $result && file_put_contents($hidden, $xml);

		if (!$result) {
			throw new Exception('Unable to store XML invoice record', 500);
		} else {
			// Store invoice verifactu extrafields after record registration
			$invoice->array_options['options_verifactu_hash'] = $record->hash;
			$invoice->array_options['options_verifactu_tms'] = $record->hashedAt->getTimestamp();

			if ($error = $record->error ?? null) {
				$invoice->array_options['options_verifactu_error'] = $error->message;
				$invoice->array_options['options_verifactu_error_code'] = $error->code;
			} else {
				$invoice->array_options['options_verifactu_error'] = '';
				$invoice->array_options['options_verifactu_error_code'] = '';
			}

			$result = $invoice->insertExtraFields();

			if ($result <= 0) {
				throw new Exception('Unable to update invoice extra fields', 500);
			}

			$parameters['record'] = $record;
			$parameters['xml'] = $xml;

			$reshook = $hookmanager->executeHooks(
				'afterAutoverifactuRecord',
				$parameters,
				$invoice,
			);
		}
	} catch (Error | Exception $e) {
		if (isset($xml) && $xml) {
			file_put_contents($file, $xml);
		}

		if (isset($record) && $record) {
			autoverifactuSendInvoice($invoice, 'anulacion');
		}

		dol_syslog('Error on verifactu request ' . print_r($e, true), LOG_ERR);
		return -1;
	}

	return 1;
}

/**
 * Send an invoice as a record to the Veri*Factu SOAP endpoints.
 *
 * @param Facture $invoice    Target invoice. Invoice should not be published before
 * @param string  $recordType Triggered action. Could be 'alta' or 'anulacion'.
 * @param string  $xml        Response body as an XML string.
 *
 * @return stdClass|null      Registered record, null if skipped.
 *
 * @throws Exception
 */
function autoverifactuSendInvoice($invoice, $recordType, &$xml = '')
{
	global $user;

	$records = array();
	$record = autoverifactuInvoiceToRecord($invoice, $recordType);

	if (!$record) {
		return;
	}

	global $hookmanager;

	$parameters = array('record' => &$record, 'invoice' => $invoice, 'action' => $action);
	$reshook = $hookmanager->executeHooks(
		'autoverifactuRecord',
		$parameters,
		$record,
	);

	if ($reshook < 0) {
		dol_syslog('Skip verifactu record registry for invoice #' . $invoice->id);
		return;
	}

	if ($reshook) {
		dol_syslog(
			'Verifactu record registry interception on "autoverifactuRecord" for invoice #'
			. $invoice->id,
		);

		return;
	}

	global $mysoc;

	$issuer = array('name' => $mysoc->nom, 'idprof1' => $mysoc->idprof1);

	$envelope = $xml = autoverifactuSoapEnvelope($record, $issuer);

	try {
		$res = autoverifactuSoapRequest($envelope);
	} catch (Throwable $th) {
		throw new Exception($th->getMessage(), 400);
	}

	autoverifactuHandleSoapResponse($res, $invoice, $record);
	return $record;
}

function autoverifactuHandleSoapResponse($res, $invoice, $record)
{
	$record = $records[$index];

	$operationType = $res->getElementsByTagName('TipoOperacion')->item(0);
	$state = $res->getElementsByTagName('EstadoRegistro')->item(0);
	$status = $invoice->array_options['options_verifactu_status'] ?? '0';

	if ($state->nodeValue === 'Incorrecto') {
		dol_syslog('# REJECTED SOAP ENVELOPE', LOG_DEBUG);
		dol_syslog($envelope, LOG_DEBUG);

		$errCode = $res->getElementsByTagName('CodigoErrorRegistro')->item(0);
		$errMessage = $res->getElementsByTagName('DescripcionErrorRegistro')->item(0);

		if (!$errMessage || !$errCode) {
			dol_syslog('# REJECTED SOAP ENVELOPE', LOG_DEBUG);
			dol_syslog($envelope, LOG_DEBUG);
			throw new Exception($res->saveXML(), 500);
		}

		$record->error = new stdClass();
		$record->error->code = $errCode->nodeValue;
		$record->error->message = $errMessage->nodeValue;

		if ($operationType->nodeValue === 'Alta') {
			if (in_array($status, array('0', '5'), true)) {
				// Rechazado.
				$invoice->array_options['options_verifactu_status'] = '5';
			} else {
				// Aceptado con errores múltiples veces.
				$invoice->array_options['options_verifactu_status'] = '3';
			}
		}
	} elseif ($state->nodeValue === 'AceptadoConErrores') {
		dol_syslog('# ACCEPTED WITH ERRORS SOAP ENVELOPE', LOG_DEBUG);
		dol_syslog($envelope, LOG_DEBUG);

		$errCode = $res->getElementsByTagName('CodigoErrorRegistro')->item(0);
		$errMessage = $res->getElementsByTagName('DescripcionErrorRegistro')->item(0);

		if (!$errMessage || !$errCode) {
			dol_syslog('# REJECTED SOAP ENVELOPE', LOG_DEBUG);
			dol_syslog($envelope, LOG_DEBUG);
			throw new Exception($res->saveXML(), 500);
		}

		$record->error = new stdClass();
		$record->error->code = $errCode->nodeValue;
		$record->error->message = $errMessage->nodeValue;

		if ($operationType->nodeValue === 'Alta') {
			if (in_array($queueState, array('2', '3'), true)) {
				// Aceptado con errores múltiples veces.
				$invoice->array_options['options_verifactu_status'] = '3';
			} else {
				// Aceptado con errores.
				$invoice->array_options['options_verifactu_status'] = '2';
			}
		}
	} else {
		if ($operationType->nodeValue === 'Alta') {
			$invoice->array_options['options_verifactu_status'] = '1';
		} else {
			$invoice->array_options['options_verifactu_status'] = '4';
		}

		autoverifactuDequeueRecord($invoice->id);
	}
}

/**
 * Sends a verifactu SOAP envelope as a POST request to verifactu API.
 *
 * @param string       $body  Body of the request.
 * @param int          $ttl   Time to live or request retries counter.
 *
 * @return DOMDocument        Request response.
 *
 * @throws Exception          On HTTP errors.
 */
function autoverifactuSoapRequest($body, $ttl = 3)
{
	global  $db;
	$testMode = (bool) getDolGlobalString('AUTOVERIFACTU_TEST_MODE');
	$base_url = $testMode ? VERIFACTU_TEST_BASE_URL : VERIFACTU_BASE_URL;
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $base_url . '/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSOAP');
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

	curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
	$res = curl_exec($ch);
	dol_syslog('# RESPUESTA ALTA REGISTRO', LOG_DEBUG);
	dol_syslog($res, LOG_DEBUG);

	// En caso de no obtener respuesta, lanzamos un error de CURL a.k.a. error de conexión.
	if ($res === false) {
		$error = curl_error($ch);
		$code = curl_errno($ch);
		throw new Exception('cURL error:' . $error, $code);
	}

	$doc = new DOMDocument();
	$doc->loadXML($res . "\n");

	// Consultamos la existencia en la resupuesta de información respecto
	// a los tiempos de envío permitidos.
	$nodes = $doc->getElementsByTagName('TiempoEsperaEnvio');
	if ($nodes->count() > 0) {
		// Obtenemos la siguiente fecha de envío permitida en base al timestamp actual
		// sumado al tiempo de espera indicado por la respuesta, y guardamos el resultado
		// en base de datos.
		$now = new DateTimeImmutable('now', new DateTimeZone('Europe/Madrid'));
		$time = (int) $nodes->item(0)->nodeValue;
		$then = $now->modify('+' . $time . ' seconds');

		$result = dolibarr_set_const($db, 'VERIFACTU_NEXT_DELIVERY_ALLOWED', $then->getTimestamp(), 'chaine', 0, '', 0);
		if ($result <= 0) {
			dol_syslog('# VERIFACTU SAVE DOLIBARR CONST VERIFACTU_NEXT_DELIVERY_ALLOWED', LOG_DEBUG);
		}
	}

	// Consultamos la existencia de reportes de error en el contenido de la respuesta.
	$faults = $doc->getElementsByTagName('Fault');
	if ($faults->count() > 0) {
		$fault = $faults->item(0);
		$code = $fault->getElementsByTagName('faultcode')->item(0);

		// Si el error es de servidor (su parte), reintentamos la llamda, hasta
		// consumir el ttl disponibles (3).
		if (in_array($code->nodeValue, array('env:Server', 'sopaenv:Server'), true)) {
			if ($ttl) {
				return autoverifactuSoapRequest($body, $ttl - 1);
			} else {
				// Exit if max retries is reached.
				dol_syslog('# VERIFACTU API SERVICE UNAVAILABLE', LOG_DEBUG);
				dol_syslog($res, LOG_DEBUG);
				throw new Exception($res, 503);
			}
		}

		dol_syslog('# REJECTED SOAP ENVELOPE', LOG_DEBUG);
		dol_syslog($body, LOG_DEBUG);

		throw new Exception($res, 400);
	}

	return $doc;
}

/**
 * Gets an verifactu invoice record and returns it inside a SOAP envelope.
 *
 * @param stdClass[]  $records         List of invoice records.
 * @param array       $issuer         Issuer data with name and id keys.
 * @param array|null  $representative Representative data with name and id keys.
 *
 * @return string                     SOAP XML enveloped record.
 */
function autoverifactuSoapEnvelope($records, $issuer, $representative = null)
{
	$xml = new DOMDocument('1.0', 'UTF-8');
	$envelope = $xml->createElement('soapenv:Envelope');
	$envelope->setAttribute('xmlns:soapenv', AUTOVERIFACTU_SOAPENV_NS);
	$envelope->setAttribute('xmlns:sum', AUTOVERIFACTU_SUM_NS);
	$envelope->setAttribute('xmlns:sum1', AUTOVERIFACTU_SUM1_NS);
	$envelope->setAttribute('xmlns:xd', AUTOVERIFACTU_XD_NS);
	$headerEl = $xml->createElement('soapenv:Header');
	$envelope->appendChild($headerEl);
	$body = $xml->createElement('soapenv:Body');
	$envelope->appendChild($body);
	$root = $xml->createElement('sum:RegFactuSistemaFacturacion');
	$body->appendChild($root);
	$regHeaderEl = $xml->createElement('sum:Cabecera');
	$root->appendChild($regHeaderEl);
	$issuerEl = $xml->createElement('sum1:ObligadoEmision');
	$regHeaderEl->appendChild($issuerEl);
	$issuerNameEl = $xml->createElement('sum1:NombreRazon', htmlspecialchars($issuer['name']));
	$issuerEl->appendChild($issuerNameEl);
	$issuerNifEl = $xml->createElement('sum1:NIF', htmlspecialchars($issuer['idprof1']));
	$issuerEl->appendChild($issuerNifEl);

	if ($representative) {
		$representativeEl = $xml->createElement('sum1:Representante');
		$regHeaderEl->appendChild($representativeEl);

		$reprNameEl = $xml->createElement('sum1:NombreRazon', htmlspecialchars($representative['name']));
		$representativeEl->appendChild($reprNameEl);

		$reprNifEl = $xml->createElement('sum1:NIF', htmlspecialchars($representative['idprof1']));
		$representativeEl->appendChild($reprNifEl);
	}

	foreach ($records as $record) {
		$recordEl = autoverifactuRecordToXML($record, $xml);
		$root->appendChild($recordEl);
	}

	$xml->appendChild($envelope);
	return $xml->saveXML($envelope);
}

/**
* Return the invoice as a Veri*Factu record object.
*
* @param Facture $invoice     Target invoice.
* @param string  $recordType  Record type. Can be 'alta' or 'anulacion'.
*
* @return stdClass|null        Record representation.
*/
function autoverifactuInvoiceToRecord($invoice, $recordType = 'alta')
{
	global $mysoc;

	$now = dol_now();
	$invoiceRef = trim($invoice->status > 0 ? $invoice->ref : $invoice->newref);

	$invoice->fetch_optionals();
	$invoice->fetch_thirdparty();
	$thirdparty = $invoice->thirdparty;

	switch ($invoice->type) {
		case Facture::TYPE_STANDARD:
		case Facture::TYPE_DEPOSIT:
			if (autoverifactuIsSimplifiedInvoice($invoice)) {
				// Factura simplificada y facturas sin identificación del destinatario (Art. 6.1.D del R.D. 1619/2012).
				$invoiceType = 'F2';
			} else {
				// Factura (Art. 6, 7.2 y 7.3 del R.D. 1619/2012).
				$invoiceType = 'F1';
			}

			// Factura emitida en sustitución de facturas simplificadas facturadas y declaradas.
			// $invoiceType = 'F3';
			break;
		case Facture::TYPE_REPLACEMENT:
		case Facture::TYPE_CREDIT_NOTE:
			if (autoverifactuIsSimplifiedInvoice($invoice)) {
				// Factura rectificativa simplificada.
				$invoiceType = 'R5';
			} else {
				// Factura rectificativa corriente.
				if (!isset($invoice->array_options['options_verifactu_rectification_type'])) {
					$invoice->errors['rectification_type'] = 'RequiredError';
					return;
				}

				$invoiceType = $invoice->array_options['options_verifactu_rectification_type'] ;
			}

			break;
		default:
			$invoiceType = 'F1';
	}

	$record = new stdClass();
	$record->type = $recordType;
	$record->status = $invoice->array_options['options_verifactu_status'] ?? '0';

	if (!empty($invoice->array_options['options_verifactu_date_operation'])) {
		$record->dateOperation = new DateTimeImmutable(
			date('Y-m-d H:i:s', $invoice->array_options['options_verifactu_date_operation']),
			new DateTimeZone('Europe/Madrid'),
		);
	} else {
		$record->dateOperation = null;
	}

	$record->issuerName = htmlspecialchars(trim($mysoc->nom));
	$record->invoiceType = $invoiceType;
	$record->description = 'Factura ' . htmlspecialchars($invoiceRef);
	$record->invoiceId = new stdClass();
	$record->invoiceId->issuerId = trim($mysoc->idprof1);
	$record->invoiceId->invoiceNumber = htmlspecialchars($invoiceRef);
	$record->invoiceId->issueDate = new DateTimeImmutable(
		date('Y-m-d H:i:s', $invoice->array_options['options_verifactu_tms'] ?: $now),
		new DateTimeZone('Europe/Madrid'),
	);

	$record->recipients = array();

	// If is not simplified, add third party data to the record
	if (!in_array($record->invoiceType, array('F2', 'R5'), true)) {
		$recipient = new stdClass();

		if ($thirdparty->country_code && $thirdparty->country_code !== 'ES') {
			$recipient->name = htmlspecialchars(trim($thirdparty->nom));
			$recipient->country = $thirdparty->country_code;

			$foreignIdType = $thirdparty->array_options['options_verifactu_foreign_id_type'] ?? null;

			$recipient->type = $foreignIdType;
			if ($thirdparty->tva_intra || $recipient->type === '02') {
				$recipient->value = trim($thirdparty->tva_intra ?? null);
			} else {
				$recipient->value = trim($thirdparty->idprof1);
			}
		} else {
			$recipient->name = htmlspecialchars(trim($thirdparty->nom));
			$recipient->nif = trim($thirdparty->idprof1);
		}

		$record->recipients[0] = $recipient;
	}

	$correctiveTypes = array('R1', 'R2', 'R3', 'R4', 'R5');
	if (in_array($record->invoiceType, $correctiveTypes, true)) {
		if ( $invoice->type === Facture::TYPE_REPLACEMENT) {
			// Fix by substitution
			$record->correctiveType = 'S';
		} else {
			// Fix by differences
			$record->correctiveType = 'I';
		}
	} else {
		$record->correctiveType = null;
	}

	$record->correctedInvoices = array();
	$record->correctedBaseAmount = null;
	$record->correctedTaxAmount = null;

	// If is corrective, then add correctiveInvoices data to the record.
	if ($record->correctiveType !== null) {
		$sourceInvoice = autoverifactuGetSourceInvoice($invoice);

		if (!$sourceInvoice) {
			$invoice->errors['sourceInvoice'] = 'RequiredError';
			return;
		} else {
			$sourceInvoice->fetch_thirdparty();
		}

		$sourceId = new stdClass();
		$sourceId->issuerId = trim($mysoc->idprof1);
		$sourceId->invoiceNumber = trim($sourceInvoice->ref);
		$sourceId->issueDate = new DateTimeImmutable(
			date('Y-m-d H:i:s', $sourceInvoice->array_options['options_verifactu_tms']),
			new DateTimeZone('Europe/Madrid'),
		);

		$record->correctedInvoices[0] = $sourceId;

		if ($record->correctiveType === 'S') {
			$record->correctedBaseAmount = number_format($sourceInvoice->total_ht, 2, '.', '');
			$record->correctedTaxAmount = number_format($sourceInvoice->total_tva, 2, '.', '');
		} else {
			$record->correctedBaseAmount = null;
			$record->correctedTaxAmount = null;
		}
	}

	$record->replacedInvoices = array();

	$record->breakdown = autoverifactuLinesToBreakdown($invoice);

	$taxTotal = 0;
	$baseTotal = 0;

	foreach ($record->breakdown as $line) {
		$equivalenceSurcharge = $line->equivalenceSurcharge->total ?? 0;

		$taxTotal += $line->taxAmount + $equivalenceSurcharge;
		$baseTotal += $line->baseAmount;
	}

	$totalAmount = $baseTotal + $taxTotal;

	$record->totalTaxAmount = number_format($taxTotal, 2, '.', '');
	$record->totalAmount = number_format($totalAmount, 2, '.', '');

	$previous = autoverifactuGetPreviousValidInvoice($invoice, $now);

	if ($previous) {
		$record->previousInvoiceId = new stdClass();
		$record->previousInvoiceId->issuerId = trim($mysoc->idprof1);
		$record->previousInvoiceId->invoiceNumber = $invoiceRef;
		$record->previousInvoiceId->issueDate = new DateTimeImmutable(
			date('Y-m-d H:i:s', $previous->array_options['options_verifactu_tms']),
			new DateTimeZone('Europe/Madrid'),
		);

		$previous->fetch_optionals();
		$record->previousHash = substr($previous->array_options['options_verifactu_hash'], 0, 64);
	} else {
		$record->previousInvoiceId = null;
		$record->previousHash = null;
	}

	$record->system = autoverifactuGetRecordComputerSystem();

	/*
	! Cuando obentenos el registro horario guardado , y genereamos una factura de
	! anulacion o corregimos algun error despues del margen que nos dan (240 seg)
	! nos indica un error de tiempo. de la otra forma no nos indica ese error. (Inicado la hora de envio)
	$record->hashedAt = new DateTimeImmutable(
		date('Y-m-d H:i:s', $invoice->array_options['options_verifactu_tms'] ?: $now),
		new DateTimeZone('Europe/Madrid'),
	);*/

	$record->hashedAt = new DateTimeImmutable(
		date('Y-m-d H:i:s', $now),
		new DateTimeZone('Europe/Madrid'),
	);
	$record->hash = autoverifactuCalculateRecordHash($record);

	global $hookmanager;
	if (!is_object($hookmanager)) {
		include_once DOL_DOCUMENT_ROOT . '/core/class/hookmanager.class.php';
		$hookmanager = new HookManager($db);
	}

	$parameters = array(
		'record' => $record,
		'invoice' => $invoice,
	);

	$reshook = $hookmanager->executeHooks(
		'autoverifactuRecord',
		$parameters,
		$invoice,
	);

	if (!empty($reshook)) {
		return;
	}

	if (autoverifactuValidateRecord($record, $invoice->errors)) {
		return $record;
	}
}

/**
 * Serializes a record as a valid Vri*Factu XML record.
 *
 * @param stdClass          $record  Invoice Veri*Factu record object.
 * @param DOMDocument|null  $xml     Inherited document. If null, node will be created
 *                                   on a new DOMDocument instance.
 *
 * @return DOMElement                 XML record representation.
 *
 * @throws Exception                  If record type is not anulacion or alta.
 */
function autoverifactuRecordToXML($record, $xml = null)
{
	$xml = $xml ?: new DOMDocument();

	$recordElementName = $record->type === 'alta'
		? 'RegistroAlta'
		: 'RegistroAnulacion';

	$root = $xml->createElement('sum:RegistroFactura');

	$recordEl = $xml->createElement('sum1:' . $recordElementName);
	$root->appendChild($recordEl);

	$recordEl->appendChild($xml->createElement('sum1:IDVersion', '1.0'));

	if ($record->type === 'alta') {
		$invoiceId = $xml->createElement('sum1:IDFactura');
		$recordEl->appendChild($invoiceId);

		$invoiceId->appendChild($xml->createElement('sum1:IDEmisorFactura', htmlspecialchars($record->invoiceId->issuerId)));
		$invoiceId->appendChild($xml->createElement('sum1:NumSerieFactura', htmlspecialchars($record->invoiceId->invoiceNumber)));
		$invoiceId->appendChild($xml->createElement(
			'sum1:FechaExpedicionFactura',
			$record->invoiceId->issueDate->format('d-m-Y')
		));

		$recordEl->appendChild($xml->createElement('sum1:NombreRazonEmisor', htmlspecialchars($record->issuerName)));

		// Etiquetas para subsanacion de errores.
		if (in_array($record->status, array('2', '3', '5'), true)) {
			$valueSubsanacion = 'S';
			$valueRechazoPrevio = 'X';

			if ($record->status === '2') {
				$valueRechazoPrevio = 'N';
			}

			if ($record->status === '3') {
				$valueRechazoPrevio = 'S';
			}

			$recordEl->appendChild($xml->createElement('sum1:Subsanacion', $valueSubsanacion));
			$recordEl->appendChild($xml->createElement('sum1:RechazoPrevio', $valueRechazoPrevio));
		}

		$recordEl->appendChild($xml->createElement('sum1:TipoFactura', $record->invoiceType));

		if ($record->dateOperation !== null) {
			$recordEl->appendChild($xml->createElement('sum1:FechaOperacion', $record->dateOperation->format('d-m-Y')));
		}

		if ($record->correctiveType !== null) {
			$recordEl->appendChild($xml->createElement('sum1:TipoRectificativa', $record->correctiveType));
		}

		if (count($record->correctedInvoices)) {
			$correctedInvoices = $xml->createElement('sum1:FacturasRectificadas');
			$recordEl->appendChild($correctedInvoices);

			foreach ($record->correctedInvoices as $correctedInvoice) {
				$fixId = $xml->createElement('sum1:IDFacturaRectificada');
				$correctedInvoices->appendChild($fixId);

				$fixId->appendChild($xml->createElement('sum1:IDEmisorFactura', htmlspecialchars($correctedInvoice->issuerId)));
				$fixId->appendChild($xml->createElement('sum1:NumSerieFactura', htmlspecialchars($correctedInvoice->invoiceNumber)));
				$fixId->appendChild($xml->createElement(
					'sum1:FechaExpedicionFactura',
					$correctedInvoice->issueDate->format('d-m-Y')
				));
			}
		}

		if (count($record->replacedInvoices)) {
			$replacedInvoices = $xml->createElement('sum1:FacturasSustituidas');
			$recordEl->appendChild($replacedInvoices);

			foreach ($record->replacedInvoices as $replacedInvoice) {
				$replId = $xml->createElement('sum1:IDFacturaSustituida');
				$replacedInvoices->appendChild($replId);

				$replId->appendChild($xml->createElement('sum1:IDEmisorFactura', htmlspecialchars($replacedInvoice->issuerId)));
				$replId->appendChild($xml->createElement('sum1:NumSerieFactura', htmlspecialchars($replacedInvoice->invoiceNumber)));
				$replId->appendChild($xml->createElement(
					'sum1:FechaExpedicionFactura',
					$replacedInvoice->issueDate->format('d-m-Y')
				));
			}
		}

		if ($record->correctedBaseAmount !== null && $record->correctedTaxAmount !== null) {
			$importEl = $xml->createElement('sum1:ImporteRectificacion');
			$recordEl->appendChild($importEl);

			$recordEl->appendChild($xml->createElement('sum1:BaseRectificada', $record->correctedBaseAmount));
			$recordEl->appendChild($xml->createElement('sum1:CuotaRectificada', $record->correctedTaxAmount));
		}

		$recordEl->appendChild($xml->createElement('sum1:DescripcionOperacion', htmlspecialchars($record->description)));

		if (count($record->recipients)) {
			$recipients = $xml->createElement('sum1:Destinatarios');
			$recordEl->appendChild($recipients);

			foreach ($record->recipients as $recipient) {
				$recipientEl = $xml->createElement('sum1:IDDestinatario');
				$recipients->appendChild($recipientEl);

				$recipientEl->appendChild($xml->createElement('sum1:NombreRazon', htmlspecialchars($recipient->name)));

				if (isset($recipient->country, $recipient->type)) {
					$foreignId = $xml->createElement('sum1:IDOtro');
					$recipientEl->appendChild($foreignId);

					$foreignId->appendChild($xml->createElement('sum1:CodigoPais', $recipient->country));
					$foreignId->appendChild($xml->createElement('sum1:IDType', $recipient->type));
					$foreignId->appendChild($xml->createElement('sum1:ID', htmlspecialchars($recipient->value)));
				} else {
					$recipientEl->appendChild($xml->createElement('sum1:NIF', htmlspecialchars($recipient->nif)));
				}
			}
		}

		$breakdown = $xml->createElement('sum1:Desglose');
		$recordEl->appendChild($breakdown);
		foreach ($record->breakdown as $details) {
			$dEl = $xml->createElement('sum1:DetalleDesglose');
			$breakdown->appendChild($dEl);

			$dEl->appendChild($xml->createElement('sum1:Impuesto', $details->taxType));
			if ($details->taxType !== '05') {
				$dEl->appendChild($xml->createElement('sum1:ClaveRegimen', $details->regimeType));
			}

			if ($details->exemptionCode) {
				$dEl->appendChild($xml->createElement('sum1:OperacionExenta', $details->exemptionCode));
			} else {
				// Se indicará la calificación de la operación en caso de no existir código de exención.
				$dEl->appendChild($xml->createElement('sum1:CalificacionOperacion', $details->operationType));
				$dEl->appendChild($xml->createElement('sum1:TipoImpositivo', $details->taxRate));
			}

			$dEl->appendChild($xml->createElement('sum1:BaseImponibleOimporteNoSujeto', $details->baseAmount));

			if (!$details->exemptionCode) {
				$dEl->appendChild($xml->createElement('sum1:CuotaRepercutida', $details->taxAmount));
			}

			// Se indicará el recargo de equivalencia y el tipo en caso de existir.
			if (!$details->exemptionCode && $details->operationType === 'S1' && isset($details->equivalenceSurcharge)) {
				$dEl->appendChild($xml->createElement(
					'sum1:TipoRecargoEquivalencia',
					$details->equivalenceSurcharge->type,
				));
				$dEl->appendChild($xml->createElement(
					'sum1:CuotaRecargoEquivalencia',
					$details->equivalenceSurcharge->total,
				));
			}
		}

		$recordEl->appendChild($xml->createElement('sum1:CuotaTotal', $record->totalTaxAmount));
		$recordEl->appendChild($xml->createElement('sum1:ImporteTotal', $record->totalAmount));
	} elseif ($record->type === 'anulacion') {
		$invoiceId = $xml->createElement('sum1:IDFactura');
		$recordEl->appendChild($invoiceId);
		$invoiceId->appendChild($xml->createElement('sum1:IDEmisorFacturaAnulada', htmlspecialchars($record->invoiceId->issuerId)));
		$invoiceId->appendChild($xml->createElement('sum1:NumSerieFacturaAnulada', htmlspecialchars($record->invoiceId->invoiceNumber)));
		$invoiceId->appendChild($xml->createElement('sum1:FechaExpedicionFacturaAnulada', $record->invoiceId->issueDate->format('d-m-Y')));
	} else {
		throw new Exception('Invalid record type: ' . $record->type);
	}

	$chainEl = $xml->createElement('sum1:Encadenamiento');
	$recordEl->appendChild($chainEl);

	if ($record->previousInvoiceId === null) {
		$chainEl->appendChild($xml->createElement('sum1:PrimerRegistro', 'S'));
	} else {
		$prevEl = $xml->createElement('sum1:RegistroAnterior');
		$chainEl->appendChild($prevEl);

		$prevEl->appendChild($xml->createElement('sum1:IDEmisorFactura', htmlspecialchars($record->previousInvoiceId->issuerId)));
		$prevEl->appendChild($xml->createElement('sum1:NumSerieFactura', htmlspecialchars($record->previousInvoiceId->invoiceNumber)));
		$prevEl->appendChild($xml->createElement(
			'sum1:FechaExpedicionFactura',
			$record->previousInvoiceId->issueDate->format('d-m-Y')
		));
		$prevEl->appendChild($xml->createElement('sum1:Huella', $record->previousHash));
	}

	$systemEl = $xml->createElement('sum1:SistemaInformatico');
	$recordEl->appendChild($systemEl);

	$systemEl->appendChild($xml->createElement('sum1:NombreRazon', htmlspecialchars($record->system->vendorName)));
	$systemEl->appendChild($xml->createElement('sum1:NIF', htmlspecialchars($record->system->vendorNif)));
	$systemEl->appendChild($xml->createElement('sum1:NombreSistemaInformatico', htmlspecialchars($record->system->name)));
	$systemEl->appendChild($xml->createElement('sum1:IdSistemaInformatico', htmlspecialchars($record->system->id)));
	$systemEl->appendChild($xml->createElement('sum1:Version', htmlspecialchars($record->system->version)));
	$systemEl->appendChild($xml->createElement('sum1:NumeroInstalacion', htmlspecialchars($record->system->installationNumber)));
	$systemEl->appendChild($xml->createElement(
		'sum1:TipoUsoPosibleSoloVerifactu',
		$record->system->onlySupportsVerifactu ? 'S' : 'N'
	));
	$systemEl->appendChild($xml->createElement(
		'sum1:TipoUsoPosibleMultiOT',
		$record->system->supportsMultipleTaxpayers ? 'S' : 'N'
	));
	$systemEl->appendChild($xml->createElement(
		'sum1:IndicadorMultiplesOT',
		$record->system->hasMultipleTaxpayers ? 'S' : 'N'
	));

	$recordEl->appendChild($xml->createElement(
		'sum1:FechaHoraHusoGenRegistro',
		$record->hashedAt->format('c'),
	));
	$recordEl->appendChild($xml->createElement('sum1:TipoHuella', '01')); // SHA-256
	$recordEl->appendChild($xml->createElement('sum1:Huella', $record->hash));

	return $root;
}

/**
* Returns invoice lines as a breakdown details array.
*
* @param Facture     $invoice Target invoice.
*
* @return stdClass[]
*/
function autoverifactuLinesToBreakdown($invoice)
{
	$groups = array();
	$group_template = array(
		'taxType' => null,
		'regimeType' => null,
		'operationType' => null,
		'excemptionCode' => null,
		'taxRate' => 0,
		'baseAmount' => 0,
		'taxAmount' => 0,
		'equivalenceSurcharge' => null,
		'equivalenceSurchargeTotal' => 0,
	);

	$defaultRegime = getDolGlobalString('AUTOVERIFACTU_DEFAULT_REGIME') ?: '01';

	foreach ($invoice->lines as $line) {
		$taxType = $line->array_options['options_verifactu_tax'] ?: '01';
		$regimeType = $line->array_options['options_verifactu_regime_type'] ?: $defaultRegime;
		$operationType = $line->array_options['options_verifactu_operation_type'] ?: 'S1';
		$exemptionCode = $line->array_options['options_verifactu_tax_excemption'] ?: null;
		$taxRate = number_format((float) $line->tva_tx, 2, '.', '');
		$baseAmount = (float) $line->total_ht;
		$taxAmount = (float) $line->total_tva;

		$equivalenceSurchargeType = !$line->localtax1_tx && $line->product_type === '0' ? number_format((float) $line->localtax1_tx, 2, '.', '') : null;
		$equivalenceSurchargeTotal = $line->total_localtax1 ? (float) $line->total_localtax1 : 0.0;

		$key = implode('|', array(
			$taxType,
			$regimeType,
			$operationType,
			$exemptionCode ?: '',
			$taxRate,
			$equivalenceSurchargeType ?: null
		));

		if (!isset($grouped[$key])) {
			$groups[$key] = array_merge($group_template, array(
				'taxType' => $taxType,
				'regimeType' => $regimeType,
				'operationType' => $operationType,
				'exemptionCode' => $exemptionCode,
				'taxRate' => $taxRate,
				'equivalenceSurchargeType' => $equivalenceSurchargeType,
			));
		}

		$groups[$key]['baseAmount'] += $baseAmount;
		$groups[$key]['taxAmount'] += $taxAmount;
		$groups[$key]['equivalenceSurchargeTotal'] += $equivalenceSurchargeTotal;
	}

	$breakdown = array();
	if (count($groups) > 12) {
		$invoice->errors[] = 'MaxRecordLinesError';
		return -1;
	}

	foreach ($groups as $group) {
		$details = new stdClass();
		$details->taxType = $group['taxType'];
		$details->regimeType = $group['regimeType'];
		$details->operationType = $group['operationType'];
		$details->exemptionCode = $group['exemptionCode'];
		$details->excemptionCode = $group['exemptionCode'];
		$details->taxRate = $group['taxRate'];
		$details->baseAmount = number_format($group['baseAmount'], 2, '.', '');
		$details->taxAmount = number_format($group['taxAmount'], 2, '.', '');

		if ($group['equivalenceSurchargeType']  ) {
			$details->equivalenceSurcharge = new stdClass();
			$details->equivalenceSurcharge->type = $group['equivalenceSurchargeType'];
			$details->equivalenceSurcharge->total = number_format($group['equivalenceSurchargeTotal'], 2, '.', '');
		}

		$breakdown[] = $details;
	}

	return $breakdown;
}

/**
 * Return the invoice record computer system data. It uses $mysoc global variable to fill
 * the vendor name and nif values.
 *
 * @return stdClass Record's computer system data.
 */
function autoverifactuGetRecordComputerSystem()
{
	if (!autoverifactuSystemCheck()) {
		return;
	}

	global $mysoc;

	$system = new stdClass();
	$system->vendorName = trim($mysoc->nom);
	$system->vendorNif = trim($mysoc->idprof1);
	$system->name = 'Auto-Veri*Factu Dolibarr';
	$system->id = 'AV';
	$system->version = '1.0.0';
	$system->installationNumber = '001';
	$system->onlySupportsVerifactu = true;
	// TODO: Handle muti company
	$system->supportsMultipleTaxpayers = false;
	$system->hasMultipleTaxpayers = false;

	return $system;
}

/**
* Calculate the record hash.
*
* @param stdClass $record Invoice record object.
*
* @return string           Record sha256 hash.
*/
function autoverifactuCalculateRecordHash($record)
{
	if ($record->type == 'alta') {
		$payload  = 'IDEmisorFactura=' . $record->invoiceId->issuerId;
		$payload .= '&NumSerieFactura=' . $record->invoiceId->invoiceNumber;
		$payload .= '&FechaExpedicionFactura=' . $record->invoiceId->issueDate->format('d-m-Y');
		$payload .= '&TipoFactura=' . $record->invoiceType;
		$payload .= '&CuotaTotal=' . $record->totalTaxAmount;
		$payload .= '&ImporteTotal=' . $record->totalAmount;
		$payload .= '&Huella=' . ($record->previousHash ?? '');
		$payload .= '&FechaHoraHusoGenRegistro=' . $record->hashedAt->format('c');
	} elseif ($record->type === 'anulacion') {
		$payload  = 'IDEmisorFacturaAnulada=' . $record->invoiceId->issuerId;
		$payload .= '&NumSerieFacturaAnulada=' . $record->invoiceId->invoiceNumber;
		$payload .= '&FechaExpedicionFacturaAnulada=' . $record->invoiceId->issueDate->format('d-m-Y');
		$payload .= '&Huella=' . ($record->previousHash ?? '');
		$payload .= '&FechaHoraHusoGenRegistro=' . $record->hashedAt->format('c');
	} else {
		$payload = '';
	}

	return strtoupper(hash('sha256', $payload));
}

/**
 * Returns a dynamic legal text to be printed on PDF invoices based on invoice characteristics.
 *
 * @param Facture $invoice Facture object.
 *
 * @return string
 */
function autoverifactuPDFLegalText($invoice)
{
	global $langs;
	$langs->load('autoverifactu@autoverifactu');

	$legalText = array();

	if ($invoice->total_localtax2) {
		$legalText[] = $langs->trans('AutoverifactuLegalTextIRPF');
	}

	$invoice->fetch_lines();
	foreach ($invoice->lines as $line) {
		$lineTaxExcemption = $line->array_options['options_verifactu_tax_excemption'] ?? null;
		if ($lineTaxExcemption) {
			$isValid = autoverifactuValidateExcemptionCode($lineTaxExcemption);
			if (!$isValid) {
				continue;
			}

			$transKey = 'AutoverifactuLegalTextExemption' . $lineTaxExcemption;
			$legalText[] = $langs->trans($transKey);
		}

		$opType = $line->array_options['options_verifactu_operation_type'] ?? null;
		$regime = $line->array_options['options_verifactu_regime_type'] ?? '01';

		if ($opType === 'S2') {
			// Sin IVA e inversión del sujeto pasivo.
			$legalText[] = $langs->trans('VerifactuLegalTextSujetoPasivo');
		} elseif ($regime === '03') {
			// Régimen especial de bienes usados.
			$legalText[] = $langs->trans('VerifactuLegalTextREBU');
		} elseif ($regime === '07') {
			// Régimen de criterio de caja.
			$legalText[] = $langs->trans('VerifactuLegalTextCriterioCaja');
		} elseif ($regime === '05' /* || $regime === '09' */) {
			// Régimen especial de las agencias de viajes.
			$legalText[] = $langs->trans('VerifactuLegalTextAgenciaViajes');
		}
	}

	if (!$legalText) {
		return;
	}

	$legalText = array_unique($legalText);

	return implode('<br>', $legalText);
}

/**
 * Checks if the last waiting time reported by the Veri*Factu API responses has been exceeded.
 *
 * @param int $lastingTime Pointer where the lasting time to the next delivery will be stored.
 *
 * @return bool
 */
function autoverifactuIsDeliveryAllowed(&$lastingTime = 0)
{
	$now = (new DateTimeImmutable('now', new DateTimeZone('Europe/Madrid')))->getTimestamp();
	$time = getDolGlobalString('VERIFACTU_NEXT_DELIVERY_ALLOWED', '0');
	$lastingTime = max($time - $now, 0);
	return $lastingTime === 0;
}

/**
 * Returns the validation URL corresponding to the giving invoice.
 *
 * @param Facture $invoice Invoice object.
 * @param bool    $as_json Switch to json response format.
 *
 * @return string.
 */
function autoverifactuCollationLink($invoice, $as_json = false)
{
	global $mysoc;

	// En caso de tener IRPF hay que substraerlo del tota, ya que en verifactu no
	// lo tiene en cuenta.
	$invoiceTotal = $invoice->total_ttc;
	if ($invoice->total_localtax2) {
		$invoiceTotal -= $invoice->total_localtax2;
	}

	// Seleccionamos entorno en base al modo actual de Auto-Veri*Factu.
	$testMode = (bool) getDolGlobalString('AUTOVERIFACTU_TEST_MODE');
	$baseUrl = $testMode ? VERIFACTU_TEST_COLLATION_BASE_URL : VERIFACTU_COLLATION_BASE_URL;

	// Endpoint de cotejo de registors de facturación.
	$endpoint = '/wlpl/TIKE-CONT/ValidarQR';

	$queryArgs = array(
		'nif' => $mysoc->idprof1,
		'numserie' => $invoice->ref,
		'fecha' => date('d-m-Y', $invoice->date),
		'importe' => number_format($invoiceTotal, 2, '.', ''),
	);

	// El campo formato solo ha de ser informado cuando se espera una
	// respuesta en formato json.
	if ($asJson) {
		$queryArgs['formato'] = 'json';
	}

	$query = http_build_query($queryArgs);

	return $baseUrl . $endpoint . '?' . $query;
}
