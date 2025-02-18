<?php

namespace OpenCatalogi\OpenCatalogiBundle\Service;

use App\Entity\Entity;
use App\Entity\Gateway as Source;
use App\Entity\ObjectEntity;
use CommonGateway\CoreBundle\Service\CallService;
use CommonGateway\CoreBundle\Service\GatewayResourceService;
use CommonGateway\CoreBundle\Service\MappingService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Yaml\Yaml;
use Twig\Error\LoaderError;
use Twig\Error\SyntaxError;

/**
 * Loops through organizations (https://opencatalogi.nl/oc.organisation.schema.json)
 * and tries to find a opencatalogi.yaml on github with its organization name to update the organization object with that fetched opencatalogi.yaml data.
 */
class FindGithubRepositoryThroughOrganizationService
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
     * @var GithubPubliccodeService
     */
    private GithubPubliccodeService $githubService;

    /**
     * @var array
     */
    private array $data;

    /**
     * @var array
     */
    private array $configuration;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $pluginLogger;

    /**
     * @var GatewayResourceService
     */
    private GatewayResourceService $resourceService;

    /**
     * @var MappingService
     */
    private MappingService $mappingService;

    /**
     * @var Yaml
     */
    private Yaml $yaml;


    /**
     * @param EntityManagerInterface  $entityManager   The Entity Manager Interface
     * @param GithubPubliccodeService $githubService   The Github Publiccode Service
     * @param CallService             $callService     The Call Service
     * @param LoggerInterface         $pluginLogger    The plugin version of the logger interface
     * @param GatewayResourceService  $resourceService The Gateway Resource Service.
     * @param MappingService          $mappingService  The Mapping Service
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        GithubPubliccodeService $githubService,
        CallService $callService,
        LoggerInterface $pluginLogger,
        GatewayResourceService $resourceService,
        MappingService $mappingService
    ) {
        $this->callService     = $callService;
        $this->entityManager   = $entityManager;
        $this->githubService   = $githubService;
        $this->pluginLogger    = $pluginLogger;
        $this->resourceService = $resourceService;
        $this->mappingService  = $mappingService;
        $this->yaml            = new Yaml();

        $this->configuration = [];
        $this->data          = [];

    }//end __construct()


    /**
     * Check the auth of the github source.
     *
     * @param Source $source The given source to check the api key.
     *
     * @return bool|null If the api key is set or not.
     */
    public function checkGithubAuth(Source $source): ?bool
    {
        if ($source->getApiKey() === null) {
            $this->pluginLogger->error('No auth set for Source: '.$source->getName().'.', ['plugin' => 'open-catalogi/open-catalogi-bundle']);

            return false;
        }//end if

        return true;

    }//end checkGithubAuth()


    /**
     * This function is searching for repositories containing a publiccode.yaml file.
     *
     * @param string $organizationName
     *
     * @return array|null|Response
     */
    private function getOpenCatalogiFromGithubRepo(string $organizationName): ?array
    {
        // make sync object
        $source = $this->resourceService->getSource('https://opencatalogi.nl/source/oc.GitHubusercontent.source.json', 'open-catalogi/open-catalogi-bundle');

        $possibleEndpoints = [
            '/'.$organizationName.'/.github/main/openCatalogi.yaml',
            '/'.$organizationName.'/.github/main/openCatalogi.yml',
            '/'.$organizationName.'/.github/master/openCatalogi.yaml',
            '/'.$organizationName.'/.github/master/openCatalogi.yml',
        ];

        foreach ($possibleEndpoints as $endpoint) {
            try {
                $response = $this->callService->call($source, $endpoint);
            } catch (Exception $e) {
                $this->pluginLogger->error('Error found trying to fetch '.$endpoint.' '.$e->getMessage());
            }

            if (isset($response) === true) {
                // @TODO use decodeResponse from the callService
                $openCatalogi = $this->yaml->parse($response->getBody()->getContents());
                $this->pluginLogger->debug("Fetch and decode went succesfull '/'.$organizationName.'/.github/master/openCatalogi.yml', '/'.$organizationName.'/.github/master/openCatalogi.yaml'");

                return $openCatalogi;
            }//end if
        }

        return null;

    }//end getOpenCatalogiFromGithubRepo()


    /**
     * This function is searching for repositories containing a publiccode.yaml file.
     *
     * @param string $organizationName used as path to fetch from
     *
     * @throws Exception
     *
     * @return array|null
     */
    private function getGithubRepoFromOrganization(string $organizationName): ?array
    {
        $source = $this->resourceService->getSource('https://opencatalogi.nl/source/oc.GitHubAPI.source.json', 'open-catalogi/open-catalogi-bundle');

        try {
            $response = $this->callService->call($source, '/repos/'.$organizationName.'/.github');
        } catch (Exception $e) {
            $this->pluginLogger->error('Error found trying to fetch /repos/'.$organizationName.'/.github: '.$e->getMessage());
        }

        if (isset($response) === true) {
            $githubRepo = $this->callService->decodeResponse($source, $response, 'application/json');
            $this->pluginLogger->debug('Fetch and decode went succesfull for /repos/'.$organizationName.'/.github');

            return $githubRepo;
        }//end if

        return null;

    }//end getGithubRepoFromOrganization()


    /**
     * Get or create a component for the given repository.
     *
     * @param ObjectEntity $repositoryObject The repository object.
     * @param ObjectEntity $organization     The organisation object.
     * @param string       $type             The type of the organisation.
     *
     * @throws Exception
     *
     * @return array|null
     */
    public function setRepositoryComponent(ObjectEntity $repositoryObject, ObjectEntity $organization, string $type): ?ObjectEntity
    {
        $componentEntity = $this->resourceService->getSchema('https://opencatalogi.nl/oc.component.schema.json', 'open-catalogi/open-catalogi-bundle');

        $component = $repositoryObject->getValue('component');
        if ($component === false) {
            $component = new ObjectEntity($componentEntity);
        }//end if

        $component->hydrate(
            [
                'name'   => $repositoryObject->getValue('name'),
                'url'    => $repositoryObject,
                // Set the organisation to usedBy if type is uses.
                'usedBy' => $type == 'use' ? [$organization] : [],
            ]
        );
        $repositoryObject->setValue('component', $component);
        $this->entityManager->persist($repositoryObject);
        $this->entityManager->persist($component);
        $this->entityManager->flush();

        return $component;

    }//end setRepositoryComponent()


    /**
     * Get an organisation from https://api.github.com/orgs/{org}/repos.
     *
     * @param string       $url          The url of the repository.
     * @param ObjectEntity $organization The organisation object.
     * @param string       $type         The type of the organisation.
     *
     * @throws GuzzleException|LoaderError|SyntaxError
     *
     * @return array|null
     */
    public function getOrganisationRepo(string $url, ObjectEntity $organization, string $type): ?ObjectEntity
    {
        // Do we have a source?
        $source = $this->resourceService->getSource('https://opencatalogi.nl/source/oc.GitHubAPI.source.json', 'open-catalogi/open-catalogi-bundle');
        if ($this->checkGithubAuth($source) === false) {
            return null;
        }//end if

        $domain = \Safe\parse_url($url, PHP_URL_HOST);
        if ($domain !== 'github.com') {
            return null;
        }//end if

        $name = trim(\Safe\parse_url($url, PHP_URL_PATH), '/');

        $this->pluginLogger->debug('Getting repo from organisation '.$name);
        $response = $this->callService->call($source, '/repos/'.$name);

        $repository = json_decode($response->getBody()->getContents(), true);

        if ($repository === null) {
            $this->pluginLogger->error('Could not find a repos from organisation with name: '.$name.' and with source: '.$source->getName());

            return null;
        }//end if

        $repositoryObject = $this->githubService->importRepository($repository);
        $this->entityManager->persist($repositoryObject);
        $this->entityManager->flush();
        $this->pluginLogger->debug('Found repo from organisation with name: '.$name);

        return $this->setRepositoryComponent($repositoryObject, $organization, $type);

    }//end getOrganisationRepo()


    /**
     * Fetches opencatalogi.yaml info with function getOpenCatalogiFromGithubRepo for an organization and updates the given organization.
     *
     * @param ObjectEntity $organization Catalogi organization https://opencatalogi.nl/oc.organisation.schema.json
     *
     * @throws GuzzleException
     *
     * @return void
     */
    public function getOrganizationCatalogi(ObjectEntity $organization): void
    {
        if ($this->getGithubRepoFromOrganization($organization->getValue('name')) === null) {
            return;
        }//end if

        $this->pluginLogger->debug('Github repo found and fetched for '.$organization->getName());

        if (($openCatalogi = $this->getOpenCatalogiFromGithubRepo($organization->getValue('name'))) === null) {
            return;
        }//end if

        $this->pluginLogger->debug('OpenCatalogi.yml or OpenCatalogi.yaml found and fetched for '.$organization->getName());

        $mapping           = $this->resourceService->getMapping('https://api.github.com/oc.githubOpenCatalogiYamlToOrg.mapping.json', 'open-catalogi/open-catalogi-bundle');
        $organizationArray = $this->mappingService->mapping($mapping, $openCatalogi);

        $organization->hydrate($organizationArray);
        $this->entityManager->persist($organization);
        $this->entityManager->flush();

        $uses = [];
        if (key_exists('softwareUsed', $openCatalogi) === true) {
            foreach ($openCatalogi['softwareUsed'] as $use) {
                // Get organisation repos and set the property.
                $uses[] = $this->getOrganisationRepo($use, $organization, 'use');
            }
        }

        $organization->setValue('uses', $uses);

        $supports = [];
        if (key_exists('softwareSupported', $openCatalogi) === true) {
            foreach ($openCatalogi['softwareSupported'] as $support) {
                if (key_exists('software', $support) === false) {
                    continue;
                }

                // Get organisation component and set the property.
                $supports[] = $supportOrganisation = $this->getOrganisationRepo($support['software'], $organization, 'supports');

                if (key_exists('contact', $support) === false) {
                    continue;
                }

                if (key_exists('email', $support['contact']) === true) {
                    $supportOrganisation->setValue('email', $support['contact']['email']);
                }

                if (key_exists('phone', $support['contact']) === true) {
                    $supportOrganisation->setValue('email', $support['contact']['phone']);
                }

                $this->entityManager->persist($supportOrganisation);
            }//end foreach
        }//end if

        $organization->setValue('supports', $supports);

        $this->entityManager->persist($organization);
        $this->entityManager->flush();

        $this->pluginLogger->debug($organization->getName().' succesfully updated with fetched openCatalogi info');

    }//end getOrganizationCatalogi()


    /**
     * Makes sure the action the action can actually runs and then executes functions to update an organization with fetched opencatalogi.yaml info.
     *
     * @param ?array $data          data set at the start of the handler (not needed here)
     * @param ?array $configuration configuration of the action          (not needed here)
     *
     * @throws GuzzleException
     *
     * @return array|null dataset at the end of the handler              (not needed here)
     */
    public function findGithubRepositoryThroughOrganizationHandler(?array $data=[], ?array $configuration=[], ?string $organisationId=null): ?array
    {
        $this->configuration = $configuration;
        $this->data          = $data;

        if ($organisationId !== null) {
            // If we are testing for one repository.
            $organisation = $this->entityManager->find('App:ObjectEntity', $organisationId);
            if ($organisation instanceof ObjectEntity === true
                && $organisation->getValue('name') !== null
                && $organisation->getValue('github') !== null
            ) {
                $this->getOrganizationCatalogi($organisation);
            }//end if

            if ($organisation instanceof ObjectEntity === false) {
                $this->pluginLogger->error('Could not find given organisation');

                return null;
            }//end if
        }

        if ($organisationId === null) {
            $organisationEntity = $this->resourceService->getSchema('https://opencatalogi.nl/oc.organisation.schema.json', 'open-catalogi/open-catalogi-bundle');

            // If we want to do it for al repositories.
            $this->pluginLogger->info('Looping through organisations');
            foreach ($organisationEntity->getObjectEntities() as $organisation) {
                if ($organisation->getValue('name') !== null
                    && $organisation->getValue('github') !== null
                ) {
                    $this->getOrganizationCatalogi($organisation);
                }
            }
        }//end if

        $this->entityManager->flush();

        $this->pluginLogger->debug('findRepositoriesThroughOrganisationHandler finished');

        return $this->data;

    }//end findGithubRepositoryThroughOrganizationHandler()


}//end class
