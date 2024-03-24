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
     *  03/2024  project: authorizenet_cim v3.0.0 file: tpl_card_update_address.php
     */

?>
<div class="review_box" id='<?= $div_id; ?>'>
    <h2><?= $h2_title; ?></h2>
    <span class="inputError">You must select an address or enter a new one before proceeding.</span>
    <div class="review_box">
        <?php
        foreach ($addressArray as $addresses) {
            $selected = '';
            if (!isset($_POST['address_selection'])) {
                if ($div_id == 'ship_to_address') {
                    if ($user->fields['customers_default_shipping_id'] == $addresses['address_book_id']) {
                        $selected = 'selected';
                    }
                } else {
                    if ($user->fields['customers_default_address_id'] == $addresses['address_book_id']) {
                        $selected = 'selected';
                    }
                }
            } elseif ($_POST['address_selection'] == $addresses['address_book_id']) {
                $selected = 'selected';
            }
            ?>
            <div class="address_selection">
                <?= zen_draw_radio_field('address_selection', $addresses['address_book_id'],
                    $selected); ?>
                <address><?= '&nbsp; ' . zen_address_format($addresses['format_id'],
                        $addresses['address'],
                        true, ' ', '; '); ?></address>
            </div>
            <?php
        }
        ?>
        <div class="address_selection">
            <?= zen_draw_radio_field('address_selection', 'new',
                (($_POST['address_selection'] ?? 'nothing') == 'new' ? 'selected' : '')); ?>
            <address id="percent15">&nbsp;Enter New CC Address</address>
            <div id="show_select">
                <?php
                if (IS_ADMIN_FLAG) {
                    require('../' . DIR_WS_TEMPLATE . '/templates/tpl_modules_address_book_details.php');
                } else {
                    require($template->get_template_dir('tpl_modules_address_book_details.php',
                            DIR_WS_TEMPLATE,
                            $current_page_base,
                            'templates') . '/' . 'tpl_modules_address_book_details.php');
                }
                ?>
            </div>
        </div>
    </div>
</div>
