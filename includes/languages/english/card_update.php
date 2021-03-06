<?php
/*  portions copyright by... zen-cart.com

    developed and brought to you by proseLA
    https://rossroberts.com

    released under GPU
    https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0

   01/2021  project: authorizenet_cim; file: card_update.php; version 2.2.3
*/
define('NAVBAR_TITLE_1', 'My Account');
define('NAVBAR_TITLE', 'Update Card');
define('HEADING_TITLE', 'Update Credit Card on Authorize.net');

define('CARD_UPDATE_CURRENT_DATA', 'Current Data:');

define('TITLE_PLEASE_SELECT', 'Address Details');
define('CARD_UPDATE_AUTHORIZENET_CIM_TEXT_CARD_INFO', 'Please enter your new card info below:');
define('CARD_UPDATE_AUTHORIZENET_CIM_TEXT_CREDIT_CARD_TYPE', 'Credit Card Type:');
define('CARD_UPDATE_AUTHORIZENET_CIM_TEXT_CREDIT_CARD_OWNER', 'Credit Card Owner:');
define('CARD_UPDATE_AUTHORIZENET_CIM_TEXT_CREDIT_CARD_NUMBER', 'Credit Card Number:');
define('CARD_UPDATE_AUTHORIZENET_CIM_TEXT_CREDIT_CARD_EXPIRES', 'Credit Card Expiry Date:');
define('CARD_UPDATE_AUTHORIZENET_CIM_TEXT_JS_CC_OWNER',
    '* The owner\'s name of the credit card must be at least ' . CC_OWNER_MIN_LENGTH . ' characters.\n');
define('CARD_UPDATE_AUTHORIZENET_CIM_TEXT_JS_CC_NUMBER',
    '* The credit card number must be at least ' . CC_NUMBER_MIN_LENGTH . ' characters.\n');
define('CARD_UPDATE_AUTHORIZENET_CIM_TEXT_JS_CC_CVV',
    '* You must enter the 3 or 4 digit number on the back of your credit card');
define('CARD_UPDATE_AUTHORIZENET_CIM_TEXT_ERROR', 'Credit Card Error!');

define('CARD_UPDATE_ERROR', 'Error updating information.');
define('CARD_UPDATE_OK', 'Successfully updated information.');

define('FILENAME_CARD_UPDATE', 'card_update');
