<?php
/*  portions copyright by... zen-cart.com

    developed and brought to you by proseLA
    https://rossroberts.com

    released under GPU
    https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0

   04/2020  project: authorizenet_cim; file: authorizenet_payments.php; version 2.0
*/

require 'includes/application_top.php';
require_once DIR_WS_CLASSES . 'authnet_order.php';
require_once DIR_FS_CATALOG . DIR_WS_LANGUAGES . $_SESSION['language'] . '/modules/payment/authorizenet_cim.php';
require_once DIR_FS_CATALOG_MODULES . 'payment/' . 'authorizenet_cim.php';
$authnet_cim = new authorizenet_cim();

$authnet_cim->checkLogName();

$oID = (int)$_GET['oID'];
$action = (isset($_GET['action']) ? $_GET['action'] : '');

$authnet_order = new authnet_order($oID);

if ($_GET['process'] == '1') {
    switch ($action) {
        case 'refund':
            $affected_rows = 0;
            $refund_amt = abs(round((float)($_GET['refund_amount']), 2));
            $_SESSION['refund_status'] = $authnet_cim->doCimRefund($oID, $refund_amt);
            $affected_rows++;
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
        <link rel="stylesheet" type="text/css" href="includes/css/authnet.css"
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
    <div class="alert ">
    <?php
    switch ($action) {
        case 'refund':
            //$index = $_GET['index'];
            $index = $authnet_order->payment[0]['index'];
            $details = $authnet_cim->getTransactionDetails($authnet_order->payment[0]['number']);

            $voidPayment = false;
            if (strpos($details->getTransaction()->getTransactionStatus(), 'Pending') !== false) {
                $voidPayment = true;
            }

            echo zen_draw_form('delete', FILENAME_AUTHNET_PAYMENTS, '', 'get', '', true);
            echo zen_draw_hidden_field('action', $action);
            echo zen_draw_hidden_field('process', 1);
            echo zen_draw_hidden_field('oID', $oID);
            echo zen_draw_hidden_field('payment_id', $index);
            ?>
            <table class="table table-condensed table-borderless">
                <tr>
                    <td align="center"
                        class="pageHeading"><?= $voidPayment ? HEADER_VOID_PAYMENT : HEADER_DELETE_PAYMENT; ?></td>
                </tr>
                <tr>
                    <td align="center" class="main">
                        <strong><?= HEADER_ORDER_ID . $authnet_order->oID . '<br />' . HEADER_PAYMENT_UID . $index; ?></strong>
                    </td>
                </tr>
                <?php
                if ($voidPayment) :
                    ?>
                    <tr>
                        <td align="center" class="main"><?= DELETE_VOID_NOTE; ?></td>
                    </tr>
                <?php
                else:
                    ?>
                    <tr>
                        <td align="center" class="main"><?= DELETE_PAYMENT_NOTE; ?></td>
                    </tr>
                    <tr>
                        <td align="center"><?= zen_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
                    </tr>
                    <tr>
                        <td align="center"><?= TEXT_REFUND_AMOUNT . '  ' . zen_draw_input_field('refund_amount', '',
                                'size="8"') . '<span class="alert">' . TEXT_NO_MINUS . '</span>'; ?></td>
                    </tr>
                <?php
                endif;
                ?>
                <tr>
                    <td align="center"><?= zen_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
                </tr>
                <tr class="alert alert-danger">
                    <td align="center" class="warningText"><?= WARN_DELETE_PAYMENT; ?>
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
            <div class="alert alert-info">

                <h2><?= $page_header; ?></h2>
                <?= sprintf(TEXT_DELETE_CONFIRM, $affected_rows); ?>
                <input type="button" class="btn btn-success"
                       value="<?= BUTTON_DELETE_CONFIRM; ?>"
                       onclick="returnParent()"></td>
            </div>
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
</div>
</body>
    </html>
<?php require 'includes/application_bottom.php';