<?php

class paystation
{
    var $code, $title, $description, $enabled;
    var $payment_successful = false;

    function paystation()
    {
        global $order;

        $this->code = 'paystation';
        $this->title = "Paystation Payment Gateway ";
        $this->description = "Paystation three-party payment module";
        $this->sort_order = MODULE_PAYMENT_PAYSTATION_SORT_ORDER;
        $this->enabled = ((MODULE_PAYMENT_PAYSTATION_STATUS == 'True') ? true : false);
        if ((int)MODULE_PAYMENT_PAYSTATION_ORDER_STATUS_ID > 0) {
            $this->order_status = MODULE_PAYMENT_PAYSTATION_ORDER_STATUS_ID;
        }

        if (is_object($order))
            $this->update_status();
        $this->paystation_url = 'https://www.paystation.co.nz/direct/paystation.dll';
    }

    function update_status()
    {
        global $order;

        if (($this->enabled == true) && ((int)MODULE_PAYMENT_PAYSTATION_ZONE > 0)) {
            $check_flag = false;
            $check_query = tep_db_query("select zone_id from " . TABLE_ZONES_TO_GEO_ZONES . " where geo_zone_id = '" . MODULE_PAYMENT_PAYSTATION_ZONE . "' and zone_country_id = '" . $order->delivery['country']['id'] . "' order by zone_id");
            while ($check = tep_db_fetch_array($check_query)) {
                if ($check['zone_id'] < 1) {
                    $check_flag = true;
                    break;
                } elseif ($check['zone_id'] == $order->delivery['zone_id']) {
                    // $check_flag = true;
                    break;
                }
            }

            if ($check_flag == false) {
                $this->enabled = false;
            }
        }
    }

    // this method returns the javascript that will validate the form entry
    function javascript_validation()
    {
        return false;
    }

    // this method returns the html that creates the input form
    function selection()
    {
        $selection = array('id' => $this->code,
            'module' => MODULE_PAYMENT_PAYSTATION_DISPLAY_TITLE);

        return $selection;
    }

    // this method is called before the data is sent to the credit card processor
    // here you can do any field validation that you need to do
    // we also set the global variables here from the form values
    function pre_confirmation_check()
    {
        $_SESSION['paystation_pstn_ct'] = $_POST['pstn_ct'];
        return false;
    }

    // this method returns the data for the confirmation page
    function confirmation()
    {
        $paystation_return = urlencode($_POST['payment'] . '|' . $_POST['sendto'] . '|' . $shipping_cost . '|' . urlencode($shipping_method) . '|' . urlencode($comments) . '&' . SID);
        $checkout_form_action = $paystation;
        return false;
    }

    function process_button()
    {
        return false;
    }

    function before_process()
    {
        global $link;

        if (isset($_SESSION['postback_process']) && $_SESSION['postback_process']) {
            if ($_SESSION['postback_errorCode'] == 0)
                return;
            else
                exit("Order not processed. Error code: " . $_SESSION['postback_errorCode']);
        }

        global $_POST, $_GET, $order, $messageStack;

        if ($_GET['ec'] == '') {
            $tempSession = $this->makePaystationSessionID(8, 8) . time();
            $psamount = (number_format($order->info['total'], 2, '.', '') * 100);
            $session_array = serialize($_SESSION);
            $session_array = mysql_escape_string(serialize($_SESSION));

            $sql = ("insert into paystation_session 
                (pstn_ms, session_data, transaction_mode)
                values ('$tempSession', '" . $session_array . "', '" . MODULE_PAYMENT_PAYSTATION_TESTMODE . "')");

            tep_db_query($sql);

            $paystationURL = "https://www.paystation.co.nz/direct/paystation.dll";

            $mr = $_SESSION['customer_id'] . ":" . time() . ":" . $order->customer['email_address'];

            $pstn_mr = urlencode($mr);

            $paystationParams = "paystation=_empty&pstn_am=" . $psamount .
                "&pstn_af=cents&pstn_pi=" . trim(MODULE_PAYMENT_PAYSTATION_ID) .
                "&pstn_gi=" . trim(MODULE_PAYMENT_PAYSTATION_GATEWAY_ID) .
                "&pstn_ms=" . $tempSession . "&pstn_nr=t&pstn_mr=" . $pstn_mr;

            $_SESSION['paystation_id'] = MODULE_PAYMENT_PAYSTATION_ID;
            $_SESSION['$paystation_ms'] = $tempSession;

            if (MODULE_PAYMENT_PAYSTATION_TESTMODE == 'Test') {
                $paystationParams .= '&pstn_tm=t';
            }

            $authResult = $this->directTransaction($paystationURL, $paystationParams);

            $p = xml_parser_create();
            xml_parse_into_struct($p, $authResult, $vals, $tags);
            xml_parser_free($p);

            for ($j = 0; $j < count($vals); $j++) {
                if (!strcmp($vals[$j]["tag"], "TI") && isset($vals[$j]["value"])) {
                    $returnTI = $vals[$j]["value"];
                }
                if (!strcmp($vals[$j]["tag"], "EC") && isset($vals[$j]["value"])) {
                    $errorCode = $vals[$j]["value"];
                }
                if (!strcmp($vals[$j]["tag"], "EM") && isset($vals[$j]["value"])) {
                    $errorMessage = $vals[$j]["value"];
                }
                if (!strcmp($vals[$j]["tag"], "DIGITALORDER") && isset($vals[$j]["value"])) {
                    $digitalOrder = $vals[$j]["value"];
                }
            }

            if ($digitalOrder) {
                tep_redirect($digitalOrder);
            } else {
                tep_redirect(tep_href_link(FILENAME_CHECKOUT_PAYMENT, 'error_message=' . $errorMessage, 'SSL', true, false));
            }
        } else {
            $responseCode = $this->_transactionVerification(MODULE_PAYMENT_PAYSTATION_ID, $_GET['ti'], $_GET['ms']);
            if (MODULE_PAYMENT_PAYSTATION_POSTBACK == 'Yes'
                && $this->transaction_already_processed(mysql_escape_string($_GET['ti']))
                && $_GET['ec'] == '0') {
                tep_redirect(tep_href_link(FILENAME_CHECKOUT_SUCCESS, '', 'SSL', true, false));
            }

            if ($_SESSION['pstn_ti']) {

                $this->paystation_postback_store_pstn_ti($_SESSION['pstn_ti']);
            }
            if (isset($_GET['ec'])) {

                if ((int)$responseCode === 0) {
                    $this->payment_successful = true;
                } else {
                    $this->payment_successful = false;
                    tep_redirect(tep_href_link(FILENAME_CHECKOUT_PAYMENT, 'error_message=' . urlencode("Paystation payment failed: " . $_GET['em']), 'SSL', true, false));

                }
            }
        }

        return false;
    }

    function after_process()
    {
        if ($_SESSION['pstn_ti']) {
            $this->paystation_postback_store_pstn_ti($_SESSION['pstn_ti']);
        } else
            $this->store_pstn_ti();

        unset($_SESSION['pstn_ti']);

        if ($this->payment_successful) {
            if ((defined('MODULE_PAYMENT_PAYSTATION_SEND_PAYMENT_EMAIL')) && (tep_validate_email(MODULE_PAYMENT_PAYSTATION_SEND_PAYMENT_EMAIL))) {
                if ((MODULE_PAYMENT_PAYSTATION_POSTBACK == 'Yes' && $this->check() == 0) || MODULE_PAYMENT_PAYSTATION_POSTBACK == 'No') {
                    $message = "osCommerce order number: " . $insert_id . "\n\nMerchant session (ms): " . $_GET['ms'] . "\nPaystation order number (ti): " . $_GET['ti'] . "\nError code (ec): " . $_GET['ec'] . "\nError message (em): " . $_GET['em'] . "\n\nThank you for choosing Paystation!";
                    tep_mail('', MODULE_PAYMENT_PAYSTATION_SEND_PAYMENT_EMAIL, 'Paystation information for order ' . $insert_id, $message, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS);
                }
            }
        }
    }

    function transaction_already_processed($pstn_ti)
    {
        global $_POST, $_GET, $insert_id, $db;
        $db_query = tep_db_query("select pstn_ti from  " . TABLE_ORDERS . " where pstn_ti = '$pstn_ti'");
        $count = tep_db_num_rows($db_query);
        return ($count >= 1);
    }

    function paystation_postback_store_pstn_ti($ti)
    {
        //Stores the paystation transaction ID to make sure order isn't created twice.
        $result = tep_db_query("select MAX(orders_id) as order_id from " . TABLE_ORDERS . " where customers_id = '" . $_SESSION['customer_id'] . "'");
        $row = tep_db_fetch_array($result);
        $order_id = $row['order_id'];

        tep_db_query("update " . TABLE_ORDERS . " set pstn_ti = '$ti' where orders_id = '" . $order_id . "'");
        return false;
    }

    function store_pstn_ti()
    {
        //Stores the paystation transaction ID to make sure order isn't created twice.
        global $_POST, $_GET, $insert_id, $db;
        if (isset($_GET['ti']))
            $ti = $_GET['ti'];
        elseif (isset($_SESSION['pstn_ti']))
            $ti = $_SESSION['pstn_ti'];

        tep_db_query("update " . TABLE_ORDERS . " set pstn_ti = '$ti' where orders_id = '" . $insert_id . "'");
        return false;
    }

    function check()
    {
        if (!isset($this->_check)) {
            $check_query = tep_db_query("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_PAYSTATION_STATUS'");
            $this->_check = tep_db_num_rows($check_query);
        }
        return $this->_check;
    }

    function install()
    {
        $postbackMessage = "We strongly suggest setting \\'Enable Postback\\' to \\'Yes\\' as it will allow the cart to capture payment results even
                    if your customers re-direct is interrupted after payment.";

        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Set Order Status', 'MODULE_PAYMENT_PAYSTATION_ORDER_STATUS_ID', '0', 'Set the status of orders made with this payment module to this value', '6', '2', 'tep_cfg_pull_down_order_statuses(', 'tep_get_order_status_name', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Enable Paystation Module', 'MODULE_PAYMENT_PAYSTATION_STATUS', 'True', 'Allows customers to select Paystation as a payment method', '6', '1', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Checkout caption', 'MODULE_PAYMENT_PAYSTATION_DISPLAY_TITLE', 'Pay using your credit card. You will be redirected to Paystation Payment Gateway to complete your payment.', 'Text to display for the payment method in the checkout.', '6', '1', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Payment Zone', 'MODULE_PAYMENT_PAYSTATION_ZONE', '0', 'If a zone is selected, only enable this payment method for that zone.', '6', '2', 'tep_get_zone_class_title', 'tep_cfg_pull_down_zone_classes(', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Paystation ID', 'MODULE_PAYMENT_PAYSTATION_ID', ' ', 'Your Paystation ID as supplied - usually a six digit number', '6', '1', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Gateway ID', 'MODULE_PAYMENT_PAYSTATION_GATEWAY_ID', ' ', 'Your Gateway ID as supplied', '6', '1', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Enable Postback', 'MODULE_PAYMENT_PAYSTATION_POSTBACK', 'Yes', '" . $postbackMessage . "' , '6', '5', 'tep_cfg_select_option(array(\'Yes\', \'No\'), ', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('HMAC Key', 'MODULE_PAYMENT_PAYSTATION_HMAC_KEY', ' ', 'HMAC key supplied by paystation', '6', '3', now())");
        tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Transaction Mode', 'MODULE_PAYMENT_PAYSTATION_TESTMODE', 'Test', 'Transaction mode used for processing orders.  Used for testing after the initial \'go live\'.  You need to coordinate with Paystation during your initial launch.', '6', '6', 'tep_cfg_select_option(array(\'Test\', \'Production\'), ', now())");

        //Add a column to the order table to store the transaction ID when a transaction is successful
        $check_pstn_ti_query = tep_db_query("show columns from " . TABLE_ORDERS . " LIKE 'pstn_ti'");
        $array = tep_db_fetch_array($check_pstn_ti_query);

        if (count($array) < 1) {
            tep_db_query("alter table " . TABLE_ORDERS . " add column pstn_ti varchar(100) after payment_method");
        }

        tep_db_query("CREATE TABLE `paystation_session` (
                        `unique_id` INT(11) NOT NULL AUTO_INCREMENT,
                        `pstn_ms` VARCHAR(50) NOT NULL,
                        `transaction_mode` VARCHAR(20) NOT NULL DEFAULT '0',
                        `session_data` MEDIUMTEXT NOT NULL,
                        PRIMARY KEY (`unique_id`))");
    }

    function remove()
    {
        tep_db_query("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
        tep_db_query("drop table if exists paystation_session ");
    }

    function keys()
    {
        return array('MODULE_PAYMENT_PAYSTATION_DISPLAY_TITLE', 'MODULE_PAYMENT_PAYSTATION_STATUS', 'MODULE_PAYMENT_PAYSTATION_ZONE', 'MODULE_PAYMENT_PAYSTATION_ID', 'MODULE_PAYMENT_PAYSTATION_GATEWAY_ID', 'MODULE_PAYMENT_PAYSTATION_POSTBACK', 'MODULE_PAYMENT_PAYSTATION_HMAC_KEY', 'MODULE_PAYMENT_PAYSTATION_TESTMODE', 'MODULE_PAYMENT_PAYSTATION_ORDER_STATUS_ID');
    }

    function _paystation_attribute_value($attribute, $string)
    {
        list(, $exploded_value) = explode('<' . $attribute . '>', $string);
        return substr($exploded_value, 0, strpos($exploded_value, '</' . $attribute . '>'));
    }

    function makePaystationSessionID($min = 8, $max = 8)
    {
        # seed the random number generator - straight from PHP manual, dunno what it does
        $seed = (double)microtime() * getrandmax();
        srand($seed);
        # make a string of $max characters with ASCII values of 40-122
        $p = 0;
        while ($p < $max):
            $r = 123 - (rand() % 75);

            //If the random character is non-alphanumeric, try again
            $charok = (chr($r) >= 'A') && (chr($r) <= 'Z') || (chr($r) >= 'a') && (chr($r) <= 'z') || (chr($r) >= '0') && (chr($r) <= '9');
            if (!$charok)
                continue;
            $pass .= chr($r);

            $p++;
        endwhile;

        if (strlen($pass) < $min):
            $pass = makePaystationSessionID($min, $max);
        endif;
        # is it's alread in the database, remake it
        return $pass;
    }

    private function _transactionVerification($paystationID, $transactionID, $merchantSession)
    {
        $transactionVerified = '';
        $lookupXML = $this->_quickLookup($paystationID, 'ms', $merchantSession);

        $p = xml_parser_create();
        xml_parse_into_struct($p, $lookupXML, $vals, $tags);
        xml_parser_free($p);
        foreach ($tags as $key => $val) {
            if ($key == "PAYSTATIONERRORCODE") {
                for ($i = 0; $i < count($val); $i++) {
                    $responseCode = $this->_parseCode($vals);
                    $transactionVerified = $responseCode;
                }

            }
        }

        return $transactionVerified;
    }

    function _quickLookup($pi, $type, $value)
    {
        $url = "https://payments.paystation.co.nz/lookup/"; //
        $params = "&pi=$pi&$type=$value";

        $authenticationKey = MODULE_PAYMENT_PAYSTATION_HMAC_KEY;
        $hmacWebserviceName = 'paystation';
        $pstn_HMACTimestamp = time();

        $hmacBody = pack('a*', $pstn_HMACTimestamp) . pack('a*', $hmacWebserviceName) . pack('a*', $params);
        $hmacHash = hash_hmac('sha512', $hmacBody, $authenticationKey);
        $hmacGetParams = '?pstn_HMACTimestamp=' . $pstn_HMACTimestamp . '&pstn_HMAC=' . $hmacHash;

        $url .= $hmacGetParams;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        if (isset($_SERVER['HTTP_USER_AGENT'])) {
            curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }


    private function _parseCode($mvalues)
    {
        $result = '';
        for ($i = 0; $i < count($mvalues); $i++) {
            if (!strcmp($mvalues[$i]["tag"], "PAYSTATIONERRORCODE") && isset($mvalues[$i]["value"])) {
                $result = $mvalues[$i]["value"];
            }
        }
        return $result;
    }


    function directTransaction($url, $params)
    {
        $defined_vars = get_defined_vars();

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_USERAGENT, $defined_vars['HTTP_USER_AGENT']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }
}
