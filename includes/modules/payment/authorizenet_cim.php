<?php
    /**
     * authorize.net CIM payment method class
     *
     * @package paymentMethod
     * @copyright Copyright 2003-2007 Zen Cart Development Team
     * @copyright Portions Copyright 2003 osCommerce
     * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
     * @version $Id: authorizenet_cim.php 7620 2007-12-11 19:12:46Z drbyte $
     */
    
    /**
     * Authorize.net Payment Module (CIM version)
     * You must have SSL active on your server to be compliant with merchant TOS
     *
     */
    include_once((IS_ADMIN_FLAG === true ? DIR_FS_CATALOG_MODULES : DIR_WS_MODULES) . 'payment/authorizenet/cim_functions.php');
    
    class authorizenet_cim extends base
    {
        /**
         * $code determines the internal 'code' name used to designate "this" payment module
         *
         * @var string
         */
        var $code;
        /**
         * $title is the displayed name for this payment method
         *
         * @var string
         */
        var $title;
        /**
         * $description is a soft name for this payment method
         *
         * @var string
         */
        var $description;
        /**
         * $enabled determines whether this module shows or not... in catalog.
         *
         * @var boolean
         */
        var $enabled;
        /**
         * $delimiter determines what separates each field of returned data from authorizenet
         *
         * @var string (single char)
         */
        var $delimiter = '|';
        /**
         * $encapChar denotes what character is used to encapsulate the response fields
         *
         * @var string (single char)
         */
        var $encapChar = '*';
        /**
         * log file folder
         *
         * @var string
         */
        var $_logDir = '';
        /**
         * communication vars
         */
        var $authorize = '';
        var $commErrNo = 0;
        var $commError = '';
        /**
         * debug content var
         */
        var $reportable_submit_data = array();
        
        /* cim variables */
        
        // cim
        
        var $version = '1.3'; // the code revision number for this class
        var $params = array();
        var $LineItems = array();
        var $success = false;
        var $error = true;
        var $error_messages = array();
        var $response;
        var $xml;
        var $update = false;
        var $resultCode;
        var $cim_code;
        var $text;
        var $refId;
        var $customerProfileId;
        var $customerPaymentProfileId;
        var $customerAddressId;
        var $directResponse;
        var $validationDirectResponse;
        var $responseDelimiter = ','; // Direct Response Delimiter.
        // Make sure this value is the same in your Authorize.net login area.
        // Account->Settings->Transaction Format Settings->Direct Response
        var $approvalCode;
        var $transID;
        // cim
        
        
        /**
         * Constructor
         *
         * @return authorizenet_cim
         */
        function __construct()
        {
            global $order, $messageStack;
            $this->code = 'authorizenet_cim';
            $this->enabled = ((MODULE_PAYMENT_AUTHORIZENET_CIM_STATUS == 'True') ? true : false); // Whether the module is installed or not
            if (($this->enabled) and IS_ADMIN_FLAG === true) {
                // Payment module title in Admin
                $this->title = MODULE_PAYMENT_AUTHORIZENET_CIM_TEXT_CATALOG_TITLE;
                if (MODULE_PAYMENT_AUTHORIZENET_CIM_STATUS == 'True' && (MODULE_PAYMENT_AUTHORIZENET_CIM_LOGIN == 'testing' || MODULE_PAYMENT_AUTHORIZENET_CIM_TXNKEY == 'Test')) {
                    $this->title .= '<span class="alert"> (Not Configured)</span>';
                } elseif (MODULE_PAYMENT_AUTHORIZENET_CIM_TESTMODE == 'Test') {
                    $this->title .= '<span class="alert"> (in Testing mode)</span>';
                }
                if ($this->enabled && !function_exists('curl_init')) {
                    $messageStack->add_session(MODULE_PAYMENT_AUTHORIZENET_CIM_TEXT_ERROR_CURL_NOT_FOUND, 'error');
                }
    
                $this->login = trim(MODULE_PAYMENT_AUTHORIZENET_CIM_LOGIN);
                $this->transkey = trim(MODULE_PAYMENT_AUTHORIZENET_CIM_TXNKEY);
                $this->test_mode = (MODULE_PAYMENT_AUTHORIZENET_CIM_TESTMODE == 'Test' ? true : false);
                $subdomain = ($this->test_mode) ? 'apitest' : 'api2';
                $this->url = "https://" . $subdomain . ".authorize.net/xml/v1/request.api";
                $this->validationMode = MODULE_PAYMENT_AUTHORIZENET_CIM_VALIDATION; // none, testMode or liveMode
    
            } elseif (IS_ADMIN_FLAG === true) {
                $this->title = MODULE_PAYMENT_AUTHORIZENET_CIM_TEXT_CATALOG_TITLE . ' Authorize.net (CIM)'; // Payment module title in Catalog
            }
            $this->description = 'authorizenet_cim'; // Descriptive Info about module in Admin
            $this->sort_order = defined('MODULE_PAYMENT_AUTHORIZENET_CIM_SORT_ORDER') ? MODULE_PAYMENT_AUTHORIZENET_CIM_SORT_ORDER : null; // Sort Order of this payment option on the customer payment page
            $this->form_action_url = zen_href_link(FILENAME_CHECKOUT_PROCESS, '', 'SSL',
              false); // Page to go to upon submitting page info
            $this->order_status = (int)DEFAULT_ORDERS_STATUS_ID;
            if (defined('MODULE_PAYMENT_AUTHORIZENET_CIM_ORDER_STATUS_ID') and (int)MODULE_PAYMENT_AUTHORIZENET_CIM_ORDER_STATUS_ID > 0) {
                $this->order_status = (int)MODULE_PAYMENT_AUTHORIZENET_CIM_ORDER_STATUS_ID;
            }
            
            $this->_logDir = DIR_FS_SQL_CACHE;
            
            if (is_object($order)) {
                $this->update_status();
            }
            
            if (!defined('DEBUG_CIM')) {
                define('DEBUG_CIM', false);
            }
        }
        
        /**
         * calculate zone matches and flag settings to determine whether this module should display to customers or not
         *
         */
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
        
        /**
         * JS validation which does error-checking of data-entry if this module is selected for use
         * (Number, Owner, and CVV Lengths)
         *
         * @return string
         */
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
        
        /**
         * Display Credit Card Information Submission Fields on the Checkout Payment Page
         *
         * @return array
         */
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
                array(
                  'title' => MODULE_PAYMENT_AUTHORIZENET_CIM_TEXT_CREDIT_CARD_OWNER,
                  'field' => zen_draw_input_field('authorizenet_cim_cc_owner',
                    $order->billing['firstname'] . ' ' . $order->billing['lastname'],
                    'id="' . $this->code . '-cc-owner"' . $onFocus),
                  'tag' => $this->code . '-cc-owner'
                ),
                array(
                  'title' => MODULE_PAYMENT_AUTHORIZENET_CIM_TEXT_CREDIT_CARD_NUMBER,
                  'field' => zen_draw_input_field('authorizenet_cim_cc_number', '4012888888881881',
                    'id="' . $this->code . '-cc-number"' . $onFocus),
                  'tag' => $this->code . '-cc-number'
                ),
                array(
                  'title' => MODULE_PAYMENT_AUTHORIZENET_CIM_TEXT_CREDIT_CARD_EXPIRES,
                  'field' => zen_draw_pull_down_menu('authorizenet_cim_cc_expires_month', $expires_month, '',
                      'id="' . $this->code . '-cc-expires-month"' . $onFocus) . '&nbsp;' . zen_draw_pull_down_menu('authorizenet_cim_cc_expires_year',
                      $expires_year, strftime('%y', mktime(0, 0, 0, 1, 1, $today['year']+1)), 'id="' . $this->code . '-cc-expires-year"' . $onFocus),
                  'tag' => $this->code . '-cc-expires-month'
                )
              )
            );
            
            if (MODULE_PAYMENT_AUTHORIZENET_CIM_USE_CVV == 'True') {
                $selection['fields'][] = array(
                  'title' => MODULE_PAYMENT_AUTHORIZENET_CIM_TEXT_CVV,
                  'field' => zen_draw_input_field('authorizenet_cim_cc_cvv', '456',
                      'size="4" maxlength="4"' . ' id="' . $this->code . '-cc-cvv"' . $onFocus) . ' ' . '<a href="javascript:popupWindow(\'' . zen_href_link(FILENAME_POPUP_CVV_HELP) . '\')">' . MODULE_PAYMENT_AUTHORIZENET_CIM_TEXT_POPUP_CVV_LINK . '</a>',
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
        
        /**
         * Evaluates the Credit Card Type for acceptance and the validity of the Credit Card Number & Expiration Date
         *
         */
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
         *
         */
        function before_process()
        {
            global $response, $db, $order, $messageStack;

            
            $this->login = trim(MODULE_PAYMENT_AUTHORIZENET_CIM_LOGIN);
            $this->transkey = trim(MODULE_PAYMENT_AUTHORIZENET_CIM_TXNKEY);
            $this->test_mode = (MODULE_PAYMENT_AUTHORIZENET_CIM_TESTMODE == 'Test' ? true : false);
            //$subdomain = ($this->test_mode) ? 'apitest' : 'api2';
            $this->url = "https://" . (($this->test_mode) ? 'apitest' : 'api2') . ".authorize.net/xml/v1/request.api";
            $this->validationMode = MODULE_PAYMENT_AUTHORIZENET_CIM_VALIDATION;
    
    
            $this->setParameter('paymentType', 'creditCard');
            $this->setParameter('cardNumber', $_POST['cc_number']);
            $this->setParameter('cc_save', $_POST['cc_save']);
            $this->setParameter('expirationDate', $_POST['cc_expires_date']); // (YYYY-MM)
            $this->setParameter('billTo_firstName', $order->billing['firstname']); // Up to 50 characters (no symbols)
            $this->setParameter('billTo_lastName', $order->billing['lastname']); // Up to 50 characters (no symbols)
            // $this->setParameter('billTo_company', $order->billing['company']); // Up to 50 characters (no symbols) (optional)
            $this->setParameter('billTo_address',
              $order->billing['street_address']); // Up to 60 characters (no symbols)
            $this->setParameter('billTo_city', $order->billing['city']); // Up to 40 characters (no symbols)
            //	$this->setParameter('billTo_state', $order->billing['state']); // A valid two-character state code (US only) (optional)
            $this->setParameter('billTo_zip', $order->billing['postcode']); // Up to 20 characters (no symbols)
            $this->setParameter('billTo_country',
              $order->billing['country']['title']); // Up to 60 characters (no symbols) (optional)
            $this->setParameter('shipTo_firstName', $order->delivery['firstname']); // Up to 50 characters (no symbols)
            $this->setParameter('shipTo_lastName', $order->delivery['lastname']); // Up to 50 characters (no symbols)
            // $this->setParameter('shipTo_company', 'Acme, Inc.'); // Up to 50 characters (no symbols) (optional)
            $this->setParameter('shipTo_address',
              $order->delivery['street_address']); // Up to 60 characters (no symbols)
            $this->setParameter('shipTo_city', $order->delivery['city']); // Up to 40 characters (no symbols)
            //	$this->setParameter('shipTo_state', $order->delivery['state']); // A valid two-character state code (US only) (optional)
            $this->setParameter('shipTo_zip', $order->delivery['postcode']); // Up to 20 characters (no symbols)
            $this->setParameter('shipTo_country',
              $order->delivery['country']['title']); // Up to 60 characters (no symbols) (optional)
            // $this->setParameter('refId', $_SESSION['customer_id']); // Up to 20 characters (optional)
            $this->setParameter('merchantCustomerId', $_SESSION['customer_id']); // Up to 20 characters (optional)
            $this->setParameter('email', $order->customer['email_address']); // Up to 255 characters (optional)
            $this->setParameter('customerType', 'individual'); // individual or business (optional)
            
            
            $order->info['cc_number'] = str_pad(substr($_POST['cc_number'], -4), strlen($_POST['cc_number']), "X",
              STR_PAD_LEFT);
            $order->info['cc_expires'] = $_POST['cc_expires'];
            $order->info['cc_type'] = $_POST['cc_type'];
            $order->info['cc_owner'] = $_POST['cc_owner'];
            $order->info['cc_cvv'] = ''; //$_POST['cc_cvv'];
            $order->info['cc_owner'] = $_POST['cc_owner'];
            
            $customerID = $_SESSION['customer_id'];
            $customerProfileId = getCustomerProfile($customerID);
            
            if ($customerProfileId == false) {
                unset($response);
                $this->createCustomerProfileRequest();
            } else {
                $this->setParameter('customerProfileId', $customerProfileId);
            }
            
            $this->checkErrors('Customer Profile');
            unset($response);
    
            $this->createCustomerPaymentProfileRequest();
            
            $this->checkErrors('Customer Payment Profile');
            unset($response);
            
            $this->createCustomerProfileTransactionRequest();
            
            $this->checkErrors('Customer Payment Transaction');
        }
    
        function checkErrors($type)
        {
            global $messageStack;
            if (isset($this->error_messages) && (!empty($this->error_messages))) {
                foreach ($this->error_messages as $error) {
                    $messageStack->add_session('checkout_payment', 'CIM payment ' . $type . ': ' . $error, 'error');
                }
                zen_redirect(zen_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL', true, false));
            }
        }
        
        function setParameter($field = "", $value = null)
        {
            $this->params[$field] = $value;
        }
        
        /**
         * Post-process activities. Updates the order-status history data with the auth code from the transaction.
         *
         * @return boolean
         */
        function after_process()
        {
            global $insert_id, $db, $order, $customerID;
            
            $sql = "update  so_payments set orders_id = :insertID
            WHERE customers_id = :custId and transaction_id = :transID and orders_id = 0";
            $sql = $db->bindVars($sql, ':custId', $_SESSION['customer_id'], 'string');
            $sql = $db->bindVars($sql, ':transID', $this->transID, 'string');
            $sql = $db->bindVars($sql, ':insertID', $insert_id, 'integer');
            $db->Execute($sql);
    
            $sql = "update " . TABLE_ORDERS . "
        	set approval_code = :approvalCode, transaction_id = :transID, cc_authorized = '1',
        	payment_profile_id = :payProfileID,
        	cc_authorized_date = '" . date("Y-m-d H:i:s") . "'
        	WHERE orders_id = :insertID ";
            $sql = $db->bindVars($sql, ':approvalCode', $this->approvalCode, 'string');
            $sql = $db->bindVars($sql, ':transID', $this->transID, 'string');
            $sql = $db->bindVars($sql, ':insertID', $insert_id, 'integer');
            $sql = $db->bindVars($sql, ':payProfileID', $this->params['customerPaymentProfileId'], 'integer');
            $db->Execute($sql);
            
            $sql = "insert into " . TABLE_ORDERS_STATUS_HISTORY . " (comments, orders_id, orders_status_id, date_added) values (:orderComments, :orderID, :orderStatus, now() )";
            $sql = $db->bindVars($sql, ':orderComments',
              'Credit Card payment.  AUTH: ' . $this->approvalCode . '. TransID: ' . $this->transID . '.',
              'string');
            $sql = $db->bindVars($sql, ':orderID', $insert_id, 'integer');
            $sql = $db->bindVars($sql, ':orderStatus', $this->order_status, 'integer');
            $db->Execute($sql);
            return false;
        }
        
        function createCustomerProfileRequest()
        {
            global $customerID, $order, $cust_payment_id;
            
            $customerID = $_SESSION['customer_id'];
            
            $data = customer_profile($customerID, $order->customer['email_address']);
            $this->xml = request_xml('createCustomerProfileRequest', $data);
            $this->process();
            
            if ($this->isSuccessful()) {
                updateCustomer($customerID, $this->customerProfileId);
                $this->setParameter('customerProfileId', $this->customerProfileId);
            } else {
                $this->log = 'createCustomerProfileRequest: ' . $customerID . '; Error: ' . $this->cim_code . ' ' . $this->text;
                $this->die_message = 'no success - customer profile request';
                $this->logError();
                if ($this->cim_code == 'E00039') {
                    $cust_payment_id = substr($this->text, (strpos($this->text, 'with ID ') + 8),
                      (strpos($this->text, ' already exists') - (strpos($this->text, 'with ID ') + 8)));
                    updateCustomer($customerID, $cust_payment_id);
                    $this->setParameter('customerProfileId', $cust_payment_id);
                }
            }
        }
    
        function createCustomerPaymentProfileRequest()
        {
            global $insert_id, $db, $order, $customerID;
        
            if ($this->params['customerProfileId'] == 0 || (!isset($this->params['customerProfileId']))) {
                $customerProfileId = getCustomerProfile($customerID);
                if ($customerProfileId == false) {
                    $this->createCustomerProfileRequest();
                    $this->setParameter('customerProfileId', $this->customerProfileId);
                } else {
                    $this->setParameter('customerProfileId', $customerProfileId);
                }
            }
    
            // here we are checking to see if the customer already has this card on file.
            // if we have it, return that profile TODO incorporate expiration date and update paymentProfile
            $this->customerPaymentProfileId = getCustomerPaymentProfile($customerID,
              substr(trim($_POST['cc_number']), -4));
    
            if (!is_null($this->customerPaymentProfileId)) {
                $this->setParameter('customerPaymentProfileId', $this->customerPaymentProfileId);
                return;
            }
        
            $data = customer_payment_profile($this->params['customerProfileId'], $order, $this->validationMode);
            $this->xml = request_xml('createCustomerPaymentProfileRequest', $data);
            $this->process();
        
            if ($this->isSuccessful()) {
                $this->setParameter('customerPaymentProfileId', $this->customerPaymentProfileId);
                $save = 'N';
                if ($_POST['cc_save'] == 'on') {
                    $save = 'Y';
                }
                
                save_cc_token($customerID, $this->customerPaymentProfileId, substr($_POST['cc_number'], -4), $_POST['cc_expires'], $save);
           
                $order->info['payment_profile_id'] = $this->customerPaymentProfileId;
    
            /*
                if (!isset($this->params['card_update'])) {
                    $exp_date = strip_tags($this->expirationDate());
                    $exp_date = substr($exp_date, -2) . substr($exp_date, 2, 2);
                    if ($order->info['admin'] == 'NEW') {
                        $sql = "UPDATE " . TABLE_ORDERS . "
        	    SET payment_profile_id = '" . (int)$this->customerPaymentProfileId . "',
        	    cc_number = 'xxxx-xxxx-xxxx-" . substr(strip_tags($this->cardNumber()), -4) . "',
        	    save_cc_data = 'Y', cc_expires = :ccexp,
        	    payment_module_code = 'authorizenet_cim', payment_method = 'authorizenet_cim'
        	    WHERE orders_id = :orderID ";
                    } else {
                        $sql = "UPDATE " . TABLE_ORDERS . "
                SET payment_profile_id = '" . (int)$this->customerPaymentProfileId . "',
                cc_number = 'xxxx-xxxx-xxxx-" . substr(strip_tags($this->cardNumber()), -4) . "',
                save_cc_data = '" . $save . "', cc_expires = :ccexp
                WHERE orders_id = :orderID ";
                    }
                    $sql = $db->bindVars($sql, ':orderID', $insert_id, 'integer');
                    $sql = $db->bindVars($sql, ':ccexp', $exp_date, 'integer');
                    if ($insert_id > 0) {
                        $db->Execute($sql);
                        $this->log = $sql;
                        $this->die_message = 'TEMP: - customerPaymentProfileRequest:';
                    }
                    //$this->logError();
                } */
            } else {
                $this->log = 'PaymentProfileRequest. Order: ' . $insert_id . '; Error: ' . $this->cim_code . ' ' . $this->text;
                $this->die_message = 'customerPaymentProfileRequest';
                $this->logError();
                if ($this->cim_code == 'E00039') {
                    /*$sql = "select * from " . TABLE_CUSTOMERS_CC . " where customers_id = :customerID and last_four = '" . substr($this->params['cardNumber'], -4) . "' order by payment_profile_id desc limit 1";
                    $sql = "UPDATE " . TABLE_ORDERS . "
                  SET payment_profile_id = :cppID, cc_number = :ccNumber, cc_expires = :ccExp, save_cc_data = 'Y'
                  WHERE orders_id = :orderID ";
                    $sql = $db->bindVars($sql, ':orderID', $insert_id, 'integer');
                    $sql = $db->bindVars($sql, ':cppID', $this->customerPaymentProfileId, 'integer');
                    $sql = $db->bindVars($sql, ':ccNumber',
                      'xxxx-xxxx-xxxx-' . substr(strip_tags($this->cardNumber()), -4), 'string');
                    $sql = $db->bindVars($sql, ':ccExp',
                      substr(strip_tags($this->expirationDate()), -2) . substr(strip_tags($this->expirationDate()), 2,
                        2), 'string');
                    $db->Execute($sql);
                    $this->log = $sql; */
                    $sql = "select * from " . TABLE_CUSTOMERS_CC . " where customers_id = :customerID and payment_profile_id = :ppid order by payment_profile_id desc limit 1";
                    $sql = $db->bindVars($sql, ':ppid', $this->customerPaymentProfileId, 'integer');
                    $sql = $db->bindVars($sql, ':customerID', $customerID, 'integer');
                    $dup_on_file = $db->Execute($sql);
                    if ($dup_on_file->RecordCount() == 0) {
                        $sql = "INSERT " . TABLE_CUSTOMERS_CC . " (customers_id, payment_profile_id, last_four, exp_date, enabled, card_last_modified)
                  values ('" . $customerID . "',  '" . (int)$this->customerPaymentProfileId . "', '" . substr(strip_tags($this->cardNumber()),
                            -4) . "', '" . strip_tags($this->expirationDate()) . "', 'Y', now())";
                        $db->Execute($sql);
                        $this->log = $sql;
                    } else {
                        if ($dup_on_file->fields['exp_date'] != strip_tags($this->expirationDate())) {
                            $this->log = 'trying to update CC expiration date off new CC number';
                            $this->die_message = 'customerPaymentProfileRequest';
                            $this->logError();
                            $this->setParameter('customerPaymentProfileId', $dup_on_file->fields['payment_profile_id']);
                            $this->updateCustomerPaymentProfileRequest();
                        }
                    }
                }
            }
        }

        
        function refId()
        {
            if (isset($this->params['refId'])) {
                if ((strlen($this->params['refId']) > 0)
                  && (strlen($this->params['refId']) <= 20)) {
                    return "<refId>" . $this->params['refId'] . "</refId>";
                } else {
                    $this->error_messages[] .= 'setParameter(): refId must be up to 20 characters';
                }
            }
        }
        

        function merchantCustomerId()
        {
            if (isset($this->params['merchantCustomerId'])) {
                if ((strlen($this->params['merchantCustomerId']) > 0) && (strlen($this->params['merchantCustomerId']) <= 20)) {
                    return "<merchantCustomerId>" . $this->params['merchantCustomerId'] . "</merchantCustomerId>";
                } else {
                    $this->error_messages[] .= 'setParameter(): merchantCustomerId must be up to 20 characters in length';
                }
            }
        }
        
        function description()
        {
            if (isset($this->params['description'])) {
                if ((strlen($this->params['description']) > 0) && (strlen($this->params['description']) <= 255)) {
                    return "<description>" . $this->params['description'] . "</description>";
                } else {
                    $this->error_messages[] .= 'setParameter(): description must be up to 255 characters in length';
                }
            }
        }
        
        function process($retries = 3)
        {
            // before we make a connection, lets check if there are basic validation errors
            if (count($this->error_messages) == 0) {
                $count = 0;
                while ($count < $retries) {
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $this->url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: text/xml"));
                    curl_setopt($ch, CURLOPT_HEADER, 1);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $this->xml);
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
                    
                    curl_setopt($ch,CURLOPT_HEADER,false);
                    // proxy option for godaddy hosted customers (required)
                    //curl_setopt($ch, CURLOPT_PROXY,"http://proxy.shr.secureserver.net:3128");
                    $this->response = curl_exec($ch);
                    $this->curlError = curl_error($ch);
                    $this->parseResults();
                    
                    if ($this->resultCode == "Ok") {
                        $this->success = true;
                        $this->error = false;
                        break;
                    } else {
                        $this->success = false;
                        $this->error = true;
                        break;
                    }
                    
                    $count++;
                }
    
                if (DEBUG_CIM) {
                    //$this->logError(true);
                }
                curl_close($ch);
            } else {
                $this->success = false;
                $this->error = true;
            }
        }
    
        function customParse($xmlTag)
        {
            foreach ($this->dom->getElementsByTagName($xmlTag) as $element) {
                return $element->nodeValue;
            }
        }
        
        function parseResults()
        {
            $this->dom = new DOMDocument();
            $this->dom->loadXML($this->response);
    
            $this->resultCode = $this->customParse('resultCode');
            $this->cim_code = $this->customParse('code');
            $this->text = $this->customParse('text');
            $this->refId = $this->customParse('refId');
            $this->customerProfileId = $this->customParse('customerProfileId');
            $this->customerPaymentProfileId = $this->customParse('customerPaymentProfileId');
            $this->customerAddressId = $this->customParse('customerAddressId');
            $this->directResponse = $this->customParse('directResponse');
            $this->validationDirectResponse = $this->customParse('validationDirectResponse');
            
            $allowed_errors = array('E00039');
            
            if ($this->resultCode == 'Error' and (!in_array($this->cim_code, $allowed_errors))) {
                array_push($this->error_messages, $this->cim_code . ': ' . $this->text);
            }

//            $this->directResponse = $this->substring_between($this->response, '<directResponse>', '</directResponse>');

            if (!empty($this->directResponse)) {
                $array = explode($this->responseDelimiter, $this->directResponse);
                $this->directResponse = $array[3];
                $this->approvalCode = $array[4];
                $this->transID = $array[6];
            }
            if (!empty($this->validationDirectResponse)) {
                $array = explode($this->responseDelimiter, $this->validationDirectResponse);
                $this->validationDirectResponse = $array[3];
            }
            
        }
        
        function substring_between($haystack, $start, $end)
        {
            if (strpos($haystack, $start) === false || strpos($haystack, $end) === false) {
                return false;
            } else {
                $start_position = strpos($haystack, $start) + strlen($start);
                $end_position = strpos($haystack, $end);
                return substr($haystack, $start_position, $end_position - $start_position);
            }
        }
        
        function isSuccessful()
        {
            return $this->success;
        }
        
        function logError($log_xml = false)
        {
            global $messageStack, $order, $lookXML;
            
            $error_log = DIR_FS_LOGS . '/cim_error.log';
            $error_sent = DIR_FS_LOGS . '/cim_xml_sent.log';
            
            if (IS_ADMIN_FLAG && !$lookXML) {
                $messageStack->add_session('CIM: ' . $this->error_messages[0] . '; ->' . $this->log, 'error');
            }
            
            if ($log_xml) {
                error_log(date(DATE_RFC822) . ': ' . $this->xml . "\n", 3, $error_sent);
                //error_log(date(DATE_RFC822) . ': ' . $order->info . "\n", 3, $error_sent);
            }
            
            if (!empty($this->log) || !empty($this->error_messages[0])) {
                error_log(date(DATE_RFC822) . ': ' . $this->error_messages[0] . '; ->' . $this->log . "\n", 3,
                  $error_log);
            }
            if ($this->cim_code == 'E00003') {
                error_log(date(DATE_RFC822) . ': ' . $this->error_messages[0] . '; XML----->' . $this->xml . "\n", 3,
                  $error_log);
            }
            if ($this->cim_code == 'E00039') {
                //error_log(date(DATE_RFC822) . ': Response:' . $this->response . "\n", 3, $error_log);
            }
            //sleep(2);
        }
    

        
        function setParamsAdmin()
        {
            global $order, $db, $insert_id;
            
            $this->setParameter('shipTo_firstName', $order->delivery['firstname']); // Up to 50 characters (no symbols)
            $this->setParameter('shipTo_lastName', $order->delivery['lastname']); // Up to 50 characters (no symbols)
            // $this->setParameter('shipTo_company', 'Acme, Inc.'); // Up to 50 characters (no symbols) (optional)
            $this->setParameter('shipTo_address',
              $order->delivery['street_address']); // Up to 60 characters (no symbols)
            $this->setParameter('shipTo_city', $order->delivery['city']); // Up to 40 characters (no symbols)
            //$this->setParameter('shipTo_state', $order->delivery['state']); // A valid two-character state code (US only) (optional)
            $this->setParameter('shipTo_zip', $order->delivery['postcode']); // Up to 20 characters (no symbols)
            $this->setParameter('shipTo_country',
              $order->delivery['country']); // Up to 60 characters (no symbols) (optional)
            $this->setParameter('customerProfileId',
              $order->billing['customerProfileId']); // Up to 20 characters (optional)
            $this->setParameter('paymentType', 'creditcard');
            $this->setParameter('cardNumber', $order->info['cc_number']);
            $this->setParameter('transactionCardCode', $order->info['cc_ccv']);
            if (isset($this->params['customerPaymentProfileId'])) {
                $this->setParameter('customerPaymentProfileId', strip_tags($this->params['customerPaymentProfileId']));
            } else {
                $this->setParameter('customerPaymentProfileId', strip_tags($order->info['payment_profile_id']));
            }
            $this->setParameter('expirationDate',
              '20' . substr($order->info['cc_expires'], 2, 2) . '-' . substr($order->info['cc_expires'], 0, 2));
            $this->setParameter('validationMode', $this->validationMode);
            $this->setParameter('billTo_firstName', $order->billing['firstname']); // Up to 50 characters (no symbols)
            $this->setParameter('billTo_lastName', $order->billing['lastname']); // Up to 50 characters (no symbols)
            // $this->setParameter('billTo_company', $order->billing['company']); // Up to 50 characters (no symbols) (optional)
            $this->setParameter('billTo_address',
              $order->billing['street_address']); // Up to 60 characters (no symbols)
            $this->setParameter('billTo_city', $order->billing['city']); // Up to 40 characters (no symbols)
            //$this->setParameter('billTo_state', $order->billing['state']); // A valid two-character state code (US only) (optional)
            $this->setParameter('billTo_zip', $order->billing['postcode']); // Up to 20 characters (no symbols)
            $this->setParameter('billTo_country',
              $order->billing['country']); // Up to 60 characters (no symbols) (optional)
            
            $this->setParameter('merchantCustomerId', $order->customer['id']); // Up to 20 characters (optional)
            $this->setParameter('email', $order->customer['email_address']);
            
            $insert_id = $order->info['orders_id'];
            
            if (isset($this->params['delete_customer_id'])) {
                $sql = "select customers_customerProfileId from " . TABLE_CUSTOMERS . "
		where customers_id = :del_customer_id";
                $sql = $db->bindVars($sql, ':del_customer_id', $this->params['delete_customer_id'], 'integer');
                $delete_customer = $db->Execute($sql);
                $this->setParameter('customerProfileId', $delete_customer->fields['customers_customerProfileId']);
            }
        }
        
        function customerProfileId()
        {
            if (isset($this->params['customerProfileId'])) {
                if (preg_match('/^[0-9]+$/', $this->params['customerProfileId'])) {
                    return "<customerProfileId>" . $this->params['customerProfileId'] . "</customerProfileId>";
                } else {
                    $this->error_messages[] .= 'setParameter(): customerProfileId must be numeric';
                }
            } else {
                $this->error_messages[] .= 'setParameter(): customerProfileId is required and must be numeric';
            }
        }
        
        function customerType()
        {
            if (isset($this->params['customerType'])) {
                if (preg_match('/^(individual|business)$/i', $this->params['customerType'])) {
                    return "<customerType>" . strtolower($this->params['customerType']) . "</customerType>";
                } else {
                    $this->error_messages[] .= 'setParameter(): customerType must be (individual or business)';
                }
            }
        }

        function billTo_firstName()
        {
            if (isset($this->params['billTo_firstName'])) {
                if ($this->update === true) {
                    return "<firstName>" . $this->params['billTo_firstName'] . "</firstName>";
                } else {
                    if ((strlen($this->params['billTo_firstName']) > 0) && (strlen($this->params['billTo_firstName']) <= 50)) {
                        return "<firstName>" . $this->params['billTo_firstName'] . "</firstName>";
                    } else {
                        $this->error_messages[] .= 'setParameter(): billTo_firstName must be up to 50 characters (no symbols)';
                    }
                }
            }
        }
        
        function billTo_lastName()
        {
            
            if (isset($this->params['billTo_lastName'])) {
                if ($this->update === true) {
                    return "<lastName>" . $this->params['billTo_lastName'] . "</lastName>";
                } else {
                    if ((strlen($this->params['billTo_lastName']) > 0) && (strlen($this->params['billTo_lastName']) <= 50)) {
                        return "<lastName>" . $this->params['billTo_lastName'] . "</lastName>";
                    } else {
                        $this->error_messages[] .= 'setParameter(): billTo_lastName must be up to 50 characters (no symbols)';
                    }
                }
            }
        }
        

        function billTo_company()
        {
            if (isset($this->params['billTo_company'])) {
                if ($this->update === true) {
                    return "<company>" . $this->params['billTo_company'] . "</company>";
                } else {
                    if ((strlen($this->params['billTo_company']) > 0) && (strlen($this->params['billTo_company']) <= 50)) {
                        return "<company>" . $this->params['billTo_company'] . "</company>";
                    } else {
                        $this->error_messages[] .= 'setParameter(): billTo_company must be up to 50 characters (no symbols)';
                    }
                }
            }
        }
        

        function billTo_address()
        {
            if (isset($this->params['billTo_address'])) {
                if ($this->update === true) {
                    return "<address>" . $this->params['billTo_address'] . "</address>";
                } else {
                    if ((strlen($this->params['billTo_address']) > 0) && (strlen($this->params['billTo_address']) <= 60)) {
                        return "<address>" . $this->params['billTo_address'] . "</address>";
                    } else {
                        $this->error_messages[] .= 'setParameter(): billTo_address must be up to 60 characters (no symbols)';
                    }
                }
            }
        }
        

        function billTo_city()
        {
            if (isset($this->params['billTo_city'])) {
                if ($this->update === true) {
                    return "<city>" . $this->params['billTo_city'] . "</city>";
                } else {
                    if ((strlen($this->params['billTo_city']) > 0) && (strlen($this->params['billTo_city']) <= 40)) {
                        return "<city>" . $this->params['billTo_city'] . "</city>";
                    } else {
                        $this->error_messages[] .= 'setParameter(): billTo_city must be up to 40 characters (no symbols)';
                    }
                }
            }
        }
        
        
        function billTo_state()
        {
            if (isset($this->params['billTo_state'])) {
                if ($this->update === true) {
                    return "<state>" . $this->params['billTo_state'] . "</state>";
                } else {
                    if (preg_match('/^[a-z]{2}$/i', $this->params['billTo_state'])) {
                        return "<state>" . $this->params['billTo_state'] . "</state>";
                    } else {
                        $this->error_messages[] .= 'setParameter(): billTo_state must be a valid two-character state code';
                    }
                }
            }
        }
        
        function billTo_zip()
        {
            if (isset($this->params['billTo_zip'])) {
                if ($this->update === true) {
                    return "<zip>" . $this->params['billTo_zip'] . "</zip>";
                } else {
                    if ((strlen($this->params['billTo_zip']) > 0) && (strlen($this->params['billTo_zip']) <= 20)) {
                        return "<zip>" . $this->params['billTo_zip'] . "</zip>";
                    } else {
                        $this->error_messages[] .= 'setParameter(): billTo_zip must be up to 20 characters (no symbols)';
                    }
                }
            }
        }
        
        function billTo_country()
        {
            if (isset($this->params['billTo_country'])) {
                if ($this->update === true) {
                    return "<country>" . $this->params['billTo_country'] . "</country>";
                } else {
                    if ((strlen($this->params['billTo_country']) > 0) && (strlen($this->params['billTo_country']) <= 60)) {
                        return "<country>" . $this->params['billTo_country'] . "</country>";
                    } else {
                        $this->error_messages[] .= 'setParameter(): billTo_country must be up to 60 characters (no symbols)';
                    }
                }
            }
        }
        
        function billTo_phoneNumber()
        {
            if (isset($this->params['billTo_phoneNumber'])) {
                if ($this->update === true) {
                    return "<phoneNumber>" . $this->params['billTo_phoneNumber'] . "</phoneNumber>";
                } else {
                    if ((strlen($this->params['billTo_phoneNumber']) > 0) && (strlen($this->params['billTo_phoneNumber']) <= 25)) {
                        return "<phoneNumber>" . $this->params['billTo_phoneNumber'] . "</phoneNumber>";
                    } else {
                        $this->error_messages[] .= 'setParameter(): billTo_phoneNumber must be up to 25 digits (no letters). Ex. (123)123-1234';
                    }
                }
            }
        }
        
        function billTo_faxNumber()
        {
            if (isset($this->params['billTo_faxNumber'])) {
                if ($this->update === true) {
                    return "<faxNumber>" . $this->params['billTo_faxNumber'] . "</faxNumber>";
                } else {
                    if ((strlen($this->params['billTo_faxNumber']) > 0) && (strlen($this->params['billTo_faxNumber']) <= 25)) {
                        return "<faxNumber>" . $this->params['billTo_faxNumber'] . "</faxNumber>";
                    } else {
                        $this->error_messages[] .= 'setParameter(): billTo_faxNumber must be up to 25 digits (no letters). Ex. (123)123-1234';
                    }
                }
            }
        }
        
        function paymentType()
        {
            if (isset($this->params['paymentType'])) {
                if (($this->params['paymentType'] == "echeck")
                  || ($this->params['paymentType'] == "bankAccount")) {
                    return "
				<bankAccount>
					" . $this->accountType() . "
					" . $this->routingNumber() . "
					" . $this->accountNumber() . "
					" . $this->nameOnAccount() . "
					" . $this->echeckType() . "
					" . $this->bankName() . "
				</bankAccount>";
                } elseif (($this->params['paymentType'] == "creditcard")
                  || ($this->params['paymentType'] == "creditCard")) {
                    return "
				<creditCard>
					" . $this->cardNumber() . "
					" . $this->expirationDate() . "
				</creditCard>";
                } else {
                    $this->error_messages[] .= 'setParameter(): paymentType is required and must be (bankAccount or creditCard)';
                }
            } else {
                $this->error_messages[] .= 'setParameter(): paymentType is required and must be (bankAccount or creditCard)';
            }
        }
        
        // This function is used to retrieve a customer shipping address for an existing customer profile.

        function accountType()
        {
            if (isset($this->params['accountType'])) {
                if ($this->update === true) {
                    return "<accountType>" . $this->params['accountType'] . "</accountType>";
                } else {
                    if (preg_match('/^(checking|savings|businessChecking)$/', $this->params['accountType'])) {
                        return "<accountType>" . $this->params['accountType'] . "</accountType>";
                    } else {
                        $this->error_messages[] .= 'setParameter(): accountType is required and must be (checking, savings or businessChecking)';
                    }
                }
            } else {
                $this->error_messages[] .= 'setParameter(): accountType is required and must be (checking, savings or businessChecking)..';
            }
        }
        
        // This function is used to update an existing customer profile.

        function routingNumber()
        {
            if (isset($this->params['routingNumber'])) {
                if ($this->update === true) {
                    return "<routingNumber>" . $this->params['routingNumber'] . "</routingNumber>";
                } else {
                    if (preg_match('/^[0-9]{9}$/', $this->params['routingNumber'])) {
                        return "<routingNumber>" . $this->params['routingNumber'] . "</routingNumber>";
                    } else {
                        $this->error_messages[] .= 'setParameter(): routingNumber is required and must be 9 digits';
                    }
                }
            } else {
                $this->error_messages[] .= 'setParameter(): routingNumber is required and must be 9 digits..';
            }
        }
        
        // This function is used to update a customer payment profile for an existing customer profile.

        function accountNumber()
        {
            if (isset($this->params['accountNumber'])) {
                if ($this->update === true) {
                    return "<accountNumber>" . $this->params['accountNumber'] . "</accountNumber>";
                } else {
                    if (preg_match('/^[0-9]{5,17}$/', $this->params['accountNumber'])) {
                        return "<accountNumber>" . $this->params['accountNumber'] . "</accountNumber>";
                    } else {
                        $this->error_messages[] .= 'setParameter(): accountNumber is required and must be 5 to 17 digits';
                    }
                }
            } else {
                $this->error_messages[] .= 'setParameter(): accountNumber is required and must be 5 to 17 digits..';
            }
        }
        
        // This function is used to update a shipping address for an existing customer profile.

        function nameOnAccount()
        {
            if (isset($this->params['nameOnAccount'])) {
                if ($this->update === true) {
                    return "<nameOnAccount>" . $this->params['nameOnAccount'] . "</nameOnAccount>";
                } else {
                    if ((strlen($this->params['nameOnAccount']) > 0) && (strlen($this->params['nameOnAccount']) <= 22)) {
                        return "<nameOnAccount>" . $this->params['nameOnAccount'] . "</nameOnAccount>";
                    } else {
                        $this->error_messages[] .= 'setParameter(): nameOnAccount is required and must be up to 22 characters in length';
                    }
                }
            } else {
                $this->error_messages[] .= 'setParameter(): nameOnAccount is required and must be up to 22 characters in length..';
            }
        }
        
        // This function is used to verify an existing customer payment profile by generating a test transaction.

        function echeckType()
        {
            if (isset($this->params['echeckType'])) {
                if ($this->update === true) {
                    return "<echeckType>" . $this->params['echeckType'] . "</echeckType>";
                } else {
                    if (preg_match('/^(CCD|PPD|TEL|WEB)$/', $this->params['echeckType'])) {
                        return "<echeckType>" . $this->params['echeckType'] . "</echeckType>";
                    } else {
                        $this->error_messages[] .= 'setParameter(): echeckType is required and must be (CCD, PPD, TEL or WEB)';
                    }
                }
            } else {
                $this->error_messages[] .= 'setParameter(): echeckType is required and must be (CCD, PPD, TEL or WEB)..';
            }
        }
        
        function bankName()
        {
            if (isset($this->params['bankName'])) {
                if ($this->update === true) {
                    return "<bankName>" . $this->params['bankName'] . "</bankName>";
                } else {
                    if ((strlen($this->params['bankName']) > 0) && (strlen($this->params['bankName']) <= 60)) {
                        return "<bankName>" . $this->params['bankName'] . "</bankName>";
                    } else {
                        $this->error_messages[] .= 'setParameter(): bankName is required and must be up to 50 characters in length';
                    }
                }
            } else {
                $this->error_messages[] .= 'setParameter(): bankName is required and must be up to 50 characters in length..';
            }
        }
        
        function cardNumber()
        {
            if (isset($this->params['cardNumber'])) {
                $this->params['cardNumber'] = str_replace('xxxx-xxxx-xxxx-', 'XXXXXXXXXXXX',
                  $this->params['cardNumber']);
                if ($this->update === true) {
                    return "<cardNumber>" . $this->params['cardNumber'] . "</cardNumber>";
                } else {
                    if (preg_match('/^[0-9]{13,16}$/', $this->params['cardNumber'])) {
                        return "<cardNumber>" . $this->params['cardNumber'] . "</cardNumber>";
                    } else {
                        $this->error_messages[] .= 'setParameter(): cardNumber is required and must be 13 to 16 digits';
                    }
                }
            } else {
                $this->error_messages[] .= 'setParameter(): cardNumber is required and must be 13 to 16 digits..';
            }
        }
        
        function expirationDate()
        {
            if (isset($this->params['expirationDate'])) {
                if ($this->update === true) {
                    return "<expirationDate>" . $this->params['expirationDate'] . "</expirationDate>";
                } else {
                    if (preg_match('/^([0-9]{4})-([0-9]{2})$/', $this->params['expirationDate'])) {
                        return "<expirationDate>" . $this->params['expirationDate'] . "</expirationDate>";
                    } else {
                        $this->error_messages[] .= 'setParameter(): expirationDate is required and must be YYYY-MM';
                    }
                }
            } else {
                $this->error_messages[] .= 'setParameter(): expirationDate is required and must be YYYY-MM..';
            }
        }
        
        function validationMode()
        {
            if (isset($this->params['validationMode'])) {
                if (preg_match('/^(none|testMode|liveMode)$/', $this->params['validationMode'])) {
                    return "<validationMode>" . $this->params['validationMode'] . "</validationMode>";
                } else {
                    $this->error_messages[] .= 'setParameter(): validationMode must be (none, testMode or liveMode)';
                }
            } else {
                $this->error_messages[] .= 'setParameter(): validationMode is required';
            }
        }
        
        function updateCustomerPaymentProfileRequest()
        {
            global $insert_id, $db, $order, $customerID, $messageStack;
            
            if ($order->info['admin'] == 'NEW') {
                $this->setParamsAdmin();
                $customerID = $order->info['customers_id'];
            } else {
                $customerID = $_SESSION['customer_id'];
            }
            
            $this->update = true;
            
            $this->xml = "<?xml version='1.0' encoding='utf-8'?>
	<updateCustomerPaymentProfileRequest xmlns='AnetApi/xml/v1/schema/AnetApiSchema.xsd'>
	<merchantAuthentication>
		<name>" . $this->login . "</name>
		<transactionKey>" . $this->transkey . "</transactionKey>
	</merchantAuthentication>
	" . $this->refId() . "
	" . $this->customerProfileId() . "
	<paymentProfile>
		" . $this->customerType() . "
		<billTo>
			" . $this->billTo_firstName() . "
			" . $this->billTo_lastName() . "
			" . $this->billTo_company() . "
			" . $this->billTo_address() . "
			" . $this->billTo_city() . "
			" . $this->billTo_state() . "
			" . $this->billTo_zip() . "
			" . $this->billTo_country() . "
			" . $this->billTo_phoneNumber() . "
			" . $this->billTo_faxNumber() . "
		</billTo>
		<payment>
			" . $this->paymentType() . "
		</payment>
	" . $this->customerPaymentProfileId() . "
	</paymentProfile>
	</updateCustomerPaymentProfileRequest>";
            $this->process();
            if ($this->isSuccessful()) {
                $save = 'N';
                if ($this->params['cc_save'] == 'on' || $order->info['admin'] == 'NEW') {
                    $save = 'Y';
                }
                $sql = "UPDATE " . TABLE_CUSTOMERS_CC . "
		    SET exp_date = '" . strip_tags($this->expirationDate()) . "', card_last_modified = now()
		    WHERE payment_profile_id = :paymentProfileID  and customers_id = :custID";
                
                $sql = $db->bindVars($sql, ':paymentProfileID', $this->params['customerPaymentProfileId'], 'integer');
                $sql = $db->bindVars($sql, ':custID', $this->params['merchantCustomerId'], 'integer');
                $db->Execute($sql);
                //$this->log = $sql;
                //$this->logError();
                
                if (!isset($this->params['card_update'])) {
                    $sql = "UPDATE " . TABLE_ORDERS . "
		    SET cc_number = 'xxxx-xxxx-xxxx-" . substr(strip_tags($this->cardNumber()), -4) . "',
		    cc_expires = '" . substr(strip_tags($this->expirationDate()), -2) . substr(strip_tags($this->expirationDate()),
                        2, 2) . "',
		    save_cc_data = '" . $save . "',
            payment_profile_id = :paymentProfileID
		    WHERE orders_id = :orderID ";
                    $sql = $db->bindVars($sql, ':orderID', $insert_id, 'integer');
                    $sql = $db->bindVars($sql, ':paymentProfileID', $this->params['customerPaymentProfileId'],
                      'integer');
                    $db->Execute($sql);
                }
            } else {
                //$this->log = 'updateCustomerPaymentProfileRequest. Order: ' . $order->info['orders_id'] . '; Error: ' . $this->cim_code . ' ' . $this->text;
                $this->log = 'updateCustomerPaymentProfileRequest. Order: ' . $insert_id . '; Error: ' . $this->cim_code . ' ' . $this->text;
                $this->die_message = 'updateCustomerPaymentProfileRequest';
                $messageStack->add_session('CIM: ' . $this->log, 'error');
                $this->logError();
                if ($this->cim_code == 'E00039') {
                    $sql = "select * from " . TABLE_CUSTOMERS_CC . " where last_four = '" . substr($this->params['cardNumber'],
                        -4) . "' limit 1";
                    $dup_on_file = $db->Execute($sql);
                    if ($dup_on_file->RecordCount() > 0) {
                        $sql = "UPDATE " . TABLE_ORDERS . "
			    SET payment_profile_id = '" . (int)$dup_on_file->fields['payment_profile_id'] . "',
			    save_cc_data = '" . $dup_on_file->fields['enabled'] . "'
			    WHERE orders_id = :orderID ";
                        $sql = $db->bindVars($sql, ':orderID', $insert_id, 'integer');
                        $db->Execute($sql);
                    }
                }
            }
        }
        
        // This function will output the proper xml for a paymentType: (echeck or creditcard)
        // The elements within "bankAccount" is still incorrect in the manual. I fixed it here.

        function customerPaymentProfileId()
        {
            if (isset($this->params['customerPaymentProfileId'])) {
                if ($this->update === true) {
                    return "<customerPaymentProfileId>" . $this->params['customerPaymentProfileId'] . "</customerPaymentProfileId>";
                } else {
                    if (preg_match('/^[0-9]+$/', $this->params['customerPaymentProfileId'])) {
                        return "<customerPaymentProfileId>" . $this->params['customerPaymentProfileId'] . "</customerPaymentProfileId>";
                    } else {
                        $this->error_messages[] .= 'setParameter(): customerPaymentProfileId is required and must be numeric';
                    }
                }
            } else {
                $this->error_messages[] .= 'setParameter(): customerPaymentProfileId is required and must be numeric..';
            }
        }
        
        // Merchant-assigned reference ID for the request (optional)

        function createCustomerShippingAddressRequest()
        {
            global $db, $order, $lookXML, $insert_id;
            $this->xml = "<?xml version='1.0' encoding='utf-8'?>
	    <createCustomerShippingAddressRequest xmlns='AnetApi/xml/v1/schema/AnetApiSchema.xsd'>
	    <merchantAuthentication>
		<name>" . $this->login . "</name>
		<transactionKey>" . $this->transkey . "</transactionKey>
	    </merchantAuthentication>
	    " . $this->refId() . "
	    " . $this->customerProfileId() . "
	    <address>
		" . $this->shipTo_firstName() . "
		" . $this->shipTo_lastName() . "
		" . $this->shipTo_company() . "
		" . $this->shipTo_address() . "
		" . $this->shipTo_city() . "
		" . $this->shipTo_state() . "
		" . $this->shipTo_zip() . "
		" . $this->shipTo_country() . "
		" . $this->shipTo_phoneNumber() . "
		" . $this->shipTo_faxNumber() . "
	    </address>
	    </createCustomerShippingAddressRequest>";
            
            $this->process();
            
            if ($this->isSuccessful()) {
                $sql = "UPDATE " . TABLE_ORDERS . "
	        SET CIM_address_id = '" . (int)$this->customerAddressId . "'
	        WHERE orders_id = :orderID ";
                $sql = $db->bindVars($sql, ':orderID', $insert_id, 'integer');
                $db->Execute($sql);
                
                $sql = "UPDATE address_book
			SET CIM_address_id = '" . (int)$this->customerAddressId . "'
	        WHERE address_book_id = '" . (int)$order->delivery['delivery_address_id'] . "'";
                $db->Execute($sql);
            } else {
                $this->log = 'createCustomerShippingAddressReq: ' . $insert_id . '; Add Id:' . $order->delivery['delivery_address_id'] . '; Error: ' . $this->cim_code . ' ' . $this->text;
                $this->die_message = 'createCustomerShippingAddressReq';
                //$this->logError();
                if ($this->cim_code == 'E00039') {
                    $lookXML = true;
                    $sql = "Select CIM_address_id from address_book
		      WHERE entry_street_address = '" . $this->params['shipTo_address'] . "'
			  AND entry_postcode = '" . $this->params['shipTo_zip'] . "'
		      AND customers_id = '" . $this->params['merchantCustomerId'] . "' ";
                    $add_id = $db->Execute($sql);
                    if ($add_id->fields['CIM_address_id'] != 0 && isset($add_id->fields['CIM_address_id'])) {
                        $sql = "UPDATE " . TABLE_ORDERS . "
		          SET CIM_address_id = '" . (int)$add_id->fields['CIM_address_id'] . "'
		          WHERE orders_id = :orderID ";
                        $sql = $db->bindVars($sql, ':orderID', $insert_id, 'integer');
                        $db->Execute($sql);
                        // new 2_2014
                        $sql = "UPDATE address_book
					SET CIM_address_id = '" . (int)$add_id->fields['CIM_address_id'] . "'
					WHERE address_book_id = '" . (int)$order->delivery['delivery_address_id'] . "'";
                        $db->Execute($sql);
                    }
                }
                $this->logError();
            }
        }
        
        // TransId vino_mod


        function shipTo_firstName()
        {
            if (isset($this->params['shipTo_firstName'])) {
                if ((strlen($this->params['shipTo_firstName']) > 0) && (strlen($this->params['shipTo_firstName']) <= 50)) {
                    return "<firstName>" . $this->params['shipTo_firstName'] . "</firstName>";
                } else {
                    $this->error_messages[] .= 'setParameter(): shipTo_firstName must be up to 50 characters (no symbols)';
                }
            }
        }
        
        // Contains tax information for the transaction (optional)

        function shipTo_lastName()
        {
            if (isset($this->params['shipTo_lastName'])) {
                if ((strlen($this->params['shipTo_lastName']) > 0) && (strlen($this->params['shipTo_lastName']) <= 50)) {
                    return "<lastName>" . $this->params['shipTo_lastName'] . "</lastName>";
                } else {
                    $this->error_messages[] .= 'setParameter(): shipTo_lastName must be up to 50 characters (no symbols)';
                }
            }
        }
        
        // The tax amount for the transaction (optional)
        // This amount must be included in the total amount for the transaction. Ex. 12.99 or 12.9999

        function shipTo_company()
        {
            if (isset($this->params['shipTo_company'])) {
                if ((strlen($this->params['shipTo_company']) > 0) && (strlen($this->params['shipTo_company']) <= 50)) {
                    return "<company>" . $this->params['shipTo_company'] . "</company>";
                } else {
                    $this->error_messages[] .= 'setParameter(): shipTo_company must be up to 50 characters (no symbols)';
                }
            }
        }
        
        // The name of the tax for the transaction (optional)

        function shipTo_address()
        {
            if (isset($this->params['shipTo_address'])) {
                if ((strlen($this->params['shipTo_address']) > 0) && (strlen($this->params['shipTo_address']) <= 60)) {
                    return "<address>" . $this->params['shipTo_address'] . "</address>";
                } else {
                    $this->error_messages[] .= 'setParameter(): shipTo_address must be up to 60 characters (no symbols)';
                }
            }
        }
        
        // The tax description for the transaction (optional)

        function shipTo_city()
        {
            if (isset($this->params['shipTo_city'])) {
                if ((strlen($this->params['shipTo_city']) > 0) && (strlen($this->params['shipTo_city']) <= 40)) {
                    return "<city>" . $this->params['shipTo_city'] . "</city>";
                } else {
                    $this->error_messages[] .= 'setParameter(): shipTo_city must be up to 40 characters (no symbols)';
                }
            }
        }
        
        // Contains tax information for the transaction (optional)

        function shipTo_state()
        {
            if (isset($this->params['shipTo_state'])) {
                if (preg_match('/^[a-z]{2}$/i', $this->params['shipTo_state'])) {
                    return "<state>" . $this->params['shipTo_state'] . "</state>";
                } else {
                    $this->error_messages[] .= 'setParameter(): shipTo_state must be a valid two-character state code';
                }
            }
        }
        
        // The shipping amount for the transaction (optional)
        // This amount must be included in the total amount for the transaction. Ex. 12.99 or 12.9999

        function shipTo_zip()
        {
            if (isset($this->params['shipTo_zip'])) {
                if ((strlen($this->params['shipTo_zip']) > 0) && (strlen($this->params['shipTo_zip']) <= 20)) {
                    return "<zip>" . $this->params['shipTo_zip'] . "</zip>";
                } else {
                    $this->error_messages[] .= 'setParameter(): shipTo_zip must be up to 20 characters (no symbols)';
                }
            }
        }
        
        // The name of the shipping for the transaction (optional)

        function shipTo_country()
        {
            if (isset($this->params['shipTo_country'])) {
                if ((strlen($this->params['shipTo_country']) > 0) && (strlen($this->params['shipTo_country']) <= 60)) {
                    return "<country>" . $this->params['shipTo_country'] . "</country>";
                } else {
                    $this->error_messages[] .= 'setParameter(): shipTo_country must be up to 60 characters (no symbols)';
                }
            }
        }
        
        // The shipping description for the transaction (optional)

        function shipTo_phoneNumber()
        {
            if (isset($this->params['shipTo_phoneNumber'])) {
                if ((strlen($this->params['shipTo_phoneNumber']) > 0) && (strlen($this->params['shipTo_phoneNumber']) <= 25)) {
                    return "<phoneNumber>" . $this->params['shipTo_phoneNumber'] . "</phoneNumber>";
                } else {
                    $this->error_messages[] .= 'setParameter(): shipTo_phoneNumber must be up to 25 digits (no letters). Ex. (123)123-1234';
                }
            }
        }
        
        // Contains duty information for the transaction (optional)

        function shipTo_faxNumber()
        {
            if (isset($this->params['shipTo_faxNumber'])) {
                if ((strlen($this->params['shipTo_faxNumber']) > 0) && (strlen($this->params['shipTo_faxNumber']) <= 25)) {
                    return "<faxNumber>" . $this->params['shipTo_faxNumber'] . "</faxNumber>";
                } else {
                    $this->error_messages[] .= 'setParameter(): shipTo_faxNumber must be up to 25 digits (no letters). Ex. (123)123-1234';
                }
            }
        }
        
        // The duty amount for the transaction (optional)
        // This amount must be included in the total amount for the transaction. Ex. 12.99 or 12.9999

        /**
         * Build admin-page components
         *
         * @param int $zf_order_id
         * @return string
         */
        function RENAME_admin_notification($zf_order_id)
        {
            global $db;
            $output = '';
            $cimdata->fields = array();
            require(DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/authorizenet/authorizenet_admin_notification.php');
            return $output;
        }
        
        // The name of the duty for the transaction (optional)

        /**
         * Used to display error message details
         *
         * @return array
         */
        function get_error()
        {
            $error = array(
              'title' => MODULE_PAYMENT_AUTHORIZENET_CIM_TEXT_ERROR,
              'error' => stripslashes(urldecode($_GET['error']))
            );
            return $error;
        }
        
        // The duty description for the transaction (optional)

        /**
         * Check to see whether module is installed
         *
         * @return boolean
         */
        function check()
        {
            global $db;
            if (!isset($this->_check)) {
                $check_query = $db->Execute("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_AUTHORIZENET_CIM_STATUS'");
                $this->_check = $check_query->RecordCount();
            }
            return $this->_check;
        }
        
        // Contains line item details about the order (optional)
        // Up to 30 distinct instances of this element may be included per transaction to describe items included in the order.
        // USAGE: see the example code for createCustomerProfileTransactionRequest() in the examples provided.

        /**
         * Install the payment module and its configuration settings
         *
         */
        function install()
        {
            global $db;
            $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Enable Authorize.net (CIM) Module', 'MODULE_PAYMENT_AUTHORIZENET_CIM_STATUS', 'True', 'Do you want to accept Authorize.net payments via the CIM Method?', '6', '1', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");
            $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Login ID', 'MODULE_PAYMENT_AUTHORIZENET_CIM_LOGIN', 'testing', 'The API Login ID used for the Authorize.net service', '6', '2', now())");
            $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added, use_function) values ('Transaction Key', 'MODULE_PAYMENT_AUTHORIZENET_CIM_TXNKEY', 'Test', 'Transaction Key used for encrypting TP data<br />(See your Authorizenet Account->Security Settings->API Login ID and Transaction Key for details.)', '6', '3', now(), 'zen_cfg_password_display')");
            $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added, use_function) values ('MD5 Hash', 'MODULE_PAYMENT_AUTHORIZENET_CIM_MD5HASH', '*Set A Hash Value at AuthNet Admin*', 'Encryption key used for validating received transaction data (MAX 20 CHARACTERS)', '6', '4', now(), 'zen_cfg_password_display')");
            /* v. 0.7 */
            $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added, use_function) values ('Reorder Authentication Passkey', 'MODULE_PAYMENT_AUTHORIZENET_CIM_PASSKEY', 'Authentication string', 'Simple passkey used for authenticating on reorder file  (MAX 20 CHARACTERS)', '6', '5', now(), 'zen_cfg_password_display')");
            $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Transaction Mode', 'MODULE_PAYMENT_AUTHORIZENET_CIM_TESTMODE', 'Test', 'Transaction mode used for processing orders', '6', '6', 'zen_cfg_select_option(array(\'Test\', \'Production\'), ', now())");
            $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Authorization Type', 'MODULE_PAYMENT_AUTHORIZENET_CIM_AUTHORIZATION_TYPE', 'Authorize', 'Do you want submitted credit card transactions to be authorized only, or authorized and captured?', '6', '7', 'zen_cfg_select_option(array(\'Authorize\', \'Authorize+Capture\'), ', now())");
            $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Enable Database Storage', 'MODULE_PAYMENT_AUTHORIZENET_CIM_STORE_DATA', 'True', 'Do you want to save the gateway communications data to the database?', '6', '8', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");
            $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Customer Notifications', 'MODULE_PAYMENT_AUTHORIZENET_CIM_EMAIL_CUSTOMER', 'False', 'Should Authorize.Net email a receipt to the customer?', '6', '9', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");
            $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Merchant Notifications', 'MODULE_PAYMENT_AUTHORIZENET_CIM_EMAIL_MERCHANT', 'False', 'Should Authorize.Net email a receipt to the merchant?', '6', '10', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");
            $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Request CVV Number', 'MODULE_PAYMENT_AUTHORIZENET_CIM_USE_CVV', 'True', 'Do you want to ask the customer for the card\'s CVV number', '6', '11', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");
            $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Validation Mode', 'MODULE_PAYMENT_AUTHORIZENET_CIM_VALIDATION', 'testMode', 'Validation Mode', '6', '12', 'zen_cfg_select_option(array(\'none\', \'testMode\', \'liveMode\'), ', now())");
            $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Sort order of display.', 'MODULE_PAYMENT_AUTHORIZENET_CIM_SORT_ORDER', '0', 'Sort order of display. Lowest is displayed first.', '6', '13', now())");
            $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Payment Zone', 'MODULE_PAYMENT_AUTHORIZENET_CIM_ZONE', '0', 'If a zone is selected, only enable this payment method for that zone.', '6', '14', 'zen_get_zone_class_title', 'zen_cfg_pull_down_zone_classes(', now())");
            $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Set Completed Order Status', 'MODULE_PAYMENT_AUTHORIZENET_CIM_ORDER_STATUS_ID', '0', 'Set the status of orders made with this payment module to this value', '6', '15', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
            $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Set Refunded Order Status', 'MODULE_PAYMENT_AUTHORIZENET_CIM_REFUNDED_ORDER_STATUS_ID', '1', 'Set the status of refunded orders to this value', '6', '16', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
            $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Debug Mode', 'MODULE_PAYMENT_AUTHORIZENET_CIM_DEBUGGING', 'Off', 'Would you like to enable debug mode?  A complete detailed log of failed transactions may be emailed to the store owner.', '6', '17', 'zen_cfg_select_option(array(\'Off\', \'Log File\', \'Log and Email\'), ', now())");
        }
        
        // Contains duty information for the transaction (optional)

        /**
         * Remove the module and all its settings
         *
         */
        function remove()
        {
            global $db;
            $db->Execute("delete from " . TABLE_CONFIGURATION . " where configuration_key like 'MODULE\_PAYMENT\_AUTHORIZENET_CIM\_%'");
        }
        
        // The merchant assigned invoice number for the transaction (optional)

        /**
         * Internal list of configuration keys used for configuration of the module
         *
         * @return array
         */
        function keys()
        {
            return array(
              'MODULE_PAYMENT_AUTHORIZENET_CIM_STATUS',
              'MODULE_PAYMENT_AUTHORIZENET_CIM_LOGIN',
              'MODULE_PAYMENT_AUTHORIZENET_CIM_TXNKEY',
              'MODULE_PAYMENT_AUTHORIZENET_CIM_MD5HASH',
              'MODULE_PAYMENT_AUTHORIZENET_CIM_PASSKEY',
              'MODULE_PAYMENT_AUTHORIZENET_CIM_TESTMODE',
              'MODULE_PAYMENT_AUTHORIZENET_CIM_AUTHORIZATION_TYPE',
              'MODULE_PAYMENT_AUTHORIZENET_CIM_STORE_DATA',
              'MODULE_PAYMENT_AUTHORIZENET_CIM_EMAIL_CUSTOMER',
              'MODULE_PAYMENT_AUTHORIZENET_CIM_EMAIL_MERCHANT',
              'MODULE_PAYMENT_AUTHORIZENET_CIM_USE_CVV',
              'MODULE_PAYMENT_AUTHORIZENET_CIM_VALIDATION',
              'MODULE_PAYMENT_AUTHORIZENET_CIM_SORT_ORDER',
              'MODULE_PAYMENT_AUTHORIZENET_CIM_ZONE',
              'MODULE_PAYMENT_AUTHORIZENET_CIM_ORDER_STATUS_ID',
              'MODULE_PAYMENT_AUTHORIZENET_CIM_REFUNDED_ORDER_STATUS_ID',
              'MODULE_PAYMENT_AUTHORIZENET_CIM_DEBUGGING'
            );
        }
        
        // The transaction description (optional)

        /**
         * Calculate validity of response
         */
        function calc_md5_response($trans_id = '', $amount = '')
        {
            if ($amount == '' || $amount == '0') {
                $amount = '0.00';
            }
            $validating = md5(MODULE_PAYMENT_AUTHORIZENET_CIM_MD5HASH . MODULE_PAYMENT_AUTHORIZENET_CIM_LOGIN . $trans_id . $amount);
            return strtoupper($validating);
        }
        
        // The merchant assigned purchase order number (optional)

        /**
         * Used to submit a refund for a given transaction.
         */
        function _doRefund($oID, $amount = 0)
        {
            global $db, $messageStack;
            $new_order_status = (int)MODULE_PAYMENT_AUTHORIZENET_CIM_REFUNDED_ORDER_STATUS_ID;
            if ($new_order_status == 0) {
                $new_order_status = 1;
            }
            $proceedToRefund = true;
            $refundNote = strip_tags(zen_db_input($_POST['refnote']));
            if (isset($_POST['refconfirm']) && $_POST['refconfirm'] != 'on') {
                $messageStack->add_session(MODULE_PAYMENT_AUTHORIZENET_CIM_TEXT_REFUND_CONFIRM_ERROR, 'error');
                $proceedToRefund = false;
            }
            if (isset($_POST['buttonrefund']) && $_POST['buttonrefund'] == MODULE_PAYMENT_AUTHORIZENET_CIM_ENTRY_REFUND_BUTTON_TEXT) {
                $refundAmt = (float)$_POST['refamt'];
                $new_order_status = (int)MODULE_PAYMENT_AUTHORIZENET_CIM_REFUNDED_ORDER_STATUS_ID;
                if ($refundAmt == 0) {
                    $messageStack->add_session(MODULE_PAYMENT_AUTHORIZENET_CIM_TEXT_INVALID_REFUND_AMOUNT, 'error');
                    $proceedToRefund = false;
                }
            }
            if (isset($_POST['cc_number']) && trim($_POST['cc_number']) == '') {
                $messageStack->add_session(MODULE_PAYMENT_AUTHORIZENET_CIM_TEXT_CC_NUM_REQUIRED_ERROR, 'error');
            }
            if (isset($_POST['trans_id']) && trim($_POST['trans_id']) == '') {
                $messageStack->add_session(MODULE_PAYMENT_AUTHORIZENET_CIM_TEXT_TRANS_ID_REQUIRED_ERROR, 'error');
                $proceedToRefund = false;
            }
            
            /**
             * Submit refund request to gateway
             */
            if ($proceedToRefund) {
                $submit_data = array(
                  'x_type' => 'CREDIT',
                  'x_card_num' => trim($_POST['cc_number']),
                  'x_amount' => number_format($refundAmt, 2),
                  'x_trans_id' => trim($_POST['trans_id'])
                );
                unset($response);
                $response = $this->_sendRequest($submit_data);
                $response_code = $response[0];
                $response_text = $response[3];
                $response_alert = $response_text . ($this->commError == '' ? '' : ' Communications Error - Please notify webmaster.');
                $this->reportable_submit_data['Note'] = $refundNote;
                $this->_debugActions($response);
                
                if ($response_code != '1') {
                    $messageStack->add_session($response_alert, 'error');
                } else {
                    // Success, so save the results
                    $sql_data_array = array(
                      'orders_id' => $oID,
                      'orders_status_id' => (int)$new_order_status,
                      'date_added' => 'now()',
                      'comments' => 'REFUND INITIATED. Trans ID:' . $response[6] . ' ' . $response[4] . "\n" . ' Gross Refund Amt: ' . $response[9] . "\n" . $refundNote,
                      'customer_notified' => 0
                    );
                    zen_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);
                    $db->Execute("update " . TABLE_ORDERS . "
                      set orders_status = '" . (int)$new_order_status . "'
                      where orders_id = '" . (int)$oID . "'");
                    $messageStack->add_session(sprintf(MODULE_PAYMENT_AUTHORIZENET_CIM_TEXT_REFUND_INITIATED,
                      $response[9], $response[6]), 'success');
                    return true;
                }
            }
            return false;
        }
        
        /**
         * Used to do any debug logging / tracking / storage as required.
         */
        function _debugActions($response, $order_time = '', $sessID = '')
        {
            global $db, $messageStack;
            if ($order_time == '') {
                $order_time = date("F j, Y, g:i a");
            }
            // convert output to 1-based array for easier understanding:
            $resp_output = array_reverse($response);
            $resp_output[] = 'Response from gateway';
            $resp_output = array_reverse($resp_output);
            
            // DEBUG LOGGING
            $errorMessage = date('M-d-Y h:i:s') .
              "\n=================================\n\n" .
              ($this->commError != '' ? 'Comm results: ' . $this->commErrNo . ' ' . $this->commError . "\n\n" : '') .
              'Response Code: ' . $response[0] . ".\nResponse Text: " . $response[3] . "\n\n" .
              'Sending to Authorizenet: ' . print_r($this->reportable_submit_data, true) . "\n\n" .
              'Results Received back from Authorizenet: ' . print_r($resp_output, true) . "\n\n" .
              'CURL communication info: ' . print_r($this->commInfo, true) . "\n";
            if (CURL_PROXY_REQUIRED == 'True') {
                $errorMessage .= 'Using CURL Proxy: [' . CURL_PROXY_SERVER_DETAILS . ']  with Proxy Tunnel: ' . ($this->proxy_tunnel_flag ? 'On' : 'Off') . "\n";
            }
            $errorMessage .= "\nRAW data received: \n" . $this->authorize . "\n\n";
            
            //    if (strstr(MODULE_PAYMENT_AUTHORIZENET_CIM_DEBUGGING, 'All')) {
            if (strstr(MODULE_PAYMENT_AUTHORIZENET_CIM_DEBUGGING,
                'Log') || strstr(MODULE_PAYMENT_AUTHORIZENET_CIM_DEBUGGING,
                'All') || (defined('AUTHORIZENET_DEVELOPER_MODE') && in_array(AUTHORIZENET_DEVELOPER_MODE,
                  array('on', 'certify'))) || true) {
                $key = $response[6] . '_' . time() . '_' . zen_create_random_value(4);
                $file = $this->_logDir . '/' . 'CIM_Debug_' . $key . '.log';
                if ($fp = @fopen($file, 'a')) {
                    fwrite($fp, $errorMessage);
                    fclose($fp);
                }
            }
            if (($response[0] != '1' && stristr(MODULE_PAYMENT_AUTHORIZENET_CIM_DEBUGGING,
                  'Alerts')) || strstr(MODULE_PAYMENT_AUTHORIZENET_CIM_DEBUGGING, 'Email')) {
                zen_mail(STORE_NAME, STORE_OWNER_EMAIL_ADDRESS,
                  'AuthorizenetCIM Alert ' . $response[7] . ' ' . date('M-d-Y h:i:s') . ' ' . $response[6],
                  $errorMessage, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS,
                  array('EMAIL_MESSAGE_HTML' => nl2br($errorMessage)), 'debug');
            }
            //    }
            
            // DATABASE SECTION
            // Insert the send and receive response data into the database.
            // This can be used for testing or for implementation in other applications
            // This can be turned on and off if the Admin Section
            if (MODULE_PAYMENT_AUTHORIZENET_CIM_STORE_DATA == 'True') {
                $db_response_text = $response[3] . ($this->commError != '' ? ' - Comm results: ' . $this->commErrNo . ' ' . $this->commError : '');
                $db_response_text .= ($response[0] == 2 && $response[2] == 4) ? ' NOTICE: Card should be picked up - possibly stolen ' : '';
                $db_response_text .= ($response[0] == 3 && $response[2] == 11) ? ' DUPLICATE TRANSACTION ATTEMPT ' : '';
                
                // Insert the data into the database
                $sql = "insert into " . TABLE_AUTHORIZENET . "  (id, customer_id, order_id, response_code, response_text, authorization_type, transaction_id, sent, received, time, session_id) values (NULL, :custID, :orderID, :respCode, :respText, :authType, :transID, :sentData, :recvData, :orderTime, :sessID )";
                $sql = $db->bindVars($sql, ':custID', $_SESSION['customer_id'], 'integer');
                $orderidarr = explode("-", $response[7]);
                $orderid = $orderidarr[0];
                $sql = $db->bindVars($sql, ':orderID', preg_replace('/[^0-9]/', '', $orderid), 'integer');
                $sql = $db->bindVars($sql, ':respCode', $response[0], 'integer');
                $sql = $db->bindVars($sql, ':respText', $db_response_text, 'string');
                $sql = $db->bindVars($sql, ':authType', $response[11], 'string');
                $sql = $db->bindVars($sql, ':transID', $this->transaction_id, 'string');
                $sql = $db->bindVars($sql, ':sentData', print_r($this->reportable_submit_data, true), 'string');
                $sql = $db->bindVars($sql, ':recvData', print_r($response, true), 'string');
                $sql = $db->bindVars($sql, ':orderTime', $order_time, 'string');
                $sql = $db->bindVars($sql, ':sessID', $sessID, 'string');
                $db->Execute($sql);
            }
        }
        
        // The name of the company associated with the customer, if applicable (optional)

        /**
         * Used to capture part or all of a given previously-authorized transaction.
         *-/
        function _doCapt($oID, $amt = 0, $currency = 'USD')
        {
            global $db, $messageStack;
            
            //@TODO: Read current order status and determine best status to set this to
            $new_order_status = (int)MODULE_PAYMENT_AUTHORIZENET_CIM_ORDER_STATUS_ID;
            if ($new_order_status == 0) {
                $new_order_status = 1;
            }
            
            $proceedToCapture = true;
            $captureNote = strip_tags(zen_db_input($_POST['captnote']));
            if (isset($_POST['captconfirm']) && $_POST['captconfirm'] == 'on') {
            } else {
                $messageStack->add_session(MODULE_PAYMENT_AUTHORIZENET_CIM_TEXT_CAPTURE_CONFIRM_ERROR, 'error');
                $proceedToCapture = false;
            }
            if (isset($_POST['btndocapture']) && $_POST['btndocapture'] == MODULE_PAYMENT_AUTHORIZENET_CIM_ENTRY_CAPTURE_BUTTON_TEXT) {
                $captureAmt = (float)$_POST['captamt'];
                /*
      if ($captureAmt == 0) {
        $messageStack->add_session(MODULE_PAYMENT_AUTHORIZENET_CIM_TEXT_INVALID_CAPTURE_AMOUNT, 'error');
        $proceedToCapture = false;
      }
*-/
            }
            if (isset($_POST['captauthid']) && trim($_POST['captauthid']) != '') {
                // okay to proceed
            } else {
                $messageStack->add_session(MODULE_PAYMENT_AUTHORIZENET_CIM_TEXT_TRANS_ID_REQUIRED_ERROR, 'error');
                $proceedToCapture = false;
            }
            /**
             * Submit capture request to Authorize.net
             *-/
            if ($proceedToCapture) {
                // Populate an array that contains all of the data to be sent to Authorize.net
                unset($submit_data);
                $submit_data = array(
                  'x_type' => 'PRIOR_AUTH_CAPTURE',
                  'x_amount' => number_format($captureAmt, 2),
                  'x_trans_id' => strip_tags(trim($_POST['captauthid'])),
                    //                         'x_invoice_num' => $new_order_id,
                    //                         'x_po_num' => $order->info['po_number'],
                    //                         'x_freight' => $order->info['shipping_cost'],
                    //                         'x_tax_exempt' => 'FALSE', /* 'TRUE' or 'FALSE' *-/
                    //                         'x_tax' => $order->info['tax'],
                );
                
                $response = $this->_sendRequest($submit_data);
                $response_code = $response[0];
                $response_text = $response[3];
                $response_alert = $response_text . ($this->commError == '' ? '' : ' Communications Error - Please notify webmaster.');
                $this->reportable_submit_data['Note'] = $captureNote;
                $this->_debugActions($response);
                
                if ($response_code != '1' || ($response[0] == 1 && $response[2] == 311)) {
                    $messageStack->add_session($response_alert, 'error');
                } else {
                    // Success, so save the results
                    $sql_data_array = array(
                      'orders_id' => (int)$oID,
                      'orders_status_id' => (int)$new_order_status,
                      'date_added' => 'now()',
                      'comments' => 'FUNDS COLLECTED. Auth Code: ' . $response[4] . "\n" . 'Trans ID: ' . $response[6] . "\n" . ' Amount: ' . ($response[9] == 0.00 ? 'Full Amount' : $response[9]) . "\n" . 'Time: ' . date('Y-m-D h:i:s') . "\n" . $captureNote,
                      'customer_notified' => 0
                    );
                    zen_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);
                    $db->Execute("update " . TABLE_ORDERS . "
                      set orders_status = '" . (int)$new_order_status . "'
                      where orders_id = '" . (int)$oID . "'");
                    $messageStack->add_session(sprintf(MODULE_PAYMENT_AUTHORIZENET_CIM_TEXT_CAPT_INITIATED,
                      ($response[9] == 0.00 ? 'Full Amount' : $response[9]), $response[6], $response[4]), 'success');
                    return true;
                }
            }
            return false;
        }
        
        // The customer's address (optional)

        /**
         * Used to void a given previously-authorized transaction.
         *-/
        function _doVoid($oID, $note = '')
        {
            global $db, $messageStack;
            
            $new_order_status = (int)MODULE_PAYMENT_AUTHORIZENET_CIM_REFUNDED_ORDER_STATUS_ID;
            if ($new_order_status == 0) {
                $new_order_status = 1;
            }
            $voidNote = strip_tags(zen_db_input($_POST['voidnote'] . $note));
            $voidAuthID = trim(strip_tags(zen_db_input($_POST['voidauthid'])));
            $proceedToVoid = true;
            if (isset($_POST['ordervoid']) && $_POST['ordervoid'] == MODULE_PAYMENT_AUTHORIZENET_CIM_ENTRY_VOID_BUTTON_TEXT) {
                if (isset($_POST['voidconfirm']) && $_POST['voidconfirm'] != 'on') {
                    $messageStack->add_session(MODULE_PAYMENT_AUTHORIZENET_CIM_TEXT_VOID_CONFIRM_ERROR, 'error');
                    $proceedToVoid = false;
                }
            }
            if ($voidAuthID == '') {
                $messageStack->add_session(MODULE_PAYMENT_AUTHORIZENET_CIM_TEXT_TRANS_ID_REQUIRED_ERROR, 'error');
                $proceedToVoid = false;
            }
            // Populate an array that contains all of the data to be sent to gateway
            $submit_data = array(
              'x_type' => 'VOID',
              'x_trans_id' => trim($voidAuthID)
            );
            /**
             * Submit void request to Gateway
             *-/
            if ($proceedToVoid) {
                $response = $this->_sendRequest($submit_data);
                $response_code = $response[0];
                $response_text = $response[3];
                $response_alert = $response_text . ($this->commError == '' ? '' : ' Communications Error - Please notify webmaster.');
                $this->reportable_submit_data['Note'] = $voidNote;
                $this->_debugActions($response);
                
                if ($response_code != '1' || ($response[0] == 1 && $response[2] == 310)) {
                    $messageStack->add_session($response_alert, 'error');
                } else {
                    // Success, so save the results
                    $sql_data_array = array(
                      'orders_id' => (int)$oID,
                      'orders_status_id' => (int)$new_order_status,
                      'date_added' => 'now()',
                      'comments' => 'VOIDED. Trans ID: ' . $response[6] . ' ' . $response[4] . "\n" . $voidNote,
                      'customer_notified' => 0
                    );
                    zen_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);
                    $db->Execute("update " . TABLE_ORDERS . "
                      set orders_status = '" . (int)$new_order_status . "'
                      where orders_id = '" . (int)$oID . "'");
                    $messageStack->add_session(sprintf(MODULE_PAYMENT_AUTHORIZENET_CIM_TEXT_VOID_INITIATED,
                      $response[6], $response[4]), 'success');
                    return true;
                }
            }
            return false;
        }
        
        // The city of the customer's address (optional)

         */
        
        function parse_api_response($content)
        {
            $parsedresponse = simplexml_load_string($content, "SimpleXMLElement", LIBXML_NOWARNING);
            if ("Ok" != $parsedresponse->messages->resultCode) {
                echo "The operation failed with the following errors:<br>";
                foreach ($parsedresponse->messages->message as $msg) {
                    echo "[" . htmlspecialchars($msg->code) . "] " . htmlspecialchars($msg->text) . "<br>";
                }
                echo "<br>";
            }
            return $parsedresponse;
        }
        
        // The ZIP code of the customer's address (optional)

        function doCimRefund($ordersID, $refund_amount)
        {
            global $db, $messageStack;
            
            $cim_refund = $db->Execute("select customers_name, customers_id, payment_profile_id, transaction_id, order_total, balance_due from orders where orders_id = '" . (int)$ordersID . "'");
            $customer = $db->Execute("select customers_customerProfileId from customers where customers_id = '" . (int)$cim_refund->fields['customers_id'] . "'");
            $sql = "select * from so_payments where orders_id = :orderID and payment_number = :payment_id";
            $sql = $db->bindVars($sql, ':orderID', $ordersID, 'integer');
            $sql = $db->bindVars($sql, ':payment_id', $cim_refund->fields['transaction_id'], 'string');
            $payment = $db->Execute($sql);
            
            $max_refund = ($cim_refund->fields['order_total'] - $cim_refund->fields['balance_due']);
            
            if (!(isset($refund_amount)) || ($refund_amount == 0) || ($refund_amount > $max_refund)) {
                $refund_amount = $max_refund;
            }
            
            $this->setParameter('customerProfileId', $customer->fields['customers_customerProfileId']);
            $this->setParameter('transactionId', trim($cim_refund->fields['transaction_id']));
            $this->setParameter('transaction_amount', number_format($refund_amount, 2, '.', ''));
            $this->setParameter('customerPaymentProfileId', $cim_refund->fields['payment_profile_id']);
            
            $this->xml = "<?xml version='1.0' encoding='utf-8'?>
	<createCustomerProfileTransactionRequest xmlns='AnetApi/xml/v1/schema/AnetApiSchema.xsd'>
	<merchantAuthentication>
		<name>" . $this->login . "</name>
		<transactionKey>" . $this->transkey . "</transactionKey>
	</merchantAuthentication>
	<transaction>
	    <profileTransRefund>
			" . $this->transaction_amount() . "
			" . $this->customerProfileId() . "
			" . $this->customerPaymentProfileId() . "
			" . $this->transactionId() . "
	    </profileTransRefund>
	</transaction>
	</createCustomerProfileTransactionRequest>";
            
            $this->process();
            
            if ($this->isSuccessful()) {
                $this->log = 'do CIM Refund order: ' . $ordersID . '; Error: ' . $this->cim_code . ' ' . $this->text;
                $this->die_message = 'Success - CIM Refund order:';
                $this->logError();
                if ($refund_amount == $cim_refund->fields['order_total']) {
                    $sql = "update " . TABLE_ORDERS . "
        	set approval_code = ' ', transaction_id = ' ', cc_authorized = '0', cc_authorized_date = ''
        	WHERE orders_id = :orderID ";
                    $sql = $db->bindVars($sql, ':orderID', $ordersID, 'integer');
                    $db->Execute($sql);
                }
                
                $sql = "insert into so_refunds (payment_id, orders_id, refund_number, refund_name, refund_amount, refund_type, payment_number, date_posted, last_modified) values (:paymentID, :orderID, :transID, :payment_name, :amount, 'REF', :payment_number, now(), now() )";
                $sql = $db->bindVars($sql, ':paymentID', $payment->fields['payment_id'], 'integer');
                $sql = $db->bindVars($sql, ':orderID', $ordersID, 'integer');
                $sql = $db->bindVars($sql, ':transID', $this->transID, 'string');
                $sql = $db->bindVars($sql, ':payment_name', $cim_refund->fields['customers_name'], 'string');
                $sql = $db->bindVars($sql, ':payment_number', trim($cim_refund->fields['transaction_id']), 'string');
                $sql = $db->bindVars($sql, ':amount', $this->params['transaction_amount'], 'noquotestring');
                
                $db->Execute($sql);
                $sql = "update so_payments set refund_amount = (refund_amount + :amount) where payment_number = :payment_number and orders_id = :orderID";
                $sql = $db->bindVars($sql, ':orderID', $ordersID, 'integer');
                $sql = $db->bindVars($sql, ':payment_number', trim($cim_refund->fields['transaction_id']), 'string');
                $sql = $db->bindVars($sql, ':amount', $this->params['transaction_amount'], 'noquotestring');
                $db->Execute($sql);
            } else {
                // new 6_2015 for transactions older than 120 days....
                //first log  first attempt...
                $this->log = 'CIM Refund problem first attempt: ' . $ordersID . '; Error: ' . $this->cim_code . ' ' . $this->text;
                $this->die_message = 'no success - CIM Refund order first attempt.';
                $this->logError();
                $this->xml = "<?xml version='1.0' encoding='utf-8'?>
        <createCustomerProfileTransactionRequest xmlns='AnetApi/xml/v1/schema/AnetApiSchema.xsd'>
            <merchantAuthentication>
            	<name>" . $this->login . "</name>
            	<transactionKey>" . $this->transkey . "</transactionKey>
            </merchantAuthentication>
            <transaction>
                <profileTransRefund>
            		" . $this->transaction_amount() . "
            		" . $this->customerProfileId() . "
            		" . $this->customerPaymentProfileId() . "
                </profileTransRefund>
            </transaction>
        </createCustomerProfileTransactionRequest>";
                
                $this->process();
                
                if ($this->isSuccessful()) {
                    $this->log = 'CIM Refund order with ID: ' . $ordersID . '; Error: ' . $this->cim_code . ' ' . $this->text;
                    $this->die_message = 'Success - CIM Refund order with ID:';
                    $this->logError();
                    if ($refund_amount == $cim_refund->fields['order_total']) {
                        $sql = "update " . TABLE_ORDERS . "
            	set approval_code = ' ', transaction_id = ' ', cc_authorized = '0', cc_authorized_date = ''
            	WHERE orders_id = :orderID ";
                        $sql = $db->bindVars($sql, ':orderID', $ordersID, 'integer');
                        $db->Execute($sql);
                    }
                    
                    $sql = "insert into so_refunds (payment_id, orders_id, refund_number, refund_name, refund_amount, refund_type, payment_number, date_posted, last_modified) values (:paymentID, :orderID, :transID, :payment_name, :amount, 'REF', :payment_number, now(), now() )";
                    $sql = $db->bindVars($sql, ':paymentID', $payment->fields['payment_id'], 'integer');
                    $sql = $db->bindVars($sql, ':orderID', $ordersID, 'integer');
                    $sql = $db->bindVars($sql, ':transID', $this->transID, 'string');
                    $sql = $db->bindVars($sql, ':payment_name', $cim_refund->fields['customers_name'], 'string');
                    $sql = $db->bindVars($sql, ':payment_number', trim($cim_refund->fields['transaction_id']),
                      'string');
                    $sql = $db->bindVars($sql, ':amount', $this->params['transaction_amount'], 'noquotestring');
                    
                    $db->Execute($sql);
                    $sql = "update so_payments set refund_amount = (refund_amount + :amount) where payment_number = :payment_number and orders_id = :orderID";
                    $sql = $db->bindVars($sql, ':orderID', $ordersID, 'integer');
                    $sql = $db->bindVars($sql, ':payment_number', trim($cim_refund->fields['transaction_id']),
                      'string');
                    $sql = $db->bindVars($sql, ':amount', $this->params['transaction_amount'], 'noquotestring');
                    $db->Execute($sql);
                } else {
                    // end 6_2015 change...
                    /* pmr 6/2015 i think this whole section with the E00027 is misplaced and does not belong here.
         * does belong here!  10_2015 */
                    if ($this->cim_code == 'E00027') {
                        $this->xml = "<?xml version='1.0' encoding='utf-8'?>
		    <createCustomerProfileTransactionRequest xmlns='AnetApi/xml/v1/schema/AnetApiSchema.xsd'>
		    <merchantAuthentication>
			<name>" . $this->login . "</name>
			<transactionKey>" . $this->transkey . "</transactionKey>
		    </merchantAuthentication>
		    <transaction>
		    <profileTransVoid>
			" . $this->customerProfileId() . "
			" . $this->customerPaymentProfileId() . "
			" . $this->transactionId() . "
		    </profileTransVoid>
		    </transaction>
		    </createCustomerProfileTransactionRequest>";
                        $this->process();
                        if ($this->isSuccessful()) {
                            // change status to what??  vino_mod
                            $sql = "update " . TABLE_ORDERS . "
		set approval_code = ' ', transaction_id = ' ', cc_authorized = '0', cc_authorized_date = ''
		WHERE orders_id = :orderID ";
                            $sql = $db->bindVars($sql, ':orderID', $ordersID, 'integer');
                            $db->Execute($sql);
                            
                            $sql = "insert into so_refunds (payment_id, orders_id, refund_number, refund_name, refund_amount, refund_type, payment_number, date_posted, last_modified) values (:paymentID, :orderID, :transID, :payment_name, :amount, 'VOID', :payment_number, now(), now() )";
                            $sql = $db->bindVars($sql, ':paymentID', $payment->fields['payment_id'], 'integer');
                            $sql = $db->bindVars($sql, ':orderID', $ordersID, 'integer');
                            $sql = $db->bindVars($sql, ':transID', $this->transID, 'string');
                            $sql = $db->bindVars($sql, ':payment_name', $cim_refund->fields['customers_name'],
                              'string');
                            $sql = $db->bindVars($sql, ':payment_number', trim($cim_refund->fields['transaction_id']),
                              'string');
                            $sql = $db->bindVars($sql, ':amount', $payment->fields['payment_amount'], 'float');
                            $db->Execute($sql);
                            $sql = "update so_payments set refund_amount = (refund_amount + :amount) where payment_number = :payment_number and orders_id = :orderID";
                            $sql = $db->bindVars($sql, ':orderID', $ordersID, 'integer');
                            $sql = $db->bindVars($sql, ':payment_number', $this->transID, 'string');
                            $sql = $db->bindVars($sql, ':amount', $payment->fields['payment_amount'], 'float');
                            $db->Execute($sql);
                        } else {
                            $this->log = 'CIM Refund/Void order: ' . $ordersID . '; Error: ' . $this->cim_code . ' ' . $this->text;
                            $this->die_message = 'no success - CIM Refund/Void order:';
                            $this->logError();
                        }
                    } else {
                        
                        $this->log = 'do CIM Refund order both ways: ' . $ordersID . '; Error: ' . $this->cim_code . ' ' . $this->text;
                        $this->die_message = 'no success - CIM Refund order both ways.';
                        $this->logError();
                    }
                }
            }
        }
        
        // This element is optional

        function transaction_amount()
        {
            global $order;
            if (isset($this->params['transaction_amount'])) {
                if (preg_match('/(^[0-9]+\.[0-9]{1,4}$)/', $this->params['transaction_amount'])) {
                    return "<amount>" . $this->params['transaction_amount'] . "</amount>";
                } else {
                    $this->error_messages[] .= 'setParameter(): transaction_amount is required and must be up to 4 digits with a decimal (no dollar symbol) current value is: ' . $this->params['transaction_amount'];
                }
            } else {
                if ($order->info['total'] > 0) {
                    return "<amount>" . $order->info['total'] . "</amount>";
                } else {
                    $this->error_messages[] .= 'setParameter(): transaction_amount required (NOT ZERO!); must be up to 4 digits with a decimal (no dollar symbol) current value is: ' . $order->info['total'];
                }
                //$this->error_messages[] .= 'setParameter(): transaction_amount is required and must be up to 4 digits with a decimal';
            }
        }
        
        // The phone number associated with the customer's address (optional)

        function transactionId()
        {
            if (isset($this->params['transactionId'])) {
                if (preg_match('/^\d+$/', $this->params['transactionId'])) {
                    return "<transId>" . $this->params['transactionId'] . "</transId>";
                } else {
                    $this->error_messages[] .= 'setParameter(): you have no idea transId is wrong - should be numeric';
                }
            }
        }
        
        // This element is optional

        function createCustomerProfileTransactionRequest()
        {
            global $db, $order, $lookXML, $insert_id;
            
            $lookXML = false;
            
            $data = customer_payment_transaction($this->params['customerProfileId'], $this->params['customerPaymentProfileId'], $order);
            $this->xml = request_xml('createCustomerProfileTransactionRequest', $data);
    
            $this->process();
            
            // if ($lookXML) {error_log(date(DATE_RFC822) . ': ' . $this->xml  . "\n", 3, '../../cim_error.log');}
            $lookXML = false;
            
            if ($this->isSuccessful()) {
                $sql = "INSERT INTO `so_payments` ( `payment_number`, `payment_name`, `payment_amount`, `payment_type`, `date_posted`, `last_modified`,  `transaction_id`, `payment_profile_id`, `approval_code`, `customers_id`)
VALUES (:transID, :nameFull, :amount, :type, now(), now(), :transID, :paymentProfileID, :approval_code, :custID)";

                
                $sql = $db->bindVars($sql, ':transID', $this->transID, 'string');
                $sql = $db->bindVars($sql, ':nameFull',
                  $this->params['billTo_firstName'] . ' ' . $this->params['billTo_lastName'], 'string');
                $sql = $db->bindVars($sql, ':amount', $order->info['total'], 'noquotestring');
                $sql = $db->bindVars($sql, ':type', $this->code, 'string');
                $sql = $db->bindVars($sql, ':paymentProfileID', $this->params['customerPaymentProfileId'], 'string');
                $sql = $db->bindVars($sql, ':approval_code', $this->approvalCode, 'string');
                 $sql = $db->bindVars($sql, ':custID', $this->params['merchantCustomerId'], 'string');
                
                $db->Execute($sql);
                
                if ($order->info['save_cc_data'] == 'N') {
                    $sql = "Select orders_id from " . TABLE_ORDERS . "
                WHERE customers_id = :custID  and payment_profile_id = :paymtProfId
                and approval_code = ''";
                    $sql = $db->bindVars($sql, ':custID', $order->info['customers_id'], 'integer');
                    $sql = $db->bindVars($sql, ':paymtProfId', $order->info['payment_profile_id'], 'integer');
                    $open_orders = $db->Execute($sql);
                    $this->log = $sql . '<---rec Count--->' . $open_orders->RecordCount();
                    //$this->logError();
                    if ($open_orders->RecordCount() == 0) {
                        $this->params['customerProfileId'] = $order->billing['customerProfileId'];
                        $this->params['customerPaymentProfileId'] = $order->info['payment_profile_id'];
                        // $this->deleteCustomerPaymentProfileRequest();
                    }
                }
            } else {
                if ($this->cim_code == 'E00027') {
                    $this->log = 'createCustomerProfileTransactionRequest_1 order: ' . $order->info['orders_id'] . '; Error: ' . $this->cim_code . ' ' . $this->text;
                    if ((strpos($this->text, 'declined') !== false) || (strpos($this->text, 'duplicate') !== false)) {
                    } else {
                        //$this->log .= ' trying to update CC expiration date off new CC number';
                    }
                    $this->die_message = 'customerPaymentProfileTransactionRequest';
                    $this->logError();
                    $order->info['admin'] = 'NEW';
                    $this->setParameter('customerPaymentProfileId', $this->orderPaymentProfileId());
                    $this->setParameter('customerProfileId', $this->orderCustomerProfileId());
                    if ((strpos($this->text, 'declined') !== false) || (strpos($this->text, 'duplicate') !== false)) {
                    } else {
                        if ($order->info['cc_expires'] != '') {
                            //$this->updateCustomerPaymentProfileRequest();
                        }
                    }
                } else {
                    $this->log = 'createCustomerProfileTransactionRequest_2 order: ' . $order->info['orders_id'] . '; Error: ' . $this->cim_code . ' ' . $this->text;
                    $this->die_message = 'no success - customer profile Transaction request';
                    $this->logError();
                }
            }
        }
        
        /************************* Shipping Functions *************************/
        
        // The customer's first name (optional)
        function transactionTax()
        {
            global $order;
            
            if (((isset($this->params['tax_amount']))
                || (isset($this->params['tax_name']))
                || (isset($this->params['tax_description']))) && ($this->params['tax_amount'] > 0)) {
                return "
			<tax>
				" . $this->tax_amount() . "
				" . $this->tax_name() . "
				" . $this->tax_description() . "
			</tax>";
            }
            //<name>". $order->totals['2']['title'] . "</name>
            
            if (isset($order->info['tax']) && ($order->info['tax'] > 0)) {
                return "<tax>
			<amount>" . round($order->info['tax'], 2) . "</amount>
	                <name>California - Taxable goods:</name>
			<description>California Sales Tax</description>
		    </tax>";
            } else {
                //$this->error_messages[] .= 'setParameter(): tax_amount from $order is not good...';
            }
        }
        
        // The customer's last name (optional)

        function tax_amount()
        {
            if (isset($this->params['tax_amount'])) {
                if (preg_match('/(^[0-9]+\.[0-9]{1,4}$)/', $this->params['tax_amount'])) {
                    return "<amount>" . round($this->params['tax_amount'], 2) . "</amount>";
                } else {
                    $this->error_messages[] .= 'setParameter(): tax_amount must be up to 4 digits with a decimal point (no dollar symbol)';
                }
            }
        }
        
        // The name of the company associated with the customer, if applicable (optional)

        function tax_name()
        {
            if (isset($this->params['tax_name'])) {
                if ((strlen($this->params['tax_name']) > 0)
                  && (strlen($this->params['tax_name']) <= 31)) {
                    return "<name>" . $this->params['tax_name'] . "</name>";
                } else {
                    $this->error_messages[] .= 'setParameter(): tax_name must be up to 31 characters';
                }
            }
        }
        
        // The customer's address (optional)

        function tax_description()
        {
            if (isset($this->params['tax_description'])) {
                if ((strlen($this->params['tax_description']) > 0)
                  && (strlen($this->params['tax_description']) <= 255)) {
                    return "<description>" . $this->params['tax_description'] . "</description>";
                } else {
                    $this->error_messages[] .= 'setParameter(): tax_description must be up to 255 characters';
                }
            }
        }
        
        // The city of the customer's address (optional)

        function transactionShipping()
        {
            global $order;
            
            foreach ($order->totals as $value) {
                if (($value['class'] == 'ot_shipping') && ($value['value'] > 0)) {
                    return "<shipping>
                    <amount>" . $value['value'] . "</amount>
                    <name>" . substr(preg_replace('/&/', 'and', $value['title']), 0, 30) . "</name>
                </shipping>";
                }
            }
        }
        
        // The ZIP code of the customer's address (optional)
    
        function orderCustomerProfileId()
        {
            global $order;
            if (isset($order->billing['customerProfileId'])) {
                if (preg_match('/^[0-9]+$/', $order->billing['customerProfileId'])) {
                    return "<customerProfileId>" . $order->billing['customerProfileId'] . "</customerProfileId>";
                } else {
                    $this->error_messages[] .= 'setParameter(): $order customerProfileId must be numeric';
                }
            } elseif (isset($this->params['customerProfileId'])) {
                if (preg_match('/^[0-9]+$/', $this->params['customerProfileId'])) {
                    return "<customerProfileId>" . $this->params['customerProfileId'] . "</customerProfileId>";
                } else {
                    $this->error_messages[] .= 'setParameter(): customerProfileId must be numeric';
                }
            } else {
                $this->error_messages[] .= 'setParameter(): customerProfileId is required and must be numeric';
            }
        }
        
        // The country of the customer's address (optional)

        function orderPaymentProfileId()
        {
            global $order;
            if (isset($order->info['payment_profile_id'])) {
                if (preg_match('/^[0-9]+$/', $order->info['payment_profile_id'])) {
                    return "<customerPaymentProfileId>" . $order->info['payment_profile_id'] . "</customerPaymentProfileId>";
                } else {
                    $this->error_messages[] .= 'setParameter(a): $order paymentProfileId must be numeric';
                }
            } elseif (isset($this->customerPaymentProfileId)) {
                if (preg_match('/^[0-9]+$/', $this->customerPaymentProfileId)) {
                    return "<customerPaymentProfileId>" . $this->customerPaymentProfileId . "</customerPaymentProfileId>";
                } else {
                    $this->error_messages[] .= 'setParameter(b): $order paymentProfileId is must be numeric';
                }
            } else {
                $this->error_messages[] .= 'setParameter(): $order paymentProfileId is required';
            }
        }
        
        // The phone number associated with the customer's address (optional)

        function orderAddressProfileId()
        {
            global $order;
            if (isset($order->delivery['CIM_address_id'])) {
                if (preg_match('/^[0-9]+$/', $order->delivery['CIM_address_id'])) {
                    return "<customerShippingAddressId>" . $order->delivery['CIM_address_id'] . "</customerShippingAddressId>";
                } else {
                    // $this->error_messages[] .= 'setParameter(): $order addressProfileId must be numeric';
                }
            } else {
                // $this->error_messages[] .= 'setParameter(): $order addressProfileId is required and must be numeric';
            }
        }
        
        // The fax number associated with the customer's address (optional)
    
       
        /************************* Other Functions *************************/
        
        // This element is optional
        // Even though the manual states this is optional, it is actually conditional in a circumstance.
        // You must have either the merchantCustomerId and/or description defined for createCustomerProfileRequest()
        function order_invoiceNumber()
        {
            if (isset($this->params['order_invoiceNumber'])) {
                if ((strlen($this->params['order_invoiceNumber']) > 0)
                  && (strlen($this->params['order_invoiceNumber']) <= 20)) {
                    return "<invoiceNumber>" . $this->params['order_invoiceNumber'] . "</invoiceNumber>";
                } else {
                    $this->error_messages[] .= 'setParameter(): order_invoiceNumber must be up to 20 characters (no symbols)';
                }
            }
        }
        
        // This element is optional
        // Even though the manual states this is optional, it is actually conditional in a circumstance.
        // You must have either the description and/or merchantCustomerId defined for createCustomerProfileRequest()

        function order_description()
        {
            if (isset($this->params['order_description'])) {
                if ((strlen($this->params['order_description']) > 0)
                  && (strlen($this->params['order_description']) <= 255)) {
                    return "<description>" . $this->params['order_description'] . "</description>";
                } else {
                    $this->error_messages[] .= 'setParameter(): order_description must be up to 255 characters (no symbols)';
                }
            }
        }
        
        // This element is optional

        function order_purchaseOrderNumber()
        {
            if (isset($this->params['order_purchaseOrderNumber'])) {
                if ((strlen($this->params['order_purchaseOrderNumber']) > 0)
                  && (strlen($this->params['order_purchaseOrderNumber']) <= 25)) {
                    return "<purchaseOrderNumber>" . $this->params['order_purchaseOrderNumber'] . "</purchaseOrderNumber>";
                } else {
                    $this->error_messages[] .= 'setParameter(): order_purchaseOrderNumber must be up to 25 characters (no symbols)';
                }
            }
        }
        
        // This element is optional

        function transactionCardCode()
        {
            if (isset($this->params['transactionCardCode'])) {
                if (preg_match('/^[0-9]{3,4}$/', $this->params['transactionCardCode'])) {
                    return "<cardCode>" . $this->params['transactionCardCode'] . "</cardCode>";
                } else {
                    $this->error_messages[] .= 'setParameter(): transactionCardCode must be 3 to 4 digits';
                }
            }
            if (isset($order->info['ccv'])) {
                if (preg_match('/^[0-9]{3,4}$/', $order->info['ccv'])) {
                    return "<cardCode>" . $order->info['ccv'] . "</cardCode>";
                }
            }
        }
        
        // This element is optional

        function deleteCustomerProfileRequest()
        {
            global $order, $db;
            if (isset($this->params['delete_customer_id'])) {
                $this->setParamsAdmin();
            }
            $this->xml = "<?xml version='1.0' encoding='utf-8'?>
            <deleteCustomerProfileRequest xmlns='AnetApi/xml/v1/schema/AnetApiSchema.xsd'>
            <merchantAuthentication>
        	<name>" . $this->login . "</name>
        	<transactionKey>" . $this->transkey . "</transactionKey>
        </merchantAuthentication>
        " . $this->refId() . "
        " . $this->customerProfileId() . "
        </deleteCustomerProfileRequest>";
            $this->process();
            if ($this->isSuccessful()) {
                $this->deleteStoredData();
            } else {
                $this->log = 'deleteCustomerProfileRequest Customer: ' . $this->params['delete_customer_id'] . '; Error: ' . $this->cim_code . ' ' . $this->text;
                $this->die_message = 'no success - deleteCustomerProfileRequest';
                $this->logError();
                if ($this->cim_code == 'E00040') {
                    $this->deleteStoredData();
                }
            }
        }
        
        // This element is optional

        function deleteStoredData()
        {
            global $db;
            $sql = "update " . TABLE_CUSTOMERS . "
		set customers_customerProfileId = 0
		WHERE customers_id = :customerID ";
            $sql = $db->bindVars($sql, ':customerID', $this->params['delete_customer_id'], 'integer');
            $db->Execute($sql);
            
            $sql = "delete from " . TABLE_CUSTOMERS_CC . "
		WHERE customers_id = :customerID ";
            $sql = $db->bindVars($sql, ':customerID', $this->params['delete_customer_id'], 'integer');
            $db->Execute($sql);
            
            $sql = "update address_book
		set CIM_address_id = 0
		WHERE customers_id = :customerID ";
            $sql = $db->bindVars($sql, ':customerID', $this->params['delete_customer_id'], 'integer');
            $db->Execute($sql);
            
            $sql = "update orders
		set CIM_address_id = 0, payment_profile_id = 0
		WHERE customers_id = :customerID
		and cc_authorized ='0'";
            $sql = $db->bindVars($sql, ':customerID', $this->params['delete_customer_id'], 'integer');
            $db->Execute($sql);
        }
        
        // This element is optional

        function deleteCustomerPaymentProfileRequest()
        {
            global $order, $db;
            $this->xml = "<?xml version='1.0' encoding='utf-8'?>
	<deleteCustomerPaymentProfileRequest xmlns='AnetApi/xml/v1/schema/AnetApiSchema.xsd'>
	<merchantAuthentication>
		<name>" . $this->login . "</name>
		<transactionKey>" . $this->transkey . "</transactionKey>
	</merchantAuthentication>
	" . $this->refId() . "
	" . $this->customerProfileId() . "
	" . $this->customerPaymentProfileId() . "
	</deleteCustomerPaymentProfileRequest>";
            $this->process();
            
            if (($this->isSuccessful()) || ($this->cim_code == 'E00040')) {
                $sql = "delete from " . TABLE_CUSTOMERS_CC . "
		WHERE customers_id = :custID  and payment_profile_id = :paymtProfId";
                $sql = $db->bindVars($sql, ':custID', $this->params['refId'], 'integer');
                //$sql = $db->bindVars($sql, ':custID', $order->customer['id'], 'integer');
                $sql = $db->bindVars($sql, ':paymtProfId', $this->params['customerPaymentProfileId'], 'integer');
                $this->log = $sql;
                $this->logError();
                $db->Execute($sql);
            } else {
                $this->log = 'deleteCustomerPaymentProfileRequest customer: ' . $this->params['refId'] . '; paymentProfile: ' . $this->params['customerPaymentProfileId'] . '; Error: ' . $this->cim_code . ' ' . $this->text;
                $this->die_message = 'no success - deleteCustomerPaymentProfileRequest';
                $this->logError();
            }
            return ($this);
        }
        
        // This element is optional

        function deleteCustomerShippingAddressRequest()
        {
            global $db;
            $this->xml = "<?xml version='1.0' encoding='utf-8'?>
	<deleteCustomerShippingAddressRequest xmlns='AnetApi/xml/v1/schema/AnetApiSchema.xsd'>
	<merchantAuthentication>
		<name>" . $this->login . "</name>
		<transactionKey>" . $this->transkey . "</transactionKey>
	</merchantAuthentication>
	" . $this->refId() . "
	" . $this->customerProfileId() . "
	" . $this->customerAddressId() . "
	</deleteCustomerShippingAddressRequest>";
            $this->process();
        }
        
        // This element is required in some functions

        function customerAddressId()
        {
            if (isset($this->params['customerAddressId'])) {
                if (preg_match('/^[0-9]+$/', $this->params['customerAddressId'])) {
                    return "<customerAddressId>" . $this->params['customerAddressId'] . "</customerAddressId>";
                } else {
                    $this->error_messages[] .= 'setParameter(): customerAddressId is required and must be numeric';
                }
            } else {
                $this->error_messages[] .= 'setParameter(): customerAddressId is required and must be numeric';
            }
        }
        
        // This element is required in some functions

        function getCustomerProfileRequest()
        {
            global $db;
            $this->xml = "<?xml version='1.0' encoding='utf-8'?>
	<getCustomerProfileRequest xmlns='AnetApi/xml/v1/schema/AnetApiSchema.xsd'>
	<merchantAuthentication>
		<name>" . $this->login . "</name>
		<transactionKey>" . $this->transkey . "</transactionKey>
	</merchantAuthentication>
	" . $this->customerProfileId() . "
	</getCustomerProfileRequest>";
            $this->process();
        }
        
        // This element is required in some functions

        function getCustomerPaymentProfileRequest()
        {
            global $db;
            $this->xml = "<?xml version='1.0' encoding='utf-8'?>
	<getCustomerPaymentProfileRequest xmlns='AnetApi/xml/v1/schema/AnetApiSchema.xsd'>
	<merchantAuthentication>
		<name>" . $this->login . "</name>
		<transactionKey>" . $this->transkey . "</transactionKey>
	</merchantAuthentication>
	" . $this->customerProfileId() . "
	" . $this->customerPaymentProfileId() . "
	</getCustomerPaymentProfileRequest>";
            $this->process();
        }
        
        // This element is required in some functions

        function getCustomerShippingAddressRequest()
        {
            global $db;
            $this->xml = "<?xml version='1.0' encoding='utf-8'?>
	<getCustomerShippingAddressRequest xmlns='AnetApi/xml/v1/schema/AnetApiSchema.xsd'>
	<merchantAuthentication>
		<name>" . $this->login . "</name>
		<transactionKey>" . $this->transkey . "</transactionKey>
	</merchantAuthentication>
	" . $this->customerProfileId() . "
	" . $this->customerAddressId() . "
	</getCustomerShippingAddressRequest>";
            $this->process();
        }
        
        // This element is required in some functions
        // This amount should include all other amounts such as tax amount, shipping amount, etc. Ex. 12.99 or 12.9999

        function updateCustomerProfileRequest()
        {
            global $db;
            $this->xml = "<?xml version='1.0' encoding='utf-8'?>
	<updateCustomerProfileRequest xmlns='AnetApi/xml/v1/schema/AnetApiSchema.xsd'>
	<merchantAuthentication>
		<name>" . $this->login . "</name>
		<transactionKey>" . $this->transkey . "</transactionKey>
	</merchantAuthentication>
	" . $this->refId() . "
	<profile>
		" . $this->merchantCustomerId() . "
		" . $this->description() . "
		" . $this->email() . "
		" . $this->customerProfileId() . "
	</profile>
	</updateCustomerProfileRequest>";
            $this->process();
        }
        
        // This element is required in some functions

        function updateCustomerShippingAddressRequest()
        {
            global $db;
            $this->xml = "<?xml version='1.0' encoding='utf-8'?>
	<updateCustomerShippingAddressRequest xmlns='AnetApi/xml/v1/schema/AnetApiSchema.xsd'>
	<merchantAuthentication>
		<name>" . $this->login . "</name>
		<transactionKey>" . $this->transkey . "</transactionKey>
	</merchantAuthentication>
	" . $this->refId() . "
	" . $this->customerProfileId() . "
	<address>
		" . $this->shipTo_firstName() . "
		" . $this->shipTo_lastName() . "
		" . $this->shipTo_company() . "
		" . $this->shipTo_address() . "
		" . $this->shipTo_city() . "
		" . $this->shipTo_state() . "
		" . $this->shipTo_zip() . "
		" . $this->shipTo_country() . "
		" . $this->shipTo_phoneNumber() . "
		" . $this->shipTo_faxNumber() . "
		" . $this->customerAddressId() . "
	</address>
	</updateCustomerShippingAddressRequest>";
            $this->process();
        }
        
        // This element is required in some functionsexpirationDate
        // Payment gateway assigned ID associated with the customer profile

        function validateCustomerPaymentProfileRequest()
        {
            global $db;
            $this->xml = "<?xml version='1.0' encoding='utf-8'?>
	<validateCustomerPaymentProfileRequest xmlns='AnetApi/xml/v1/schema/AnetApiSchema.xsd'>
	<merchantAuthentication>
		<name>" . $this->login . "</name>
		<transactionKey>" . $this->transkey . "</transactionKey>
	</merchantAuthentication>
	" . $this->customerProfileId() . "
	" . $this->customerPaymentProfileId() . "
	" . $this->customerShippingAddressId() . "
	" . $this->validationMode() . "
	</validateCustomerPaymentProfileRequest>";
            $this->process();
        }
        
        function customerShippingAddressId()
        {
            if (isset($this->params['customerShippingAddressId'])) {
                if (preg_match('/^[0-9]+$/', $this->params['customerShippingAddressId'])) {
                    return "<customerShippingAddressId>" . $this->params['customerShippingAddressId'] . "</customerShippingAddressId>";
                } else {
                    $this->error_messages[] .= 'setParameter(): customerShippingAddressId is required and must be numeric';
                }
            }
        }
        
        function cardData()
        {
            /* v. 0.7 card update */
            $this->cardNumber = $this->substring_between($this->response, '<cardNumber>', '</cardNumber>');
            $this->expirationDate = $this->substring_between($this->response, '<expirationDate>', '</expirationDate>');
            $this->state = $this->substring_between($this->response, '<state>', '</state>');
            $this->city = $this->substring_between($this->response, '<city>', '</city>');
            $this->firstName = $this->substring_between($this->response, '<firstName>', '</firstName>');
            $this->lastName = $this->substring_between($this->response, '<lastName>', '</lastName>');
            $this->address = $this->substring_between($this->response, '<address>', '</address>');
            $this->zip = $this->substring_between($this->response, '<zip>', '</zip>');
            $this->country = $this->substring_between($this->response, '<country>', '</country>');
        }
        
        function shipping_amount()
        {
            if (isset($this->params['shipping_amount'])) {
                if (preg_match('/(^[0-9]+\.[0-9]{1,4}$)/', $this->params['shipping_amount'])) {
                    return "<amount>" . $this->params['shipping_amount'] . "</amount>";
                } else {
                    $this->error_messages[] .= 'setParameter(): shipping_amount must be up to 4 digits with a decimal point. (no dollar symbol)';
                }
            }
        }
        
        
        // This element is required in some functions
        // Payment gateway assigned ID associated with the customer payment profile

        function shipping_name()
        {
            if (isset($this->params['shipping_name'])) {
                if ((strlen($this->params['shipping_name']) > 0)
                  && (strlen($this->params['shipping_name']) <= 31)) {
                    return "<name>" . $this->params['shipping_name'] . "</name>";
                } else {
                    $this->error_messages[] .= 'setParameter(): shipping_name must be up to 31 characters';
                }
            }
        }
        
        // This element is required in some functions, otherwise optional
        // Payment gateway assigned ID associated with the customer shipping address
        // Note: If the customer AddressId is not passed, shipping information will not be included with the transaction.

        function shipping_description()
        {
            if (isset($this->params['shipping_description'])) {
                if ((strlen($this->params['shipping_description']) > 0)
                  && (strlen($this->params['shipping_description']) <= 255)) {
                    return "<description>" . $this->params['shipping_description'] . "</description>";
                } else {
                    $this->error_messages[] .= 'setParameter(): shipping_description must be up to 255 characters';
                }
            }
        }
        
        // In validateCustomerPaymentProfileRequest(), customerShippingAddressId() is used in place of customerAddressId().
        // The Authorize.net manual is still incorrect on this.
        // Payment gateway assigned ID associated with the customer shipping address
        // Note: If the customer Shipping AddressId is not passed, shipping information will not be included with the transaction.

        function transactionDuty()
        {
            if ((isset($this->params['duty_amount']))
              || (isset($this->params['duty_name']))
              || (isset($this->params['duty_description']))) {
                return "
			<duty>
				" . $this->duty_amount() . "
				" . $this->duty_name() . "
				" . $this->duty_description() . "
			</duty>";
            }
        }
        
        // This element is optional

        function duty_amount()
        {
            if (isset($this->params['duty_amount'])) {
                if (preg_match('/(^[0-9]+\.[0-9]{1,4}$)/', $this->params['duty_amount'])) {
                    return "<amount>" . $this->params['duty_amount'] . "</amount>";
                } else {
                    $this->error_messages[] .= 'setParameter(): duty_amount must be up to 4 digits with a decimal point. (no dollar symbol)';
                }
            }
        }
        
        // This element is optional

        function duty_name()
        {
            if (isset($this->params['duty_name'])) {
                if ((strlen($this->params['duty_name']) > 0)
                  && (strlen($this->params['duty_name']) <= 31)) {
                    return "<name>" . $this->params['duty_name'] . "</name>";
                } else {
                    $this->error_messages[] .= 'setParameter(): duty_name must be up to 31 characters';
                }
            }
        }

        function duty_description()
        {
            if (isset($this->params['duty_description'])) {
                if ((strlen($this->params['duty_description']) > 0)
                  && (strlen($this->params['duty_description']) <= 255)) {
                    return "<description>" . $this->params['duty_description'] . "</description>";
                } else {
                    $this->error_messages[] .= 'setParameter(): duty_description must be up to 255 characters';
                }
            }
        }

        function transactionTaxExempt()
        {
            if (isset($this->params['transactionTaxExempt'])) {
                if (preg_match('/^(true|false)$/i', $this->params['transactionTaxExempt'])) {
                    return "<taxExempt>" . $this->params['transactionTaxExempt'] . "</taxExempt>";
                } else {
                    $this->error_messages[] .= 'setParameter(): transactionTaxExempt is required and must be (true or false)';
                }
            }
        }
        
        function transactionRecurringBilling()
        {
            if (isset($this->params['transactionRecurringBilling'])) {
                if (preg_match('/^(true|false)$/i', $this->params['transactionRecurringBilling'])) {
                    return "<recurringBilling>" . $this->params['transactionRecurringBilling'] . "</recurringBilling>";
                } else {
                    $this->error_messages[] .= 'setParameter(): transactionRecurringBilling must be (true or false)';
                }
            }
        }
        
        function transactionApprovalCode()
        {
            if (isset($this->params['transactionApprovalCode'])) {
                if (($this->transactionType() == "profileTransCaptureOnly")
                  && (strlen($this->params['transactionApprovalCode']) == 6)) {
                    return "<approvalCode>" . $this->params['transactionApprovalCode'] . "</approvalCode>";
                } else {
                    $this->error_messages[] .= 'setParameter(): transactionApprovalCode must be 6 characters and transactionType value must be (profileTransCaptureOnly)';
                }
            }
        }
        
        function transactionType()
        {
            if (isset($this->params['transactionType'])) {
                if (preg_match('/^(profileTransCaptureOnly|profileTransAuthCapture|profileTransAuthOnly)$/',
                  $this->params['transactionType'])) {
                    return $this->params['transactionType'];
                } else {
                    $this->error_messages[] .= 'setParameter(): transactionType must be (profileTransCaptureOnly, profileTransAuthCapture or profileTransAuthOnly)';
                }
            } else {
                $this->error_messages[] .= 'setParameter(): transactionType must be (profileTransCaptureOnly, profileTransAuthCapture or profileTransAuthOnly)';
            }
        }
    }

