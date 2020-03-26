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
        
        $node = $dom->importNode($data, true);
        $request->appendChild($node);
        
        $dom->appendChild($request);
        
        $full_request = $dom->saveXML();
        
        return ($full_request);
    }
    
    function customer_profile($customer_id, $email)
    {
        $dom = new DOMDocument();
        $dom->formatOutput = true;
        
        $profile = $dom->createElement('profile');
        $profile->appendChild($dom->createElement('merchantCustomerId', $customer_id));
        $profile->appendChild($dom->createElement('email', $email));
        
        $dom->appendChild($profile);
        
        $node = $dom->getElementsByTagName('profile')->item(0);
        return ($node);
    }
    
    function customer_payment_profile($customer_profile_id, $order, $validation_mode)
    {/*
        $dom = new DOMDocument();
        $dom->formatOutput = true;
        
        $profile = $dom->createElement('profile');
        $profile->appendChild($dom->createElement('merchantCustomerId', $customer_id));
        $profile->appendChild($dom->createElement('email', $email));
        
        $dom->appendChild($profile);
        
        $node = $dom->getElementsByTagName('profile')->item(0);
        return ($node);
*/
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
        
        
        $node = $dom->getElementsByTagName('data')->item(1);
        return $node;
        
        //echo $node;
        
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