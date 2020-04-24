<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\TravisScripts\Generator;

use Piwik\TravisScripts\Generator;
use Symfony\Component\Console\Output\OutputInterface;

class PiwikTestsPluginsTravisYmlGenerator extends Generator
{
    /**
     * @var string
     */
    private $repoPath;

    /**
     * @param string $repoPath
     * @param string[] $options
     */
    public function __construct($repoPath, $options, OutputInterface $output)
    {
        parent::__construct($options, $output);

        $this->repoPath = $repoPath;
    }

    protected function configureView()
    {
        parent::configureView();

        $this->view->setGenerationMode('piwik-tests-plugins');
        $this->view->setTravisShScriptLocation("./travis.sh");
        $this->view->setPathToCustomTravisStepsFiles($this->getTestsRepoPath() . "/travis");
        $this->view->setTravisShCwd("\$TRAVIS_BUILD_DIR");
    }

    public function getTravisYmlOutputPath()
    {
        return $this->repoPath . '/.travis.yml';
    }

    private function getTestsRepoPath()
    {
        return dirname($this->getTravisYmlOutputPath());
    }

    protected function getOptionsForSelfReferentialCommand()
    {
        $options = parent::getOptionsForSelfReferentialCommand();
        $options['piwik-tests-plugins'] = '..'; // make sure --piwik-tests-plugins is used correctly when executed in travis-ci
        return $options;
    }
}