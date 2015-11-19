<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

/**
 * Tests for plugin quality. All plugins maintained by Piwik or Piwik PRO must
 * pass this test.
 */
class PluginQualityTest extends PHPUnit_Framework_TestCase
{
    private static $knownUIPhpFiles = array(
        "/Controller.php",
        "/Menu.php",
    );

    private $pluginName;
    private $pluginDir;
    private $pluginFiles;
    private $pluginHasUIFiles;
    private $pluginHasNonUIFiles;
    private $pluginSlug;
    private $githubToken;

    public function setUp()
    {
        $this->pluginName = getenv('PLUGIN_NAME');
        if (empty($this->pluginName)) {
            throw new Exception("PLUGIN_NAME environment variable is not set, do not know which plugin to run tests against.");
        }

        $this->githubToken = getenv('GITHUB_USER_TOKEN');
        if (empty($this->githubToken)) {
            throw new Exception("GITHUB_USER_TOKEN environment variable is not set, cannot run some tests.");
        }

        $this->pluginSlug = getenv('TRAVIS_REPO_SLUG');
        if (empty($this->pluginSlug)) {
            throw new Exception("TRAVIS_REPO_SLUG environment variable is not set. Needed for github tests.");
        }

        $this->pluginDir = __DIR__ . '/../../../plugins/' . $this->pluginName;
        if (!is_dir($this->pluginDir)) {
            throw new Exception("Cannot find plugin directory at '{$this->pluginDir}'.");
        }

        $this->pluginFiles = $this->getPluginCodeFiles();
        $this->pluginHasUIFiles = $this->hasUIFiles();
        $this->pluginHasNonUIFiles = $this->hasNonUIFiles();
    }

    public function test_Plugin_HasIntegrationAndSystemTests_IfNonUIFilesExist()
    {
        if (!$this->pluginHasNonUIFiles) {
            return;
        }

        // TODO: check for unit tests?
        $testsDir = $this->getPluginTestsDirectory();
        if (empty($testsDir)) {
            $this->fail("Plugin has no tests.");
        }

        $phpTestTypes = array('Integration', 'System');
        foreach ($phpTestTypes as $type) {
            $numberOfTests = $this->getDirectoryFileCount($testsDir . '/' . $type, '/Test\.php$/');
            $this->assertGreaterThan(0, $numberOfTests, "Plugin has 0 $type tests.");
        }
    }

    public function test_Plugin_HasUITests_IfUIFilesExist()
    {
        if (!$this->pluginHasUIFiles) {
            return;
        }

        $testsDir = $this->getPluginTestsDirectory();
        if (empty($testsDir)) {
            $this->fail("Plugin has no tests.");
        }

        $numberOfUITests = $this->getDirectoryFileCount($testsDir . '/UI', '/_spec\.js$/');
        $this->assertGreaterThan(0, $numberOfUITests, "Plugin has 0 UI tests.");
    }

    public function test_Plugin_HasAtLeastOneScreenshot_IfUIFilesExist()
    {
        if (!$this->pluginHasUIFiles) {
            return;
        }

        $screenshotFileCount = 0;
        foreach ($this->pluginFiles as $file) {
            if (preg_match('/\/screenshots\/.*\.(png|jpeg)$/', $file)) {
                ++$screenshotFileCount;
            }
        }

        $this->assertGreaterThan(0, $screenshotFileCount, "Plugin has UI files but no screenshots.");
    }

    public function test_GithubRepo_HasDescriptionSet()
    {
        $repoDescription = $this->getPluginRepoDescription();
        $this->assertNotEmpty($repoDescription);
    }

    private function getPluginTestsDirectory()
    {
        $possibleDirs = array(
            $this->pluginDir . '/tests',
            $this->pluginDir . '/Test',
        );

        foreach ($possibleDirs as $dir) {
            if (is_dir($dir)) {
                return $dir;
            }
        }

        return null;
    }

    private function getDirectoryFileCount($directory, $regex)
    {
        $count = 0;
        foreach (scandir($directory) as $file) {
            if (preg_match($regex, $file)) {
                ++$count;
            }
        }
        return $count;
    }

    private function getPluginCodeFiles()
    {
        $result = array();

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->pluginDir), RecursiveIteratorIterator::LEAVES_ONLY);

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            $filePath = $file->getPath() . '/' . $file->getBasename();
            if (preg_match('/\.(php|js|twig|less)$/', $filePath)
                && !preg_match('/\/tests|Test\//', $filePath)
            ) {
                $result[] = str_replace($this->pluginDir, "", $filePath);
            }
        }

        return $result;
    }

    private function hasNonUIFiles()
    {
        foreach ($this->pluginFiles as $file) {
            if (!$this->isUIFile($file)) {
                return true;
            }
        }
        return false;
    }

    private function hasUIFiles()
    {
        foreach ($this->pluginFiles as $file) {
            if ($this->isUIFile($file)) {
                return true;
            }
        }
        return false;
    }

    private function isUIFile($file)
    {
        return preg_match('/\.(js|twig|less)$/', $file)
            || in_array($file, self::$knownUIPhpFiles);
    }

    private function getPluginRepoDescription()
    {
        $repoUrl = "https://api.github.com/repos/{$this->pluginSlug}";
        $authHeader = "Authorization: Basic " . base64_encode(":" . $this->githubToken) . "\r\n";

        $context = stream_context_create(array(
            'http' => array(
                'method' => 'GET',
                'header' => $authHeader
            ),
        ));
        $repoInfo = file_get_contents($repoUrl, null, $context);

        return $repoInfo["description"];
    }
}