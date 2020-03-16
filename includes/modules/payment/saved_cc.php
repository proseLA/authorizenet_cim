<?php

/**
 * @package saved_cc payment module
 *
 * @package paymentMethod
 * @copyright Copyright 2003-2010 Zen Cart Development Team
 * @copyright Portions Copyright 2003 osCommerce
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: saved_cc.php 15420 2014-2-17 21:27:05Z prose_la $
 */
class saved_cc extends base
{
    var $code, $title, $description, $enabled;

// class constructor
    function __construct()
    {
        global $order;

        $this->code = 'saved_cc';
        $this->title = MODULE_PAYMENT_SAVED_CC_TEXT_TITLE;
        // $this->title = 'Saved Credit Card';
        $this->description = MODULE_PAYMENT_SAVED_CC_TEXT_DESCRIPTION; // Descriptive Info about module in Admin
        $this->sort_order = 1; // Sort Order of this payment option on the customer payment page
        $this->enabled = true;
    }

    function javascript_validation()
    {
        return false;
    }

    function selection()
    {
        global $order;
        global $reorder_var;
        global $db;

        for ($i = 1; $i < 13; $i++) {
            $expires_month[] = array(
                'id' => sprintf('%02d', $i),
                'text' => strftime('%B - (%m)', mktime(0, 0, 0, $i, 1, 2000))
            );
        }

        $today = getdate();
        for ($i = $today['year']; $i < $today['year'] + 10; $i++) {
            $expires_year[] = array(
                'id' => strftime('%y', mktime(0, 0, 0, 1, 1, $i)),
                'text' => strftime('%Y', mktime(0, 0, 0, 1, 1, $i))
            );
        }

        $onFocus = ' onfocus="methodSelect(\'pmt-' . $this->code . '\')"';
        $cc_test = $today['year'] . '-' . str_pad($today['mon'], 2, 0, STR_PAD_LEFT);
        $enabled = " and enabled = 'Y' ";
        if (($_SESSION['emp_admin_login'] == true)) {
            $enabled = '';
        }
        $sql = "Select * from customers_cc where exp_date >= '" . $cc_test . " ' and customers_id = :custID " . $enabled . " order by index_id desc";

        $sql = $db->bindVars($sql, ':custID', $_SESSION['customer_id'], 'integer');
        $card_on_file = $db->Execute($sql);

        $cards = array();

        while (!$card_on_file->EOF) {
            $_SESSION['saved_cc'] = 'yes';
            $cards[] = array(
                'id' => $card_on_file->fields['index_id'],
                'text' => 'Card ending in ' . $card_on_file->fields['last_four']
            );
            $card_on_file->MoveNext();
        }

        $selection = array(
            'id' => $this->code,
            'module' => 'Stored Credit Card',
            // 'index' => $card_on_file->fields['index_id'],
            'fields' => array(
                array(
                    'title' => 'Saved Credit Card',
                    'field' => zen_draw_pull_down_menu('saved_cc', $cards, ''), //.$this->code.'-saved-cc"' . $onFocus),
                    'tag' => 'card_index'
                )
            )
        );

        if (!empty($cards)) {
            return $selection;
        } else {
            return false;
        }

    }

    function pre_confirmation_check()
    {
        return false;
    }

    function confirmation()
    {
        $_SESSION['saved_cc'] = (int)($_POST['saved_cc']);
        return array('title' => MODULE_PAYMENT_SAVED_CC_TEXT_DESCRIPTION);
    }

    function process_button()
    {
        return false;
    }

    function before_process()
    {
        return false;
    }

    function after_process()
    {
        global $insert_id, $db, $order;

        $sql = "select * from customers_cc where customers_id = :customerID and index_id = :indexID ";
        $sql = $db->bindVars($sql, ':indexID', $order->info['saved_cc'], 'integer');
        $sql = $db->bindVars($sql, ':customerID', $_SESSION['customer_id'], 'integer');
        $card_on_file = $db->Execute($sql);

        if (!$card_on_file->EOF) {
            $cc_num = 'xxxx-xxxx-xxxx-' . $card_on_file->fields['last_four'];
            $exp_date = substr($card_on_file->fields['exp_date'], -2) . substr($card_on_file->fields['exp_date'], 2, 2);
            $sql = 'update ' . TABLE_ORDERS . ' set payment_profile_id = :ppid,  cc_number = :ccnum, cc_expires = :ccexp, payment_method = "Credit Card on file" where orders_id = :oID';
            $sql = $db->bindVars($sql, ':ppid', $card_on_file->fields['payment_profile_id'], 'integer');
            $sql = $db->bindVars($sql, ':ccnum', $cc_num, 'string');
            $sql = $db->bindVars($sql, ':ccexp', $exp_date, 'integer');
            $sql = $db->bindVars($sql, ':oID', $insert_id, 'integer');
            $db->Execute($sql);
        }
        return false;
    }

    function get_error()
    {
        return false;
    }

    function check()
    {
        global $db;
        return true;
    }

    function install()
    {
        global $db, $messageStack;
    }

    function remove()
    {
        global $db;
        //  $db->Execute("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
    }

    function keys()
    {
        //  return array('MODULE_PAYMENT_MONEYORDER_STATUS', 'MODULE_PAYMENT_MONEYORDER_ZONE', 'MODULE_PAYMENT_MONEYORDER_ORDER_STATUS_ID', 'MODULE_PAYMENT_MONEYORDER_SORT_ORDER', 'MODULE_PAYMENT_MONEYORDER_PAYTO');
        return false;
    }
}
