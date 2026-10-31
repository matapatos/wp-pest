<?php

/**
 * WordPress integration tests with PestPHP.
 *
 * @package MadeByDenis\\WpPestIntegrationTestSetup
 * @link    https://github.com/dingo-d/wp-pest-integration-test-setup
 * @license https://opensource.org/licenses/MIT MIT
 */

declare(strict_types=1);

namespace MadeByDenis\WpPestIntegrationTestSetup\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Composer-based setup command that scaffolds the test suites without templating
 *
 * Instead of downloading WordPress and rendering .tmpl files, this command copies
 * static, placeholder-free files into the project and lets Composer install
 * WordPress core and the SQLite drop-in via installer-paths.
 *
 * @package MadeByDenis\WpPestIntegrationTestSetup
 *
 * @since 1.9.0
 */
#[AsCommand(name: 'setup:composer')]
class ComposerSetupCommand extends Command
{
	/**
	 * Project type argument string
	 *
	 * @since 1.9.0
	 *
	 * @var string
	 */
	private const PROJECT_TYPE = 'project-type';

	/**
	 * WordPress version option string
	 *
	 * @since 1.9.0
	 *
	 * @var string
	 */
	private const WP_VERSION = 'wp-version';

	/**
	 * Root path of the project
	 *
	 * @since 1.9.0
	 *
	 * @var string
	 */
	private string $rootPath;

	/**
	 * Filesystem dependency
	 *
	 * @since 1.9.0
	 *
	 * @var Filesystem
	 */
	private Filesystem $filesystem;

	/**
	 * Command class constructor
	 *
	 * @since 1.9.0
	 *
	 * @param string $rootPath Root path of the project.
	 * @param Filesystem $filesystem Symfony filesystem dependency.
	 */
	public function __construct(string $rootPath, Filesystem $filesystem)
	{
		$this->rootPath = $rootPath;
		$this->filesystem = $filesystem;

		parent::__construct();
	}

	/**
	 * Configures the current command
	 *
	 * @since 1.9.0
	 *
	 * @return void
	 */
	protected function configure(): void
	{
		$this
			->setDescription('Sets up the test suites using Composer installer-paths (no templating).')
			->setHelp(
				'This command scaffolds static test files and prints the composer.json snippets ' .
				'needed to install WordPress core and the SQLite drop-in via installer-paths.'
			)
			->addArgument(
				self::PROJECT_TYPE,
				InputArgument::REQUIRED,
				'Select whether you want to setup tests for theme or a plugin. Can be "theme" or "plugin"'
			)
			->addOption(
				self::WP_VERSION,
				null,
				InputOption::VALUE_OPTIONAL,
				'WordPress version constraint to suggest in the printed composer.json snippet.',
				'latest'
			);
	}

	/**
	 * Executes the current command
	 *
	 * @param InputInterface $input Command input values.
	 * @param OutputInterface $output Command output.
	 *
	 * @since 1.9.0
	 *
	 * @return int
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$io = new SymfonyStyle($input, $output);
		$ds = DIRECTORY_SEPARATOR;

		$projectType = $input->getArgument(self::PROJECT_TYPE);

		if (!in_array($projectType, ['theme', 'plugin'], true)) {
			// @phpstan-ignore-next-line
			$io->error("The argument must either be 'theme' or 'plugin', $projectType provided.");

			return Command::FAILURE;
		}

		$wpVersion = $input->getOption(self::WP_VERSION);
		$wpVersion = !empty($wpVersion) ? $wpVersion : 'latest';

		$testsDir = $this->rootPath . $ds . 'tests';

		if ($this->filesystem->exists($testsDir)) {
			$io->info('tests/ directory already exists. Moving on.');
		} else {
			try {
				$this->setUpStaticTestFiles($testsDir, $projectType);
				$io->success('Folder and files created successfully.');
			} catch (IOException $exception) {
				$io->error("Error happened when creating files and folders at {$exception->getPath()}. " .
					"Error message: {$exception->getMessage()}");

				return Command::FAILURE;
			}
		}

		$this->printComposerInstructions($io, $projectType, $wpVersion);

		$io->success("All done! Add the composer.json snippet below, run `composer install`, then write tests \ud83d\ude04");

		return Command::SUCCESS;
	}

	/**
	 * Copies the static (non-templated) test files into the project
	 *
	 * @param string $testsPath Path to the tests directory.
	 * @param string $projectType Type of project, theme or plugin.
	 *
	 * @since 1.9.0
	 *
	 * @return void
	 * @throws IOException Throws exception in case something fails with fs operations.
	 */
	private function setUpStaticTestFiles(string $testsPath, string $projectType): void
	{
		$ds = DIRECTORY_SEPARATOR;

		$this->filesystem->mkdir($testsPath . $ds . 'Unit', 0755, true);
		$this->filesystem->mkdir($testsPath . $ds . 'Integration', 0755, true);

		$templatesFolder = dirname(__FILE__, 3) . $ds . 'templates' . $ds . 'static';

		$bootstrap = ($projectType === 'theme') ? 'bootstrap-theme.php' : 'bootstrap-plugin.php';

		$this->filesystem->copy($templatesFolder . $ds . 'phpunit.xml', $this->rootPath . $ds . 'phpunit.xml');
		$this->filesystem->copy($templatesFolder . $ds . $bootstrap, $testsPath . $ds . 'bootstrap.php');
		$this->filesystem->copy($templatesFolder . $ds . 'ExampleUnitTest.php', $testsPath . $ds . 'Unit' . $ds . 'ExampleTest.php');
		$this->filesystem->copy($templatesFolder . $ds . 'ExampleIntegrationTest.php', $testsPath . $ds . 'Integration' . $ds . 'ExampleTest.php'); // phpcs:ignore Generic.Files.LineLength.TooLong
		$this->filesystem->copy($templatesFolder . $ds . 'Pest.php', $testsPath . $ds . 'Pest.php');
	}

	/**
	 * Prints the composer.json snippet the user needs to add
	 *
	 * @param SymfonyStyle $io IO instance.
	 * @param string $projectType Type of project, theme or plugin.
	 * @param string $wpVersion WordPress version constraint to suggest.
	 *
	 * @since 1.9.0
	 *
	 * @return void
	 */
	private function printComposerInstructions(SymfonyStyle $io, string $projectType, string $wpVersion): void
	{
		$constraint = $wpVersion === 'latest' ? '^*' : $wpVersion;

		$snippet = <<<JSON
		{
			"repositories": [
				{ "type": "vcs", "url": "https://github.com/WordPress/wordpress-develop" },
				{ "type": "composer", "url": "https://wpackagist.org" }
			],
			"require-dev": {
				"wordpress/wordpress": "{$constraint}",
				"wp-phpunit/wp-phpunit": "latest",
				"dingo-d/wp-sqlite-db": "^1.4.1",
				"yoast/wp-test-utils": "^1.2",
				"pestphp/pest": "^1.2"
			},
			"extra": {
				"installer-paths": {
					"./wp/": ["type:wordpress-core"]
				},
				"dropin-paths": {
					"wp/wp-content/": ["dingo-d/wp-sqlite-db:src/db.php"]
				}
			},
			"config": {
				"allow-plugins": {
					"composer/installers": true,
					"pestphp/pest-plugin": true
				}
			}
		}
		JSON;

		$io->section('Add this to your composer.json, then run `composer install`');
		$io->text($snippet);

		if ($projectType === 'plugin') {
			$io->text('The plugin under test is loaded automatically from your project root in tests/bootstrap.php — no placeholder needed.');
		}

		$io->text('To test against another WordPress version, change the "wordpress/wordpress" constraint (e.g. "6.4.*") or run `composer require --dev wordpress/wordpress:6.4.*`, then re-run the tests. Use a CI matrix over several constraints to test multiple versions.');
	}
}
