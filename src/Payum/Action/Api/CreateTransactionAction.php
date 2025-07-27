<?php namespace Vankosoft\VendoSdkBundle\Payum\Action\Api;

use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Exception\LogicException;
use Payum\Core\Exception\RequestNotSupportedException;

use Vankosoft\VendoSdkBundle\Payum\Request\Api\CreateTransaction;
use Vankosoft\VendoSdkBundle\Api\Exceptions;

class CreateTransactionAction extends AbstractApiAction
{
    /**
     * {@inheritDoc}
     */
    public function execute( $request )
    {
        /** @var $request CreateTransaction */
        RequestNotSupportedException::assertSupports( $this, $request );

        $model = ArrayObject::ensureArrayObject( $request->getModel() );

        try {
            $redirectUrl    = $this->getBoricaFactory()
                                    ->request()
                                    ->amount( '1' ) // 1 BGN
                                    ->orderID( 1 ) // Unique identifier in your system
                                    ->description( 'testing the process' ) // Short description of the purchase (up to 125 chars)
                                    ->currency( 'BGN' ) // The currency of the payment
                                    ->register(); // Type of the request
            
            /*
            $charge = Charge::create($model->toUnsafeArrayWithoutLocal());

            $model->replace($charge->toArray(true));
            */
        } catch ( Exceptions\ApiErrorException $e ) {
            $model->replace( $e->getJsonBody() );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function supports( $request )
    {
        return
            $request instanceof CreateTransaction &&
            $request->getModel() instanceof \ArrayAccess
        ;
    }
}
