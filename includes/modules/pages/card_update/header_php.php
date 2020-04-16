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
    
    require_once DIR_WS_MODULES . 'require_languages.php';
    
    require_once DIR_FS_CATALOG . DIR_WS_LANGUAGES . $_SESSION['language'] . '/modules/payment/authorizenet_cim.php';
    require DIR_WS_MODULES . 'payment/authorizenet_cim.php';
    
    $cim = new authorizenet_cim();
    
    $userProfile = $cim->getCustomerProfile($customer_id);
    if ($userProfile == false) {
        // do messageStack error
        //redirect
        die(__FILE__ . ':' . __LINE__);
    }
    
    if (isset($_POST['delete_cid'])) {
        $payment_profile = $cim->checkValidPaymentProfile($customer_id, $_POST['delete_cid']);
        $delete_cid = $cim->deleteCustomerPaymentProfile($userProfile, $payment_profile['payment_profile_id']);
        
        $start = strpos($delete_cid, 'ERROR');
        $startE0040 = strpos($log, 'E00040');
        if (($start === false) || ($startE0040 !== false)) {
            $messageStack->add_session(FILENAME_CARD_UPDATE, 'Your credit card has been deleted!', 'success');
        } else {
            $messageStack->add_session(FILENAME_CARD_UPDATE,
              'There was a problem deleting your card.  Please contact the store owner.', 'error');
        }
    }
    if (isset($_POST['update_cid'])) {
        $payment_profile = $cim->checkValidPaymentProfile($customer_id, $_POST['update_cid']);
        $update_cid = $cim->updateCustomerPaymentProfile($userProfile, $payment_profile['payment_profile_id']);
        
        $start = strpos($update_cid, 'ERROR');
        if ($start === false) {
            $messageStack->add_session(FILENAME_ACCOUNT, 'Your credit card has been UPDATED!', 'success');
            if (isset($_POST['address_selection']) && ($_POST['address_selection'] !== 'new')) {
                // maybe we need to do this in the authorizenet?
                //update_default_billto();
            }
            zen_redirect(zen_href_link(FILENAME_ACCOUNT, '', 'SSL'));
        } else {
            $messageStack->add_session(FILENAME_CARD_UPDATE, 'Problem updating your card: ' . $update_cid, 'error');
            zen_redirect(zen_href_link(FILENAME_CARD_UPDATE, '', 'SSL'));
        }
    }
    
    if (isset($_POST['new_cid'])) {
        $new_cid = $cim->createCustomerPaymentProfileRequest();
        
        $start = strpos($new_cid, 'ERROR');
        if ($start === false) {
            $messageStack->add_session(FILENAME_ACCOUNT, 'You have successfully added a new Credit Card!', 'success');
            if (isset($_POST['address_selection']) && ($_POST['address_selection'] !== 'new')) {
                //update_default_billto();
            }
            zen_redirect(zen_href_link(FILENAME_ACCOUNT, '', 'SSL'));
        } elseif ($new_cid->error) {
            $messageStack->add_session(FILENAME_CARD_UPDATE, $new_cid, 'error');
            zen_redirect(zen_href_link(FILENAME_CARD_UPDATE, '', 'SSL'));
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
    
    
    $zco_notifier->notify('NOTIFY_HEADER_END_CARD_UPDATE');


