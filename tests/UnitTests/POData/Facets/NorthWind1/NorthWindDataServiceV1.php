<?php

namespace UnitTests\POData\Facets\NorthWind1;

use POData\Configuration\EntitySetRights;
use POData\IDataService;
use POData\IRequestHandler;

use POData\Configuration\DataServiceProtocolVersion;
use POData\Configuration\DataServiceConfiguration;
use POData\IServiceProvider;
use POData\DataService;


class NorthWindDataServiceV1 extends DataService2 implements IServiceProvider
{
    private $_northWindMetadata = null;
    
    /**
     * This method is called only once to initialize service-wide policies
     * 
     * @param DataServiceConfiguration $config
     */
    public function initializeService(DataServiceConfiguration &$config)
    {
        $config->setEntitySetAccessRule('*', EntitySetRights::ALL);
        //we are using V1 protocol, but still we set page size because with
        //a top value which is less than pagesize we can use V1 protocol 
        //even though paging is enabled.
        $config->setEntitySetPageSize('*', 5);
        $config->setAcceptCountRequests(true);
        $config->setAcceptProjectionRequests(true);
        $config->setMaxDataServiceVersion(DataServiceProtocolVersion::V1);
    }

    /**
     * 
     * @see library/POData.IServiceProvider::getService()
     * 
     * @return object
     */
    public function getService($serviceType)
    {
        if ($serviceType === 'IDataServiceMetadataProvider') {
            if (is_null($this->_northWindMetadata)) {
                $this->_northWindMetadata = NorthWindMetadata::Create();
            }

            return $this->_northWindMetadata;
        } else if ($serviceType === 'IDataServiceQueryProvider') {
            return new NorthWindQueryProvider2();
        } else if ($serviceType === 'IDataServiceStreamProvider') {
            return null;
        }

        return null;
    }    
}