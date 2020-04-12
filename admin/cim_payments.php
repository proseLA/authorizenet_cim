<?php
    /*
    //////////////////////////////////////////////////////////////////////////
    //  SUPER ORDERS v3.0                                               	//
    //                                                                  	//
    //  Based on Super Order 2.0                                        	//
    //  By Frank Koehl - PM: BlindSide (original author)                	//
    //                                                                  	//
    //  Super Orders Updated by:						//
    //  ~ JT of GTICustom							//
    //  ~ C Jones Over the Hill Web Consulting (http://overthehillweb.com)	//
    //  ~ Loose Chicken Software Development, david@loosechicken.com	//
    //                                                      		//
    //  Powered by Zen-Cart (www.zen-cart.com)              		//
    //  Portions Copyright (c) 2005 The Zen-Cart Team       		//
    //                                                     			//
    //  Released under the GNU General Public License       		//
    //  available at www.zen-cart.com/license/2_0.txt       		//
    //  or see "license.txt" in the downloaded zip          		//
    //////////////////////////////////////////////////////////////////////////
    //  DESCRIPTION:   This file generates a pop-up window that is used to 	//
    //	enter and edit payment information for a given order.		//
    //////////////////////////////////////////////////////////////////////////
    // $Id: super_batch_forms.php v 2010-10-24 $
    */
    
    require 'includes/application_top.php';
    require_once DIR_WS_CLASSES . 'cim_order.php';
    if (!defined('FILENAME_CIM_PAYMENTS')) {
        require_once DIR_FS_CATALOG . DIR_WS_LANGUAGES . $_SESSION['language'] . '/modules/payment/authorizenet_cim.php';
    }
    
    if (defined('DEBUG_CIM') && (DEBUG_CIM == true)) {
$log = ini_get('error_log');
    $start = strpos($log, 'cim');
    if ($start === false) {
        $end = strrpos(ini_get('error_log'), "/");
        if ($end !== false) {
            $log_prefix = (IS_ADMIN_FLAG) ? '/cimDEBUG-adm-' : '/cimDEBUG-';
            $log_date = new DateTime();
            $debug_logfile_path = substr($log, 0, $end) . $log_prefix . $log_date->format('Ymd-His-u') . '.log';
            unset($log_prefix, $log_date);
            ini_set('error_log', $debug_logfile_path);
        }
    }
    }
    
    $oID = (int)$_GET['oID'];
    $payment_mode = $_GET['payment_mode'];
    $action = (isset($_GET['action']) ? $_GET['action'] : '');
    
    $cim = new cim_order($oID);
    
    // the following "if" clause actually inputs data into the DB
    if ($_GET['process'] == '1') {
        switch ($action) {
            /*
            // add a new payment entry
            case 'add':
                $update_status = (isset($_GET['update_status']) ? $_GET['update_status'] : false);
                $notify_customer = (isset($_GET['notify_customer']) ? $_GET['notify_customer'] : false);
                
                //update_status($oID, $new_status, $notified = 0, $comments = '')
                
                switch ($payment_mode) {

                    
                    case 'refund':
                        $new_index = $cim->add_refund($_GET['payment_id'], $_GET['refund_number'], $_GET['refund_name'],
                          $_GET['refund_amount'], $_GET['refund_type']);
                        
                        if ($update_status) {
                            update_status($oID, AUTO_STATUS_REFUND, $notify_customer,
                              sprintf(AUTO_COMMENTS_REFUND, $_GET['refund_number']));
                        }
                        
                        // notify the customer
                        if ($notify_customer) {
                            $_POST['notify_comments'] = 'on';
                            email_latest_status($oID);
                        }
                        
                        zen_redirect(zen_href_link(FILENAME_CIM_PAYMENTS,
                          'oID=' . $cim->oID . '&payment_mode=' . $payment_mode . '&index=' . $new_index . '&action=confirm',
                          'SSL'));
                        break;
                }  // END switch ($payment_mode)
                
                break;  // END case 'add' */
            case 'refund':
                $affected_rows = 0;
                switch ($payment_mode) {
                    case 'payment':
                        require_once DIR_FS_CATALOG_MODULES . 'payment/' . 'authorizenet_cim.php';
                        $cim_module = new authorizenet_cim();
                        $refund_amt = abs(round((float)($_GET['refund_amount']), 2));
                        
                        $_SESSION['refund_status'] = $cim_module->doCimRefund($oID, $refund_amt);
                        $affected_rows++;
                        
                        break;  // END case 'payment'
                }  // END switch ($payment_mode)
                
                zen_redirect(zen_href_link(FILENAME_CIM_PAYMENTS,
                  'oID=' . $oID . '&affected_rows=' . $affected_rows . '&action=refund_done', 'SSL'));
                break;  // END case 'delete'
            /*case 'deleteCard':
                require_once DIR_FS_CATALOG_MODULES . 'payment/' . 'authorizenet_cim.php';
                $cim_module = new authorizenet_cim();
              */
            
        }  // END switch ($action)
        
    }  // END if ($process)
    
    // the "else" handles displaying & gathering data to/from the user
    else {
        ?>
        <!doctype html public "-//W3C//DTD HTML 4.01 Transitional//EN">
        <html <?= HTML_PARAMS; ?>>
        <head>
            <meta http-equiv="Content-Type" content="text/html; charset=<?= CHARSET; ?>">
            <title><?= 'Admin:  ' . ucwords(str_replace('_', ' ', basename($_SERVER["SCRIPT_FILENAME"], '.php'))); ?></title>
            <link rel="stylesheet" type="text/css" href="includes/super_stylesheet.css">
            <link rel="stylesheet" type="text/css" href="includes/stylesheet.css">
            <link rel="stylesheet" type="text/css" href="includes/cssjsmenuhover.css" media="all" id="hoverJS">
            <script type="text/javascript" language="javascript" src="includes/general.js"></script>
            <script type="text/javascript" language="javascript" src="includes/formval.js"></script>
            <script language="JavaScript" type="text/javascript">
                <!--
                function returnParent() {
                    window.opener.location.reload(true);
                    window.opener.focus();
                    self.close();
                }

                // Only script specific to this form goes here.
                // General-purpose routines are in a separate file.
                function validateOnSubmit() {
                    var elem;
                    var errs = 0;
                    // execute all element validations in reverse order, so focus gets
                    // set to the first one in error.
                    if (!validateTelnr(document.forms.demo.telnr, 'inf_telnr', true)) errs += 1;
                    if (!validateAge(document.forms.demo.age, 'inf_age', false)) errs += 1;
                    if (!validateEmail(document.forms.demo.email, 'inf_email', true)) errs += 1;
                    if (!validatePresent(document.forms.demo.from, 'inf_from')) errs += 1;

                    if (errs > 1) alert('There are fields which need correction before sending');
                    if (errs == 1) alert('There is a field which needs correction before sending');

                    return (errs == 0);
                }

                //-->
            </script>
        </head>
        <body onload="self.focus()">
        <?php
        // display alerts/error messages, if any
        if ($messageStack->size > 0) {
            ?>
            <div class="messageStack-header noprint">
                <?php
                    echo $messageStack->output();
                ?>
            </div>
            <?php
        } ?>
        <table border="0" width="100%" cellspacing="0" cellpadding="0" align="center">
        <tr>
        <td align="center">
        <table border="0" cellspacing="0" cellpadding="2">
        <?php
        switch ($action) {
            /*
            case 'add':
                echo zen_draw_form('add', FILENAME_CIM_PAYMENTS, '', 'get', '', true);
                echo zen_draw_hidden_field('action', $action);
                echo zen_draw_hidden_field('process', 1);
                echo zen_draw_hidden_field('payment_mode', $payment_mode);
                echo zen_draw_hidden_field('oID', $cim->oID);
                
                switch ($payment_mode) {
                    case 'payment':
                        $po_array = $cim->build_po_array(TEXT_NONE);
                        ?>
                        <tr>
                            <td colspan="2" align="center" class="pageHeading"><?= HEADER_ENTER_PAYMENT; ?></td>
                        </tr>
                        <tr>
                            <td colspan="2" align="center" class="main">
                                <strong><?= HEADER_ORDER_ID . $cim->oID; ?></strong></td>
                        </tr>
                        <tr>
                            <td colspan="2" align="center"><?= zen_draw_separator('pixel_trans.gif', '1',
                                  '10'); ?></td>
                        </tr>
                        <tr>
                            <td class="main" align="right"><?= TEXT_PAYMENT_NUMBER; ?></td>
                            <td class="main"><?= zen_draw_input_field('payment_number', '', 'size="25"'); ?></td>
                        </tr>
                        <tr>
                            <td class="main" align="right"><?= TEXT_PAYMENT_NAME; ?></td>
                            <td class="main"><?= zen_draw_input_field('payment_name', '', 'size="25"'); ?></td>
                        </tr>
                        <tr>
                            <td class="main" align="right"><?= TEXT_PAYMENT_AMOUNT; ?></td>
                            <td class="main"><?= zen_draw_input_field('payment_amount', '', 'size="8"'); ?></td>
                        </tr>
                        <tr>
                            <td class="main" align="right"><?= TEXT_PAYMENT_TYPE; ?></td>
                            <td class="main"><?= zen_draw_pull_down_menu('payment_type', $cim->payment_key, '',
                                  ''); ?></td>
                        </tr>
                        <tr>
                            <td class="main" align="right"><?= TEXT_ATTACHED_PO; ?></td>
                            <td class="main"><?= zen_draw_pull_down_menu('purchase_order_id', $po_array, '',
                                  ''); ?></td>
                        </tr>
                        <?php
                        break;
                    
                    case 'refund':
                        $payment_array = $cim->build_payment_array(TEXT_NONE);
                        ?>
                        <tr>
                            <td colspan="2" align="center" class="pageHeading"><?= HEADER_ENTER_REFUND; ?></td>
                        </tr>
                        <tr>
                            <td colspan="2" align="center" class="main">
                                <strong><?= HEADER_ORDER_ID . $cim->oID; ?></strong></td>
                        </tr>
                        <tr>
                            <td colspan="2" align="center"><?= zen_draw_separator('pixel_trans.gif', '1',
                                  '10'); ?></td>
                        </tr>
                        <tr>
                            <td class="main" align="right"><?= TEXT_ATTACHED_PAYMENT; ?></td>
                            <td class="main"><?= zen_draw_pull_down_menu('payment_id', $payment_array, '',
                                  ''); ?></td>
                        </tr>
                        <tr>
                            <td class="main" align="right"><?= TEXT_REFUND_NUMBER; ?></td>
                            <td class="main"><?= zen_draw_input_field('refund_number', '', 'size="25"'); ?></td>
                        </tr>
                        <tr>
                            <td class="main" align="right"><?= TEXT_REFUND_NAME; ?></td>
                            <td class="main"><?= zen_draw_input_field('refund_name', '', 'size="25"'); ?></td>
                        </tr>
                        <tr>
                            <td class="main" align="right"><?= TEXT_REFUND_AMOUNT; ?></td>
                            <td class="main"><?= zen_draw_input_field('refund_amount', '',
                                    'size="8"') . '<span class="alert">' . TEXT_NO_MINUS . '</span>'; ?></td>
                        </tr>
                        <tr>
                            <td class="main" align="right"><?= TEXT_REFUND_TYPE; ?></td>
                            <td class="main"><?= zen_draw_pull_down_menu('refund_type', $cim->payment_key, '',
                                  ''); ?></td>
                        </tr>
                        <?php
                        break;
                }  // END switch ($payment_mode)
                ?>
                </table></td>
                <tr>
                    <td colspan="2" align="center"><?= zen_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
                </tr>
                <tr>
                    <td align="center" colspan="2">
                        <table border="0" cellspacing="0" cellpadding="0">
                            <tr>
                                <td class="main"><?= zen_draw_checkbox_field('update_status', 1,
                                        false) . CHECKBOX_UPDATE_STATUS; ?></td>
                            </tr>
                            <tr>
                                <td class="main"><?= zen_draw_checkbox_field('notify_customer', 1,
                                        false) . CHECKBOX_NOTIFY_CUSTOMER; ?></td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td colspan="2" align="center"><?= zen_draw_separator('pixel_trans.gif', '1', '15'); ?></td>
                </tr>
                <tr>
                    <td class="main" colspan="2" align="center">
                        <INPUT TYPE="BUTTON" VALUE="<?= BUTTON_CANCEL; ?>" onclick="returnParent()">
                        <INPUT TYPE="SUBMIT" VALUE="<?= BUTTON_SUBMIT; ?>"
                               onclick="document.add.submit();this.disabled=true">
                    </td>
                </tr>
                </form>
                </table></td>
                <?php
                break;  // END case 'add'
                */
            case 'refund':
                $index = $_GET['index'];
                echo zen_draw_form('delete', FILENAME_CIM_PAYMENTS, '', 'get', '', true);
                echo zen_draw_hidden_field('action', $action);
                echo zen_draw_hidden_field('process', 1);
                echo zen_draw_hidden_field('payment_mode', $payment_mode);
                echo zen_draw_hidden_field('oID', $oID);
                switch ($payment_mode) {
                    case 'payment':
                        echo zen_draw_hidden_field('payment_id', $index);
                        // check for attached refunds
                        $refund_exists = false;
                        $refund_count = 0;
                        if (is_array($cim->refund)) {
                            for ($a = 0; $a < sizeof($cim->refund); $a++) {
                                if ($cim->refund[$a]['payment'] == $index) {
                                    $refund_exists = true;
                                    $refund_count++;
                                }
                            }
                        }
                        ?>
                        <tr>
                            <td colspan="2" align="center" class="pageHeading"><?= HEADER_DELETE_PAYMENT; ?></td>
                        </tr>
                        <tr>
                            <td colspan="2" align="center" class="main">
                                <strong><?= HEADER_ORDER_ID . $cim->oID . '<br />' . HEADER_PAYMENT_UID . $index; ?></strong>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2" align="center" class="main"><?= DELETE_PAYMENT_NOTE; ?></td>
                        </tr>
                        <tr>
                            <td colspan="2" align="center"><?= zen_draw_separator('pixel_trans.gif', '1',
                                  '10'); ?></td>
                        </tr>
                        <tr>
                            <td class="main" align="right"><?= TEXT_REFUND_AMOUNT; ?></td>
                            <td class="main"><?= zen_draw_input_field('refund_amount',
                                    $cim->refund[$x]['amount'],
                                    'size="8"') . '<span class="alert">' . TEXT_NO_MINUS . '</span>'; ?></td>
                        </tr>
                        <?php

                        ?>
                        <tr>
                            <td colspan="2" align="center"><?= zen_draw_separator('pixel_trans.gif', '1',
                                  '10'); ?></td>
                        </tr>
                        <tr class="alert alert-danger">
                        <td colspan="2" align="center" class="warningText"><?= WARN_DELETE_PAYMENT; ?>
                        <?php
                        break;
                    case 'refund':
                        echo zen_draw_hidden_field('refund_id', $index);
                        ?>
                        <tr>
                            <td colspan="2" align="center" class="pageHeading"><?= HEADER_DELETE_REFUND; ?></td>
                        </tr>
                        <tr>
                            <td align="center" colspan="2" class="main">
                                <strong><?= HEADER_ORDER_ID . $oID . '<br />' . HEADER_REFUND_UID . $index; ?></strong>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2" align="center"><?= zen_draw_separator('pixel_trans.gif', '1',
                                  '10'); ?></td>
                        </tr>
                        <tr class="alert alert-danger">
                        <td colspan="2" align="center" class="warningText"><?= WARN_DELETE_REFUND; ?>
                        <?php
                        break;
                }  // END switch ($payment_mode)
                ?>
                <p><input type="button" class="btn btn-info" value="<?= BUTTON_CANCEL; ?>"
                          onclick="returnParent()">
                    <input type="submit" class="btn btn-warning" value="<?= BUTTON_SUBMIT; ?>"
                           onclick="document.delete.submit();this.disabled=true"></td>
                </tr>
                </form>
                </table></td>
                <?php
                break;  // END case
            case 'refund_done':
                $affected_rows = $_GET['affected_rows'];
                if (!$_SESSION['refund_status']) {
                    $page_header = HEADER_REFUND_FAIL;
                    $affected_rows = 0;
                } else {
                    $page_header = HEADER_REFUND_DONE;
                }
                ?>
                <tr>
                    <td colspan="2" align="center" class="pageHeading"><?= $page_header; ?></td>
                </tr>
                <tr>
                    <td colspan="2" align="center"><?= zen_draw_separator('pixel_trans.gif', '1', '15'); ?></td>
                </tr>
                <tr>
                    <td colspan="2" align="center" class="main"><?= sprintf(TEXT_DELETE_CONFIRM,
                          $affected_rows); ?></td>
                </tr>
                <tr>
                    <td colspan="2" align="center"><?= zen_draw_separator('pixel_trans.gif', '1', '15'); ?></td>
                </tr>
                <tr>
                    <td class="main" colspan="2" align="center"><input type="button" class="btn btn-success"
                                                                       value="<?= BUTTON_DELETE_CONFIRM; ?>"
                                                                       onclick="returnParent()"></td>
                </tr>
                <?php
                break;  // END case
                /*
            case 'confirm':
                $index = $_GET['index'];
                switch ($payment_mode) {
                    case 'payment':
                        $payment_info = $db->Execute("select p.*, po.po_number
                                          from " . TABLE_CIM_PAYMENTS . " p
                                          left join " . TABLE_CIM_PURCHASE_ORDERS . " po
                                          on p.purchase_order_id = po.purchase_order_id
                                          where p.payment_id = '" . $index . "' limit 1");
                        ?>
                        <tr>
                            <td colspan="2" align="center"
                                class="pageHeading"><?= HEADER_CONFIRM_PAYMENT; ?></td>
                        </tr>
                        <tr>
                            <td align="left" class="main"><strong><?= HEADER_ORDER_ID . $cim->oID; ?></strong>
                            </td>
                            <td align="right" class="main"><strong><?= HEADER_PAYMENT_UID . $index; ?></strong>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2" align="center"><?= zen_draw_separator('pixel_trans.gif', '1',
                                  '10'); ?></td>
                        </tr>
                        <tr>
                            <td class="main" width="50%" align="right"><?= TEXT_PAYMENT_NUMBER; ?></td>
                            <td class="main" width="50%" align="left">
                                <strong><?= $payment_info->fields['payment_number']; ?></strong></td>
                        </tr>
                        <tr>
                            <td class="main" width="50%" align="right"><?= TEXT_PAYMENT_NAME; ?></td>
                            <td class="main" width="50%" align="left">
                                <strong><?= $payment_info->fields['payment_name']; ?></strong></td>
                        </tr>
                        <tr>
                            <td class="main" width="50%" align="right"><?= TEXT_PAYMENT_AMOUNT; ?></td>
                            <td class="main" width="50%" align="left">
                                <strong><?= $payment_info->fields['payment_amount']; ?></strong></td>
                        </tr>
                        <tr>
                            <td class="main" width="50%" align="right"><?= TEXT_PAYMENT_TYPE; ?></td>
                            <td class="main" width="50%" align="left">
                                <strong><?= $cim->full_type($payment_info->fields['payment_type']); ?></strong>
                            </td>
                        </tr>
                        <tr>
                            <td class="main" width="50%" align="right"><?= TEXT_ATTACHED_PO; ?></td>
                            <td class="main" width="50%" align="left">
                                <strong><?=($payment_info->fields['purchase_order_id'] == 0 ? TEXT_NONE : $payment_info->fields['po_number']); ?></strong>
                            </td>
                        </tr>
                        <?php
                        break;
                    case 'refund':
                        $refund = $db->Execute("select r.*, p.payment_number
                                    from " . TABLE_CIM_REFUNDS . " r
                                    left join " . TABLE_CIM_PAYMENTS . " p on p.payment_id = r.payment_id
                                    where refund_id = '" . $index . "' limit 1");
                        ?>
                        <tr>
                            <td colspan="2" align="center" class="pageHeading"><?= HEADER_CONFIRM_REFUND; ?></td>
                        </tr>
                        <tr>
                            <td align="left" class="main"><strong><?= HEADER_ORDER_ID . $cim->oID; ?></strong>
                            </td>
                            <td align="right" class="main"><strong><?= HEADER_REFUND_UID . $index; ?></strong>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2" align="center"><?= zen_draw_separator('pixel_trans.gif', '1',
                                  '10'); ?></td>
                        </tr>
                        <tr>
                            <td class="main" width="50%" align="right"><?= TEXT_ATTACHED_PAYMENT; ?></td>
                            <td class="main" width="50%">
                                <strong><?= $refund->fields['payment_number']; ?></strong></td>
                        </tr>
                        <tr>
                            <td class="main" width="50%" align="right"><?= TEXT_REFUND_NUMBER; ?></td>
                            <td class="main" width="50%">
                                <strong><?= $refund->fields['transaction_id']; ?></strong></td>
                        </tr>
                        <tr>
                            <td class="main" width="50%" align="right"><?= TEXT_REFUND_NAME; ?></td>
                            <td class="main" width="50%"><strong><?= $refund->fields['refund_name']; ?></strong>
                            </td>
                        </tr>
                        <tr>
                            <td class="main" width="50%" align="right"><?= TEXT_REFUND_AMOUNT; ?></td>
                            <td class="main" width="50%">
                                <strong><?= $refund->fields['refund_amount']; ?></strong></td>
                        </tr>
                        <tr>
                            <td class="main" width="50%" align="right"><?= TEXT_REFUND_TYPE; ?></td>
                            <td class="main" width="50%">
                                <strong><?= $cim->full_type($refund->fields['refund_type']); ?></strong></td>
                        </tr>
                        <?php
                        break;
                }  // END switch ($payment_mode)
                ?>
                <tr>
                    <td colspan="2" align="center"><?= zen_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
                </tr>
                <tr>
                <td colspan="2">
                    <table border="0" cellspacing="2" cellpadding="2">
                        <tr>
                            <td class="main"><input type="button" value="<?= BUTTON_SAVE_CLOSE; ?>"
                                                    onclick="this.disabled=true; returnParent();"></td>
                            <td class="main"><?= '<input type="button" value="' . BUTTON_MODIFY . '" onclick="this.disabled=true; window.location.href=\'' . zen_href_link(FILENAME_CIM_PAYMENTS,
                                    'oID=' . $cim->oID . '&payment_mode=' . $payment_mode . '&index=' . $index . '&action=my_update',
                                    'SSL') . '\'">'; ?></td>
                            <td class="main"><?= '<input type="button" value="' . BUTTON_ADD_NEW . '" onclick="this.disabled=true; window.location.href=\'' . zen_href_link(FILENAME_CIM_PAYMENTS,
                                    'oID=' . $cim->oID . '&payment_mode=' . $payment_mode . '&action=add',
                                    'SSL') . '\'">'; ?></td>
                        </tr>
                        </tr>
                    </table>
                </td>
                <?php
                break;  // END case
                */
            case 'clearCards':
                ?>
                <div class="alert alert-danger">Are you sure you want to delete all the stored credit cards for:<br/>

                <h2><?= zen_customers_name($_GET['cID']) . '?'; ?></h2>
                <a href=<?= zen_href_link('cim_payments',
              'cID=' . (int)$_GET['cID'] . '&action=clearCards_confirm',
              'SSL') . ' class="btn btn-danger" role="button" id="cards_btn" class="btn btn-danger btn-margin">Delete Credit Cards</a></div>';
                break;
            case 'clearCards_confirm':
                if (!defined('FILENAME_CIM_PAYMENTS')) {
                    require_once DIR_FS_CATALOG . DIR_WS_LANGUAGES . $_SESSION['language'] . '/modules/payment/authorizenet_cim.php';
                }
                require_once DIR_FS_CATALOG_MODULES . 'payment/' . 'authorizenet_cim.php';
                $cim_module = new authorizenet_cim();
        
                $profileId = $cim_module->getCustomerProfile((int)$_GET['cID']);
                if ($profileId) {
                    $cim_module->deleteStoredData($_GET['cID'], $profileId);
                }
                ?>
                <div class="alert alert-info">All cards were deleted for:<br/>

                <h2><?= zen_customers_name($_GET['cID']); ?></h2>
                 Any errors will have been logged!<br/>
<button class="btn btn-info" onclick="window.close()">Discard</button>
</div>
<?php
                break;
            
        }  // END switch ($action)
        
    }  // END else
?>
    <!-- body_text_eof //-->
</tr>
    </table>
    <!-- body_eof //-->
</body>
    </html>
<?php require(DIR_WS_INCLUDES . 'application_bottom.php');
