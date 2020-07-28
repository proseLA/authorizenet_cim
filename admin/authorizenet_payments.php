<?php
	/*  portions copyright by... zen-cart.com

		developed and brought to you by proseLA
		https://rossroberts.com

		released under GPU
		https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0

	   04/2020  project: authorizenet_cim; file: authorizenet_payments.php; version 2.2.1
	*/

	require 'includes/application_top.php';
	require_once DIR_WS_CLASSES . 'authnet_order.php';
	require_once DIR_FS_CATALOG . DIR_WS_LANGUAGES . $_SESSION['language'] . '/modules/payment/authorizenet_cof.php';
	require_once DIR_FS_CATALOG_MODULES . 'payment/' . 'authorizenet_cof.php';
	require_once DIR_WS_CLASSES . 'currencies.php';
	$currencies = new currencies();

	$authnet_cim = new authorizenet_cof();

	$authnet_cim->checkLogName();

	$action_array_index_not_necessary = [
		'refund_capture_done',
		'more_money_done',
		'more_money',
		'clearCards',
		'clearCards_confirm',
	];

	$oID = isset($_GET['oID']) ? (int)$_GET['oID'] : (int)$_POST['oID'];
	$action = (isset($_GET['action']) ? $_GET['action'] : $_POST['action']);

	$index_necessary = true;

	if (in_array($action, $action_array_index_not_necessary)) {
		$index_necessary = false;
	}

	$authnet_order = new authnet_order($oID);

	$cim_payment_index = (isset($_POST['payment_id']) ? $_POST['payment_id'] : $_GET['index']);

	if (is_null($cim_payment_index)) {
		$index = null;
	} else {

// the following takes the index/key/payments_id from CIM PAYMENTS table, and returns the array index for the payment; ie it gives you the index for $authnet_order->payment[$index]
		$index = $authnet_order->getPaymentIndex($cim_payment_index);
	}


	if (!isset($index) && $index_necessary) {
		trigger_error('Payment index not part of this order! Order: ' . $oID . ' Payment Index: ' . $cim_payment_index);
		$_SESSION['payment_index_error'] = true;
		$messageStack->add_session(PAYMENT_INDEX_ERROR, 'error');
		zen_redirect(zen_href_link(FILENAME_AUTHNET_PAYMENTS, 'oID=' . $oID . '&action=more_money_done', 'SSL'));
	}

	if (isset($_POST['oID'])) {
		$post_amount = abs($authnet_order->num_2_dec($_POST['amount']));
		switch ($action) {
			case 'refund':
				$refund_amt = $post_amount;
				$_SESSION['refund_status'] = $authnet_cim->doCimRefund($oID, $refund_amt, $cim_payment_index);
				zen_redirect(zen_href_link(FILENAME_AUTHNET_PAYMENTS,
					'oID=' . $oID . '&action=refund_capture_done', 'SSL'));
				break;
			case 'capture':
				if ($_POST['payment_id'] != $authnet_order->payment[$index]['index']) {
					$_SESSION['capture_error_status'] = true;
					$messageStack->add_session(CAPTURE_NOT_MATCH, 'error');
					zen_redirect(zen_href_link(FILENAME_AUTHNET_PAYMENTS, 'oID=' . $oID . '&action=refund_capture_done',
						'SSL'));
				}
				$amount = ($post_amount == 0) ? $amount = $authnet_order->payment[$index]['amount'] : $post_amount;
				$_SESSION['capture_error_status'] = $authnet_cim->capturePreviouslyAuthorizedAmount($authnet_order->payment[$index]['number'],
					$amount);
				zen_redirect(zen_href_link(FILENAME_AUTHNET_PAYMENTS, 'oID=' . $oID . '&action=refund_capture_done',
					'SSL'));
				break;
			case 'more_money':
				if ($authnet_order->balance_due == 0) {
					$_SESSION['more_money_error'] = true;
					$messageStack->add_session(MORE_MONEY_ERROR, 'error');
					zen_redirect(zen_href_link(FILENAME_AUTHNET_PAYMENTS, 'oID=' . $oID . '&action=more_money_done',
						'SSL'));
				}
				if ($post_amount == 0) {
					$_POST['amount'] = $authnet_order->num_2_dec($authnet_order->balance_due);
				}
				if ($_POST['amount'] < 0) {
					//should never get here; will result in error from gateway
					$_POST = 0;
				}
				$customer_profile = $authnet_cim->getCustomerProfile($authnet_order->cID);
				$last_index = sizeof($authnet_order->payment) - 1;

				$profile_to_charge = $authnet_order->payment[$last_index]['payment_profile_id'];

				if (isset($_POST['ccIndex']) and !$_POST['ccIndex'] == 0) {
					$profile = $authnet_cim->getCustomerPaymentProfile($authnet_order->cID, '', $_POST['ccIndex']);
				}

				if ($profile['profile'] != false) {
					$profile_to_charge = $profile['profile'];
				}

				$new_charge_error = $authnet_cim->adminCharge($customer_profile, $profile_to_charge, true);

				if ($new_charge_error) {
					$_SESSION['more_money_error'] = true;
				}
				zen_redirect(zen_href_link(FILENAME_AUTHNET_PAYMENTS, 'oID=' . $oID . '&action=more_money_done',
					'SSL'));
				break;

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
		$alert_class = "alert-info";
		switch ($action) {
			case 'refund':
				$voidPayment = false;
				$status = $authnet_cim->getTransactionDetails($authnet_order->payment[$index]['number'])->getTransaction()->getTransactionStatus();
				if (strpos($status, 'Pending') !== false) {
					$voidPayment = true;
				}

				echo zen_draw_form('delete', FILENAME_AUTHNET_PAYMENTS, '', 'post', '', true);
				echo zen_draw_hidden_field('action', $action);
				echo zen_draw_hidden_field('oID', $oID);
				echo zen_draw_hidden_field('payment_id', $authnet_order->payment[$index]['index']);
				?>
                <table class="table table-condensed table-borderless">
                    <tr>
                        <td align="center"
                            class="pageHeading"><?= $voidPayment ? HEADER_VOID_PAYMENT : HEADER_DELETE_PAYMENT; ?></td>
                    </tr>
                    <tr>
                        <td align="center" class="main">
                            <strong><?= HEADER_ORDER_ID . $authnet_order->oID . '<br />' . HEADER_PAYMENT_UID . $cim_payment_index; ?></strong>
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
                                <td align="center"><?= TEXT_AMOUNT . '  ' . zen_draw_input_field('amount', '',
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
			case 'capture':
				$captureFunds = false;
				$status = $authnet_cim->getTransactionDetails($authnet_order->payment[$index]['number'])->getTransaction()->getTransactionStatus();
				if (strpos($status, 'authorizedPendingCapture') !== false) {
					$captureFunds = true;
				} else {
					$_SESSION['capture_error_status'] = true;
					$messageStack->add_session(CAPTURE_BAD_STATUS, 'error');
					zen_redirect(zen_href_link(FILENAME_AUTHNET_PAYMENTS, 'oID=' . $oID . '&action=refund_capture_done',
						'SSL'));
				}

				echo zen_draw_form('capture', FILENAME_AUTHNET_PAYMENTS, '', 'post', '', true);
				echo zen_draw_hidden_field('action', $action);
				echo zen_draw_hidden_field('oID', $oID);
				echo zen_draw_hidden_field('payment_id', $authnet_order->payment[$index]['index']);
				?>
                <table class="table table-condensed table-borderless">
                    <tr>
                        <td align="center"
                            class="pageHeading"><?= HEADER_CAPTURE_FUNDS; ?></td>
                    </tr>
                    <tr>
                        <td align="center" class="main">
                            <strong><?= HEADER_ORDER_ID . $authnet_order->oID . '<br />' . HEADER_PAYMENT_UID . $cim_payment_index; ?></strong>
                        </td>
                    </tr>

                    <tr>
                        <td align="center" class="main"><?= CAPTURE_NOTE; ?>
                            <br/>
                            <span class="pageHeading  "><?= $currencies->format($authnet_order->payment[$index]['amount']); ?></span>
                        </td>
                    </tr>
                    <tr>
                        <td align="center"><?= zen_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
                    </tr>
                    <tr>
                        <td align="center"><?= TEXT_AMOUNT . '  ' . zen_draw_input_field('amount', '',
								'size="8"'); ?></td>
                    </tr>
                    <tr>
                        <td align="center"><?= zen_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
                    </tr>
                    <tr class="alert alert-danger">
                        <td align="center" class="alert-info"><?= WARN_CAPTURE_PAYMENT; ?>
                            <p><input type="button" class="btn btn-info" value="<?= BUTTON_CANCEL; ?>"
                                      onclick="returnParent()">
                                <input type="submit" class="btn btn-warning" value="<?= BUTTON_SUBMIT; ?>"
                                       onclick="document.delete.submit();this.disabled=true"></td>
                    </tr>
                </table>
                </form>
				<?php
				break;  // END case
			case 'refund_capture_done':
				if (!$_SESSION['refund_status']) {
					$page_header = HEADER_REFUND_FAIL;
					$alert_class = "alert-warning";
				} else {
					$page_header = HEADER_REFUND_DONE;
				}
				unset($_SESSION['refund_status']);
				if (isset($_SESSION['capture_error_status'])) {
					if ($_SESSION['capture_error_status']) {
						$page_header = HEADER_CAPTURE_FAIL;
						$alert_class = "alert-warning";
					} else {
						$page_header = HEADER_CAPTURE_DONE;
						$alert_class = "alert-info";
					}
					unset($_SESSION['capture_error_status']);
				}
				?>
                <div class="alert <?= $alert_class; ?>">
                    <h2><?= $page_header; ?></h2>
                    <input type="button" class="btn btn-success "
                           value="<?= BUTTON_DELETE_CONFIRM; ?>"
                           onclick="returnParent()"></td>
                </div>
				<?php
				break;  // END case
			case 'more_money_done':
				if (isset($_SESSION['more_money_error']) && $_SESSION['more_money_error']) {
					$page_header = HEADER_MORE_MONEY_FAIL;
					$alert_class = "alert-warning";
				} else {
					$page_header = HEADER_MORE_MONEY_DONE;
				}

				if (isset($_SESSION['payment_index_error']) && $_SESSION['payment_index_error']) {
					$page_header = HEADER_PAYMENT_INDEX_ERROR;
					$alert_class = "alert-warning";
				}
				unset($_SESSION['payment_index_error']);
				unset($_SESSION['more_money_error']);

				?>
                <div class="alert <?= $alert_class; ?>">
                    <h2><?= $page_header; ?></h2>
                    <input type="button" class="btn btn-success "
                           value="<?= BUTTON_DELETE_CONFIRM; ?>"
                           onclick="returnParent()"></td>
                </div>
				<?php
				break;  // END case
			case 'clearCards':
				?>
                <div class="alert alert-danger"><?= DELETE_CARDS; ?><br/>
                    <h2><?= zen_customers_name($_GET['cID']) . '?'; ?></h2>
                    <a href=<?= zen_href_link(FILENAME_AUTHNET_PAYMENTS,
						'cID=' . (int)$_GET['cID'] . '&action=clearCards_confirm', 'SSL'); ?>   class="btn btn-danger"
                    role="button" id="cards_btn" class="btn btn-danger
                    btn-margin"><?= BUTTON_DELETE_CARDS; ?></a>
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
			case 'more_money':

				echo zen_draw_form('delete', FILENAME_AUTHNET_PAYMENTS, '', 'post', '', true);
				echo zen_draw_hidden_field('action', $action);
				echo zen_draw_hidden_field('oID', $oID);
				echo zen_draw_hidden_field('ccIndex', $_GET['ccindex']);
				//echo zen_draw_hidden_field('payment_id', $index);
				?>
                <table class="table table-condensed table-borderless">
                    <tr>
                        <td align="center"
                            class="pageHeading"><?= HEADER_MORE_MONEY; ?></td>
                    </tr>
                    <tr>
                        <td align="center" class="main">
                            <strong><?= HEADER_ORDER_ID . $authnet_order->oID; ?></strong>
                        </td>
                    </tr>

                    <tr>
                        <td align="center"><?= zen_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
                    </tr>
                    <tr>
                        <td align="center"><?= TEXT_AMOUNT . '  ' . zen_draw_input_field('amount', '',
								'size="8"') . '<br><br><span class="alert">' . CURRENT_TOTAL . $currencies->format($authnet_order->order_total) . '<br>' . CURRENT_BALANCE . $currencies->format($authnet_order->balance_due) . '<br>' . LEAVE_BLANK . '<br></span>'; ?></td>
                    </tr>
                    <tr>
                        <td align="center"><?= CHARGE_DESCRIPTION . '  ' . zen_draw_input_field('charge_description',
								'', 'size="30"'); ?></td>
                    </tr>
                    <tr>
                        <td align="center"><?= zen_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
                    </tr>
                    <tr class="alert alert-danger">
                        <td align="center" class="warningText"><?= WARN_MORE_MONEY; ?><br>
                            <p><input type="button" class="btn btn-info" value="<?= BUTTON_CANCEL; ?>"
                                      onclick="returnParent()">
                                <input type="submit" class="btn btn-warning" value="<?= BUTTON_SUBMIT; ?>"
                                       onclick="document.delete.submit();this.disabled=true"></td>
                    </tr>
                </table>
                </form>
				<?php
				break;  // END case
		}
	}// END else
?>
</div>
</body>
    </html>
<?php require 'includes/application_bottom.php';