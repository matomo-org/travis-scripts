<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\TravisScripts\tests\Integration;

use PHPUnit\Framework\TestCase;
use Piwik\TravisScripts\Parser;
use Piwik\TravisScripts\TravisYmlView;
use Spyc;

/**
 * @group TestRunner
 * @group TestRunner_TravisYmlViewTest
 */
class TravisYmlViewTest extends TestCase
{
    public function testViewGeneratesCorrectLookingYAML()
    {
        $view = new TravisYmlView();
        $view->setGenerationMode('plugin');
        $view->setPlugin('ExamplePlugin');
        $view->setExtraGlobalEnvVars(array('secure: artifactspass', 'secure: githubtoken'));
        $view->setGenerateYmlCommand('./console generate:travis-yml \'arg1\' arg2');
        $view->setPathToCustomTravisStepsFiles(PIWIK_INCLUDE_PATH . '/plugins/ExamplePlugin/tests/travis');
        $view->setLatestStableVersion('2.14.0');
        $view->setTestsToRun(array(
            array('name' => "PluginTests", 'vars' => "MYSQL_ADAPTER=PDO_MYSQL"),
            array('name' => "PluginTests", 'vars' => "MYSQL_ADAPTER=PDO_MYSQL TEST_AGAINST_CORE=latest_stable")
        ));
        $view->useTravisContainerEnvironment();
        $output = $view->render();

        $yaml = Spyc::YAMLLoadString($output);

        $this->assertNotEmpty($yaml['env']);
        $this->assertNotEmpty($yaml['env']['global']);
        $this->assertContains("PLUGIN_NAME=ExamplePlugin", $yaml['env']['global']);
        $this->assertContains("PIWIK_ROOT_DIR=\$TRAVIS_BUILD_DIR/piwik", $yaml['env']['global']);
        $this->assertContains(array('secure' => 'artifactspass'), $yaml['env']['global']);
        $this->assertContains(array('secure' => 'githubtoken'), $yaml['env']['global']);

        $this->assertNotEmpty($yaml['env']['matrix']);
        $this->assertContains("TEST_SUITE=PluginTests MYSQL_ADAPTER=PDO_MYSQL", $yaml['env']['matrix']);
        $this->assertContains("TEST_SUITE=PluginTests MYSQL_ADAPTER=PDO_MYSQL TEST_AGAINST_CORE=latest_stable", $yaml['env']['matrix']);
        $this->assertNotContains("TEST_SUITE=UITests MYSQL_ADAPTER=PDO_MYSQL", $yaml['env']['matrix']);

        $this->assertEquals('trusty', $yaml['dist']);
        $this->assertEquals(false, $yaml['sudo']);

        $this->assertBuildSectionsNotEmpty($yaml);

        $this->assertContains("export GENERATE_TRAVIS_YML_COMMAND=\"./console generate:travis-yml \\'arg1\\' arg2\"", $yaml['install']);

        $this->assertViewUsesPluginSpecifiedTravisCommands($yaml);
        $this->assertViewUsesPluginSpecifiedAptPackages($yaml);
    }

    public function testViewPreservesCommentsAndEnvVarsIfExistingYml()
    {
        $view = new TravisYmlView();
        $view->setGenerationMode('plugin');
        $view->setPlugin('ExamplePlugin');
        $view->setExtraGlobalEnvVars(array('secure: artifactspass', 'secure: githubtoken'));
        $view->setGenerateYmlCommand('./console generate:travis-yml arg1 arg2');
        $view->setPathToCustomTravisStepsFiles(PIWIK_INCLUDE_PATH . '/plugins/ExamplePlugin/tests/travis');

        $parser = new Parser();
        $existingSections = $parser->processExistingTravisYml(__DIR__ . '/../resources/test.travis.yml');
        $view->setExistingSections($existingSections);

        $output = $view->render();

        $yaml = Spyc::YAMLLoadString($output);

        $this->assertNotEmpty($yaml['env']);
        $this->assertNotEmpty($yaml['env']['global']);

        $this->assertContains("PRESERVED_VAR=123", $yaml['env']['global']);
        $this->assertContains(array('secure' => 'anotherpreservedvar'), $yaml['env']['global']);
        $this->assertNotContains("PLUGIN_NAME=ExamplePlugin", $yaml['env']['global']);
        $this->assertNotContains("PIWIK_ROOT_DIR=\$TRAVIS_BUILD_DIR/piwik", $yaml['env']['global']);

        $this->assertEquals('required', $yaml['sudo']);

        $this->assertBuildSectionsNotEmpty($yaml);

        $this->assertNotEmpty($yaml['custom_section']);
        $this->assertContains("custom_section:\n  - this will be preserved\n  # as should this", $output);

        $this->assertNotEmpty($yaml['notifications']);
        $this->assertContains("notifications:\n  # another section\n  - a\n  - b\n  - c", $output);

        $this->assertViewUsesPluginSpecifiedTravisCommands($yaml);
        $this->assertViewUsesPluginSpecifiedAptPackages($yaml);
    }

    public function testViewGeneratesCorrectLookingYAMLForCore()
    {
        $view = new TravisYmlView();
        $view->setGenerationMode('core');

        // no setPlugin call here signifies generating for core
        $parser = new Parser();
        $existingSections = $parser->processExistingTravisYml(__DIR__ . '/../resources/test.travis.yml');
        $view->setExistingSections($existingSections);

        $view->setExtraGlobalEnvVars(array('secure: artifactspass', 'secure: githubtoken'));
        $view->setGenerateYmlCommand('./console generate:travis-yml \'arg1\' arg2');
        $output = $view->render();

        $yaml = Spyc::YAMLLoadString($output);

        $this->assertNotEmpty($yaml['env']);
        $this->assertNotEmpty($yaml['env']['global']);

        $this->assertBuildSectionsNotEmpty($yaml);

        $this->assertViewDoesNotUsePluginSpecifiedTravisCommands($yaml);
        $this->assertViewDoesNotUsePluginSpecifiedAptPackages($yaml);
    }

    public function testViewGeneratesCorrectLookingYAMLWhenCustomPhpVersionsUsed()
    {
        $view = new TravisYmlView();
        $view->setGenerationMode('plugin');
        $view->setPlugin('ExamplePlugin');
        $view->setPhpVersions(array('5.4', '5.6', 'hhvm'));
        $view->setLatestStableVersion('2.14.0');
        $view->setGenerateYmlCommand('./console generate:travis-yml arg1 arg2');
        $output = $view->render();

        $yaml = Spyc::YAMLLoadString($output);

        $this->assertNotEmpty($yaml['php']);
        $this->assertEquals(array('5.4', '5.6', 'hhvm'), $yaml['php']);
    }

    private function assertBuildSectionsNotEmpty($yaml)
    {
        $this->assertNotEmpty($yaml['before_install']);
        $this->assertNotEmpty($yaml['install']);
        $this->assertNotEmpty($yaml['before_script']);
        $this->assertNotEmpty($yaml['after_script']);
        $this->assertNotEmpty($yaml['after_success']);
    }

    private function assertViewUsesPluginSpecifiedTravisCommands($yaml)
    {
        $this->assertEquals("before_install hook line 1", reset($yaml['before_install']));
        $this->assertEquals("before_install hook line 2", end($yaml['before_install']));

        $this->assertEquals("before_script hook line 1", reset($yaml['before_script']));
        $this->assertEquals("before_script hook line 2", end($yaml['before_script']));

        $this->assertEquals("install hook line 1", reset($yaml['install']));
        $this->assertEquals("install hook line 2", end($yaml['install']));

        $this->assertEquals("after_success hook line 1", reset($yaml['after_success']));
        $this->assertEquals("after_success hook line 2", end($yaml['after_success']));

        $this->assertEquals("after_script hook line 1", reset($yaml['after_script']));
        $this->assertEquals("after_script hook line 2", end($yaml['after_script']));
    }

    private function assertViewDoesNotUsePluginSpecifiedTravisCommands($yaml)
    {
        $this->assertNotEquals("before_install hook line 1", reset($yaml['before_install']));
        $this->assertNotEquals("before_install hook line 2", end($yaml['before_install']));

        $this->assertNotEquals("before_script hook line 1", reset($yaml['before_script']));
        $this->assertNotEquals("before_script hook line 2", end($yaml['before_script']));

        $this->assertNotEquals("install hook line 1", reset($yaml['install']));
        $this->assertNotEquals("install hook line 2", end($yaml['install']));

        $this->assertNotEquals("after_success hook line 1", reset($yaml['after_success']));
        $this->assertNotEquals("after_success hook line 2", end($yaml['after_success']));

        $this->assertNotEquals("after_script hook line 1", reset($yaml['after_script']));
        $this->assertNotEquals("after_script hook line 2", end($yaml['after_script']));
    }

    private function assertViewUsesPluginSpecifiedAptPackages($yaml)
    {
        $sources = $yaml['addons']['apt']['sources'];
        $packages = $yaml['addons']['apt']['packages'];

        $this->assertContains('custom apt source', $sources);
        $this->assertContains('custom apt package', $packages);
        $this->assertContains('another custom apt package', $packages);
    }

    private function assertViewDoesNotUsePluginSpecifiedAptPackages($yaml)
    {
        $sources = $yaml['addons']['apt']['sources'];
        $packages = $yaml['addons']['apt']['packages'];

        $this->assertNotContains('custom apt source', $sources);
        $this->assertNotContains('custom apt package', $packages);
        $this->assertNotContains('another custom apt package', $packages);
    }
}