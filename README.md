#Pesapal PHP API Reference (Unofficial)#

[PesaPal](pesapal.com) provides a simple, safe and secure way for individuals
and businesses to make and accept payments in Africa. PesaPal payments work on
the Internet and directly on the handset.

PesaPal Escrow features protect buyers and sellers by giving them a chance to
verify the quality of goods and services before releasing payments.

PesaPal partners with Banks, Mobile Network Operators and Credit Card companies
to give consumers as many payment options as possible. For businesses, they
offer timely settlement of any payments to their bank account of choice.

_Ps: List of releases for download can be found [github.com/itsmrwave/pesapal-php/releases][1]_

_Ps 2: Purpose of this is to make it easier for other people willing to
integrate to Pesapal ... initial code posted on the official site had some minor
bugs. I have made these changes therein, [documented them on the Pesapal Forums
here](http://developer.pesapal.com/forum/2-pesapal-integration/455-unofficial-
pesapal-integration-reference) and shared my findings with the Pesapal team so
that they can ammend the documentation on their site._

***

1. [File Structure](#file-structure)
2. [How To Use](#how-to-use) - [Simplified](#simplified) & [Step-by-Step Walkthrough](#step-by-step-walkthrough)
3. [Testing Sandbox](#testing-sandbox)
4. [API Methods](#api-methods)
5. [Resources](#resources)

##File Structure##

```
/
│
├── assets/
│    └── merchant_ipn_settings.png
├── OAuth.php
├── README.md
├── pesapal-iframe.php
├── pesapal-ipn-listener.php
└── shopping-cart-form.html
```

##How To Use##

###Simplified###

1. [Include Pesapal php file](#1-include) into your project.
2. [Generate iFrame](#1-include) on your website using [this code _(sample)_](pesapal-iframe.php). There are [some necessary parameters required](#3-assign-form-details) to generate the form correctly which you need to provide. Please note that in [pesapal-iframe sample code](pesapal-iframe.php), an assumption is made that the data is passed in by a form ([see example shopping cart form](shopping-cart-form.php)) via the `$_POST` variable. You don't have to stick with this method, feel free to use whatever method suits you. What matters is that the data is eventually passed in.
3. Once the user correctly follows through with the chosen payment option, [Pesapal responds](#8-store-payment-by-the-user) with a id and reference to a callback URL of your choosing. You can then [add the transaction in your DB as a _pending_ transaction](#8-store-payment-by-the-user).
4. At this point, it's still not safe to assume that the payment was successful. Pesapal in the meantime will be processing the payment and once it's confirmed on their end, the will send you a notification via an API call on your chosen IPN Listener URL.
5. [Once you recieve data on the IPN](#9-listen-to-ipn-and-query-for-status), now you can query the payment status after which you can update the payment status to complete in your DB if successful.

###Step-by-Step Walkthrough###

####1. Include####

Include [OAuth class](OAuth.php) that helps in constructing the OAuth Request

```php
include_once('OAuth.php');
```

####2. Assign Variables####

1. `$token` and `$params` – null
2. `$consumer_key` – merchant key issued by PesaPal to the merchant
3. `$consumer_secret` – merchant secret issued by PesaPal to the merchant
4. `$signature_method` (leave as default) – `new OAuthSignatureMethod_HMAC_SHA1();`
5. `$iframelink` – the link that is passed to the iframe pointing to the PesaPal server

```php
// Pesapal parameters
$token = $params = null;

// PesaPal Sandbox is at http://demo.pesapal.com. Use this to test your
// developement and  when you are ready to go live change to
// https://www.pesapal.com

// Register a merchant account on demo.pesapal.com and use the merchant key for
// testing. When you are ready to go live make sure you change the key to the
// live account registered on www.pesapal.com
$consumer_key = 'YOUR_PESAPAL_MERCHANT_CONSUMER_KEY';

// Use the secret from your test account on demo.pesapal.com. When you are ready
// to go live make sure you  change the secret to the live account registered on
// www.pesapal.com
$consumer_secret = 'YOUR_PESAPAL_MERCHANT_CONSUMER_SECRET';

$signature_method = new OAuthSignatureMethod_HMAC_SHA1();

// Change this to https://www.pesapal.com/API/PostPesapalDirectOrderV4 when you
// are ready to go live
$iframelink = 'http://demo.pesapal.com/api/PostPesapalDirectOrderV4';
```

####3. Assign Form Details####

Assign form details passed to [pesapal-iframe.php](pesapal-iframe.php) from
[shopping-cart-form.php](shopping-cart-form.php) to the specified variables.

```php
// Get form details
$amount         = (integer) $_POST['amount'];
$desc           = $_POST['description'];
$type           = $_POST['type'];                       // default value = MERCHANT
$reference      = $_POST['reference'];                  // unique order id of the transaction, generated by merchant
$first_name     = $_POST['first_name'];                 // optional 
$last_name      = $_POST['last_name'];                  // optional
$email          = $_POST['email'];
$phonenumber    = '';                                   // ONE of email or phonenumber is required
```

####4. Define The Callback####

The callback is the full URL pointing to the page the iframe redirects to after
processing the order on pesapal.com. This page will handle the response from
Pesapal.

```php
$callback_url = 'http://www.YOURDOMAIN.com/pesapal_callback.php';
```

####5. Construct the POST XML####

The format is standard so no editing is required. Encode the variable using
htmlentities.

```php
$post_xml = '<?xml version="1.0" encoding="utf-8"?>';
$post_xml .= '<PesapalDirectOrderInfo ';
$post_xml .= 'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" ';
$post_xml .= 'xmlns:xsd="http://www.w3.org/2001/XMLSchema" ';
$post_xml .= 'Amount="'.$amount.'" ';
$post_xml .= 'Description="'.$desc.'" ';
$post_xml .= 'Type="'.$type.'" ';
$post_xml .= 'Reference="'.$reference.'" ';
$post_xml .= 'FirstName="'.$first_name.'" ';
$post_xml .= 'LastName="'.$last_name.'" ';
$post_xml .= 'Email="'.$email.'" ';
$post_xml .= 'PhoneNumber="'.$phonenumber.'" ';
$post_xml .= 'xmlns="http://www.pesapal.com" />';
$post_xml = htmlentities($post_xml);
```

####6. Construct the OAuth Request URL####

Using the Oauth class that we included, construct the OAuth Request URL using
the parameters declared above (the format is standard so no editing is
required). Then post transaction to pesapal.

```php
$consumer = new OAuthConsumer($consumer_key, $consumer_secret);

// Construct the OAuth Request URL & post transaction to pesapal
$iframe_src = OAuthRequest::from_consumer_and_token($consumer, $token, 'GET', $iframelink, $params);
$iframe_src -> set_parameter('oauth_callback', $callback_url);
$iframe_src -> set_parameter('pesapal_request_data', $post_xml);
$iframe_src -> sign_request($signature_method, $consumer, $token);
```

####7. Construct the OAuth Request URL####

Pass in the `$iframe_src` as the iframe’s src to generate the Pesapal iFrame

```html
<iframe src="<?php echo $iframe_src; ?>" width="100%" height="800px"  scrolling="no" frameBorder="0">
    <p>Browser unable to load iFrame</p>
</iframe>
```

User can now use the iFrame to pay via Pesapal.

_Ps: When testing via demo.pesapal.com, mobile transactions done here do not
require you to send real money using your mobile phone! Use the [dummy-money
tool](http://demo.pesapal.com/mobilemoneytest) for your testing needs._

####8. Store Payment By The User###

Once the payment process has been completed by the user, PesaPal will redirect
to your site using the url you assigned to `$callback_url`, along with the
following query string parameters;

1. `$pesapal_merchant_reference` – this is the same as $reference (unique order id) that you posted to PesaPal
2. `$pesapal_transaction_tracking_id` – this is a unique id for the transaction on PesaPal that you can use to track the status of the transaction later

Your code in the Callback URL should store the
`$pesapal_transaction_tracking_id` in your database against the order. It's up
to you to do this in any way that suits you as per your application design.

```php
$reference = null;
$pesapal_tracking_id = null;

if(isset($_GET['pesapal_merchant_reference'])) {

    $reference = $_GET['pesapal_merchant_reference'];
}

if(isset($_GET['pesapal_transaction_tracking_id'])) {

    $pesapal_tracking_id = $_GET['pesapal_transaction_tracking_id'];
}

// +++ WRITE CODE TO STORE THE DATA FETCHED HERE IN YOUR DB +++
// Save $pesapal_tracking_id in your database against the order with orderid = $reference
```

####9. Listen to IPN and Query for Status####

So, once you have posted a transaction to PesaPal, how do you find if the
payment has competed? Or failed?

PesaPal can send you a notification every time a status change has happened on
one of your transactions following which you can query for the status. This is
referred to as IPN or Instant Payment Notifications. Once a transaction has been
posted to PesaPal, you can listen for Instant Payment Notifications on a URL on
your site.

This step can be broken down to 3 minor steps for clarity sake (details will
follow thereafter);

1. Setup IPN - do this from the Pesapal Merchant Dashboard (My Account > IPN Settings). Set your website domain and IPN Listener URL
2. Get data sent to your IPN -  data is sent via GET query parameters
3. Query for the status - you do this using the _QueryPaymentStatus_ API method

PesaPal will call the URL you entered above with the following query parameters;

```
pesapal_notification_type=CHANGE
pesapal_transaction_tracking_id=<the unique tracking id of the transaction>
pesapal_merchant_reference=<the merchant reference>
```

You must respond to the HTTP request with the same data that you received from
PesaPal. For example:

```
pesapal_notification_type=CHANGE&pesapal_transaction_tracking_id=XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXX&pesapal_merchant_reference=12345
```

PesaPal will retry a number of times, just in case they don't receive the
correct response for one reason or the other (e.g. due to network failure). And
since you now have these two values, you can query PesaPal over a secure SSL
connection (that's the https in https://www.pesapal.com) for the status of the
transaction using the _QueryPaymentStatus_ API method.

To call the _QueryPaymentStatus_ API method, you need to package the request
using OAuth, similar to [when you posted a transaction to PesaPal](#6-construct-the-oauth-request-url).

_Ps: Find [sample code for the IPN-Listener here](pesapal-ipn-listener.php),
commented for extra guidance._

_Ps 2: At this point you may be wondering why PesaPal didn't send you the status
of the transaction? They only send you the  `$pesapal_transaction_tracking_id`
and the `$pesapal_merchant_reference` for security reasons._

_Ps 3: Find screenshot of Merchant IPN Settings panel below;_

![Merchant IPN Settings Image](assets/merchant_ipn_settings.png "Merchant IPN (Instant Payment Notifications) Settings Screenshot")

##Testing Sandbox##

On [demo.pesapal.com](http://demo.pesapal.com/), you can take PesaPal for a test
drive. With the sandbox you can test your integration before you take it live.
You can create test merchant and buyer accounts, post transactions, make
payments using our mobile money test module, query for payment status, etc.
Please note that ___no real money is used___ on the demo site and you can test
cash payment using the [dummy-money tool](http://demo.pesapal.com/mobilemoneytest).

When you have confirmed that the integration is working as expected, switch to
the live pesapal site, simply by updating the url and registering a live
merchant account.

_Ps: Email and SMS notifications are not available in sandbox._

_Ps 2: The [live Pesapal account (on
pesapal.com)](https://www.pesapal.com/account/register) & the [demo Pesapal
account (on demo.pesapal.com)](http://demo.pesapal.com/account/register) are
completely different and do not share consumer_key, consumer_secret or URLs of
Pesapal API endpoints. Links to create accounts on each are
[here](https://www.pesapal.com/account/register) &
[here](http://demo.pesapal.com/account/register) respectively. You will then be
able to find the consumer key & secret in the dashboard of each account once you
log in._

_Ps 3: Live API endpoints are HTTPS but the demo api endpoints are HTTP._

##API Methods##

1. __PostPesapalDirectOrderV4__ - Use this to post a transaction to PesaPal. PesaPal will present the user with a page which contains the available payment options and will redirect to your site once the user has completed the payment process. A tracking id will be returned as a query parameter – this can be used subsequently to track the payment status on pesapal for this transaction.
2. __QueryPaymentStatus__ - Use this to query the status of the transaction. When a transaction is posted to PesaPal, it may be in a PENDING, COMPLETED or FAILED state. If the transaction is PENDING, the payment may complete or fail at a later stage. Both the unique order id generated by your system and the pesapal tracking id are required as input parameters.
3. __QueryPaymentStatusByMerchantRef__ - Same as _QueryPaymentStatus_, but only the unique order id generated by your system is required as the input parameter.
4. __QueryPaymentDetails__ - Same as _QueryPaymentStatus_, but additional information is returned.

##Resources##

* [PesaPal Website](https://www.pesapal.com/) _(official)_
* [PesaPal Testing Sandbox](http://demo.pesapal.com/) _(official)_
* [Pesapal Developer Community](http://developer.pesapal.com/) _(official)_
* [Pesapal API Reference](http://developer.pesapal.com/how-to-integrate/api-reference) _(official)_

[1]: https://github.com/itsmrwave/pesapal-php/releases
