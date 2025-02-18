<?php

namespace OpenCatalogi\OpenCatalogiBundle\Command;

use OpenCatalogi\OpenCatalogiBundle\Service\ComponentenCatalogusService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Command to execute the FindGithubRepositoryThroughOrganizationService.
 */
class ComponentenCatalogusGetComponentsCommand extends Command
{

    /**
     * @var string
     */
    protected static $defaultName = 'opencatalogi:componentencatalogus:components';

    /**
     * @var ComponentenCatalogusService
     */
    private ComponentenCatalogusService $compCatService;


    /**
     * @param ComponentenCatalogusService $compCatService componenten Catalogus Service
     */
    public function __construct(ComponentenCatalogusService $compCatService)
    {
        $this->compCatService = $compCatService;
        parent::__construct();

    }//end __construct()


    /**
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setDescription('This command triggers OpenCatalogi ComponentenCatalogusService')
            ->setHelp('This command allows you to get all components or one component from componentencatalogus.commonground.nl/api/components')
            ->addOption('component', 'c', InputOption::VALUE_OPTIONAL, 'Get a single component by id');

    }//end configure()


    /**
     * @param InputInterface  $input  The input
     * @param OutputInterface $output The output
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $style = new SymfonyStyle($input, $output);

        // Handle the command options.
        $componentId = $input->getOption('component', false);

        $style->info('Execute getComponents');

        if ($componentId === null) {
            if (empty($this->compCatService->getComponents()) === true) {
                return Command::FAILURE;
            }
        }

        if ($componentId !== null
            && empty($this->compCatService->getComponent($componentId)) === true
        ) {
            return Command::FAILURE;
        }

        return Command::SUCCESS;

    }//end execute()


}//end class
