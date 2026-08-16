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

	if (
		$invoice->status == Facture::STATUS_DRAFT &&
		!in_array(
			$action,
			array(
				'BILL_VALIDATE',
				// 'DON_VALIDATE',
				// 'CASHCONTROL_VALIDATE',
			),
			true,
		)
	) {
		return 0;
	} elseif (
		$invoice->status == Facture::STATUS_VALIDATED &&
		$action !== 'BILL_CANCEL'
		&&
		$action !== 'verifactuResend' //excluyo en caso de reenvio
	) {
		return 0;
	}
	;
	if (empty($conf->facture->multidir_output[$conf->entity])) {
		dol_syslog('Constant $conf->facture->multidir_output not defined', LOG_ERR);
		$invoice->error[] = 'NotExistInvoiceOutput'; //añado el error
		return -1;
	}

	$now = new DateTimeImmutable(
		'now',
		new DateTimeZone('Europe/Madrid'),
	);

	$invoice->fetch_thirdparty();
	$thirdparty = $invoice->thirdparty;
	$valid_id = $thirdparty->id_prof_check(1, $thirdparty);

	//las facturas simplificadas no tienen tercero y por tanto tienen que evitar esta validación
	if (!autoverifactuIsPosInvoice($invoice) && $valid_id <= 0 && !$thirdparty->tva_intra) {
		dol_syslog('Skip invoice verifactu record registration due to thirdparty without a vaid idprof1');
		$invoice->error[] = 'NotNIFThirdparty';
		return -1;
	}

	$invoice->fetch_lines();
	if (!count($invoice->lines)) {
		dol_syslog('Skip invoice verifactu record registration to an invoice without lines');
		$invoice->error[] = 'NotLinesFacure';
		return -1;
	}

	$invoiceref = dol_sanitizeFileName($invoice->ref);
	$dir = $conf->facture->multidir_output[$invoice->entity ?? $conf->entity] . '/' . $invoiceref;
	//he añadido la fecha para que no se sobreescriban los XML
	//de esta forma se tiene un historial de todas los XML enviados
	if ($action === 'BILL_VALIDATE') {
		$file = $dir . '/' . $invoiceref . '-alta-' . $now->format('Y-m-d_H-i-s') . '.xml';
		$hidden = $dir . '/.verifactu-alta-' . $now->format('Y-m-d_H-i-s') . '.xml';
	} elseif ($action === 'verifactuResend') {
		$file = $dir . '/' . $invoiceref . '-subsanacion-' . $now->format('Y-m-d_H-i-s') . '.xml';
		$hidden = $dir . '/.verifactu-subsanacion-' . $now->format('Y-m-d_H-i-s') . '.xml';
	} else {
		$file = $dir . '/' . $invoiceref . '-anulacion-' . $now->format('Y-m-d_H-i-s') . '.xml';
		$hidden = $dir . '/.verifactu-anulacion-' . $now->format('Y-m-d_H-i-s') . '.xml';
	}

	if (!file_exists($dir)) {
		if (dol_mkdir($dir) < 0) {
			dol_syslog('Unable to create verifactu files directory ' . $dir, LOG_ERR);
			$invoice->error[] = 'NotCreateDirectoryVerifactu';
			return -1;
		}
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
	);

	$reshook = $hookmanager->executeHooks(
		'beforeAutoverifactuRecord',
		$parameters,
		$invoice,
	);

	if ($reshook < 0) {
		dol_syslog('Skip verfiactu record registry for invoice #' . $invoice->id);
		return $reshook;
	} elseif ($reshook) {
		dol_syslog(
			'Verfiactu record registry interception on "beforeAutoverifactuRecord" for invoice #'
			. $invoice->id
		);

		return $reshook;
	}

	try {
		$record = autoverifactuSendInvoice($invoice, $action, $xml);

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
 * @param Facture $invoice Target invoice. Invoice should not be published before
 * @param string  $action  Triggered action. Can be BILL_VALIDATE or BILL_CANCEL.
 * @param string  $xml    Response body as an XML string.
 *
 * @return stdClass|null    Registered record, null if skipped.
 *
 * @throws Exception
 */
function autoverifactuSendInvoice($invoice, $action, &$xml = '')
{
	global $user;

	if (!autoverifactuSystemCheck()) {
		dol_syslog('Veri*Factu bridge does not pass system checks');
		return;
	}

	$enabled = getDolGlobalString('AUTOVERIFACTU_ENABLED') === '1';

	if (!$enabled) {
		dol_syslog('Veri*Factu bridge is not enabled');
		return;
	}

	$recordType = ($action === 'BILL_VALIDATE' ||  $action === 'verifactuResend') ? 'alta' : 'anulacion';

	if ($recordType === 'alta' && autoverifactuIsInvoiceRecorded($invoice)) {
		dol_syslog(
			'Skip verifactu invoice registration because invoice #'
			. $invoice->id .
			'is already registered',
		);
		$invoice->error[] = 'alradyRegisterInvoice';//añado los errores
		return -1 ;
	}

	$record = autoverifactuInvoiceToRecord($invoice, $recordType);

	if (!$record) {
		throw new Exception('Inconsistent invoice data');
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
		return $reshook;
	} elseif ($reshook) {
		dol_syslog(
			'Verifactu record registry interception on "autoverifactuRecord" for invoice #'
			. $invoice->id,
		);

		return $reshook;
	}

	global $mysoc;

	$issuer = array(
		'name' => $mysoc->nom,
		'idprof1' => $mysoc->idprof1,
	);

	//no exisiste la funcion autoverifactuValidateIssuer
	/*$issuerIsValid = autoverifactuValidateIssuer($issuer);

	if (!$issuerIsValid) {
		throw new Exception('Inconsistent issuer data');
	}*/

	$envelope = $xml = autoverifactuSoapEnvelope(
		$record,
		$issuer
	);

	try {
		$res = autoverifactuSoapRequest($envelope);
	} catch (\Throwable $th) {
		if ($th->getCode() === 503) {
			//en caso de un error interno y que la factura sea alta meto en la lista a enviar
			if ($recordType === 'alta') {
				$invoice->array_options['options_verifactu_status'] = '3';
				$invoice->insertExtraFields();
				dol_syslog('VERI*FACTU: FACTURE ID ' . $object->id . ' TEMPORARILY QUEUED.', LOG_DEBUG);
				return $record;
				throw new Exception($th->getMessage(), 503);
			} else {
				 throw new Exception($th->getMessage(), 400);
			}
		} else {
			throw new Exception($th->getMessage(), 400);
		}
	}


	$status = $res->getElementsByTagName('EstadoRegistro')->item(0);

	if ($status->nodeValue === 'Incorrecto') {
		dol_syslog('# REJECTED SOAP ENVELOPE', LOG_DEBUG);
		dol_syslog($envelope, LOG_DEBUG);

		if ($operationTypeNode->nodeValue === 'Anulacion') {
			//error de anulacion
			$invoice->array_options['options_verifactu_status'] = '7';
		} else {
			//error de alta

			if (in_array($invoice->array_options['options_verifactu_status'], array('0','2'), true)) {
				//No esta regsitrado(Pendiente de envio o rechazado)
				$invoice->array_options['options_verifactu_status'] = '2';
			} else {
				//esta regsitrado (Registrado correctamente pero con errores o Registrado correctamente pero con errores múltiples veces)
				$invoice->array_options['options_verifactu_status'] = '5';
			}
		}
		$invoice->ref = $record->invoiceId->invoiceNumber;

		$errCode = $res->getElementsByTagName('CodigoErrorRegistro')[0] ?? null;
		$errMessage = $res->getElementsByTagName('DescripcionErrorRegistro')[0] ?? null;
		if (!$errMessage || !$errCode) {
			dol_syslog('# REJECTED SOAP ENVELOPE', LOG_DEBUG);
			dol_syslog($envelope, LOG_DEBUG);
			throw new Exception($res->saveXML(), 500);
		}
		$record->error = new stdClass();
		$record->error->code = $errCode->nodeValue;
		$record->error->message = $errMessage->nodeValue;
		$result = $invoice->update($user);
		$invoice->insertExtraFields();
		if ($result <= 0) {
			throw new Exception($invoice->error, 1);
		}
		//throw new Exception($res->saveXML(), 400);
	} elseif ($status->nodeValue === 'AceptadoConErrores') {
		$errCode = $res->getElementsByTagName('CodigoErrorRegistro')[0] ?? null;
		$errMessage = $res->getElementsByTagName('DescripcionErrorRegistro')[0] ?? null;
		if (!$errMessage || !$errCode) {
			dol_syslog('# REJECTED SOAP ENVELOPE', LOG_DEBUG);
			dol_syslog($envelope, LOG_DEBUG);
			throw new Exception($res->saveXML(), 500);
		}

		$record->error = new stdClass();
		$record->error->code = $errCode->nodeValue;
		$record->error->message = $errMessage->nodeValue;
		//actualizo el estado a Enviada y correcta con errores
		$operationTypeNode = $res->getElementsByTagName('TipoOperacion')[0];
		if ($operationTypeNode->nodeValue === 'Anulacion') {
			//error de anulacion cuando esta la factura registrada
			$invoice->array_options['options_verifactu_status'] = '7';
		} else {
			//
			if (  in_array($invoice->array_options['options_verifactu_status'], array('4','5'), true)) {
				//hay errores en facturas de corrección
				$invoice->array_options['options_verifactu_status'] = '5';
			} else {
				//no hay errores en facturas de correción
				$invoice->array_options['options_verifactu_status'] = '4';
			}
		}


		$invoice->insertExtraFields();
	} else {
		//actualizo el estado a Enviada y correcta
		$operationTypeNode = $res->getElementsByTagName('TipoOperacion')[0];
		//actualizo el estado segun sea anulacion o alta
		$operationTypeNode->nodeValue === 'Anulacion' ? $invoice->array_options['options_verifactu_status'] = '6' : $invoice->array_options['options_verifactu_status'] = '1';
		$invoice->array_options['options_verifactu_error'] = '';
		$invoice->array_options['options_verifactu_error_code'] = '';

		$invoice->insertExtraFields();
	}



	return $record;
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
	//var_dump(htmlentities($body));
	curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
	$res = curl_exec($ch);
	dol_syslog('# RESPUESTA ALTA REGISTRO', LOG_DEBUG);
	dol_syslog($res, LOG_DEBUG);
	// echo "<br>"; echo "<br>"; echo "<br>"; echo "<br>";
	if ($res === false) {
		$error = curl_error($ch);
		$code = curl_errno($ch);
		curl_close($ch);
		throw new Exception('cURL error:' . $error, $code);
	}
	curl_close($ch);
	// var_dump(htmlentities($res));
	$doc = new DOMDocument();
	$doc->loadXML($res . "\n");
	$faults = $doc->getElementsByTagName('Fault');
	//obtengo de la respuesta el tiempo de espara hasta la proxima registro
	$shippingWaitingTimeNodes = $doc->getElementsByTagName('TiempoEsperaEnvio');
	if ($shippingWaitingTimeNodes->length > 0) {
		$now = new DateTimeImmutable(
			'now',
			new DateTimeZone('Europe/Madrid'),
		);
		$shippingWaitingTime = (int) $shippingWaitingTimeNodes->item(0)->nodeValue;
		$newShipment = $now->modify('+' . $shippingWaitingTime . ' seconds');
		$newValueTimestamp = $newShipment->getTimestamp();
		//guardo la fecha en la que puedo realizar el proximo envio
		$result = dolibarr_set_const($db, 'VERIFACTU_NEXT_DELIVERY_ALLOWED', $newValueTimestamp, 'chaine', 0, '', 0);
		if ($result <= 0) {
			dol_syslog('# VERIFACTU SAVE DOLIBARR CONST VERIFACTU_NEXT_DELIVERY_ALLOWED', LOG_DEBUG);
		}
	}
	if ($faults->count() > 0) {
		$fault = $faults[0];
		$code = $fault->getElementsByTagName('faultcode')[0];
		if (in_array($code->nodeValue, array('env:Server', 'sopaenv:Server'), true)) {
			if ($ttl) {
				// Retry on soapenv:Server errors with a limit of 3 attempts.
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
 * @param stdClass    $record         Invoice record.
 * @param array       $issuer         Issuer data with name and id keys.
 * @param array|null  $representative Representative data with name and id keys.
 *
 * @return string                     SOAP XML enveloped record.
 */
function autoverifactuSoapEnvelope($record, $issuer, $representative = null)
{
	$xml = new DOMDocument();
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

	$recordEl = autoverifactuRecordToXML($record, $xml);
	$root->appendChild($recordEl);

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
	//ejecuto la funcion que añadira los diferentes textos
	//legales que deben de ir en el pdf de la factura
	autoverifactuSetLegalText($invoice);

	switch ($invoice->type) {
		case Facture::TYPE_STANDARD:
		case Facture::TYPE_DEPOSIT:
			if (autoverifactuIsPosInvoice($invoice)) {
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
			if (autoverifactuIsPosInvoice($invoice)) {
				// Factura rectificativa simplificada
				$invoiceType = 'R5';
			} else {
				// Factura rectificativa corriente.
				if (!isset($invoice->array_options['options_verifactu_rectification_type'])) {
					 throw new Exception('Error not value options_verifactu_rectification_type', 1);
				}
				$invoiceType = $invoice->array_options['options_verifactu_rectification_type'] ;
			}

			break;
		default:
			$invoiceType = 'F1';
	}


	$record = new stdClass();
	$record->type = $recordType;
	$record->statusVerifactu = $invoice->array_options['options_verifactu_status'];
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

	// calculo el total y obtengo el total calculado para su posterior validación
	$record->factureTotalAmount = number_format(
		$invoice->total_ht + $invoice->total_tva + $invoice->total_localtax1,
		2,
		'.',
		''
	);

	$record->factureTotalTaxAmount = number_format($invoice->total_tva + $invoice->total_localtax1, 2, '.', '');


	$record->factureTtc = number_format($invoice->total_ttc - $invoice->total_localtax2, 2, '.', '');

	$record->recipients = array();

	// If is not simplified, add third party data to the record
	if (!in_array($record->invoiceType, array('F2', 'R5'), true)) {
		$recipient = new stdClass();

		if ($thirdparty->country_code && $thirdparty->country_code !== 'ES') {
			$recipient->name = htmlspecialchars(trim($thirdparty->nom));
			$recipient->country = $thirdparty->country_code;
			if ($thirdparty->tva_intra) {
				//NIF-IVA
				$recipient->type = '02';
				$recipient->value = trim($thirdparty->tva_intra);
			} else {
				//en caso de ser de no ser de españa y que no nos den el NIF intra
				//nos tienen que indicar que tipo de identificación es
				if (
					!in_array($thirdparty->array_options['options_verifactu_identification_thirdparty'], array('03','04','05','06','07'))
				) {
					//no existe tipo de identificacion
					$invoice->errors[] = 'NotTypeIdentificationThirdparty';
					return 0;
				}
				if ($thirdparty->array_options['options_verifactu_identification_thirdparty'] == '03') {
					//Pasaporte (Passport)
					$recipient->type = '03';
					$recipient->value = trim($thirdparty->idprof1);
				} elseif ($thirdparty->array_options['options_verifactu_identification_thirdparty'] == '04') {
					//DOCUMENTO OFICIAL DE IDENTIFICACIÓN EXPEDIDO POR EL PAÍS (Document issued by the country)
					$recipient->type = '04';
					$recipient->value = trim($thirdparty->idprof1);
				} elseif ($thirdparty->array_options['options_verifactu_identification_thirdparty'] == '05') {
					//CERTIFICADO DE RESIDENCIA (Residence Certificate)
					$recipient->type = '05';
					$recipient->value = trim($thirdparty->idprof1);
				} elseif ($thirdparty->array_options['options_verifactu_identification_thirdparty'] == '06') {
					//OTRO DOCUMENTO PROBATORIO (Other supporting document)
					$recipient->type = '06';
					$recipient->value = trim($thirdparty->idprof1);
				} elseif ($thirdparty->array_options['options_verifactu_identification_thirdparty'] == '07') {
					//No censado (Unregistered)
					$recipient->type = '07';
					//  $recipient->value = trim($thirdparty->idprof1);
				}
			}
		} else {
			$recipient->name = htmlspecialchars(trim($thirdparty->nom));
			$recipient->nif = trim($thirdparty->idprof1);
		}
		$record->recipients[0] = $recipient;
	}

	if (
		in_array(
			$record->invoiceType,
			array('R1', 'R2', 'R3', 'R4', 'R5'),
			true
		)
	) {
		if ( $invoice->type === (string) Facture::TYPE_REPLACEMENT) {
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
			dol_syslog('Can not find the source invoice of the corrective invoice #' . $invoice->id, LOG_ERR);
			$invoice->error[] = 'NotFoundSourceInvoiceCorrective';
			return -1;
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



	$tax_total = 0;
	$base_total = 0;
	foreach ($record->breakdown as $line) {
		$equivalenceSurcharge = 0;
		if (isset($line->equivalenceSurcharge)) {
			$equivalenceSurcharge = $line->equivalenceSurcharge->total;
		}


		$tax_total += (float) $line->taxAmount + (float) $equivalenceSurcharge;
		$base_total += (float) $line->baseAmount;
	}

	$record->totalTaxAmount = number_format($tax_total, 2, '.', '');
	$record->totalAmount = number_format(
		$base_total + $tax_total,
		2,
		'.',
		'',
	);



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
		return $reshook;
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


		//etiquetas para subsanacion de errores
		if (in_array($record->statusVerifactu, array('2','4','5'), true)) {
			$valueSubsanacion = 'S';
			$valueRechazoPrevio = 'X';

			if ($record->statusVerifactu === '4') {
				$valueRechazoPrevio = 'N';
			}
			if ($record->statusVerifactu === '5') {
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
			}


			if (!($details->exemptionCode)) {
				$dEl->appendChild($xml->createElement('sum1:TipoImpositivo', $details->taxRate));
			}

			$dEl->appendChild($xml->createElement('sum1:BaseImponibleOimporteNoSujeto', $details->baseAmount));

			if (!($details->exemptionCode)) {
				$dEl->appendChild($xml->createElement('sum1:CuotaRepercutida', $details->taxAmount));
			}

			// Se indicará el recargo de equivalencia y el tipo en caso de existir
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
		//anulacion
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
* Get an invoice and returns its lines as a breakdown details array.
*
* @param Facture     $invoice Target invoice.
*
* @return stdClass[]
*/
function autoverifactuLinesToBreakdown($invoice)
{

	$grouped = array();
	$defaultRegime = getDolGlobalString('AUTOVERIFACTU_DEFAULT_REGIME') ?: '01';
	/*   echo "<pre>";
	var_dump($invoice->lines);
	echo "</pre>";
	echo "<br>";
	 echo "<br>";
	  echo "<br>";*/
	foreach ($invoice->lines as $line) {
		//echo getDolGlobalString('AUTOVERIFACTU_TAX') ."|".$line->array_options['options_verifactu_regime_type']."|". $line->array_options['options_verifactu_operation_type']."|".$line->array_options['options_verifactu_tax_excemption']."|".$line->tva_tx."|".$line->localtax1_tx ;
		$taxType = $line->array_options['options_Verifactu_Tax'] ?: '01';
		$regimeType = $line->array_options['options_verifactu_regime_type'] ?: $defaultRegime;
		$operationType = $line->array_options['options_verifactu_operation_type'] ?: 'S1';
		$exemptionCode = $line->array_options['options_verifactu_tax_excemption'] ?: null;
		$taxRate = number_format((float) $line->tva_tx, 2, '.', '');
		$baseAmount = (float) $line->total_ht;
		$taxAmount = (float) $line->total_tva;
		/*   echo "-----------------------------------";
		echo "<br>";
		var_dump($line->localtax1_tx);
			  echo "<br>";
		var_dump( !NumberIsZero($line->localtax1_tx));
		echo "<br>";
		echo "-----------------------------------";*/
		$equivalenceSurchargeType = !NumberIsZero($line->localtax1_tx) && $line->product_type === '0' ? number_format((float) $line->localtax1_tx, 2, '.', '') : null;
		$equivalenceSurchargeTotal = $line->total_localtax1 ? (float) $line->total_localtax1 : 0.0;
		/*echo "<br>";
		echo "******************";
		echo "<br>";
		echo $equivalenceSurchargeType;
		echo "<br>";
		echo "******************";*/
		//genero in key determinado
		//para cada uno de los
		//diferentes tipos impositivos
		$key = implode('|', array(
			$taxType,
			$regimeType,
			$operationType,
			$exemptionCode ?: '',
			$taxRate,
			$equivalenceSurchargeType ?: null
		));
		/* echo "<br>";
		echo $key;
		echo "<br>";
		echo $line->id;
		echo "<br>";
		echo  var_dump(isset($grouped[$key]));
		echo "<br>";*/


		//si no existe creo lo añado
		if (!isset($grouped[$key])) {
			$grouped[$key] = array(
				'taxType' => $taxType,
				'regimeType' => $regimeType,
				'operationType' => $operationType,
				'exemptionCode' => $exemptionCode,
				'taxRate' => $taxRate,
				'baseAmount' => 0.0,
				'taxAmount' => 0.0,
				'equivalenceSurchargeType' => $equivalenceSurchargeType,
				'equivalenceSurchargeTotal' => 0.0
			);
		}
		//sumo los tipos
		$grouped[$key]['baseAmount'] += $baseAmount;
		$grouped[$key]['taxAmount'] += $taxAmount;
		$grouped[$key]['equivalenceSurchargeTotal'] += $equivalenceSurchargeTotal;
		/*echo "<pre>";
		var_dump($grouped);
		echo "</pre>";*/
	}
	//creo la clase y las guardo en el arrray
	$breakdown = array();
	if (count($grouped) > 12) {
		$invoice->error[] = 'maximumNumberOfTaxRates';
		return -1;
	}
	foreach ($grouped as $group) {
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
			/*echo $group['equivalenceSurchargeType'];
			echo "<br>";
			echo $group['equivalenceSurchargeTotal'];*/
			$details->equivalenceSurcharge = new stdClass();
			$details->equivalenceSurcharge->type = $group['equivalenceSurchargeType'];
			$details->equivalenceSurcharge->total = number_format($group['equivalenceSurchargeTotal'], 2, '.', '');
		}
		$breakdown[] = $details;
	}
	//var_dump($breakdown);
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
		echo '<br><br>';
		$payload  = 'IDEmisorFactura=' . $record->invoiceId->issuerId;
		$payload .= '&NumSerieFactura=' . $record->invoiceId->invoiceNumber;
		$payload .= '&FechaExpedicionFactura=' . $record->invoiceId->issueDate->format('d-m-Y');
		$payload .= '&TipoFactura=' . $record->invoiceType;
		$payload .= '&CuotaTotal=' . $record->totalTaxAmount;
		$payload .= '&ImporteTotal=' . $record->totalAmount;
		$payload .= '&Huella=' . ($record->previousHash ?? '');
		$payload .= '&FechaHoraHusoGenRegistro=' . $record->hashedAt->format('c');
	} elseif ($record->type === 'anulacion') {
		// Otherwise, it's a validated invoice in process to be canceled.
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
 * Verifactu invoice record registration. (Crontab)
 *
 * @param Facture[]  $invoices Array object invoices.
 *
 * @return int                  Return <0 if KO, 0 if skipped, >0 if OK.
*/
function autoverifactuRegisterInvoiceList($invoices)
{
	global $conf, $user;

	if (empty($invoices) || !is_array($invoices)) {
		return 0;
	}

	if (empty($conf->facture->multidir_output[$conf->entity])) {
		dol_syslog('Constant $conf->facture->multidir_output not defined', LOG_ERR);
		return -1;
	}

	$now = new DateTimeImmutable('now', new DateTimeZone('Europe/Madrid'));

	$recordsLote = array();
	$facturasValidasLote = array();

	//recorro las facturas
	foreach ($invoices as $invoice) {
		if ($invoice->type > Facture::TYPE_DEPOSIT) {
			continue;
		}

		$invoice->fetch_thirdparty();
		$thirdparty = $invoice->thirdparty;
		$valid_id = $thirdparty->id_prof_check(1, $thirdparty);

		if (!autoverifactuIsPosInvoice($invoice) && $valid_id <= 0 && !$thirdparty->tva_intra) {
			dol_syslog('Skip invoice #' . $invoice->id . ' due to thirdparty without valid idprof1');
			continue;
		}

		if (!count($invoice->lines)) {
			dol_syslog('Skip invoice #' . $invoice->id . ' due to no lines');
			continue;
		}

		$recordType = 'alta';
		$record = autoverifactuInvoiceToRecord($invoice, $recordType);

		if ($record) {
			$recordsLote[] = $record;
			$facturasValidasLote[] = $invoice; // Guardamos la correspondencia
		}
	}

	if (empty($recordsLote)) {
		return 0;
	}

	global $mysoc;

	$issuer = array(
		'name' => $mysoc->nom,
		'idprof1' => $mysoc->idprof1,
	);

	try {
		//genero el xml para envios masivos
		$envelope = autoverifactuSoapEnvelopeMass($recordsLote, $issuer);


		$resDOM = autoverifactuSoapRequest($envelope);

		$exitos = 0;
		$registroFacturaNodes = $resDOM->getElementsByTagName('RespuestaLinea');

		//analizamos uno a uno las repuetas para cada factura
		foreach ($facturasValidasLote as $index => $invoice) {
			$nodoRespuesta = $registroFacturaNodes->item($index);
			$status = $nodoRespuesta ? $nodoRespuesta->getElementsByTagName('EstadoRegistro')[0]->nodeValue : 'Incorrecto';
			$invoiceref = dol_sanitizeFileName($invoice->ref);
			$dir = $conf->facture->multidir_output[$invoice->entity ?? $conf->entity] . '/' . $invoiceref;
			if (!file_exists($dir)) dol_mkdir($dir);

			if ($status === 'Correcto' || $status === 'AceptadoConErrores') {
				// Guardar localmente el XML individual o masivo por cumplimiento normativo de almacenamiento
				if ($action === 'BILL_VALIDATE') {
					$file = $dir . '/' . $invoiceref . '-alta-' . $now->format('Y-m-d_H-i-s') . '.xml';
					$hidden = $dir . '/.verifactu-alta-' . $now->format('Y-m-d_H-i-s') . '.xml';
				} elseif ($action === 'verifactuResend') {
					$file = $dir . '/' . $invoiceref . '-subsanacion-' . $now->format('Y-m-d_H-i-s') . '.xml';
					$hidden = $dir . '/.verifactu-subsanacion-' . $now->format('Y-m-d_H-i-s') . '.xml';
				} else {
					$file = $dir . '/' . $invoiceref . '-anulacion-' . $now->format('Y-m-d_H-i-s') . '.xml';
					$hidden = $dir . '/.verifactu-anulacion-' . $now->format('Y-m-d_H-i-s') . '.xml';
				}

				$file = $dir . '/' . $invoiceref .  '-alta-' . $now->format('Y-m-d_H-i-s') . 'xml' ;
				$result = file_put_contents($file, $envelope);
				$result = $result && file_put_contents($hidden, $envelope);
				if ($result) {
					throw new Exception('Unable to store XML invoice record', 500);
				}
				// Actualizar huellas y estados de éxito (1 = Correcto, 4 = Aceptado con Errores)
				$invoice->array_options['options_verifactu_hash'] = $recordsLote[$index]->hash;
				$invoice->array_options['options_verifactu_tms'] = $recordsLote[$index]->hashedAt->getTimestamp();

				if ($status === 'AceptadoConErrores') {
					$errMessage = $nodoRespuesta->getElementsByTagName('DescripcionErrorRegistro')[0]->nodeValue;
					$invoice->array_options['options_verifactu_error'] = $errMessage;
					$errCode = $nodoRespuesta->getElementsByTagName('CodigoErrorRegistro')[0]->nodeValue;
					$invoice->array_options['options_verifactu_error_code'] = $errCode;

					if ( in_array($invoice->array_options['options_verifactu_status'], array('4','5'), true) ) {
						  //en caso de existir subsanaciones o estar registrada originalmente
						$invoice->array_options['options_verifactu_status'] = '5';
					} else {
						//en caso de no estar registrada originalmente
						$invoice->array_options['options_verifactu_status'] = '4';
					}
				} else {
					//correcto
					$invoice->array_options['options_verifactu_error'] = '';
					$invoice->array_options['options_verifactu_error_code'] = '';
					$invoice->array_options['options_verifactu_status'] = '1';
				}
				$invoice->insertExtraFields();
				$exitos++;
			} else {
				// Estado 'Incorrecto' (La factura individual ha sido rechazada estructuralmente)
				$errMessage = $nodoRespuesta ? $nodoRespuesta->getElementsByTagName('DescripcionErrorRegistro')[0]->nodeValue : 'Error estructural en lote';
				$errCode = $nodoRespuesta->getElementsByTagName('CodigoErrorRegistro')[0]->nodeValue;
				if (in_array($invoice->array_options['options_verifactu_status'], array('0','2'), true)) {
					  //No esta regsitrado(Pendiente de envio o rechazado)
					$invoice->array_options['options_verifactu_status'] = '2'; // 2 = Rechazado
				} else {
					//esta regsitrado (Registrado correctamente pero con errores o Registrado correctamente pero con errores múltiples veces)
					$invoice->array_options['options_verifactu_status'] = '5';
				}
				$invoice->array_options['options_verifactu_error'] = $errMessage;
				$invoice->array_options['options_verifactu_error_code'] = $errCode;
				$invoice->insertExtraFields();
				dol_syslog('Factura #' . $invoice->id . ' rechazada por AEAT: ' . $errMessage, LOG_ERR);
				$invoice->ref = $record->invoiceId->invoiceNumber;
				$result = $invoice->update($user);
				if ($result <= 0) {
					throw new Exception($invoice->error, 1);
				}
			}
		}

		return $exitos == count($facturasValidasLote);
	} catch (Exception $e) {
		dol_syslog('Critical error in Veri*Factu mass mailing:' . $e->getMessage(), LOG_ERR);
		return -1;
	}
}

/**
 * Gets an verifactu list invoice record and returns it inside a SOAP envelope.
 *
 * @param stdClass[]    $records        List invoice record.
 * @param array         $issuer         Issuer data with name and id keys.
 * @param array|null    $representative Representative data with name and id keys.
 *
 * @return string                       SOAP XML enveloped record.
 */
function autoverifactuSoapEnvelopeMass($records, $issuer, $representative = null)
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

	// Contenedor principal ÚNICO para todo el lote
	$root = $xml->createElement('sum:RegFactuSistemaFacturacion');
	$body->appendChild($root);

	// Cabecera ÚNICA del emisor
	$regHeaderEl = $xml->createElement('sum:Cabecera');
	$root->appendChild($regHeaderEl);

	$issuerEl = $xml->createElement('sum1:ObligadoEmision');
	$regHeaderEl->appendChild($issuerEl);

	$issuerEl->appendChild($xml->createElement('sum1:NombreRazon', htmlspecialchars($issuer['name'])));
	$issuerEl->appendChild($xml->createElement('sum1:NIF', htmlspecialchars($issuer['idprof1'])));


	foreach ($records as $record) {
		$recordEl = autoverifactuRecordToXML($record, $xml);
		$root->appendChild($recordEl);
	}

	$xml->appendChild($envelope);
	return $xml->saveXML($envelope);
}

/**
 * Esta funcion nos indica si un valor es 0 o esta vacio true si es cero o vacio false si tiene un valor != 0
 *
 * @param mixed $valor Valor a evaluar.
 *
 * @return bool
 */
function NumberIsZero($valor)
{
	if (!isset($valor)) {
		return true;
	}

	// Comprobamos si el texto es un 0 con o sin decimales
	if (preg_match('/^0(\.0+)?$/', $valor)) {
		// Verificamos si realmente contiene un punto antes de hacer el explode
		if (strpos($valor, '.') !== false) {
			$cantidadDecimales = strlen(explode('.', $valor)[1]);
		} else {
			$cantidadDecimales = 0; // Si no hay punto, tiene 0 decimales
		}

		return true;
	}

	return false;
}

/**
 * Añade los textos legales al pdf dependiendo de las caractericticas de la factura.
 *
 * @param Facture $invoice Facture object.
 *
 * @return Facture
 *
 * @throws Exception
 */
function autoverifactuSetLegalText($invoice)
{
	if (!isset($invoice->array_options['options_verifactu_pdfLegalText'])) {
		$invoice->array_options['options_verifactu_pdfLegalText'] = [];
	} elseif (strlen($invoice->array_options['options_verifactu_pdfLegalText']) > 0) {
		$invoice->array_options['options_verifactu_pdfLegalText'] = explode(',', $invoice->array_options['options_verifactu_pdfLegalText']);
	}

	/* IRPF */
	if (!NumberIsZero($invoice->total_localtax2)) {
		$invoice->array_options['options_verifactu_pdfLegalText'][] = 'VerifactuLegalTextIRPF';
	}

	$SujetoPasivo = true;
	if (in_array('VerifactuLegalTextSujetoPasivo', $invoice->array_options['options_verifactu_pdfLegalText'])) {
		$SujetoPasivo = false;
	}

	$criterioCaja = true;
	if (in_array('VerifactuLegalTextCriterioCaja', $invoice->array_options['options_verifactu_pdfLegalText'])) {
		$criterioCaja = false;
	}

	$excemptionIVA = [];
	if (in_array('VerifactuLegalTextTaxExcemptionE1', $invoice->array_options['options_verifactu_pdfLegalText'])) {
		$excemptionIVA[] = 'E1';
	}

	if (in_array('VerifactuLegalTextTaxExcemptionE2', $invoice->array_options['options_verifactu_pdfLegalText'])) {
		$excemptionIVA[] = 'E2';
	}

	if (in_array('VerifactuLegalTextTaxExcemptionE3', $invoice->array_options['options_verifactu_pdfLegalText'])) {
		$excemptionIVA[] = 'E3';
	}

	if (in_array('VerifactuLegalTextTaxExcemptionE4', $invoice->array_options['options_verifactu_pdfLegalText'])) {
		$excemptionIVA[] = 'E4';
	}

	if (in_array('VerifactuLegalTextTaxExcemptionE5', $invoice->array_options['options_verifactu_pdfLegalText'])) {
		$excemptionIVA[] = 'E5';
	}

	if (in_array('VerifactuLegalTextTaxExcemptionE6', $invoice->array_options['options_verifactu_pdfLegalText'])) {
		$excemptionIVA[] = 'E6';
	}

	$rebu = true;
	if (in_array('VerifactuLegalTextREBU', $invoice->array_options['options_verifactu_pdfLegalText'])) {
		$rebu = false;
	}

	$agenciaViajes = true;
	if (in_array('VerifactuLegalTextAgenciaViajes', $invoice->array_options['options_verifactu_pdfLegalText'])) {
		$agenciaViajes = false;
	}

	foreach ($invoice->lines as $line) {
		//exceciones IVA
		if ($line->array_options['options_verifactu_tax_excemption'] ) {
			switch ($line->array_options['options_verifactu_tax_excemption']) {
				case 'E1':
					if (!in_array('E1', $excemptionIVA)) {
						$invoice->array_options['options_verifactu_pdfLegalText'][] = 'VerifactuLegalTextTaxExcemptionE1';
						$excemptionIVA[] = 'E1';
					}

					break;
				case 'E2':
					if (!in_array('E2', $excemptionIVA)) {
						$invoice->array_options['options_verifactu_pdfLegalText'][] = 'VerifactuLegalTextTaxExcemptionE2';
						$excemptionIVA[] = 'E2';
					}
					break;
				case 'E3':
					if (!in_array('E3', $excemptionIVA)) {
						$invoice->array_options['options_verifactu_pdfLegalText'][] = 'VerifactuLegalTextTaxExcemptionE3';
						$excemptionIVA[] = 'E3';
					}
					break;
				case 'E4':
					if (!in_array('E4', $excemptionIVA)) {
						$invoice->array_options['options_verifactu_pdfLegalText'][] = 'VerifactuLegalTextTaxExcemptionE4';
						$excemptionIVA[] = 'E4';
					}
					break;
				case 'E5':
					if (!in_array('E5', $excemptionIVA)) {
						$invoice->array_options['options_verifactu_pdfLegalText'][] = 'VerifactuLegalTextTaxExcemptionE5';
						$excemptionIVA[] = 'E5';
					}

					break;
				case 'E6':
					if (!in_array('E6', $excemptionIVA)) {
						$invoice->array_options['options_verifactu_pdfLegalText'][] = 'VerifactuLegalTextTaxExcemptionE6';
						$excemptionIVA[] = 'E6';
					}
					break;
				default:
					throw new Exception('Error  options_verifactu_error not valid value', 1);
					break;
			}
		}

		//Inversión del Sujeto Pasivo
		if ($line->array_options['options_verifactu_operation_type'] === 'S2' && $SujetoPasivo) {
			$invoice->array_options['options_verifactu_pdfLegalText'][] = 'VerifactuLegalTextSujetoPasivo';
			$SujetoPasivo = false;
		}
		//regimen de criterio de caja
		if ($line->array_options['options_verifactu_regime_type'] === '07' && $criterioCaja) {
			$invoice->array_options['options_verifactu_pdfLegalText'][] = 'VerifactuLegalTextCriterioCaja';
			$criterioCaja = false;
		}

		if ($line->array_options['options_verifactu_regime_type'] === '03' && $rebu) {
			$invoice->array_options['options_verifactu_pdfLegalText'][] = 'VerifactuLegalTextREBU';
			$rebu = false;
		}
		if ($line->array_options['options_verifactu_regime_type'] === '09' && $agenciaViajes) {
			$invoice->array_options['options_verifactu_pdfLegalText'][] = 'VerifactuLegalTextAgenciaViajes';
			$agenciaViajes = false;
		}
	}

	$invoice->array_options['options_verifactu_pdfLegalText'] = implode(',', $invoice->array_options['options_verifactu_pdfLegalText']);

	$result = $invoice->insertExtraFields();
	if ($result < 0) {
		throw new Exception('Error  save options_verifactu_pdfLegalText ', 1);
	}
}

/**
 * Checks if the last waiting time reported by the Veri*Factu API responses has been exceeded.
 *
 * @return bool
 */
function autoverifactuIsDeliveryAllowed()
{
	$now = new DateTimeImmutable('now', new DateTimeZone('Europe/Madrid'));
	return $now->getTimestamp() >= getDolGlobalString('VERIFACTU_NEXT_DELIVERY_ALLOWED', '0');
}