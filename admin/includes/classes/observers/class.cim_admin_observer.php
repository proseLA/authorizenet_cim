<?php
/*  portions copyright by... zen-cart.com

    developed and brought to you by proseLA
    https://rossroberts.com

    released under GPU
    https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0

   04/2020  project: authorizenet_cim; file: class.cim_admin_observer.php; version 2.1
*/
    
    if (!defined('IS_ADMIN_FLAG') || IS_ADMIN_FLAG !== true) {
        die('Illegal Access');
    }
    
    class cim_admin_observer extends base
    {
        public function __construct()
        {
            $this->attach($this, array(
              'NOTIFY_ADMIN_ORDERS_PAYMENTDATA_COLUMN2',
              'NOTIFY_ADMIN_CUSTOMERS_MENU_BUTTONS',
              'NOTIFY_ADMIN_FOOTER_END',
            ));
        }
        
        public function update(&$class, $eventID, &$p1, &$p2, &$p3, &$p4)
        {
            switch ($eventID) {
                case 'NOTIFY_ADMIN_ORDERS_PAYMENTDATA_COLUMN2':
                    require_once DIR_WS_CLASSES . 'authnet_order.php';
                    require_once DIR_WS_CLASSES . 'currencies.php';
                    $currencies = new currencies();
                    
                    $authnet = new authnet_order($p1);
	                if ($authnet->payment) {
                    ?>
                    <div class="panel panel-default " style="width: 60%">
                        <table class="table table-hover table-bordered">
                            <thead>
                            <tr>
                                <th colspan="2"><?= TEXT_CIM_DATA ?></th>
	                            <?php
		                            $last_index = sizeof($authnet->payment) - 1;
		                            if (in_array(MODULE_PAYMENT_AUTHORIZENET_CIM_ALLOW_MORE, array('True','TRUE','true')) && $authnet->balance_due > 0 && !empty($authnet->payment[$last_index]['payment_profile_id']) && +$authnet->payment[$last_index]['payment_profile_id'] !== 0) {
			                            ?>
                                        <th colspan="2"><?= $authnet->button_new_funds($authnet->payment[$last_index]['index']) ?></th>
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
                                    for ($a = 0; $a < sizeof($authnet->payment); $a++) {
                                        ?>
                                        <tr class="bg-success">
                                            <th scope="row" ><?= $authnet->payment[$a]['number']; ?></th>
                                            <td ><?= $authnet->payment[$a]['name']; ?></td>
                                            <th scope="row"><?= $currencies->format($authnet->payment[$a]['amount']); ?></td>
                                            <td ><?= $authnet->full_type($authnet->payment[$a]['type']); ?></td>
                                            <td ><?= zen_datetime_short($authnet->payment[$a]['posted']); ?></td>
                                            <td ><?= zen_datetime_short($authnet->payment[$a]['captured']); ?></td>
                                            <td ><?= $authnet->payment[$a]['approval_code']; ?></td>
                                            <td ><?php
                                                    $date = new DateTime($authnet->payment[$a]['posted']);
                                                    $now = new DateTime();
                                                    ((($authnet->payment[$a]['amount'] > $authnet->payment[$a]['refund_amount']) &&  $date->diff($now)->format("%d") < 120) ? $authnet->button_refund('payment',
                                                      $authnet->payment[$a]['index']) : "");
                                                    ($authnet->payment[$a]['status'] == 'A' && ($authnet->payment[$a]['amount'] - $authnet->payment[$a]['refund_amount']) > 0) ? $authnet->button_capture($authnet->payment[$a]['index']) : "";

                                                    ?></td>
                                        </tr>
                                        <?php
                                        if ($authnet->refund) {
                                            for ($b = 0; $b < sizeof($authnet->refund); $b++) {
                                                if ($authnet->refund[$b]['payment'] == $authnet->payment[$a]['index']) {
                                                    ?>
                                                    <tr class="refundRow bg-danger" onMouseOver="rowOverEffect(this)"
                                                        onMouseOut="rowOutEffect(this)">
                                                        <th scope="row"><?= $authnet->refund[$b]['number']; ?></td>
                                                        <td ><?= $authnet->refund[$b]['name']; ?></td>
                                                        <th scope="row"><?= '-' . $currencies->format($authnet->refund[$b]['amount']); ?></td>
                                                        <td ><?= $authnet->full_type($authnet->refund[$b]['type']); ?></td>
                                                        <td ><?= zen_datetime_short($authnet->refund[$b]['posted']); ?></td>
                                                        <td ></td>
                                                        <td ><?= $authnet->refund[$b]['approval_code']; ?></td>
                                                        <td > </td>
                                                    </tr>
                                                    <?php
                                                }  // END if ($authnet->refund[$b]['payment'] == $authnet->payment[$a]['index'])
                                            }  // END for($b = 0; $b < sizeof($authnet->refund); $b++)
                                        }  // END if ($authnet->refund)
                                    }  // END for($a = 0; $a < sizeof($payment); $a++)

                            ?>
                            <tfoot>
                            <tf>
                                <td class="ot-shipping-Text">Amount Applied: <?= $currencies->format($authnet->amount_applied); ?>  Amount Due: <?= $currencies->format($authnet->balance_due); ?>
                                    </td>
                            </tf>
                            </tfoot>
                            </tbody>
                        </table>
                    </div>
                <?php
	                }  // END if ($authnet->payment)
                    break;
                case 'NOTIFY_ADMIN_FOOTER_END':
                    ?>
                    <script>
                        function cimpopupWindow(url) {
                            window.open(url, 'popupWindow', 'toolbar=no,location=no,directories=no,status=no,menu bar=no,scrollbars=yes,resizable=yes,copyhistory=no,width=450,height=280,screenX=150,screenY=150,top=150,left=150')
                        }
                    </script>
                <?php
                    break;
                case 'NOTIFY_ADMIN_CUSTOMERS_MENU_BUTTONS':
                    if (empty($p1) || !is_object($p1) || empty($p2) || !is_array($p2)) {
                        trigger_error('Missing or invalid parameters for the NOTIFY_ADMIN_CUSTOMERS_MENU_BUTTONS notifier.',
                          E_USER_ERROR);
                        exit ();
                    }

                    require_once DIR_FS_CATALOG . DIR_WS_LANGUAGES . $_SESSION['language'] . '/modules/payment/authorizenet_cim.php';
                    require_once DIR_FS_CATALOG_MODULES . 'payment/' . 'authorizenet_cim.php';
                    $authnet_cim = new authorizenet_cim();
                    
                    $cards = $authnet_cim->getCustomerCards($p1->customers_id, true);
    
                    // only show button if customer has cards on file
                    if (!empty($cards->count()) || $cards->count() > 0) {
                        $p2[] = array(
                          'align' => 'text-center',
                          'text' => '<a href="javascript:cimpopupWindow(\'' . zen_href_link(FILENAME_AUTHNET_PAYMENTS,
                              'cID=' . $p1->customers_id . '&action=clearCards',
                              'NONSSL') . '\', \'scrollbars=yes,resizable=yes,width=100,height=1000,screenX=150,screenY=100,top=100,left=150\')"' .
                            'class="btn btn-danger" role="button" id="cards_btn" class="btn btn-danger btn-margin">Delete Credit Cards</a>'
                        );
                    }
                    break;
                default:
                    break;
            }
        }
    }