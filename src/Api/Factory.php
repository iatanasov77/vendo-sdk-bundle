<?php namespace Vankosoft\VendoSdkBundle\Api;

use VendoSdk\S2S\Request\Payment;
use Vankosoft\VendoSdkBundle\Payum\Keys;

final class Factory
{
    /** @var Keys */
    private $apiKeys;

    public function __construct( Keys $apiKeys )
    {
        $this->apiKeys  = $apiKeys;
    }
    
    public function preAuthorizeCreditCard()
    {
        try {
            $creditCardPayment = new Payment();
            $creditCardPayment->setApiSecret( $this->apiKeys->getApiSecret() );
            $creditCardPayment->setMerchantId( $this->apiKeys->getMerchantId() );//Your Vendo Merchant ID
            $creditCardPayment->setSiteId( $this->apiKeys->getSiteId() );//Your Vendo Site ID
            $creditCardPayment->setIsTest( $this->apiKeys->getIsTest() );
            
            //Set this flag to true when you do not want to capture the transaction amount immediately, but only validate the
            // payment details and block (reserve) the amount. The capture of a preauth-only transaction can be performed with
            // the CapturePayment class.
            $creditCardPayment->setPreAuthOnly( true );
            
            $creditCardPayment->setAmount(8.00);
            $creditCardPayment->setCurrency(\VendoSdk\Vendo::CURRENCY_USD);
            
            $externalRef = new \VendoSdk\S2S\Request\Details\ExternalReferences();
            $externalRef->setTransactionReference('your_tx_reference_999');
            $creditCardPayment->setExternalReferences($externalRef);
            
            /**
             * Add items to your request, you can add one or more
             */
            $cartItem = new \VendoSdk\S2S\Request\Details\Item();
            $cartItem->setId(123);//set your product id
            $cartItem->setDescription('Registration fee');//your product description
            $cartItem->setPrice(8.00);
            $cartItem->setQuantity(1);
            $creditCardPayment->addItem($cartItem);
            
            /**
             * Provide the credit card details that you collected from the user
             */
            $ccDetails = new \VendoSdk\S2S\Request\Details\PaymentMethod\CreditCard();
            $ccDetails->setNameOnCard('John Doe');
            $ccDetails->setCardNumber('4111111111111111');//this is a test card number, it will only work for test transactions
            $ccDetails->setExpirationMonth('05');
            $ccDetails->setExpirationYear('2029');
            $ccDetails->setCvv(123);//do not store nor log the CVV
            $creditCardPayment->setPaymentDetails($ccDetails);
            
            /**
             * Customer details
             */
            $customer = new \VendoSdk\S2S\Request\Details\Customer();
            $customer->setFirstName('John');
            $customer->setLastName('Doe');
            $customer->setEmail('john.doe.test@thisisatest.test');
            $customer->setLanguageCode('en');
            $customer->setCountryCode('US');
            $creditCardPayment->setCustomerDetails($customer);
            
            /**
             * Shipping details. This is required.
             */
            $shippingAddress = new \VendoSdk\S2S\Request\Details\ShippingAddress();
            $shippingAddress->setFirstName($customer->getFirstName());
            $shippingAddress->setLastName($customer->getLastName());
            $shippingAddress->setAddress($customer->getAddress());
            $shippingAddress->setCountryCode($customer->getCountryCode());
            $shippingAddress->setCity($customer->getCity());
            $shippingAddress->setState($customer->getState());
            $shippingAddress->setPostalCode($customer->getPostalCode());
            $shippingAddress->setPhone($customer->getPhone());
            //If you're selling digital content then you are allowed to use dummy details like the ones below
            $shippingAddress->setAddress('123 Example Street');
            $shippingAddress->setCity('Miami');
            $shippingAddress->setState('FL');
            $shippingAddress->setPostalCode('33000');
            $shippingAddress->setPhone('1000000000');
            $creditCardPayment->setShippingAddress($shippingAddress);
            
            /**
             * User request details
             */
            $request = new \VendoSdk\S2S\Request\Details\ClientRequest();
            $request->setIpAddress($_SERVER['REMOTE_ADDR'] ?: '127.0.0.1');//you must pass a valid IPv4 address
            $request->setBrowserUserAgent($_SERVER['HTTP_USER_AGENT'] ?: null);
            $creditCardPayment->setRequestDetails($request);
            
            $response = $creditCardPayment->postRequest();
            
            echo "\n\nRESULT BELOW\n";
            if ($response->getStatus() == \VendoSdk\Vendo::S2S_STATUS_OK) {
                echo "The transactions was successfully processed. Vendo's Transaction ID is: " . $response->getTransactionDetails()->getId();
                echo "\n**IMPORTANT:** You must save the Vendo Transaction ID if you need to capture the payment later.";
                echo "\nThe credit card payment Auth Code is: " . $response->getCreditCardPaymentResult()->getAuthCode();
                echo "\nThe Payment Details Token is: ". $response->getPaymentToken();
                echo "\nYou must save the payment details token if you need or want to process future recurring billing or one-clicks\n";
                echo "\nThis is your transaction reference (the one you set it in the request): " . $response->getExternalReferences()->getTransactionReference();
            } elseif ($response->getStatus() == \VendoSdk\Vendo::S2S_STATUS_NOT_OK) {
                echo "The transaction failed.";
                echo "\nError message: " . $response->getErrorMessage();
                echo "\nError code: " . $response->getErrorCode();
            } elseif ($response->getStatus() == \VendoSdk\Vendo::S2S_STATUS_VERIFICATION_REQUIRED) {
                echo "The transaction must be verified";
                echo "\nYou MUST :";
                echo "\n   1. Save the verificationId: " . $response->getResultDetails()->getVerificationId();
                echo "\n   2. Redirect the user to the verification URL: " . $response->getResultDetails()->getVerificationUrl();
                echo "\nthe user will verify his payment details, then he will be redirected to the Success URL that's configured in your account at Vendo's back office.";
                echo "\nwhen the user comes back you need to post the request to vendo again, please call credit_card_3ds_verifiction example.";
            }
            echo "\n\n\n";
            
            
        } catch (\VendoSdk\Exception $exception) {
            die ('An error occurred when processing your API request. Error message: ' . $exception->getMessage());
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
            die ('An error occurred when processing the HTTP request. Error message: ' . $e->getMessage());
        }
    }
}