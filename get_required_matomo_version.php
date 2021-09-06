<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

$pluginName = $argv[1];
$returnMaxVersion = !empty($argv[2]) && $argv[2] === 'max';

// tiny script to get plugin version from plugin.json from a bash script
require_once __DIR__ . '/../../core/Version.php';
require_once __DIR__ . '/matomo_version_parser.php';

// at this point in travis the plugin to test against is not in the piwik directory. we could move it to piwik
// beforehand, but for plugins that are also stored as submodules, this would erase the plugin or fail when git
// submodule update is called
$pluginJsonPath     = __DIR__ . "/../../../$pluginName/plugin.json";
$pluginJsonContents = file_get_contents($pluginJsonPath);
$pluginJsonContents = json_decode($pluginJsonContents, true);

$requiredVersions = getRequiredMatomoVersions($pluginJsonContents);

if ($returnMaxVersion) {
    $versionToReturn = getMaxVersion($requiredVersions);

    if (empty($versionToReturn)) {
        $versionToReturn = trim(file_get_contents('https://api.matomo.org/LATEST_BETA'));
    }
} else {
    $versionToReturn = getMinVersion($requiredVersions);
}

if (empty($versionToReturn)) {
    $versionToReturn = "master";
}

echo $versionToReturn;
