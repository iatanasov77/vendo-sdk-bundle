<?php namespace Vankosoft\VendoSdkBundle\Payum\Action\Api;

use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Exception\LogicException;
use Payum\Core\Exception\RequestNotSupportedException;

use Vankosoft\VendoSdkBundle\Payum\Request\Api\CheckTransactionStatus;

class CheckTransactionStatusAction extends AbstractApiAction
{
    /**
     * {@inheritDoc}
     */
    public function execute( $request )
    {
        /** @var $request CheckTransaction */
        RequestNotSupportedException::assertSupports( $this, $request );
        
        $model = ArrayObject::ensureArrayObject( $request->getModel() );
        
        try {
            $redirectUrl    = $this->getBoricaFactory()
                                    ->amount( '1' ) // 1 BGN
                                    ->orderID( 1 ) // Unique identifier in your system
                                    ->description( 'testing the process' ) // Short description of the purchase (up to 125 chars)
                                    ->currency( 'BGN' ) // The currency of the payment
                                    ->status(); // Type of the request
            
            /*
             $charge = Charge::create($model->toUnsafeArrayWithoutLocal());
             
             $model->replace($charge->toArray(true));
             */
            // } catch ( Exception\ApiErrorException $e ) {
        } catch ( \Exception $e ) {
            $model->replace( $e->getJsonBody() );
        }
    }
    
    /**
     * {@inheritDoc}
     */
    public function supports( $request )
    {
        return
        $request instanceof CheckTransactionStatus &&
            $request->getModel() instanceof \ArrayAccess
        ;
    }
}
