<?php
    
    define('HEADER_ENTER_PAYMENT', 'Enter Payment');
    define('HEADER_ENTER_PO', 'Enter Purchase Order');
    define('HEADER_ENTER_REFUND', 'Enter Refund');
    
    define('HEADER_UPDATE_PAYMENT', 'Modify Payment');
    define('HEADER_UPDATE_PO', 'Modify PO');
    define('HEADER_UPDATE_REFUND', 'Modify Refund');
    
    define('HEADER_CONFIRM_PAYMENT', 'Review Payment');
    define('HEADER_CONFIRM_PO', 'Review Purchase Order');
    define('HEADER_CONFIRM_REFUND', 'Review Refund');
    
    //define('HEADER_DELETE_PAYMENT', 'Delete Payment');
    
    define('HEADER_DELETE_PAYMENT', 'Refund Payment');
    define('DELETE_PAYMENT_NOTE',
      'Leave blank for Total Refund.<br>Payments not settled will void;<br>irrespective of amount entered.');
    define('HEADER_DELETE_PO', 'Delete Purchase Order');
    define('HEADER_DELETE_REFUND', 'Delete Refund');
    
    define('HEADER_ORDER_ID', 'Order #');
    define('HEADER_PAYMENT_UID', 'Payment UID #');
    define('HEADER_REFUND_UID', 'Refund UID #');
    define('HEADER_PO_UID', 'Purchase Order UID #');
    
    define('TEXT_PAYMENT_NUMBER', 'Number:');
    define('TEXT_PAYMENT_NAME', 'Name:');
    define('TEXT_PAYMENT_AMOUNT', 'Amount:');
    define('TEXT_PAYMENT_TYPE', 'Type:');
    define('TEXT_ATTACHED_PO', 'P.O. Assignment:');
    
    define('TEXT_PO_NUMBER', 'P.O. Number:');
    
    define('TEXT_ATTACHED_PAYMENT', 'Target Payment:');
    define('TEXT_REFUND_NUMBER', 'Number:');
    define('TEXT_REFUND_NAME', 'Name:');
    define('TEXT_REFUND_AMOUNT', 'Amount:');
    define('TEXT_REFUND_TYPE', 'Type');
    define('TEXT_NO_MINUS', ' * No minus sign');
    
    define('BUTTON_SUBMIT', 'Submit');
    define('BUTTON_CANCEL', 'Cancel');
    define('BUTTON_SAVE_CLOSE', 'Close & Return');
    define('BUTTON_MODIFY', 'Modify');
    define('BUTTON_ADD_NEW', 'Add Another');
    define('BUTTON_ADD_PAYMENT', 'Add Payment');
    
    define('CHECKBOX_UPDATE_STATUS', 'Update order status with pre-set status & comments?');
    define('CHECKBOX_NOTIFY_CUSTOMER', 'Notify the customer?');
    
    define('WARN_DELETE_PAYMENT', 'Are you sure you want to refund this payment?<p>This action cannot be undone!');
    define('WARN_DELETE_PO', 'Are you sure you want to delete this purchase order?<p>This action cannot be undone!');
    define('WARN_DELETE_REFUND', 'Are you sure you want to delete this refund?<p>This action cannot be undone!');
    
    define('TEXT_REFUND_ACTION',
      '<strong>%s</strong> refund(s) currently tied to this payment.  What do you want to do?');
    define('REFUND_ACTION_KEEP', 'Keep the refund associated with the order, but not a particular payment');
    define('REFUND_ACTION_MOVE', 'Move the refund to another payment: ');
    define('REFUND_ACTION_DROP', 'Remove it along with the refund');
    
    define('TEXT_PAYMENT_ACTION',
      '<strong>%s</strong> payment(s) currently tied to this P.O.  What do you want to do?');
    define('PAYMENT_ACTION_KEEP', 'Keep the payment associated with the order, but not a particular P.O.');
    define('PAYMENT_ACTION_MOVE', 'Move the payment to another P.O.:');
    define('PAYMENT_ACTION_DROP', 'Remove it along with the payment');
    
    define('HEADER_DELETE_CONFIRM', 'Delete Successful');
    define('TEXT_DELETE_CONFIRM', 'The operation is complete.<p><strong>%s</strong> line(s) affected in the process.');
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