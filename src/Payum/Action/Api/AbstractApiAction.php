<?php namespace Vankosoft\VendoSdkBundle\Payum\Action\Api;

use Payum\Core\Action\ActionInterface;
use Payum\Core\ApiAwareInterface;
use Payum\Core\ApiAwareTrait;
use Payum\Core\GatewayAwareTrait;

use Vankosoft\VendoSdkBundle\Payum\Keys;
use Vankosoft\VendoSdkBundle\Api\Factory;

abstract class AbstractApiAction implements ActionInterface, ApiAwareInterface
{
    use ApiAwareTrait {
        setApi as _setApi;
    }
    use GatewayAwareTrait;
    
    public function __construct()
    {
        $this->apiClass = Keys::class;
    }
    
    /**
     * {@inheritDoc}
     */
    public function setApi( $api )
    {
        $this->_setApi( $api );
    }

    protected function getVendoSdkFactory()
    {
        return new Factory( $this->api );
    }
}