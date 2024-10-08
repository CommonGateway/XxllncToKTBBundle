<?php

namespace CommonGateway\XxllncToKTBBundle\Service;

use App\Service\SynchronizationService;
use CommonGateway\CoreBundle\Service\CallService;
use CommonGateway\CoreBundle\Service\GatewayResourceService as ResourceService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;

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
        private readonly EntityManagerInterface $entityManager,
    ) {

    }//end __construct()


    /**
     * Synchronizes a CustomerInteractionBundle taak to the zaaksysteem v2 task equilevant.
     *
     * Can handle create, update and delete. Prerequisite is that the taak has a zaak that is synchronized as case in the zaaksysteem.
     *
     * @param array $data
     * @param array $configuration
     *
     * @return array $data
     */
    private function synchronizeTask(array $data, array $configuration): array
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
            $response = $this->callService->call(source: $source, endpoint: $endpoint, method: 'GET');
            $response = $this->callService->decodeResponse(source: $source, response: $response);
        } catch (Exception $e) {
            $this->pluginLogger->error("Failed to fetch tasks for case: {$data['case_uuid']}, message:  {$e->getMessage()}");

            return $data;
        }//end try

        // Check if the entity_id is equal to task_id
        foreach ($response['data'] as $task) {
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

        // Save to database.
        $this->entityManager->persist($synchronization);
        $this->entityManager->flush();

        // Set taakId for the next action (sync case to zaak) which also updates the taak with the case url
        $data['taakId'] = $synchronization->getObject()->getId()->toString();

        // Create response.
        // $response         = ['message' => 'Notification received and task synchronized'];
        // $data['response'] = new Response(\Safe\json_encode($response), 200, ['Content-Type' => 'application/json']);

        return $data;

    }//end synchronizeTask()


    /**
     * Executes synchronizeTaak
     *
     * @param array $data
     * @param array $configuration
     *
     * @return array $this->synchronizeTaak()
     */
    public function execute(array $data, array $configuration): array
    {
        return $this->synchronizeTask(data: $data['body'], configuration: $configuration);

    }//end execute()


}//end class
