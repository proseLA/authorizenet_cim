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
     *  03/2024  project: authorizenet_cim v3.0.0 file: tpl_card_update_default.php
     */

    $def_month = '04';
    $def_year = (int)date('y') + 2;
?>
    <div class="centerColumn" id="cardUpdate">
        <h1><?= HEADING_TITLE; ?></h1>
        <?php
            $valid_cid = $cim->checkValidPaymentProfile($customer_id, ($_GET['cid'] ?? ''));
            if (isset($_GET['cid']) && ($_GET['action'] == 'delete')) {

                if ($valid_cid['valid']) {
                    //echo 'ok to delete do form with confirm';
                    echo zen_draw_form('card_delete', zen_href_link(FILENAME_CARD_UPDATE, '', 'SSL'), 'post');
                    ?>
                    <div class='payment_select zebra'>
                        Please confirm deletion of Credit Card ending in <span
                                class='last_four'><?= $valid_cid['last_four']; ?></span> expiring on
                        <span class='expiration_date'>
		  <?= $valid_cid['exp_date']; ?>
		</span>
                        <span class='pay_buttons'>
		  <input type="submit" value="delete"/></a>
                            <input type="hidden" name="delete_cid" value="<?= (int)$_GET['cid'] ?>"/>
	</span>
                    </div>
                    </form>

                    <?php
                } else {
                    echo "<div class='payment_select zebra'> Sorry, that is an Invalid Credit Card.  Please contact us if you continue to have problems. </div>";
                }
            } elseif (isset($_GET['cid']) && ($_GET['action'] == 'update')) {
                //echo 'validate and update expiration_date';
                if ($valid_cid['valid']) {
                    // echo 'ok to update CC expiration number with form';
                    $def_month = substr($valid_cid['exp_date'], -2);
                    $def_year = substr($valid_cid['exp_date'], 2, 2);

                    echo zen_draw_form('card_update', zen_href_link(FILENAME_CARD_UPDATE, '', 'SSL'), 'post',
                        'novalidate');
                    ?>
                    <input type="hidden" name="update_cid" value="<?= (int)$_GET['cid'] ?>"/>
                    <div class='review_box size18'>
                        Update Credit Card ending in <span
                                class='last_four'><?= $valid_cid['last_four']; ?></span>
                    </div>
                    <div class='review_box size18'>
                        <?php
                            echo 'Month:' . zen_draw_pull_down_menu('cc_month', $expires_month,
                                    $def_month) . '&nbsp;Year:' . zen_draw_pull_down_menu('cc_year', $expires_year,
                                    $def_year);
                        ?>
                    </div>
                    <?php
                    require($template->get_template_dir('tpl_modules_address_book_details.php', DIR_WS_TEMPLATE,
                            $current_page_base,
                            'templates') . '/' . 'tpl_card_update_address.php');
                    ?>
                    <div id='update_cc'>
	<span class='create_buttons'>
		  <input type="submit" value="update"/></a>
	</span>
                    </div>
                    </form>
                    <?php
                } else {
                    echo "<div class='payment_select'> Sorry, that is an Invalid Credit Card.  Please contact us if you continue to have problems. </div>";
                }
            } elseif (($_GET['action'] ?? 'doNothing') == 'new') {
                $onFocus = ' onfocus="methodSelect(\'pmt-authorizenet_cim\')"';
                ?>
                <h2>Enter New Credit Card Information</h2>
                <?php
                //  echo zen_draw_form('card_update', zen_href_link(FILENAME_CARD_UPDATE, '', 'SSL'), 'post', 'onsubmit="return check_form(card_update);"');
                echo zen_draw_form('card_new', zen_href_link(FILENAME_CARD_UPDATE, 'action=new', 'SSL'), 'post');
                ?>
                <input type="hidden" name="new_cid" value="NEW"/>
                <div class="review_box">
                    <div class="new_card">
                        <div class="fec-credit-card-info">
                            <div class="fec-field">
                                <label for="cc_owner" class="inputLabel">Cardholder Name:</label>
                                <?= zen_draw_input_field('cc_owner',
                                    $user->fields['customers_firstname'] . ' ' . $user->fields['customers_lastname'],
                                    'id="cc_owner"'); ?>
                                <span id="owner_error" class="inputError">This field is required</span>
                            </div>
                            <div class="fec-field">
                                <label for="cc_number" class="inputLabel">Credit Card Number:</label>
                                <input type="text" name="cc_number" id="cc_number"/>
                                <span id="cc_number_error"
                                      class="inputError">Please enter a valid Credit Card Number</span>
                            </div>
                            <div class="fec-field">
                                <label for="cc-expires" class="inputLabel">Expiration Date:</label>
                                <?= zen_draw_pull_down_menu('cc_month', $expires_month,
                                    $def_month) . '&nbsp;&nbsp;' . zen_draw_pull_down_menu('cc_year', $expires_year,
                                    $def_year); ?>
                                <span id="cc_date_error" class="inputError">Expiration Date looks in the past.</span>
                            </div>

                            <?php
                                //echo zen_draw_input_field('cc_owner', $user->fields['firstname'] . ' ' . $user->fields['lastname'], 'id="authorizenet_cim-cc-owner"'. $onFocus); ?>
                            <!--/div-->
                        </div>
                    </div>
                </div>

                <?php
                require($template->get_template_dir('tpl_modules_address_book_details.php', DIR_WS_TEMPLATE,
                        $current_page_base,
                        'templates') . '/' . 'tpl_card_update_address.php');
                ?>

                <div>
	<span class='create_buttons'>
		  <input type="submit" value="Create"/>
	</span>
                </div>
                </form>
                <?php
            } else {

                $today = getdate();

                if (isset($_POST['authorizenet_cim_cc_expires_year'])) {
                    if (('20' . $_POST['authorizenet_cim_cc_expires_year']) > $today['year']) {
                    } elseif (($_POST['authorizenet_cim_cc_expires_month']) >= $today['mon']) {
                    } else {
                        ?>
                        <h3>You have an Invalid Credit Card Expiration Date</h3>
                        <?php
                    }
                }
                //if (strlen($cc_options) == 0) {
                if (empty($cards_saved)) {
                    ?>
                    <h3>You have no Credit Cards On File</h3>
                    <?php
                } else {
                    ?>
                    <div id='payment_choices'>
                    <?php
                    // echo zen_draw_form('card_update', zen_href_link(FILENAME_CARD_UPDATE, '', 'SSL'), 'post');
                    foreach ($cards_saved as $card) {
                        ?>
                        <div class='payment_select zebra'>
                            Credit Card ending in <span class='last_four'><?= $card['last_four']; ?></span>
                            expiring on
                            <span class='expiration_date'>
		  <?= $card['exp_date']; ?>
		</span>
                            <span class='pay_buttons'>
		  <?= '<a href="' . zen_href_link(FILENAME_CARD_UPDATE, 'cid=' . $card['id'] . '&action=update',
              'SSL') . '"> '; ?>
		  <button class="a-button-text btn btn-primary button">Edit</button>
                                </a>
		  <?= '<a href="' . zen_href_link(FILENAME_CARD_UPDATE, 'cid=' . $card['id'] . '&action=delete',
              'SSL') . '"> '; ?>
		  <button class="a-button-text btn btn-primary button">Delete</button>
                                </a>
		</span>
                            <?php
                                if ($_SESSION['emp_admin_login'] ?? false) {
                                    if ($card['enabled'] == 'Y') {
                                        echo "  Card is <span class='last_four'>Enabled</span>";
                                    } else {
                                        echo "  Card is <span class='last_four'>NOT Enabled</span>";
                                    }
                                }
                            ?>
                        </div>
                        <?php
                    }
                }
                ?>
                <div class='payment_select zebra'>
                    Enter a new Credit Card:
                    <span class='pay_buttons'>
		<?= '<a href="' . zen_href_link(FILENAME_CARD_UPDATE, 'action=new', 'SSL') . '"> '; ?>
                        <button class="a-button-text btn btn-primary button">Enter</button>
                        </a>
	  </span>
                </div>
                </div>

                <?php
            }
        ?>
    </div>
<?php
