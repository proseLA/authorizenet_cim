<?php
    
    // if the customer is not logged on, redirect them to the login page
    if (!zen_is_logged_in() || zen_in_guest_checkout()) {
        $_SESSION['navigation']->set_snapshot();
        zen_redirect(zen_href_link(FILENAME_LOGIN, '', 'SSL'));
    } else {
        // validate customer
        if (zen_get_customer_validate_session($_SESSION['customer_id']) == false) {
            $_SESSION['navigation']->set_snapshot();
            zen_redirect(zen_href_link(FILENAME_LOGIN, '', 'SSL'));
        }
    }
    $customer_id = $_SESSION['customer_id'];
    
    require DIR_WS_MODULES . 'require_languages.php';
    require DIR_WS_MODULES . 'payment/authorizenet_cim.php';
    
     $cim = new authorizenet_cim();
    
    //require DIR_WS_LANGUAGES . $_SESSION['language'] . '/modules/payment/cim_tables.php';
    
    $user = $cim->getCustomerProfile($customer_id);
    
    //$messageStack->reset();
    
    if (isset($_POST['delete_cid'])) {
        $delete_cid = delete_ccid($_POST['delete_cid']);
        if (($delete_cid->error) && ($delete_cid->cim_code != 'E00040')) {
            $messageStack->add_session('card_update', $delete_cid->cim_code . '; ' . $delete_cid->text, 'error');
        } else {
            $messageStack->add_session('card_update', 'Your credit card has been deleted!', 'success');
        }
    }
    if (isset($_POST['update_cid'])) {
        $validate_error = false;
        $update_cid = create_ccid();  // create_ccid is now used for update as well as creation!
        
        if ($validate_error) {
            zen_redirect(zen_href_link('card_update', 'cid=' . $_POST['update_cid'] . '&action=update', 'SSL'));
        }
        if (($update_cid->error)) {
            $messageStack->add_session('card_update', $update_cid->cim_code . '; ' . $update_cid->text, 'error');
        } else {
            $messageStack->add_session(FILENAME_ACCOUNT, 'Your credit card has been changed!', 'success');
            if (isset($_POST['address_selection']) && ($_POST['address_selection'] !== 'new')) {
                update_default_billto();
            }
            zen_redirect(zen_href_link(FILENAME_ACCOUNT, '', 'SSL'));
        }
    }
    
    if (isset($_POST['new_cid'])) {
        $new_cid = create_ccid();
        if ($new_cid->success) {
            $messageStack->add_session(FILENAME_ACCOUNT, 'You have successfully added a new Credit Card!', 'success');
            if (isset($_POST['address_selection']) && ($_POST['address_selection'] !== 'new')) {
                update_default_billto();
            }
            zen_redirect(zen_href_link(FILENAME_ACCOUNT, '', 'SSL'));
        } elseif ($new_cid->error) {
            $messageStack->add_session('card_update', $update_cid->cim_code . '; ' . $update_cid->text, 'error');
        }
    }
    
    $breadcrumb->add(NAVBAR_TITLE);
    
   
    
    if ($_SESSION['emp_admin_login'] == true) {
        $cards_saved = $cim->getCustomerCards($customer_id, true);
    } else {
        $cards_saved = $cim->getCustomerCards($customer_id);
    }
    
    
    
    
    $addresses_query = "SELECT address_book_id, entry_firstname as firstname, entry_lastname as lastname,
       entry_company as company, entry_street_address as street_address, entry_suburb as suburb, entry_city as city,
       entry_postcode as postcode,
                      entry_state as state, entry_zone_id as zone_id, entry_country_id as country_id
                    FROM   " . TABLE_ADDRESS_BOOK . "
                    WHERE  customers_id = :customersID
                    ORDER BY firstname, lastname";
    
    $addresses_query = $db->bindVars($addresses_query, ':customersID', $customer_id, 'integer');
    $addresses = $db->Execute($addresses_query);
    
    while (!$addresses->EOF) {
        $format_id = zen_get_address_format_id($addresses->fields['country_id']);
        $addressArray[] = array(
          'firstname' => $addresses->fields['firstname'],
          'lastname' => $addresses->fields['lastname'],
          'address_book_id' => $addresses->fields['address_book_id'],
          'format_id' => $format_id,
          'address' => $addresses->fields
        );
        $addresses->MoveNext();
    }
    
    $today = getdate();
    
    
    
    include_once(DIR_WS_CLASSES . 'cc_validation.php');
    
    $cim->setParameter('customerProfileId', $user->fields['customers_customerProfileId']); // Numeric (required)
    
    $cim->setParameter('refId', $customer_id); // Up to 20 characters (optional)
    $cim->setParameter('validationMode', $cim->validationMode);
    
    $card_date = "20" . zen_db_prepare_input($_POST['authorizenet_cim_cc_expires_year']);
    $card_date .= '-' . zen_db_prepare_input($_POST['authorizenet_cim_cc_expires_month']);
    
    $csql = "select * from " . TABLE_COUNTRIES . "
           WHERE countries_id = :countryID ";
    $csql = $db->bindVars($csql, ':countryID', zen_db_prepare_input($_POST['zone_country_id']), 'integer');
    $country = $db->Execute($csql);
    $bill_country = $country->fields['countries_iso_code_2'];
    
    $cim->setParameter('paymentType', 'creditCard');
    $cim->setParameter('cardNumber', $card_number);
    $cim->setParameter('customerPaymentProfileId', zen_db_prepare_input($_POST['use_cc']));
    
    $cc_sql = "select * from " . TABLE_CUSTOMERS_CC . "
      WHERE customers_id = :custID and payment_profile_id = :cppID";
    $cc_sql = $db->bindVars($cc_sql, ':custID', $customer_id, 'integer');
    $cc_sql = $db->bindVars($cc_sql, ':cppID', zen_db_prepare_input($_POST['use_cc']), 'integer');
    $cc = $db->Execute($cc_sql);
    
    $cim->setParameter('cardNumber', 'XXXXXXXXXXXX' . $cc->fields['last_four']);
    
    $cim->setParameter('expirationDate', $card_date); // (YYYY-MM)
    
    $cim->setParameter('billTo_firstName',
      zen_db_prepare_input($_POST['firstname'])); // Up to 50 characters (no symbols)
    $cim->setParameter('billTo_lastName', zen_db_prepare_input($_POST['lastname'])); // Up to 50 characters (no symbols)
    if ($_POST['company'] != "") {
        $cim->setParameter('billTo_company', zen_db_prepare_input($_POST['company']));
    } // Up to 50 characters (no symbols) (optional)
    $cim->setParameter('billTo_address',
      zen_db_prepare_input($_POST['street_address'])); // Up to 60 characters (no symbols)
    $cim->setParameter('billTo_city', zen_db_prepare_input($_POST['city'])); // Up to 40 characters (no symbols)
    $cim->setParameter('billTo_state',
      zen_db_prepare_input($_POST['state'])); // A valid two-character state code (US only) (optional)
    $cim->setParameter('billTo_zip', zen_db_prepare_input($_POST['postcode'])); // Up to 20 characters (no symbols)
    $cim->setParameter('billTo_country', $bill_country); // Up to 60 characters (no symbols) (optional)
    
    function validate_ccid($ccID)
    {
        global $db;
        
        $sql = "select * from " . TABLE_CUSTOMERS_CC . "
          WHERE customers_id = :custID and index_id = :iid";
        $sql = $db->bindVars($sql, ':custID', $customer_id, 'integer');
        $sql = $db->bindVars($sql, ':iid', $ccID, 'integer');
        return $db->Execute($sql);
        
    }
    
    function update_default_billto()
    {
        global $db;
        $sql = "update " . TABLE_CUSTOMERS . " set customers_default_address_id = :cdaID
          WHERE customers_id = :custID";
        $sql = $db->bindVars($sql, ':custID', $customer_id, 'integer');
        $sql = $db->bindVars($sql, ':cdaID', $_POST['address_selection'], 'integer');
        $db->Execute($sql);
        return;
    }
    
    function delete_ccid($ccID)
    {
        global $db, $user;
        
        $valid_cid = validate_ccid($ccID);
        $cim = new authorizenet_cim();
        $cim->setParameter('customerPaymentProfileId', zen_db_prepare_input($valid_cid->fields['payment_profile_id']));
        $cim->setParameter('refId', $customer_id); // Up to 20 characters (optional)
        $cim->setParameter('customerProfileId', $user->fields['customers_customerProfileId']);
        $cim->setParameter('action', 'card_delete');
        
        $delete = $cim->deleteCustomerPaymentProfileRequest();
        return ($delete);
        
    }
    
    function create_ccid()
    {
        global $db, $user, $messageStack, $validate_error;
        
        include_once(DIR_WS_CLASSES . 'cc_validation.php');
        
        $validate_error = false;
        if (!isset($_POST['address_selection']) && (isset($_POST['new_cid']))) {
            $messageStack->add_session('card_update', 'You must select an address or select and enter a new address',
              'error');
            $validate_error = true;
        }
        
        if ($_POST['address_selection'] == 'new') {
            if (!isset($_POST['street_address']) || ($_POST['street_address'] == '')) {
                $messageStack->add_session('card_update', 'You must enter a street address.', 'error');
                $validate_error = true;
            }
            if (!isset($_POST['city']) || ($_POST['city'] == '')) {
                $messageStack->add_session('card_update', 'You must enter a city.', 'error');
                $validate_error = true;
            }
            if (!isset($_POST['state']) || ($_POST['state'] == '')) {
                $messageStack->add_session('card_update', 'You must enter a state.', 'error');
                $validate_error = true;
            }
            if (!isset($_POST['postcode']) || ($_POST['postcode'] == '')) {
                $messageStack->add_session('card_update', 'You must enter a postal code.', 'error');
                $validate_error = true;
            }
        }
        
        if (isset($_POST['new_cid'])) {
            $cc_valid = new cc_validation();
            $result = $cc_valid->validate($_POST['cc_number'], $_POST['cc_month'], $_POST['cc_year'], '');
            
            $card_error = false;
            $error = '';
            if (($result < 0) || ($result == false)) {
                switch ($result) {
                    case -1:
                        $error = sprintf(TEXT_CCVAL_ERROR_UNKNOWN_CARD, substr($cc_valid->cc_number, 0, 4));
                        $card_error = true;
                        break;
                    case -2:
                    case -3:
                    case -4:
                        $error = TEXT_CCVAL_ERROR_INVALID_DATE;
                        $card_error = true;
                        break;
                    case false:
                        $error = TEXT_CCVAL_ERROR_INVALID_NUMBER;
                        $card_error = true;
                        break;
                }
                $messageStack->add_session('card_update', $error, 'error');
                return;
            }
        }
        
        if ($validate_error) {
            if (!isset($update_cid)) {
                $update_cid = new stdClass();
            }
            $update_cid->error = true;
            return;
        }
        
        $cim = new authorizenet_cim();
        $cim->setParameter('customerProfileId', $user->fields['customers_customerProfileId']); // Numeric (required)
        
        $cim->setParameter('refId', $customer_id); // Up to 20 characters (optional)
        $cim->setParameter('validationMode', $cim->validationMode);
        
        $card_date = "20" . zen_db_prepare_input($_POST['cc_year']);
        $card_date .= '-' . zen_db_prepare_input($_POST['cc_month']);
        $cim->setParameter('expirationDate', $card_date); // (YYYY-MM)
        
        $cim->setParameter('paymentType', 'creditCard');
        $cim->setParameter('cardNumber', zen_db_prepare_input($_POST['cc_number']));
        $cim->setParameter('merchantCustomerId', $customer_id);
        
        if (isset($_POST['update_cid'])) {
            $valid_cid = validate_ccid($_POST['update_cid']);
            $cim->setParameter('customerPaymentProfileId',
              zen_db_prepare_input($valid_cid->fields['payment_profile_id']));
            $cim->setParameter('refId', $customer_id); // Up to 20 characters (optional)
            $cim->setParameter('billTo_firstName',
              $user->fields['customers_firstname']); // Up to 50 characters (no symbols)
            $cim->setParameter('billTo_lastName', $user->fields['customers_lastname']);
            $cim->setParameter('cardNumber', 'XXXXXXXXXXXX' . zen_db_prepare_input($valid_cid->fields['last_four']));
        } else {
            $owner = zen_db_prepare_input($_POST['cc_owner']);
            $pos = strpos($owner, ' ');
            if ($pos == false) {
                $first_name = '';
                $last_name = $owner;
            } else {
                $first_name = substr($owner, 0, $pos);
                $last_name = substr($owner, $pos);
            }
            $cim->setParameter('billTo_firstName', $first_name); // Up to 50 characters (no symbols)
            $cim->setParameter('billTo_lastName', $last_name); // Up to 50 characters (no symbols)
        }
        
        if ($_POST['address_selection'] == 'new') {
            $cim->setParameter('billTo_address',
              zen_db_prepare_input($_POST['street_address'])); // Up to 60 characters (no symbols)
            $cim->setParameter('billTo_city', zen_db_prepare_input($_POST['city'])); // Up to 40 characters (no symbols)
            //$cim->setParameter('billTo_state', zen_db_prepare_input($_POST['state'])); // A valid two-character state code (US only) (optional)
            $cim->setParameter('billTo_zip',
              zen_db_prepare_input($_POST['postcode'])); // Up to 20 characters (no symbols)
            
            $csql = "select * from " . TABLE_COUNTRIES . "
           WHERE countries_id = :countryID ";
            $csql = $db->bindVars($csql, ':countryID', zen_db_prepare_input($_POST['zone_country_id']), 'integer');
            $country = $db->Execute($csql);
            $bill_country = $country->fields['countries_iso_code_2'];
            $cim->setParameter('billTo_country', $bill_country); // Up to 60 characters (no symbols) (optional)
        } else {
            $addresses_query = "SELECT * FROM   " . TABLE_ADDRESS_BOOK . "
                    WHERE  customers_id = :customersID and address_book_id = :ad_book_id";
            $addresses_query = $db->bindVars($addresses_query, ':customersID', $customer_id, 'integer');
            $addresses_query = $db->bindVars($addresses_query, ':ad_book_id', $_POST['address_selection'], 'integer');
            $addresses = $db->Execute($addresses_query);
            $cim->setParameter('billTo_address',
              $addresses->fields['entry_street_address']); // Up to 60 characters (no symbols)
            $cim->setParameter('billTo_city', $addresses->fields['entry_city']); // Up to 40 characters (no symbols)
            //$cim->setParameter('billTo_state', $addresses->fields['entry_state']); // A valid two-character state code (US only) (optional)
            $cim->setParameter('billTo_zip', $addresses->fields['entry_postcode']); // Up to 20 characters (no symbols)
            $csql = "select * from " . TABLE_COUNTRIES . "
           WHERE countries_id = :countryID ";
            $csql = $db->bindVars($csql, ':countryID', $addresses->fields['entry_country_id'], 'integer');
            $country = $db->Execute($csql);
            $bill_country = $country->fields['countries_iso_code_2'];
            $cim->setParameter('billTo_country', $bill_country); // Up to 60 characters (no symbols) (optional)
        }
        
        $cim->setParameter('card_update', 'new_card');
        $cim->setParameter('cc_save', 'on');
        if (isset($_POST['update_cid'])) {
            $cim->updateCustomerPaymentProfileRequest();
        } else {
            $create = $cim->createCustomerPaymentProfileRequest();
        }
        return ($cim);
    }
    
    $zco_notifier->notify('NOTIFY_HEADER_END_CARD_UPDATE');


