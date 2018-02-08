#!/usr/bin/env php
<?php
/**
 * @package   CLIArchiver
 * @copyright Copyright (c) 2017-2018 Akeeba Ltd / Nicholas K. Dionysopoulos
 * @license   GNU General Public License version 3 or later; see LICENSE.txt
 */

// Load Composer's autoloader
$loader = require_once __DIR__ . '/vendor/autoload.php';

use Akeeba\CLIArchiver\Command\Create;
use Silly\Application;
use Symfony\Component\Console\Output\ConsoleOutput;

// Load the version.php file
require_once 'version.php';

$app = new Application('Akeeba CLI Archiver', CLIARCHIVER_VERSION);

$app->command(
	'create archive [filespec]* [-r|--relative-to=] [-e|--exclude=]* [-f|--format=] [-k|--key=] [-s|--part-size=] [-d|--dereference-symlinks] [-t|--static-salt] [-v|--verbose] [-D|--debug]', new Create()
)->defaults([
	'filespec'    => [getcwd()],
	'relative-to' => getcwd(),
	'exclude'     => [],
	'format'      => 'jpa',
	'key'         => '',
	'part-size'   => 0,
])->descriptions('Create a JPA or JPS archive compatible with Akeeba software.', [
	'archive'              => 'The path to the archive',
	'filespec'             => 'The file(s) or folder(s) to include in the archive. Default: current working directory.',
	'relative-to'          => 'Remove this path from the included files. Default: current working directory.',
	'exclude'              => 'Exclude these files or folders (you can pass this argument multiple times).',
	'format'               => 'Archive format, jpa or jps. Default: jpa.',
	'key'                  => 'JPS encryption key.',
	'part-size'            => 'Part size for archive splitting in bytes, or specify unit e.g. 2M. Default: 0 (no splitting).',
	'dereference-symlinks' => 'Should I dereference (follow) symlinks? Otherwise symlink target paths are stored in the archive.',
	'static-salt'          => 'Use a static salt for JPS creation. Much faster but slightly decreases resistance to cryptanalysis.',
	'verbose'              => 'Verbose output',
	'debug'                => 'Debug output. Overrides verbose.',
])
;

// Mark ourselves as a single command application
$app->setDefaultCommand('create', true);

try
{
	$app->run();
}
catch (Exception $e)
{
	$output = new ConsoleOutput();
	$output->writeln("<error>An unexpected error occurred</error>");
	$output->writeln("<error>{$e->getMessage()}</error>");
	$output->writeln("Stack trace", ConsoleOutput::VERBOSITY_DEBUG);
	$output->write($e->getTrace(), true, ConsoleOutput::VERBOSITY_DEBUG);
}