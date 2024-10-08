<?php

namespace CommonGateway\XxllncToKTBBundle\Service;

use App\Service\SynchronizationService as OldSynchronizationService;
use CommonGateway\CoreBundle\Service\SynchronizationService;
use CommonGateway\CoreBundle\Service\MappingService;
use App\Service\GatewayResourceService as ResourceService;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Gateway as Source;
use Psr\Log\LoggerInterface;
use Ramsay\Uuid;

/**
 * This class handles the synchronizations of CustomerInteractionBundle taken to zaaksysteem tasks.
 *
 * Can create, update and delete tasks.
 *
 * @author  Conduction BV <info@conduction.nl>, Barry Brands <barry@conduction.nl>
 * @license EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @category Service
 */
class KTBToXxllncService
{


    /**
     * __construct.
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ResourceService $resourceService,
        private readonly OldSynchronizationService $oldSynchronizationService,
        private readonly SynchronizationService $synchronizationService,
        private readonly MappingService $mappingService,
        private readonly LoggerInterface $pluginLogger,
    ) {

    }//end __construct()


    /**
     * Gets the sourceId of the zaak of a taak which should be already synchronized and existing in the zaaksystem.
     *
     * @param string|null $zaakUrl The zaak url of the taak which could be null, if null we cannot synchronize the taak
     * @param Source      $source  The source where the zaak exists, should be a zaaksysteem v2 instance.
     *
     * @return string|null         The sourceId of the zaak or null if it does not exists.
     */
    private function getZaakSourceId(?string $zaakUrl=null, Source $source): ?string
    {
        $this->pluginLogger->debug('KTBToXxllncService -> getZaakSourceId');
        $pluginName = 'common-gateway/xxllnc-to-ktb-bundle';

        // Get Zaak object ZGWBundle (prerequisite that this Zaak already exists in zaaksysteem).
        if ($zaakUrl === null || filter_var($zaakUrl, FILTER_VALIDATE_URL) === false) {
            $this->pluginLogger->error("Stopping sync, taak.zaak is not set or not a url.");
            return null;
        }

        $zaakId = substr($zaakUrl, (strrpos($zaakUrl, '/') + 1));
        if (Uuid::isValid($zaakId) === false) {
            $this->pluginLogger->error("Stopping sync, taak.zaak its id is invalid.");
            return null;
        }

        $zaakObject = $this->resourceService->getObject(id: $zaakId, pluginName: $pluginName);
        if ($zaakObject === null) {
            $this->pluginLogger->error("Stopping sync, could not find zaak with id: $zaakId.");
            return null;
        }

        $zaakSync = $this->oldSynchronizationService->findSyncByObject(objectEntity: $zaakObject, source: $source, entity: $zaakObject->getEntity());
        if ($zaakSync->geSourceId() === null) {
            $this->pluginLogger->error("Stopping sync, could not find a sourceId on the zaak: $zaakId of the taak.");
            return null;
        }

        return $zaakSync->getSourceId();

    }//end getZaakSourceId()


    /**
     * Synchronizes a CustomerInteractionBundle taak to the zaaksysteem v2 task equilevant.
     *
     * Can handle create, update and delete. Prerequisite is that the taak has a zaak that is synchronized as case in the zaaksysteem.
     *
     * @param array $data
     * @param array $configuration
     *
     * @return array $this->data
     */
    private function synchronizeTaak(array $data, array $configuration): array
    {
        $this->pluginLogger->debug('KTBToXxllncService -> synchronizeTaak');
        $pluginName = 'common-gateway/xxllnc-to-ktb-bundle';

        // Get Source zaaksysteem v2.
        $source = $this->resourceService->getSource(reference: $configuration['source'], pluginName: $pluginName);
        if ($source === null) {
            return $data;
        }

        // Get taak object CustomerInterActionBundle.
        $taakObject = $this->resourceService->getObject(id: $data['_self']['id'], pluginName: $pluginName);
        if ($taakObject === null) {
            return $data;
        }

        // Create or find a Synchronization.
        $taakSync = $this->oldSynchronizationService->findSyncByObject(objectEntity: $taakObject, source: $source, entity: $taakObject->getEntity());

        $endpoint        = '/api/v2/cm/task';
        $getZaakSourceId = true;
        $data            = $data;
        switch ($configuration['currentThrow']) {
            // If creating the sourceId should be new.
        case 'commongateway.object.create':
            $endpoint .= '/create';
            $sourceId  = Uuid::uuidv4();
        case 'commongateway.object.update':
            $endpoint .= '/update';
            // If deleting or updating the sourceId shoudld be from the synchronization.
        case 'commongateway.object.update':
        case 'commongateway.object.delete':
            $sourceId = $taakSync->getSourceId();
            // If creating or updating we use the same mapping and need the case_uuid and the task_uuid we have set in earlier cases.
        case 'commongateway.object.create':
        case 'commongateway.object.update':
            $mapping      = $this->resourceService->getMapping($configuration['mapping'], pluginName: $pluginName);
            $zaakSourceId = $this->getZaakSourceId($taakObject->getValue('zaak'), $source);
            $data         = array_merge($data, ['case_uuid' => $zaakSourceId, 'task_uuid' => $sourceId]);
            // If deleting we need a different mapping and only need the task_uuid.
        case 'commongateway.object.delete':
            $endpoint       .= '/delete';
            $getZaakSourceId = false;
            $mapping         = $this->resourceService->getMapping($configuration['deleteMapping'], pluginName: $pluginName);
            $data            = array_merge($data, ['task_uuid' => $sourceId]);
            // If we have a different throw stop sync.
        default:
            $this->pluginLogger->error("Stopping task sync, invalid event thrown: {$configuration['currentThrow']}");
            return $data;
        }//end switch

        if ($getZaakSourceId === true) {
            $zaakSourceId = $this->getZaakSourceId($taakObject->getValue('zaak'), $source);
        }

        $this->pluginLogger->debug("Mapping taak to task");
        $objectArray = $this->mappingService->mapping($mapping, $data);

        // Synchronize the task to the Source.
        $responseBody = $this->synchronizationService->synchronizeTemp(synchronization: $taakSync, objectArray: $objectArray, objectEntity: $taakObject, location: $endpoint);

        $this->pluginLogger->debug("Mapping taak to task");

        // Set sourceId with earlier generated uuid if request went successfull.
        if (isset($responseBody['data']['success']) === true && $responseBody['data']['success'] === true) {
            $taakSync->setSourceId($sourceId);
            $this->entityManager->persist($taakSync);
            $this->entityManager->flush();
            $this->pluginLogger->info("Succesfully synchronized taak with id: {$data['_self']['id']} and sourceId: $sourceId .");
        } else {
            $this->pluginLogger->error("No success message received from zaaksysteem, something went wrong synchronzing task.");
        }

        return $data;

    }//end synchronizeTaak()


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
        $this->pluginLogger->debug('KTBToXxllncService -> execute');

        return $this->synchronizeTaak(data: $data, configuration: $configuration);

    }//end execute()


}//end class
