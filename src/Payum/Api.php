<?php namespace Vankosoft\VendoSdkBundle\Payum;

use Payum\Core\Bridge\Spl\ArrayObject;
use VendoSdk\S2S\Request\Payment;
use VendoSdk\S2S\Request\Details\ExternalReferences;
use VendoSdk\S2S\Request\Details\Item;
use VendoSdk\S2S\Request\Details\PaymentMethod\CreditCard;
use VendoSdk\S2S\Request\Details\Customer;
use VendoSdk\S2S\Request\Details\ClientRequest;
use VendoSdk\S2S\Response\PaymentResponse;

class Api
{
    const PRICING_PLAN_ATTRIBUTE_KEY    = 'vendo_plan_id';
    
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
    
    public function doPreAuthorizeCreditCard( array $model, array $clientRequest ): PaymentResponse
    {
        $creditCardPayment = $this->createCreditCardPayment( $model['amount'], $model['currency'] );
        
        //Set this flag to true when you do not want to capture the transaction amount immediately, but only validate the
        // payment details and block (reserve) the amount. The capture of a preauth-only transaction can be performed with
        // the CapturePayment class.
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
        
        /**
         * User request details
         */
        $request = new ClientRequest();
        $request->setIpAddress( $clientRequest['ip'] ?: '127.0.0.1' ); //you must pass a valid IPv4 address
        $request->setBrowserUserAgent( $clientRequest['browser'] ?: null );
        $creditCardPayment->setRequestDetails( $request );
        
        return $creditCardPayment->postRequest();
    }
    
    protected function createCreditCardPayment( float $amount, string $currency ): Payment
    {
        $creditCardPayment = new Payment();
        $creditCardPayment->setApiSecret( $this->options['api_secret'] );
        $creditCardPayment->setMerchantId( $this->options['merchant_id'] );//Your Vendo Merchant ID
        $creditCardPayment->setSiteId( $this->options['site_id'] );//Your Vendo Site ID
        $creditCardPayment->setIsTest( $this->options['sandbox'] );
        
        $creditCardPayment->setAmount( $amount );
        $creditCardPayment->setCurrency( $currency ); // \VendoSdk\Vendo::CURRENCY_EUR
        
        return $creditCardPayment;
    }
    
    protected function createCartItem( array $itemFields ): Item
    {
        $cartItem = new Item();
        $cartItem->setId( $itemFields['id'] ); //set your product id
        $cartItem->setDescription( $itemFields['desc'] ); //your product description
        $cartItem->setPrice( $itemFields['price'] );
        $cartItem->setQuantity( $itemFields['qty'] );
        
        return $cartItem;
    }
    
    protected function createCreditCard( array $ccFields ): CreditCard
    {
        $ccDetails = new CreditCard();
        $ccDetails->setNameOnCard( $ccFields['name'] );
        //$ccDetails->setCardNumber( '4111111111111111' ); //this is a test card number, it will only work for test transactions
        $ccDetails->setCardNumber( $ccFields['number'] );
        $ccDetails->setExpirationMonth( $ccFields['ccmonth'] );
        $ccDetails->setExpirationYear( $ccFields['ccyear'] );
        $ccDetails->setCvv( \intval( $ccFields['cvv'] ) ); //do not store nor log the CVV
        
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