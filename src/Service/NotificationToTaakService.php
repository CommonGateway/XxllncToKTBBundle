<?php
/**
 * This class handles the synchronization of a notification of a zaaksysteem task taken to a customerinteractionbundle taak.
 *
 * Fetches all tasks of the case of the notification.
 *
 * @author  Conduction BV <info@conduction.nl>, Barry Brands <barry@conduction.nl>
 * @license EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @category Service
 */

namespace CommonGateway\XxllncToKTBBundle\Service;

use App\Service\SynchronizationService as OldSynchronizationService;
use CommonGateway\CoreBundle\Service\SynchronizationService;
use CommonGateway\CoreBundle\Service\CallService;
use CommonGateway\CoreBundle\Service\MappingService;
use App\Service\GatewayResourceService as ResourceService;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Gateway as Source;
use Psr\Log\LoggerInterface;
use Ramsay\Uuid;

class NotificationToTaakService
{

    /**
     * @var array
     */
    private array $configuration;

    /**
     * @var array
     */
    private array $data;

    /**
     * __construct.
     */
    public function __construct(
        private readonly EntityManagerInterface    $entityManager,
        private readonly ResourceService           $resourceService,
        private readonly CallService               $callService,
        private readonly OldSynchronizationService $oldSynchronizationService,
        private readonly SynchronizationService    $synchronizationService,
        private readonly MappingService            $mappingService,
        private readonly LoggerInterface           $pluginLogger,
    ) {
    }//end __construct()


    /**
     * Synchronizes a CustomerInteractionBundle taak to the zaaksysteem v2 task equilevant.
     * 
     * Can handle create, update and delete. Prerequisite is that the taak has a zaak that is synchronized as case in the zaaksysteem.
     * 
     * @return array $this->data
     */
    private function synchronizeTask(): array
    {
        $this->pluginLogger->debug('NotificationToTaakService -> synchronizeTask');
        $pluginName = 'common-gateway/xxllnc-to-ktb-bundle';

        // Get Source zaaksysteem v2.
        $source = $this->resourceService->getSource(reference: $this->configuration['source'], pluginName: $pluginName);
        if ($source === null) {
            return $this->data;
        }

        // Get taak schema.
        $schema = $this->resourceService->getSchema(reference: $this->configuration['schema'], pluginName: $pluginName);
        if ($schema === null) {
            return $this->data;
        }

        // Get task to taak mapping.
        $mapping = $this->resourceService->getMapping(reference: $this->configuration['mapping'], pluginName: $pluginName);
        if ($mapping === null) {
            return $this->data;
        }

        $endpoint = $this->configuration['endpoint'] . "?filter[relationships.case.id]=" . $this->data['case_uuid'];

        // Fetch all tasks of the case
        try {
            $this->pluginLogger->info("Fetching tasks for case id: {$this->data['case_uuid']}..");
            $response = $this->callService->call($source, $endpoint, 'GET', [], false, false);
            $tasks    = $this->callService->decodeResponse(source: $source, response: $response);
        } catch (Exception $e) {
            // isset($this->style) === true && $this->style->error("Failed to fetch case: $caseID, message:  {$e->getMessage()}");
            $this->pluginLogger->error("Failed to fetch tasks for case: {$this->data['case_uuid']}, message:  {$e->getMessage()}");

            return null;
        }//end try

        // Check if the entity_id is equal to task id
        foreach ($tasks as $task) {
            if ($this->data['entity_id'] === $task['id']) {
                $taskWeNeed = $task;
            }
        }

        if (isset($taskWeNeed) === false) {
            $this->pluginLogger->error("Could not find the correct task ({$this->data['entity_id']}) in the tasks of the case ({$this->data['case_uuid']})");

            return $this->data;
        }

        // Synchronize correct task.
        $synchronization = $this->oldSynchronizationService->findSyncBySource(source: $source, entity: $schema, sourceId: $taskWeNeed['id'], endpoint: $endpoint);
        $synchronization = $this->oldSynchronizationService->synchronize(synchronization: $synchronization, sourceObject: $taskWeNeed, unsafe: false, mapping: $mapping);

        return $this->data;
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
        $this->data = $data;
        $this->configuration = $configuration;

        return $this->synchronizeTask();
    }//end execute()


}//end class
