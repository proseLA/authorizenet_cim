<?php
    
    /**
     *  authorizenet_cof payment module
     *  prose_la
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
            // this module is entirely dependent on the authorizenet_cim module.  if that is not enabled.  neither is this.
            $this->enabled = ((MODULE_PAYMENT_AUTHORIZENET_CIM_STATUS == 'True') ? true : false);
            if ($this->enabled == true) {
                $this->description = MODULE_PAYMENT_SAVED_CC_TEXT_DESCRIPTION; // Descriptive Info about module in Admin
                $this->sort_order = 1; // Sort Order of this payment option on the customer payment page
            } else {
                $this->title .= ' <span class="alert">(to enable; enable authorizenet CIM module)</span>';
            }
        }
    
        function javascript_validation()
        {
            return false;
        }
        
        function selection()
        {
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
              'fields' => array(
                array(
                  'title' => 'Saved Credit Card',
                  'field' => zen_draw_pull_down_menu('saved_cc_index', $cards, '', $onFocus),
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
            global $messageStack;
            $_SESSION['saved_cc_index'] = $_POST['saved_cc_index'];
        }
        
        function confirmation()
        {
            return array('title' => MODULE_PAYMENT_SAVED_CC_TEXT_DESCRIPTION);
        }
        
        function process_button()
        {
            return false;
        }
        
        function before_process()
        {
            global $messageStack, $customerID;
            
            $cc_index = $_SESSION['saved_cc_index'];
            $customerID = $_SESSION['customer_id'];
            
            $valid_payment_profile = $this->checkValidPaymentProfile($customerID, $cc_index);
            
            if (!$valid_payment_profile['valid']) {
                $messageStack->add_session('checkout_payment',
                  'There was a problem with that card.  Please select a different card!', 'error');
                trigger_error('the card index does not correspond to the right customer!');
                zen_redirect(zen_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL', true, false));
            }
            
            $customerProfileId = $this->getCustomerProfile($customerID);
            $this->setParameter('customerProfileId', $customerProfileId);
            $this->setParameter('customerPaymentProfileId', $valid_payment_profile['payment_profile_id']);
    
            $this->response = $this->chargeCustomerProfile($customerProfileId, $valid_payment_profile['payment_profile_id']);
    
            $this->addErrorsMessageStack('Customer Payment Transaction');
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
        }
        
        function keys()
        {
            return array();
        }
    }
