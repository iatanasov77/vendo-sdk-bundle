<?php namespace Vankosoft\VendoSdkBundle\Payum\Action\Api;

use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Action\ActionInterface;
use Payum\Core\ApiAwareInterface;
use Payum\Core\ApiAwareTrait;
use Payum\Core\GatewayAwareTrait;

use GuzzleHttp\Exception\GuzzleException;
use VendoSdk\Exception as VendoSdkException;
use VendoSdk\Vendo;

use Vankosoft\VendoSdkBundle\Payum\Api;
use Vankosoft\VendoSdkBundle\Payum\Request\Api\RecurringPayment;

class RecurringPaymentAction implements ActionInterface, ApiAwareInterface
{
    use GatewayAwareTrait;
    use ApiAwareTrait;
    
    public function __construct()
    {
        $this->apiClass = Api::class;
    }
    
    /**
     * {@inheritDoc}
     */
    public function execute( $request )
    {
        /** @var $request CreateTransaction */
        RequestNotSupportedException::assertSupports( $this, $request );
        
        $model = ArrayObject::ensureArrayObject( $request->getModel() );
        
        $model['subscription_id'] = $model['local']['subscription_id'];
        $model['subscription_price'] = $model['local']['subscription_price'];
        $model['subscription_currency'] = $model['local']['subscription_currency'];
        $model['plan_id'] = $model['local']['plan_id'];
        $model['plan_description'] = $model['local']['plan_description'];
        $model[Api::PAYMENT_TOKEN] = $model['local'][Api::PAYMENT_TOKEN];
        
        try {
            $response = $this->api->doRecurringPayment( $model->toUnsafeArrayWithoutLocal() );
            
            $model['status'] = $response->getStatus();
            $model['status_message'] = $this->api->getStatusMessage( $response );
            
            if ( $model['status'] == Vendo::S2S_STATUS_OK ) {
                
            }
        } catch ( VendoSdkException $e ) {
            die ( 'An error occurred when processing your API request. Error message: ' . $e->getMessage() );
        } catch ( GuzzleException $e ) {
            die ( 'An error occurred when processing the HTTP request. Error message: ' . $e->getMessage() );
        }
    }
    
    /**
     * {@inheritDoc}
     */
    public function supports( $request )
    {
        return
            $request instanceof RecurringPayment &&
            $request->getModel() instanceof \ArrayAccess
        ;
    }
}
