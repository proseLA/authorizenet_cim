<?php
/*  portions copyright by... zen-cart.com

    developed and brought to you by proseLA
    https://rossroberts.com

    released under GPU
    https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0

   04/2020  project: authorizenet_cim; file: authorizenet_cim.php; version 2.0
*/

define('MODULE_PAYMENT_AUTHORIZENET_CIM_TEXT_ADMIN_TITLE',
    'Authorize.net (CIM)'); // Payment option title as displayed in the admin

if (defined('MODULE_PAYMENT_AUTHORIZENET_CIM_STATUS') and MODULE_PAYMENT_AUTHORIZENET_CIM_STATUS == 'True') {
    define('MODULE_PAYMENT_AUTHORIZENET_CIM_TEXT_DESCRIPTION',
        '<a target="_blank" href="https://account.authorize.net/">Authorize.net Merchant Login</a>' . (MODULE_PAYMENT_AUTHORIZENET_CIM_TESTMODE != 'Production' ? '<br /><br />Testing Info:<br /><b>Automatic Approval Credit Card Numbers:</b><br />Visa#: 4007000000027<br />MC#: 5424000000000015<br />Discover#: 6011000000000012<br />AMEX#: 370000000000002<br /><br /><b>Note:</b> These credit card numbers will return a decline in live mode, and an approval in test mode.  Any future date can be used for the expiration date and any 3 or 4 (AMEX) digit number can be used for the CVV Code.<br /><br /><b>Automatic Decline Credit Card Number:</b><br /><br />Card #: 4222222222222<br /><br />This card number can be used to receive decline notices for testing purposes.<br /><br />' : ''));
} else {
    define('MODULE_PAYMENT_AUTHORIZENET_CIM_STATUS', false);
    define('MODULE_PAYMENT_AUTHORIZENET_CIM_TEXT_DESCRIPTION',
        '<a target="_blank" href="http://reseller.authorize.net/application/?resellerId=111066">Click Here to Sign Up for an Account</a><br /><br /><a target="_blank" href="https://account.authorize.net/">Authorize.net Merchant Area</a><br /><br /><strong>Requirements:</strong><br /><hr />*<strong>Authorize.net Account</strong> (see link above to signup)<br />*<strong>CURL is required </strong>and MUST be compiled with SSL support into PHP by your hosting company<br />*<strong>Authorize.net username and transaction key</strong> available from your Merchant Area');
}
define('MODULE_PAYMENT_AUTHORIZENET_CIM_TEXT_ERROR_CURL_NOT_FOUND',
    'CURL functions not found - required for Authorize.net CIM payment module');

// Catalog Items
define('MODULE_PAYMENT_AUTHORIZENET_CIM_TEXT_CATALOG_TITLE',
    'New Credit Card');  // Payment option title as displayed to the customer
define('MODULE_PAYMENT_AUTHORIZENET_CIM_TEXT_CREDIT_CARD_TYPE', 'Credit Card Type:');
define('MODULE_PAYMENT_AUTHORIZENET_CIM_TEXT_CREDIT_CARD_OWNER', 'Cardholder Name:');
define('MODULE_PAYMENT_AUTHORIZENET_CIM_TEXT_CREDIT_CARD_NUMBER', 'Credit Card Number:');
define('MODULE_PAYMENT_AUTHORIZENET_CIM_TEXT_CREDIT_CARD_EXPIRES', 'Expiry Date:');
define('MODULE_PAYMENT_AUTHORIZENET_CIM_TEXT_CVV', 'CVV Number:');
define('MODULE_PAYMENT_AUTHORIZENET_CIM_TEXT_REORDER', 'Reorder every:');
define('MODULE_PAYMENT_AUTHORIZENET_CIM_TEXT_POPUP_CVV_LINK', 'What\'s this?');
define('MODULE_PAYMENT_AUTHORIZENET_CIM_TEXT_JS_CC_OWNER',
    '* The owner\'s name of the credit card must be at least ' . CC_OWNER_MIN_LENGTH . ' characters.\n');
define('MODULE_PAYMENT_AUTHORIZENET_CIM_TEXT_JS_CC_NUMBER',
    '* The credit card number must be at least ' . CC_NUMBER_MIN_LENGTH . ' characters.\n');
define('MODULE_PAYMENT_AUTHORIZENET_CIM_TEXT_JS_CC_CVV',
    '* The 3 or 4 digit CVV number must be entered from the back of the credit card.\n');
define('MODULE_PAYMENT_AUTHORIZENET_CIM_TEXT_DECLINED_MESSAGE',
    'Your credit card could not be authorized for this reason. Please correct the information and try again or contact us for further assistance.');
define('MODULE_PAYMENT_AUTHORIZENET_CIM_TEXT_ERROR', 'Credit Card Error!');

define('MODULE_PAYMENT_AUTHORIZENET_CIM_ENTRY_REFUND_TITLE', '<strong>Refund Transactions</strong>');
define('MODULE_PAYMENT_AUTHORIZENET_CIM_ENTRY_REFUND', 'You may refund money to the customer\'s credit card here:');
define('MODULE_PAYMENT_AUTHORIZENET_CIM_TEXT_REFUND_CONFIRM_CHECK', 'Check this box to confirm your intent: ');
define('MODULE_PAYMENT_AUTHORIZENET_CIM_ENTRY_REFUND_AMOUNT_TEXT', 'Enter the amount you wish to refund');
define('MODULE_PAYMENT_AUTHORIZENET_CIM_ENTRY_REFUND_CC_NUM_TEXT',
    'Enter the last 4 digits of the Credit Card you are refunding.');
define('MODULE_PAYMENT_AUTHORIZENET_CIM_ENTRY_REFUND_TRANS_ID', 'Enter the original Transaction ID:');
define('MODULE_PAYMENT_AUTHORIZENET_CIM_ENTRY_REFUND_TEXT_COMMENTS', 'Notes (will show on Order History):');
define('MODULE_PAYMENT_AUTHORIZENET_CIM_ENTRY_REFUND_DEFAULT_MESSAGE', 'Refund Issued');
define('MODULE_PAYMENT_AUTHORIZENET_CIM_ENTRY_REFUND_SUFFIX',
    'You may refund an order up to the amount already captured. You must supply the last 4 digits of the credit card number used on the initial order.<br />Refunds must be issued within 120 days of the original transaction date.');

define('MODULE_PAYMENT_AUTHORIZENET_CIM_ENTRY_CAPTURE_TITLE', '<strong>Capture Transactions</strong>');
define('MODULE_PAYMENT_AUTHORIZENET_CIM_ENTRY_CAPTURE', 'You may capture previously-authorized funds here:');
define('MODULE_PAYMENT_AUTHORIZENET_CIM_ENTRY_CAPTURE_AMOUNT_TEXT', 'Enter the amount to Capture: ');
define('MODULE_PAYMENT_AUTHORIZENET_CIM_TEXT_CAPTURE_CONFIRM_CHECK', 'Check this box to confirm your intent: ');
define('MODULE_PAYMENT_AUTHORIZENET_CIM_ENTRY_CAPTURE_TRANS_ID', 'Enter the original Transaction ID: ');
define('MODULE_PAYMENT_AUTHORIZENET_CIM_ENTRY_CAPTURE_TEXT_COMMENTS', 'Notes (will show on Order History):');
define('MODULE_PAYMENT_AUTHORIZENET_CIM_ENTRY_CAPTURE_DEFAULT_MESSAGE', 'Settled previously-authorized funds.');
define('MODULE_PAYMENT_AUTHORIZENET_CIM_ENTRY_CAPTURE_SUFFIX',
    'Captures must be performed within 30 days of the original authorization. You may only capture an order ONCE. <br />Please be sure the amount specified is correct.<br />If you leave the amount blank, the original amount will be used instead.');

define('MODULE_PAYMENT_AUTHORIZENET_CIM_ENTRY_VOID_TITLE', '<strong>Voiding Transactions</strong>');
define('MODULE_PAYMENT_AUTHORIZENET_CIM_ENTRY_VOID', 'You may void a transaction which has not yet been settled:');
define('MODULE_PAYMENT_AUTHORIZENET_CIM_TEXT_VOID_CONFIRM_CHECK', 'Check this box to confirm your intent:');
define('MODULE_PAYMENT_AUTHORIZENET_CIM_ENTRY_VOID_TEXT_COMMENTS', 'Notes (will show on Order History):');
define('MODULE_PAYMENT_AUTHORIZENET_CIM_ENTRY_VOID_DEFAULT_MESSAGE', 'Transaction Cancelled');
define('MODULE_PAYMENT_AUTHORIZENET_CIM_ENTRY_VOID_SUFFIX',
    'Voids must be completed before the original transaction is settled in the daily batch.');
    