<?php namespace Vankosoft\VendoSdkBundle\Payum\Action\Api;

use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Action\ActionInterface;
use Payum\Core\ApiAwareInterface;
use Payum\Core\ApiAwareTrait;
use Payum\Core\GatewayAwareTrait;

use GuzzleHttp\Exception\GuzzleException;
use VendoSdk\Exception as VendoSdkException;

use Vankosoft\VendoSdkBundle\Payum\Api;
use Vankosoft\VendoSdkBundle\Payum\Request\Api\CapturePayment;

class CapturePaymentAction implements ActionInterface, ApiAwareInterface
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
        try {
            $response = $this->api->doCapturePayment( $model['transaction'] );
            
            $model['status'] = $response->getStatus();
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
            $request instanceof CapturePayment &&
            $request->getModel() instanceof \ArrayAccess
        ;
    }
}
