<?php namespace Vankosoft\VendoSdkBundle\Controller;

use Vankosoft\PaymentBundle\Controller\AbstractCheckoutController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use Vankosoft\PaymentBundle\Model\Interfaces\OrderInterface;
use Vankosoft\VendoSdkBundle\Payum\Api as VendoSdkApi;

class VendoSdkController extends AbstractCheckoutController
{
    public function prepareAction( Request $request ): Response
    {
        $cart           = $this->orderFactory->getShoppingCart();
        $payment        = $this->preparePayment( $cart );
        
        $captureToken   = $this->payum->getTokenFactory()->createCaptureToken(
            $cart->getPaymentMethod()->getGateway()->getGatewayName(),
            $payment,
            'vs_vendo_sdk_done'
        );
        
        $captureUrl = base64_encode( $captureToken->getTargetUrl() );
        return $this->redirect( $this->generateUrl( 'vs_payment_show_credit_card_form', ['formAction' => $captureUrl] ) );
        
        //return $this->redirect( $captureToken->getTargetUrl() );
    }
    
    protected function preparePayment( OrderInterface $cart )
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
        $paymentDetails   = $this->preparePaymentDetails( $cart );
        $payment->setDetails( $paymentDetails );
        
        $this->doctrine->getManager()->persist( $cart );
        $this->doctrine->getManager()->flush();
        $storage->update( $payment );
        
        return $payment;
    }
    
    protected function preparePaymentDetails( OrderInterface $cart ): array
    {
        $paymentDetails   = [
            'local' => [
                'save_card' => true,
            ]
        ];
        
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
}