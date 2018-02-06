<?php
/**
 * Created by PhpStorm.
 * User: sledg
 * Date: 06/02/2018
 * Time: 8:24 PM
 */

namespace Akeeba\CLIArchiver\Command;


use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

class Create
{
	/**
	 * Creates a JPA or JPS archive
	 *
	 * @param string          $archive    Path to the archive to be created
	 * @param array           $filespec   Files and folders to add to the archive
	 * @param string          $relativeTo Remove this path from the one recorded in the backup archive
	 * @param array           $exclude    Exclude these files or folders
	 * @param string          $format     Archive format, JPA or JPS
	 * @param string          $key        Encryption key for JPS archives
	 * @param string          $partSize   Part size for archive splitting
	 * @param bool            $verbose    Enable verbose output?
	 * @param bool            $debug      Enable debug output?
	 * @param InputInterface  $input      Symfony console input
	 * @param OutputInterface $output     Symfony console output
	 */
	public function __invoke(string $archive, array $filespec, string $relativeTo, array $exclude, string $format, string $key, string $partSize, bool $verbose, bool $debug, InputInterface $input, OutputInterface $output)
	{
		// Parse verbosity
		if ($verbose)
		{
			$output->setVerbosity(ConsoleOutput::VERBOSITY_VERBOSE);
		}

		if ($debug)
		{
			$output->setVerbosity(ConsoleOutput::VERBOSITY_DEBUG);
		}

		// Figure out the archiver format
		$format = $this->validateFormat($format);

		// TODO Normalize $archive filename (extension must match $format)

		// TODO Get the archiver

		// TODO Set up with $key and $partSize

		// TODO Scan filespecs and add files
	}

	private function validateFormat($format)
	{
		if (!is_string($format) || empty($format))
		{
			return 'jpa';
		}

		return (strtolower($format) == 'jps') ? 'jps' : 'jpa';
	}
}