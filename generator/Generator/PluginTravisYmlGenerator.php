<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\TravisScripts\Generator;

use Exception;
use Piwik\TravisScripts\Generator;
use Symfony\Component\Console\Output\OutputInterface;

class PluginTravisYmlGenerator extends Generator
{
    /**
     * @var string
     */
    private $targetPlugin;

    public function __construct($targetPlugin, $options, OutputInterface $output)
    {
        parent::__construct($options, $output);

        $this->targetPlugin = $targetPlugin;
    }

    protected function travisEncrypt($data)
    {
        $cwd = getcwd();

        // change dir to target plugin since plugin will be in its own git repo
        chdir($this->getPluginRootFolder());

        try {
            $result = parent::travisEncrypt($data);

            chdir($cwd);

            return $result;
        } catch (Exception $ex) {
            chdir($cwd);

            throw $ex;
        }
    }

    public function getTravisYmlOutputPath()
    {
        return $this->getPluginRootFolder() . "/.travis.yml";
    }

    public function getPluginRootFolder()
    {
        return $this->getPiwikRootDir() . "/plugins/{$this->targetPlugin}";
    }

    protected function configureView()
    {
        parent::configureView();

        $this->view->setGenerationMode('plugin');
        $this->view->setPlugin($this->targetPlugin);
        $this->view->setPathToCustomTravisStepsFiles($this->getPluginRootFolder() . "/tests/travis");
        $this->view->setLatestStableVersion($this->getLatestStableVersion());

        $testsToRun = array();
        $testsToExclude = array();

        if ($this->isTargetPluginContainsPluginTests()) {
            $testsToRun[] = array('name' => 'PluginTests',
                'vars' => "MYSQL_ADAPTER=PDO_MYSQL TEST_AGAINST_PIWIK_BRANCH=\$PIWIK_LATEST_STABLE_TEST_TARGET");
            $testsToRun[] = array('name' => 'PluginTests',
                'vars' => "MYSQL_ADAPTER=PDO_MYSQL TEST_AGAINST_CORE=minimum_required_piwik");

            $testsToExclude[] = array('description' => 'execute latest stable tests only w/ PHP 5.5',
                'php' => '5.3',
                'env' => 'TEST_SUITE=PluginTests MYSQL_ADAPTER=PDO_MYSQL TEST_AGAINST_CORE=minimum_required_piwik');
        }

        if ($this->isTargetPluginContainsUITests()) {
            $testsToRun[] = array('name' => 'UITests',
                'vars' => "MYSQL_ADAPTER=PDO_MYSQL TEST_AGAINST_PIWIK_BRANCH=\$PIWIK_LATEST_STABLE_TEST_TARGET");

            $testsToExclude[] = array('description' => 'execute UI tests only w/ PHP 5.6',
                'php' => '5.3.3',
                'env' => 'TEST_SUITE=UITests MYSQL_ADAPTER=PDO_MYSQL TEST_AGAINST_PIWIK_BRANCH=\$PIWIK_LATEST_STABLE_TEST_TARGET');
        }

        if (empty($testsToRun)) {
            throw new Exception("No tests to run for this plugin, aborting .travis.yml generation.");
        }

        $this->view->setTestsToRun($testsToRun);
        $this->view->setTestsToExclude($testsToExclude);
    }

    private function isTargetPluginContainsPluginTests()
    {
        $pluginPath = $this->getPluginRootFolder();
        return $this->doesFolderContainPluginTests($pluginPath . "/tests")
        || $this->doesFolderContainPluginTests($pluginPath . "/Test");
    }

    private function doesFolderContainPluginTests($folderPath)
    {
        return $this->folderContains($folderPath, '.*Test\.php');
    }

    private function isTargetPluginContainsUITests()
    {
        $pluginPath = $this->getPluginRootFolder();
        return $this->doesFolderContainUITests($pluginPath . "/tests")
            || $this->doesFolderContainUITests($pluginPath . "/Test");
    }

    private function doesFolderContainUITests($folderPath)
    {
        return $this->folderContains($folderPath, '.*_spec\.js');
    }

    private function folderContains($folderPath, $filePattern)
    {
        if (!is_dir($folderPath)) {
            return false;
        }

        $directoryIterator = new \RecursiveDirectoryIterator($folderPath);
        $flatIterator = new \RecursiveIteratorIterator($directoryIterator);
        $fileIterator = new \RegexIterator($flatIterator, $filePattern, \RegexIterator::GET_MATCH);

        return $fileIterator->valid();
    }

    private function getLatestStableVersion()
    {
        shell_exec("cd '" . $this->getPiwikRootDir() . "' && git fetch --tags");

        $tags = shell_exec("cd '" . $this->getPiwikRootDir() . "' && git tag -l ");
        $tags = explode("\n", $tags);
        $tags = array_map('trim', $tags);
        $tags = array_filter($tags, function ($value) {
            return preg_match('/[0-9]+\.[0-9]+\.[0-9]+/', $value);
        });
        usort($tags, 'version_compare');

        $latestStable = end($tags);
        $this->log("info", "Testing against latest known stable $latestStable.");
        return $latestStable;
    }
}
