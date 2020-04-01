<?php
    
    /**
     *  saved_cc payment module
     *  prose_la
     */
    include_once((IS_ADMIN_FLAG === true ? DIR_FS_CATALOG_MODULES : DIR_WS_MODULES) . 'payment/authorizenet/cim_functions.php');
    
    class saved_cc extends base
    {
        var $code, $title, $description, $enabled;
        
        var $reportable_submit_data = array();
        
        /* cim variables */
        
        // cim
        
        var $version = '2.0'; // the code revision number for this class
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
        
        // class constructor
        function __construct()
        {
            global $order;
            
            $this->code = 'saved_cc';
            $this->title = MODULE_PAYMENT_SAVED_CC_TEXT_TITLE;
            // this module is entirely dependent on the authorizenet_cim module.  if that is not enabled.  neither is this.
            $this->enabled = ((MODULE_PAYMENT_AUTHORIZENET_CIM_STATUS == 'True') ? true : false);
            if ($this->enabled == true) {
                // $this->title = 'Saved Credit Card';
                $this->description = MODULE_PAYMENT_SAVED_CC_TEXT_DESCRIPTION; // Descriptive Info about module in Admin
                $this->sort_order = 1; // Sort Order of this payment option on the customer payment page
            } else {
                $this->title .= ' <span class="alert">(to enable; enable authorizenet CIM module)</span>';
            }
            $this->order_status = (int)DEFAULT_ORDERS_STATUS_ID;
            if (defined('MODULE_PAYMENT_AUTHORIZENET_CIM_ORDER_STATUS_ID') and (int)MODULE_PAYMENT_AUTHORIZENET_CIM_ORDER_STATUS_ID > 0) {
                $this->order_status = (int)MODULE_PAYMENT_AUTHORIZENET_CIM_ORDER_STATUS_ID;
            }
        }
        
        function javascript_validation()
        {
            return false;
        }
        
        function selection()
        {
            global $order;
            global $reorder_var;
            global $db;
            
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
            $cc_test = $today['year'] . '-' . str_pad($today['mon'], 2, 0, STR_PAD_LEFT);
            $enabled = " and enabled = 'Y' ";
            if (($_SESSION['emp_admin_login'] == true)) {
                $enabled = '';
            }
            $sql = "Select * from " . TABLE_CUSTOMERS_CC . " where exp_date >= '" . $cc_test . " ' and customers_id = :custID " . $enabled . " order by index_id desc";
            
            $sql = $db->bindVars($sql, ':custID', $_SESSION['customer_id'], 'integer');
            $card_on_file = $db->Execute($sql);
            
            $cards = array();
            
            while (!$card_on_file->EOF) {
                $_SESSION['saved_cc'] = 'yes';
                $cards[] = array(
                  'id' => $card_on_file->fields['index_id'],
                  'text' => 'Card ending in ' . $card_on_file->fields['last_four']
                );
                $card_on_file->MoveNext();
            }
            
            $selection = array(
              'id' => $this->code,
              'module' => 'Stored Credit Card',
                // 'index' => $card_on_file->fields['index_id'],
              'fields' => array(
                array(
                  'title' => 'Saved Credit Card',
                  'field' => zen_draw_pull_down_menu('saved_cc', $cards, ''), //.$this->code.'-saved-cc"' . $onFocus),
                  'tag' => 'card_index'
                )
              )
            );
            
            if (!empty($cards)) {
                return $selection;
            } else {
                return false;
            }
            
        }
        
        function pre_confirmation_check()
        {
            return false;
        }
        
        function confirmation()
        {
            $_SESSION['saved_cc'] = (int)($_POST['saved_cc']);
            return array('title' => MODULE_PAYMENT_SAVED_CC_TEXT_DESCRIPTION);
        }
        
        function process_button()
        {
            return false;
        }
        
        function before_process()
        {
            global $response, $db, $order, $messageStack, $customerID;
            
            // echo '--------> ' . . ' <----------';
            $cc_index = $_SESSION['saved_cc'];
            $customerID = $_SESSION['customer_id'];
            
            $valid_payment_profile = check_customer_card($customerID, $cc_index);
            
            if (!$valid_payment_profile) {
                $messageStack->add_session('checkout_payment',
                  'There was a problem with that card.  Please select a different card!', 'error');
                trigger_error('the card index does not correspond to the right customer!');
                zen_redirect(zen_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL', true, false));
            }
            
            $customerProfileId = getCustomerProfile($customerID);
            $this->setParameter('customerProfileId', $customerProfileId);
            $this->setParameter('customerPaymentProfileId', $valid_payment_profile);
            
            $this->createCustomerProfileTransactionRequest();
        }
        
        function createCustomerProfileTransactionRequest()
        {
            global $order;
            
            $data = customer_payment_transaction($this->params['customerProfileId'],
              $this->params['customerPaymentProfileId'], $order);
            $this->xml = request_xml('createCustomerProfileTransactionRequest', $data);
            
            $this->process();
            
            if ($this->isSuccessful()) {
                
                insert_payment($this->transID, $order->billing['firstname'] . ' ' . $order->billing['lastname'],
                  $order->info['total'],
                  $this->code, $this->params['customerPaymentProfileId'], $this->approvalCode,
                  $_SESSION['customer_id']);
                
            } else {
                $this->log = 'payment request order: ' . $order->info['orders_id'] . '; Error: ' . $this->cim_code . ' ' . $this->text;
                $this->die_message = 'no success - customer profile Transaction request';
                $this->logError(DEBUG_CIM);
            }
        }
    
 
        function after_process()
        {
            global $insert_id, $db, $order;
            
            after_process_common($customerID, $this->transID, $insert_id, $this->approvalCode,
              $this->params['customerPaymentProfileId'], $this->order_status);
            
            /*
            $sql = "select * from " . TABLE_CUSTOMERS_CC . " where customers_id = :customerID and index_id = :indexID ";
            $sql = $db->bindVars($sql, ':indexID', $order->info['saved_cc'], 'integer');
            $sql = $db->bindVars($sql, ':customerID', $_SESSION['customer_id'], 'integer');
            $card_on_file = $db->Execute($sql);
            
            if (!$card_on_file->EOF) {
                $cc_num = 'xxxx-xxxx-xxxx-' . $card_on_file->fields['last_four'];
                $exp_date = substr($card_on_file->fields['exp_date'], -2) . substr($card_on_file->fields['exp_date'], 2,
                    2);
                $sql = 'update ' . TABLE_ORDERS . ' set payment_profile_id = :ppid,  cc_number = :ccnum, cc_expires = :ccexp, payment_method = "Credit Card on file" where orders_id = :oID';
                $sql = $db->bindVars($sql, ':ppid', $card_on_file->fields['payment_profile_id'], 'integer');
                $sql = $db->bindVars($sql, ':ccnum', $cc_num, 'string');
                $sql = $db->bindVars($sql, ':ccexp', $exp_date, 'integer');
                $sql = $db->bindVars($sql, ':oID', $insert_id, 'integer');
                $db->Execute($sql);
            }
            */
            return false;
        }
        
        function get_error()
        {
            return false;
        }
        
        function check()
        {
            global $db;
            return true;
        }
        
        function install()
        {
            global $db, $messageStack;
        }
        
        function remove()
        {
            global $db;
            //  $db->Execute("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
        }
        
        function keys()
        {
            //  return array('MODULE_PAYMENT_MONEYORDER_STATUS', 'MODULE_PAYMENT_MONEYORDER_ZONE', 'MODULE_PAYMENT_MONEYORDER_ORDER_STATUS_ID', 'MODULE_PAYMENT_MONEYORDER_SORT_ORDER', 'MODULE_PAYMENT_MONEYORDER_PAYTO');
            return array();
        }
        // common functions with authorizenet_cim
    
        function setParameter($field = "", $value = null)
        {
            $this->params[$field] = $value;
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
                    
                    curl_setopt($ch, CURLOPT_HEADER, false);
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
                    $this->logError(true);
                }
                curl_close($ch);
            } else {
                $this->success = false;
                $this->error = true;
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
        
        function customParse($xmlTag)
        {
            foreach ($this->dom->getElementsByTagName($xmlTag) as $element) {
                return $element->nodeValue;
            }
        }
        
        function logError($log_xml = false)
        {
            global $messageStack, $order, $lookXML;
            $lookXML = false;
            //$log_xml = true;
            
            $error_log = DIR_FS_LOGS . '/cim_error.log';
            $error_sent = DIR_FS_LOGS . '/cim_xml_sent.log';
            
            //if (IS_ADMIN_FLAG && !$lookXML) {
            $messageStack->add_session('CIM: ' . $this->error_messages[0] . '; ->' . $this->log, 'error');
            //}
            
            if ($log_xml) {
                error_log(date(DATE_RFC822) . ': ' . $this->xml . "\n", 3, $error_sent);
            }
            
            if (!empty($this->log) || !empty($this->error_messages[0])) {
                error_log(date(DATE_RFC822) . ': ' . print_r($this->error_messages) . '; ->' . $this->log . "\n", 3,
                  $error_log);
            }
            if ($this->cim_code == 'E00003') {
                error_log(date(DATE_RFC822) . ': ' . $this->error_messages[0] . '; XML----->' . $this->xml . "\n", 3,
                  $error_log);
            }
            if ($this->cim_code == 'E00027') {
                //error_log(date(DATE_RFC822) . ': Response:' . $this->response . "\n", 3, $error_log);
            }
            //sleep(2);
        }
        
        function isSuccessful()
        {
            return $this->success;
        }
        
    }
