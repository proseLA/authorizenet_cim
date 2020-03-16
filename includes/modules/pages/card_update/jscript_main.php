<?php
    /**
     * jscript_form_check
     *
     * @package page
     * @copyright Copyright 2003-2010 Zen Cart Development Team
     * @copyright Portions Copyright 2003 osCommerce
     * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
     * @version $Id: jscript_main.php 17018 2010-07-27 07:25:41Z drbyte $
     */
?>
<script language="javascript" type="text/javascript"><!--
var selected;

function check_form_optional(form_name) {
  var form = form_name;
  if (!form.elements['firstname']) {
    return true;
  } else {
    var firstname = form.elements['firstname'].value;
    var lastname = form.elements['lastname'].value;
    var street_address = form.elements['street_address'].value;

    if (firstname == '' && lastname == '' && street_address == '') {
      return true;
    } else {
      return check_form(form_name);
    }
  }
}
var form = "";
var submitted = false;
var error = false;
var error_message = "";

function check_input(field_name, field_size, message) {
  if (form.elements[field_name] && (form.elements[field_name].type != "hidden")) {
    if (field_size == 0) return;
    var field_value = form.elements[field_name].value;

    if (field_value == '' || field_value.length < field_size) {
      error_message = error_message + "* " + message + "\n";
      error = true;
    }
  }
}

function check_radio(field_name, message) {
  var isChecked = false;

  if (form.elements[field_name] && (form.elements[field_name].type != "hidden")) {
    var radio = form.elements[field_name];

    for (var i=0; i<radio.length; i++) {
      if (radio[i].checked == true) {
        isChecked = true;
        break;
      }
    }

    if (isChecked == false) {
      error_message = error_message + "* " + message + "\n";
      error = true;
    }
  }
}

function check_select(field_name, field_default, message) {
  if (form.elements[field_name] && (form.elements[field_name].type != "hidden")) {
    var field_value = form.elements[field_name].value;

    if (field_value == field_default) {
      error_message = error_message + "* " + message + "\n";
      error = true;
    }
  }
}

function check_password(field_name_1, field_name_2, field_size, message_1, message_2) {
  if (form.elements[field_name_1] && (form.elements[field_name_1].type != "hidden")) {
    var password = form.elements[field_name_1].value;
    var confirmation = form.elements[field_name_2].value;

    if (password == '' || password.length < field_size) {
      error_message = error_message + "* " + message_1 + "\n";
      error = true;
    } else if (password != confirmation) {
      error_message = error_message + "* " + message_2 + "\n";
      error = true;
    }
  }
}

function check_password_new(field_name_1, field_name_2, field_name_3, field_size, message_1, message_2, message_3) {
  if (form.elements[field_name_1] && (form.elements[field_name_1].type != "hidden")) {
    var password_current = form.elements[field_name_1].value;
    var password_new = form.elements[field_name_2].value;
    var password_confirmation = form.elements[field_name_3].value;

    if (password_current == '' ) {
      error_message = error_message + "* " + message_1 + "\n";
      error = true;
    } else if (password_new == '' || password_new.length < field_size) {
      error_message = error_message + "* " + message_2 + "\n";
      error = true;
    } else if (password_new != password_confirmation) {
      error_message = error_message + "* " + message_3 + "\n";
      error = true;
    }
  }
}

function check_state(min_length, min_message, select_message) {
  if (form.elements["state"] && form.elements["zone_id"]) {
    if (!form.state.disabled && form.zone_id.value == "") check_input("state", min_length, min_message);
  } else if (form.elements["state"] && form.elements["state"].type != "hidden" && form.state.disabled) {
    check_select("zone_id", "", select_message);
  }
}

function check_form(form_name) {
  if (submitted == true) {
    alert("<?php echo JS_ERROR_SUBMITTED; ?>");
    return false;
  }

  error = false;
  form = form_name;
  error_message = "<?php echo JS_ERROR; ?>";

<?php echo '  check_radio("address_selection", "You must select a billing address for this credit card.");' . "\n"; ?>
<?php $new_address = '  check_select("address_selection", "new", "NEW");';

        $cc_validation = new cc_validation();
        $result = $cc_validation->validate($_POST['cc_number'], $_POST['cc_month'], $_POST['cc_year'], '');
        $card_error = false;
        switch ($result) {
            case -1:
                $error = sprintf(TEXT_CCVAL_ERROR_UNKNOWN_CARD, substr($cc_validation->cc_number, 0, 4));
                $card_error = true;
                break;
            case -2:
            case -3:
            case -4:
                $error = TEXT_CCVAL_ERROR_INVALID_DATE;
                $card_error = true;
                break;
            case false:
                $error = TEXT_CCVAL_ERROR_INVALID_NUMBER;
                $card_error = true;
                break;
        }
        if ($card_error) {
            ?>
    
    error_message = error_message + "* " + "<?php echo $error; ?>" + "\n";
    error = true;
    <?php
        }
    ?>

<?php if (!$new_address == '') {
        if ((int)ENTRY_FIRST_NAME_MIN_LENGTH > 0) { ?>
  check_input("firstname", <?php echo (int)ENTRY_FIRST_NAME_MIN_LENGTH; ?>, "<?php echo ENTRY_FIRST_NAME_ERROR; ?>");
<?php } ?>
<?php if ((int)ENTRY_LAST_NAME_MIN_LENGTH > 0) { ?>
  check_input("lastname", <?php echo (int)ENTRY_LAST_NAME_MIN_LENGTH; ?>, "<?php echo ENTRY_LAST_NAME_ERROR; ?>");
<?php } ?>

<?php if (ACCOUNT_DOB == 'true' && (int)ENTRY_DOB_MIN_LENGTH != 0) {
            echo '  check_input("dob", ' . (int)ENTRY_DOB_MIN_LENGTH . ', "' . ENTRY_DATE_OF_BIRTH_ERROR . '");' . "\n";
        } ?>
<?php if (ACCOUNT_COMPANY == 'true' && (int)ENTRY_COMPANY_MIN_LENGTH != 0) {
            echo '  check_input("company", ' . (int)ENTRY_COMPANY_MIN_LENGTH . ', "' . ENTRY_COMPANY_ERROR . '");' . "\n";
        } ?>

<?php if ((int)ENTRY_EMAIL_ADDRESS_MIN_LENGTH > 0) { ?>
  check_input("email_address", <?php echo (int)ENTRY_EMAIL_ADDRESS_MIN_LENGTH; ?>, "<?php echo ENTRY_EMAIL_ADDRESS_ERROR; ?>");
<?php } ?>
<?php if ((int)ENTRY_STREET_ADDRESS_MIN_LENGTH > 0) { ?>
  check_input("street_address", <?php echo (int)ENTRY_STREET_ADDRESS_MIN_LENGTH; ?>, "<?php echo ENTRY_STREET_ADDRESS_ERROR; ?>");
<?php } ?>
<?php if ((int)ENTRY_POSTCODE_MIN_LENGTH > 0) { ?>
  check_input("postcode", <?php echo (int)ENTRY_POSTCODE_MIN_LENGTH; ?>, "<?php echo ENTRY_POST_CODE_ERROR; ?>");
<?php } ?>
<?php if ((int)ENTRY_CITY_MIN_LENGTH > 0) { ?>
  check_input("city", <?php echo (int)ENTRY_CITY_MIN_LENGTH; ?>, "<?php echo ENTRY_CITY_ERROR; ?>");
<?php } ?>
<?php if (ACCOUNT_STATE == 'true') { ?>
  check_state(<?php echo (int)ENTRY_STATE_MIN_LENGTH . ', "' . ENTRY_STATE_ERROR . '", "' . ENTRY_STATE_ERROR_SELECT; ?>");
<?php } ?>

  check_select("country", "", "<?php echo ENTRY_COUNTRY_ERROR; ?>");

<?php
    }
    ?>


  if (error == true) {
    alert(error_message);
    return false;
  } else {
    submitted = true;
    return true;
  }
}
//--></script>
