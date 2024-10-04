<?php
/**
 * This class handles the update of a customerinteractiobundle taak to xxllnc task.
 *
 * This ActionHandler executes the
 * KTBToXxllncService->syncTaakToTask.
 *
 * @author  Conduction BV <info@conduction.nl>, Barry Brands <barry@conduction.nl>
 * @license EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @category ActionHandler
 */

namespace CommonGateway\XxllncToKTBBundle\ActionHandler;

use App\Exception\GatewayException;
use CommonGateway\CoreBundle\ActionHandler\ActionHandlerInterface;
use CommonGateway\XxllncToKTBBundle\Service\KTBToXxllncService as Service;
use Psr\Cache\CacheException;
use Psr\Cache\InvalidArgumentException;
use Respect\Validation\Exceptions\ComponentException;

class KTBToXxllncHandler implements ActionHandlerInterface
{

    /**
     * Class constructor.
     *
     * @param Service $service
     */
    public function __construct(
        private readonly Service $service,
    )
    {
    }//end __construct()


    /**
     * This function returns the required configuration as
     * a [json-schema](https://json-schema.org/) array.
     *
     * @return array a [json-schema](https://json-schema.org/) that this action should comply to
     */
    public function getConfiguration(): array
    {
        return [
            '$id'         => 'https://development.zaaksysteem.nl/schemas/KTBToXxllnc.ActionHandler.schema.json',
            '$schema'     => 'https://docs.commongateway.nl/schemas/ActionHandler.schema.json',
            'title'       => 'KTBToXxllnc',
            'description' => 'This handler sends the taak to xxllnc zaaksysteem task',
            'required'    => [],
            'properties'  => [],
        ];

    }//end getConfiguration()


    /**
     * This function executes the service
     *
     * @param array $data          The data from the call
     * @param array $configuration The configuration of the action
     *
     * @throws GatewayException
     * @throws CacheException
     * @throws InvalidArgumentException
     * @throws ComponentException
     *
     * @return array
     */
    public function run(array $data, array $configuration): array
    {
        return $this->service->execute($data, $configuration);

    }//end run()


}//end class
