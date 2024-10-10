<?php

namespace CommonGateway\XxllncToKTBBundle\Service;

use App\Entity\ObjectEntity;
use App\Service\SynchronizationService;
use CommonGateway\CoreBundle\Service\CallService;
use CommonGateway\CoreBundle\Service\GatewayResourceService as ResourceService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

use Exception;

/**
 * This class handles the synchronization of a requestor of a task to a betrokkene object in the CustomerInteractionBundle.
 *
 * Fetches the case of the requestor and then synchronizes the requestor to the betrokkene.
 *
 * @author  Conduction BV <info@conduction.nl>, Barry Brands <barry@conduction.nl>
 * @license EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @category Service
 */
class RequestorToBetrokkeneService
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
     * @param string       $taakId         The ID of the taak to add the betrokkene to.
     *
     * @return ObjectEntity|null The updated taak object.
     */
    private function addBetrokkeneToTaak(ObjectEntity $taakBetrokkene, string $taakId): ?ObjectEntity
    {
        $this->pluginLogger->debug('RequestorToBetrokkeneService -> addBetrokkeneToTaak');

        $taakObject = $this->resourceService->getObject(id: $taakId);
        if ($taakObject === null) {
            $this->pluginLogger->error("Taak not found with id {$taakId}, can not add betrokkene to it", ['plugin' => 'common-gateway/xxllnc-to-ktb-bundle']);

            return null;
        }


        $taakObject->setValue('betrokkenen', [$taakBetrokkene->getId()->toString()]);
        $this->entityManager->persist($taakObject);
        $this->entityManager->flush();

        return $taakObject;

    }//end addBetrokkeneToTaak()


    /**
     * Synchronizes a zaaksysteem case requestor to a betrokkene object in the CustomerInteractionBundle.
     *
     * @param array $data
     * @param array $configuration
     *
     * @return array $data
     */
    private function synchronizeRequestor(array $data, array $configuration): array
    {
        $this->pluginLogger->debug('RequestorToBetrokkeneService -> synchronizeRequestor');
        $pluginName = 'common-gateway/xxllnc-to-ktb-bundle';

        // get needed config objects.
        $source   = $this->resourceService->getSource(reference: $configuration['source'], pluginName: $pluginName);
        $schema   = $this->resourceService->getSchema(reference: $configuration['schema'], pluginName: $pluginName);
        $mapping  = $this->resourceService->getMapping(reference: $configuration['mapping'], pluginName: $pluginName);
        $endpoint = ($configuration['endpoint'] ?? "/case") . "/{$data['case_uuid']}";

        if ($source === null || $schema === null || $mapping === null) {
            return $data;
        }

        // Fetch the case of the task
        try {
            $this->pluginLogger->info("Fetching case with case id: {$data['case_uuid']}..");
            $response = $this->callService->call(source: $source, endpoint: $endpoint, method: 'GET');
            $case     = $this->callService->decodeResponse(source: $source, response: $response);
        } catch (Exception $e) {
            $this->pluginLogger->error("Failed to fetch case with case id: {$data['case_uuid']}, message:  {$e->getMessage()}", ['plugin' => $pluginName]);

            return $data;
        }//end try

        if (isset($case['result']['instance']['requestor']['instance']['subject']['instance']['personal_number']) === false) {
            $this->pluginLogger->error("Case requestor personal number is not set, can not sync requestor to betrokkene", ['plugin' => $pluginName]);

            return $data;
        }

        // Id of requestor in a case.
        $sourceIdLocation = 'result.instance.requestor.reference';

        // Find or create synchronization object.
        $synchronization = $this->synchronizationService->findSyncBySource(source: $source, entity: $schema, sourceId: $sourceIdLocation, endpoint: $endpoint);

        // Synchronize.
        $synchronization = $this->synchronizationService->synchronize(synchronization: $synchronization, sourceObject: $case, unsafe: false, mapping: $mapping);


        // Save to database.
        $this->entityManager->persist($synchronization);
        $this->entityManager->flush();

        $this->addBetrokkeneToTaak($synchronization->getObject(), $data['taakId']);

        return $data;

    }//end synchronizeRequestor()


    /**
     * Executes synchronizeRequestor
     *
     * @param array $data
     * @param array $configuration
     *
     * @return array $this->synchronizeRequestor()
     */
    public function execute(array $data, array $configuration): array
    {
        return $this->synchronizeRequestor(data: $data, configuration: $configuration);

    }//end execute()


}//end class
