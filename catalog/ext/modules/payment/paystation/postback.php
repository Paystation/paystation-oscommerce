<?php
chdir('../../../../');
include('includes/application_top.php');

if (MODULE_PAYMENT_PAYSTATION_POSTBACK == 'No') {
    echo "Postback not enabled in Paystation module for Oscommerce - post response ignored";
    exit();
}

$xml = file_get_contents('php://input');
$xml = simplexml_load_string($xml);

if (!empty($xml)) {
    $errorCode = $xml->ec;
    $errorMessage = $xml->em;
    $transactionId = $xml->ti;
    $cardType = $xml->ct;
    $merchantReference = $xml->merchant_ref;
    $testMode = $xml->tm;
    $merchantSession = $xml->MerchantSession;
    $usedAcquirerMerchantId = $xml->UsedAcquirerMerchantID;
    $amount = $xml->PurchaseAmount; // Note this is in cents
    $transactionTime = $xml->TransactionTime;
    $requestIp = $xml->RequestIP;

    $message = "Error Code: " . $errorCode . "<br/>";
    $message .= "Error Message: " . $errorMessage . "<br/>";
    $message .= "Transaction ID: " . $transactionId . "<br/>";
    $message .= "Card Type: " . $cardType . "<br/>";
    $message .= "Merchant Reference: " . $merchantReference . "<br/>";
    $message .= "Test Mode: " . $testMode . "<br/>";
    $message .= "Merchant Session: " . $merchantSession . "<br/>";
    $message .= "Merchant ID: " . $usedAcquirerMerchantId . "<br/>";
    $message .= "Amount: " . $amount . " (cents)<br/>";
    $message .= "Transaction Time: " . $transactionTime . "<br/>";
    $message .= "IP: " . $requestIp . "<br/>";

    $ti = $transactionId;
    $ti = $ti->__toString();

    $_SESSION['postback_process'] = true;
    $_SESSION['postback_errorCode'] = (int)($errorCode->__toString());
    $_SESSION['pstn_ti'] = $ti;

    if (!transaction_already_processed($ti)) {
        $sql = "select * from paystation_session where pstn_ms='$merchantSession'";

        $result = tep_db_query($sql);
        $row = tep_db_fetch_array($result);
        $_SESSION = unserialize($row['session_data']);
        $_SESSION['postback_process'] = true;
        $_SESSION['postback_errorCode'] = (int)($errorCode->__toString());
        $_SESSION['pstn_ti'] = $ti;
        if ($row['transaction_mode'] != MODULE_PAYMENT_PAYSTATION_TESTMODE) exit();
        tep_redirect(tep_href_link(FILENAME_CHECKOUT_PROCESS, '', 'SSL', true, false));

    }
}

exit();

function transaction_already_processed($pstn_ti)
{
    global $_POST, $_GET, $insert_id, $db;
    $db_query = tep_db_query("select pstn_ti from  " . TABLE_ORDERS . " where pstn_ti = '$pstn_ti'");
    $count = tep_db_num_rows($db_query);
    return ($count >= 1);
}
