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
 * This class handles the synchronization of a assignee of a task to a betrokkene object in the CustomerInteractionBundle.
 *
 * Fetches the case of the assignee and then synchronizes the assignee to the betrokkene.
 *
 * @author  Conduction BV <info@conduction.nl>, Barry Brands <barry@conduction.nl>
 * @license EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @category Service
 */
class AssigneeToBetrokkeneService
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
     * Adds a betrokkene to a taak.
     *
     * @param ObjectEntity $taakBetrokkene The betrokkene to add to the taak.
     * @param string       $taskId         The ID of the taak to add the betrokkene to.
     *
     * @return ObjectEntity The updated taak object.
     */
    private function addBetrokkeneToTaak(ObjectEntity $taakBetrokkene, string $taskId): ObjectEntity
    {
        $this->pluginLogger->debug('AssigneeToBetrokkeneService -> addBetrokkeneToTaak');

        $taakObject = $this->resourceService->getObject(id: $taskId);
        $taakObject->setValue('betrokkenen', [$taakBetrokkene => getId()->toString()]);
        $this->entityManager->persist($taakObject);
        $this->entityManager->flush();

        return $taakObject;

    }//end addBetrokkeneToTaak()


    /**
     * Synchronizes a zaaksysteem case assignee to a betrokkene object in the CustomerInteractionBundle.
     *
     * @param array $data
     * @param array $configuration
     *
     * @return array $data
     */
    private function synchronizeAssignee(array $data, array $configuration): array
    {
        $this->pluginLogger->debug('AssigneeToBetrokkeneService -> synchronizeAssignee');
        $pluginName = 'common-gateway/xxllnc-to-ktb-bundle';

        if ($data['body']['case_uuid'] === null) {
            $this->pluginLogger->error("Case uuid is null, can not sync assignee to betrokkene");

            return $data;
        }

        // get needed config objects.
        $source   = $this->resourceService->getSource(reference: $configuration['source'], pluginName: $pluginName);
        $schema   = $this->resourceService->getSchema(reference: $configuration['schema'], pluginName: $pluginName);
        $mapping  = $this->resourceService->getMapping(reference: $configuration['mapping'], pluginName: $pluginName);
        $endpoint = ($configuration['endpoint'] ?? "/case");

        if ($source === null || $schema === null || $mapping === null) {
            return $data;
        }

        // Fetch the case of the task
        try {
            $this->pluginLogger->info("Fetching case with case id: {$data['body']['case_uuid']}..");
            $response = $this->callService->call(source: $source, endpoint: $endpoint, method: 'GET');
            $case     = $this->callService->decodeResponse(source: $source, response: $response);
        } catch (Exception $e) {
            $this->pluginLogger->error("Failed to fetch case with case id: {$data['body']['case_uuid']}, message:  {$e->getMessage()}");

            return $data;
        }//end try

        // Id of assignee in a case.
        $sourceIdLocation = '?';

        // Find or create synchronization object.
        $synchronization = $this->synchronizationService->findSyncBySource(source: $source, entity: $schema, sourceId: $sourceIdLocation, endpoint: $endpoint);

        // Synchronize.
        $synchronization = $this->synchronizationService->synchronize(synchronization: $synchronization, sourceObject: $case, unsafe: false, mapping: $mapping);

        // Save to database.
        $this->entityManager->persist($synchronization);
        $this->entityManager->flush();

        $this->addBetrokkeneToTaak($synchronization->getObject(), $data['taskId']);

        return $data;

    }//end synchronizeAssignee()


    /**
     * Executes synchronizeAssignee
     *
     * @param array $data
     * @param array $configuration
     *
     * @return array $this->synchronizeAssignee()
     */
    public function execute(array $data, array $configuration): array
    {
        return $this->synchronizeAssignee(data: $data, configuration: $configuration);

    }//end execute()


}//end class
