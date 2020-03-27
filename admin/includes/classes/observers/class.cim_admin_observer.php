<?php
// -----
//
	if (!defined('IS_ADMIN_FLAG') || IS_ADMIN_FLAG !== true) {
		die('Illegal Access');
	}
	
	class cim_admin_observer extends base {
		public function __construct() {
			$this->attach($this, array(
        'NOTIFY_ADMIN_ORDERS_PAYMENTDATA_COLUMN2',
			));
			
		}
		
		public function update(&$class, $eventID, &$p1, &$p2, &$p3, &$p4) {
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
					$so = new super_order($p1);
              echo '<div>hey paully</div>';
                    ?><tr>
        <td><table border="0" cellspacing="0" cellpadding="2" width="100%">
          <tr>
            <td class="main"><strong><?php echo zen_image(DIR_WS_IMAGES . 'icon_money_add.png', TEXT_PAYMENT_DATA) . '&nbsp;' . TEXT_PAYMENT_DATA; ?></strong></td>
            <!--td align="right" colspan="7"><?php $so->button_add('payment'); $so->button_add('purchase_order'); $so->button_add('refund'); ?></td-->
          </tr>
          <tr class="dataTableHeadingRow">
            <td class="dataTableHeadingContent" align="left" width="15%"><?php echo PAYMENT_TABLE_NUMBER; ?></td>
            <td class="dataTableHeadingContent" align="left" width="15%"><?php echo PAYMENT_TABLE_NAME; ?></td>
            <td class="dataTableHeadingContent" align="right" width="15%"><?php echo PAYMENT_TABLE_AMOUNT; ?></td>
            <td class="dataTableHeadingContent" align="center" width="15%"><?php echo PAYMENT_TABLE_TYPE; ?></td>
            <td class="dataTableHeadingContent" align="left" width="15%"><?php echo PAYMENT_TABLE_POSTED; ?></td>
            <td class="dataTableHeadingContent" align="left" width="15%"><?php echo PAYMENT_TABLE_MODIFIED; ?></td>
            <td class="dataTableHeadingContent" align="right" width="10%"><?php echo PAYMENT_TABLE_ACTION; ?></td>
          </tr>
<?php
	$original_grand_total_paid=0;
        if ($so->payment) {
          for($a = 0; $a < sizeof($so->payment); $a++) {
            if ($a != 0) {
?>
          <tr>
            <td><?php echo zen_draw_separator('pixel_trans.gif', '1', '5'); ?></td>
          </tr>
<?php
            }
			$original_grand_total_paid =$original_grand_total_paid + $so->payment[$a]['amount'];
?>
          <!-- VINO_MOD out...tr class="paymentRow" onMouseOver="rowOverEffect(this)" onMouseOut="rowOutEffect(this)" <?php echo 'onclick="popupWindow(\'' . zen_href_link(FILENAME_SUPER_PAYMENTS, 'oID=' . $so->oID . '&payment_mode=payment&index=' . $so->payment[$a]['index'] . '&action=my_update', 'SSL') . '\', \'scrollbars=yes,resizable=yes,width=400,height=300,screenX=150,screenY=100,top=100,left=150\')"'; ?>-->
          <tr class="paymentRow" >
            <td class="paymentContent" align="left"><?php echo $so->payment[$a]['number']; ?></td>
            <td class="paymentContent" align="left"><?php echo $so->payment[$a]['name']; ?></td>
            <td class="paymentContent" align="right"><strong><?php echo $currencies->format($so->payment[$a]['amount']); ?></strong></td>
            <td class="paymentContent" align="center"><?php echo $so->full_type($so->payment[$a]['type']); ?></td>
            <td class="paymentContent" align="left"><?php echo zen_datetime_short($so->payment[$a]['posted']); ?></td>
            <td class="paymentContent" align="left"><?php echo zen_datetime_short($so->payment[$a]['modified']); ?></td>
            <td class="paymentContent" align="right"><?php /*$so->button_update('payment', $so->payment[$a]['index']); */ ($so->payment[$a]['amount']> $so->payment[$a]['refund_amount'] ? $so->button_delete('payment', $so->payment[$a]['index']) : "");?></td>
          
          </tr>
<?php
            if ($so->refund) {
              for($b = 0; $b < sizeof($so->refund); $b++) {
                if ($so->refund[$b]['payment'] == $so->payment[$a]['index']) {
?>
          <!-- vino_mod tr class="refundRow" onMouseOver="rowOverEffect(this)" onMouseOut="rowOutEffect(this)" <?php echo 'onclick="popupWindow(\'' . zen_href_link(FILENAME_SUPER_PAYMENTS, 'oID=' . $so->oID . '&payment_mode=refund&index=' . $so->refund[$b]['index'] . '&action=my_update', 'SSL') . '\', \'scrollbars=yes,resizable=yes,width=400,height=300,screenX=150,screenY=100,top=100,left=150\')"'; ?>-->
          <tr class="refundRow" onMouseOver="rowOverEffect(this)" onMouseOut="rowOutEffect(this)" >
            <td class="refundContent" align="left"><?php echo $so->refund[$b]['number']; ?></td>
            <td class="refundContent" align="left"><?php echo $so->refund[$b]['name']; ?></td>
            <td class="refundContent" align="right"><strong><?php echo '-' . $currencies->format($so->refund[$b]['amount']); ?></strong></td>
            <td class="refundContent" align="center"><?php echo $so->full_type($so->refund[$b]['type']); ?></td>
            <td class="refundContent" align="left"><?php echo zen_datetime_short($so->refund[$b]['posted']); ?></td>
            <td class="refundContent" align="left"><?php echo zen_datetime_short($so->refund[$b]['modified']); ?></td>
            <td class="refundContent" align="right"><?php /*$so->button_update('refund', $so->refund[$b]['index']); *-/ $so->button_delete('refund', $so->refund[$b]['index']);*/ ?> </td>
          </tr>
<?php
                }  // END if ($so->refund[$b]['payment'] == $so->payment[$a]['index'])
              }  // END for($b = 0; $b < sizeof($so->refund); $b++)
            }  // END if ($so->refund)
          }  // END for($a = 0; $a < sizeof($payment); $a++)
        }  // END if ($so->payment)
        if ($so->purchase_order) {
          for($c = 0; $c < sizeof($so->purchase_order); $c++) {
            if ($c < 1 && $so->payment) {
?>
          <tr>
            <td><?php echo zen_draw_separator('pixel_trans.gif', '1', '5'); ?></td>
          </tr>
          <tr>
            <td colspan="7"><?php echo zen_black_line(); ?></td>
          </tr>
          <tr>
            <td><?php echo zen_draw_separator('pixel_trans.gif', '1', '5'); ?></td>
          </tr>
<?php
            }
            elseif ($c > 1) {
?>
          <tr>
            <td><?php echo zen_draw_separator('pixel_trans.gif', '1', '5'); ?></td>
          </tr>
<?php
            }
?>
          <!-- vino_mod tr class="purchaseOrderRow" onMouseOver="rowOverEffect(this)" onMouseOut="rowOutEffect(this)" <?php echo 'onclick="popupWindow(\'' . zen_href_link(FILENAME_SUPER_PAYMENTS, 'oID=' . $so->oID . '&payment_mode=purchase_order&index=' . $so->purchase_order[$c]['index'] . '&action=my_update', 'SSL') . '\', \'scrollbars=yes,resizable=yes,width=400,height=300,screenX=150,screenY=100,top=100,left=150\')"'; ?>-->
          <tr class="purchaseOrderRow" onMouseOver="rowOverEffect(this)" onMouseOut="rowOutEffect(this)" >
            <td class="purchaseOrderContent" colspan="4" align="left"><?php echo $so->purchase_order[$c]['number']; ?></td>
            <td class="purchaseOrderContent" align="left"><?php echo zen_datetime_short($so->purchase_order[$c]['posted']); ?></td>
            <td class="purchaseOrderContent" align="left"><?php echo zen_datetime_short($so->purchase_order[$c]['modified']); ?></td>
            <td class="purchaseOrderContent" align="right"><?php /*$so->button_update('purchase_order', $so->purchase_order[$c]['index']); */ $so->button_delete('purchase_order', $so->purchase_order[$c]['index']);?></td>
          </tr>
<?php
            if ($so->po_payment) {
              for($d = 0; $d < sizeof($so->po_payment); $d++) {
                if ($so->po_payment[$d]['assigned_po'] == $so->purchase_order[$c]['index']) {
                  if ($d != 0) {
?>
          <tr>
            <td><?php echo zen_draw_separator('pixel_trans.gif', '1', '5'); ?></td>
          </tr>
<?php
                  }
?>
          <!-- vino_mod tr class="paymentRow" onMouseOver="rowOverEffect(this)" onMouseOut="rowOutEffect(this)" <?php echo 'onclick="popupWindow(\'' . zen_href_link(FILENAME_SUPER_PAYMENTS, 'oID=' . $so->oID . '&payment_mode=payment&index=' . $so->po_payment[$d]['index'] . '&action=my_update', 'SSL') . '\', \'scrollbars=yes,resizable=yes,width=400,height=300,screenX=150,screenY=100,top=100,left=150\')"'; ?>-->
          <tr class="paymentRow" onMouseOver="rowOverEffect(this)" onMouseOut="rowOutEffect(this)" >
            <td class="paymentContent" align="left"><?php echo $so->po_payment[$d]['number']; ?></td>
            <td class="paymentContent" align="left"><?php echo $so->po_payment[$d]['name']; ?></td>
            <td class="paymentContent" align="right"><strong><?php echo $currencies->format($so->po_payment[$d]['amount']); ?></strong></td>
            <td class="paymentContent" align="center"><?php echo $so->full_type($so->po_payment[$d]['type']); ?></td>
            <td class="paymentContent" align="left"><?php echo zen_datetime_short($so->po_payment[$d]['posted']); ?></td>
            <td class="paymentContent" align="left"><?php echo zen_datetime_short($so->po_payment[$d]['modified']); ?></td>
            <td class="paymentContent" align="right"><?php /*$so->button_update('payment', $so->po_payment[$d]['index']); */ $so->button_delete('payment', $so->po_payment[$d]['index']); ?></td>
          </tr>
<?php
                  if ($so->refund) {
                    for($e = 0; $e < sizeof($so->refund); $e++) {
                      if ($so->refund[$e]['payment'] == $so->po_payment[$d]['index']) {
?>
          <!-- vino_mod tr class="refundRow" onMouseOver="rowOverEffect(this)" onMouseOut="rowOutEffect(this)" <?php echo 'onclick="popupWindow(\'' . zen_href_link(FILENAME_SUPER_PAYMENTS, 'oID=' . $so->oID . '&payment_mode=refund&index=' . $so->refund[$e]['index'] . '&action=my_update', 'SSL') . '\', \'scrollbars=yes,resizable=yes,width=400,height=300,screenX=150,screenY=100,top=100,left=150\')"'; ?>-->
          <tr class="refundRow" onMouseOver="rowOverEffect(this)" onMouseOut="rowOutEffect(this)" >
            <td class="refundContent" align="left"><?php echo $so->refund[$e]['number']; ?></td>
            <td class="refundContent" align="left"><?php echo $so->refund[$e]['name']; ?></td>
            <td class="refundContent" align="right"><strong><?php echo '-' . $currencies->format($so->refund[$e]['amount']); ?></strong></td>
            <td class="refundContent" align="center"><?php echo $so->full_type($so->refund[$e]['type']); ?></td>
            <td class="refundContent" align="left"><?php echo zen_datetime_short($so->refund[$e]['posted']); ?></td>
            <td class="refundContent" align="left"><?php echo zen_datetime_short($so->refund[$e]['modified']); ?></td>
            <td class="refundContent" align="right"><?php /* $so->button_update('refund', $so->refund[$e]['index']); */ $so->button_delete('refund', $so->refund[$e]['index']); ?></td>
          </tr>
<?php
                      }  // END if ($so->refund[$e]['payment'] == $so->po_payment[$d]['index'])
                    }  // END for($e = 0; $e < sizeof($so->refund); $e++)
                  }  // END if ($so->refund)
                }  // END if ($so->po_payment[$d]['assigned_po'] == $so->purchase_order[$c]['index'])
              }  // END for($d = 0; $d < sizeof($so->po_payment); $d++)
            }  // END if ($so->po_payment)
          }  // END for($c = 0; $c < sizeof($so->purchase_order); $c++)
        }  // END if ($so->purchase_order)
        // display any refunds not tied directly to a payment
        if ($so->refund) {
          for ($f = 0; $f < sizeof($so->refund); $f++) {
            if ($so->refund[$f]['payment'] == 0) {
              if ($f < 1) {
?>
          <tr>
            <td><?php echo zen_draw_separator('pixel_trans.gif', '1', '5'); ?></td>
          </tr>
          <tr>
            <td colspan="7"><?php echo zen_black_line(); ?></td>
          </tr>
          <tr>
            <td><?php echo zen_draw_separator('pixel_trans.gif', '1', '5'); ?></td>
          </tr>
<?php
              } else {
?>
          <tr>
            <td><?php echo zen_draw_separator('pixel_trans.gif', '1', '5'); ?></td>
          </tr>
<?php
              }
?>
          <!-- vino_mod tr class="refundRow" onMouseOver="rowOverEffect(this)" onMouseOut="rowOutEffect(this)" <?php echo 'onclick="popupWindow(\'' . zen_href_link(FILENAME_SUPER_PAYMENTS, 'oID=' . $so->oID . '&payment_mode=refund&index=' . $so->refund[$f]['index'] . '&action=my_update', 'SSL') . '\', \'scrollbars=yes,resizable=yes,width=400,height=300,screenX=150,screenY=100,top=100,left=150\')"'; ?>-->
          <tr class="refundRow" onMouseOver="rowOverEffect(this)" onMouseOut="rowOutEffect(this)" >
            <td class="refundContent" align="left"><?php echo $so->refund[$f]['number']; ?></td>
            <td class="refundContent" align="left"><?php echo $so->refund[$f]['name']; ?></td>
            <td class="refundContent" align="right"><strong><?php echo '-' . $currencies->format($so->refund[$f]['amount']); ?></strong></td>
            <td class="refundContent" align="center"><?php echo $so->full_type($so->refund[$f]['type']); ?></td>
            <td class="refundContent" align="left"><?php echo zen_datetime_short($so->refund[$f]['posted']); ?></td>
            <td class="refundContent" align="left"><?php echo zen_datetime_short($so->refund[$f]['modified']); ?></td>
            <td class="refundContent" align="right"><?php /* $so->button_update('refund', $so->refund[$f]['index']); */ $so->button_delete('refund', $so->refund[$f]['index']); ?></td>
          </tr>
<?php
            }
          }
        }  // END if ($so->refund)
?>
        </table></td>
      </tr>
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
								'SSL') . '">' . '<input type="submit" name="submit" value="Constant Contact" id="cc_btn" class="btn btn-info btn-margin">' . '</a><br /><a href="' . zen_href_link('cust_ship_pref', 'cid=' . $p1->customers_id, 'SSL') . '">' . '<input type="submit" name="submit" value="Set Ship Date" id="shipdate_btn" class="btn btn-warning btn-margin">' . '</a>'
					);
					
					break;
				
				case 'NOTIFY_ADMIN_CUSTOMERS_MENU_BUTTONS_END':
					$one_year_ago = date('Y-m-d');
					$one_year_ago = strtotime('-1 year', strtotime($one_year_ago));
					$one_year_ago = date('Y-m-j', $one_year_ago);
					break;
					
					$customers_bottles = $GLOBALS['db']->Execute("SELECT sum(products_quantity) as bottle_count, sum(products_quantity_shipped) as shipped_count from " . TABLE_ORDERS . " o left join " . TABLE_ORDERS_PRODUCTS . " op on o.orders_id = op.orders_id WHERE o.customers_id = '" . $p1->customers_id . "'  and o.orders_status <> 199 group by o.customers_id");
					$customers_shipping = $GLOBALS['db']->Execute("SELECT sum(value) as ship_total from " . TABLE_ORDERS . " o left join orders_total ot on o.orders_id = ot.orders_id WHERE o.customers_id = '" . $p1->customers_id . "'  and o.orders_status <> 199 and ot.class = 'ot_shipping' group by o.customers_id");
					$customers_order_total = $GLOBALS['db']->Execute("SELECT sum(value) as order_total from " . TABLE_ORDERS . " o left join orders_total ot on o.orders_id = ot.orders_id WHERE o.customers_id = '" . $p1->customers_id . "'  and o.orders_status <> 199 and ot.class = 'ot_total' group by o.customers_id");
					$customers_12_month_total = $GLOBALS['db']->Execute("SELECT sum(value) as order_total from " . TABLE_ORDERS . " o left join orders_total ot on o.orders_id = ot.orders_id WHERE o.date_purchased > '" . $one_year_ago . "' and o.customers_id = '" . $p1->customers_id . "'  and o.orders_status <> 199 and ot.class = 'ot_total' group by o.customers_id");
					$customers_ship_charges = $GLOBALS['db']->Execute("SELECT sum(fdx_shipment_cost) as fedex_charges, count(fdx_shipment_id) as number_shipments, max(fdx_ship_date) as last_date from fedex_shipments left join " . TABLE_ORDERS . " o on o.orders_id = fdx_order_id WHERE o.customers_id = '" . $p1->customers_id . "'  and fdx_stat = 'A' group by o.customers_id");
					$customers_ship_zip = $GLOBALS['db']->Execute("SELECT fdx_del_postcode, fdx_shipment_id from fedex_shipments left join " . TABLE_ORDERS . " o on o.orders_id = fdx_order_id WHERE o.customers_id = '" . $p1->customers_id . "'  and fdx_stat = 'A' order by fdx_shipment_id DESC");
					// end paul r mod

					$ss_names = array(
						'HOLD' => 'Hold Shipments',
						'DATE' => 'Hold Until ',
						'SHIP' => 'Normal Shipping'
					);


					$ssrs = ship_status_get(intval($p1->customers_id));
					$ship_status = '<a href="' . zen_href_link('cust_ship_pref', 'cid=' .
							intval($p1->customers_id), 'SSL') . '">Shipping Status:</a> ';
					$ship_status = $ss_names[$ssrs['code']];
					if ($ssrs['code'] == 'DATE') {
						$ship_status .= ' ' . $ssrs['date'];
					}

					$p2[] = array('text' => '<br /><span class="font-weight-bold font-italic sh_err3">' .$ship_status . '</span>');
					
					$p2[] = array('text' => '<br />Customers Email Address: ' . $p1->customers_email_address);
					if (is_null($p1->customers_default_ship_change_date) || $p1->customers_default_ship_change_date == 0) {
						$ship_date_change = "None";
					} else {
						$ship_date_change = $p1->customers_default_ship_change_date;
					}
					$p2[] = array('text' => '<br />Default Shipping Address Change: ' . $ship_date_change);
					$customers_orders = $GLOBALS['db']->Execute("SELECT o.orders_id, o.date_purchased, o.order_total, o.currency, o.currency_value,
                                          cgc.amount
                                                  FROM " . TABLE_ORDERS . " o
                                                  LEFT JOIN " . TABLE_COUPON_GV_CUSTOMER . " cgc ON o.customers_id = cgc.customer_id
                                                  WHERE customers_id = " . (int)$p1->customers_id . "
                                                  ORDER BY date_purchased desc");
					
					if ($customers_orders->RecordCount() != 0) {
						
						$p2[] = array(
							'text' => TEXT_INFO_LAST_ORDER . ' ' . zen_date_short($customers_orders->fields['date_purchased']) . '<br />' . TEXT_INFO_ORDERS_TOTAL . ' ' . $GLOBALS['currencies']->format($customers_orders->fields['order_total'],
									true, $customers_orders->fields['currency'],
									$customers_orders->fields['currency_value'])
						);
						$p2[] = array(
							'text' => '<br />' . "Orders Total: " . ' ' . $GLOBALS['currencies']->format($customers_order_total->fields['order_total'],
									true, $customers_orders->fields['currency'],
									$customers_orders->fields['currency_value'])
						);
						$p2[] = array(
							'text' => "Previous 12 Month Total: " . ' ' . $GLOBALS['currencies']->format($customers_12_month_total->fields['order_total'],
									true, $customers_orders->fields['currency'],
									$customers_orders->fields['currency_value'])
						);
						//$p2[] = array('text' => "2014 YTD Total: " . ' ' . $GLOBALS['currencies']->format($customers_ytd_total->fields['order_total'], true, $customers_orders->fields['currency'], $customers_orders->fields['currency_value']));
						$p2[] = array(
							'text' => '<br />' . "Bottles Purchased: " . ' ' . $customers_bottles->fields['bottle_count'] . '<br />' . "Total Customer Shipping Charges: " . ' ' . $GLOBALS['currencies']->format($customers_shipping->fields['ship_total'],
									true, $customers_orders->fields['currency'],
									$customers_orders->fields['currency_value'])
						);
					}
					
					
					if ($p1->customers_customerProfileId <> 0) {
						$p2[] = array(
							'text' => '<br /><a href="' . zen_href_link(FILENAME_CUSTOMERS,
									zen_get_all_get_params(array(
										'cID',
										'action'
									)) . 'cID=' . $p1->customers_id . '&action=deletep',
									'SSL') . '">Delete All Credit Card data</a>'
						);
					}
					
					// paul rosenberg more changes to the end of page....
					if ($customers_ship_charges->fields['number_shipments'] != 0) {
						$p2[] = array(
							'text' => '<br />' . "Number of Shipments: " . ' ' . $customers_ship_charges->fields['number_shipments'] . '<br />' . "Bottles Shipped: " . ' ' . $customers_bottles->fields['shipped_count'] . '<br />' . "Approximate Carrier/FedEx Charges: " . ' ' . $GLOBALS['currencies']->format($customers_ship_charges->fields['fedex_charges'],
									true, $customers_orders->fields['currency'],
									$customers_orders->fields['currency_value']) . "<br />Last Shipment Date: " . zen_date_short($customers_ship_charges->fields['last_date']) . $customers_ship_charges->fields['fdx_ship_date']
						);

// get single case rate for last shipment.
						$zip3 = substr(trim($customers_ship_zip->fields['fdx_del_postcode']), 0, 3);
						$zoneResult = $GLOBALS['db']->Execute("select zone_id from lawc_shipping_zones where zone_low_zip <= '$zip3' and zone_high_zip >= '$zip3'");
						
						if ($zoneResult->RecordCount()) {
							$zone = $zoneResult->fields['zone_id'];
						}
						$casesRate = $GLOBALS['db']->Execute("select * from shipping_rates where rate_zone = '$zone' and rate_bottles = 7");
						//print_r($casesRate);
						$p2[] = array(
							'text' => '<br />' . "Ground Case Rate for Last Shipment: " . ' ' . $GLOBALS['currencies']->format($casesRate->fields['rate_ground'],
									true, $customers_orders->fields['currency'],
									$customers_orders->fields['currency_value'])
						);
					}

					/*
					$ss_names = array(
						'HOLD' => 'Hold Shipments',
						'DATE' => 'Hold Until ',
						'SHIP' => 'Normal Shipping'
					);
					
					
					$ssrs = ship_status_get(intval($p1->customers_id));
					$ship_status = '<a href="' . zen_href_link('cust_ship_pref', 'cid=' .
							intval($p1->customers_id), 'SSL') . '">Shipping Status:</a> ';
					$ship_status = $ss_names[$ssrs['code']];
					if ($ssrs['code'] == 'DATE') {
						$ship_status .= ' ' . $ssrs['date'];
					}

					$p2[] = array('text' => $ship_status);
					*/
					
					if (strpos($_SERVER['QUERY_STRING'], 'cID') == false) {
						$params = 'cID=' . intval($p1->customers_id) . '&' . $_SERVER['QUERY_STRING'];
					} else {
						$params = $_SERVER['QUERY_STRING'];
					}

//$password_reset  = '<br><br><a href="' . zen_href_link('cust_reset_pw', 'cID=' . intval($p1->customers_id) . '&' , 'SSL') .'">Reset PW to <b>VinsRare</b></a> ';
//$password_reset  = '<br><br><a href="' . zen_href_link('cust_reset_pw', $params, 'SSL') .'">Reset PW to <b>VinsRare</b></a> ';

//$p2[] = array('text'=>$password_reset);
					
					
					break;
				
				case 'NOTIFY_ADMIN_CUSTOMERS_LISTING_NEW_FIELDS':
					
					$p2 = ', c.customers_telephone, a.entry_company, a.entry_street_address, a.entry_city, a.entry_postcode, c.customers_authorization, c.customers_referral,  c.customers_customerProfileId ';
				
				default:
					break;
			}
		}
		
		protected function addEditOrderButton($orders_id, $button_list) {
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
		
		protected function createEditOrdersLink($orders_id, $link_button, $link_text, $include_zc156_parms = true) {
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
