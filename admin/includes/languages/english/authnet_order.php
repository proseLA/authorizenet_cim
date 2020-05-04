<?php
/*  portions copyright by... zen-cart.com

    developed and brought to you by proseLA
    https://rossroberts.com

    released under GPU
    https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0

   04/2020  project: authorizenet_cim; file: authnet_order.php; version 2.0
*/

    define('TEXT_CIM_DATA', 'Authorizenet CIM Payments');
    define('CIM_NUMBER', 'Transaction ID');
    define('CIM_NAME', 'Payment Name');
    define('CIM_AMOUNT', 'Amount');
    define('CIM_TYPE', 'Payment Type');
    define('CIM_POSTED', 'Date Posted');
    define('CIM_MODIFIED', 'Last Modified');
    define('CIM_ACTION', 'Action');
    define('CIM_APPROVAL', 'Code');

    define('HEADER_DELETE_PAYMENT', 'Refund Payment');
    define('HEADER_VOID_PAYMENT', 'Void Payment');
    define('DELETE_PAYMENT_NOTE',
      'Leave blank for Total Refund.<br>');
    define('DELETE_VOID_NOTE', 'This payment is unsettled.  You can void the payment, or wait until after settlement (less than 24 hours), to issue a refund.');
    define('HEADER_DELETE_PO', 'Delete Purchase Order');
    define('HEADER_DELETE_REFUND', 'Delete Refund');

    define('DELETE_CARDS', 'Are you sure you want to delete all the stored credit cards for: ');
    
    define('HEADER_ORDER_ID', 'Order #');
    define('HEADER_PAYMENT_UID', 'Payment UID #');
    define('HEADER_REFUND_UID', 'Refund UID #');

    define('HEADER_CAPTURE_FUNDS', 'Get the Money!<br />Capture Funds');
    define('CAPTURE_NOTE', 'Leave Blank to Capture Full Amount');
    define('WARN_CAPTURE_PAYMENT', 'Are you sure you want to capture these funds?');
    define('HEADER_CAPTURE_DONE', 'Capture was Successful!');
    define('HEADER_CAPTURE_FAIL', 'There was a Problem with the Capture');

    define('CAPTURE_BAD_STATUS', 'According to authorize.net, this payment is not in proper status for capture!');
    define('CAPTURE_NOT_MATCH', 'The order payment does not match the the order posted from the form');

    define('TEXT_AMOUNT', 'Amount:');
    define('TEXT_NO_MINUS', ' * No minus sign');
    
    define('BUTTON_SUBMIT', 'Submit');
    define('BUTTON_CANCEL', 'Cancel');
    define('BUTTON_SAVE_CLOSE', 'Close & Return');
    define('BUTTON_MODIFY', 'Modify');
    define('BUTTON_ADD_NEW', 'Add Another');
    define('BUTTON_ADD_PAYMENT', 'Add Payment');
    define('BUTTON_DELETE_CARDS', 'Delete Credit Cards');
    define('BUTTON_CAPTURE', 'Capture');
    define('BUTTON_REFUND', 'Refund');
    
    define('WARN_DELETE_PAYMENT', 'Are you sure you want to refund/void this payment?<p>This action cannot be undone!');

    define('HEADER_REFUND_DONE', 'Refund/Void Successful');
    define('HEADER_REFUND_FAIL', 'Refund/Void Failed!');
    define('TEXT_DELETE_CONFIRM', 'The operation is complete.<p><strong>%s</strong> line(s) affected in the process.<br/>');
    define('BUTTON_DELETE_CONFIRM', 'Return');
    
    define('EMAIL_SEPARATOR', '------------------------------------------------------');
    define('EMAIL_TEXT_SUBJECT', 'Order Update');
    define('EMAIL_TEXT_ORDER_NUMBER', 'Order Number:');
    define('EMAIL_TEXT_INVOICE_URL', 'Detailed Invoice:');
    define('EMAIL_TEXT_DATE_ORDERED', 'Date Ordered:');
    define('EMAIL_TEXT_COMMENTS_UPDATE', "\n" . '<br><strong><em>Comments:</em></strong><br>' . "\n");
    define('EMAIL_TEXT_STATUS_UPDATED', "\n" . '<br>Your current order status is:  ');
    define('EMAIL_TEXT_STATUS_LABEL', '<em>%s</em><br><br>' . "\n\n");
    define('EMAIL_TEXT_STATUS_PLEASE_REPLY',
      "\n" . '<br>Please reply to this email if you have any questions.<br><br>' . "\n\n");