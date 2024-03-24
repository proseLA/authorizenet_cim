<?php

    $define = [
        'TEXT_CIM_DATA' => 'Authorizenet CIM Payments',
        'CIM_NUMBER' => 'Transaction ID',
        'CIM_NAME' => 'Payment Name',
        'CIM_AMOUNT' => 'Amount',
        'CIM_TYPE' => 'Payment Type',
        'CIM_POSTED' => 'Date Posted',
        'CIM_MODIFIED' => 'Date Captured',
        'CIM_ACTION' => 'Action',
        'CIM_APPROVAL' => 'Code',

        'LAST_CARD' => 'Last Card Used: ',

        'HEADER_DELETE_PAYMENT' => 'Refund Payment',
        'HEADER_VOID_PAYMENT' => 'Void Payment',
        'DELETE_PAYMENT_NOTE',
        'Leave blank for Total Refund.<br>',
        'DELETE_VOID_NOTE',
        'This payment is unsettled.  You can void the payment, or wait until after settlement (less than 24 hours), to issue a refund.',
        'HEADER_DELETE_PO' => 'Delete Purchase Order',
        'HEADER_DELETE_REFUND' => 'Delete Refund',
        'HEADER_MORE_MONEY' => 'New Auth/Capture',

        'DELETE_CARDS' => 'Are you sure you want to delete all the stored credit cards for: ',

        'HEADER_ORDER_ID' => 'Order #',
        'HEADER_PAYMENT_UID' => 'Payment UID #',
        'HEADER_REFUND_UID' => 'Refund UID #',

        'HEADER_CAPTURE_FUNDS' => 'Get the Money!<br />Capture Funds',
        'CAPTURE_NOTE' => 'Leave Blank to Capture Full Amount',
        'WARN_CAPTURE_PAYMENT' => 'Are you sure you want to capture these funds?',
        'HEADER_CAPTURE_DONE' => 'Capture was Successful!',
        'HEADER_CAPTURE_FAIL' => 'There was a Problem with the Capture',

        'CAPTURE_BAD_STATUS' => 'According to authorize.net, this payment is not in proper status for capture!',
        'CAPTURE_NOT_MATCH' => 'The order payment does not match the the order posted from the form',
        'MORE_MONEY_ERROR' => 'There is no amount due on this order.',
        'MORE_MONEY_POST_ERROR' => 'Please enter how much you want to charge.',

        'TEXT_AMOUNT' => 'Amount:',
        'TEXT_NO_MINUS' => ' * No minus sign',
        'CURRENT_TOTAL' => 'Current Order Total: ',
        'CHARGE_DESCRIPTION' => 'Charge Description: ',

        'CURRENT_BALANCE' => 'Balance Due: ',

        'LEAVE_BLANK' => 'Leave blank to get current balance due on order.',

        'BUTTON_SUBMIT' => 'Submit',
        'BUTTON_CANCEL' => 'Cancel',
        'BUTTON_SAVE_CLOSE' => 'Close & Return',
        'BUTTON_MODIFY' => 'Modify',
        'BUTTON_ADD_NEW' => 'Add Another',
        'BUTTON_ADD_PAYMENT' => 'Add Payment',
        'BUTTON_DELETE_CARDS' => 'Delete Credit Cards',
        'BUTTON_CAPTURE' => 'Capture',
        'BUTTON_REFUND' => 'Refund',
        'BUTTON_NEW_FUNDS' => 'Get Money',

        'WARN_DELETE_PAYMENT' => 'Are you sure you want to refund/void this payment?<p>This action cannot be undone!',

        'WARN_MORE_MONEY' => 'Are you sure you want to get a new authorization?',

        'HEADER_REFUND_DONE' => 'Refund/Void Successful',
        'HEADER_REFUND_FAIL' => 'Refund/Void Failed!',

        'HEADER_MORE_MONEY_DONE' => 'New Authorization Successful!',
        'HEADER_MORE_MONEY_FAIL' => 'New Authorization Failed!',
        'HEADER_PAYMENT_INDEX_ERROR' => 'Problem: Index not Part of Order!',

        'PAYMENT_INDEX_ERROR' => 'Payment Index Error.  Log File generated.',

        'TEXT_DELETE_CONFIRM' => 'The operation is complete.<p><strong>%s</strong> line(s) affected in the process.<br/>',
        'BUTTON_DELETE_CONFIRM' => 'Return',

        'CAPTURED_AT_GATEWAY' => 'According to authorize.net, this payment is already captured at the gateway!',
    ];
    return $define;
