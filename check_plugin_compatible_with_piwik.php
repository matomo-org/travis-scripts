<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

require_once __DIR__ . '/../../core/Version.php';

$pluginName = $argv[1];

// at this point in travis the plugin to test against is not in the piwik directory. we could move it to piwik
// beforehand, but for plugins that are also stored as submodules, this would erase the plugin or fail when git
// submodule update is called
$pluginJsonPath = __DIR__ . "/../../../$pluginName/plugin.json";

$pluginJsonContents = file_get_contents($pluginJsonPath);
$pluginJsonContents = json_decode($pluginJsonContents, true);

if (!empty($pluginJsonContents["require"]["piwik"])
    && version_compare(\Piwik\Version::VERSION, $pluginJsonContents["require"]["piwik"]) < 0
) {
    echo "\n******* Plugin $pluginName's minimum required Piwik is > than the test against version "
        . \Piwik\Version::VERSION . " *******\n";
    exit(1);
}
