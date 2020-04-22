<?php
/*
//  SUPER ORDERS v3.0                                               	//
//  Based on Super Order 2.0                                        	//
//  By Frank Koehl - PM: BlindSide (original author)                	//
//  Super Orders 3 Updated by:
//  ~ JT of GTICustom
//  ~ C Jones Over the Hill Web Consulting (http://overthehillweb.com)	//
//  ~ Loose Chicken Software Development, david@loosechicken.com	//
//                                                      		//
//                                                     			//
//  Released under the GNU General Public License       		//
//  available at www.zen-cart.com/license/2_0.txt       		//
//  or see "license.txt" in the downloaded zip          		//
//////////////////////////////////////////////////////////////////////////
//  DESCRIPTION:   This file generates a pop-up window that is used to 	//
//	enter and edit payment information for a given order.		//
//////////////////////////////////////////////////////////////////////////
*/

require 'includes/application_top.php';
require_once DIR_WS_CLASSES . 'authnet_order.php';
require_once DIR_FS_CATALOG . DIR_WS_LANGUAGES . $_SESSION['language'] . '/modules/payment/authorizenet_cim.php';
require_once DIR_FS_CATALOG_MODULES . 'payment/' . 'authorizenet_cim.php';
$authnet_cim = new authorizenet_cim();

$authnet_cim->checkLogName();

$oID = (int)$_GET['oID'];
$payment_mode = $_GET['payment_mode'];
$action = (isset($_GET['action']) ? $_GET['action'] : '');

$cim = new authnet_order($oID);

// the following "if" clause actually inputs data into the DB
if ($_GET['process'] == '1') {
    switch ($action) {
        case 'refund':
            $affected_rows = 0;
            switch ($payment_mode) {
                case 'payment':
                    $refund_amt = abs(round((float)($_GET['refund_amount']), 2));
                    $_SESSION['refund_status'] = $authnet_cim->doCimRefund($oID, $refund_amt);
                    $affected_rows++;
                    break;  
            }  
            zen_redirect(zen_href_link(FILENAME_AUTHNET_PAYMENTS,
                'oID=' . $oID . '&affected_rows=' . $affected_rows . '&action=refund_done', 'SSL'));
            break;
        default:
            throw new \Exception('Unexpected value');  // END case 'delete'
    }  // END switch ($action)
} else {
    // the "else" handles displaying & gathering data to/from the user
    ?>
    <!doctype html public "-//W3C//DTD HTML 4.01 Transitional//EN">
    <html <?= HTML_PARAMS; ?>>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=<?= CHARSET; ?>">
        <title><?= 'Admin:  ' . ucwords(str_replace('_', ' ',
                basename($_SERVER["SCRIPT_FILENAME"], '.php'))); ?></title>
        <link rel="stylesheet" type="text/css" href="includes/stylesheet.css">
        <link rel="stylesheet" type="text/css" href="includes/cssjsmenuhover.css" media="all" id="hoverJS">
        <script type="text/javascript" language="javascript" src="includes/general.js"></script>
        <script language="JavaScript" type="text/javascript">
            <!--
            function returnParent() {
                window.opener.location.reload(true);
                window.opener.focus();
                self.close();
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
            <?= $messageStack->output(); ?>
        </div>
        <?php
    }
    ?>
    <table border="0" width="100%" cellspacing="0" cellpadding="0" align="center">
    <tr>
    <td align="center">
    <?php
    switch ($action) {
        case 'refund':
            $index = $_GET['index'];
            echo zen_draw_form('delete', FILENAME_AUTHNET_PAYMENTS, '', 'get', '', true);
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
                    <table border="0" cellspacing="0" cellpadding="2">
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
                        <td colspan="2" align="center"><?= zen_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
                    </tr>
                    <tr>
                        <td class="main" align="right"><?= TEXT_REFUND_AMOUNT; ?></td>
                        <td class="main"><?= zen_draw_input_field('refund_amount', '',
                                'size="8"') . '<span class="alert">' . TEXT_NO_MINUS . '</span>'; ?></td>
                    </tr>
                    <tr>
                        <td colspan="2" align="center"><?= zen_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
                    </tr>
                    <tr class="alert alert-danger">
                    <td colspan="2" align="center" class="warningText"><?= WARN_DELETE_PAYMENT; ?>
                    <?php
                    break;
                    /*
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
                        <td colspan="2" align="center"><?= zen_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
                    </tr>
                    <tr class="alert alert-danger">
                    <td colspan="2" align="center" class="warningText"><?= WARN_DELETE_REFUND; ?>
                    <?php
                    break;
                    */
            }  
            ?>
            <p><input type="button" class="btn btn-info" value="<?= BUTTON_CANCEL; ?>"
                      onclick="returnParent()">
                <input type="submit" class="btn btn-warning" value="<?= BUTTON_SUBMIT; ?>"
                       onclick="document.delete.submit();this.disabled=true"></td>
            </tr>
            </table>
            </form>

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
                <td colspan="2" align="center" class="main"><?= sprintf(TEXT_DELETE_CONFIRM, $affected_rows); ?></td>
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
        case 'clearCards':
            ?>
            <div class="alert alert-danger">Are you sure you want to delete all the stored credit cards for:<br/>
                <h2><?= zen_customers_name($_GET['cID']) . '?'; ?></h2>
                <a href=<?= zen_href_link(FILENAME_AUTHNET_PAYMENTS,
                    'cID=' . (int)$_GET['cID'] . '&action=clearCards_confirm', 'SSL'); ?>   class="btn btn-danger"
                role="button" id="cards_btn" class="btn btn-danger
                btn-margin">Delete Credit Cards</a>
            </div>
            <?php
            break;
        case 'clearCards_confirm':
            $profileId = $authnet_cim->getCustomerProfile((int)$_GET['cID']);
            if ($profileId) {
                $authnet_cim->deleteStoredData($_GET['cID'], $profileId);
            }
            ?>
            <div class="alert alert-info">All cards were deleted for:<br/>

                <h2><?= zen_customers_name($_GET['cID']); ?></h2>
                Any errors will have been logged!<br/>
                <button class="btn btn-info" onclick="window.close()">Discard</button>
            </div>
            <?php
            break;

    }  

}// END else


?>
    <!-- body_text_eof //-->
    </td>
    </tr>
</table>
    <!-- body_eof //-->
</body>
    </html>
<?php require 'includes/application_bottom.php';