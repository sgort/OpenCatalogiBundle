<?php

namespace OpenCatalogi\OpenCatalogiBundle\ActionHandler;

use CommonGateway\CoreBundle\ActionHandler\ActionHandlerInterface;
use OpenCatalogi\OpenCatalogiBundle\Service\GithubPubliccodeService;

/**
 * Haalt applications op van de componenten catalogus.
 */
class GithubApiGetPubliccodeRepositoriesHandler implements ActionHandlerInterface
{

    /**
     * @var GithubPubliccodeService
     */
    private GithubPubliccodeService $service;


    /**
     * @param GithubPubliccodeService $service The  githubPubliccodeService
     */
    public function __construct(GithubPubliccodeService $service)
    {
        $this->service = $service;

    }//end __construct()


    /**
     *  This function returns the required configuration as a [json-schema](https://json-schema.org/) array.
     *
     * @return array a [json-schema](https://json-schema.org/) that this  action should comply to
     */
    public function getConfiguration(): array
    {
        return [
            '$id'         => 'https://opencatalogi.nl/ActionHandler/GithubApiGetPubliccodeRepositoriesHandler.ActionHandler.json',
            '$schema'     => 'https://docs.commongateway.nl/schemas/ActionHandler.schema.json',
            'title'       => 'GithubApiGetPubliccodeRepositoriesHandler',
            'description' => 'This is a action to create objects from the fetched applications from the componenten catalogus.',
            'required'    => [],
            'properties'  => [],
        ];

    }//end getConfiguration()


    /**
     * This function runs the application to gateway service plugin.
     *
     * @param array $data          The data from the call
     * @param array $configuration The configuration of the action
     *
     * @return array
     */
    public function run(array $data, array $configuration): array
    {
        return $this->service->getRepositories();

    }//end run()


}//end class
