<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */
namespace Piwik\TravisScripts;

use Exception;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Base class for .travis.yml file generators.
 */
abstract class Generator
{
    const DEFAULT_MINIMUM_PHP_VERSION_TO_TEST = '5.5';
    const DEFAULT_MAXIMUM_PHP_VERSION_TO_TEST = '5.6';

    private static $knownMinorPhpVersionsOnTravis = array(
        // for example '5.3.3'
    );

    /**
     * @var string[]
     */
    protected $options;

    /**
     * @var OutputInterface
     */
    private $output;

    /**
     * @var TravisYmlView
     */
    protected $view;

    /**
     * @var string
     */
    protected $repoRootDirOverride = null;

    /**
     * @var string[]
     */
    protected $phpVersions = array(
        self::DEFAULT_MAXIMUM_PHP_VERSION_TO_TEST,
        self::DEFAULT_MINIMUM_PHP_VERSION_TO_TEST,
    );

    /**
     * @var string
     */
    protected $minimumPhpVersion;

    /**
     * Constructor.
     *
     * @param string[] $options The string options applied to the generate:travis-yml command.
     */
    public function __construct($options, OutputInterface $output)
    {
        $this->options = $options;
        $this->output = $output;
        $this->repoRootDirOverride = @$options['repo-root-dir'];

        if (!empty($this->options['php-versions'])) {
            $phpVersions = explode(',', $this->options['php-versions']);
            $this->phpVersions = $phpVersions;

            $this->setMinimumPhpVersionFromPhpVersions();
        }

        $this->view = new TravisYmlView();
    }

    /**
     * Generates the contents of a .travis.yml file and returns them.
     *
     * @return string
     */
    public function generate()
    {
        $this->configureView();

        return $this->view->render();
    }

    /**
     * Writes the contents of a .travis.yml file to the correct destination. If the --dump option
     * is specified, the file is saved here instead of the .travis.yml file it should be saved to.
     *
     * @param string $travisYmlContents
     * @return string Returns the path of the file that was written to.
     * @throws Exception if the path being written is not writable.
     */
    public function dumpTravisYmlContents($travisYmlContents)
    {
        $writePath = @$this->options['dump'];
        if (empty($writePath)) {
            $writePath = $this->getTravisYmlOutputPath();
        }

        if (!is_writable(dirname($writePath))) {
            throw new Exception("Cannot write to '$writePath'!");
        }

        file_put_contents($writePath, $travisYmlContents);

        return $writePath;
    }

    /**
     * Returns the path of the .travis.yml file we are generating. The --dump option has no effect on
     * this path.
     */
    public abstract function getTravisYmlOutputPath();

    protected function configureView()
    {
        if (empty($this->minimumPhpVersion)) {
            $this->setMinimumPhpVersionFromPhpVersions();
        }

        $this->log("info", "Using minimum PHP version: {$this->minimumPhpVersion}");

        $thisConsoleCommand = $this->getExecutedConsoleCommandForTravis();
        $this->view->setGenerateYmlCommand($thisConsoleCommand);

        if (!empty($this->phpVersions)) {
            $phpVersions = $this->getPhpVersionsKnownToExistOnTravis();
            $this->view->setPhpVersions($phpVersions);
        }

        $outputYmlPath = $this->getTravisYmlOutputPath();
        if (file_exists($outputYmlPath)) {
            $this->log('info', "Found existing YAML file at $outputYmlPath.");

            $parser = new Parser();
            $existingSections = $parser->processExistingTravisYml($outputYmlPath);
            $this->view->setExistingSections($existingSections);
        } else {
            $this->log('info', "Could not find existing YAML file at $outputYmlPath, generating a new one.");
        }

        $this->setExtraEnvironmentVariables();

        if (!empty($this->options['sudo-false'])) {
            $this->view->useNewTravisInfrastructure();
        }
    }

    protected function travisEncrypt($data)
    {
        $this->log('info', "Encrypting \"$data\"...");

        $command = "travis encrypt \"$data\"";

        exec($command, $commandOutput, $returnCode);
        if ($returnCode !== 0) {
            throw new Exception("Cannot encrypt \"$data\" for travis! Please make sure you have the travis command line "
                . "utility installed (see http://blog.travis-ci.com/2013-01-14-new-client/).\n\n"
                . "return code: $returnCode\n\n"
                . "travis output:\n\n" . implode("\n", $commandOutput));
        }

        if (empty($commandOutput)) {
            throw new Exception("Cannot parse travis encrypt output:\n\n" . implode("\n", $commandOutput));
        }

        // when not executed from a command line travis encrypt will return only the encrypted data
        $encryptedData = $commandOutput[0];

        if (substr($encryptedData, 0, 1) != '"'
            || substr($encryptedData, -1) != '"'
        ) {
            $encryptedData = '"' . addslashes($encryptedData) . '"';
        }

        return "secure: " . $encryptedData;
    }

    protected function getExecutedConsoleCommandForTravis()
    {
        $command = "php ./tests/travis/generator/main.php " . GenerateTravisYmlFile::COMMAND_NAME;

        $options = $this->getOptionsForSelfReferentialCommand();

        foreach ($options as $name => $value) {
            if ($value === false
                || $value === null
            ) {
                continue;
            }

            if ($value === true) {
                $command .= " --$name";
            } else if (is_array($value)) {
                foreach ($value as $arrayValue) {
                    $command .= " --$name=\"" . addslashes($arrayValue) . "\"";
                }
            } else {
                $command .= " --$name=\"" . addslashes($value) . "\"";
            }
        }

        return $command;
    }

    private function setExtraEnvironmentVariables()
    {
        if (!empty($this->view->variables['existingEnv'])) {
            $this->log('info', "Existing .yml file found, ignoring global variables specified on command line.");
            return;
        }

        $extraVars = array();

        $artifactsPass = @$this->options['artifacts-pass'];
        if (!empty($artifactsPass)) {
            $extraVars[] = $this->travisEncrypt("ARTIFACTS_PASS=" . $artifactsPass);
        }

        $githubToken = @$this->options['github-token'];
        if (!empty($githubToken)) {
            $extraVars[] = $this->travisEncrypt("GITHUB_USER_TOKEN=" . $githubToken);
        }

        $this->view->setExtraGlobalEnvVars($extraVars);
    }

    protected function getOptionsForSelfReferentialCommand()
    {
        $options = $this->options;
        unset($options['github-token']);
        unset($options['artifacts-pass']);
        unset($options['dump']);
        unset($options['repo-root-dir']);
        $options['verbose'] = true;
        return $options;
    }

    protected function log($level, $message)
    {
        if ($this->output) {
            $this->output->writeln("<info>[$level] $message</info>");
        }
    }

    protected function getPiwikRootDir()
    {
        return __DIR__ . "/../../..";
    }

    private function setMinimumPhpVersionFromPhpVersions()
    {
        $phpVersions = $this->phpVersions;
        usort($phpVersions, 'version_compare');
        $this->minimumPhpVersion = reset($phpVersions);
    }

    private function getPhpVersionsKnownToExistOnTravis()
    {
        $self = $this;
        return array_map(function ($version) use ($self) {
            if (in_array($version, Generator::$knownMinorPhpVersionsOnTravis)
                || substr_count($version, ".") < 2
            ) {
                return $version;
            } else {
                $parts = explode('.', $version, 3);
                $parts = array($parts[0], $parts[1]);
                $versionWithoutPatch =  implode('.', $parts);

                $self->log("info", "Version '$version' is not known to be available on travis, using '$versionWithoutPatch'.");

                return $versionWithoutPatch;
            }
        }, $this->phpVersions);
    }
}