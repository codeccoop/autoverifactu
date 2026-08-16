<?php

require_once DOL_DOCUMENT_ROOT . '/custom/autoverifactu/lib/verifactu.lib.php';

class VerifactuQueue
{
	private $db;

	public function __construct($db)
	{
		$this->db = $db;
	}

	/*
	Funcion para obtener las facturas con el estado en cola (3) cuando se pueda enviar
	a la api una peticion porque el tiempo de espera ya a pasado.
	*/
	public function processMassiveQueue()
	{
		global $conf, $langs;
		$langs->load('autoverifactu@autoverifactu');
		$now = new DateTimeImmutable('now', new DateTimeZone('Europe/Madrid'));
		if ($now->getTimestamp() < getDolGlobalString('VERIFACTU_NEXT_DELIVERY_ALLOWED', '0')) {
			//sigue en espera
			return 0;
		} else {
			//se puede enviar
			include_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';
			//obtengo las facturas en cola max 30.
			$sql = 'SELECT fk_object FROM ' . MAIN_DB_PREFIX . "facture_extrafields WHERE verifactu_status ='3'  LIMIT 30";
			$resql = $this->db->query($sql);
			if ($resql && $this->db->num_rows($resql) > 0) {
				//en caso de existir facturas en cola genero un array con las facturas
				$listInvoice = array();
				while ($obj = $this->db->fetch_object($resql)) {
					$invoice = new Facture($this->db);
					if ($invoice->fetch($obj->fk_object) > 0) {
						$invoice->fetch_optionals();
						 $listInvoice[] = $invoice;
					}
				}
				//genero el xml y las envio
				$result = autoverifactuRegisterInvoiceList($listInvoice);
				if ($result < 0) {
					if (!empty($object->errors)) {
						$this->errors = array_merge($this->errors, (array) $object->errors);
					} else {
						$this->errors[] = $langs->trans('RecordCreationFail');
					}
				}
				return $result;
			}
			return 0;
		}
	}
}
