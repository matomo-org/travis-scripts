<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\TravisScripts\Generator;

use Piwik\TravisScripts\Generator;

class CoreTravisYmlGenerator extends Generator
{
    protected function configureView()
    {
        parent::configureView();

        $this->view->setGenerationMode('core');
    }

    public function getTravisYmlOutputPath()
    {
        return $this->getRepoRootDir() . '/.travis.yml';
    }

    private function getRepoRootDir()
    {
        return $this->repoRootDirOverride ?: $this->getPiwikRootDir();
    }
}