<?php

namespace OpenCatalogi\OpenCatalogiBundle\Service;

use App\Entity\Entity;
use App\Entity\Mapping;
use App\Entity\ObjectEntity;
use CommonGateway\CoreBundle\Service\CallService;
use CommonGateway\CoreBundle\Service\GatewayResourceService;
use CommonGateway\CoreBundle\Service\MappingService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Encoder\YamlEncoder;

class EnrichPubliccodeFromGithubUrlService
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
     * @var MappingService
     */
    private MappingService $mappingService;

    /**
     * @var GithubPubliccodeService
     */
    private GithubPubliccodeService $githubService;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $pluginLogger;

    /**
     * @var GatewayResourceService
     */
    private GatewayResourceService $resourceService;

    /**
     * @var array
     */
    private array $configuration;

    /**
     * @var array
     */
    private array $data;


    /**
     * @param EntityManagerInterface  $entityManager   The Entity Manager Interface
     * @param CallService             $callService     The Call Service
     * @param MappingService          $mappingService  The Mapping Service
     * @param GithubPubliccodeService $githubService   The Github Publiccode Service
     * @param LoggerInterface         $pluginLogger    The plugin version of the logger interface.
     * @param GatewayResourceService  $resourceService The Gateway Resource Service.
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        CallService $callService,
        MappingService $mappingService,
        GithubPubliccodeService $githubService,
        LoggerInterface $pluginLogger,
        GatewayResourceService $resourceService
    ) {
        $this->entityManager   = $entityManager;
        $this->callService     = $callService;
        $this->githubService   = $githubService;
        $this->mappingService  = $mappingService;
        $this->pluginLogger    = $pluginLogger;
        $this->resourceService = $resourceService;
        $this->configuration   = [];
        $this->data            = [];

    }//end __construct()


    /**
     * This function fetches repository data.
     *
     * @param string $publiccodeUrl endpoint to request
     *
     * @throws GuzzleException
     *
     * @return array|null|Response
     */
    public function getPubliccodeFromUrl(string $repositoryUrl)
    {
        $source = $this->resourceService->getSource('https://opencatalogi.nl/source/oc.GitHubAPI.source.json', 'open-catalogi/open-catalogi-bundle');

        $possibleEndpoints = [
            '/repos/'.$repositoryUrl.'/contents/publiccode.yaml',
            '/repos/'.$repositoryUrl.'/contents/publiccode.yml',
        ];

        foreach ($possibleEndpoints as $endpoint) {
            try {
                $response = $this->callService->call($source, $endpoint);
            } catch (Exception $e) {
                $this->pluginLogger->error('Error found trying to fetch '.$endpoint.' '.$e->getMessage());
            }

            if (isset($response) === true) {
                return $this->githubService->parsePubliccode($repositoryUrl, $response);
            }
        }

        return null;

    }//end getPubliccodeFromUrl()


    /**
     * This function fetches repository data.
     *
     * @param string $publiccodeUrl endpoint to request
     *
     * @throws GuzzleException
     *
     * @return array|null|Response
     */
    public function getPubliccodeFromRawUserContent(string $repositoryUrl)
    {
        $source = $this->resourceService->getSource('https://opencatalogi.nl/source/oc.GitHubusercontent.source.json', 'open-catalogi/open-catalogi-bundle');

        $possibleEndpoints = [
            '/'.$repositoryUrl.'/main/publiccode.yaml',
            '/'.$repositoryUrl.'/main/publiccode.yml',
            '/'.$repositoryUrl.'/master/publiccode.yaml',
            '/'.$repositoryUrl.'/master/publiccode.yml',
        ];

        foreach ($possibleEndpoints as $endpoint) {
            try {
                $response = $this->callService->call($source, $endpoint);
            } catch (Exception $e) {
                $this->pluginLogger->error('Error found trying to fetch '.$endpoint.' '.$e->getMessage());
            }

            if (isset($response) === true) {
                $yamlEncoder = new YamlEncoder();

                // @TODO: Use the CallService decodeBody
                return $yamlEncoder->decode($response->getBody()->getContents(), 'yaml');
            }
        }

        return null;

    }//end getPubliccodeFromRawUserContent()


    /**
     * @TODO
     *
     * @param ObjectEntity $repository
     * @param string       $repositoryUrl
     *
     * @throws Exception
     *
     * @return ObjectEntity|null dataset at the end of the handler
     */
    public function enrichRepositoryWithPubliccode(ObjectEntity $repository, string $repositoryUrl): ?ObjectEntity
    {
        $url = trim(\Safe\parse_url($repositoryUrl, PHP_URL_PATH), '/');

        // Get the publiccode through the raw.githubusercontent source
        $publiccode = $this->getPubliccodeFromRawUserContent($url);
        if (is_array($publiccode) === true) {
            $this->githubService->mapPubliccode($repository, $publiccode);
        }

        // If still not found, get the publiccode through the api.github source
        if ($publiccode === null) {
            $publiccode = $this->getPubliccodeFromUrl($url);
            if (is_array($publiccode) === true) {
                $this->githubService->mapPubliccode($repository, $publiccode);
            }
        }

        return $repository;

    }//end enrichRepositoryWithPubliccode()


    /**
     * @param array|null  $data          data set at the start of the handler
     * @param array|null  $configuration configuration of the action
     * @param string|null $repositoryId
     *
     * @return array dataset at the end of the handler
     */
    public function enrichPubliccodeFromGithubUrlHandler(?array $data=[], ?array $configuration=[], ?string $repositoryId=null): array
    {
        $this->configuration = $configuration;
        $this->data          = $data;

        if ($repositoryId !== null) {
            // If we are testing for one repository.
            $repository = $this->entityManager->find('App:ObjectEntity', $repositoryId);
            if ($repository instanceof ObjectEntity === true
                && $repository->getValue('publiccode_url') === null
            ) {
                $this->enrichRepositoryWithPubliccode($repository, $repository->getValue('url'));
            }

            if ($repository instanceof ObjectEntity === false) {
                $this->pluginLogger->error('Could not find given repository');
            }
        }

        if ($repositoryId === null) {
            $repositoryEntity = $this->resourceService->getSchema('https://opencatalogi.nl/oc.repository.schema.json', 'open-catalogi/open-catalogi-bundle');

            // If we want to do it for al repositories.
            $this->pluginLogger->debug('Looping through repositories');
            foreach ($repositoryEntity->getObjectEntities() as $repository) {
                if ($repository->getValue('publiccode_url') === null) {
                    $this->enrichRepositoryWithPubliccode($repository, $repository->getValue('url'));
                }
            }
        }

        $this->entityManager->flush();

        $this->pluginLogger->debug('enrichPubliccodeFromGithubUrlHandler finished');

        return $this->data;

    }//end enrichPubliccodeFromGithubUrlHandler()


}//end class
