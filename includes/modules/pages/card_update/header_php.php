<?php
	/*  portions copyright by... zen-cart.com

		developed and brought to you by proseLA
		https://rossroberts.com

		released under GPU
		https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0

	   01/2021  project: authorizenet_cim; file: header_php.php; version 2.2.3
	*/

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

	$addressSelected = $_POST['address_selection'] ?? '';
	require_once DIR_WS_MODULES . 'require_languages.php';

	require_once DIR_FS_CATALOG . DIR_WS_LANGUAGES . $_SESSION['language'] . '/modules/payment/authorizenet_cim.php';
	require DIR_WS_MODULES . 'payment/authorizenet_cim.php';

	$cim = new authorizenet_cim();
	$userProfile = $cim->getCustomerProfile($customer_id);
	$user = $cim->getCustomer();

	if ($userProfile == false) {
		$messageStack->add_session(FILENAME_ACCOUNT, 'Sorry, you have no credit cards on file.', 'error');
		zen_redirect(zen_href_link(FILENAME_ACCOUNT, '', 'SSL'));
	}

	if (isset($_POST['delete_cid'])) {
		$payment_profile = $cim->checkValidPaymentProfile($customer_id, $_POST['delete_cid']);
		$delete_cid = $cim->deleteCustomerPaymentProfile($userProfile, $payment_profile['payment_profile_id']);

		$start = strpos($delete_cid, 'ERROR');
		$startE0040 = strpos($delete_cid, 'E00040');
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
			$cim->updateDefaultCustomerBillto($addressSelected);
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
			$cim->updateDefaultCustomerBillto($addressSelected);
			zen_redirect(zen_href_link(FILENAME_ACCOUNT, '', 'SSL'));
		} elseif ($new_cid->error) {
			$messageStack->add_session(FILENAME_CARD_UPDATE, $new_cid, 'error');
			zen_redirect(zen_href_link(FILENAME_CARD_UPDATE, '', 'SSL'));
		}
	}
	$today = getdate();
	for ($i = $today['year']; $i < $today['year'] + 10; $i++) {
		$expires_year[] = array(
			'id' => strftime('%y', mktime(0, 0, 0, 1, 1, $i)),
			'text' => strftime('%Y', mktime(0, 0, 0, 1, 1, $i))
		);
	}
	for ($i = 1; $i < 13; $i++) {
		$expires_month[] = array(
			'id' => sprintf('%02d', $i),
			'text' => strftime('%B', mktime(0, 0, 0, $i, 1, 2000))
		);
	}

	if (($messageStack->size('card_update') > 0) && (($_REQUEST['action'] ?? '') !== 'delete')) {
		echo $messageStack->output('card_update');
		$messageStack->reset();
	}
	$h2_title = 'Select Billing Address for Credit Card or Enter New Billing Address';
	$div_id = 'cc_address';
	$new_address_title = 'New Bill-To Address';

	$breadcrumb->add(NAVBAR_TITLE_1, zen_href_link(FILENAME_ACCOUNT, '', 'SSL'));
	$breadcrumb->add(NAVBAR_TITLE);

	if (IS_ADMIN_FLAG && $_SESSION['emp_admin_login'] == true) {
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
	$entry_query = "SELECT entry_country_id
                  FROM   " . TABLE_ADDRESS_BOOK . " a, " . TABLE_CUSTOMERS . " c
                  WHERE  a.customers_id = :customersID
                  AND  a.customers_id = c.customers_id
                  AND    a.address_book_id = c.customers_default_address_id";

	$entry_query = $db->bindVars($entry_query, ':customersID', $_SESSION['customer_id'], 'integer');
	$entry = $db->Execute($entry_query);

	if ($entry->EOF) {
		$entry->fields['entry_country_id'] = '223';
	}
	$zone_id = 0;


	$entry->fields['entry_gender'] = 'm';
	$entry->fields['entry_firstname'] = '';
	$entry->fields['entry_lastname'] = '';
	$entry->fields['entry_company'] = '';
	$entry->fields['entry_street_address'] = '';
	$entry->fields['entry_suburb'] = '';
	$entry->fields['entry_city'] = '';
	$entry->fields['entry_state'] = '';
	$entry->fields['entry_zone_id'] = 0;
	$entry->fields['entry_postcode'] = '';

	$flag_show_pulldown_states = true;
	$selected_country = 223;
	$state_field_label = ENTRY_STATE;

	include_once(DIR_WS_CLASSES . 'cc_validation.php');
