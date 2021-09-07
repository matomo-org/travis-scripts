<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

require_once __DIR__ . '/../../core/Version.php';
require_once __DIR__ . '/matomo_version_parser.php';

$pluginName = $argv[1];

// at this point in travis the plugin to test against is not in the piwik directory. we could move it to piwik
// beforehand, but for plugins that are also stored as submodules, this would erase the plugin or fail when git
// submodule update is called
$pluginJsonPath = __DIR__ . "/../../../$pluginName/plugin.json";

$pluginJsonContents = file_get_contents($pluginJsonPath);
$pluginJsonContents = json_decode($pluginJsonContents, true);

$requiredVersions = getRequiredMatomoVersions($pluginJsonContents);

foreach ($requiredVersions as $requiredVersion) {
    $comparison = $requiredVersion['comparison'];
    $version = $requiredVersion['version'];

    if (!empty($version)
        && preg_match("/^[^0-9]*(.*)/", $version, $matches)
        && !empty($matches[1])
        && version_compare(\Piwik\Version::VERSION, $matches[1]) < 0
    ) {
        echo "\n******* Plugin $pluginName's required Piwik ('$comparison$version') is > than the test against version "
            . \Piwik\Version::VERSION . " *******\n";
        echo "Did you bump your plugin's required Piwik to a version that doesn't exist yet? Make sure the "
            . "PIWIK_TEST_TARGET environment variable is set to the right version, branch or commit hash in your "
            . ".travis.yml.\n";
        exit(1);
    } else {
        echo "Plugin $pluginName's required Piwik ('$comparison$version') is less than the test against version "
            . \Piwik\Version::VERSION . "\n";
    }

}
