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
    const PRO_HEADER_COMMENT = '/**
 * Copyright (C) Piwik PRO - All rights reserved.
 *
 * Using this code requires that you first get a license from Piwik PRO.
 * Unauthorized copying of this file, via any medium is strictly prohibited.
 *
 * @link http://piwik.pro
 */';

    const OPEN_SOURCE_HEADER_COMMENT = '/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */';

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
    private $pluginJsonContents;

    public function setUp()
    {
        $this->pluginName = getenv('PLUGIN_NAME');
        if (empty($this->pluginName)) {
            throw new Exception("PLUGIN_NAME environment variable is not set, do not know which plugin to run tests against.");
        }

        $this->githubToken = getenv('GITHUB_USER_TOKEN');

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

        $pluginJsonPath = $this->pluginDir . '/plugin.json';
        if (!file_exists($pluginJsonPath)) {
            throw new Exception("plugin.json does not exist");
        }

        $pluginJsonContents = file_get_contents($pluginJsonPath);
        $pluginJsonContents = json_decode($pluginJsonContents, $assoc = true);
        if (empty($pluginJsonContents)) {
            throw new Exception("plugin.json is either empty or invalid JSON");
        }
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

    public function test_PluginDotJsonFile_HasDescriptionMatchingGithubDesc()
    {
        $pluginJsonContents = $this->pluginJsonContents;

        $this->assertArrayHasKey("description", $pluginJsonContents);

        $pluginJsonDescription = $pluginJsonContents["description"];
        $this->assertNotContains("TODO", $pluginJsonDescription);

        $repoDescription = $this->getPluginRepoDescription();
        $this->assertNotEmpty($repoDescription);

        $this->assertEquals($repoDescription, $pluginJsonDescription,
            "Plugin description in plugin.json does not match github repo description.");
    }

    public function test_PluginDotJsonFile_HasPluginName_ThatMatchesPluginNamespace()
    {
        $this->assertArrayHasKey($this->pluginJsonContents, "name");

        $pluginName = $this->pluginJsonContents["name"];
        $this->assertEquals($this->pluginName, $pluginName,
            "Plugin name in plugin.json does not match PLUGIN_NAME env var in travis.");

        foreach ($this->pluginFiles as $file) {
            if (!preg_match('/\.php$/', $file)) {
                continue;
            }

            $fileContents = file_get_contents($this->pluginDir . '/' . $file);
            if (!preg_match('/^namespace\s*Piwik\Plugins\([^\;\s]+)/', $fileContents, $matches)) {
                continue;
            }

            $namespacePluginName = $matches[1];
            $this->assertEquals($pluginName, $namespacePluginName,
                "Plugin name in namespace in file '$file' does not match plugin name in plugin.json.");
        }
    }

    public function test_PluginDotJsonFile_HasPluginVersion_ThatMatchesComposerDotJsonVersion()
    {
        $this->assertArrayHasKey($this->pluginJsonContents, "version");

        $composerJsonPath = $this->pluginDir . '/composer.json';
        if (!file_exists($composerJsonPath)) {
            return;
        }

        $composerJsonContents = file_get_contents($composerJsonPath);
        $composerJsonContents = json_decode($composerJsonContents, $assoc = false);
        if (empty($composerJsonContents)) {
            throw new Exception("composer.json file is either empty or invalid JSON");
        }

        $this->assertArrayHasKey($composerJsonContents, "version");
        $this->assertEquals($this->pluginJsonContents["version"], $composerJsonContents["version"],
            "Version in plugin.json does not match version in composer.json.");
    }

    public function test_PluginDotJsonFile_HasCorrectFields_IfPluginIsOpenSource()
    {
        if ($this->getRepoOwner() != "piwik") {
            return;
        }

        $this->assertArrayHasKey("homepage", $this->pluginJsonContents);
        $this->assertEquals("http://plugins.piwik.org/" . $this->pluginName, $this->pluginJsonContents["homepage"]);

        $this->assertArrayHasKey("authors", $this->pluginJsonContents);
        $this->assertEquals(array("name" => "Piwik", "email" => "hello@piwik.org", "homepage" => "http://piwik.org"),
            $this->pluginJsonContents["authors"]);

        $this->assertArrayHasKey("license", $this->pluginJsonContents);
        $this->assertEquals("GPL v3+", $this->pluginJsonContents["license"]);
    }

    public function test_PluginDotJsonFile_HasCorrectFields_IfPluginIsClosedSource()
    {
        if ($this->getRepoOwner() != "piwikpro") {
            return;
        }

        $this->assertArrayHasKey("homepage", $this->pluginJsonContents);
        $this->assertEquals("https://piwik.pro/plugins", $this->pluginJsonContents["homepage"]);

        $this->assertArrayHasKey("authors", $this->pluginJsonContents);
        $this->assertEquals(array("name" => "Piwik PRO", "email" => "contact@piwik.pro", "homepage" => "https://piwik.pro"),
            $this->pluginJsonContents["authors"]);

        $this->assertArrayHasKey("license", $this->pluginJsonContents);
        $this->assertEquals("Paid plugin", $this->pluginJsonContents["license"]);
    }

    public function test_PluginPhpFiles_HaveProperCommentHeader()
    {
        $expectedHeader = $this->getExpectedPluginHeader();

        foreach ($this->getPluginCodeFiles('.php') as $file) {
            $this->assertPhpFileHasHeader($expectedHeader, $file);
        }
    }

    public function test_PluginJsFiles_HaveProperCommentHeader()
    {
        $expectedHeader = $this->getExpectedPluginHeader();

        foreach ($this->getPluginCodeFiles('.js') as $file) {
            $this->assertJsFileHasHeader($expectedHeader, $file);
        }
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

    private function getPluginCodeFiles($extension = null)
    {
        $result = array();

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->pluginDir), RecursiveIteratorIterator::LEAVES_ONLY);

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            $filePath = $file->getPath() . '/' . $file->getBasename();
            if (!preg_match('/\.(php|js|twig|less)$/', $filePath)
                || preg_match('/\/tests|Test|vendor\//', $filePath)
            ) {
                continue;
            }

            if ($extension !== null
                && !preg_match('/' . preg_quote($extension) . '$/', $filePath)
            ) {
                continue;
            }

            $result[] = str_replace($this->pluginDir, "", $filePath);
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
        $authHeader = "Authorization: Basic " . base64_encode(":" . $this->getGithubToken()) . "\r\n";

        $context = stream_context_create(array(
            'http' => array(
                'method' => 'GET',
                'header' => $authHeader
            ),
        ));
        $repoInfo = file_get_contents($repoUrl, null, $context);

        return $repoInfo["description"];
    }

    private function getRepoOwner()
    {
        $parts = explode("/", $this->pluginSlug);
        return $parts[0];
    }

    private function getExpectedPluginHeader()
    {
        if ($this->getRepoOwner() == 'piwikpro') {
            return self::PRO_HEADER_COMMENT;
        } else {
            return self::OPEN_SOURCE_HEADER_COMMENT;
        }
    }

    private function assertPhpFileHasHeader($expectedHeader, $file)
    {
        $fileContents = file_get_contents($file);
        $this->assertStringStartsWith("<?php\n" . $expectedHeader, $fileContents);
    }

    private function assertJsFileHasHeader($expectedHeader, $file)
    {
        $fileContents = file_get_contents($file);
        $this->assertStringStartsWith($expectedHeader, $fileContents);
    }

    private function getGithubToken()
    {
        if (empty($this->githubToken)) {
            throw new Exception("GITHUB_USER_TOKEN environment variable is not set, cannot run some tests.");
        }
    }
}