<?php namespace Vankosoft\VendoSdkBundle\Payum\Action;

use Payum\Core\Action\ActionInterface;
use Payum\Core\Request\GetStatusInterface;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Exception\RequestNotSupportedException;
use VendoSdk\Vendo;

class StatusAction implements ActionInterface
{
    /**
     * {@inheritDoc}
     *
     * @param GetStatusInterface $request
     */
    public function execute( $request )
    {
        RequestNotSupportedException::assertSupports( $this, $request );

        $model = ArrayObject::ensureArrayObject( $request->getModel() );

        if ( $model['error'] ) {
            $request->markFailed();
            
            return;
        }
        
        if ( false == $model['status'] && false == $model['card'] ) {
            $request->markNew();
            
            return;
        }
        
        if ( false == $model['status'] && $model['card'] ) {
            $request->markPending();
            
            return;
        }
        
        if ( $model['status'] == Vendo::S2S_STATUS_NOT_OK ) {
            $request->markFailed();
            
            return;
        }
        
        if ( $model['status'] == Vendo::S2S_STATUS_OK ) {
            $request->markCaptured();
            
            return;
        }
        
        if ( $model['status'] == Vendo::S2S_STATUS_VERIFICATION_REQUIRED ) {
            $request->markPending();
            
            return;
        }
        
        /*
        if ( Constants::STATUS_SUCCEEDED == $model['status'] && $model['captured'] && $model['paid'] ) {
            $request->markCaptured();
            
            return;
        }
        
        if ( Constants::STATUS_PAID == $model['status'] && $model['captured'] && $model['paid'] ) {
            $request->markCaptured();
            
            return;
        }
        
        
        if ( Constants::STATUS_SUCCEEDED == $model['status'] && false == $model['captured'] ) {
            $request->markAuthorized();
            
            return;
        }
        if ( Constants::STATUS_PAID == $model['status'] && false == $model['captured'] ) {
            $request->markAuthorized();
            
            return;
        }
        */
        
        $request->markUnknown();
    }

    /**
     * {@inheritDoc}
     */
    public function supports( $request )
    {
        return
            $request instanceof GetStatusInterface &&
            $request->getModel() instanceof \ArrayAccess
        ;
    }
}
