<?php
    
    function request_xml($request_type, $data)
    {
        $dom = new DOMDocument('1.0', 'utf-8');
        $dom->formatOutput = true;
        
        $request = $dom->createElementNS('AnetApi/xml/v1/schema/AnetApiSchema.xsd', $request_type);
        
        $merchant = $dom->createElement('merchantAuthentication');
        $merchant->appendChild($dom->createElement('name', trim(MODULE_PAYMENT_AUTHORIZENET_CIM_LOGIN)));
        $merchant->appendChild($dom->createElement('transactionKey', trim(MODULE_PAYMENT_AUTHORIZENET_CIM_TXNKEY)));
        $request->appendChild($merchant);
        
        foreach ($data->childNodes as $node) {
            $append_node = $dom->importNode($node, true);
            $request->appendChild($append_node);
        }
        
        $dom->appendChild($request);
        
        $full_request = $dom->saveXML();
        
        return ($full_request);
    }
    
    function customer_profile($customer_id, $email)
    {
        $dom = new DOMDocument();
        $dom->formatOutput = true;
        $data = $dom->createElement('data');
        
        $profile = $dom->createElement('profile');
        $profile->appendChild($dom->createElement('merchantCustomerId', $customer_id));
        $profile->appendChild($dom->createElement('email', $email));
        $data->appendChild($profile);
        
        $dom->appendChild($data);
        
        $node = $dom->getElementsByTagName('data')->item(0);
        return ($node);
    }
    
    function customer_payment_profile($customer_profile_id, $order, $validation_mode)
    {
        $dom = new DOMDocument();
        $dom->formatOutput = true;
        $data = $dom->createElement('data');
        
        $element = $dom->createElement('customerProfileId', $customer_profile_id);
        $data->appendChild($element);
        
        $paymentProfile = $dom->createElement('paymentProfile');
        $billTo = $dom->createElement('billTo');
        $billTo->appendChild($dom->createElement('firstName', $order->billing['firstname']));
        $billTo->appendChild($dom->createElement('lastName', $order->billing['lastname']));
        $billTo->appendChild($dom->createElement('company', $order->billing['company']));
        $billTo->appendChild($dom->createElement('address', $order->billing['street_address']));
        $billTo->appendChild($dom->createElement('city', $order->billing['city']));
        $billTo->appendChild($dom->createElement('zip', $order->billing['postcode']));
        $billTo->appendChild($dom->createElement('country', $order->billing['country']['title']));
        
        $paymentProfile->appendChild($billTo);
        
        $payment = $dom->createElement('payment');
        
        $creditCard = $dom->createElement('creditCard');
        
        $creditCard->appendChild($dom->createElement('cardNumber', $_POST['cc_number']));
        $creditCard->appendChild($dom->createElement('expirationDate', $_POST['cc_expires']));
        $creditCard->appendChild($dom->createElement('cardCode', $_POST['cc_cvv']));
        
        $payment->appendChild($creditCard);
        $paymentProfile->appendChild($payment);
        $data->appendChild($paymentProfile);
        $data->appendChild($dom->createElement('validationMode', $validation_mode));
        
        $dom->appendChild($data);
        
        $node = $dom->getElementsByTagName('data')->item(0);
        return $node;
    }
    
    function customer_payment_transaction($customer_profile_id, $payment_id, $order)
    {
        $dom = new DOMDocument();
        $dom->formatOutput = true;
        $data = $dom->createElement('data');
        
        $transaction = $dom->createElement('transaction');
        $profileTransAuthCapture = $dom->createElement('profileTransAuthCapture');
        $profileTransAuthCapture->appendChild($dom->createElement('amount', $order->info['total']));
        
        if (count($order->products) > 0) {
            foreach (array_slice($order->products, 0, 30) as $items) {
                $line_items = $dom->createElement('lineItems');
                $line_items->appendChild($dom->createElement('itemId', zen_get_prid($items['id'])));
                $line_items->appendChild($dom->createElement('name', substr(preg_replace('/[^a-z0-9_ ]/i', '',
                  preg_replace('/&nbsp;/', ' ', $items['name'])), 0, 30)));
                $line_items->appendChild($dom->createElement('quantity', $items['qty']));
                $line_items->appendChild($dom->createElement('unitPrice', $items['final_price']));
                $line_items->appendChild($dom->createElement('taxable', ($order->info['tax'] == 0 ? 'false' : 'true')));
                $profileTransAuthCapture->appendChild($line_items);
            }
        }
        
        $profileTransAuthCapture->appendChild($dom->createElement('customerProfileId', $customer_profile_id));
        $profileTransAuthCapture->appendChild($dom->createElement('customerPaymentProfileId', $payment_id));
        
        $order = $dom->createElement('order');
        $order->appendChild($dom->createElement('invoiceNumber', order_number($order->info)));
        $profileTransAuthCapture->appendChild($order);
        $transaction->appendChild($profileTransAuthCapture);
        
        $data->appendChild($transaction);
        $dom->appendChild($data);
        
        /*
        $html = $dom->saveHTML();
        print_r($html);
        die(__FILE__ . ':' . __LINE__);
        */
        
        $node = $dom->getElementsByTagName('data')->item(0);
        return $node;
    }
    
    function customer_refund_transaction($customer_profile_id, $payment_profile_id, $transaction_id, $amount)
    {
        $dom = new DOMDocument();
        $dom->formatOutput = true;
        $data = $dom->createElement('data');
        
        $transaction = $dom->createElement('transaction');
        $profileTransRefund = $dom->createElement('profileTransRefund');
        $profileTransRefund->appendChild($dom->createElement('amount', $amount));
        $profileTransRefund->appendChild($dom->createElement('customerProfileId', $customer_profile_id));
        $profileTransRefund->appendChild($dom->createElement('customerPaymentProfileId', $payment_profile_id));
        $profileTransRefund->appendChild($dom->createElement('transId', $transaction_id));
        
        $transaction->appendChild($profileTransRefund);
        
        $data->appendChild($transaction);
        $dom->appendChild($data);
        
        $node = $dom->getElementsByTagName('data')->item(0);
        return $node;
    }
    
    function customer_refund_payment($customer_profile_id, $payment_profile_id, $amount)
    {
        $dom = new DOMDocument();
        $dom->formatOutput = true;
        $data = $dom->createElement('data');
        
        $transaction = $dom->createElement('transaction');
        $profileTransRefund = $dom->createElement('profileTransRefund');
        $profileTransRefund->appendChild($dom->createElement('amount', $amount));
        $profileTransRefund->appendChild($dom->createElement('customerProfileId', $customer_profile_id));
        $profileTransRefund->appendChild($dom->createElement('customerPaymentProfileId', $payment_profile_id));
        
        $transaction->appendChild($profileTransRefund);
        
        $data->appendChild($transaction);
        $dom->appendChild($data);
        
        $node = $dom->getElementsByTagName('data')->item(0);
        return $node;
    }
    
    function customer_void_transaction($orderID, $transaction_id)
    {
        $dom = new DOMDocument();
        $dom->formatOutput = true;
        $data = $dom->createElement('data');
        
        $data->appendChild($dom->createElement('refId', $orderID));
        $transactionRequest = $dom->createElement('transactionRequest');
        $transactionRequest->appendChild($dom->createElement('TransactionType','voidTransaction'));
        $transactionRequest->appendChild($dom->createElement('reftransId', $transaction_id));
        
        $data->appendChild($transactionRequest);
        $dom->appendChild($data);
        
        $node = $dom->getElementsByTagName('data')->item(0);
        return $node;
    }
    
    function order_number($order)
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
    
    function updateCustomer($customerID, $profileID)
    {
        global $db;
        $sql = "UPDATE " . TABLE_CUSTOMERS . "
            SET customers_customerProfileId = :profileID
            WHERE customers_id = :custID ";
        $sql = $db->bindVars($sql, ':custID', $customerID, 'integer');
        $sql = $db->bindVars($sql, ':profileID', $profileID, 'integer');
        $db->Execute($sql);
    }
    
    function getCustomerProfile($customer_id)
    {
        global $db;
        $sql = "SELECT customers_customerProfileId FROM " . TABLE_CUSTOMERS . "
            WHERE customers_id = :custId ";
        $sql = $db->bindVars($sql, ':custId', $customer_id, 'string');
        $check_customer = $db->Execute($sql);
        
        if ($check_customer->fields['customers_customerProfileId'] !== 0) {
            $customerProfileId = $check_customer->fields['customers_customerProfileId'];
        } else {
            $customerProfileId = false;
        }
        return $customerProfileId;
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
        } else {
            $paymentProfileId = false;
        }
        return $paymentProfileId;
    }
    
    function save_cc_token($custId, $payment_profile, $lastfour, $exp_date, $save)
    {
        global $db;
        
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
    
    // function called only for full refund.
    function updateOrderInfo($ordersID)
    {
        global $db;
    
        $new_order_status = (int)MODULE_PAYMENT_AUTHORIZENET_CIM_REFUNDED_ORDER_STATUS_ID;
        if ($new_order_status == 0) {
            $new_order_status = 1;
        }
        $sql = "update " . TABLE_ORDERS . "
        	set approval_code = ' ', transaction_id = ' ', cc_authorized = '0', cc_authorized_date = null, orders_status = :stat
        	WHERE orders_id = :orderID ";
        $sql = $db->bindVars($sql, ':orderID', $ordersID, 'integer');
        $sql = $db->bindVars($sql, ':stat', $new_order_status, 'integer');
        $db->Execute($sql);
    }
    
    function insert_payment($transID, $name, $total, $type, $profileID, $approval, $custID)
    {
        global $db;
        $sql = "insert into " . TABLE_CIM_PAYMENTS . " ( payment_name, payment_amount, payment_type, date_posted, last_modified,  transaction_id, payment_profile_id, approval_code, customers_id)
VALUES (:nameFull, :amount, :type, now(), now(), :transID, :paymentProfileID, :approval_code, :custID)";
        
        $sql = $db->bindVars($sql, ':transID', $transID, 'string');
        $sql = $db->bindVars($sql, ':nameFull', $name, 'string');
        $sql = $db->bindVars($sql, ':amount', $total, 'noquotestring');
        $sql = $db->bindVars($sql, ':type', $type, 'string');
        $sql = $db->bindVars($sql, ':paymentProfileID', $profileID, 'string');
        $sql = $db->bindVars($sql, ':approval_code', $approval, 'string');
        $sql = $db->bindVars($sql, ':custID', $custID, 'string');
        $db->Execute($sql);
    }
    
    function insert_refund($paymentID, $ordersID, $transID, $name, $payment_trans_id, $amount, $type)
    {
        global $db;
        $sql = "insert into " . TABLE_CIM_REFUNDS . " (payment_id, orders_id, transaction_id, refund_name, refund_amount, refund_type, payment_trans_id, date_posted, last_modified) values (:paymentID, :orderID, :transID, :payment_name, :amount, :type, :payment_trans_id, now(), now() )";
        $sql = $db->bindVars($sql, ':paymentID', $paymentID, 'integer');
        $sql = $db->bindVars($sql, ':orderID', $ordersID, 'integer');
        $sql = $db->bindVars($sql, ':transID', $transID, 'string');
        $sql = $db->bindVars($sql, ':payment_name', $name, 'string');
        $sql = $db->bindVars($sql, ':type', $type, 'string');
        $sql = $db->bindVars($sql, ':payment_trans_id', trim($payment_trans_id), 'string');
        $sql = $db->bindVars($sql, ':amount', $amount, 'noquotestring');
        $db->Execute($sql);
    }
    
    function update_payment($ordersID, $transID, $amount)
    {
        global $db;
        $sql = "update " . TABLE_CIM_PAYMENTS . " set refund_amount = (refund_amount + :amount) where transaction_id = :transID and orders_id = :orderID";
        $sql = $db->bindVars($sql, ':orderID', $ordersID, 'integer');
        $sql = $db->bindVars($sql, ':transID', trim($transID), 'string');
        $sql = $db->bindVars($sql, ':amount', $amount, 'noquotestring');
        $db->Execute($sql);
    }
    
    // returns payment profile ID or false if the index is not found or it does not match the logged in customer.
    function check_customer_card($customerID, $cc_index)
    {
        global $db;
        $sql = "SELECT * FROM " . TABLE_CUSTOMERS_CC . "
            WHERE index_id = :indexID";
        $sql = $db->bindVars($sql, ':indexID', $cc_index, 'integer');
        $check_customer_cc = $db->Execute($sql);
        
        if ($check_customer_cc->fields['customers_id'] !== $customerID) {
            $return = false;
        } else {
            $return = $check_customer_cc->fields['payment_profile_id'];
        }
        return $return;
    
    }
    
    function after_process_common($customerID, $transID, $insertID, $approval, $payment_profile_id, $status)
    {
        global $db;
        
        $sql = "update  " . TABLE_CIM_PAYMENTS . " set orders_id = :insertID
            WHERE customers_id = :custId and transaction_id = :transID and orders_id = 0";
        $sql = $db->bindVars($sql, ':custId', $customerID, 'integer');
        $sql = $db->bindVars($sql, ':transID', $transID, 'string');
        $sql = $db->bindVars($sql, ':insertID', $insertID, 'integer');
        $db->Execute($sql);
        
        $sql = "update " . TABLE_ORDERS . "
        	set approval_code = :approvalCode, transaction_id = :transID, cc_authorized = '1',
        	payment_profile_id = :payProfileID, orders_status = :orderStatus,
        	cc_authorized_date = now()
        	WHERE orders_id = :insertID ";
        $sql = $db->bindVars($sql, ':approvalCode', $approval, 'string');
        $sql = $db->bindVars($sql, ':transID', $transID, 'string');
        $sql = $db->bindVars($sql, ':insertID', $insertID, 'integer');
        $sql = $db->bindVars($sql, ':orderStatus', $status, 'integer');
        $sql = $db->bindVars($sql, ':payProfileID', $payment_profile_id, 'integer');
        $db->Execute($sql);
        
        $sql = "insert into " . TABLE_ORDERS_STATUS_HISTORY . " (comments, orders_id, orders_status_id, date_added) values (:orderComments, :orderID, :orderStatus, now() )";
        $sql = $db->bindVars($sql, ':orderComments',
          'Credit Card payment.  AUTH: ' . $approval . '. TransID: ' . $transID . '.',
          'string');
        $sql = $db->bindVars($sql, ':orderID', $insertID, 'integer');
        $sql = $db->bindVars($sql, ':orderStatus', $status, 'integer');
        $db->Execute($sql);
    }