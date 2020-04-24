<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\TravisScripts;

require_once __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Console\Application;

class GeneratorEntryPoint extends Application
{
    protected function getDefaultCommands()
    {
        $defaultCommands = parent::getDefaultCommands();
        $defaultCommands[] = new GenerateTravisYmlFile();
        return $defaultCommands;
    }
}

$application = new GeneratorEntryPoint();
$application->run();
