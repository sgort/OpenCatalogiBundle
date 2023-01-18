<?php

// src/Service/LarpingService.php
namespace OpenCatalogi\OpenCatalogiBundle\Service;

use App\Entity\Action;
use App\Entity\DashboardCard;
use App\Entity\Cronjob;
use App\Entity\Endpoint;
use App\Entity\Gateway as Source;
use App\Entity\ObjectEntity;
use App\Entity\CollectionEntity;
use CommonGateway\CoreBundle\Installer\InstallerInterface;
use OpenCatalogi\OpenCatalogiBundle\Service\CatalogiService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class InstallationService implements InstallerInterface
{
    private EntityManagerInterface $entityManager;
    private ContainerInterface $container;
    private SymfonyStyle $io;
    private CatalogiService $catalogiService;

    public const OBJECTS_THAT_SHOULD_HAVE_CARDS = [
        'https://opencatalogi.nl/oc.component.schema.json',
        'https://opencatalogi.nl/oc.application.schema.json',
        'https://opencatalogi.nl/oc.catalogi.schema.json'
    ];

    public const SCHEMAS_THAT_SHOULD_HAVE_ENDPOINTS = [
        ['reference' => 'https://opencatalogi.nl/oc.component.schema.json',        'path' => '/components',        'methods' => []],
        ['reference' => 'https://opencatalogi.nl/oc.organisation.schema.json',     'path' => '/organisations',     'methods' => []],
        ['reference' => 'https://opencatalogi.nl/oc.application.schema.json',      'path' => '/applications',      'methods' => []],
        ['reference' => 'https://opencatalogi.nl/oc.catalogi.schema.json',         'path' => '/catalogi',        'methods' => []],
        ['reference' => 'https://opencatalogi.nl/oc.repository.schema.json',       'path' => '/repositories',        'methods' => []],
    ];

    public const ACTION_HANDLERS = [
        //            'OpenCatalogi\OpenCatalogiBundle\ActionHandler\CatalogiHandler',
        'OpenCatalogi\OpenCatalogiBundle\ActionHandler\EnrichPubliccodeHandler',
        'OpenCatalogi\OpenCatalogiBundle\ActionHandler\PubliccodeCheckRepositoriesForPubliccodeHandler',
        'OpenCatalogi\OpenCatalogiBundle\ActionHandler\PubliccodeFindGithubRepositoryThroughOrganizationHandler',
        'OpenCatalogi\OpenCatalogiBundle\ActionHandler\PubliccodeFindOrganizationThroughRepositoriesHandler',
        'OpenCatalogi\OpenCatalogiBundle\ActionHandler\PubliccodeFindRepositoriesThroughOrganizationHandler',
        'OpenCatalogi\OpenCatalogiBundle\ActionHandler\PubliccodeRatingHandler'
    ];

    public function __construct(EntityManagerInterface $entityManager, ContainerInterface $container, CatalogiService $catalogiService)
    {
        $this->entityManager = $entityManager;
        $this->container = $container;
        $this->catalogiService = $catalogiService;
    }

    /**
     * Set symfony style in order to output to the console
     *
     * @param SymfonyStyle $io
     * @return self
     */
    public function setStyle(SymfonyStyle $io):self
    {
        $this->io = $io;

        return $this;
    }

    public function install(){
        $this->checkDataConsistency();
    }

    public function update(){
        $this->checkDataConsistency();
    }

    public function uninstall(){
        // Do some cleanup
    }

    public function addActionConfiguration($actionHandler): array
    {
        $defaultConfig = [];

        // What if there are no properties?
        if(!isset($actionHandler->getConfiguration()['properties'])){
            return $defaultConfig;
        }

        foreach ($actionHandler->getConfiguration()['properties'] as $key => $value) {

            switch ($value['type']) {
                case 'string':
                case 'array':
                    $defaultConfig[$key] = $value['example'];
                    break;
                case 'object':
                    break;
                case 'uuid':
                    if (in_array('$ref', $value) &&
                        $entity = $this->entityManager->getRepository('App:Entity')->findOneBy(['reference' => $value['$ref']])) {
                        $defaultConfig[$key] = $entity->getId()->toString();
                    }
                    break;
                default:
                    // throw error
            }
        }
        return $defaultConfig;
    }

    /**
     * This function creates actions for all the actionHandlers in OpenCatalogi
     *
     * @return void
     */
    public function addActions(): void
    {
        $actionHandlers = $this::ACTION_HANDLERS;
        (isset($this->io)?$this->io->writeln(['','<info>Looking for actions</info>']):'');

        foreach ($actionHandlers as $handler) {
            $actionHandler = $this->container->get($handler);

            if ($this->entityManager->getRepository('App:Action')->findOneBy(['class'=> get_class($actionHandler)])) {

                (isset($this->io)?$this->io->writeln(['Action found for '.$handler]):'');
                continue;
            }

            if (!$schema = $actionHandler->getConfiguration()) {
                continue;
            }

            $defaultConfig = $this->addActionConfiguration($actionHandler);

            $action = new Action($actionHandler);
            $action->setListens(['opencatalogi.default.listens']);
            $action->setConfiguration($defaultConfig);

            $this->entityManager->persist($action);

            (isset($this->io)?$this->io->writeln(['Action created for '.$handler]):'');
        }
    }

    private function createEndpoints($objectsThatShouldHaveEndpoints): array
    {
        $endpointRepository = $this->entityManager->getRepository('App:Endpoint');
        $endpoints = [];
        foreach($objectsThatShouldHaveEndpoints as $objectThatShouldHaveEndpoint) {
            $entity = $this->entityManager->getRepository('App:Entity')->findOneBy(['reference' => $objectThatShouldHaveEndpoint['reference']]);
            if (!$endpointRepository->findOneBy(['name' => $entity->getName()])) {
                $endpoint = new Endpoint($entity, $objectThatShouldHaveEndpoint['path'], $objectThatShouldHaveEndpoint['methods']);

                $this->entityManager->persist($endpoint);
                $this->entityManager->flush();
                $endpoints[] = $endpoint;
            }
        }
        (isset($this->io) ? $this->io->writeln(count($endpoints).' Endpoints Created'): '');

        return $endpoints;
    }

    private function addSchemasToCollection(CollectionEntity $collection, string $schemaPrefix): CollectionEntity
    {
        $entities = $this->entityManager->getRepository('App:Entity')->findByReferencePrefix($schemaPrefix);
        foreach($entities as $entity) {
            $entity->addCollection($collection);
        }
        return $collection;
    }

    private function createCollections(): array
    {
        $collectionConfigs = [
            ['name' => 'OpenCatalogi',  'prefix' => 'oc', 'schemaPrefix' => 'https://opencatalogi.nl'],
        ];
        $collections = [];
        foreach($collectionConfigs as $collectionConfig) {
            $collectionsFromEntityManager = $this->entityManager->getRepository('App:CollectionEntity')->findBy(['name' => $collectionConfig['name']]);
            if(count($collectionsFromEntityManager) == 0){
                $collection = new CollectionEntity($collectionConfig['name'], $collectionConfig['prefix'], 'OpenCatalogiBundle');
            } else {
                $collection = $collectionsFromEntityManager[0];
            }
            $collection = $this->addSchemasToCollection($collection, $collectionConfig['schemaPrefix']);
            $this->entityManager->persist($collection);
            $this->entityManager->flush();
            $collections[$collectionConfig['name']] = $collection;
        }
        (isset($this->io) ? $this->io->writeln(count($collections).' Collections Created'): '');
        return $collections;
    }

    public function createDashboardCards($objectsThatShouldHaveCards)
    {
        foreach ($objectsThatShouldHaveCards as $object) {
            (isset($this->io) ? $this->io->writeln('Looking for a dashboard card for: ' . $object) : '');
            $entity = $this->entityManager->getRepository('App:Entity')->findOneBy(['reference' => $object]);
            if (
                !$dashboardCard = $this->entityManager->getRepository('App:DashboardCard')->findOneBy(['entityId' => $entity->getId()])
            ) {
                $dashboardCard = new DashboardCard();
                $dashboardCard->setType('schema');
                $dashboardCard->setEntity('App:Entity');
                $dashboardCard->setObject('App:Entity');
                $dashboardCard->setName($entity->getName());
                $dashboardCard->setDescription($entity->getDescription());
                $dashboardCard->setEntityId($entity->getId());
                $dashboardCard->setOrdering(1);
                $this->entityManager->persist($dashboardCard);
                (isset($this->io) ? $this->io->writeln('Dashboard card created') : '');
                continue;
            }
            (isset($this->io) ? $this->io->writeln('Dashboard card found') : '');
        }
    }

    public function
    checkDataConsistency(){

        // Lets create some genneric dashboard cards
        $this->createDashboardCards($this::OBJECTS_THAT_SHOULD_HAVE_CARDS);

        $this->createCollections();

        $this->createEndpoints($this::SCHEMAS_THAT_SHOULD_HAVE_ENDPOINTS);

        // Lets see if there is a generic search endpoint
        if(!$searchEnpoint = $this->entityManager->getRepository('App:Endpoint')->findOneBy(['pathRegex'=>'^search'])){
            $searchEnpoint = new Endpoint();
            $searchEnpoint->setName('Search');
            $searchEnpoint->setDescription('Generic Search Endpoint');
            $searchEnpoint->setPathRegex('^search');
            $searchEnpoint->setMethod('GET');
            $searchEnpoint->setMethods(['GET']);
            $searchEnpoint->setOperationType('collection');
            $this->entityManager->persist($searchEnpoint);
        }

        // aanmaken van Actions
        $this->addActions();

        (isset($this->io)?$this->io->writeln(['','<info>Looking for cronjobs</info>']):'');
        // We only need 1 cronjob so lets set that
        if(!$cronjob = $this->entityManager->getRepository('App:Cronjob')->findOneBy(['name'=>'Open Catalogi']))
        {
            $cronjob = new Cronjob();
            $cronjob->setName('Open Catalogi');
            $cronjob->setDescription("This cronjob fires all the open catalogi actions ever 5 minutes");
            $cronjob->setThrows(['opencatalogi.default.listens']);
            $cronjob->setIsEnabled(true);

            $this->entityManager->persist($cronjob);

            (isset($this->io)?$this->io->writeln(['','Created a cronjob for Open Catalogi']):'');
        }
        else {
            (isset($this->io)?$this->io->writeln(['','There is alreade a cronjob for Open Catalogi']):'');
        }

        if(!$cronjob = $this->entityManager->getRepository('App:Cronjob')->findOneBy(['name'=>'Github scrapper']))
        {
            $cronjob = new Cronjob();
            $cronjob->setName('Github scrapper');
            $cronjob->setDescription("This cronjob fires all the open catalogi github actions ever 5 minutes");
            $cronjob->setThrows(['opencatalogi.github']);
            $cronjob->setIsEnabled(true);

            $this->entityManager->persist($cronjob);

            (isset($this->io)?$this->io->writeln(['','Created a cronjob for Github']):'');
        }
        else{

            (isset($this->io)?$this->io->writeln(['','There is alreade a cronjob for Open Catalogi']):'');
        }

        if(!$cronjob = $this->entityManager->getRepository('App:Cronjob')->findOneBy(['name'=>'Federation']))
        {
            $cronjob = new Cronjob();
            $cronjob->setName('Federation');
            $cronjob->setDescription("This cronjob fires all the open catalogi federation actions ever 5 minutes");
            $cronjob->setThrows(['opencatalogi.federation']);
            $cronjob->setIsEnabled(true);

            $this->entityManager->persist($cronjob);

            (isset($this->io)?$this->io->writeln(['','Created a cronjob for Federation
            ']):'');
        }
        else{

            (isset($this->io)?$this->io->writeln(['','There is alreade a cronjob for Federation']):'');
        }

        // Lets grap the catalogi entity
        $catalogiEntity = $this->entityManager->getRepository('App:Entity')->findOneBy(['reference'=>'https://opencatalogi.nl/oc.catalogi.schema.json']);

        // Setup Github and make a dashboard card
        if(!$github = $this->entityManager->getRepository('App:Gateway')->findOneBy(['location'=>'https://api.github.com'])){
            (isset($this->io)?$this->io->writeln(['Creating GitHub Source']):'');
            $github = new Source();
            $github->setName('GitHub');
            $github->setDescription('A place where repositories of code live');
            $github->setLocation('https://api.github.com');
            $github->setAuth('none');
            $this->entityManager->persist($github);

            $dashboardCard = new DashboardCard($github);
            $this->entityManager->persist($dashboardCard);
        }


        // Now we kan do a first federation
        $this->catalogiService->setStyle($this->io);
        //$this->catalogiService->readCatalogi($opencatalogi);

        /*@todo register this catalogi to the federation*/
        // This requers a post to a pre set webhook


        $this->entityManager->flush();
    }
}
