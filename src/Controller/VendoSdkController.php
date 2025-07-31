<?php namespace Vankosoft\VendoSdkBundle\Controller;

use Vankosoft\PaymentBundle\Controller\AbstractCheckoutController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use Payum\Core\Request\GetHumanStatus;
use Vankosoft\PaymentBundle\Model\Interfaces\OrderInterface;
use Vankosoft\VendoSdkBundle\Payum\Api as VendoSdkApi;

class VendoSdkController extends AbstractCheckoutController
{
    public function prepareAction( Request $request ): Response
    {
        $cart           = $this->orderFactory->getShoppingCart();
        $formPost       = $request->request->all( 'credit_card_form' );
        $requestDetails = [
            'ip'        => $request->getClientIp(),
            'browser'   => $request->headers->get( 'User-Agent' ),
        ];
        $payment        = $this->preparePayment( $cart, $formPost, $requestDetails );
        
        $captureToken   = $this->payum->getTokenFactory()->createCaptureToken(
            $cart->getPaymentMethod()->getGateway()->getGatewayName(),
            $payment,
            'vs_vendo_sdk_done'
        );
        
        return $this->redirect( $captureToken->getTargetUrl() );
    }
    
    /*
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
     */
    public function doneAction( Request $request ): Response
    {
        $token      = $this->payum->getHttpRequestVerifier()->verify( $request );
        
        // you can invalidate the token. The url could not be requested any more.
        $this->payum->getHttpRequestVerifier()->invalidate( $token );
        
        $gateway    = $this->payum->getGateway( $token->getGatewayName() );
        $gateway->execute( $paymentStatus = new GetHumanStatus( $token ) );
        
        // using shortcut
        if ( $paymentStatus->isCaptured() || $paymentStatus->isAuthorized() || $paymentStatus->isPending() ) {
            // success
            return $this->paymentSuccess( $request, $paymentStatus );
        }
        
        // using shortcut
        if ( $paymentStatus->isFailed() || $paymentStatus->isCanceled() ) {
            // failure
            return $this->paymentFailed( $request, $paymentStatus );
        }
        echo '<pre>'; var_dump( $paymentStatus->getValue() ); die;
    }
    
    protected function preparePayment( OrderInterface $cart, array $formPost, array $requestDetails )
    {
        $storage = $this->payum->getStorage( $this->paymentClass );
        $payment = $storage->create();
        
        $payment->setOrder( $cart );
        $payment->setNumber( uniqid() );
        $payment->setCurrencyCode( $cart->getCurrencyCode() );
        $payment->setRealAmount( $cart->getTotalAmount() ); // Need this for Real (Human Readable) Amount.
        $payment->setTotalAmount( $cart->getTotalAmount() ); // Amount must convert to at least 100 stotinka.
        $payment->setDescription( $cart->getDescription() );
        
        $user   = $this->securityBridge->getUser();
        $payment->setClientId( $user ? $user->getId() : 'UNREGISTERED_USER' );
        $payment->setClientEmail( $user ? $user->getEmail() : 'UNREGISTERED_USER' );
        
        // Payment Details
        $paymentDetails   = $this->preparePaymentDetails( $cart, $formPost );
        $paymentDetails['local']['order'] = $this->prepareOrderDetails( $cart );
        $paymentDetails['local']['customer'] = $this->prepareCustomerDetails();
        $paymentDetails['local']['client_request'] = $requestDetails;
        
        $payment->setDetails( $paymentDetails );
        
        $this->doctrine->getManager()->persist( $cart );
        $this->doctrine->getManager()->flush();
        $storage->update( $payment );
        
        return $payment;
    }
    
    protected function preparePaymentDetails( OrderInterface $cart, array $formPost ): array
    {
        $paymentDetails   = [
            'local' => [
                'save_card' => true,
            ]
        ];
        
        if ( ! empty( $formPost ) ) {
            $paymentDetails['local']['credit_card'] = $formPost;
        }
        
        $subscriptions  = $cart->getSubscriptions();
        $hasPricingPlan = ! empty( $subscriptions );
        
        if ( $hasPricingPlan ) {
            $gateway        = $cart->getPaymentMethod()->getGateway();
            $pricingPlan    = $subscriptions[0]->getPricingPlan();
            $gtAttributes   = $pricingPlan->getGatewayAttributes();
            
            if (
                $this->vsPayment->isGatewaySupportRecurring( $gateway ) &&
                $cart->hasRecurringPayment() &&
                \array_key_exists( VendoSdkApi::PRICING_PLAN_ATTRIBUTE_KEY, $gtAttributes )
            ) {
                // Subscribing a customer to a plan
                $paymentDetails['local']['customer']['plan'] = $gtAttributes[VendoSdkApi::PRICING_PLAN_ATTRIBUTE_KEY];
            }
        }
        
        return $paymentDetails;
    }
    
    protected function prepareOrderDetails( OrderInterface $cart ): array
    {
        $orderDetails   = [
            'id'      => $cart->getId(),
            'description'   => $cart->getDescription(),
            'items'         => [],
        ];
        
        foreach ( $cart->getItems() as $item ) {
            $orderDetails['items'][] = [
                'id'    => $item->getId(),
                'desc'  => $cart->getDescription(),
                'price' => $item->getPrice(),
                'qty'   => $item->getQty(),
            ];
        }
        
        return $orderDetails;
    }
    
    protected function prepareCustomerDetails(): array
    {
        $user   = $this->securityBridge->getUser();
        $customerDetails = [
            'first_name'    => $user->getInfo()->getFirstName(),
            'last_name'     => $user->getInfo()->getLastName(),
            'email'         => $user->getEmail(),
            'language_code' => \explode( '_', $user->getPreferedLocale() )[0],
            'country_code'  => \explode( '_', $user->getPreferedLocale() )[1],
        ];
        
        return $customerDetails;
    }
}