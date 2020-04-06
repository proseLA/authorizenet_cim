<?php
    
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
            ));
            
        }
        
        public function update(&$class, $eventID, &$p1, &$p2, &$p3, &$p4)
        {
            switch ($eventID) {
                // -----
                // Issued during the orders-listing sidebar generation, after the upper button-list has been created.
                //
                // $p1 ... Contains the current $oInfo object, which contains the orders-id.
                // $p2 ... A reference to the current $contents array; the NEXT-TO-LAST element has been updated
                //         with the built-in button list.
                //
                
                case 'NOTIFY_ADMIN_ORDERS_PAYMENTDATA_COLUMN2':
                    require_once(DIR_WS_CLASSES . 'cim_order.php');
                    require_once(DIR_WS_CLASSES . 'currencies.php');
                    $currencies = new currencies();
                    
                    $cim = new cim_order($p1);
                    ?>
                    <div class="panel panel-default " style="width: 60%">
                        <table class="table table-hover">
                            <tr>
                                <td class="main"><strong><?= TEXT_CIM_DATA ?></strong></td>
                                
                            </tr>
                            <tr class="dataTableHeadingRow">
                                <th class="dataTableHeadingContent" align="left"
                                    width="15%"><?= CIM_NUMBER; ?></th>
                                <th class="dataTableHeadingContent" align="left"
                                    width="15%"><?= CIM_NAME; ?></th>
                                <th class="dataTableHeadingContent" align="right"
                                    width="15%"><?= CIM_AMOUNT; ?></th>
                                <th class="dataTableHeadingContent" align="center"
                                    width="15%"><?= CIM_TYPE; ?></th>
                                <th class="dataTableHeadingContent" align="left"
                                    width="15%"><?= CIM_POSTED; ?></th>
                                <th class="dataTableHeadingContent" align="left"
                                    width="15%"><?= CIM_APPROVAL; ?></th>
                                <th class="dataTableHeadingContent" align="right"
                                    width="10%"><?= CIM_ACTION; ?></th>
                            </tr>
                            <?php
                                if ($cim->payment) {
                                    for ($a = 0; $a < sizeof($cim->payment); $a++) {
                                        if ($a != 0) {
                                            ?>
                                            <tr>
                                                <td><?= zen_draw_separator('pixel_trans.gif', '1', '5'); ?></td>
                                            </tr>
                                            <?php
                                        }
                                        ?>
                                        <tr class="paymentRow bg-success">
                                            <td class="paymentContent"
                                                align="left"><?= $cim->payment[$a]['number']; ?></td>
                                            <td class="paymentContent"
                                                align="left"><?= $cim->payment[$a]['name']; ?></td>
                                            <td class="paymentContent" align="right">
                                                <strong><?= $currencies->format($cim->payment[$a]['amount']); ?></strong>
                                            </td>
                                            <td class="paymentContent"
                                                align="center"><?= $cim->full_type($cim->payment[$a]['type']); ?></td>
                                            <td class="paymentContent"
                                                align="left"><?= zen_datetime_short($cim->payment[$a]['posted']); ?></td>
                                            <td class="paymentContent"
                                                align="left"><?= $cim->payment[$a]['approval_code']; ?></td>
                                            <td class="paymentContent"
                                                align="right"><?php /*$cim->button_update('payment', $cim->payment[$a]['index']); */
                                                    ($cim->payment[$a]['amount'] > $cim->payment[$a]['refund_amount'] ? $cim->button_delete('payment',
                                                      $cim->payment[$a]['index']) : ""); ?></td>

                                        </tr>
                                        <?php
                                        if ($cim->refund) {
                                            for ($b = 0; $b < sizeof($cim->refund); $b++) {
                                                if ($cim->refund[$b]['payment'] == $cim->payment[$a]['index']) {
                                                    ?>
                                                    <tr class="refundRow bg-danger" onMouseOver="rowOverEffect(this)"
                                                        onMouseOut="rowOutEffect(this)">
                                                        <td class="refundContent"
                                                            align="left"><?= $cim->refund[$b]['number']; ?></td>
                                                        <td class="refundContent"
                                                            align="left"><?= $cim->refund[$b]['name']; ?></td>
                                                        <td class="refundContent" align="right">
                                                            <strong><?= '-' . $currencies->format($cim->refund[$b]['amount']); ?></strong>
                                                        </td>
                                                        <td class="refundContent"
                                                            align="center"><?= $cim->full_type($cim->refund[$b]['type']); ?></td>
                                                        <td class="refundContent"
                                                            align="left"><?= zen_datetime_short($cim->refund[$b]['posted']); ?></td>
                                                        <td class="refundContent"
                                                            align="left"><?= $cim->refund[$b]['approval_code']; ?></td>
                                                        <td class="refundContent"
                                                            align="right"><?php /*$cim->button_update('refund', $cim->refund[$b]['index']); *-/ $cim->button_delete('refund', $cim->refund[$b]['index']);*/ ?> </td>
                                                    </tr>
                                                    <?php
                                                }  // END if ($cim->refund[$b]['payment'] == $cim->payment[$a]['index'])
                                            }  // END for($b = 0; $b < sizeof($cim->refund); $b++)
                                        }  // END if ($cim->refund)
                                    }  // END for($a = 0; $a < sizeof($payment); $a++)
                                }  // END if ($cim->payment)
                            ?>
                        </table>
                    </div>
                <?php
                    break;
                case 'NOTIFY_ADMIN_ORDERS_MENU_BUTTONS':
                    if (is_object($p1)) {
                        $index_to_update = count($p2) - 2;
                        $p2[$index_to_update]['text'] = $this->addEditOrderButton($p1->orders_id,
                          $p2[$index_to_update]['text']);
                    }
                    break;
                
                // -----
                // Issued during the orders-listing sidebar generation, after the lower-button-list has been created.
                //
                // $p1 ... Contains the current $oInfo object (could be empty), containing the orders-id.
                // $p2 ... A reference to the current $contents array; the LAST element has been updated
                //         with the built-in button list.
                //
                case 'NOTIFY_ADMIN_ORDERS_MENU_BUTTONS_END':
                    if (is_object($p1)) {
                        $index_to_update = count($p2) - 1;
                        $p2[$index_to_update]['text'] = $this->addEditOrderButton($p1->orders_id,
                          $p2[$index_to_update]['text']);
                    }
                    break;
                
                // -----
                // Issued during an order's detailed display, allows the insertion of the "edit" button to link
                // the order to the "Edit Orders" processing.
                //
                // $p1 ... The order's ID
                // $p2 ... A reference to the order-class object.
                // $p3 ... A reference to the $extra_buttons string, which is updated to include that edit button.
                //
                case 'NOTIFY_ADMIN_ORDERS_EDIT_BUTTONS':
                    $p3 .= '&nbsp;' . $this->createEditOrdersLink($p1,
                        zen_image_button(EO_IMAGE_BUTTON_EDIT, IMAGE_EDIT), IMAGE_EDIT);
                    break;
                
                
                // -----
                // This notifier, issued by the Zen Cart v1.5.5 customers.php script, allows a plugin to add buttons
                // to the Customers->Customers display.
                //
                // $p1 ... A read-only copy of the current customer's $cInfo object NOW UPDATEABLE DUE TO &
                // $p2 ... An updateable copy of the current right-sidebar contents.
                //
                case 'NOTIFY_ADMIN_ORDER_PREDISPLAY_HOOK':
                    break;
                    require_once(DIR_WS_CLASSES . 'super_order.php');
                    $p3 = new super_order($p1);
                    break;
                
                case 'NOTIFY_ADMIN_CUSTOMERS_MENU_BUTTONS':
                    if (empty($p1) || !is_object($p1) || empty($p2) || !is_array($p2)) {
                        trigger_error('Missing or invalid parameters for the NOTIFY_ADMIN_CUSTOMERS_MENU_BUTTONS notifier.',
                          E_USER_ERROR);
                        exit ();
                    }
                    
                    $p2[] = array(
                      'align' => 'text-center',
                      'text' => '<a href="' . zen_href_link('customer_list_by_item.php',
                          'cid=' . $p1->customers_id,
                          'SSL') . '">' . '<input type="submit" name="submit" value="Delete Credit Cards" id="cards_btn" class="btn btn-danger btn-margin">' . '</a>'
                    );
                    
                    break;
                
                case 'NOTIFY_ADMIN_CUSTOMERS_LISTING_NEW_FIELDS':
                    
                    $p2 = ', c.customers_telephone, a.entry_company, a.entry_street_address, a.entry_city, a.entry_postcode, c.customers_authorization, c.customers_referral,  c.customers_customerProfileId ';
                
                default:
                    break;
            }
        }
        
        protected function addEditOrderButton($orders_id, $button_list)
        {
            $updated_button_list = str_replace(
              array(
                EO_IMAGE_BUTTON_EDIT,
                IMAGE_EDIT,
              ),
              array(
                EO_IMAGE_BUTTON_DETAILS,
                IMAGE_DETAILS
              ),
              $button_list
            );
            return $updated_button_list . '&nbsp;' . $this->createEditOrdersLink($orders_id,
                zen_image_button(EO_IMAGE_BUTTON_EDIT, IMAGE_EDIT), IMAGE_EDIT);
        }
        
        protected function createEditOrdersLink($orders_id, $link_button, $link_text, $include_zc156_parms = true)
        {
            $link_parms = '';
            if ($this->isPre156ZenCart) {
                $anchor_text = $link_button;
            } else {
                $anchor_text = $link_text;
                if ($include_zc156_parms) {
                    $link_parms = ' class="btn btn-primary" role="button"';
                }
            }
            return '&nbsp;<a href="' . zen_href_link(FILENAME_EDIT_ORDERS,
                zen_get_all_get_params(array('oID', 'action')) . "oID=$orders_id&action=edit",
                'NONSSL') . "\"$link_parms>$anchor_text</a>";
        }
    }
