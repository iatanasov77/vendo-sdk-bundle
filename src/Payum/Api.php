<?php namespace Vankosoft\VendoSdkBundle\Payum;

use Payum\Core\Bridge\Spl\ArrayObject;

use VendoSdk\Vendo;
use VendoSdk\S2S\Request\Payment;
use VendoSdk\S2S\Request\CapturePayment;

use VendoSdk\S2S\Request\Details\ExternalReferences;
use VendoSdk\S2S\Request\Details\Item;
use VendoSdk\S2S\Request\Details\PaymentMethod\CreditCard;
use VendoSdk\S2S\Request\Details\PaymentMethod\Token;
use VendoSdk\S2S\Request\Details\Customer;
use VendoSdk\S2S\Request\Details\ClientRequest;
use VendoSdk\S2S\Request\Details\SubscriptionSchedule;

use VendoSdk\S2S\Response\PaymentResponse;
use VendoSdk\S2S\Response\CaptureResponse;

class Api
{
    const PRICING_PLAN_ATTRIBUTE_KEY    = 'vendo_plan_id';
    const PAYMENT_TOKEN                 = 'vendo_payment_token';
    
    const STATUS_ERROR                  = 'vendo_error';
    
    /** @var mixed */
    protected $options = [];

    /**
     * Api constructor.
     * @param array $options
     */
    public function __construct( array $options )
    {
        $options = ArrayObject::ensureArrayObject( $options );
        $options->defaults( $this->options );
        $this->options = $options;
    }
    
    public function getStatusMessage( mixed $response ): string
    {
        $message = '';
        switch ( $response->getStatus() ) {
            case Vendo::S2S_STATUS_OK:
                $message = "The transactions was successfully processed. Vendo's Transaction ID is: {$response->getTransactionDetails()->getId()}";
                $message .= "\n**IMPORTANT:** You must save the Vendo Transaction ID if you need to capture the payment later.";
                $message .= "\nThe credit card payment Auth Code is: {$response->getCreditCardPaymentResult()->getAuthCode()}";
                $message .= "\nThe Payment Details Token is: {$response->getPaymentToken()}";
                $message .= "\nYou must save the payment details token if you need or want to process future recurring billing or one-clicks\n";
                $message .= "\nThis is your transaction reference (the one you set it in the request): {$response->getExternalReferences()->getTransactionReference()}";
                break;
            case Vendo::S2S_STATUS_NOT_OK:
                $message = "The transaction failed.";
                $message .= "\nError message: {$response->getErrorMessage()}";
                $message .= "\nError code: {$response->getErrorCode()}";
                break;
            case Vendo::S2S_STATUS_VERIFICATION_REQUIRED:
                $message = "The transaction must be verified";
                $message .= "\nYou MUST :";
                $message .= "\n   1. Save the verificationId: {$response->getResultDetails()->getVerificationId()}";
                $message .= "\n   2. Redirect the user to the verification URL: {$response->getResultDetails()->getVerificationUrl()}";
                $message .= "\nthe user will verify his payment details, then he will be redirected to the Success URL that's configured in your account at Vendo's back office.";
                $message .= "\nwhen the user comes back you need to post the request to vendo again, please call credit_card_3ds_verifiction example.";
                break;
        }
        
        return $message;
    }
    
    /**
     * Validate the payment details and block (reserve) the amount.
     * The capture of a preauth-only transaction can be performed with the doCapturePayment method.
     * 
     * @param array $model
     * @param array $clientRequest
     * @return PaymentResponse
     */
    public function doCreditCardPayment( array $model, array $clientRequest ): PaymentResponse
    {
        // echo '<pre>'; var_dump( $this->options['sandbox'] ); die;
        $creditCardPayment = $this->buildCreditCardPayment( $model );
        
        /**
         * User request details
         */
        $request = new ClientRequest();
        $request->setIpAddress( $clientRequest['ip'] ?: '127.0.0.1' ); // you must pass a valid IPv4 address
        $request->setBrowserUserAgent( $clientRequest['browser'] ?: null );
        $creditCardPayment->setRequestDetails( $request );
        
        return $creditCardPayment->postRequest();
    }
    
    public function doCreditCardSignup( array $model, array $clientRequest ): PaymentResponse
    {
        // echo '<pre>'; var_dump( $this->options['sandbox'] ); die;
        $creditCardSignup = $this->buildCreditCardPayment( $model );
        
        /**
         * Subscription Schedule
         */
        $schedule = new SubscriptionSchedule();
        $schedule->setRebillDuration( 30 ); // days
        $schedule->setRebillAmount( 2.34 ); // billing currency
        $creditCardSignup->setSubscriptionSchedule( $schedule );
        
        /**
         * User request details
         */
        $request = new ClientRequest();
        $request->setIpAddress( $clientRequest['ip'] ?: '127.0.0.1' ); // you must pass a valid IPv4 address
        $request->setBrowserUserAgent( $clientRequest['browser'] ?: null );
        $creditCardSignup->setRequestDetails( $request );
        
        return $creditCardSignup->postRequest();
    }
    
    public function doCapturePayment( int $transactionId ): CaptureResponse
    {
        $capture = new CapturePayment();
        $capture->setApiSecret( $this->options['api_secret'] );
        $capture->setMerchantId( $this->options['merchant_id'] ); // Your Vendo Merchant ID
        $capture->setIsTest( $this->options['sandbox'] );
        $capture->setTransactionId( $transactionId ); // The Vendo Transaction ID that you want to capture.
        
        return $capture->postRequest();
    }
    
    public function doRecurringPayment( array $model ): PaymentResponse
    {
        $tokenPayment = $this->createCreditCardPayment( $model['subscription_price'], $model['subscription_currency'] );
        
        // You must set the flag below to TRUE if you're processing a recurring billing transaction or if you initiated this
        // payment on behalf of your user.
        $tokenPayment->setIsMerchantInitiatedTransaction( false );
        
        $externalRef = new ExternalReferences();
        $externalRef->setTransactionReference( $model['subscription_id'] );
        $tokenPayment->setExternalReferences( $externalRef );
        
        /**
         * Add items to your request, you can add one or more
         */
        $cartItem = $this->createCartItem([
            'id'    => $model['plan_id'],
            'desc'  => $model['plan_description'],
            'price' => $model['subscription_price'],
            'qty'   => 1,
        ]);
        $tokenPayment->addItem( $cartItem );
        
        /**
         * Provide the token of the payment details that were used by this user for this site
         */
        $token = new Token();
        $token->setToken( $model[self::PAYMENT_TOKEN] ); // this is a dummy example, get it from your database or use a token from a previous test
        $tokenPayment->setPaymentDetails( $token );
        
        /**
         * User request details
         */
        $request = new ClientRequest();
        $request->setIpAddress( '127.0.0.1' ); // you must pass a valid IPv4 address
        $request->setBrowserUserAgent( null );
        $tokenPayment->setRequestDetails( $request );
        
        return $tokenPayment->postRequest();
    }
    
    protected function buildCreditCardPayment( array $model ): Payment
    {
        // echo '<pre>'; var_dump( $this->options['sandbox'] ); die;
        $creditCardPayment = $this->createCreditCardPayment( $model['amount'], $model['currency'] );
        $creditCardPayment->setPreAuthOnly( true );
        
        $externalRef = new ExternalReferences();
        $externalRef->setTransactionReference( $model['order']['id'] );
        $creditCardPayment->setExternalReferences( $externalRef );
        
        /**
         * Add items to your request, you can add one or more
         */
        foreach ( $model['order']['items'] as $itemFields ) {
            $cartItem = $this->createCartItem( $itemFields );
            $creditCardPayment->addItem( $cartItem );
        }
        
        /**
         * Provide the credit card details that you collected from the user
         */
        $ccDetails = $this->createCreditCard( $model['credit_card'] );
        $creditCardPayment->setPaymentDetails( $ccDetails );
        
        /**
         * Customer details
         */
        $customer = $this->createCustomer( $model['customer'] );
        $creditCardPayment->setCustomerDetails( $customer );
        
        return $creditCardPayment;
    }
    
    protected function createCreditCardPayment( float $amount, string $currency ): Payment
    {
        $creditCardPayment = new Payment();
        $creditCardPayment->setApiSecret( $this->options['api_secret'] );
        $creditCardPayment->setMerchantId( $this->options['merchant_id'] ); // Your Vendo Merchant ID
        $creditCardPayment->setSiteId( $this->options['site_id'] ); // Your Vendo Site ID
        $creditCardPayment->setIsTest( $this->options['sandbox'] );
        
        $creditCardPayment->setAmount( $amount );
        $creditCardPayment->setCurrency( $currency ); // \VendoSdk\Vendo::CURRENCY_EUR
        
        return $creditCardPayment;
    }
    
    protected function createCartItem( array $itemFields ): Item
    {
        $cartItem = new Item();
        $cartItem->setId( $itemFields['id'] ); // set your product id
        $cartItem->setDescription( $itemFields['desc'] ); // your product description
        $cartItem->setPrice( $itemFields['price'] );
        $cartItem->setQuantity( $itemFields['qty'] );
        
        return $cartItem;
    }
    
    protected function createCreditCard( array $ccFields ): CreditCard
    {
        $ccDetails = new CreditCard();
        $ccDetails->setNameOnCard( $ccFields['name'] );
        //$ccDetails->setCardNumber( '4111111111111111' ); // this is a test card number, it will only work for test transactions
        $ccDetails->setCardNumber( $ccFields['number'] );
        $ccDetails->setExpirationMonth( $ccFields['ccmonth'] );
        $ccDetails->setExpirationYear( $ccFields['ccyear'] );
        $ccDetails->setCvv( \intval( $ccFields['cvv'] ) ); // do not store nor log the CVV
        
        return $ccDetails;
    }
    
    protected function createCustomer( array $customerFields ): Customer
    {
        $customer = new Customer();
        $customer->setFirstName( $customerFields['first_name'] );
        $customer->setLastName( $customerFields['last_name'] );
        $customer->setEmail( $customerFields['email'] );
        $customer->setLanguageCode( $customerFields['language_code'] );
        $customer->setCountryCode( $customerFields['country_code'] );
        
        return $customer;
    }
}
