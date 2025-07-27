<?php namespace Vankosoft\VendoSdkBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class VSVendoSdkBundle extends Bundle
{
    public function build( ContainerBuilder $container ): void
    {
        parent::build( $container );
    }
    
    /**
     * {@inheritdoc}
     */
    public function getContainerExtension(): ?ExtensionInterface
    {
        return new \Vankosoft\VendoSdkBundle\DependencyInjection\VSVendoSdkExtension();
    }
}
