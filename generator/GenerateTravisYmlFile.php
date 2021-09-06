<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\TravisScripts;

use Piwik\TravisScripts\Generator\CoreTravisYmlGenerator;
use Piwik\TravisScripts\Generator\PiwikTestsPluginsTravisYmlGenerator;
use Piwik\TravisScripts\Generator\PluginTravisYmlGenerator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Exception;

/**
 * Command to generate an self-updating .travis.yml file either for Matomo Core or
 * an individual Matomo plugin.
 */
class GenerateTravisYmlFile extends Command
{
    const COMMAND_NAME = 'generate:travis-yml';

    protected function configure()
    {
        $this->setName(self::COMMAND_NAME)
            ->setDescription('Generates a .travis.yml file for a plugin. The file can be auto-updating based on the parameters supplied.')
            ->addOption('plugin', null, InputOption::VALUE_REQUIRED, 'The plugin for whom a .travis.yml file should be generated.')
            ->addOption('core', null, InputOption::VALUE_NONE, 'Supplied when generating the .travis.yml file for Matomo core.'
                . ' Should only be used by core developers.')
            ->addOption('piwik-tests-plugins', null, InputOption::VALUE_REQUIRED, 'Supplied when generating the .travis.yml file for the '
                . 'piwik-tests-plugins repository. Should only be used by core developers.')
            ->addOption('artifacts-pass', null, InputOption::VALUE_REQUIRED,
                "Password to the Matomo build artifacts server. Will be encrypted in the .travis.yml file.")
            ->addOption('github-token', null, InputOption::VALUE_REQUIRED,
                "Github token of a user w/ push access to this repository. Used to auto-commit updates to the "
                . ".travis.yml file and checkout dependencies. Will be encrypted in the .travis.yml file.\n\n"
                . "If not supplied, the .travis.yml will fail the build if it needs updating.")
            ->addOption('php-versions', null, InputOption::VALUE_OPTIONAL,
                "List of PHP versions to test against, ie, 5.4,5.5,5.6. Defaults to: 5.3.3,5.4,5.5,5.6.")
            ->addOption('dump', null, InputOption::VALUE_REQUIRED, "Debugging option. Saves the output .travis.yml to the specified file.")
            ->addOption('repo-root-dir', null, InputOption::VALUE_REQUIRED, "Path to the repo for whom a .travis.yml file will be generated for.")
            ->addOption('force-php-tests', null, InputOption::VALUE_NONE, "Forces the presence of the PHP tests jobs for plugin builds.")
            ->addOption('force-ui-tests', null, InputOption::VALUE_NONE, "Forces the presence of the UI tests jobs for plugin builds.")
            ->addOption('dist-trusty', null, InputOption::VALUE_NONE, "Just for backwards compatibility, using travis' trusty distribution is the default now.")
            ->addOption('distribution', null, InputOption::VALUE_REQUIRED, "If supplied, the .travis.yml file will use the given travis' distribution. Possible values are trusty, xenial or bionic")
            ->addOption('sudo-false', null, InputOption::VALUE_NONE, "If supplied, the .travis.yml file will use travis' container environment.");
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $generator = $this->createTravisYmlGenerator($input, $output);
        $travisYmlContents = $generator->generate();

        $writePath = $generator->dumpTravisYmlContents($travisYmlContents);
        $output->writeln("<info>Generated .travis.yml file at '$writePath'!</info>");
    }

    private function createTravisYmlGenerator(InputInterface $input, OutputInterface $output)
    {
        $allOptions = $input->getOptions();

        $isCore = $input->getOption('core');
        if ($isCore) {
            return new CoreTravisYmlGenerator($allOptions, $output);
        }

        $targetPlugin = $input->getOption('plugin');
        if ($targetPlugin) {
            return new PluginTravisYmlGenerator($targetPlugin, $allOptions, $output);
        }

        $piwikTestsPluginPath = $input->getOption('piwik-tests-plugins');
        if ($piwikTestsPluginPath) {
            return new PiwikTestsPluginsTravisYmlGenerator($piwikTestsPluginPath, $allOptions,$output);
        }

        throw new Exception("Neither --plugin option, --core option or --piwik-tests-plugins specified; don't know what type"
            . " of .travis.yml file to generate. Execute './console help generate:travis-yml' for more info");
    }
}