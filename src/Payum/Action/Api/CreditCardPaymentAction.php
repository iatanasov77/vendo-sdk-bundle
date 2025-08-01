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
use Vankosoft\VendoSdkBundle\Payum\Request\Api\CreditCardPayment;

class CreditCardPaymentAction implements ActionInterface, ApiAwareInterface
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
        
        $model['order'] = $model['local']['order'];
        $model['customer'] = $model['local']['customer'];
        $model['credit_card'] = $model['local']['credit_card'];
        
        try {
            $response = $this->api->doCreditCardPayment( 
                $model->toUnsafeArrayWithoutLocal(),
                $model['local']['client_request']
            );
            
            $model['status'] = $response->getStatus();
            $model['status_message'] = $this->api->getStatusMessage( $response );
            
            if ( $model['status'] == Vendo::S2S_STATUS_OK ) {
                $model['transaction'] = $response->getTransactionDetails()->getId();
                $model['credit_card_token'] = $response->getPaymentToken();
            }
        } catch ( VendoSdkException $e ) {
            $model['error'] = 'An error occurred when processing your API request. Error message: ' . $e->getMessage();
        } catch ( GuzzleException $e ) {
            $model['error'] = 'An error occurred when processing the HTTP request. Error message: ' . $e->getMessage();
        }
    }

    /**
     * {@inheritDoc}
     */
    public function supports( $request )
    {
        return
            $request instanceof CreditCardPayment &&
            $request->getModel() instanceof \ArrayAccess
        ;
    }
}
