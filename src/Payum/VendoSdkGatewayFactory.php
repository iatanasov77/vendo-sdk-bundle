<?php namespace Vankosoft\VendoSdkBundle\Payum;

use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\GatewayFactory;

use Vankosoft\VendoSdkBundle\Payum\Action\AuthorizeAction;
use Vankosoft\VendoSdkBundle\Payum\Action\CancelAction;
use Vankosoft\VendoSdkBundle\Payum\Action\ConvertPaymentAction;
use Vankosoft\VendoSdkBundle\Payum\Action\CaptureAction;
use Vankosoft\VendoSdkBundle\Payum\Action\NotifyAction;
use Vankosoft\VendoSdkBundle\Payum\Action\RefundAction;
use Vankosoft\VendoSdkBundle\Payum\Action\StatusAction;

use Vankosoft\VendoSdkBundle\Payum\Action\Api\CreditCardPaymentAction;
use Vankosoft\VendoSdkBundle\Payum\Action\Api\CapturePaymentAction;
use Vankosoft\VendoSdkBundle\Payum\Action\Api\RecurringPaymentAction;

class VendoSdkGatewayFactory extends GatewayFactory
{
    /**
     * {@inheritDoc}
     */
    protected function populateConfig( ArrayObject $config )
    {
        /*
         * I dont know which actions are needed for now
         */
        $config->defaults([
            'payum.factory_name'                => 'vendo_sdk',
            'payum.factory_title'               => 'Vendo SDK',
            
            'payum.action.capture'              => new CaptureAction(),
            'payum.action.authorize'            => new AuthorizeAction(),
            'payum.action.refund'               => new RefundAction(),
            'payum.action.cancel'               => new CancelAction(),
            'payum.action.notify'               => new NotifyAction(),
            'payum.action.status'               => new StatusAction(),
            'payum.action.convert_payment'      => new ConvertPaymentAction(),
            
            'payum.action.credit_card_payment'  => new CreditCardPaymentAction(),
            'payum.action.capture_payment'      => new CapturePaymentAction(),
            'payum.action.recurring_payment'    => new RecurringPaymentAction(),
        ]);

        if ( false == $config['payum.api'] ) {
            $config['payum.default_options'] = [
                'sandbox'       => true,
                'merchant_id'   => '',
                'site_id'       => '',
                'api_secret'    => '',
            ];
            $config->defaults( $config['payum.default_options'] );
            $config['payum.required_options'] = ['merchant_id', 'site_id', 'api_secret'];

            $config['payum.api'] = function ( ArrayObject $config ) {
                $config->validateNotEmpty( $config['payum.required_options'] );

                return new Api( (array)$config );
            };
        }
    }
}
