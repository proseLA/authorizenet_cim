<?php
    
    class cim_order
    {
        var $payment, $refund, $payment_key, $payment_key_array;
        var $oID, $cID, $order_total, $amount_applied, $balance_due, $status, $status_date;
        
        // instantiates the class and gathers existing data
        function __construct($orders_id)
        {
            $this->payment = array();
            $this->refund = array();
            $this->payment_key = array();
            $this->payment_key_array = array();
            
            $this->oID = (int)$orders_id;   // now you have the order_id whenever you need it
            
            if (!defined('TABLE_CIM_PAYMENTS')) {
                include DIR_FS_CATALOG . DIR_WS_LANGUAGES . $_SESSION['language'] . '/modules/payment/cim_tables.php';
            }
            include DIR_FS_CATALOG . DIR_WS_ADMIN . DIR_WS_LANGUAGES . $_SESSION['language'] . '/cim_order.php';
            $this->start();
        }
        
        
        function start()
        {
            global $db;
            
            // scrape some useful info from the record in the orders table
            $order_query = $db->Execute("select * from " . TABLE_ORDERS . " where orders_id = '" . $this->oID . "'");
            $this->cID = $order_query->fields['customers_id'];
            $this->order_total = $order_query->fields['order_total'];
            
            if (zen_not_null($order_query->fields['date_cancelled'])) {
                $this->status_date = $order_query->fields['date_cancelled'];
                $this->status = "cancelled";
            } elseif (zen_not_null($order_query->fields['date_completed'])) {
                $this->status_date = $order_query->fields['date_completed'];
                $this->status = "completed";
            } else {
                $this->status_date = false;
                $this->status = false;
            }
            
            // build an array to translate the payment_type codes stored in so_payments
            $payment_key_query = $db->Execute("select * from " . TABLE_CIM_PAYMENT_TYPES . "
                                       where language_id = '" . $_SESSION['languages_id'] . "'
                                       order by payment_type_full asc");
            while (!$payment_key_query->EOF) {
                // this array is used by the full_type() function
                $this->payment_key_array[$payment_key_query->fields['payment_type_code']] = $payment_key_query->fields['payment_type_full'];
                
                // and this one can be used to build dropdown menus
                $this->payment_key[] = array(
                  'id' => $payment_key_query->fields['payment_type_code'],
                  'text' => $payment_key_query->fields['payment_type_full']
                );
                $payment_key_query->MoveNext();
            }
            
            // get all payments not tied to a purchase order
            $payments_query = $db->Execute("select * from " . TABLE_CIM_PAYMENTS . "
                                    where orders_id = '" . $this->oID . "'
                                    order by date_posted asc");
            
            if (zen_not_null($payments_query->fields['orders_id'])) {
                while (!$payments_query->EOF) {
                    $this->payment[] = array(
                      'index' => $payments_query->fields['payment_id'],
                      'number' => $payments_query->fields['transaction_id'],
                      'name' => $payments_query->fields['payment_name'],
                      'amount' => $payments_query->fields['payment_amount'],
                      'refund_amount' => $payments_query->fields['refund_amount'],
                      'type' => $payments_query->fields['payment_type'],
                      'posted' => $payments_query->fields['date_posted'],
                      'approval_code' => $payments_query->fields['approval_code']
                    );
                    $payments_query->MoveNext();
                }
            } else {
                unset($this->payment);
                $this->payment = false;
            }
            
            // get any refunds
            if ($this->payment) {   // gotta have payments in order to refund them
                $refunds_query = $db->Execute("select * from " . TABLE_CIM_REFUNDS . "
                                     where orders_id = '" . $this->oID . "'
                                     order by date_posted asc");
                
                if (zen_not_null($refunds_query->fields['orders_id'])) {
                    while (!$refunds_query->EOF) {
                        $this->refund[] = array(
                          'index' => $refunds_query->fields['refund_id'],
                          'payment' => $refunds_query->fields['payment_id'],
                          'number' => $refunds_query->fields['transaction_id'],
                          'name' => $refunds_query->fields['refund_name'],
                          'amount' => $refunds_query->fields['refund_amount'],
                          'type' => $refunds_query->fields['refund_type'],
                          'payment_number' => $refunds_query->fields['payment_trans_id'],
                          'posted' => $refunds_query->fields['date_posted'],
                          'approval_code' => $refunds_query->fields['approval_code']
                        );
                        $refunds_query->MoveNext();
                    }
                } else {
                    unset($this->refund);
                    $this->refund = false;
                }
            }
            
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
            $this->balance_due = $this->order_total - $this->amount_applied;
            
            // compare this balance to the one stored in the orders table, update if necessary
            if ($this->balance_due != $order_query->fields['balance_due']) {
                $this->new_balance();
            }
            
        }   // END function start
        
        
        // input the current value of $this->balance_due into balance_due
        // field in the orders table
        function new_balance()
        {
            //$a['balance_due'] = $this->balance_due;
            //zen_db_perform(TABLE_ORDERS, $a, 'update', 'orders_id = ' . $this->oID);
        }
        
        
        // timestamp the date_completed field in orders table
        // will also NULL out date_cancelled field if set (you can't have both at once!)
        function mark_completed()
        {
            global $db;
            if ($this->status == false || $this->status == "cancelled") {
                $db->Execute("UPDATE " . TABLE_ORDERS . " SET date_completed = now() WHERE orders_id = '" . $this->oID . "'");
                
                if ($this->status == "cancelled") {
                    $db->Execute("UPDATE " . TABLE_ORDERS . " SET date_cancelled = NULL WHERE orders_id = '" . $this->oID . "'");
                }
                if (STATUS_ORDER_COMPLETED != 0) {
                    update_status($this->oID, STATUS_ORDER_COMPLETED);
                }
                $this->status = "completed";
                $this->status_date = zen_datetime_short(date('Y-m-d H:i:s'));
            }
        }
        
        
        // timestamp the date_cancelled field in orders table
        // will also NULL out date_completed field if set (you can't have both at once!)
        function mark_cancelled()
        {
            global $db;
            if ($this->status == false || $this->status == "completed") {
                $db->Execute("UPDATE " . TABLE_ORDERS . " SET date_cancelled = now() WHERE orders_id = '" . $this->oID . "'");
                
                if ($this->status == "completed") {
                    $db->Execute("UPDATE " . TABLE_ORDERS . " SET date_completed = NULL WHERE orders_id = '" . $this->oID . "'");
                }
                if (STATUS_ORDER_CANCELLED != 0) {
                    update_status($this->oID, STATUS_ORDER_CANCELLED);
                }
                $this->status = "cancelled";
                $this->status_date = zen_datetime_short(date('Y-m-d H:i:s'));
            }
        }
        
        
        // removes the cancelled/completed timestamp
        function reopen()
        {
            global $db;
            $db->Execute("update " . TABLE_ORDERS . " set
                  date_completed = NULL, date_cancelled = NULL
                  where orders_id = '" . $this->oID . "' limit 1");
            
            if (STATUS_ORDER_REOPEN != 0) {
                update_status($this->oID, STATUS_ORDER_REOPEN);
            }
            $this->status = false;
            $this->status_date = false;
        }
        
        
        // Begin - Recreate authoriznet_cim information stored in orders table as a line item in SO payment system
        function cim_line_item()
        {
            global $db;
            // first we look for credit card payments
            $cc_data = $db->Execute("select customers_name, cc_type, cc_owner, cc_number, cc_expires, cc_cvv, date_purchased, order_total, payment_profile_id, transaction_id
                             from " . TABLE_ORDERS . " where orders_id = '" . $this->oID . "' limit 1");
            if ($cc_data->RecordCount()) {
                // convert CC type to match shorthand type in SO payemnt system
                // collect payment types from the DB
                /*$payment_data = $db->Execute("select * from " . TABLE_CIM_PAYMENT_TYPES . "
                                              where language_id = " . $_SESSION['languages_id']);
                $cc_type_key = array();
                while (!$payment_data->EOF) {
                  $cc_type_key[$payment_data->fields['payment_type_full']] = $payment_data->fields['payment_type_code'];
                  $payment_data->MoveNext();
                }
          */
                if ($cc_data->fields['cc_owner'] == '') {
                    $name = $cc_data->fields['customers_name'];
                } else {
                    $name = $cc_data->fields['cc_owner'];
                }
                // convert CC name to match shorthand type in SO payment system
                // the name used at checkout must match name entered into Admin > Localization > Payment Types!
                //$payment_type = $cc_type_key[$cc_data->fields['cc_type']];
                $new_cc_payment = array(
                  'orders_id' => $this->oID,
                  'payment_number' => $cc_data->fields['transaction_id'],
                  'payment_name' => $name,
                  'payment_amount' => $cc_data->fields['order_total'],
                  'payment_type' => 'authorizenet_cim',
                  'date_posted' => 'now()',
                  'last_modified' => 'now()'
                );
                
                zen_db_perform(TABLE_CIM_PAYMENTS, $new_cc_payment);
            }
        }
        
        
        // builds an array of all payments attached to an order, suitable for a dropdown menu
        function build_payment_array($include_blank = false)
        {
            global $db;
            $payment_array = array();
            
            // include a user-defined "empty" entry if requested
            if ($include_blank) {
                $payment_array[] = array(
                  'id' => false,
                  'text' => $include_blank
                );
            }
            
            $payment_query = $db->Execute("select payment_id, transaction_id from " . TABLE_CIM_PAYMENTS . " where orders_id = '" . $this->oID . "'");
            
            while (!$payment_query->EOF) {
                $payment_array[] = array(
                  'id' => $payment_query->fields['payment_id'],
                  'text' => $payment_query->fields['transaction_id']
                );
                $payment_query->MoveNext();
            }
            
            return $payment_array;
        }
        
        // Displays a button that will open a popup window to enter a new payment entry
        // This code assumes you have the popupWindow() function defined in your header!
        // Valid $payment_mode entries are: 'payment', 'purchase_order', 'refund'
        function button_add($payment_mode)
        {
            echo '&nbsp;<a href="javascript:couponpopupWindow(\'' .
              zen_href_link(FILENAME_CIM_PAYMENTS,
                'oID=' . $this->oID . '&payment_mode=' . $payment_mode . '&action=add',
                'NONSSL') . '\', \'scrollbars=yes,resizable=yes,width=400,height=300,screenX=150,screenY=100,top=100,left=150\')">' .
              zen_image_button('btn_' . $payment_mode . '.gif',
                sprintf(ALT_TEXT_ADD, str_replace('_', ' ', $payment_mode))) . '</a>';
        }
        
        // Displays a button that will open a popup window to update an existing payment entry
        // This code assumes you have the popupWindow() function defined in your header!
        // Valid $payment_mode entries are: 'payment', 'purchase_order', 'refund'
        function button_update($payment_mode, $index)
        {
            echo '&nbsp;<a href="javascript:couponpopupWindow(\'' .
              zen_href_link(FILENAME_CIM_PAYMENTS,
                'oID=' . $this->oID . '&payment_mode=' . $payment_mode . '&index=' . $index . '&action=my_update',
                'NONSSL') . '\', \'scrollbars=yes,resizable=yes,width=400,height=300,screenX=150,screenY=100,top=100,left=150\')">' .
              zen_image_button('btn_modify.gif',
                sprintf(ALT_TEXT_UPDATE, str_replace('_', ' ', $payment_mode))) . '</a>';
        }
        
        // Displays a button that will open a popup window to confirm deleting a payment entry
        // This code assumes you have the popupWindow() function defined in your header!
        // Valid $payment_mode entries are: 'payment', 'purchase_order', 'refund'
        function button_delete($payment_mode, $index)
        {
            echo '&nbsp;<a href="javascript:cimpopupWindow(\'' .
              zen_href_link(FILENAME_CIM_PAYMENTS,
                'oID=' . $this->oID . '&payment_mode=' . $payment_mode . '&index=' . $index . '&action=delete',
                'NONSSL') . '\', \'scrollbars=yes,resizable=yes,width=100,height=1000,screenX=150,screenY=100,top=100,left=150\')"' .
              'class="btn btn-danger" role="button" >Refund</a>';
            //    zen_image_button('btn_refund.gif', sprintf(ALT_TEXT_DELETE, str_replace('_', ' ', $payment_mode))) . '</a>';
        }
        
        
        function add_payment($payment_number, $payment_name, $payment_amount, $payment_type, $purchase_order_id = false)
        {
            
            $new_payment = array(
              'orders_id' => $this->oID,
              'transaction_id' => zen_db_prepare_input($payment_number),
              'payment_name' => zen_db_prepare_input($payment_name),
              'payment_amount' => zen_db_prepare_input($payment_amount),
              'payment_type' => zen_db_prepare_input($payment_type),
              'date_posted' => 'now()',
              'last_modified' => 'now()'
            );
            
            // link the payment to its P.O. if applicable
            if ($purchase_order_id) {
                $new_payment['purchase_order_id'] = (int)$purchase_order_id;
            }
            
            zen_db_perform(TABLE_CIM_PAYMENTS, $new_payment);
            
            $new_index = mysql_insert_id();
            return $new_index;
        }
        
        
        function update_payment(
          $payment_id,
          $purchase_order_id = false,
          $payment_number = false,
          $payment_name = false,
          $payment_amount = false,
          $payment_type = false,
          $orders_id = false
        ) {
            $update_payment = array();
            $update_payment['last_modified'] = 'now()';
            
            if ($orders_id && $orders_id != '') {
                $update_payment['orders_id'] = (int)$orders_id;
            }
            if ($payment_number && $payment_number != '') {
                $update_payment['payment_number'] = zen_db_prepare_input($payment_number);
            }
            if ($payment_name && $payment_name != '') {
                $update_payment['payment_name'] = zen_db_prepare_input($payment_name);
            }
            if ($payment_amount && $payment_amount != '') {
                $update_payment['payment_amount'] = zen_db_prepare_input($payment_amount);
            }
            if ($payment_type && $payment_type != '') {
                $update_payment['payment_type'] = zen_db_prepare_input($payment_type);
            }
            if (is_numeric($purchase_order_id)) {
                $update_payment['purchase_order_id'] = (int)$purchase_order_id;
            }
            
            zen_db_perform(TABLE_CIM_PAYMENTS, $update_payment, 'update', "payment_id = '" . $payment_id . "'");
        }
        
        
        function add_refund($payment_id, $refund_number, $refund_name, $refund_amount, $refund_type)
        {
            
            $new_refund = array(
              'payment_id' => (int)$payment_id,
              'orders_id' => $this->oID,
              'transaction_id' => zen_db_prepare_input($refund_number),
              'refund_name' => zen_db_prepare_input($refund_name),
              'refund_amount' => zen_db_prepare_input($refund_amount),
              'refund_type' => zen_db_prepare_input($refund_type),
              'date_posted' => 'now()',
              'last_modified' => 'now()'
            );
            
            zen_db_perform(TABLE_CIM_REFUNDS, $new_refund);
            
            $new_index = mysql_insert_id();
            return $new_index;
        }
        
        
        function update_refund(
          $refund_id,
          $payment_id = false,
          $refund_number = false,
          $refund_name = false,
          $refund_amount = false,
          $refund_type = false,
          $orders_id = false
        ) {
            $update_refund = array();
            $update_refund['last_modified'] = 'now()';
            
            if (is_numeric($payment_id)) {
                $update_refund['payment_id'] = (int)$payment_id;
            }
            if ($refund_number && $refund_number != '') {
                $update_refund['transaction_id'] = zen_db_prepare_input($refund_number);
            }
            if ($refund_name && $refund_name != '') {
                $update_refund['refund_name'] = zen_db_prepare_input($refund_name);
            }
            if ($refund_amount && $refund_amount != '') {
                $update_refund['refund_amount'] = zen_db_prepare_input($refund_amount);
            }
            if ($refund_type && $refund_type != '') {
                $update_refund['refund_type'] = zen_db_prepare_input($refund_type);
            }
            if ($orders_id && $orders_id != '') {
                $update_refund['orders_id'] = (int)$orders_id;
            }
            
            zen_db_perform(TABLE_CIM_REFUNDS, $update_refund, 'update', "refund_id = '" . $refund_id . "'");
        }
        
        
        function delete_refund($refund_id, $payment_id = false, $all = false)
        {
            global $db;
            $db->Execute("delete from " . TABLE_CIM_REFUNDS . " where refund_id = '" . $refund_id . "' limit 1");
        }
        
        
        function delete_payment($payment_id)
        {
            global $db;
            echo $payment_id;
            new dBug($this);
            die(__FILE__ . ':' . __LINE__);
            
            //$db->Execute("delete from " . TABLE_CIM_PAYMENTS . " where payment_id = '" . $payment_id . "' limit 1");
        }
        
        function delete_all_data()
        {
            global $db;
            // remove payment data
            $db->Execute("delete from " . TABLE_CIM_PAYMENTS . " where orders_id = '" . $this->oID . "'");
            // remove refund data
            $db->Execute("delete from " . TABLE_CIM_REFUNDS . " where orders_id = '" . $this->oID . "'");
        }
        
        
        // translates payment type codes into full text
        function full_type($code)
        {
            if (array_key_exists($code, $this->payment_key_array)) {
                $full_text = $this->payment_key_array[$code];
            } else {
                $full_text = $code;
            }
            return $full_text;
        }
        
        function find_refunds($payment_id)
        {
            $refund_array = array();
            
            for ($x = 0; $x < sizeof($this->refund); $x++) {
                if ($this->refund[$x]['payment'] == $payment_id) {
                    $refund_array[] = array(
                      'index' => $this->refund[$x]['index'],
                      'payment' => $payment_id,
                      'number' => $this->refund[$x]['number'],
                      'name' => $this->refund[$x]['name'],
                      'amount' => $this->refund[$x]['amount'],
                      'type' => $this->refund[$x]['type'],
                      'posted' => $this->refund[$x]['posted'],
                      'modified' => $this->refund[$x]['modified']
                    );
                }
            }
            
            return $refund_array;
        }
        
    }  // END class super_order