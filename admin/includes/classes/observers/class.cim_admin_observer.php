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
                                <!--td align="right" colspan="7"><?php $cim->button_add('payment');
                                    $cim->button_add('purchase_order');
                                    $cim->button_add('refund'); ?></td-->
                            </tr>
                            <tr class="dataTableHeadingRow">
                                <td class="dataTableHeadingContent" align="left"
                                    width="15%"><?= CIM_NUMBER; ?></td>
                                <td class="dataTableHeadingContent" align="left"
                                    width="15%"><?= CIM_NAME; ?></td>
                                <td class="dataTableHeadingContent" align="right"
                                    width="15%"><?= CIM_AMOUNT; ?></td>
                                <td class="dataTableHeadingContent" align="center"
                                    width="15%"><?= CIM_TYPE; ?></td>
                                <td class="dataTableHeadingContent" align="left"
                                    width="15%"><?= CIM_POSTED; ?></td>
                                <td class="dataTableHeadingContent" align="left"
                                    width="15%"><?= CIM_MODIFIED; ?></td>
                                <td class="dataTableHeadingContent" align="right"
                                    width="10%"><?= CIM_ACTION; ?></td>
                            </tr>
                            <?php
                                $original_grand_total_paid = 0;
                                if ($cim->payment) {
                                    for ($a = 0; $a < sizeof($cim->payment); $a++) {
                                        if ($a != 0) {
                                            ?>
                                            <tr>
                                                <td><?= zen_draw_separator('pixel_trans.gif', '1', '5'); ?></td>
                                            </tr>
                                            <?php
                                        }
                                        $original_grand_total_paid = $original_grand_total_paid + $cim->payment[$a]['amount'];
                                        ?>
                                        <!-- VINO_MOD out...tr class="paymentRow" onMouseOver="rowOverEffect(this)" onMouseOut="rowOutEffect(this)" <?= 'onclick="couponpopupWindow(\'' . zen_href_link(FILENAME_CIM_PAYMENTS,
                                            'oID=' . $cim->oID . '&payment_mode=payment&index=' . $cim->payment[$a]['index'] . '&action=my_update',
                                            'SSL') . '\', \'scrollbars=yes,resizable=yes,width=400,height=300,screenX=150,screenY=100,top=100,left=150\')"'; ?>-->
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
                                                align="left"><?= zen_datetime_short($cim->payment[$a]['modified']); ?></td>
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
                                                    <!-- vino_mod tr class="refundRow" onMouseOver="rowOverEffect(this)" onMouseOut="rowOutEffect(this)" <?= 'onclick="couponpopupWindow(\'' . zen_href_link(FILENAME_CIM_PAYMENTS,
                                                        'oID=' . $cim->oID . '&payment_mode=refund&index=' . $cim->refund[$b]['index'] . '&action=my_update',
                                                        'SSL') . '\', \'scrollbars=yes,resizable=yes,width=400,height=300,screenX=150,screenY=100,top=100,left=150\')"'; ?>-->
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
                                                            align="left"><?= zen_datetime_short($cim->refund[$b]['modified']); ?></td>
                                                        <td class="refundContent"
                                                            align="right"><?php /*$cim->button_update('refund', $cim->refund[$b]['index']); *-/ $cim->button_delete('refund', $cim->refund[$b]['index']);*/ ?> </td>
                                                    </tr>
                                                    <?php
                                                }  // END if ($cim->refund[$b]['payment'] == $cim->payment[$a]['index'])
                                            }  // END for($b = 0; $b < sizeof($cim->refund); $b++)
                                        }  // END if ($cim->refund)
                                    }  // END for($a = 0; $a < sizeof($payment); $a++)
                                }  // END if ($cim->payment)
                                if ($cim->purchase_order) {
                                    for ($c = 0; $c < sizeof($cim->purchase_order); $c++) {
                                        if ($c < 1 && $cim->payment) {
                                            ?>
                                            <tr>
                                                <td><?= zen_draw_separator('pixel_trans.gif', '1', '5'); ?></td>
                                            </tr>
                                            <tr>
                                                <td colspan="7"><?= zen_black_line(); ?></td>
                                            </tr>
                                            <tr>
                                                <td><?= zen_draw_separator('pixel_trans.gif', '1', '5'); ?></td>
                                            </tr>
                                            <?php
                                        } elseif ($c > 1) {
                                            ?>
                                            <tr>
                                                <td><?= zen_draw_separator('pixel_trans.gif', '1', '5'); ?></td>
                                            </tr>
                                            <?php
                                        }
                                        ?>
                                        <!-- vino_mod tr class="purchaseOrderRow" onMouseOver="rowOverEffect(this)" onMouseOut="rowOutEffect(this)" <?= 'onclick="couponpopupWindow(\'' . zen_href_link(FILENAME_CIM_PAYMENTS,
                                            'oID=' . $cim->oID . '&payment_mode=purchase_order&index=' . $cim->purchase_order[$c]['index'] . '&action=my_update',
                                            'SSL') . '\', \'scrollbars=yes,resizable=yes,width=400,height=300,screenX=150,screenY=100,top=100,left=150\')"'; ?>-->
                                        <tr class="purchaseOrderRow" onMouseOver="rowOverEffect(this)"
                                            onMouseOut="rowOutEffect(this)">
                                            <td class="purchaseOrderContent" colspan="4"
                                                align="left"><?= $cim->purchase_order[$c]['number']; ?></td>
                                            <td class="purchaseOrderContent"
                                                align="left"><?= zen_datetime_short($cim->purchase_order[$c]['posted']); ?></td>
                                            <td class="purchaseOrderContent"
                                                align="left"><?= zen_datetime_short($cim->purchase_order[$c]['modified']); ?></td>
                                            <td class="purchaseOrderContent"
                                                align="right"><?php /*$cim->button_update('purchase_order', $cim->purchase_order[$c]['index']); */
                                                    $cim->button_delete('purchase_order',
                                                      $cim->purchase_order[$c]['index']); ?></td>
                                        </tr>
                                        <?php
                                        if ($cim->po_payment) {
                                            for ($d = 0; $d < sizeof($cim->po_payment); $d++) {
                                                if ($cim->po_payment[$d]['assigned_po'] == $cim->purchase_order[$c]['index']) {
                                                    if ($d != 0) {
                                                        ?>
                                                        <tr>
                                                            <td><?= zen_draw_separator('pixel_trans.gif', '1',
                                                                  '5'); ?></td>
                                                        </tr>
                                                        <?php
                                                    }
                                                    ?>
                                                    <!-- vino_mod tr class="paymentRow" onMouseOver="rowOverEffect(this)" onMouseOut="rowOutEffect(this)" <?= 'onclick="couponpopupWindow(\'' . zen_href_link(FILENAME_CIM_PAYMENTS,
                                                        'oID=' . $cim->oID . '&payment_mode=payment&index=' . $cim->po_payment[$d]['index'] . '&action=my_update',
                                                        'SSL') . '\', \'scrollbars=yes,resizable=yes,width=400,height=300,screenX=150,screenY=100,top=100,left=150\')"'; ?>-->
                                                    <tr class="paymentRow" onMouseOver="rowOverEffect(this)"
                                                        onMouseOut="rowOutEffect(this)">
                                                        <td class="paymentContent"
                                                            align="left"><?= $cim->po_payment[$d]['number']; ?></td>
                                                        <td class="paymentContent"
                                                            align="left"><?= $cim->po_payment[$d]['name']; ?></td>
                                                        <td class="paymentContent" align="right">
                                                            <strong><?= $currencies->format($cim->po_payment[$d]['amount']); ?></strong>
                                                        </td>
                                                        <td class="paymentContent"
                                                            align="center"><?= $cim->full_type($cim->po_payment[$d]['type']); ?></td>
                                                        <td class="paymentContent"
                                                            align="left"><?= zen_datetime_short($cim->po_payment[$d]['posted']); ?></td>
                                                        <td class="paymentContent"
                                                            align="left"><?= zen_datetime_short($cim->po_payment[$d]['modified']); ?></td>
                                                        <td class="paymentContent"
                                                            align="right"><?php /*$cim->button_update('payment', $cim->po_payment[$d]['index']); */
                                                                $cim->button_delete('payment',
                                                                  $cim->po_payment[$d]['index']); ?></td>
                                                    </tr>
                                                    <?php
                                                    if ($cim->refund) {
                                                        for ($e = 0; $e < sizeof($cim->refund); $e++) {
                                                            if ($cim->refund[$e]['payment'] == $cim->po_payment[$d]['index']) {
                                                                ?>
                                                                <!-- vino_mod tr class="refundRow" onMouseOver="rowOverEffect(this)" onMouseOut="rowOutEffect(this)" <?= 'onclick="couponpopupWindow(\'' . zen_href_link(FILENAME_CIM_PAYMENTS,
                                                                    'oID=' . $cim->oID . '&payment_mode=refund&index=' . $cim->refund[$e]['index'] . '&action=my_update',
                                                                    'SSL') . '\', \'scrollbars=yes,resizable=yes,width=400,height=300,screenX=150,screenY=100,top=100,left=150\')"'; ?>-->
                                                                <tr class="refundRow" onMouseOver="rowOverEffect(this)"
                                                                    onMouseOut="rowOutEffect(this)">
                                                                    <td class="refundContent"
                                                                        align="left"><?= $cim->refund[$e]['number']; ?></td>
                                                                    <td class="refundContent"
                                                                        align="left"><?= $cim->refund[$e]['name']; ?></td>
                                                                    <td class="refundContent" align="right">
                                                                        <strong><?= '-' . $currencies->format($cim->refund[$e]['amount']); ?></strong>
                                                                    </td>
                                                                    <td class="refundContent"
                                                                        align="center"><?= $cim->full_type($cim->refund[$e]['type']); ?></td>
                                                                    <td class="refundContent"
                                                                        align="left"><?= zen_datetime_short($cim->refund[$e]['posted']); ?></td>
                                                                    <td class="refundContent"
                                                                        align="left"><?= zen_datetime_short($cim->refund[$e]['modified']); ?></td>
                                                                    <td class="refundContent"
                                                                        align="right"><?php /* $cim->button_update('refund', $cim->refund[$e]['index']); */
                                                                            $cim->button_delete('refund',
                                                                              $cim->refund[$e]['index']); ?></td>
                                                                </tr>
                                                                <?php
                                                            }  // END if ($cim->refund[$e]['payment'] == $cim->po_payment[$d]['index'])
                                                        }  // END for($e = 0; $e < sizeof($cim->refund); $e++)
                                                    }  // END if ($cim->refund)
                                                }  // END if ($cim->po_payment[$d]['assigned_po'] == $cim->purchase_order[$c]['index'])
                                            }  // END for($d = 0; $d < sizeof($cim->po_payment); $d++)
                                        }  // END if ($cim->po_payment)
                                    }  // END for($c = 0; $c < sizeof($cim->purchase_order); $c++)
                                }  // END if ($cim->purchase_order)
                                // display any refunds not tied directly to a payment
                                if ($cim->refund) {
                                    for ($f = 0; $f < sizeof($cim->refund); $f++) {
                                        if ($cim->refund[$f]['payment'] == 0) {
                                            if ($f < 1) {
                                                ?>
                                                <tr>
                                                    <td><?= zen_draw_separator('pixel_trans.gif', '1',
                                                          '5'); ?></td>
                                                </tr>
                                                <tr>
                                                    <td colspan="7"><?= zen_black_line(); ?></td>
                                                </tr>
                                                <tr>
                                                    <td><?= zen_draw_separator('pixel_trans.gif', '1',
                                                          '5'); ?></td>
                                                </tr>
                                                <?php
                                            } else {
                                                ?>
                                                <tr>
                                                    <td><?= zen_draw_separator('pixel_trans.gif', '1',
                                                          '5'); ?></td>
                                                </tr>
                                                <?php
                                            }
                                            ?>
                                            <!-- vino_mod tr class="refundRow" onMouseOver="rowOverEffect(this)" onMouseOut="rowOutEffect(this)" <?= 'onclick="couponpopupWindow(\'' . zen_href_link(FILENAME_CIM_PAYMENTS,
                                                'oID=' . $cim->oID . '&payment_mode=refund&index=' . $cim->refund[$f]['index'] . '&action=my_update',
                                                'SSL') . '\', \'scrollbars=yes,resizable=yes,width=400,height=300,screenX=150,screenY=100,top=100,left=150\')"'; ?>-->
                                            <tr class="refundRow" onMouseOver="rowOverEffect(this)"
                                                onMouseOut="rowOutEffect(this)">
                                                <td class="refundContent"
                                                    align="left"><?= $cim->refund[$f]['number']; ?></td>
                                                <td class="refundContent"
                                                    align="left"><?= $cim->refund[$f]['name']; ?></td>
                                                <td class="refundContent" align="right">
                                                    <strong><?= '-' . $currencies->format($cim->refund[$f]['amount']); ?></strong>
                                                </td>
                                                <td class="refundContent"
                                                    align="center"><?= $cim->full_type($cim->refund[$f]['type']); ?></td>
                                                <td class="refundContent"
                                                    align="left"><?= zen_datetime_short($cim->refund[$f]['posted']); ?></td>
                                                <td class="refundContent"
                                                    align="left"><?= zen_datetime_short($cim->refund[$f]['modified']); ?></td>
                                                <td class="refundContent"
                                                    align="right"><?php /* $cim->button_update('refund', $cim->refund[$f]['index']); */
                                                        $cim->button_delete('refund',
                                                          $cim->refund[$f]['index']); ?></td>
                                            </tr>
                                            <?php
                                        }
                                    }
                                }  // END if ($cim->refund)
                            ?>
                        </table>
                    </div>
                <?php
                //die(__FILE__ . ':' . __LINE__);
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
                          'SSL') . '">' . '<input type="submit" name="submit" value="Products" id="products_btn" class="btn btn-info btn-margin">' . '</a>'
                        . ' <br /><a href="' . zen_href_link('set_default_delivery_address.php',
                          'cID=' . $p1->customers_id,
                          'SSL') . '">' . '<input type="submit" name="submit" value="Set Delivery Address" id="delivery_btn" class="btn btn-info btn-margin">' . '</a><a href="' . zen_href_link('email_constant_cont_resignup.php',
                          zen_get_all_get_params(array('cID', 'action')) . 'cID=' . $p1->customers_id,
                          'SSL') . '">' . '<input type="submit" name="submit" value="Constant Contact" id="cc_btn" class="btn btn-info btn-margin">' . '</a><br /><a href="' . zen_href_link('cust_ship_pref',
                          'cid=' . $p1->customers_id,
                          'SSL') . '">' . '<input type="submit" name="submit" value="Set Ship Date" id="shipdate_btn" class="btn btn-warning btn-margin">' . '</a>'
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
