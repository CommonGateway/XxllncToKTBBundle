<?php

namespace CommonGateway\XxllncToKTBBundle\ActionHandler;

use App\Exception\GatewayException;
use CommonGateway\CoreBundle\ActionHandler\ActionHandlerInterface;
use CommonGateway\XxllncToKTBBundle\Service\NotificationToTaakService as Service;
use Psr\Cache\CacheException;
use Psr\Cache\InvalidArgumentException;
use Respect\Validation\Exceptions\ComponentException;

/**
 * This class handles the execution of the AssigneeToBetrokkeneService.
 *
 * This ActionHandler executes the
 * AssigneeToBetrokkene->synchronizeAssignee.
 *
 * @author  Conduction BV <info@conduction.nl>, Barry Brands <barry@conduction.nl>
 * @license EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @category ActionHandler
 */
class AssigneeToBetrokkeneHandler implements ActionHandlerInterface
{


    /**
     * Class constructor.
     *
     * @param Service $service
     */
    public function __construct(
        private readonly Service $service,
    ) {

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
            '$id'         => 'https://development.zaaksysteem.nl/schemas/NotificationToTaak.ActionHandler.schema.json',
            '$schema'     => 'https://docs.commongateway.nl/schemas/ActionHandler.schema.json',
            'title'       => 'NotificationToTaak',
            'description' => 'This handler gets throught the notification the task and syncs it to taak',
            'required'    => [],
            'properties'  => [
                'endpoint' => [
                    'type'        => 'string',
                    'description' => 'The endpoint we request the tasks from.',
                    'example'     => '/api/v2/cm/task/get_task_list',
                ],
                'source'   => [
                    'type'        => 'string',
                    'description' => 'The source we use to fetch tasks.',
                    'example'     => 'https://development.zaaksysteem.nl/source/xxllnc.zaaksysteemv2.source.json',
                ],
                'mapping'  => [
                    'type'        => 'string',
                    'description' => 'The mapping we use for tasks to taken.',
                    'example'     => 'https://commongateway.nl/mapping/xxllnctoktb.TaskToTaak.mapping.json',
                ],
                'schema'   => [
                    'type'        => 'string',
                    'description' => 'The schema of the customerinteractionbundle taak.',
                    'example'     => 'https://commongateway.nl/klant.taak.schema.json',
                ],
            ],
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
