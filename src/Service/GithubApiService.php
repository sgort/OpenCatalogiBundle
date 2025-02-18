<?php

namespace OpenCatalogi\OpenCatalogiBundle\Service;

use App\Entity\Entity;
use App\Entity\Mapping;
use App\Entity\ObjectEntity;
use CommonGateway\CoreBundle\Service\CacheService;
use CommonGateway\CoreBundle\Service\CallService;
use CommonGateway\CoreBundle\Service\GatewayResourceService;
use CommonGateway\CoreBundle\Service\MappingService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;

class GithubApiService
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
     * @var MappingService
     */
    private MappingService $mappingService;

    /**
     * @var array
     */
    private array $configuration;

    /**
     * @var array
     */
    private array $data;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $pluginLogger;

    /**
     * @var GatewayResourceService
     */
    private GatewayResourceService $resourceService;


    /**
     * @param EntityManagerInterface $entityManager   The Entity Manager Interface
     * @param CallService            $callService     The Call Service
     * @param CacheService           $cacheService    The Cache Service
     * @param MappingService         $mappingService  The Mapping Service
     * @param LoggerInterface        $pluginLogger    The plugin version of the logger interface
     * @param GatewayResourceService $resourceService The Gateway Resource Service.
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        CallService $callService,
        CacheService $cacheService,
        MappingService $mappingService,
        LoggerInterface $pluginLogger,
        GatewayResourceService $resourceService
    ) {
        $this->entityManager   = $entityManager;
        $this->callService     = $callService;
        $this->cacheService    = $cacheService;
        $this->mappingService  = $mappingService;
        $this->pluginLogger    = $pluginLogger;
        $this->resourceService = $resourceService;

        $this->configuration = [];
        $this->data          = [];

    }//end __construct()


    /**
     * This function create or get the component of the repository.
     *
     * @param ObjectEntity $repository The repository object.
     *
     * @throws Exception
     *
     * @return ObjectEntity|null
     */
    public function connectComponent(ObjectEntity $repository): ?ObjectEntity
    {
        $componentEntity = $this->resourceService->getSchema('https://opencatalogi.nl/oc.component.schema.json', 'open-catalogi/open-catalogi-bundle');
        $components      = $this->cacheService->searchObjects(null, ['url' => $repository->getSelf()], [$componentEntity->getId()->toString()])['results'];

        if ($components === []) {
            $component = new ObjectEntity($componentEntity);
            $component->hydrate(
                [
                    'name' => $repository->getValue('name'),
                    'url'  => $repository,
                ]
            );
            $this->entityManager->persist($component);
        }//end if

        if (count($components) === 1) {
            $component = $this->entityManager->find('App:ObjectEntity', $components[0]['_self']['id']);
        }//end if

        if (isset($component) === true) {
            return $component;
        }//end if

        return null;

    }//end connectComponent()


    /**
     * This function checks if a github repository is public.
     *
     * @param string $slug The slug of the repository
     *
     * @return bool Boolean for if the repository is public.
     */
    public function checkPublicRepository(string $slug): bool
    {
        $source = $this->resourceService->getSource('https://opencatalogi.nl/source/oc.GitHubAPI.source.json', 'open-catalogi/open-catalogi-bundle');

        $slug = preg_replace('/^https:\/\/github.com\//', '', $slug);
        $slug = rtrim($slug, '/');

        try {
            $response   = $this->callService->call($source, '/repos/'.$slug);
            $repository = $this->callService->decodeResponse($source, $response);
        } catch (Exception $exception) {
            // @TODO Monolog ?
            $this->pluginLogger->error("Exception while checking if public repository: {$exception->getMessage()}");

            return false;
        }

        return $repository['private'] === false;

    }//end checkPublicRepository()


}//end class
