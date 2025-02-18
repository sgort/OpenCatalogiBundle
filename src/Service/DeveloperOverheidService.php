<?php

namespace OpenCatalogi\OpenCatalogiBundle\Service;

use App\Entity\Entity;
use App\Entity\Gateway;
use App\Entity\Gateway as Source;
use App\Entity\Mapping;
use App\Entity\ObjectEntity;
use App\Service\SynchronizationService;
use CommonGateway\CoreBundle\Service\CacheService;
use CommonGateway\CoreBundle\Service\CallService;
use CommonGateway\CoreBundle\Service\GatewayResourceService;
use CommonGateway\CoreBundle\Service\MappingService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 *  This class handles the interaction with developer.overheid.nl.
 */
class DeveloperOverheidService
{

    /**
     * @var EntityManagerInterface
     */
    private EntityManagerInterface $entityManager;

    /**
     * @var CallService
     */
    private CallService $callService;

    /**
     * @var CacheService
     */
    private CacheService $cacheService;

    /**
     * @var SynchronizationService
     */
    private SynchronizationService $syncService;

    /**
     * @var MappingService
     */
    private MappingService $mappingService;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $pluginLogger;

    /**
     * @var GithubApiService
     */
    private GithubApiService $githubApiService;

    /**
     * @var GatewayResourceService
     */
    private GatewayResourceService $resourceService;


    /**
     * @param EntityManagerInterface $entityManager    The Entity Manager Interface.
     * @param CallService            $callService      The Call Service.
     * @param CacheService           $cacheService     The Cache Service.
     * @param SynchronizationService $syncService      The Synchronization Service.
     * @param MappingService         $mappingService   The Mapping Service.
     * @param GithubApiService       $githubApiService The Github Api Service.
     * @param LoggerInterface        $pluginLogger     The plugin version of the logger interface.
     * @param GatewayResourceService $resourceService  The Gateway Resource Service.
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        CallService $callService,
        CacheService $cacheService,
        SynchronizationService $syncService,
        MappingService $mappingService,
        GithubApiService $githubApiService,
        LoggerInterface $pluginLogger,
        GatewayResourceService $resourceService
    ) {
        $this->entityManager    = $entityManager;
        $this->callService      = $callService;
        $this->cacheService     = $cacheService;
        $this->syncService      = $syncService;
        $this->mappingService   = $mappingService;
        $this->githubApiService = $githubApiService;
        $this->pluginLogger     = $pluginLogger;
        $this->resourceService  = $resourceService;

    }//end __construct()


    /**
     * Get repositories through the repositories of developer.overheid.nl/repositories.
     *
     * @todo duplicate with GithubPubliccodeService ?
     *
     * @return array|null
     */
    public function getRepositories(): ?array
    {
        $result = [];
        // Do we have a source?
        $source = $this->resourceService->getSource('https://opencatalogi.nl/source/oc.developerOverheid.source.json', 'open-catalogi/open-catalogi-bundle');

        $repositories = $this->callService->getAllResults($source, '/repositories');

        $this->pluginLogger->info('Found '.count($repositories).' repositories', ['plugin' => 'open-catalogi/open-catalogi-bundle']);
        foreach ($repositories as $repository) {
            $result[] = $this->importRepository($repository);
        }//end foreach

        $this->entityManager->flush();

        return $result;

    }//end getRepositories()


    /**
     * Get a repository through the repositories of developer.overheid.nl/repositories/{id}.
     *
     * @todo duplicate with GithubPubliccodeService ?
     *
     * @param string $id
     *
     * @return array|null
     */
    public function getRepository(string $id): ?array
    {
        // Do we have a source?
        $source = $this->resourceService->getSource('https://opencatalogi.nl/source/oc.developerOverheid.source.json', 'open-catalogi/open-catalogi-bundle');

        $this->pluginLogger->debug('Getting repository '.$id, ['plugin' => 'open-catalogi/open-catalogi-bundle']);
        $response = $this->callService->call($source, '/repositories/'.$id);

        $repository = json_decode($response->getBody()->getContents(), true);

        if ($repository === null) {
            $this->pluginLogger->error('Could not find a repository with id: '.$id.' and with source: '.$source->getName(), ['plugin' => 'open-catalogi/open-catalogi-bundle']);

            return null;
        }//end if

        $repository = $this->importRepository($repository);
        if ($repository === null) {
            return null;
        }//end if

        $this->entityManager->flush();

        $this->pluginLogger->info('Found repository with id: '.$id, ['plugin' => 'open-catalogi/open-catalogi-bundle']);

        return $repository->toArray();

    }//end getRepository()


    /**
     * @todo duplicate with GithubPubliccodeService ?
     *
     * @param $repository
     *
     * @return ObjectEntity|null
     */
    public function importRepository($repository): ?ObjectEntity
    {
        // Do we have a source?
        $source           = $this->resourceService->getSource('https://opencatalogi.nl/source/oc.developerOverheid.source.json', 'open-catalogi/open-catalogi-bundle');
        $repositoryEntity = $this->resourceService->getSchema('https://opencatalogi.nl/oc.repository.schema.json', 'open-catalogi/open-catalogi-bundle');

        $this->pluginLogger->info('Checking repository '.$repository['name'], ['plugin' => 'open-catalogi/open-catalogi-bundle']);
        $synchronization = $this->syncService->findSyncBySource($source, $repositoryEntity, $repository['id']);
        $synchronization = $this->syncService->synchronize($synchronization, $repository);

        $repositoryObject = $synchronization->getObject();

        $component = $this->githubApiService->connectComponent($repositoryObject);
        if ($component !== null) {
            $repositoryObject->setValue('component', $component);
            $this->entityManager->persist($repositoryObject);
            $this->entityManager->flush();
        }//end if

        return $repositoryObject;

    }//end importRepository()


    /**
     * Get components through the components of developer.overheid.nl/apis.
     *
     * @todo duplicate with ComponentenCatalogusService ?
     *
     * @return array|null
     */
    public function getComponents(): ?array
    {
        $result = [];

        // Do we have a source?
        $source = $this->resourceService->getSource('https://opencatalogi.nl/source/oc.developerOverheid.source.json', 'open-catalogi/open-catalogi-bundle');

        $this->pluginLogger->debug('Trying to get all components from source '.$source->getName(), ['plugin' => 'open-catalogi/open-catalogi-bundle']);

        $components = $this->callService->getAllResults($source, '/apis');

        $this->pluginLogger->info('Found '.count($components).' components', ['plugin' => 'open-catalogi/open-catalogi-bundle']);
        foreach ($components as $component) {
            $result[] = $this->importComponent($component);
        }//end foreach

        $this->entityManager->flush();

        return $result;

    }//end getComponents()


    /**
     * Get a component trough the components of developer.overheid.nl/apis/{id}.
     *
     * @todo duplicate with ComponentenCatalogusService ?
     *
     * @param string $id The id of the component to find.
     *
     * @return array|null
     */
    public function getComponent(string $id): ?array
    {
        // Do we have a source?
        $source = $this->resourceService->getSource('https://opencatalogi.nl/source/oc.developerOverheid.source.json', 'open-catalogi/open-catalogi-bundle');

        $this->pluginLogger->debug('Trying to get component with id: '.$id, ['plugin' => 'open-catalogi/open-catalogi-bundle']);
        $response = $this->callService->call($source, '/apis/'.$id);

        $component = json_decode($response->getBody()->getContents(), true);

        if ($component === null) {
            $this->pluginLogger->error('Could not find a component with id: '.$id.' and with source: '.$source->getName(), ['plugin' => 'open-catalogi/open-catalogi-bundle']);

            return null;
        }//end if

        $component = $this->importComponent($component);
        if ($component === null) {
            return null;
        }//end if

        $this->entityManager->flush();

        $this->pluginLogger->info('Found component with id: '.$id, ['plugin' => 'open-catalogi/open-catalogi-bundle']);

        return $component->toArray();

    }//end getComponent()


    /**
     * Turn a repo array into an object we can handle.
     *
     * @param array $repository The repository to synchronise.
     *
     * @return ?ObjectEntity
     */
    public function handleRepositoryArray(array $repository): ?ObjectEntity
    {
        // Do we have a source?
        $source = $this->resourceService->getSource('https://opencatalogi.nl/source/oc.developerOverheid.source.json', 'open-catalogi/open-catalogi-bundle');

        $repositoryEntity = $this->resourceService->getSchema('https://opencatalogi.nl/oc.repository.schema.json', 'open-catalogi/open-catalogi-bundle');

        // Handle sync.
        $synchronization = $this->syncService->findSyncBySource($source, $repositoryEntity, $repository['id']);
        $this->pluginLogger->debug('Checking component '.$repository['name'], ['plugin' => 'open-catalogi/open-catalogi-bundle']);
        $synchronization = $this->syncService->synchronize($synchronization, $repository);

        return $synchronization->getObject();

    }//end handleRepositoryArray()


    /**
     * @param array        $componentArray  The component array to import.
     * @param ObjectEntity $componentObject The resulting component object.
     *
     * @return ObjectEntity|null
     */
    public function importLegalRepoOwnerThroughComponent(array $componentArray, ObjectEntity $componentObject): ?ObjectEntity
    {
        $organisationEntity = $this->resourceService->getSchema('https://opencatalogi.nl/oc.organisation.schema.json', 'open-catalogi/open-catalogi-bundle');
        $legalEntity        = $this->resourceService->getSchema('https://opencatalogi.nl/oc.legal.schema.json', 'open-catalogi/open-catalogi-bundle');

        // If the component isn't already set to a organisation (legal.repoOwner) create or get the org and set it to the component legal repoOwner.
        if (key_exists('legal', $componentArray) === true
            && key_exists('repoOwner', $componentArray['legal']) === true
            && key_exists('name', $componentArray['legal']['repoOwner']) === true
        ) {
            $organisation = $this->entityManager->getRepository('App:ObjectEntity')->findOneBy(['entity' => $organisationEntity, 'name' => $componentArray['legal']['repoOwner']['name']]);

            if ($organisation === null) {
                $organisation = new ObjectEntity($organisationEntity);
                $organisation->hydrate(
                    [
                        'name'    => $componentArray['legal']['repoOwner']['name'],
                        'email'   => key_exists('email', $componentArray['legal']['repoOwner']) === true ? $componentArray['legal']['repoOwner']['email'] : null,
                        'phone'   => key_exists('phone', $componentArray['legal']['repoOwner']) === true ? $componentArray['legal']['repoOwner']['phone'] : null,
                        'website' => key_exists('website', $componentArray['legal']['repoOwner']) === true ? $componentArray['legal']['repoOwner']['website'] : null,
                        'type'    => key_exists('type', $componentArray['legal']['repoOwner']) === true ? $componentArray['legal']['repoOwner']['type'] : null,
                    ]
                );
            }//end if

            $this->entityManager->persist($organisation);

            if (($legal = $componentObject->getValue('legal')) !== null) {
                $legal->setValue('repoOwner', $organisation);
                $this->entityManager->persist($legal);

                $componentObject->setValue('legal', $legal);
                $this->entityManager->persist($componentObject);
                $this->entityManager->flush();

                return $componentObject;
            }//end if

            $legal = new ObjectEntity($legalEntity);
            $legal->hydrate(
                ['repoOwner' => $organisation]
            );
            $this->entityManager->persist($legal);
            $componentObject->setValue('legal', $legal);
            $this->entityManager->persist($componentObject);
            $this->entityManager->flush();

            return $componentObject;
        }//end if

        return null;

    }//end importLegalRepoOwnerThroughComponent()


    /**
     * @todo duplicate with ComponentenCatalogusService ?
     *
     * @param array $component The component to import.
     *
     * @return ObjectEntity|null
     */
    public function importComponent(array $component): ?ObjectEntity
    {
        // Do we have a source?
        $source          = $this->resourceService->getSource('https://opencatalogi.nl/source/oc.developerOverheid.source.json', 'open-catalogi/open-catalogi-bundle');
        $componentEntity = $this->resourceService->getSchema('https://opencatalogi.nl/oc.component.schema.json', 'open-catalogi/open-catalogi-bundle');
        $mapping         = $this->resourceService->getMapping('https://developer.overheid.nl/api/oc.developerOverheidComponent.mapping.json', 'open-catalogi/open-catalogi-bundle');

        $synchronization = $this->syncService->findSyncBySource($source, $componentEntity, $component['id']);

        $this->pluginLogger->debug('Mapping object'.$component['service_name'], ['plugin' => 'open-catalogi/open-catalogi-bundle']);
        $this->pluginLogger->debug('The mapping object '.$mapping, ['plugin' => 'open-catalogi/open-catalogi-bundle']);

        $this->pluginLogger->debug('Checking component '.$component['service_name'], ['plugin' => 'open-catalogi/open-catalogi-bundle']);

        // Do the mapping of the component set two variables.
        $componentMapping = $componentArray = $this->mappingService->mapping($mapping, $component);

        // Unset component legal before creating object, we don't want duplicate organisations.
        if (key_exists('legal', $componentMapping) === true && key_exists('repoOwner', $componentMapping['legal']) === true) {
            unset($componentMapping['legal']['repoOwner']);
        }//end if

        $synchronization = $this->syncService->synchronize($synchronization, $componentMapping);
        $componentObject = $synchronization->getObject();

        $this->importLegalRepoOwnerThroughComponent($componentArray, $componentObject);

        if ($component['related_repositories'] !== []) {
            $repository       = $component['related_repositories'][0];
            $repositoryObject = $this->handleRepositoryArray($repository);
            $repositoryObject->setValue('component', $componentObject);
            $componentObject->setValue('url', $repositoryObject);
        }//end if

        $this->entityManager->persist($componentObject);

        return $componentObject;

    }//end importComponent()


}//end class
