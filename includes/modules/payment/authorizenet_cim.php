<?php
/*  portions copyright by... zen-cart.com

    developed and brought to you by proseLA
    https://rossroberts.com

    released under GPU
    https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0

   04/2020  project: authorizenet_cim; file: authorizenet_cim.php; version 2.0
*/
    
    if (!file_exists($sdk_loader = DIR_FS_CATALOG . 'includes/modules/payment/authorizenet/authorizenet-sdk/autoload.php')) {
        return false;
    }
    
    require $sdk_loader;

use net\authorize\api\contract\v1 as AnetAPI;
use net\authorize\api\controller as AnetController;

class authorizenet_cim extends base
    {
        
        var $code, $title, $description, $enabled, $authorize = '';
        
        var $version = '2.0.1';
        var $params = array();
        var $success = false;
        var $error = true;
        var $response;
        var $xml;
        var $update = false;
        var $text;
        var $customerProfileId;
        var $customerPaymentProfileId;
        var $approvalCode;
        var $transID;
    
        var $errorMessages = array();
    
        // zen-cart base payment functions

        function __construct()
        {
            global $order, $messageStack;
            $this->code = 'authorizenet_cim';
            $this->enabled = (defined('MODULE_PAYMENT_AUTHORIZENET_CIM_STATUS') && MODULE_PAYMENT_AUTHORIZENET_CIM_STATUS == 'True');
            $this->sort_order = defined('MODULE_PAYMENT_AUTHORIZENET_CIM_SORT_ORDER') ? MODULE_PAYMENT_AUTHORIZENET_CIM_SORT_ORDER : null;
            if (($this->enabled) && IS_ADMIN_FLAG === true) {
                $this->title = MODULE_PAYMENT_AUTHORIZENET_CIM_TEXT_CATALOG_TITLE;
                if (MODULE_PAYMENT_AUTHORIZENET_CIM_STATUS == 'True' && (MODULE_PAYMENT_AUTHORIZENET_CIM_LOGIN == 'testing' || MODULE_PAYMENT_AUTHORIZENET_CIM_TXNKEY == 'Test')) {
                    $this->title .= '<span class="alert"> (Not Configured)</span>';
                } elseif (MODULE_PAYMENT_AUTHORIZENET_CIM_TESTMODE == 'Test') {
                    $this->title .= '<span class="alert"> (in Testing mode)</span>';
                }
                if ($this->enabled && !function_exists('curl_init')) {
                    $messageStack->add_session(MODULE_PAYMENT_AUTHORIZENET_CIM_TEXT_ERROR_CURL_NOT_FOUND, 'error');
                }
            } elseif (IS_ADMIN_FLAG === true) {
                $this->title = MODULE_PAYMENT_AUTHORIZENET_CIM_TEXT_CATALOG_TITLE . ' Authorize.net (CIM)';
            } else {
                $this->title = MODULE_PAYMENT_AUTHORIZENET_CIM_TEXT_CATALOG_TITLE;
            }
            $this->description = 'Authorizenet API using CIM: version ' . $this->version . MODULE_PAYMENT_AUTHORIZENET_CIM_TEXT_DESCRIPTION;
            $this->form_action_url = zen_href_link(FILENAME_CHECKOUT_PROCESS, '', 'SSL', false);
            $this->order_status = (int)DEFAULT_ORDERS_STATUS_ID;
            if (defined('MODULE_PAYMENT_AUTHORIZENET_CIM_ORDER_STATUS_ID') && (int)MODULE_PAYMENT_AUTHORIZENET_CIM_ORDER_STATUS_ID > 0) {
                $this->order_status = (int)MODULE_PAYMENT_AUTHORIZENET_CIM_ORDER_STATUS_ID;
            }
        
            if ($this->enabled && is_object($order)) {
                $this->update_status();
            }
        
            if (!defined('DEBUG_CIM') && ($this->enabled)) {
                if (in_array(MODULE_PAYMENT_AUTHORIZENET_CIM_DEBUGGING, array('True','TRUE','true'))) {
                    define('DEBUG_CIM', true);
                } else {
                    define('DEBUG_CIM', false);
                }
            }
        }
    
        function update_status()
        {
            global $order, $db;
            if (($this->enabled == true) && ((int)MODULE_PAYMENT_AUTHORIZENET_CIM_ZONE > 0)) {
                $check_flag = false;
                $check = $db->Execute("select zone_id from " . TABLE_ZONES_TO_GEO_ZONES . " where geo_zone_id = '" . MODULE_PAYMENT_AUTHORIZENET_CIM_ZONE . "' and zone_country_id = '" . $order->billing['country']['id'] . "' order by zone_id");
                while (!$check->EOF) {
                    if ($check->fields['zone_id'] < 1) {
                        $check_flag = true;
                        break;
                    } elseif ($check->fields['zone_id'] == $order->billing['zone_id']) {
                        $check_flag = true;
                        break;
                    }
                    $check->MoveNext();
                }
            
                if ($check_flag == false) {
                    $this->enabled = false;
                }
            }
        }
    
        function javascript_validation()
        {
            $js = '  if (payment_value == "' . $this->code . '") {' . "\n" .
              '    var cc_owner = document.checkout_payment.authorizenet_cim_cc_owner.value;' . "\n" .
              '    var cc_number = document.checkout_payment.authorizenet_cim_cc_number.value;' . "\n";
            if (MODULE_PAYMENT_AUTHORIZENET_CIM_USE_CVV == 'True') {
                $js .= '    var cc_cvv = document.checkout_payment.authorizenet_cim_cc_cvv.value;' . "\n";
            }
            $js .= '    if (cc_owner == "" || cc_owner.length < ' . CC_OWNER_MIN_LENGTH . ') {' . "\n" .
              '      error_message = error_message + "' . MODULE_PAYMENT_AUTHORIZENET_CIM_TEXT_JS_CC_OWNER . '";' . "\n" .
              '      error = 1;' . "\n" .
              '    }' . "\n" .
              '    if (cc_number == "" || cc_number.length < ' . CC_NUMBER_MIN_LENGTH . ') {' . "\n" .
              '      error_message = error_message + "' . MODULE_PAYMENT_AUTHORIZENET_CIM_TEXT_JS_CC_NUMBER . '";' . "\n" .
              '      error = 1;' . "\n" .
              '    }' . "\n";
            if (MODULE_PAYMENT_AUTHORIZENET_CIM_USE_CVV == 'True') {
                $js .= '    if (cc_cvv == "" || cc_cvv.length < "3" || cc_cvv.length > "4") {' . "\n" .
                  '      error_message = error_message + "' . MODULE_PAYMENT_AUTHORIZENET_CIM_TEXT_JS_CC_CVV . '";' . "\n" .
                  '      error = 1;' . "\n" .
                  '    }' . "\n";
            }
            $js .= '  }' . "\n";
        
            return $js;
        }
    
        function selection()
        {
            global $order;
        
            for ($i = 1; $i < 13; $i++) {
                $expires_month[] = array(
                  'id' => sprintf('%02d', $i),
                  'text' => strftime('%B - (%m)', mktime(0, 0, 0, $i, 1, 2000))
                );
            }
        
            $today = getdate();
            for ($i = $today['year']; $i < $today['year'] + 10; $i++) {
                $expires_year[] = array(
                  'id' => strftime('%y', mktime(0, 0, 0, 1, 1, $i)),
                  'text' => strftime('%Y', mktime(0, 0, 0, 1, 1, $i))
                );
            }
        
            $onFocus = ' onfocus="methodSelect(\'pmt-' . $this->code . '\')"';
        
            $selection = array(
              'id' => $this->code,
              'module' => MODULE_PAYMENT_AUTHORIZENET_CIM_TEXT_CATALOG_TITLE,
              'fields' => array(
                  /*array(
                      'field' => '<div class="apple-pay-button apple-pay-button-white"></div>',
                  ), */
                array(
                  'title' => MODULE_PAYMENT_AUTHORIZENET_CIM_TEXT_CREDIT_CARD_OWNER,
                  'field' => zen_draw_input_field('authorizenet_cim_cc_owner',
                    $order->billing['firstname'] . ' ' . $order->billing['lastname'],
                    'id="' . $this->code . '-cc-owner"' . $onFocus),
                  'tag' => $this->code . '-cc-owner'
                ),
                array(
                  'title' => MODULE_PAYMENT_AUTHORIZENET_CIM_TEXT_CREDIT_CARD_NUMBER,
                  'field' => zen_draw_input_field('authorizenet_cim_cc_number', '',
                    'id="' . $this->code . '-cc-number"' . $onFocus),
                  'tag' => $this->code . '-cc-number'
                ),
                array(
                  'title' => MODULE_PAYMENT_AUTHORIZENET_CIM_TEXT_CREDIT_CARD_EXPIRES,
                  'field' => zen_draw_pull_down_menu('authorizenet_cim_cc_expires_month', $expires_month, '',
                      'id="' . $this->code . '-cc-expires-month"' . $onFocus) . '&nbsp;' . zen_draw_pull_down_menu('authorizenet_cim_cc_expires_year',
                      $expires_year, strftime('%y', mktime(0, 0, 0, 1, 1, $today['year'] + 1)),
                      'id="' . $this->code . '-cc-expires-year"' . $onFocus),
                  'tag' => $this->code . '-cc-expires-month'
                )
              )
            );
        
            if (MODULE_PAYMENT_AUTHORIZENET_CIM_USE_CVV == 'True') {
                $selection['fields'][] = array(
                  'title' => MODULE_PAYMENT_AUTHORIZENET_CIM_TEXT_CVV,
                  'field' => zen_draw_input_field('authorizenet_cim_cc_cvv', '',
                      'size="4" maxlength="4" class="cvv_input"' . ' id="' . $this->code . '-cc-cvv"' . $onFocus) . ' ' . '<a href="javascript:popupWindow(\'' . zen_href_link(FILENAME_POPUP_CVV_HELP) . '\')">' . MODULE_PAYMENT_AUTHORIZENET_CIM_TEXT_POPUP_CVV_LINK . '</a>',
                  'tag' => $this->code . '-cc-cvv'
                );
            }
            $selection['fields'][] = array(
              'title' => 'Keep Card on File',
              'field' => zen_draw_checkbox_field('authorizenet_cim_save', $save_cc,
                '' . ' id="' . $this->code . '-save"' . $onFocus),
              'tag' => $this->code . '-save'
            );
        
            return $selection;
        }

        function pre_confirmation_check()
        {
            global $messageStack;
        
            include(DIR_WS_CLASSES . 'cc_validation.php');
        
            $cc_validation = new cc_validation();
            $result = $cc_validation->validate($_POST['authorizenet_cim_cc_number'],
              $_POST['authorizenet_cim_cc_expires_month'], $_POST['authorizenet_cim_cc_expires_year'],
              $_POST['authorizenet_cim_cc_cvv']);
            $error = '';
            switch ($result) {
                case -1:
                    $error = sprintf(TEXT_CCVAL_ERROR_UNKNOWN_CARD, substr($cc_validation->cc_number, 0, 4));
                    break;
                case -2:
                case -3:
                case -4:
                    $error = TEXT_CCVAL_ERROR_INVALID_DATE;
                    break;
                case false:
                    $error = TEXT_CCVAL_ERROR_INVALID_NUMBER;
                    break;
            }
        
            if (($result == false) || ($result < 1)) {
                $payment_error_return = 'payment_error=' . $this->code . '&authorizenet_cim_cc_owner=' . urlencode($_POST['authorizenet_cim_cc_owner']) . '&authorizenet_cim_cc_expires_month=' . $_POST['authorizenet_cim_cc_expires_month'] . '&authorizenet_cim_cc_expires_year=' . $_POST['authorizenet_cim_cc_expires_year'];
                $messageStack->add_session('checkout_payment', $error . '<!-- [' . $this->code . '] -->', 'error');
                zen_redirect(zen_href_link(FILENAME_CHECKOUT_PAYMENT, $payment_error_return, 'SSL', true, false));
            }
        
            $this->cc_card_type = $cc_validation->cc_type;
            $this->cc_card_number = $cc_validation->cc_number;
            $this->cc_expiry_month = $cc_validation->cc_expiry_month;
            $this->cc_expiry_year = $cc_validation->cc_expiry_year;
        }
    
        /**
         * Display Credit Card Information on the Checkout Confirmation Page
         *
         * @return array
         */
        function confirmation()
        {
            $confirmation = array(
              'fields' => array(
                array(
                  'title' => MODULE_PAYMENT_AUTHORIZENET_CIM_TEXT_CREDIT_CARD_TYPE,
                  'field' => $this->cc_card_type
                ),
                array(
                  'title' => MODULE_PAYMENT_AUTHORIZENET_CIM_TEXT_CREDIT_CARD_OWNER,
                  'field' => $_POST['authorizenet_cim_cc_owner']
                ),
                array(
                  'title' => MODULE_PAYMENT_AUTHORIZENET_CIM_TEXT_CREDIT_CARD_NUMBER,
                  'field' => str_repeat('X', strlen($this->cc_card_number) - 12) . '-' . str_repeat('X',
                      4) . '-' . str_repeat('X', 4) . '-' . substr($this->cc_card_number, -4)
                ),
                array(
                  'title' => MODULE_PAYMENT_AUTHORIZENET_CIM_TEXT_CREDIT_CARD_EXPIRES,
                  'field' => strftime('%B, %Y', mktime(0, 0, 0, $_POST['authorizenet_cim_cc_expires_month'], 1,
                    '20' . $_POST['authorizenet_cim_cc_expires_year']))
                ),
              )
            );
            return $confirmation;
        }
    
        /**
         * Build the data and actions to process when the "Submit" button is pressed on the order-confirmation screen.
         * This sends the data to the payment gateway for processing.
         * (These are hidden fields on the checkout confirmation page)
         *
         * @return string
         */
        function process_button()
        {
            $process_button_string = zen_draw_hidden_field('cc_owner', $_POST['authorizenet_cim_cc_owner']) .
              zen_draw_hidden_field('cc_expires', $this->cc_expiry_month . substr($this->cc_expiry_year, -2)) .
              zen_draw_hidden_field('cc_expires_date', $this->cc_expiry_year . "-" . $this->cc_expiry_month) .
              zen_draw_hidden_field('cc_type', $this->cc_card_type) .
              zen_draw_hidden_field('payment', $_POST['payment']) .
              zen_draw_hidden_field('cc_save', $_POST['authorizenet_cim_save']) .
              zen_draw_hidden_field('cc_number', $this->cc_card_number);
            if (MODULE_PAYMENT_AUTHORIZENET_CIM_USE_CVV == 'True') {
                $process_button_string .= zen_draw_hidden_field('cc_cvv', $_POST['authorizenet_cim_cc_cvv']);
            }
            $process_button_string .= zen_draw_hidden_field(zen_session_name(), zen_session_id());
        
            return $process_button_string;
        }
    
        /**
         * Store the CC info to the order and process any results that come back from the payment gateway
         */
        function before_process()
        {
            global $response, $db, $order, $messageStack, $customerID;
        
            $order->info['cc_number'] = str_pad(substr($_POST['cc_number'], -4), strlen($_POST['cc_number']), "X",
              STR_PAD_LEFT);
            $order->info['cc_expires'] = $_POST['cc_expires'];
            $order->info['cc_type'] = $_POST['cc_type'];
            $order->info['cc_owner'] = $_POST['cc_owner'];
            $order->info['cc_owner'] = $_POST['cc_owner'];
        
            $customerID = $_SESSION['customer_id'];
            $customerProfileId = $this->getCustomerProfile($customerID);
        
            if ($customerProfileId == false) {
                $this->createCustomerProfileRequest();
            } else {
                $this->setParameter('customerProfileId', $customerProfileId);
            }
        
            $this->addErrorsMessageStack('Customer Profile');
        
            $this->createCustomerPaymentProfileRequest();
        
            $this->addErrorsMessageStack('Customer Payment Profile');
        
            $this->response = $this->chargeCustomerProfile($this->params['customerProfileId'],
              $this->params['customerPaymentProfileId']);
        
            $this->addErrorsMessageStack('Customer Payment Transaction');
        }
    
        function after_process()
        {
            global $insert_id, $customerID;
        
            $this->updateOrderAndPayment($customerID, $this->transID, $insert_id, $this->approvalCode,
              $this->params['customerPaymentProfileId'], $this->order_status);
        
        }
    
        function remove()
        {
            global $db;
            $db->Execute("delete from " . TABLE_CONFIGURATION . " where configuration_key like 'MODULE\_PAYMENT\_AUTHORIZENET\_CIM\_%'");
        }
    
        function keys()
        {
            return array(
              'MODULE_PAYMENT_AUTHORIZENET_CIM_STATUS',
              'MODULE_PAYMENT_AUTHORIZENET_CIM_LOGIN',
              'MODULE_PAYMENT_AUTHORIZENET_CIM_TXNKEY',
              'MODULE_PAYMENT_AUTHORIZENET_CIM_TESTMODE',
                'MODULE_PAYMENT_AUTHORIZENET_CIM_AUTHORIZATION_TYPE',
                //'MODULE_PAYMENT_AUTHORIZENET_CIM_STORE_DATA',
                //'MODULE_PAYMENT_AUTHORIZENET_CIM_EMAIL_CUSTOMER',
                //'MODULE_PAYMENT_AUTHORIZENET_CIM_EMAIL_MERCHANT',
              'MODULE_PAYMENT_AUTHORIZENET_CIM_USE_CVV',
              'MODULE_PAYMENT_AUTHORIZENET_CIM_VALIDATION',
              'MODULE_PAYMENT_AUTHORIZENET_CIM_SORT_ORDER',
              'MODULE_PAYMENT_AUTHORIZENET_CIM_ZONE',
              'MODULE_PAYMENT_AUTHORIZENET_CIM_ORDER_STATUS_ID',
              'MODULE_PAYMENT_AUTHORIZENET_CIM_REFUNDED_ORDER_STATUS_ID',
              'MODULE_PAYMENT_AUTHORIZENET_CIM_DEBUGGING',
            );
        }
    
        function get_error()
        {
            $error = array(
              'title' => MODULE_PAYMENT_AUTHORIZENET_CIM_TEXT_ERROR,
              'error' => stripslashes(urldecode($_GET['error']))
            );
            return $error;
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
            if (!defined('MODULE_PAYMENT_AUTHORIZENET_CIM_STATUS') || empty(MODULE_PAYMENT_AUTHORIZENET_CIM_STATUS)) {
                $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Enable Authorize.net (CIM) Module', 'MODULE_PAYMENT_AUTHORIZENET_CIM_STATUS', 'True', 'Do you want to accept Authorize.net payments via the CIM Method?', '6', '1', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");
            }
            if (!defined('MODULE_PAYMENT_AUTHORIZENET_CIM_LOGIN')) {
                $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Login ID', 'MODULE_PAYMENT_AUTHORIZENET_CIM_LOGIN', 'testing', 'The API Login ID used for the Authorize.net service', '6', '2', now())");
            }
            if (!defined('MODULE_PAYMENT_AUTHORIZENET_CIM_TXNKEY')) {
                $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added, use_function) values ('Transaction Key', 'MODULE_PAYMENT_AUTHORIZENET_CIM_TXNKEY', 'Test', 'Transaction Key used for encrypting TP data<br />(See your Authorizenet Account->Security Settings->API Login ID and Transaction Key for details.)', '6', '3', now(), 'zen_cfg_password_display')");
            }
            if (!defined('MODULE_PAYMENT_AUTHORIZENET_CIM_TESTMODE')) {
                $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Transaction Mode (test => sandbox)', 'MODULE_PAYMENT_AUTHORIZENET_CIM_TESTMODE', 'Test', 'Transaction mode used for processing orders', '6', '6', 'zen_cfg_select_option(array(\'Test\', \'Production\'), ', now())");
            }
            if (!defined('MODULE_PAYMENT_AUTHORIZENET_CIM_AUTHORIZATION_TYPE')) $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Authorization Type', 'MODULE_PAYMENT_AUTHORIZENET_CIM_AUTHORIZATION_TYPE', 'Authorize', 'Do you want submitted credit card transactions to be authorized only, or authorized and captured?', '6', '7', 'zen_cfg_select_option(array(\'Authorize\', \'Authorize+Capture\'), ', now())");
            //if (!defined('MODULE_PAYMENT_AUTHORIZENET_CIM_STORE_DATA')) $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Enable Database Storage', 'MODULE_PAYMENT_AUTHORIZENET_CIM_STORE_DATA', 'True', 'Do you want to save the gateway communications data to the database?', '6', '8', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");
            //if (!defined('MODULE_PAYMENT_AUTHORIZENET_CIM_EMAIL_CUSTOMER')) $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Customer Notifications', 'MODULE_PAYMENT_AUTHORIZENET_CIM_EMAIL_CUSTOMER', 'False', 'Should Authorize.Net email a receipt to the customer?', '6', '9', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");
            //if (!defined('MODULE_PAYMENT_AUTHORIZENET_CIM_EMAIL_MERCHANT')) $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Merchant Notifications', 'MODULE_PAYMENT_AUTHORIZENET_CIM_EMAIL_MERCHANT', 'False', 'Should Authorize.Net email a receipt to the merchant?', '6', '10', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");
            if (!defined('MODULE_PAYMENT_AUTHORIZENET_CIM_USE_CVV')) {
                $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Request CVV Number', 'MODULE_PAYMENT_AUTHORIZENET_CIM_USE_CVV', 'True', 'Do you want to ask the customer for the card\'s CVV number? If set to false, ensure that on merchant dashboard at authorize.net, you have card code not selected as required.  See https://developer.authorize.net/api/reference/responseCodes.html?code=33', '6', '11', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");
            }
            if (!defined('MODULE_PAYMENT_AUTHORIZENET_CIM_VALIDATION')) {
                $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Validation Mode (liveMode validates cardInfo)', 'MODULE_PAYMENT_AUTHORIZENET_CIM_VALIDATION', 'liveMode', 'Validation Mode', '6', '12', 'zen_cfg_select_option(array(\'none\', \'testMode\', \'liveMode\'), ', now())");
            }
            if (!defined('MODULE_PAYMENT_AUTHORIZENET_CIM_SORT_ORDER')) {
                $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Sort order of display.', 'MODULE_PAYMENT_AUTHORIZENET_CIM_SORT_ORDER', '0', 'Sort order of display. Lowest is displayed first.', '6', '13', now())");
            }
            if (!defined('MODULE_PAYMENT_AUTHORIZENET_CIM_ZONE')) {
                $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Payment Zone', 'MODULE_PAYMENT_AUTHORIZENET_CIM_ZONE', '0', 'If a zone is selected, only enable this payment method for that zone.', '6', '14', 'zen_get_zone_class_title', 'zen_cfg_pull_down_zone_classes(', now())");
            }
            if (!defined('MODULE_PAYMENT_AUTHORIZENET_CIM_ORDER_STATUS_ID')) {
                $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Set Completed Order Status', 'MODULE_PAYMENT_AUTHORIZENET_CIM_ORDER_STATUS_ID', '0', 'Set the status of orders made with this payment module to this value', '6', '15', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
            }
            if (!defined('MODULE_PAYMENT_AUTHORIZENET_CIM_REFUNDED_ORDER_STATUS_ID')) {
                $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Set Refunded Order Status', 'MODULE_PAYMENT_AUTHORIZENET_CIM_REFUNDED_ORDER_STATUS_ID', '1', 'Set the status of refunded orders to this value (refund amounts must be equal to payment total)', '6', '16', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
            }
            if (!defined('MODULE_PAYMENT_AUTHORIZENET_CIM_DEBUGGING')) {
                $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Debug Mode', 'MODULE_PAYMENT_AUTHORIZENET_CIM_DEBUGGING', 'False', 'Would you like to enable debug mode?  Failed transactions will always be logged in the cim_response.log file in your ZC logs directory.', '6', '17', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");
            }
        
            $this->tableCheckup();
        }
        
        // end zen-cart base functions
    
        // helper functions
    
        function logError($logData, $error = false)
        {
            $response_log = (defined('DIR_FS_LOGS') ? DIR_FS_LOGS : DIR_FS_SQL_CACHE) . '/cim_response.log';
        
            if ($error) {
                $this->errorMessages[] = $logData;
            }
        
            if (DEBUG_CIM) {
                $this->checkLogName();
                trigger_error($logData);
            }
        
            if ($error || DEBUG_CIM) {
                error_log(date(DATE_RFC2822) . ":\n" . $logData . "\n", 3, $response_log);
            }
        }
    
        function checkLogName()
        {
            $log = ini_get('error_log');
            $start = strpos($log, 'cim');
            if ($start === false) {
                $end = strrpos(ini_get('error_log'), "/");
                if ($end !== false) {
                    $log_prefix = (IS_ADMIN_FLAG) ? '/cimDEBUG-adm-' : '/cimDEBUG-';
                    $log_date = new DateTime();
                    $debug_logfile_path = substr($log, 0, $end) . $log_prefix . $log_date->format('Ymd-His-u') . '.log';
                    unset($log_prefix, $log_date);
                    ini_set('error_log', $debug_logfile_path);
                }
            }
        }
    
        function addErrorsMessageStack($type)
        {
            global $messageStack;
        
            if (isset($this->errorMessages) && (!empty($this->errorMessages))) {
                foreach ($this->errorMessages as $error) {
                    if (!defined('IS_ADMIN_FLAG') || IS_ADMIN_FLAG !== true) {
                        $messageStack->add_session(FILENAME_CHECKOUT_PAYMENT, $type . ': ' . $error, 'error');
                    } else {
                        $messageStack->add_session($type . ': ' . $error, 'error');
                    }
                }
                if (!defined('IS_ADMIN_FLAG') || IS_ADMIN_FLAG !== true) {
                    zen_redirect(zen_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL', true, false));
                }
            }
        }
    
        function convertExpDate($year, $month)
        {
            if (isset($_POST['cc_expires_date'])) {
                return $_POST['cc_expires_date'];
            } else {
                return '20' . $year . '-' . $month;
            }
        }
    
        function getCustomerPaymentProfile($customer_id, $last_four)
        {
            global $db;
        
            $sql = "SELECT * FROM " . TABLE_CUSTOMERS_CC . "
            WHERE customers_id = :custId and last_four = :last4 order by index_id desc limit 1";
            $sql = $db->bindVars($sql, ':custId', $customer_id, 'string');
            $sql = $db->bindVars($sql, ':last4', $last_four, 'string');
            $check_customer_cc = $db->Execute($sql);
        
            if ($check_customer_cc->fields['payment_profile_id'] !== 0) {
                $paymentProfileId = $check_customer_cc->fields['payment_profile_id'];
                $exp_date = $check_customer_cc->fields['exp_date'];
            } else {
                $paymentProfileId = false;
                $exp_date = false;
            }
            return array('profile' => $paymentProfileId, 'exp_date' => $exp_date);
        }
    
        function billtoAddress($response = null)
        {
            global $order;
        
            //for card_update
            if (isset($_POST['address_selection'])) {
                // card_update address change
                $order = new stdClass();
                $order->billing = $this->getAddressInfo();
            } elseif (!is_object($order)) {
                // card_update - just update exp date
                if (!is_null($response)) {
                    return $response->getPaymentProfile()->getbillTo();
                } else {
                    trigger_error('from card update, no payment profile, should never get here');
                    return;
                }
            }
            // from checkout or getAddressInfo above
            $billto = new AnetAPI\CustomerAddressType();
            $billto->setFirstName($order->billing['firstname']);
            $billto->setLastName($order->billing['lastname']);
            $billto->setCompany($order->billing['company']);
            $billto->setAddress($order->billing['street_address']);
            $billto->setCity($order->billing['city']);
            $billto->setState($order->billing['state']);
            $billto->setZip($order->billing['postcode']);
            $billto->setCountry($order->billing['country']['title']);
            //$billto->setPhoneNumber();
            //$billto->setfaxNumber();
            return $billto;
        }
    
        function getAddressInfo()
        {
            // for card_update
            global $customerID, $db;
        
            $return = [];
            if ($_POST['address_selection'] == 'new') {
                $this->addNewAddress();
            
                $return['firstname'] = $_POST['customers_firstname'];
                $return['lastname'] = $_POST['customers_lastname'];
                $return['street_address'] = $_POST['street_address'];
                $return['city'] = $_POST['city'];
                if (empty($_POST['zone_id'])) {
                    $return['state'] = $_POST['state'];
                } else {
                    $return['state'] = zen_get_zone_name($_POST['zone_country_id'], $_POST['zone_id'], $_POST['state']);
                }
                $return['postcode'] = $_POST['postcode'];
                $return['country']['title'] = zen_get_country_name($_POST['zone_country_id']);
            
            } else {
                // from order class
                $sql = "select ab.entry_firstname, ab.entry_lastname, ab.entry_company,
                                   ab.entry_street_address, ab.entry_suburb, ab.entry_postcode,
                                   ab.entry_city, ab.entry_zone_id, z.zone_name, ab.entry_country_id,
                                   c.countries_id, c.countries_name, c.countries_iso_code_2,
                                   c.countries_iso_code_3, c.address_format_id, ab.entry_state
                                  from " . TABLE_ADDRESS_BOOK . " ab
                                  left join " . TABLE_ZONES . " z on (ab.entry_zone_id = z.zone_id)
                                  left join " . TABLE_COUNTRIES . " c on (ab.entry_country_id = c.countries_id)
                                  where ab.customers_id = :custID
                                  and ab.address_book_id = :addBookID";
                $sql = $db->bindVars($sql, ':custID', $customerID, 'integer');
                $sql = $db->bindVars($sql, ':addBookID', $_POST['address_selection'], 'integer');
                $address = $db->Execute($sql);
            
                $return['firstname'] = $address->fields['entry_firstname'];
                $return['lastname'] = $address->fields['entry_lastname'];
                $return['company'] = $address->fields['entry_company'];
                $return['street_address'] = $address->fields['entry_street_address'];
                $return['city'] = $address->fields['entry_city'];
                $return['state'] = ((zen_not_null($address->fields['entry_state'])) ? $address->fields['entry_state'] : $address->fields['zone_name']);
                $return['postcode'] = $address->fields['entry_postcode'];
                $return['country']['title'] = $address->fields['countries_name'];
            }
            return $return;
        }
    
        function addNewAddress()
        {
            global $customer_id, $db, $zco_notifier;
        
            $sql_data_array = array(
              array('fieldName' => 'entry_firstname', 'value' => $_POST['firstname'], 'type' => 'stringIgnoreNull'),
              array('fieldName' => 'entry_lastname', 'value' => $_POST['lastname'], 'type' => 'stringIgnoreNull'),
              array(
                'fieldName' => 'entry_street_address',
                'value' => $_POST['street_address'],
                'type' => 'stringIgnoreNull'
              ),
              array('fieldName' => 'entry_postcode', 'value' => $_POST['postcode'], 'type' => 'stringIgnoreNull'),
              array('fieldName' => 'entry_city', 'value' => $_POST['city'], 'type' => 'stringIgnoreNull'),
              array('fieldName' => 'entry_country_id', 'value' => $_POST['zone_country_id'], 'type' => 'integer')
            );
        
            if (ACCOUNT_GENDER == 'true') {
                $sql_data_array[] = array(
                  'fieldName' => 'entry_gender',
                  'value' => $_POST['gender'],
                  'type' => 'enum:m|f'
                );
            }
            if (ACCOUNT_COMPANY == 'true') {
                $sql_data_array[] = array(
                  'fieldName' => 'entry_company',
                  'value' => $_POST['company'],
                  'type' => 'stringIgnoreNull'
                );
            }
            if (ACCOUNT_SUBURB == 'true') {
                $sql_data_array[] = array(
                  'fieldName' => 'entry_suburb',
                  'value' => $_POST['suburb'],
                  'type' => 'stringIgnoreNull'
                );
            }
        
            if (ACCOUNT_STATE == 'true') {
                if (!empty($_POST['zone_id']) && $_POST['zone_id'] > 0) {
                    $sql_data_array[] = array(
                      'fieldName' => 'entry_zone_id',
                      'value' => $_POST['zone_id'],
                      'type' => 'integer'
                    );
                    $sql_data_array[] = array(
                      'fieldName' => 'entry_state',
                      'value' => '',
                      'type' => 'stringIgnoreNull'
                    );
                } else {
                    $sql_data_array[] = array('fieldName' => 'entry_zone_id', 'value' => '0', 'type' => 'integer');
                    $sql_data_array[] = array(
                      'fieldName' => 'entry_state',
                      'value' => $_POST['state'],
                      'type' => 'stringIgnoreNull'
                    );
                }
            }
        
            $sql_data_array[] = array(
              'fieldName' => 'customers_id',
              'value' => $customer_id,
              'type' => 'integer'
            );

            $db->perform(TABLE_ADDRESS_BOOK, $sql_data_array);
            $new_address_book_id = $db->Insert_ID();
            $this->updateDefaultCustomerBillTo($new_address_book_id);
            $zco_notifier->notify('NOTIFY_MODULE_ADDRESS_BOOK_ADDED_ADDRESS_BOOK_RECORD',
              array_merge(array('address_id' => $new_address_book_id), $sql_data_array));
        }
    
        function nextOrderNumber($order)
        {
            global $db;
            if (isset($order['orders_id'])) {
                return $order['orders_id'];
            } else {
                $nextID = $db->Execute("SELECT (orders_id + 1) AS nextID FROM " . TABLE_ORDERS . " ORDER BY orders_id DESC LIMIT 1");
                $nextID = $nextID->fields['nextID'];
                return $nextID;
            }
        }
    
        function saveCard()
        {
            if (($_POST['cc_save'] == 'on') || ($_POST['new_cid'] == 'NEW') || isset($_POST['update_cid'])) {
                return 'Y';
            } else {
                return 'N';
            }
        }
    
        function doCimRefund($ordersID, $refund_amount)
        {
            global $db;
        
            $sql = "SELECT cust.customers_customerProfileId, cim.*, ord.order_total FROM " . TABLE_CIM_PAYMENTS . " cim
            left join " . TABLE_CUSTOMERS_CIM_PROFILE . " cust on cim.customers_id = cust.customers_id
            left join " . TABLE_ORDERS . " ord on cim.orders_id = ord.orders_id
            where cim.orders_id = :orderID and ((cim.payment_amount - cim.refund_amount) > 0) order by cim.payment_id desc limit 1";
            $sql = $db->bindVars($sql, ':orderID', $ordersID, 'integer');
            $refund = $db->Execute($sql);
        
            $max_refund = $refund->fields['payment_amount'];
        
            // need to look at previous amounts refunded on transaction
            $sql = "select sum(refund_amount) as refunded from " . TABLE_CIM_REFUNDS . "  where payment_trans_id = :transID";
            $sql = $db->bindVars($sql, ':transID', $refund->fields['transaction_id'], 'string');
            $previous_refund = $db->Execute($sql);
        
            if (isset($previous_refund->fields['refunded']) and (!is_null($previous_refund->fields['refunded']))) {
                $max_refund -= $previous_refund->fields['refunded'];
            }
        
            if (!(isset($refund_amount)) || ($refund_amount == 0) || ($refund_amount > $max_refund)) {
                $refund_amount = $max_refund;
            }
        
            $details = $this->getTransactionDetails($refund->fields['transaction_id']);
        
            if (strpos($details->getTransaction()->getTransactionStatus(), 'Pending') !== false) {
                // if the transaction is pending, a void will void all of the amount
                $response = $this->voidTransaction($ordersID, $refund, $max_refund);
                $this->addErrorsMessageStack('Void');
            } else {
                $response = $this->refundTransaction($ordersID, $refund, $refund_amount);
                $this->addErrorsMessageStack('Refund');
            }
            if ($response->getMessages()->getResultCode() == "Ok") {
                return true;
            } else {
                return false;
            }
        }
    
        function checkZeroBalance($ordersID)
        {
            global $db;
            $sql = "SELECT sum(payment_amount) as payment from " . TABLE_CIM_PAYMENTS . "
                where orders_id = :orderID
                group by orders_id";
            $sql = $db->bindVars($sql, ':orderID', $ordersID, 'integer');
            $payments = $db->Execute($sql);
        
            $sql = "select sum(refund_amount) as refunded from " . TABLE_CIM_REFUNDS . "  where orders_id = :orderID";
            $sql = $db->bindVars($sql, ':orderID', $ordersID, 'integer');
            $refunds = $db->Execute($sql);
        
            if (($payments->fields['payment'] - $refunds->fields['refunded']) == 0) {
                return true;
            }
            return false;
        }
    
        function getCustomerProfile($customer_id)
        {
            global $db;
            $sql = "SELECT customers_customerProfileId FROM " . TABLE_CUSTOMERS_CIM_PROFILE . " WHERE customers_id = :custId ";
            $sql = $db->bindVars($sql, ':custId', $customer_id, 'string');
            $check_customer = $db->Execute($sql);

            if ($check_customer->fields['customers_customerProfileId'] != 0) {
                $customerProfileId = $check_customer->fields['customers_customerProfileId'];
            } else {
                $customerProfileId = false;
            }
            return $customerProfileId;
        }
    
        function merchantCredentials()
        {
            $merch = new AnetAPI\MerchantAuthenticationType();
            $merch->setName(trim(MODULE_PAYMENT_AUTHORIZENET_CIM_LOGIN));
            $merch->setTransactionKey(trim(MODULE_PAYMENT_AUTHORIZENET_CIM_TXNKEY));
            return ($merch);
        }
    
        function getControllerResponse($controller)
        {
            if (in_array(MODULE_PAYMENT_AUTHORIZENET_CIM_TESTMODE, array('Test', 'Sandbox'))) {
                return $controller->executeWithApiResponse(\net\authorize\api\constants\ANetEnvironment::SANDBOX);
            } else {
                return $controller->executeWithApiResponse(\net\authorize\api\constants\ANetEnvironment::PRODUCTION);
            }
        }
    
        function setParameter($field = "", $value = null)
        {
            $this->params[$field] = $value;
        }
    
        // functions that use the authorize API and return responses.
    
        function createCustomerProfileRequest()
        {
            global $customerID, $order;
        
            $customerID = $_SESSION['customer_id'];
        
            // Create a new CustomerProfileType and add the payment profile object
            $customerProfile = new AnetAPI\CustomerProfileType();
            $customerProfile->setDescription($order->customer['firstname'] . ' ' . $order->customer['lastname']);
            $customerProfile->setMerchantCustomerId($customerID);
            $customerProfile->setEmail($order->customer['email_address']);
            //$customerProfile->setpaymentProfiles($paymentProfiles);
            //$customerProfile->setShipToList($shippingProfiles);
        
            // Assemble the complete transaction request
            $request = new AnetAPI\CreateCustomerProfileRequest();
            $request->setMerchantAuthentication($this->merchantCredentials());
            $request->setProfile($customerProfile);
        
            $controller = new AnetController\CreateCustomerProfileController($request);
            $response = $this->getControllerResponse($controller);
        
            $error = true;
            if (($response != null) && ($response->getMessages()->getResultCode() == "Ok")) {
                $error = false;
                $this->updateCustomer($customerID, $response->getCustomerProfileId());
                $this->setParameter('customerProfileId', $response->getCustomerProfileId());
                $logData = "Succesfully created customer profile : " . $response->getCustomerProfileId() . "\n";
            } else {
                $logData = "ERROR :  Invalid response\n";
                $errorMessages = $response->getMessages()->getMessage();
                $logData .= "Response : " . $errorMessages[0]->getCode() . "  " . $errorMessages[0]->getText() . "\n";
                if ($errorMessages[0]->getCode() == 'E00039') {
                    $id_text = $errorMessages[0]->getText();
                    $start = strpos($id_text, 'with ID ');
                    $end = strrpos($id_text, " already");
                    $profile = substr($id_text, $start + 8, ($end - ($start+8)));
                    if (is_numeric($profile)) {
                        $this->updateCustomer($customerID, $profile);
                        $this->setParameter('customerProfileId', $response->getCustomerProfileId());
                        $error = false;
                    }
                }
            }
        
            $this->logError($logData, $error);
            return $response;
        }
    
        function createCustomerPaymentProfileRequest()
        {
            global $order, $customerID;
        
            // for card_update
            if (!isset($customerID) && (!defined('IS_ADMIN_FLAG') || ('IS_ADMIN_FLAG' == 0))) {
                $customerID = $_SESSION['customer_id'];
            }
        
            if (!isset($this->params['customerProfileId']) || ($this->params['customerProfileId'] == 0)) {
                $customerProfileId = $this->getCustomerProfile($customerID);

                if ($customerProfileId == false) {
                    $this->createCustomerProfileRequest();
                } else {
                    $this->setParameter('customerProfileId', $customerProfileId);
                }
            }
        
            // for card_update
            $exp_date = $this->convertExpDate($_POST['cc_year'], $_POST['cc_month']);
        
            // here we are checking to see if the customer already has this card on file.
            // if we have it, return that profile
            $existing_profile = $this->getCustomerPaymentProfile($customerID,
              substr(trim($_POST['cc_number']), -4));
        
            if (!is_null($existing_profile['profile'])) {
                if ($existing_profile['exp_date'] !== $exp_date) {
                    $this->updateCustomerPaymentProfile($this->params['customerProfileId'],
                      $existing_profile['profile']);
                }
                $this->setParameter('customerPaymentProfileId', $existing_profile['profile']);
                return;
            }
        
            // Set credit card information for payment profile
            $creditCard = new AnetAPI\CreditCardType();
            $creditCard->setCardNumber($_POST['cc_number']);
            $creditCard->setExpirationDate($exp_date);
            $creditCard->setCardCode($_POST['cc_cvv']);
            $paymentCreditCard = new AnetAPI\PaymentType();
            $paymentCreditCard->setCreditCard($creditCard);

            // Create the Bill To info for new payment type
            $billto = $this->billtoAddress();
        
            // Create a new Customer Payment Profile object
            $paymentprofile = new AnetAPI\CustomerPaymentProfileType();
            $paymentprofile->setCustomerType('individual');
            $paymentprofile->setBillTo($billto);
            $paymentprofile->setPayment($paymentCreditCard);
            $paymentprofile->setDefaultPaymentProfile(true);
        
            //$paymentprofiles[] = $paymentprofile;
        
            // Assemble the complete transaction request
            $paymentprofilerequest = new AnetAPI\CreateCustomerPaymentProfileRequest();
            $paymentprofilerequest->setMerchantAuthentication($this->merchantCredentials());
        
            // Add an existing profile id to the request
            $paymentprofilerequest->setCustomerProfileId($this->params['customerProfileId']);
            $paymentprofilerequest->setPaymentProfile($paymentprofile);
            $paymentprofilerequest->setValidationMode(MODULE_PAYMENT_AUTHORIZENET_CIM_VALIDATION);
        
            // Create the controller and get the response
            $controller = new AnetController\CreateCustomerPaymentProfileController($paymentprofilerequest);
            $response = $this->getControllerResponse($controller);
        
            $error = true;
            if (($response != null) && ($response->getMessages()->getResultCode() == "Ok")) {
                $error = false;
                $logData = "Create Customer Payment Profile SUCCESS: " . $response->getCustomerPaymentProfileId() . "\n";
                $this->setParameter('customerPaymentProfileId', $response->getCustomerPaymentProfileId());
                $save = $this->saveCard();
            
                $this->saveCCToken($customerID, $this->params['customerPaymentProfileId'],
                  substr($_POST['cc_number'], -4),
                  $exp_date, $save);
                $order->info['payment_profile_id'] = $this->params['customerPaymentProfileId'];
            } else {
                $logData = "Create Customer Payment Profile: ERROR Invalid response\n";
                $errorMessages = $response->getMessages()->getMessage();
                $logData .= "Customer: " . $customerID . "\n";
                $logData .= "Response : " . $errorMessages[0]->getCode() . "  " . $errorMessages[0]->getText() . "\n";
                if ($errorMessages[0]->getCode() == 'E00039') {
                    $error = false;
                    $this->setParameter('customerPaymentProfileId', $response->getCustomerPaymentProfileId());
                    $save = $this->saveCard();
                    $this->saveCCToken($customerID, $this->params['customerPaymentProfileId'],
                      substr($_POST['cc_number'], -4),
                      $exp_date, $save);
                    $order->info['payment_profile_id'] = $this->params['customerPaymentProfileId'];
                }
            }
            $this->logError($logData, $error);
            return $logData;
        }
    
        function chargeCustomerProfile($profileid, $paymentprofileid)
        {
            global $order;
        
            $profileToCharge = new AnetAPI\CustomerProfilePaymentType();
            $profileToCharge->setCustomerProfileId($profileid);
            $paymentProfile = new AnetAPI\PaymentProfileType();
            $paymentProfile->setPaymentProfileId($paymentprofileid);
            $paymentProfile->setCardCode($_POST['cc_cvv']);
            $profileToCharge->setPaymentProfile($paymentProfile);
        
            $transactionRequestType = new AnetAPI\TransactionRequestType();
            if (MODULE_PAYMENT_AUTHORIZENET_CIM_AUTHORIZATION_TYPE == 'Authorize') {
                $transactionRequestType->setTransactionType("authOnlyTransaction");
            } else {
                 $transactionRequestType->setTransactionType("authCaptureTransaction");
            }
            $transactionRequestType->setAmount(number_format($order->info['total'], 2, '.', ''));
            $transactionRequestType->setProfile($profileToCharge);
        
            if (count($order->products) > 0) {
                foreach (array_slice($order->products, 0, 30) as $items) {
                    $lineItem1 = new AnetAPI\LineItemType();
                    $lineItem1->setItemId(zen_get_prid($items['id']));
                    $lineItem1->setName(substr(preg_replace('/[^a-z0-9_ ]/i', '',
                      preg_replace('/&nbsp;/', ' ', $items['name'])), 0, 30));
                    //$lineItem1->setDescription("Here's the first line item");
                    $lineItem1->setQuantity($items['qty']);
                    $lineItem1->setUnitPrice($items['final_price']);
                    $lineItem1->getTaxable($order->info['tax'] == 0 ? false : true);
                    $transactionRequestType->addToLineItems($lineItem1);
                }
            }
        
            $request = new AnetAPI\CreateTransactionRequest();
            $request->setMerchantAuthentication($this->merchantCredentials());
            $request->setRefId($this->nextOrderNumber($order->info));
            $request->setTransactionRequest($transactionRequestType);
            $controller = new AnetController\CreateTransactionController($request);
            $response = $this->getControllerResponse($controller);
        
            $error = true;
            if ($response != null) {
                if ($response->getMessages()->getResultCode() == "Ok") {
                    $tresponse = $response->getTransactionResponse();
                
                    if ($tresponse != null && $tresponse->getMessages() != null) {
                        $error = false;
                        $logData = "Transaction Response code : " . $tresponse->getResponseCode() . "\n";
                        $logData .= " Charge Customer Profile APPROVED  :" . "\n";
                        $logData .= " Charge Customer Profile AUTH CODE : " . $tresponse->getAuthCode() . "\n";
                        $this->approvalCode = $tresponse->getAuthCode();
                        $logData .= " Charge Customer Profile TRANS ID  : " . $tresponse->getTransId() . "\n";
                        $this->transID = $tresponse->getTransId();
                        $logData .= " Code : " . $tresponse->getMessages()[0]->getCode() . "\n";
                        $logData .= " Description : " . $tresponse->getMessages()[0]->getDescription() . "\n";
                    
                        $this->insertPayment($tresponse->getTransId(),
                          $order->billing['firstname'] . ' ' . $order->billing['lastname'],
                          $order->info['total'], $this->code, $this->params['customerPaymentProfileId'],
                          $tresponse->getAuthCode(),
                          (isset($order->customer['id']) ? $order->customer['id'] : $_SESSION['customer_id']));
                    } else {
                        $logData = "Transaction Failed \n";
                        if ($tresponse->getErrors() != null) {
                            $logData .= " Error code  : " . $tresponse->getErrors()[0]->getErrorCode() . "\n";
                            $logData .= " Error message : " . $tresponse->getErrors()[0]->getErrorText() . "\n";
                        }
                    }
                } else {
                    $logData = "Transaction Failed \n";
                    $tresponse = $response->getTransactionResponse();
                    if ($tresponse != null && $tresponse->getErrors() != null) {
                        $logData .= " Error code  : " . $tresponse->getErrors()[0]->getErrorCode() . "\n";
                        $logData .= " Error message : " . $tresponse->getErrors()[0]->getErrorText() . "\n";
                    } else {
                        $logData .= " Error code  : " . $response->getMessages()->getMessage()[0]->getCode() . "\n";
                        $logData .= " Error message : " . $response->getMessages()->getMessage()[0]->getText() . "\n";
                    }
                }
            } else {
                $logData = "No response returned \n";
            }
            $this->logError($logData, $error);
            return $response;
        }
    
        function updateCustomerPaymentProfile($customerProfileId, $customerPaymentProfileId)
        {
            global $order, $customer_id;
        
            //for card_update
            $exp_date = $this->convertExpDate($_POST['cc_year'], $_POST['cc_month']);
        
            $request = new AnetAPI\GetCustomerPaymentProfileRequest();
            $request->setMerchantAuthentication($this->merchantCredentials());
            $request->setCustomerProfileId($customerProfileId);
            $request->setCustomerPaymentProfileId($customerPaymentProfileId);
        
            $controller = new AnetController\GetCustomerPaymentProfileController($request);
            $response = $this->getControllerResponse($controller);
            if (($response != null) && ($response->getMessages()->getResultCode() == "Ok")) {
            
                $billto = $this->billToAddress($response);
            
                $creditCard = new AnetAPI\CreditCardType();
                $creditCard->setCardNumber($response->getPaymentProfile()->getPayment()->getCreditCard()->getCardNumber());
                $creditCard->setExpirationDate($exp_date);
            
                $paymentCreditCard = new AnetAPI\PaymentType();
                $paymentCreditCard->setCreditCard($creditCard);
                $paymentprofile = new AnetAPI\CustomerPaymentProfileExType();
                //$paymentprofile->setBillTo($billto);
                $paymentprofile->setCustomerPaymentProfileId($customerPaymentProfileId);
                $paymentprofile->setPayment($paymentCreditCard);
            
                $paymentprofile->setBillTo($billto);
            
                // Submit a UpdatePaymentProfileRequest
                $request = new AnetAPI\UpdateCustomerPaymentProfileRequest();
                $request->setMerchantAuthentication($this->merchantCredentials());
                $request->setCustomerProfileId($customerProfileId);
                $request->setPaymentProfile($paymentprofile);
            
                $controller = new AnetController\UpdateCustomerPaymentProfileController($request);
                $response = $this->getControllerResponse($controller);
                $error = true;
                if (($response != null) && ($response->getMessages()->getResultCode() == "Ok")) {
                    $Message = $response->getMessages()->getMessage();
                    $error = false;
                    $logData = "Update Customer Payment Profile SUCCESS: " . $Message[0]->getCode() . "  " . $Message[0]->getText() . "\n";
                    $save = $this->saveCard();
                    $this->updateCCToken($_SESSION['customer_id'], $customerPaymentProfileId, $exp_date, $save);
                } else {
                    if ($response != null) {
                        $errorMessages = $response->getMessages()->getMessage();
                        $logData = "Failed to Update Customer Payment Profile :  " . $errorMessages[0]->getCode() . "  " . $errorMessages[0]->getText() . "\n";
                    }
                }
                $this->logError($logData, $error);
                return $logData;
            }
        }
    
        function getTransactionDetails($transactionId)
        {
            $request = new AnetAPI\GetTransactionDetailsRequest();
            $request->setMerchantAuthentication($this->merchantCredentials());
            $request->setTransId($transactionId);
    
            $controller = new AnetController\GetTransactionDetailsController($request);
            $response = $this->getControllerResponse($controller);
    
            $error = true;
            if (($response != null) && ($response->getMessages()->getResultCode() == "Ok")) {
                $logData = "SUCCESS: Transaction Status:" . $response->getTransaction()->getTransactionStatus() . "\n";
                $logData .= "  Auth Amount:" . $response->getTransaction()->getAuthAmount() . "\n";
                $logData .= "  Trans ID:" . $response->getTransaction()->getTransId() . "\n";
                $error = false;
            } else {
                $logData = "ERROR :  Invalid response\n";
                $errorMessages = $response->getMessages()->getMessage();
                $logData .= "Response : " . $errorMessages[0]->getCode() . "  " . $errorMessages[0]->getText() . "\n";
            }
            $this->logError($logData, $error);
        
            return $response;
        }
    
        function refundTransaction($ordersID, $refund, $refund_amount)
        {
            $profileToCharge = new AnetAPI\CustomerProfilePaymentType();
            $profileToCharge->setCustomerProfileId($refund->fields['customers_customerProfileId']);
            $paymentProfile = new AnetAPI\PaymentProfileType();
            $paymentProfile->setPaymentProfileId($refund->fields['payment_profile_id']);
            $profileToCharge->setPaymentProfile($paymentProfile);
        
            //create a transaction
            $transactionRequest = new AnetAPI\TransactionRequestType();
            $transactionRequest->setTransactionType("refundTransaction");
            $transactionRequest->setAmount($refund_amount);
            $transactionRequest->setProfile($profileToCharge);
            $transactionRequest->setRefTransId($refund->fields['transaction_id']);
        
        
            $request = new AnetAPI\CreateTransactionRequest();
            $request->setMerchantAuthentication($this->merchantCredentials());
            $request->setRefId($ordersID);
            $request->setTransactionRequest($transactionRequest);
            $controller = new AnetController\CreateTransactionController($request);
            $response = $this->getControllerResponse($controller);
        
            $error = true;
            if ($response != null) {
                if ($response->getMessages()->getResultCode() == "Ok") {
                    $tresponse = $response->getTransactionResponse();
                
                    if ($tresponse != null && $tresponse->getMessages() != null) {
                        $error = false;
                        $logData = " Transaction Response code : " . $tresponse->getResponseCode() . "\n";
                        $logData .= " Refund SUCCESS: " . $tresponse->getTransId() . "\n";
                        $logData .= " Code : " . $tresponse->getMessages()[0]->getCode() . "\n";
                        $logData .= " Description : " . $tresponse->getMessages()[0]->getDescription() . "\n";
                    
                        $this->insertRefund($refund->fields['payment_id'], $ordersID, $tresponse->getTransId(),
                          $refund->fields['payment_name'], $refund->fields['transaction_id'], $refund_amount, 'REF',
                          $tresponse->getMessages()[0]->getCode());
                        $this->updatePaymentForRefund($ordersID, $refund->fields['transaction_id'], $refund_amount);
                        $update_status = $this->checkZeroBalance($ordersID);
                        if ($update_status) {
                            $this->updateOrderInfo($ordersID, MODULE_PAYMENT_AUTHORIZENET_CIM_REFUNDED_ORDER_STATUS_ID);
                        }
                    
                    } else {
                        $logData = "Transaction Failed \n";
                        if ($tresponse->getErrors() != null) {
                            $logData .= " Error code  : " . $tresponse->getErrors()[0]->getErrorCode() . "\n";
                            $logData .= " Error message : " . $tresponse->getErrors()[0]->getErrorText() . "\n";
                        }
                    }
                } else {
                    $logData = "Transaction Failed \n";
                    $tresponse = $response->getTransactionResponse();
                    if ($tresponse != null && $tresponse->getErrors() != null) {
                        $logData .= " Error code  : " . $tresponse->getErrors()[0]->getErrorCode() . "\n";
                        $logData .= " Error message : " . $tresponse->getErrors()[0]->getErrorText() . "\n";
                    } else {
                        $logData .= " Error code  : " . $response->getMessages()->getMessage()[0]->getCode() . "\n";
                        $logData .= " Error message : " . $response->getMessages()->getMessage()[0]->getText() . "\n";
                    }
                }
            } else {
                $logData = "No response returned \n";
            }
            $this->logError($logData, $error);
            return $response;
        }
    
        function voidTransaction($ordersID, $refund, $refund_amount)
        {
            $transactionRequestType = new AnetAPI\TransactionRequestType();
            $transactionRequestType->setTransactionType("voidTransaction");
            $transactionRequestType->setRefTransId(trim($refund->fields['transaction_id']));
        
            $request = new AnetAPI\CreateTransactionRequest();
            $request->setMerchantAuthentication($this->merchantCredentials());
            $request->setRefId($ordersID);
            $request->setTransactionRequest($transactionRequestType);
            $controller = new AnetController\CreateTransactionController($request);
            $response = $this->getControllerResponse($controller);
        
            $error = true;
            if ($response != null) {
                if ($response->getMessages()->getResultCode() == "Ok") {
                    $tresponse = $response->getTransactionResponse();
                
                    if ($tresponse != null && $tresponse->getMessages() != null) {
                    
                        $this->insertRefund($refund->fields['payment_id'], $ordersID, $tresponse->getTransId(),
                          $refund->fields['payment_name'],
                          $refund->fields['transaction_id'], $refund_amount, 'VOID', $tresponse->getAuthCode());
                        $this->updatePaymentForRefund($ordersID, $refund->fields['transaction_id'], $refund_amount);
                        $this->updateOrderInfo($ordersID, MODULE_PAYMENT_AUTHORIZENET_CIM_REFUNDED_ORDER_STATUS_ID);
                        $error = false;
                        $logData = " Transaction Response code : " . $tresponse->getResponseCode() . "\n";
                        $logData .= " Void transaction SUCCESS AUTH CODE: " . $tresponse->getAuthCode() . "\n";
                        $logData .= " Void transaction SUCCESS TRANS ID  : " . $tresponse->getTransId() . "\n";
                        $logData .= " Code : " . $tresponse->getMessages()[0]->getCode() . "\n";
                        $logData .= " Description : " . $tresponse->getMessages()[0]->getDescription() . "\n";
                    } else {
                        $logData = "Transaction Failed \n";
                        $logData .= "Order ID: " . $ordersID . "\n";
                        if ($tresponse->getErrors() != null) {
                            $logData .= " Error code  : " . $tresponse->getErrors()[0]->getErrorCode() . "\n";
                            $logData .= " Error message : " . $tresponse->getErrors()[0]->getErrorText() . "\n";
                        }
                    }
                } else {
                    $logData = "Transaction Failed \n";
                    $logData .= "Order ID: " . $ordersID . "\n";
                    $tresponse = $response->getTransactionResponse();
                    if ($tresponse != null && $tresponse->getErrors() != null) {
                        $logData .= " Error code  : " . $tresponse->getErrors()[0]->getErrorCode() . "\n";
                        $message = $tresponse->getErrors()[0]->getErrorText() . "\n";
                    } else {
                        $logData .= " Error code  : " . $response->getMessages()->getMessage()[0]->getCode() . "\n";
                        $logData .= " Error message : " . $response->getMessages()->getMessage()[0]->getText() . "\n";
                    }
                }
            } else {
                $logData = "No response returned \n";
                $logData .= "Order ID: " . $ordersID . "\n";
            }
            $this->logError($logData, $error);
            return $response;
        }
    
        function deleteCustomerPaymentProfile($customerProfileId, $customerpaymentprofileid)
        {
            global $db;
        
            $request = new AnetAPI\DeleteCustomerPaymentProfileRequest();
            $request->setMerchantAuthentication($this->merchantCredentials());
            $request->setCustomerProfileId($customerProfileId);
            $request->setCustomerPaymentProfileId($customerpaymentprofileid);
            $controller = new AnetController\DeleteCustomerPaymentProfileController($request);
            $response = $this->getControllerResponse($controller);
        
            $error = true;
            if (($response != null) && ($response->getMessages()->getResultCode() == "Ok")) {
                $error = false;
                $logData = "SUCCESS: Delete Customer Payment Profile" . "\n";
            } else {
                $logData = "ERROR :  Delete Customer Payment Profile: Invalid response\n";
                $errorMessages = $response->getMessages()->getMessage();
                $logData .= "Response : " . $errorMessages[0]->getCode() . "  " . $errorMessages[0]->getText() . "\n";
            }
            $this->logError($logData, $error);
        
            $sql = "delete from " . TABLE_CUSTOMERS_CC . "  WHERE payment_profile_id = :profileID ";
            $sql = $db->bindVars($sql, ':profileID', $customerpaymentprofileid, 'integer');
            $db->Execute($sql);
        
            return $logData;
        }
    
        function deleteCustomerProfile($customerProfileId)
        {
            global $db;
        
            $request = new AnetAPI\DeleteCustomerProfileRequest();
            $request->setMerchantAuthentication($this->merchantCredentials());
            $request->setCustomerProfileId($customerProfileId);
        
            $controller = new AnetController\DeleteCustomerProfileController($request);
            $response = $this->getControllerResponse($controller);
        
            $error = true;
            if (($response != null) && ($response->getMessages()->getResultCode() == "Ok")) {
                $error = false;
                $logData = "DeleteCustomerProfile SUCCESS : " . "\n";
            } else {
                $logData = "ERROR :  DeleteCustomerProfile: Invalid response\n";
                $errorMessages = $response->getMessages()->getMessage();
                $logData .= "Response : " . $errorMessages[0]->getCode() . "  " . $errorMessages[0]->getText() . "\n";
            }
        
            $this->logError($logData, $error);
        
            $sql = "UPDATE " . TABLE_CUSTOMERS_CIM_PROFILE . " set customers_customerProfileId = 0, date_modified = now()
		            WHERE customers_customerProfileId = :profileID ";
            $sql = $db->bindVars($sql, ':profileID', $customerProfileId, 'integer');
            $db->Execute($sql);
        
            return $response;
        }

    function capturePreviouslyAuthorizedAmount($transactionid, $amount)
    {

        $transactionRequestType = new AnetAPI\TransactionRequestType();
        $transactionRequestType->setTransactionType("priorAuthCaptureTransaction");
        $transactionRequestType->setRefTransId($transactionid);
        $transactionRequestType->setAmount($amount);


        $request = new AnetAPI\CreateTransactionRequest();
        $request->setMerchantAuthentication($this->merchantCredentials());
        $request->setTransactionRequest($transactionRequestType);

        $controller = new AnetController\CreateTransactionController($request);
        $response = $this->getControllerResponse($controller);

        $error = true;
        if ($response != null) {
            if ($response->getMessages()->getResultCode() == "Ok") {
                $tresponse = $response->getTransactionResponse();

                if ($tresponse != null && $tresponse->getMessages() != null) {
                    $error = false;
                    $logData = " Transaction Response code : " . $tresponse->getResponseCode() . "\n";
                    $logData .= "Successful." . "\n";
                    $logData .= "Capture Previously Authorized Amount, Trans ID : " . $tresponse->getRefTransId() . "\n";
                    $logData .= " Code : " . $tresponse->getMessages()[0]->getCode() . "\n";
                    $logData .= " Description : " . $tresponse->getMessages()[0]->getDescription() . "\n";
                    $this->capturePayment($transactionid, $amount);
                } else {
                    $logData = "Transaction Failed \n";
                    if ($tresponse->getErrors() != null) {
                        $logData .= " Error code  : " . $tresponse->getErrors()[0]->getErrorCode() . "\n";
                        $logData .= " Error message : " . $tresponse->getErrors()[0]->getErrorText() . "\n";
                    }
                }
            } else {
                $logData = "Transaction Failed \n";
                $tresponse = $response->getTransactionResponse();
                if ($tresponse != null && $tresponse->getErrors() != null) {
                    $logData .= " Error code  : " . $tresponse->getErrors()[0]->getErrorCode() . "\n";
                    $logData .= " Error message : " . $tresponse->getErrors()[0]->getErrorText() . "\n";
                } else {
                    $logData .= " Error code  : " . $response->getMessages()->getMessage()[0]->getCode() . "\n";
                    $logData .= " Error message : " . $response->getMessages()->getMessage()[0]->getText() . "\n";
                }
            }
        } else {
            $logData = "No response returned \n";
        }
        $this->logError($logData, $error);

        return $error;
    }
    
        // functions for updating some aspect of the zen-cart database.
    
        function insertPayment($transID, $name, $total, $type, $profileID, $approval, $custID)
        {
            global $db;
            $sql = "insert into " . TABLE_CIM_PAYMENTS . " ( payment_name, payment_amount, payment_type, date_posted, last_modified,  transaction_id, payment_profile_id, approval_code, customers_id, status)
VALUES (:nameFull, :amount, :type, now(), now(), :transID, :paymentProfileID, :approval_code, :custID, :status)";

            if (MODULE_PAYMENT_AUTHORIZENET_CIM_AUTHORIZATION_TYPE == 'Authorize') {
                $status = 'A';
            } else {
                $status = 'C';
            }
        
            $sql = $db->bindVars($sql, ':transID', $transID, 'string');
            $sql = $db->bindVars($sql, ':nameFull', $name, 'string');
            $sql = $db->bindVars($sql, ':amount', $total, 'noquotestring');
            $sql = $db->bindVars($sql, ':type', $type, 'string');
            $sql = $db->bindVars($sql, ':paymentProfileID', $profileID, 'string');
            $sql = $db->bindVars($sql, ':approval_code', $approval, 'string');
            $sql = $db->bindVars($sql, ':custID', $custID, 'string');
            $sql = $db->bindVars($sql, ':status', $status, 'string');
            $db->Execute($sql);
        }
    
        function insertRefund($paymentID, $ordersID, $transID, $name, $payment_trans_id, $amount, $type, $approval_code)
        {
            global $db;
            $sql = "insert into " . TABLE_CIM_REFUNDS . " (payment_id, orders_id, transaction_id, refund_name, refund_amount, refund_type, payment_trans_id, date_posted, approval_code) values (:paymentID, :orderID, :transID, :payment_name, :amount, :type, :payment_trans_id, now(), :apprCode )";
            $sql = $db->bindVars($sql, ':paymentID', $paymentID, 'integer');
            $sql = $db->bindVars($sql, ':orderID', $ordersID, 'integer');
            $sql = $db->bindVars($sql, ':transID', $transID, 'string');
            $sql = $db->bindVars($sql, ':payment_name', $name, 'string');
            $sql = $db->bindVars($sql, ':type', $type, 'string');
            $sql = $db->bindVars($sql, ':apprCode', $approval_code, 'string');
            $sql = $db->bindVars($sql, ':payment_trans_id', trim($payment_trans_id), 'string');
            $sql = $db->bindVars($sql, ':amount', $amount, 'noquotestring');
            $db->Execute($sql);
        }
    
        function updatePaymentForRefund($ordersID, $transID, $amount)
        {
            global $db;
            $sql = "update " . TABLE_CIM_PAYMENTS . " set refund_amount = (refund_amount + :amount) where transaction_id = :transID and orders_id = :orderID";
            $sql = $db->bindVars($sql, ':orderID', $ordersID, 'integer');
            $sql = $db->bindVars($sql, ':transID', trim($transID), 'string');
            $sql = $db->bindVars($sql, ':amount', $amount, 'noquotestring');
            $db->Execute($sql);
        }

    function capturePayment($transID, $amount)
    {
        global $db;
        $sql = "update " . TABLE_CIM_PAYMENTS . " set payment_amount = :amount, status = 'C' where transaction_id = :transID and status ='A'";
        $sql = $db->bindVars($sql, ':transID', trim($transID), 'string');
        $sql = $db->bindVars($sql, ':amount', $amount, 'noquotestring');
        $db->Execute($sql);
    }
    
        function updateDefaultCustomerBillto($id)
        {
            global $db, $customer_id, $zco_notifier;

            if (isset($_POST['primary']) && $_POST['primary'] == 'on') {

                $sql = "UPDATE " . TABLE_CUSTOMERS . " SET customers_default_address_id = :addID WHERE customers_id = :custID";
                $sql = $db->bindVars($sql, ":addID", $id, 'integer');
                $sql = $db->bindVars($sql, ":custID", $customer_id, 'integer');
                $action = $db->Execute($sql);

                $zco_notifier->notify('NOTIFY_MODULE_ADDRESS_BOOK_UPDATED_PRIMARY_CUSTOMER_RECORD',
                    array('address_id' => $id, 'customers_id' => $customer_id));
            }
        }
    
        function updateOrderAndPayment($customerID, $transID, $insertID, $approval, $payment_profile_id, $status)
        {
            global $db;
        
            $sql = "update  " . TABLE_CIM_PAYMENTS . " set orders_id = :insertID
            WHERE customers_id = :custId and transaction_id = :transID and orders_id = 0";
            $sql = $db->bindVars($sql, ':custId', $customerID, 'integer');
            $sql = $db->bindVars($sql, ':transID', $transID, 'string');
            $sql = $db->bindVars($sql, ':insertID', $insertID, 'integer');
            $db->Execute($sql);
        
            $this->updateOrderInfo($insertID, $status);
        
            $sql = "insert into " . TABLE_ORDERS_STATUS_HISTORY . " (comments, orders_id, orders_status_id, date_added) values (:orderComments, :orderID, :orderStatus, now() )";
            $sql = $db->bindVars($sql, ':orderComments',
              'Credit Card payment.  AUTH: ' . $approval . '. TransID: ' . $transID . '.',
              'string');
            $sql = $db->bindVars($sql, ':orderID', $insertID, 'integer');
            $sql = $db->bindVars($sql, ':orderStatus', $status, 'integer');
            $db->Execute($sql);
        }
    
        function updateOrderInfo($ordersID, $status)
        {
            global $db;
        
            $sql = "UPDATE " . TABLE_ORDERS . "
        	SET orders_status = :stat
        	WHERE orders_id = :orderID ";
            $sql = $db->bindVars($sql, ':orderID', $ordersID, 'integer');
            $sql = $db->bindVars($sql, ':stat', $status, 'integer');
            $db->Execute($sql);
        }
    
        function deleteStoredData($customer_id, $customerProfileID)
        {
            $cards = $this->getCustomerCards($customer_id, true);
            foreach ($cards as $card) {
                $this->deleteCustomerPaymentProfile($customerProfileID, $card['payment_profile_id']);
            }
            // i think i best to keep the profile in case one needs to do a refund on an existing transaction.
            $this->deleteCustomerProfile($customerProfileID);
        }
    
        function getCustomerCards($customerID, $all = false)
        {
            global $db;
            $sql = "SELECT * FROM " . TABLE_CUSTOMERS_CC . " WHERE customers_id = :custID AND payment_profile_id <> 0";
            if (!$all) {
                $sql .= " and enabled = 'Y'";
            }
            $sql = $db->bindVars($sql, ':custID', $customerID, 'integer');
            $customer_cards = $db->Execute($sql);
        
            return $customer_cards;
        }
    
        function checkValidPaymentProfile($customerID, $cc_index)
        {
            global $db;
            $sql = "SELECT * FROM " . TABLE_CUSTOMERS_CC . "
            WHERE index_id = :indexID";
            $sql = $db->bindVars($sql, ':indexID', $cc_index, 'integer');
            $check_customer_cc = $db->Execute($sql);
        
            if ($check_customer_cc->fields['customers_id'] != $customerID) {
                $return['valid'] = false;
            } else {
                $return['payment_profile_id'] = $check_customer_cc->fields['payment_profile_id'];
                $return['valid'] = true;
                $return['last_four'] = $check_customer_cc->fields['last_four'];
                $return['exp_date'] = $check_customer_cc->fields['exp_date'];
            }
            return $return;
        }
    
        function updateCCToken($custId, $payment_profile, $exp_date, $save)
        {
            global $db;
        
            $sql = "UPDATE " . TABLE_CUSTOMERS_CC . " SET exp_date = :expDate, enabled = :save, card_last_modified = now()
            where customers_id = :custID and payment_profile_id = :profID";
            // (customers_id, payment_profile_id, last_four, exp_date, enabled, card_last_modified)
            // values (:custID,  :profID, :lastFour, :expDate, :save, now())";
            $sql = $db->bindVars($sql, ':custID', $custId, 'integer');
            $sql = $db->bindVars($sql, ':profID', $payment_profile, 'integer');
            $sql = $db->bindVars($sql, ':expDate', $exp_date, 'string');
            $sql = $db->bindVars($sql, ':save', $save, 'string');
        
            $db->Execute($sql);
        }
    
        function saveCCToken($custId, $payment_profile, $lastfour, $exp_date, $save)
        {
            global $db;
        
            //$insert_date = '20' . substr($exp_date,2,2) .'-'. substr($exp_date,0,2);
            $sql = "INSERT " . TABLE_CUSTOMERS_CC . "
	        (customers_id, payment_profile_id, last_four, exp_date, enabled, card_last_modified)
	        values (:custID,  :profID, :lastFour, :expDate, :save, now())";
            $sql = $db->bindVars($sql, ':custID', $custId, 'integer');
            $sql = $db->bindVars($sql, ':profID', $payment_profile, 'integer');
            $sql = $db->bindVars($sql, ':lastFour', $lastfour, 'string');
            $sql = $db->bindVars($sql, ':expDate', $exp_date, 'string');
            $sql = $db->bindVars($sql, ':save', $save, 'string');
        
            $db->Execute($sql);
        }
    
        function updateCustomer($customerID, $profileID)
        {
            global $db;
            $sql = "SELECT * FROM " . TABLE_CUSTOMERS_CIM_PROFILE . " WHERE customers_id = :custID";
            $sql = $db->bindVars($sql, ':custID', $customerID, 'integer');
            $customer = $db->Execute($sql);

            if ($customer->count() > 0) {
                $sql = "UPDATE " . TABLE_CUSTOMERS_CIM_PROFILE . " set customers_customerProfileId = :profileID,
                date_modified = now() WHERE customers_id = :custID";
            } else {
                $sql = "INSERT INTO " . TABLE_CUSTOMERS_CIM_PROFILE . " (`customers_id`, `customers_customerProfileId`, `date_created`, `date_modified`)
            VALUES (:custID, :profileID, now(), now())";
            }
            $sql = $db->bindVars($sql, ':custID', $customerID, 'integer');
            $sql = $db->bindVars($sql, ':profileID', $profileID, 'integer');
            $db->Execute($sql);
        }

    protected function tableCheckup()
    {
        global $db, $sniffer;

        if (!$sniffer->table_exists(TABLE_CIM_PAYMENTS)) {
            $sql = "
                CREATE TABLE `" . TABLE_CIM_PAYMENTS . "` (
                `payment_id` int(11) NOT NULL AUTO_INCREMENT,
  `orders_id` int(11) NOT NULL DEFAULT 0,
  `transaction_id` varchar(20) NOT NULL,
  `payment_name` varchar(40) NOT NULL DEFAULT '',
  `payment_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `refund_amount` decimal(14,2) unsigned NOT NULL DEFAULT 0.00,
  `payment_profile_id` int(11) unsigned zerofill NOT NULL,
  `approval_code` varchar(10) DEFAULT NULL,
  `customers_id` int(11) NOT NULL,
  `payment_type` varchar(20) NOT NULL DEFAULT '',
  `date_posted` datetime DEFAULT NULL,
  `last_modified` datetime DEFAULT NULL,
  PRIMARY KEY (`payment_id`),
  KEY `refund_index` (`orders_id`)
)";
            $db->Execute($sql);
        }

        if (!$sniffer->table_exists(TABLE_CIM_PAYMENT_TYPES)) {
            $sql = "
                CREATE TABLE `" . TABLE_CIM_PAYMENT_TYPES . "` (
  `payment_type_id` int(11) NOT NULL AUTO_INCREMENT,
  `language_id` int(11) NOT NULL DEFAULT 1,
  `payment_type_code` varchar(4) NOT NULL DEFAULT '',
  `payment_type_full` varchar(20) NOT NULL DEFAULT '',
  PRIMARY KEY (`payment_type_id`),
  UNIQUE KEY `type_code` (`payment_type_code`),
  KEY `type_code_2` (`payment_type_code`)
)";
            $db->Execute($sql);

            $sql = "INSERT INTO `" . TABLE_CIM_PAYMENT_TYPES . "` (`payment_type_id`, `language_id`, `payment_type_code`, `payment_type_full`) VALUES
(1,	1,	'CA',	'Cash'),
(2,	1,	'CK',	'Check'),
(3,	1,	'MO',	'Money Order'),
(4,	1,	'WU',	'Western Union'),
(5,	1,	'ADJ',	'Adjustment'),
(6,	1,	'REF',	'Refund'),
(7,	1,	'CC',	'Credit Card'),
(8,	1,	'MC',	'MasterCard'),
(9,	1,	'VISA',	'Visa'),
(10,	1,	'AMEX',	'American Express'),
(11,	1,	'DISC',	'Discover'),
(12,	1,	'DINE',	'Diners Club'),
(13,	1,	'SOLO',	'Solo'),
(14,	1,	'MAES',	'Maestro'),
(15,	1,	'JCB',	'JCB'),
(16,	1,	'VOID',	'Void')";
            $db->Execute($sql);
        }

        if (!$sniffer->table_exists(TABLE_CIM_REFUNDS)) {
            $sql = "
CREATE TABLE `" . TABLE_CIM_REFUNDS . "` (
  `refund_id` int(11) NOT NULL AUTO_INCREMENT,
  `payment_id` int(11) NOT NULL DEFAULT 0,
  `orders_id` int(11) NOT NULL DEFAULT 0,
  `transaction_id` varchar(20) NOT NULL DEFAULT '',
  `refund_name` varchar(40) NOT NULL DEFAULT '',
  `refund_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `refund_type` varchar(20) NOT NULL DEFAULT 'REF',
  `approval_code` varchar(20) DEFAULT NULL,
  `payment_trans_id` varchar(20) NOT NULL,
  `date_posted` datetime DEFAULT NULL,
  PRIMARY KEY (`refund_id`)
)";
            $db->Execute($sql);
        }

        if (!$sniffer->table_exists(TABLE_CUSTOMERS_CC)) {
            $sql = "
CREATE TABLE `" . TABLE_CUSTOMERS_CC . "` (
  `index_id` int(11) NOT NULL AUTO_INCREMENT,
  `customers_id` int(11) NOT NULL,
  `payment_profile_id` int(11) NOT NULL,
  `last_four` char(4) NOT NULL,
  `exp_date` char(7) NOT NULL,
  `shipping_address_id` int(11) NOT NULL DEFAULT 0,
  `enabled` enum('Y','N') NOT NULL DEFAULT 'N',
  `card_last_modified` datetime NOT NULL,
  PRIMARY KEY (`index_id`)
)";
            $db->Execute($sql);
        }
        if (!$sniffer->table_exists(TABLE_CUSTOMERS_CIM_PROFILE)) {
            $sql = "
CREATE TABLE `" . TABLE_CUSTOMERS_CIM_PROFILE . "` (
 `index` int(11) NOT NULL AUTO_INCREMENT,
  `customers_id` int(11) NOT NULL,
  `customers_customerProfileId` int(11) NOT NULL,
  `date_created` datetime NOT NULL,
  `date_modified` datetime NOT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`index`)
)";
            $db->Execute($sql);
        }

        $fieldOkay1 = (method_exists($sniffer, 'field_type')) ? $sniffer->field_exists(TABLE_CIM_PAYMENTS, 'status') : false;
        if ($fieldOkay1 !== true) {
            $db->Execute("ALTER TABLE " . TABLE_CIM_PAYMENTS . " ADD `status` enum('A','C') NOT NULL DEFAULT 'C' AFTER `payment_type`");
        }
    }
}
