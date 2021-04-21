<?php
	/*  portions copyright by... zen-cart.com

		developed and brought to you by proseLA
		https://rossroberts.com

		released under GPU
		https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0

	   04/2021  project: authorizenet_cim; file: authnet_order.php; version 2.3.0
	*/

	class authnet_order
	{
		var $payment, $refund, $payment_key, $payment_key_array;
		var $oID, $cID, $order_total, $amount_applied, $balance_due, $status, $status_date;

		// instantiates the class and gathers existing data
		function __construct($orders_id)
		{
			$this->payment = [];
			$this->refund = [];
			$this->payment_key = [];
			$this->payment_key_array = [];

			$this->oID = (int)$orders_id;   // now you have the order_id whenever you need it

			if (IS_ADMIN_FLAG) {
			include DIR_FS_ADMIN . DIR_WS_LANGUAGES . $_SESSION['language'] . '/authnet_order.php';
			}
			$this->start();
		}

		private function start()
		{
			global $db, $zco_notifier;

			$order_query = $db->Execute("select * from " . TABLE_ORDERS . " where orders_id = '" . $this->oID . "'");
			if ($order_query->EOF) {
				return;
			}
			$this->cID = $order_query->fields['customers_id'];
			$this->order_total = $this->num_2_dec($order_query->fields['order_total']);
			$this->status = $order_query->fields['orders_status'];

			// build an array to translate the payment_type codes stored in so_payments
			$payment_key_query = $db->Execute("select * from " . TABLE_CIM_PAYMENT_TYPES . "
                                       where language_id = '" . $_SESSION['languages_id'] . "'
                                       order by payment_type_full asc");
			while (!$payment_key_query->EOF) {
				// this array is used by the full_type() function
				$this->payment_key_array[$payment_key_query->fields['payment_type_code']] = $payment_key_query->fields['payment_type_full'];

				// and this one can be used to build dropdown menus
				$this->payment_key[] = [
					'id' => $payment_key_query->fields['payment_type_code'],
					'text' => $payment_key_query->fields['payment_type_full']
				];
				$payment_key_query->MoveNext();
			}

			// get all payments not tied to a purchase order
			$payments_query = $db->Execute("select * from " . TABLE_CIM_PAYMENTS . "
                                    where orders_id = '" . $this->oID . "'
                                    order by date_posted asc");

			if (!$payments_query->EOF) {
				while (!$payments_query->EOF) {
					$this->payment[] = [
						'index' => $payments_query->fields['payment_id'],
						'number' => $payments_query->fields['transaction_id'],
						'name' => $payments_query->fields['payment_name'],
						'amount' => $payments_query->fields['payment_amount'],
						'refund_amount' => $payments_query->fields['refund_amount'],
						'type' => $payments_query->fields['payment_type'],
						'posted' => $payments_query->fields['date_posted'],
						'captured' => $payments_query->fields['last_modified'],
						'approval_code' => $payments_query->fields['approval_code'],
						'status' => $payments_query->fields['status'],
						'payment_profile_id' => $payments_query->fields['payment_profile_id'],
					];
					$payments_query->MoveNext();
				}
			}

			// get any refunds
			if ($this->payment) {   // gotta have payments in order to refund them
				$refunds_query = $db->Execute("select * from " . TABLE_CIM_REFUNDS . "
                                     where orders_id = '" . $this->oID . "'
                                     order by date_posted asc");

				if (!$refunds_query->EOF) {
					while (!$refunds_query->EOF) {
						$this->refund[] = [
							'index' => $refunds_query->fields['refund_id'],
							'payment' => $refunds_query->fields['payment_id'],
							'number' => $refunds_query->fields['transaction_id'],
							'name' => $refunds_query->fields['refund_name'],
							'amount' => $refunds_query->fields['refund_amount'],
							'type' => $refunds_query->fields['refund_type'],
							'payment_number' => $refunds_query->fields['payment_trans_id'],
							'posted' => $refunds_query->fields['date_posted'],
							'approval_code' => $refunds_query->fields['approval_code']
						];
						$refunds_query->MoveNext();
					}
				}
			}

			$zco_notifier->notify('NOTIFY_ADMIN_AUTHNET_PAYMENTS', '', $this);

			// calculate and store the order total, amount applied, & balance due for the order
			// add individual payments if they exists
			if ($this->payment) {
				for ($i = 0; $i < sizeof($this->payment); $i++) {
					$this->amount_applied += $this->payment[$i]['amount'];
				}
			}

			// now subtract out any refunds if they exist
			if ($this->refund) {
				for ($i = 0; $i < sizeof($this->refund); $i++) {
					$this->amount_applied -= $this->refund[$i]['amount'];
				}
			}

			// subtract from the order total to get the balance due
			$this->balance_due = $this->num_2_dec($this->order_total - $this->amount_applied);

		}

		function num_2_dec($number)
		{
			return round((float)$number, 2);
		}

		function button_refund($payment_mode, $index)
		{
			echo '&nbsp;<a href="javascript:cimpopupWindow(\'' .
				zen_href_link(FILENAME_AUTHNET_PAYMENTS,
					'oID=' . $this->oID . '&payment_mode=' . $payment_mode . '&action=refund' . '&index=' . $index,
					'NONSSL') . '\', \'scrollbars=yes,resizable=yes,width=100,height=1000,screenX=150,screenY=100,top=100,left=150\')"' .
				'class="btn btn-danger btn-sm" role="button" >' . BUTTON_REFUND . '</a>';
		}

		function button_capture($index)
		{
			echo '&nbsp;<a href="javascript:cimpopupWindow(\'' .
				zen_href_link(FILENAME_AUTHNET_PAYMENTS,
					'oID=' . $this->oID . '&index=' . $index . '&action=capture',
					'NONSSL') . '\', \'scrollbars=yes,resizable=yes,width=100,height=1000,screenX=150,screenY=100,top=100,left=150\')"' .
				'class="btn btn-success btn-sm" role="button" >' . BUTTON_CAPTURE . '</a>';
		}

		// translates payment type codes into full text

		function button_new_funds($index)
		{
			echo '&nbsp;<a href="javascript:cimpopupWindow(\'' .
				zen_href_link(FILENAME_AUTHNET_PAYMENTS,
					'oID=' . $this->oID . '&index=' . $index . '&action=more_money&ccindex=' . ($_POST['ccindex'] ?? ''),
					'NONSSL') . '\', \'scrollbars=yes,resizable=yes,width=100,height=1000,screenX=150,screenY=100,top=100,left=150\')"' .
				'class="btn btn-primary btn-sm" role="button" type="submit">' . BUTTON_NEW_FUNDS . '</a>';
		}

		function full_type($code)
		{
			if (array_key_exists($code, $this->payment_key_array)) {
				$full_text = $this->payment_key_array[$code];
			} else {
				$full_text = $code;
			}
			return $full_text;
		}

		function getPaymentIndex($index)
		{
			$return = 0;
			if (!is_array($this->payment)) {
				return 0;
			}
			$last_index = sizeof($this->payment) - 1;
			for ($i = $last_index; $i > -1; $i--) {
				if ($index == $this->payment[$i]['index']) {
					$return = $i;
					break;
				}
			}
			return $return;
		}

		function getCustCardIndex($payment_profile_id, $all = false)
		{
			global $db;
			$sql = "SELECT * FROM " . TABLE_CUSTOMERS_CC . " WHERE  payment_profile_id = :ppID";
			if (!$all) {
				$sql .= " and enabled = 'Y'";
			}
			$sql = $db->bindVars($sql, ':ppID', $payment_profile_id, 'integer');
			$cardIndex = $db->Execute($sql);
			if ($cardIndex->RecordCount() == 1) {
				return $cardIndex->fields['index_id'];
			}
			return false;
		}
	}
