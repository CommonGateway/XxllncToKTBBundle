<?php
/**
 * The XxllncToKTBBundle bundle handles synchronization between zaaksysteem v2 notifications for tasks and CustomerInteractionBundle taken.
 *
 * @author  Conduction.nl <info@conduction.nl>
 * @license EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 */

namespace CommonGateway\XxllncToKTBBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class XxllncToKTBBundle extends Bundle
{

    const PLUGIN_NAME = 'common-gateway/xxllnc-to-ktb-bundle';

    /**
     * Returns the path the bundle is in
     *
     * @return string
     */
    public function getPath(): string
    {
        return \dirname(__DIR__);

    }//end getPath()


}//end class
