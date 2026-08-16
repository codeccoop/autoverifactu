<?php
/* Copyright (C) 2022       Laurent Destailleur     <eldy@users.sourceforge.net>
 * Copyright (C) 2024       Frédéric France         <frederic.france@free.fr>
 * Copyright (C) 2026       Yamil Esteban           <development@oyr.es>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file   htdocs/custom/autoverifactu/class/verufactuqueue.class.php
 * \inroup autoverifactu
 * \brief  Invoice record queue handler
 */

require_once DOL_DOCUMENT_ROOT . '/custom/autoverifactu/lib/verifactu.lib.php';

/**
 * Class VerifactuQueue.
 */
class VerifactuQueue
{
	/**
	 * Handles the dolibarr's database client singleton object.
	 *
	 * @var DoliDBMysqli
	 */
	private $db;

	/**
	 * Handles a list of queue processing errors.
	 *
	 * @var string[]
	 */
	public $errors = array();

	/**
	 * Class constructor. Stores the $db reference as an internal attribute.
	 *
	 * @param DoliDBMysqli $db Database client instance.
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Loads pending invoices and performs a batch submit of invoice records to the Verii*Factu SOAP API.
	 *
	 * @return int
	 */
	public function processMassiveQueue()
	{
		global $conf, $langs;

		$langs->load('autoverifactu@autoverifactu');

		if (!autoverifactuIsDeliveryAllowed()) {
			return -1;
		}

		include_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';

		// Obtengo las facturas en cola max 30.
		$sql = 'SELECT fk_object FROM ' . MAIN_DB_PREFIX . "facture_extrafields WHERE verifactu_status ='3'  LIMIT 30";

		$resql = $this->db->query($sql);

		if ($resql && $this->db->num_rows($resql) > 0) {
			// En caso de existir facturas en cola genero un array con las facturas.
			$invoices = array();
			while ($obj = $this->db->fetch_object($resql)) {
				$invoice = new Facture($this->db);

				if ($invoice->fetch($obj->fk_object) > 0) {
					$invoice->fetch_optionals();
					$invoices[] = $invoice;
				}
			}

			// genero el xml y las envio
			$result = autoverifactuRegisterInvoiceList($invoices);

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
