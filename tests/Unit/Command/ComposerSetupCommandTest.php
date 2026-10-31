<?php

namespace MadeByDenis\WpPestIntegrationTestSetup\Tests\Unit\Command;

use Brain\Monkey;
use MadeByDenis\WpPestIntegrationTestSetup\Command\ComposerSetupCommand;
use Symfony\Component\Filesystem\Filesystem;
use Zenstruck\Console\Test\TestCommand;

use function MadeByDenis\WpPestIntegrationTestSetup\Tests\deleteOutputDir;

beforeEach(function () {
	Monkey\setUp();
	$ds = DIRECTORY_SEPARATOR;

	$this->outputDir = dirname(__FILE__, 3) . $ds . 'output';
	$this->fileSystem = new Filesystem();

	$this->command = new ComposerSetupCommand($this->outputDir, $this->fileSystem);
});

afterEach(function () {
	Monkey\tearDown();

	// Clean up the output dir.
	deleteOutputDir();
});

it("checks that the command name is correct", function () {
	expect($this->command::getDefaultName())->toBe('setup:composer');
});

it("checks that the command throws error when argument isn't correct", function () {
	TestCommand::for($this->command)
		->addArgument('bla')
		->execute()
		->assertStatusCode(1)
		->assertOutputContains("The argument must either be 'theme' or 'plugin', bla provided");
});

it("creates static test files without placeholders for a theme", function () {
	TestCommand::for($this->command)
		->addArgument('theme')
		->execute()
		->assertStatusCode(0)
		->assertOutputContains('composer.json');

	$ds = DIRECTORY_SEPARATOR;
	$testsDir = $this->outputDir . $ds . 'tests';

	expect($this->outputDir . $ds . 'phpunit.xml')->toBeFile();
	expect($testsDir . $ds . 'bootstrap.php')->toBeFile();
	expect($testsDir . $ds . 'Pest.php')->toBeFile();
	expect($testsDir . $ds . 'Unit' . $ds . 'ExampleTest.php')->toBeFile();
	expect($testsDir . $ds . 'Integration' . $ds . 'ExampleTest.php')->toBeFile();

	$bootstrap = (string) file_get_contents($testsDir . $ds . 'bootstrap.php');

	expect($bootstrap)->not->toContain('%%%PLUGIN-SLUG%%%');
});

it("creates static test files without placeholders for a plugin", function () {
	TestCommand::for($this->command)
		->addArgument('plugin')
		->execute()
		->assertStatusCode(0)
		->assertOutputContains('plugin under test is loaded automatically');

	$ds = DIRECTORY_SEPARATOR;
	$bootstrap = (string) file_get_contents($this->outputDir . $ds . 'tests' . $ds . 'bootstrap.php');

	expect($bootstrap)->not->toContain('%%%PLUGIN-SLUG%%%');
	expect($bootstrap)->toContain('Plugin Name:');
});

it("suggests the passed WordPress version constraint in the composer snippet", function () {
	TestCommand::for($this->command)
		->addArgument('theme')
		->addOption('wp-version', '6.4.*')
		->execute()
		->assertStatusCode(0)
		->assertOutputContains('"wordpress/wordpress": "6.4.*"');
});

it("moves on when the tests directory already exists", function () {
	$this->fileSystem->mkdir($this->outputDir . DIRECTORY_SEPARATOR . 'tests');

	TestCommand::for($this->command)
		->addArgument('theme')
		->execute()
		->assertStatusCode(0)
		->assertOutputContains('tests/ directory already exists. Moving on.');
});
