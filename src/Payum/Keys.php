<?php namespace Vankosoft\VendoSdkBundle\Payum;

use Payum\Core\Bridge\Spl\ArrayObject;

final class Keys
{
    /** @var ArrayObject */
    protected $config;

    /**
     * @param ArrayObject $config
     */
    public function __construct( ArrayObject $config )
    {
        $this->config   = $config;
    }

    /**
     * @return string
     */
    public function getMerchantId()
    {
        return $this->config['merchant_id'];
    }

    /**
     * @return string
     */
    public function getSiteId()
    {
        return $this->config['site_id'];
    }
    
    /**
     * @return string
     */
    public function getApiSecret()
    {
        return $this->config['api_secret'];
    }
    
    /**
     * @return bool
     */
    public function getIsTest()
    {
        return isset( $this->config['sandbox'] ) ? $this->config['sandbox'] : false;
    }
}
