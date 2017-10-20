<?php
include_once('OAuth.php');

// Register a merchant account on demo.pesapal.com and use the merchant key for
// testing. When you are ready to go live make sure you change the key to the
// live account registered on www.pesapal.com
$consumer_key = 'YOUR_PESAPAL_MERCHANT_CONSUMER_KEY';

// Use the secret from your test account on demo.pesapal.com. When you are ready
// to go live make sure you  change the secret to the live account registered on
// www.pesapal.com
$consumer_secret = 'YOUR_PESAPAL_MERCHANT_CONSUMER_SECRET';

// Change to https://www.pesapal.com/api/QueryPaymentStatus' when you are ready
// to go live!
$statusrequestAPI = 'http://demo.pesapal.com/api/QueryPaymentStatus';
// $statusrequestAPI = 'https://www.pesapal.com/API/QueryPaymentStatus';

// Fetch parameters sent to you by PesaPal IPN call. PesaPal will call the URL
// you entered above with the below query parameters;
$pesapal_notification_type        = $_GET['pesapal_notification_type'];
$pesapal_transaction_tracking_id  = $_GET['pesapal_transaction_tracking_id'];
$pesapal_merchant_reference       = $_GET['pesapal_merchant_reference'];

if ($pesapal_notification_type == 'CHANGE' && $pesapal_transaction_tracking_id != '') {

    // Pesapal parameters
    $token = $params = NULL;

    $consumer = new OAuthConsumer($consumer_key, $consumer_secret);

    // Get transaction status
    $signature_method = new OAuthSignatureMethod_HMAC_SHA1();
    $request_status = OAuthRequest::from_consumer_and_token($consumer, $token, 'GET', $statusrequestAPI, $params);
    $request_status -> set_parameter('pesapal_merchant_reference', $pesapal_merchant_reference);
    $request_status -> set_parameter('pesapal_transaction_tracking_id',$pesapal_transaction_tracking_id);
    $request_status -> sign_request($signature_method, $consumer, $token);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $request_status);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HEADER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    if (defined('CURL_PROXY_REQUIRED')) {
        if (CURL_PROXY_REQUIRED == 'True') {

            $proxy_tunnel_flag = (defined('CURL_PROXY_TUNNEL_FLAG') && strtoupper(CURL_PROXY_TUNNEL_FLAG) == 'FALSE') ? false : true;
            curl_setopt ($ch, CURLOPT_HTTPPROXYTUNNEL, $proxy_tunnel_flag);
            curl_setopt ($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
            curl_setopt ($ch, CURLOPT_PROXY, CURL_PROXY_SERVER_DETAILS);
        }
    }

   $response = curl_exec($ch);

   $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
   $raw_header  = substr($response, 0, $header_size - 4);
   $headerArray = explode('\r\n\r\n', $raw_header);
   $header      = $headerArray[count($headerArray) - 1];

   // Transaction status
   $elements = preg_split('/=/',substr($response, $header_size));
   $status = $elements[1]; // PENDING, COMPLETED or FAILED

   curl_close ($ch);

    // At this point $status may have value of PENDING, COMPLETED or FAILED.
    // Please note that (as mentioned above) PesaPal will call the URL you
    // entered above with the 3 query parameters. You must respond to the HTTP
    // request with the same data that you received from PesaPal. PesaPal will
    // retry a number of times, if they don't receive the correct response (for
    // example due to network failure). So if successful, we update our DB ...
    // if it FAILED, we can cancel out transaction in our DB and notify user
   if ($status == 'COMPLETED') {

        // Transaction confirmed that it's successful so we update the DB

        // UPDATE YOUR DB TABLE WITH NEW STATUS FOR TRANSACTION WITH
        // pesapal_transaction_tracking_id $pesapal_transaction_tracking_id

        $db_update_successful = IF_YOUR_DB_UPDATE_IS_SUCCESSFUL_THIS_IS_UPTO_YOU_TO_DETERMINE;

        if ($db_update_successful) {

            // Send Pesapal response only if we were able to update DB
            $resp = 'pesapal_notification_type='.$pesapal_notification_type;
            $resp .= '&pesapal_transaction_tracking_id='.$pesapal_transaction_tracking_id;
            $resp .= '&pesapal_merchant_reference='.$pesapal_merchant_reference;
            ob_start();
            echo $resp;
            ob_flush();
       }
    }
    elseif ($status == 'FAILED') {

        // Transaction confirmed that it's NOT successful so we update the DB

        // UPDATE YOUR DB TABLE AND CANCEL TRANSACTION OR MARK AS FAILED, POSSIBLY NOTIFY USER
        // pesapal_transaction_tracking_id $pesapal_transaction_tracking_id

        $resp = 'pesapal_notification_type='.$pesapal_notification_type;
        $resp .= '&pesapal_transaction_tracking_id='.$pesapal_transaction_tracking_id;
        $resp .= '&pesapal_merchant_reference='.$pesapal_merchant_reference;
        ob_start();
        echo $resp;
        ob_flush();
    }

    // If PENDING ... we do nothing ... Pesapal will keep sending us the IPN but
    // at least we know it's pending

    exit;
}
?>
