<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

// tiny script to get plugin version from plugin.json from a bash script
require_once __DIR__ . '/../../core/Version.php';

function getRequiredMatomoVersions($pluginJsonContents)
{
    $requiredMatomoVersion = '';
    if (isset($pluginJsonContents["require"]["piwik"])) {
        $requiredMatomoVersion = (string) $pluginJsonContents["require"]["piwik"];
    } else if (isset($pluginJsonContents["require"]["matomo"])) {
        $requiredMatomoVersion = (string) $pluginJsonContents["require"]["matomo"];
    }

    $requiredVersions = explode(',', $requiredMatomoVersion);

    $versions = array();
    foreach ($requiredVersions as $required) {
        if (preg_match('{^(<>|!=|>=?|<=?|==?)\s*(.*)}', $required, $matches)) {
            $comparison = trim($matches[1]);
            $version = $matches[2];

            if (!preg_match("/^[^0-9]*(.*)/", $version)
                || empty($version)
                || version_compare($version, \Piwik\Version::VERSION) > 0) {
                // not a valid version number
                continue;
            }

            $versions[] = array(
                'comparison' => $comparison,
                'version' => $version
            );
        }
    }

    return $versions;
}

function getMinVersion(array $requiredVersions)
{
    $minVersion = '';

    foreach ($requiredVersions as $required) {
        $comparison = $required['comparison'];
        $version    = $required['version'];

        if (in_array($comparison, array('>=','>', '=='))) {
            if (empty($minVersion)) {
                $minVersion = $version;
            } elseif (version_compare($version, $minVersion, '<=')) {
                $minVersion = $version;
            }
        }
    }

    return $minVersion;
}

function getMaxVersion(array $requiredVersions)
{
    $maxVersion = '';

    foreach ($requiredVersions as $required) {
        $comparison = $required['comparison'];
        $version    = $required['version'];

        if ($comparison == '<' && $version == '3.0.0-b1') {
            $maxVersion = trim(file_get_contents('https://api.matomo.org/1.0/getLatestVersion/?release_channel=latest_2x_beta'));
            continue;
        } elseif ($comparison == '<' && $version == '4.0.0-b1') {
            $maxVersion = trim(file_get_contents('https://api.matomo.org/1.0/getLatestVersion/?release_channel=latest_3x_beta'));
            continue;
        } elseif ($comparison == '<' && $version == '5.0.0-b1') {
            $maxVersion = trim(file_get_contents('https://api.matomo.org/1.0/getLatestVersion/?release_channel=latest_4x_beta'));
            continue;
        } elseif ($comparison == '<' && $version == '6.0.0-b1') {
            $maxVersion = trim(file_get_contents('https://api.matomo.org/1.0/getLatestVersion/?release_channel=latest_5x_beta'));
            continue;
        }

        if (in_array($comparison, array('<', '<=', '=='))) {
            if (empty($maxVersion)) {
                $maxVersion = $version;
            } elseif (version_compare($version, $maxVersion, '>=')) {
                $maxVersion = $version;
            }
        }
    }

    return $maxVersion;
}
