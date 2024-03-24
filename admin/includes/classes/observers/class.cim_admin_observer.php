<?php
    /**
     *  developed, copyrighted and brought to you by @proseLA (github)
     *  https://mxworks.cc
     *  copyright 2024 proseLA
     *
     *  consider a donation.  payment modules are the core of any shopping cart.
     *  a lot of work went into the development of this module.  consider an annual donation of
     *  5 basis points of your sales if you want to keep this module going.
     *
     *  released under GPU
     *  https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
     *
     *  use of this software constitutes acceptance of license
     *  mxworks will vigilantly pursue any violations of this license.
     *
     *  some portions of code may be copyrighted and licensed by www.zen-cart.com
     *
     *  03/2024  project: authorizenet_cim v3.0.0 file: class.cim_admin_observer.php
     */


    if (!defined('IS_ADMIN_FLAG') || IS_ADMIN_FLAG !== true) {
        die('Illegal Access');
    }

    class cim_admin_observer extends base
    {
        public function __construct()
        {
            $this->attach($this, [
                'NOTIFY_ADMIN_ORDERS_PAYMENTDATA_COLUMN2',
                'NOTIFY_ADMIN_CUSTOMERS_MENU_BUTTONS',
                'NOTIFY_ADMIN_FOOTER_END',
            ]);
        }

        public function update(&$class, $eventID, &$p1, &$p2, &$p3, &$p4)
        {
            switch ($eventID) {
                case 'NOTIFY_ADMIN_ORDERS_PAYMENTDATA_COLUMN2':
                    require_once DIR_WS_CLASSES . 'authnet_order.php';
                    require_once DIR_WS_CLASSES . 'currencies.php';
                    $currencies = new currencies();

                    zen_include_language_file('authorizenet_cof.php', '/modules/payment/', 'inline');
                    require_once DIR_FS_CATALOG_MODULES . 'payment/' . 'authorizenet_cof.php';
                    $cof = new authorizenet_cof();

                    $authnet = new authnet_order($p1);
                    if ($authnet->payment || $authnet->balance_due > 0) {
                        ?>
                        <div class="panel panel-default " style="width: 60%">
                            <table class="table table-hover table-bordered">
                                <thead>
                                <tr>
                                    <th colspan="2"><?= TEXT_CIM_DATA ?></th>
                                    <?php
                                        $header_columns = 2;
                                        if ($authnet->payment) {
                                            $last_index = sizeof($authnet->payment) - 1;
                                        } else {
                                            $last_index = 0;
                                        }
                                        $cards = $cof->getCustomerCardsAsArray($authnet->cID, true);
                                        if (in_array(MODULE_PAYMENT_AUTHORIZENET_CIM_ALLOW_MORE, [
                                                'True',
                                                'TRUE',
                                                'true',
                                            ]) && $authnet->balance_due > 0 && $authnet->status != $this->cancelled_status() && (count($cards) > 0)) {
                                            $header_columns += 2;
                                            ?>
                                            <th colspan="2"><?= $authnet->button_new_funds($authnet->payment[$last_index]['index'] ?? '') ?></th>

                                            <?php
                                            $key = false;

                                            if (count($cards) > 1) {
                                                if (isset($_POST['ccindex'])) {
                                                    $cc_index = $_POST['ccindex'];
                                                    $key = array_search($cc_index, array_column($cards, 'id'));
                                                }


                                                if ($key === false && (string)$key != '0' && ($authnet->payment)) {
                                                    $cc_index = $authnet->getCustCardIndex($authnet->payment[$last_index]['payment_profile_id'],
                                                        true);
                                                    $key = array_search($cc_index, array_column($cards, 'id'));
                                                }

                                                if (!$key && (string)$key != '0') {
                                                    $cards[] = ['id' => '0', 'text' => 'Card not in file'];
                                                    $cc_index = 0;
                                                }
                                                $header_columns += 4;
                                                ?>
                                                <th colspan="4">

                                                    <?php
                                                        echo zen_draw_form('selection', FILENAME_ORDERS,
                                                            zen_get_all_get_params(), 'post',
                                                            'class="form-horizontal"');
                                                        echo zen_draw_label(LAST_CARD, 'ccindex',
                                                            'class="control-label" style="margin-right: 15px;"');
                                                        echo zen_draw_pull_down_menu('ccindex', $cards, $cc_index,
                                                            'onChange="this.form.submit()" class="btn btn-info"');
                                                    ?>
                                                    </form>

                                                </th>
                                                <?php
                                            }
                                        }
                                        if ($header_columns < 8) {
                                            ?>
                                            <td colspan="<?= (8 - $header_columns); ?>"></td>
                                            <?php
                                        }
                                    ?>
                                </tr>
                                <tr class="dataTableHeadingRow">
                                    <th scope="col"><?= CIM_NUMBER; ?></th>
                                    <th scope="col"><?= CIM_NAME; ?></th>
                                    <th scope="col"><?= CIM_AMOUNT; ?></th>
                                    <th scope="col"><?= CIM_TYPE; ?></th>
                                    <th scope="col"><?= CIM_POSTED; ?></th>
                                    <th scope="col"><?= CIM_MODIFIED; ?></th>
                                    <th scope="col"><?= CIM_APPROVAL; ?></th>
                                    <th scope="col"><?= CIM_ACTION; ?></th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php
                                    if ($authnet->payment) {
                                        for ($a = 0; $a < sizeof($authnet->payment); $a++) {
                                            ?>
                                            <tr class="bg-success">
                                                <th scope="row"><?= $authnet->payment[$a]['number']; ?></th>
                                                <td><?= $authnet->payment[$a]['name']; ?></td>
                                                <th scope="row"><?= $currencies->format($authnet->payment[$a]['amount']); ?></th>
                                                <td><?= $authnet->full_type($authnet->payment[$a]['type']); ?></td>
                                                <td><?= zen_datetime_short($authnet->payment[$a]['posted']); ?></td>
                                                <td><?= zen_datetime_short($authnet->payment[$a]['captured']); ?></td>
                                                <td><?= $authnet->payment[$a]['approval_code']; ?></td>
                                                <td><?php
                                                        $date = new DateTime($authnet->payment[$a]['posted']);
                                                        $now = new DateTime();
                                                        ((($authnet->payment[$a]['amount'] > $authnet->payment[$a]['refund_amount']) && (abs($date->diff($now)->format("%R%a")) < 120)) ? $authnet->button_refund('payment',
                                                            $authnet->payment[$a]['index']) : "");
                                                        ($authnet->payment[$a]['status'] == 'A' && ($authnet->payment[$a]['amount'] - $authnet->payment[$a]['refund_amount']) > 0) ? $authnet->button_capture($authnet->payment[$a]['index']) : "";

                                                    ?></td>
                                            </tr>
                                            <?php
                                            if ($authnet->refund) {
                                                for ($b = 0; $b < sizeof($authnet->refund); $b++) {
                                                    if ($authnet->refund[$b]['payment'] == $authnet->payment[$a]['index']) {
                                                        ?>
                                                        <tr class="refundRow bg-danger">
                                                            <th scope="row"><?= $authnet->refund[$b]['number']; ?></th>
                                                            <td><?= $authnet->refund[$b]['name']; ?></td>
                                                            <th scope="row"><?= '-' . $currencies->format($authnet->refund[$b]['amount']); ?></th>
                                                            <td><?= $authnet->full_type($authnet->refund[$b]['type']); ?></td>
                                                            <td><?= zen_datetime_short($authnet->refund[$b]['posted']); ?></td>
                                                            <td></td>
                                                            <td><?= $authnet->refund[$b]['approval_code']; ?></td>
                                                            <td></td>
                                                        </tr>
                                                        <?php
                                                    }  // END if ($authnet->refund[$b]['payment'] == $authnet->payment[$a]['index'])
                                                }  // END for($b = 0; $b < sizeof($authnet->refund); $b++)
                                            }  // END if ($authnet->refund)
                                        }  // END for($a = 0; $a < sizeof($payment); $a++)
                                    }

                                ?>
                                </tbody>
                                <tfoot>
                                <tr>
                                    <td colspan="8" class="ot-shipping-Text">Amount
                                        Applied: <?= $currencies->format($authnet->amount_applied); ?> Amount
                                        Due: <?= $currencies->format($authnet->balance_due); ?>
                                    </td>
                                </tr>
                                </tfoot>
                            </table>
                        </div>
                        <?php
                    }  // END if ($authnet->payment)
                    break;
                case 'NOTIFY_ADMIN_FOOTER_END':
                    ?>
                    <script>
                        function cimpopupWindow(url) {
                            window.open(url, 'popupWindow', 'toolbar=no,location=no,directories=no,status=no,menu bar=no,scrollbars=yes,resizable=yes,copyhistory=no,width=450,height=480,screenX=150,screenY=150,top=150,left=150')
                        }
                    </script>
                    <?php
                    break;
                case 'NOTIFY_ADMIN_CUSTOMERS_MENU_BUTTONS':
                    if (empty($p1) || !is_object($p1) || empty($p2) || !is_array($p2)) {
                        trigger_error('Missing or invalid parameters for the NOTIFY_ADMIN_CUSTOMERS_MENU_BUTTONS notifier.',
                            E_USER_ERROR);
                    }

                    zen_include_language_file('authorizenet_cim.php', '/modules/payment/', 'inline');
                    require_once DIR_FS_CATALOG_MODULES . 'payment/' . 'authorizenet_cim.php';
                    zen_include_language_file('authnet_order.php', '/', 'inline');
                    $authnet_cim = new authorizenet_cim();


                    $cards = $authnet_cim->getCustomerCards($p1->customers_id, true);

                    $valid_profile = $authnet_cim->getCustomerProfile($p1->customers_id);
                    // only show button if customer has cards on file
                    if (($valid_profile) && (!$cards->EOF)) {
                        $p2[] = [
                            'align' => 'text-center',
                            'text' => '<a href="javascript:cimpopupWindow(\'' . zen_href_link(FILENAME_AUTHNET_PAYMENTS,
                                    'cID=' . $p1->customers_id . '&action=clearCards',
                                    'NONSSL') . '\', \'scrollbars=yes,resizable=yes,width=100,height=1000,screenX=150,screenY=100,top=100,left=150\')"' .
                                'class="btn btn-danger" role="button" id="cards_btn" class="btn btn-danger btn-margin">' . BUTTON_DELETE_CARDS . '</a>',
                        ];
                    }
                    break;
                default:
                    break;
            }
        }

        private function cancelled_status()
        {
            global $db;
            $status = $db->Execute('SELECT orders_status_id FROM ' . TABLE_ORDERS_STATUS . ' WHERE orders_status_name LIKE "%Cancelled%" LIMIT 1');
            if ($status->EOF) {
                return ' ';
            } else {
                return $status->fields['orders_status_id'];
            }
        }
    }
