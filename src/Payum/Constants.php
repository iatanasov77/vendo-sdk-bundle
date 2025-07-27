<?php namespace Vankosoft\VendoSdkBundle\Payum;

use VendoSdk\Vendo;

class Constants
{
    public const STATUS_SUCCEEDED = Vendo::S2S_STATUS_OK;
    
    public const STATUS_FAILED = Vendo::S2S_STATUS_NOT_OK;
    
    public const STATUS_PAID = 'paid';
    
    private function __construct()
    {
    }
}
