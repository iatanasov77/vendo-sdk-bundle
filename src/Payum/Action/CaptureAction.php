<?php namespace Vankosoft\VendoSdkBundle\Payum\Action;

use Payum\Core\Action\ActionInterface;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\GatewayAwareInterface;
use Payum\Core\GatewayAwareTrait;
use Payum\Core\Request\Capture;
use Payum\Core\Exception\RequestNotSupportedException;

use Vankosoft\VendoSdkBundle\Payum\Request\Api\CreditCardPayment;

class CaptureAction implements ActionInterface, GatewayAwareInterface
{
    use GatewayAwareTrait;

    /**
     * {@inheritDoc}
     *
     * @param Capture $request
     */
    public function execute( $request )
    {
        RequestNotSupportedException::assertSupports( $this, $request );

        $model = ArrayObject::ensureArrayObject( $request->getModel() );

        if ( $model['status'] ) {
            return;
        }
        
        $this->gateway->execute( new CreditCardPayment( $model ) );
    }

    /**
     * {@inheritDoc}
     */
    public function supports( $request )
    {
        return
            $request instanceof Capture &&
            $request->getModel() instanceof \ArrayAccess
        ;
    }
}
