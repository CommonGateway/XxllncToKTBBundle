<?php

namespace CommonGateway\XxllncToKTBBundle\Service;

use CommonGateway\CoreBundle\Service\SynchronizationService;
use CommonGateway\CoreBundle\Service\CallService;
use App\Service\GatewayResourceService as ResourceService;
use Psr\Log\LoggerInterface;
use Exception;

/**
 * This class handles the synchronization of a notification of a zaaksysteem task taken to a customerinteractionbundle taak.
 *
 * Fetches all tasks of the case of the notification, find the right one of the notification and then synchronizes.
 *
 * @author  Conduction BV <info@conduction.nl>, Barry Brands <barry@conduction.nl>
 * @license EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @category Service
 */
class NotificationToTaakService
{


    /**
     * __construct.
     */
    public function __construct(
        private readonly ResourceService $resourceService,
        private readonly CallService $callService,
        private readonly SynchronizationService $synchronizationService,
        private readonly LoggerInterface $pluginLogger,
    ) {

    }//end __construct()


    /**
     * Synchronizes a CustomerInteractionBundle taak to the zaaksysteem v2 task equilevant.
     *
     * Can handle create, update and delete. Prerequisite is that the taak has a zaak that is synchronized as case in the zaaksysteem.
     *
     * @param array $configuration
     * @param array $data
     *
     * @return array $data
     */
    private function synchronizeTask(array $configuration, array $data): array
    {
        $this->pluginLogger->debug('NotificationToTaakService -> synchronizeTask');
        $pluginName = 'common-gateway/xxllnc-to-ktb-bundle';

        // get needed config objects.
        $source  = $this->resourceService->getSource(reference: $configuration['source'], pluginName: $pluginName);
        $schema  = $this->resourceService->getSchema(reference: $configuration['schema'], pluginName: $pluginName);
        $mapping = $this->resourceService->getMapping(reference: $configuration['mapping'], pluginName: $pluginName);

        if ($source === null || $schema === null || $mapping === null) {
            return $data;
        }

        $endpoint = $configuration['endpoint']."?filter[relationships.case.id]=".$data['case_uuid'];

        // Fetch all tasks of the case
        try {
            $this->pluginLogger->info("Fetching tasks for case id: {$data['case_uuid']}..");
            $response = $this->callService->call($source, $endpoint, 'GET', [], false, false);
            $tasks    = $this->callService->decodeResponse(source: $source, response: $response);
        } catch (Exception $e) {
            $this->pluginLogger->error("Failed to fetch tasks for case: {$data['case_uuid']}, message:  {$e->getMessage()}");

            return null;
        }//end try

        // Check if the entity_id is equal to task id
        foreach ($tasks as $task) {
            if ($data['entity_id'] === $task['id']) {
                $taskWeNeed = $task;
            }
        }

        if (isset($taskWeNeed) === false) {
            $this->pluginLogger->error("Could not find the correct task ({$data['entity_id']}) in the tasks of the case ({$data['case_uuid']})");

            return $data;
        }

        // Find or create synchronization object.
        $synchronization = $this->synchronizationService->findSyncBySource(source: $source, entity: $schema, sourceId: $taskWeNeed['id'], endpoint: $endpoint);

        // Synchronize.
        $synchronization = $this->synchronizationService->synchronize(synchronization: $synchronization, sourceObject: $taskWeNeed, unsafe: false, mapping: $mapping);

        return $data;

    }//end synchronizeTask()


    /**
     * Executes synchronizeTaak
     *
     * @param array $configuration
     * @param array $data
     *
     * @return array $this->synchronizeTaak()
     */
    public function execute(array $configuration, array $data): array
    {
        return $this->synchronizeTask(configuration: $configuration, data: $data);

    }//end execute()


}//end class
