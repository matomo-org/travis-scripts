<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
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

        if (empty($this->minimumPhpVersion)) {
            $minimumPhpVersion = $this->getPluginMinimumPhpVersion();

            if (!empty($minimumPhpVersion)) {
                $this->minimumPhpVersion = $this->getPhpVersionKnownToExistOnTravis($minimumPhpVersion);
                $this->replaceMinimumPhpVersionInPhpVersions();
            }
        }
    }

    protected function travisEncrypt($data)
    {
        $cwd = getcwd();

        // change dir to target plugin since plugin will be in its own git repo
        chdir($this->getRepoRootDir());

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
        return $this->getRepoRootDir() . "/.travis.yml";
    }

    public function getRepoRootDir()
    {
        return $this->repoRootDirOverride ?: ($this->getPiwikRootDir() . "/plugins/{$this->targetPlugin}");
    }

    protected function configureView()
    {
        parent::configureView();

        $this->view->setGenerationMode('plugin');
        $this->view->setPlugin($this->targetPlugin);
        $this->view->setPathToCustomTravisStepsFiles($this->getRepoRootDir() . "/tests/travis");
        $this->view->setLatestStableVersion($this->getLatestStableVersion());

        $testsToRun = array();
        $testsToExclude = array();

        if ($this->isTargetPluginContainsPluginTests()) {
            $testsToRun[] = array('name' => 'PluginTests',
                'vars' => "MYSQL_ADAPTER=PDO_MYSQL TEST_AGAINST_PIWIK_BRANCH=\$PIWIK_TEST_TARGET");
            $testsToRun[] = array('name' => 'PluginTests',
                'vars' => "MYSQL_ADAPTER=PDO_MYSQL TEST_AGAINST_CORE=minimum_required_piwik");

            $testsToExclude[] = array('description' => 'execute latest stable tests only w/ PHP ' . $this->minimumPhpVersion,
                'php' => $this->minimumPhpVersion,
                'env' => 'TEST_SUITE=PluginTests MYSQL_ADAPTER=PDO_MYSQL TEST_AGAINST_CORE=minimum_required_piwik');
        }

        if ($this->isTargetPluginContainsUITests()) {
            $testsToRun[] = array('name' => 'UITests',
                'vars' => "MYSQL_ADAPTER=PDO_MYSQL TEST_AGAINST_PIWIK_BRANCH=\$PIWIK_TEST_TARGET");

            $testsToExclude[] = array('description' => 'execute UI tests only w/ PHP ' . $this->minimumPhpVersion,
                'php' => $this->minimumPhpVersion,
                'env' => "TEST_SUITE=UITests MYSQL_ADAPTER=PDO_MYSQL TEST_AGAINST_PIWIK_BRANCH=\$PIWIK_TEST_TARGET");
        }

        if ($this->isTargetPluginContainsJavaScriptTests()) {
            $testsToRun[] = array('name' => 'JavascriptTests',
                'vars' => "MYSQL_ADAPTER=PDO_MYSQL TEST_AGAINST_PIWIK_BRANCH=\$PIWIK_TEST_TARGET");

            $testsToRun[] = array('name' => 'JavascriptTests',
                'vars' => "MYSQL_ADAPTER=PDO_MYSQL TEST_AGAINST_CORE=\minimum_required_piwik");

            $testsToExclude[] = array('description' => 'execute JS tests only w/ PHP ' . $this->minimumPhpVersion,
                'php' => $this->minimumPhpVersion,
                'env' => "TEST_SUITE=UITests MYSQL_ADAPTER=PDO_MYSQL TEST_AGAINST_PIWIK_BRANCH=\$PIWIK_TEST_TARGET");

            $testsToExclude[] = array('description' => 'execute JS tests only w/ PHP ' . $this->minimumPhpVersion,
                'php' => $this->minimumPhpVersion,
                'env' => "TEST_SUITE=UITests MYSQL_ADAPTER=PDO_MYSQL TEST_AGAINST_CORE=\minimum_required_piwik");
        }

        if (empty($testsToRun)) {
            throw new Exception("No tests to run for this plugin, aborting .travis.yml generation.");
        }

        $this->view->setTestsToRun($testsToRun);
        $this->view->setTestsToExclude($testsToExclude);
    }

    private function isTargetPluginContainsPluginTests()
    {
        if ($this->options['force-php-tests']) {
            return true;
        }

        $pluginPath = $this->getRepoRootDir();
        return $this->doesFolderContainPluginTests($pluginPath . "/tests")
        || $this->doesFolderContainPluginTests($pluginPath . "/Test");
    }

    private function doesFolderContainPluginTests($folderPath)
    {
        return $this->folderContains($folderPath, '/.*Test\.php/');
    }

    private function isTargetPluginContainsUITests()
    {
        if ($this->options['force-ui-tests']) {
            return true;
        }

        $pluginPath = $this->getRepoRootDir();
        return $this->doesFolderContainUITests($pluginPath . "/tests")
            || $this->doesFolderContainUITests($pluginPath . "/Test");
    }

    private function isTargetPluginContainsJavaScriptTests()
    {
        $pluginPath = $this->getRepoRootDir();
        return file_exists($pluginPath . "/tests/javascript/index.php")
            || file_exists($pluginPath . "/Test/javascript/index.php");
    }

    private function doesFolderContainUITests($folderPath)
    {
        return $this->folderContains($folderPath, '/.*_spec\.js/');
    }

    private function folderContains($folderPath, $filePattern)
    {
        if (!is_dir($folderPath)) {
            return false;
        }

        $directoryIterator = new \RecursiveDirectoryIterator($folderPath);
        $flatIterator = new \RecursiveIteratorIterator($directoryIterator);
        $fileIterator = new \RegexIterator($flatIterator, $filePattern, \RegexIterator::GET_MATCH);
        $fileIterator->rewind();

        return $fileIterator->valid();
    }

    private function getLatestStableVersion()
    {
        $this->log("info", "Executing git fetch to find latest stable...");
        shell_exec("cd '" . $this->getPiwikRootDir() . "' && git fetch origin 'refs/tags/*:refs/tags/*'");

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

    private function getPluginMinimumPhpVersion()
    {
        $pluginJsonPath = $this->getPluginJsonRootDir();
        if (empty($pluginJsonPath)) {
            $this->log("info", "No plugin.json file found, cannot detect minimum PHP version.");
            return null;
        }

        $pluginJsonContents = file_get_contents($pluginJsonPath);
        $pluginJsonContents = json_decode($pluginJsonContents, $assoc = true);
        if (empty($pluginJsonContents['require']['php'])) {
            $this->log("info", "No PHP version requirement in plugin.json");
            return null;
        }

        $phpRequirement = $pluginJsonContents['require']['php'];
        if (!preg_match('/>=([0-9]+\.[0-9]+\.[0-9]+)/', $phpRequirement, $matches)) {
            $this->log("info", "Cannot detect minimum php version from php requirement: '$phpRequirement'");
            return null;
        }

        $phpRequirement = $matches[1];

        $this->log("info", "Detected minimum PHP version: '$phpRequirement'");

        return $phpRequirement;
    }

    private function getPluginJsonRootDir()
    {
        return $this->getRepoRootDir() . '/plugin.json';
    }

    private function replaceMinimumPhpVersionInPhpVersions()
    {
        $phpVersions = $this->phpVersions;
        uasort($phpVersions, 'version_compare');

        reset($phpVersions);
        $minInArrayKey = key($phpVersions);

        $this->phpVersions[$minInArrayKey] = $this->minimumPhpVersion;
    }
}
