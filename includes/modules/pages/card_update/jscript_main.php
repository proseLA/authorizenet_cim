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
<link rel="stylesheet" type="text/css" href="includes/templates/template_default/css/cim.css" />
<script language="javascript" type="text/javascript"><!--
var selected;

$(function() {
	var exMonth;
	var exYear;
	$("#pmt-saved_cc").attr('checked', 'checked');
	$(":radio").filter(':checked').css("color", "green");
	$("input[name='payment']:checked").css("color", "red");
	$(".colorred h3").css("text-transform", "capitalize");
	$("#prevOrders tbody tr:odd, #prevOrdersNo tbody tr:odd, #myAccountOrdersStatus tbody tr:odd").addClass('zebra');
	$(".navigation").removeClass('zebra');
	$("div[name='payment']").mouseover(function() { $(this).css("color", "green"); });
	$("#checkoutShippingMethods div.fec-box-check-radio, #checkoutPayment div.fec-box-check-radio").hover(function() { $(this).css({"color": "white", "background": "#583227"}); },
		function() { $(this).css({"color": "#333", "background":"#ebebeb"}); });
	$("#createAcctDefault div.fec-box-check-radio").hover(function() { $(this).css({"color": "white", "background": "#583227"}); },
		function() { $(this).css({"color": "#404040", "background":"white"}); });
	$('#removeHover').unbind('mouseenter mouseleave');
	var width = $(window).width();
	if (width < 450) {
	    $(".fec-required").css("margin-top", "-15px");
	}
	if (width <750) {$("#mj-slideshow").empty();}
	$('input[name="loginButton_x"]').click(function(em) {
		if (!isValidEmailAddress($('input[name="email_address"]').val())) {
			$("#email_valid_error").removeClass("inputError").addClass("inputError_show");
			em.preventDefault();
		} else {$("#email_valid_error").removeClass("inputError_show").addClass("inputError");}
	});
	$("#createAcctDefault form[name='create_account']").submit(function (p) {
		error_free=true;
		email_exists=false;
		var sEmail = $('#email-address').val();
		if ($.trim(sEmail).length === 0) {
			$("#email_error").removeClass("inputError").addClass("inputError_show"); error_free=false;}
		else{$("#email_error").removeClass("inputError_show").addClass("inputError");
			if (!isValidEmailAddress(sEmail)) {
				$("#email_valid_error").removeClass("inputError").addClass("inputError_show"); error_free=false;}
			else {
				$("#email_valid_error").removeClass("inputError_show").addClass("inputError");
				$.post('checkemail.php', {'email' : sEmail}, function(data) {
					if(data.match("exist")) {
						$(".overlayError").removeClass("inputError_show").addClass("inputError");
						$("#email_exists_error, #email_exists_error2").removeClass("inputError").addClass("inputError_show"); error_free=false; email_exists=true;
					}
					else {$("#email_exists_error, #email_exists_error2").removeClass("inputError_show").addClass("inputError"); email_exists=false;}
				});
			}
		}
		if (!email_exists) {
			$("#password_confirm_error, #password_error").removeClass("inputError_show").addClass("inputError");
			if ($.trim($('#password-new').val()).length < 5) {
				$("#password_error").removeClass("inputError").addClass("inputError_show"); error_free=false;}
			else{$("#password_error").removeClass("inputError_show").addClass("inputError");
				if (($.trim($('#password-new').val())) !== ($.trim($('#password-confirm').val()))) {
					$("#password_confirm_error").removeClass("inputError").addClass("inputError_show"); error_free=false;}
				else{$("#password_confirm_error").removeClass("inputError_show").addClass("inputError");}
			}
			validateNewAddress();
		}
	});
	$("#show_select").hide();
	$('input[name="address_selection"]').click(function() {
		if ($('input[name="address_selection"][value="new"]').is(":checked")) {
			$("#show_select").show(1000);
			 $("#cardUpdate > form[name='card_update']").removeAttr("novalidate");
		} else {
			$("#show_select").hide(300);
			 $("#cardUpdate > form[name='card_update']").attr("novalidate", "true");
		}
	});
	$(".last_four, .expiration_date").css("font-weight","bold");
	$("#cardUpdate > form > div:nth-child(5)").removeClass('zebra');
	var card_good=false;
	var cc_num = $('#cc_number');
	if (cc_num.length) {
	$('#cc_number').validateCreditCard(function(result)
	{
		if (result.length_valid && result.luhn_valid) {
			card_good=true;
		}
	});
	}
	var card_pay_good = false;
	var cc_num_pay = $('#authorizenet_cim-cc-number');
	if (cc_num_pay.length) {
		$('#authorizenet_cim-cc-number').validateCreditCard(function(result)
		{
			if (result.length_valid && result.luhn_valid) {
				card_pay_good = true;
			}
		});
	}
	$("#cardUpdate > form[name='card_new']").submit(function (e) {
		error_free=true;
		if (!$('input[name="address_selection"]').is(":checked")) {
			$("#cardUpdate > form > span").removeClass("inputError").addClass("inputError_show"); error_free=false;}
		else{$("#cardUpdate > form > span").removeClass("inputError_show").addClass("inputError");}
		if ($('#cc_owner').val().length === 0) {
			$("#owner_error").removeClass("inputError").addClass("inputError_show"); error_free=false;}
		else{$("#owner_error").removeClass("inputError_show").addClass("inputError");}
		if (!card_good) {
			$("#cc_number_error").removeClass("inputError").addClass("inputError_show"); error_free=false;}
		else{$("#cc_number_error").removeClass("inputError_show").addClass("inputError");}
		if ($('input[name="address_selection"]:checked').val() == 'new') {
			validateNewAddress();
		}
		exMonth=$("#select-cc_month").val()-1;
		exYear=20+$("#select-cc_year").val();
		if (!validateExpDate()) {
			$("#cc_date_error").removeClass("inputError").addClass("inputError_show"); error_free=false;}
		else{$("#cc_date_error").removeClass("inputError_show").addClass("inputError");}
		if (!error_free){e.preventDefault();}
	});
	if ($('input[name="address_selection"][value="new"]').is(":checked")) {
	    $("#show_select").show(700); $('#change_billing').fadeOut();
	}
	$("#change_billing").click(function() {
		$("#cc_address").show(1000);
		$('#change_billing').fadeOut();
	});
	$("#cardUpdate > form[name='card_update']").submit(function (e) {
		error_free=true;
		validateNewAddress();
		if (!error_free){e.preventDefault();}
	});
	$("#checkout form[name='checkout_payment']").submit(function (t) {
		error_free=true;
		if (!$('input[name="conditions"]').is(":checked")) {
			$('.termsdescription').addClass('fec-fieldset-legend-two');
			$("#conditions_error").removeClass("inputError").addClass("inputError_show"); error_free=false;
		} else {
			$("#conditions_error").removeClass("inputError_show").addClass("inputError");
			$('.termsdescription').removeClass('fec-fieldset-legend-two');
		}
		if (($('#pmt-authorizenet_cim:checked').length>0)||($('#removeHover').length ==1)) {
			if (!card_pay_good) {
				$("#cc_number_error").removeClass("inputError").addClass("inputError_show");
				error_free=false;
			} else {$("#cc_number_error").removeClass("inputError_show").addClass("inputError");}
			exMonth=$("#authorizenet_cim-cc-expires-month").val()-1;
			exYear=20+$("#authorizenet_cim-cc-expires-year").val();
			if (!validateExpDate()) {
				$("#cc_date_error").removeClass("inputError").addClass("inputError_show");
				error_free=false;
			} else {$("#cc_date_error").removeClass("inputError_show").addClass("inputError");}
		} else if ($('#pmt-saved_cc').is(":checked")) {
			$("#cc_number_error").removeClass("inputError_show").addClass("inputError");
			$("#cc_date_error").removeClass("inputError_show").addClass("inputError");
		}
	});

	function validateNewAddress() {
		$('.obligate').each(function (index, value) {
			if($(this).attr('id').match("_shipping")){
				var field_error = $(this).attr('id').substr(0, ($(this).attr('id').length-9));
			} else { var field_error = $(this).attr('id');}
			if ($(this).val().length == 0) {
				$("#"+field_error+"_error").removeClass("inputError").addClass("inputError_show"); error_free=false;}
			else{$("#"+field_error+"_error").removeClass("inputError_show").addClass("inputError");}
		});
		if ($('#shippingAddress-checkbox').is(':not(:checked)')) {
			if ($("#select-zone_id_shipping").val().length == 0) {
				$("#select-zone_id_error").removeClass("inputError").addClass("inputError_show"); error_free=false;
			}
		}
	}

function validateExpDate() {
	var today, someday;
	today = new Date();
	someday = new Date();
	someday.setFullYear(exYear, exMonth, 1);
	if (someday < today) {
		return false;
	} else {return true;}
}
$.fn.exists = function(callback) {
  var args = [].slice.call(arguments, 1);

  if (this.length) {
    callback.call(this, args);
  }
  return this;
};
function isValidEmailAddress(emailAddress) {q
var pattern = /^([a-z\d!#$%&'*+\-\/=?^_`{|}~\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]+(\.[a-z\d!#$%&'*+\-\/=?^_`{|}~\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]+)*|"((([ \t]*\r\n)?[ \t]+)?([\x01-\x08\x0b\x0c\x0e-\x1f\x7f\x21\x23-\x5b\x5d-\x7e\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]|\\[\x01-\x09\x0b\x0c\x0d-\x7f\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]))*(([ \t]*\r\n)?[ \t]+)?")@(([a-z\d\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]|[a-z\d\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF][a-z\d\-._~\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]*[a-z\d\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])\.)+([a-z\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]|[a-z\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF][a-z\d\-._~\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]*[a-z\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])\.?$/i;
    return pattern.test(emailAddress);
}
});

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

  var $,
    __indexOf = [].indexOf || function(item) { for (var i = 0, l = this.length; i < l; i++) { if (i in this && this[i] === item) return i; } return -1; };

  $ = jQuery;

  $.fn.validateCreditCard = function(callback, options) {
    var card, card_type, card_types, get_card_type, is_valid_length, is_valid_luhn, normalize, validate, validate_number, _i, _len, _ref, _ref1;
    card_types = [
      {
        name: 'amex',
        pattern: /^3[47]/,
        valid_length: [15]
      }, {
        name: 'diners_club_carte_blanche',
        pattern: /^30[0-5]/,
        valid_length: [14]
      }, {
        name: 'diners_club_international',
        pattern: /^36/,
        valid_length: [14]
      }, {
        name: 'jcb',
        pattern: /^35(2[89]|[3-8][0-9])/,
        valid_length: [16]
      }, {
        name: 'laser',
        pattern: /^(6304|670[69]|6771)/,
        valid_length: [16, 17, 18, 19]
      }, {
        name: 'visa_electron',
        pattern: /^(4026|417500|4508|4844|491(3|7))/,
        valid_length: [16]
      }, {
        name: 'visa',
        pattern: /^4/,
        valid_length: [16]
      }, {
        name: 'mastercard',
        pattern: /^(5[1-5]|222[1-9]|22[3-9]|2[3-6]|27[01]|2720)/,
        valid_length: [16]
      }, {
        name: 'maestro',
        pattern: /^(5018|5020|5038|6304|6759|676[1-3])/,
        valid_length: [12, 13, 14, 15, 16, 17, 18, 19]
      }, {
        name: 'discover',
        pattern: /^(6011|622(12[6-9]|1[3-9][0-9]|[2-8][0-9]{2}|9[0-1][0-9]|92[0-5]|64[4-9])|65)/,
        valid_length: [16]
      }
    ];
    if (options == null) {
      options = {};
    }
    if ((_ref = options.accept) == null) {
      options.accept = (function() {
        var _i, _len, _results;
        _results = [];
        for (_i = 0, _len = card_types.length; _i < _len; _i++) {
          card = card_types[_i];
          _results.push(card.name);
        }
        return _results;
      })();
    }
    _ref1 = options.accept;
    for (_i = 0, _len = _ref1.length; _i < _len; _i++) {
      card_type = _ref1[_i];
      if (__indexOf.call((function() {
        var _j, _len1, _results;
        _results = [];
        for (_j = 0, _len1 = card_types.length; _j < _len1; _j++) {
          card = card_types[_j];
          _results.push(card.name);
        }
        return _results;
      })(), card_type) < 0) {
        throw "Credit card type '" + card_type + "' is not supported";
      }
    }
    get_card_type = function(number) {
      var _j, _len1, _ref2;
      _ref2 = (function() {
        var _k, _len1, _ref2, _results;
        _results = [];
        for (_k = 0, _len1 = card_types.length; _k < _len1; _k++) {
          card = card_types[_k];
          if (_ref2 = card.name, __indexOf.call(options.accept, _ref2) >= 0) {
            _results.push(card);
          }
        }
        return _results;
      })();
      for (_j = 0, _len1 = _ref2.length; _j < _len1; _j++) {
        card_type = _ref2[_j];
        if (number.match(card_type.pattern)) {
          return card_type;
        }
      }
      return null;
    };
    is_valid_luhn = function(number) {
      var digit, n, sum, _j, _len1, _ref2;
      sum = 0;
      _ref2 = number.split('').reverse();
      for (n = _j = 0, _len1 = _ref2.length; _j < _len1; n = ++_j) {
        digit = _ref2[n];
        digit = +digit;
        if (n % 2) {
          digit *= 2;
          if (digit < 10) {
            sum += digit;
          } else {
            sum += digit - 9;
          }
        } else {
          sum += digit;
        }
      }
      return sum % 10 === 0;
    };
    is_valid_length = function(number, card_type) {
      var _ref2;
      return _ref2 = number.length, __indexOf.call(card_type.valid_length, _ref2) >= 0;
    };
    validate_number = function(number) {
      var length_valid, luhn_valid;
      card_type = get_card_type(number);
      luhn_valid = false;
      length_valid = false;
      if (card_type != null) {
        luhn_valid = is_valid_luhn(number);
        length_valid = is_valid_length(number, card_type);
      }
      return callback({
        card_type: card_type,
        luhn_valid: luhn_valid,
        length_valid: length_valid
      });
    };
    validate = function() {
      var number;
      number = normalize($(this).val());
      return validate_number(number);
    };
    normalize = function(number) {
      return number.replace(/[ -]/g, '');
    };
    this.bind('input', function() {
      $(this).unbind('keyup');
      return validate.call(this);
    });
    this.bind('keyup', function() {
      return validate.call(this);
    });
    if (this.length !== 0) {
      validate.call(this);
    }
    return this;
  };
//--></script>
