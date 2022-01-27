<?php
	/*  portions copyright by... zen-cart.com

		developed and brought to you by proseLA
		https://rossroberts.com

		released under GPU
		https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0

	   04/2021  project: authorizenet_cim; file: authorizenet_cof.php; version 2.3.1
	*/
	require_once (IS_ADMIN_FLAG === true ? DIR_FS_CATALOG_MODULES : DIR_WS_MODULES) . 'payment/authorizenet_cim.php';

	class authorizenet_cof extends authorizenet_cim
	{
		var $code, $title, $description, $enabled;

		function __construct()
		{
			global $order;

			require_once (IS_ADMIN_FLAG === true ? DIR_FS_CATALOG_LANGUAGES : DIR_WS_LANGUAGES) . $_SESSION['language'] . '/modules/payment/authorizenet_cim.php';

			parent::__construct();

			$this->code = 'authorizenet_cof';
			$this->title = MODULE_PAYMENT_SAVED_CC_TEXT_TITLE;
			if (IS_ADMIN_FLAG !== true) {
				$this->title = MODULE_PAYMENT_SAVED_CC_TEXT_TITLE_STORE;
			}
			// this module is entirely dependent on the authorizenet_cim module.  if that is not enabled.  neither is this.
			$this->enabled = ((MODULE_PAYMENT_AUTHORIZENET_CIM_STATUS == 'True') ? true : false);
			if ($this->enabled == true) {
				$this->description = MODULE_PAYMENT_SAVED_CC_TEXT_DESCRIPTION . ' version ' . $this->version; // Descriptive Info about module in Admin
				$this->sort_order = 1; // Sort Order of this payment option on the customer payment page
			} else {
				$this->title .= ' <span class="alert">(to enable; enable authorizenet CIM module)</span>';
			}
		}

		function javascript_validation()
		{
			$js = '  if (payment_value == "' . $this->code . '") {' . "\n";
			if (MODULE_PAYMENT_AUTHORIZENET_COF_USE_CVV == 'True') {
				$js .= '    var cc_cvv = document.checkout_payment.authorizenet_cof_cc_cvv.value;' . "\n";
			}
			if (MODULE_PAYMENT_AUTHORIZENET_COF_USE_CVV == 'True') {
				$js .= '    if (cc_cvv == "" || cc_cvv.length < "3" || cc_cvv.length > "4") {' . "\n" .
					'      error_message = error_message + "' . MODULE_PAYMENT_AUTHORIZENET_COF_TEXT_JS_CC_CVV . '";' . "\n" .
					'      error = 1;' . "\n" .
					'    }' . "\n";
			}
			$js .= '  }' . "\n";
			return $js;
		}

		function selection()
		{
			$onFocus = ' onfocus="methodSelect(\'pmt-' . $this->code . '\')"';
			$all = false;
			if ((isset($_SESSION['emp_admin_login']) && $_SESSION['emp_admin_login'] == true)) {
				$all = true;
			}
			$cards = $this->getCustomerCardsAsArray($_SESSION['customer_id'], $all);

			$selection = [
				'id' => $this->code,
				'module' => 'Stored Credit Card',
				'fields' => [
					[
						'title' => 'Saved Credit Card',
						'field' => zen_draw_pull_down_menu('saved_cc_index', $cards, '', $onFocus),
						'tag' => 'select-saved_cc_index'
					]
				]
			];
			if (MODULE_PAYMENT_AUTHORIZENET_COF_USE_CVV == 'True') {
				$selection['fields'][] = [
					'title' => MODULE_PAYMENT_AUTHORIZENET_CIM_TEXT_CVV,
					'field' => zen_draw_input_field('authorizenet_cof_cc_cvv', '',
							'size="4" maxlength="4" class="cvv_input"' . ' id="' . $this->code . '-cc-cvv"' . $onFocus) . ' ' . '<a href="javascript:popupWindow(\'' . zen_href_link(FILENAME_POPUP_CVV_HELP) . '\')">' . MODULE_PAYMENT_AUTHORIZENET_CIM_TEXT_POPUP_CVV_LINK . '</a>',
					'tag' => $this->code . '-cc-cvv'
				];
			}

			if (!empty($cards)) {
				$_SESSION['saved_cc'] = 'yes';
				return $selection;
			} else {
				return false;
			}
		}

		function pre_confirmation_check()
		{
			global $messageStack;
			//$_SESSION['saved_cc_index'] = $_POST['saved_cc_index'];

			if (MODULE_PAYMENT_AUTHORIZENET_COF_USE_CVV == 'True') {
				$length = strlen($_POST['authorizenet_cof_cc_cvv']);
				if ($length < 3 || $length > 4) {
					$payment_error_return = 'payment_error=' . $this->code . '&authorizenet_cof_cc_cvv=' . urlencode($_POST['authorizenet_cof_cc_cvv']);
					$messageStack->add_session('checkout_payment',
						MODULE_PAYMENT_AUTHORIZENET_COF_TEXT_JS_CC_CVV . '<!-- [' . $this->code . '] -->', 'error');
					zen_redirect(zen_href_link(FILENAME_CHECKOUT_PAYMENT, $payment_error_return, 'SSL', true, false));
				}
			}
		}

		function confirmation()
		{
			return ['title' => MODULE_PAYMENT_SAVED_CC_TEXT_DESCRIPTION];
		}

		function process_button()
		{
			$process_button_string = zen_draw_hidden_field('saved_cc_index', $_POST['saved_cc_index']);
			if (MODULE_PAYMENT_AUTHORIZENET_COF_USE_CVV == 'True') {
				$process_button_string .= zen_draw_hidden_field('cc_cvv', $_POST['authorizenet_cof_cc_cvv']);
			}
			return $process_button_string;
		}

		function before_process()
		{
			global $messageStack, $customerID, $order;

			$customerID = $_SESSION['customer_id'];

			$valid_payment_profile = $this->checkValidPaymentProfile($customerID, $_POST['saved_cc_index']);

			if (!$valid_payment_profile['valid']) {
				$messageStack->add_session('checkout_payment',
					'There was a problem with that card.  Please select a different card!', 'error');
				trigger_error('the card index does not correspond to the right customer!');
				zen_redirect(zen_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL', true, false));
			}

			$order->info['cc_number'] = str_pad($valid_payment_profile['last_four'], CC_NUMBER_MIN_LENGTH, "X",
				STR_PAD_LEFT);
			$order->info['cc_expires'] = substr($valid_payment_profile['exp_date'],
					-2) . substr($valid_payment_profile['exp_date'], 2, 2);

			$customerProfileId = $this->getCustomerProfile($customerID);
			$this->setParameter('customerProfileId', $customerProfileId);
			$this->setParameter('customerPaymentProfileId', $valid_payment_profile['payment_profile_id']);

			$this->response = $this->chargeCustomerProfile($customerProfileId,
				$valid_payment_profile['payment_profile_id']);

			$this->addErrorsMessageStack('Customer Payment Transaction');
		}

		function get_error()
		{
			return false;
		}

		function check()
		{
			global $db;
			if (!isset($this->_check)) {
				$check_query = $db->Execute("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_AUTHORIZENET_CIM_STATUS'");
				$this->_check = $check_query->RecordCount();
			}
			if ($this->_check > 0) {
				$this->install();
			} // install any missing keys

			return $this->_check;
		}

		function install()
		{
			global $db;

			if (!defined('MODULE_PAYMENT_AUTHORIZENET_COF_USE_CVV')) {
				$db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Request CVV Number', 'MODULE_PAYMENT_AUTHORIZENET_COF_USE_CVV', 'True', 'Do you want to ask for the customer for the card\'s CVV number when using the card on file option? If set to false, ensure that on your merchant dashboard at authorize.net, you do NOT have card code selected as required.  See https://developer.authorize.net/api/reference/responseCodes.html?code=33', '6', '11', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");
			}
		}

		function remove()
		{
			global $db;
		}

		function keys()
		{
			return ['MODULE_PAYMENT_AUTHORIZENET_COF_USE_CVV',];
		}
	}
