<?php
/**
 * @package   CLIArchiver
 * @copyright Copyright (c) 2017-2019 Akeeba Ltd / Nicholas K. Dionysopoulos
 * @license   GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Akeeba\CLIArchiver\Command;


use Akeeba\CLIArchiver\Archiver\Jpa;
use Akeeba\CLIArchiver\Archiver\Jps;
use Akeeba\CLIArchiver\Util\Conversions;
use Akeeba\CLIArchiver\Util\FileSystem;
use DirectoryIterator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

class Create
{
	/** @var FileSystem */
	private $fsUtils;

	/** @var OutputInterface */
	private $output;

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
	public function __invoke(string $archive, array $filespec, string $relativeTo, array $exclude, string $format, string $key, string $partSize, bool $dereferenceSymlinks, bool $staticSalt, bool $verbose, bool $debug, InputInterface $input, OutputInterface $output)
	{
		$this->fsUtils = new FileSystem();
		$this->output  = $output;

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

		// Normalize the part size
		$partSize = Conversions::humanToIntegerBytes($partSize);

		// Get the archiver
		switch ($format)
		{
			case 'jps':
				$archiver = new Jps($partSize, $dereferenceSymlinks, $output, $key, $staticSalt);
				break;

			case 'jpa':
			default:
				$archiver = new Jpa($partSize, $dereferenceSymlinks, $output);
				break;
		}

		// Normalize $archive filename (extension must match $format)
		$archive = $this->normalizeArchive($archive, $archiver->getExtension());
		$this->output->writeln("<info>Creating archive $archive</info>");
		$archiver->initialize($archive);

		// Normalize include and exclude paths
		$filespec = array_map([$this, 'normalizePath'], $filespec);
		$exclude  = array_map([$this, 'normalizePath'], $exclude);

		// Get the remove path: CWD; or the first excluded folder; or the folder of the first excluded file
		$removePath = realpath(getcwd());

		if (!empty($exclude))
		{
			$removePath = $exclude[0];

			if (is_file($removePath) && !is_dir($removePath))
			{
				$removePath = dirname($removePath);
			}
		}

		// Scan filespecs and add files
		$this->scanPaths($filespec, $exclude, function ($addThisFile) use ($archiver, $removePath) {
			$this->output->writeln("$addThisFile", OutputInterface::VERBOSITY_NORMAL);
			$archiver->addFile($addThisFile, $removePath);
		});

		$this->output->writeln("<info>Archive $archive created successfully.</info>");
	}

	private function validateFormat(string $format): string
	{
		if (!is_string($format) || empty($format))
		{
			return 'jpa';
		}

		return (strtolower($format) == 'jps') ? 'jps' : 'jpa';
	}

	private function normalizeArchive(string $path, string $extension): string
	{
		if (substr($path, -strlen($extension)) === $extension)
		{
			$path = substr($path, 0, -strlen($extension));
		}

		return $this->normalizePath($path) . $extension;
	}

	private function normalizePath(string $path): string
	{
		if ((substr($path, 0, 3) == '../') || substr($path, 0, 2) == './')
		{
			$path = getcwd() . '/' . $path;
		}

		return realpath(dirname($path)) . '/' . basename($path);
	}

	private function scanPaths(array $includes, array $excludes, callable $callback): void
	{
		foreach ($includes as $path)
		{
			$this->scanPath($path, $excludes, $callback);
		}
	}

	private function scanPath(string $path, array $exclude, callable $callback): void
	{
		$this->output->writeln("<comment>Scanning folder $path</comment>", OutputInterface::VERBOSITY_DEBUG);
		$di = new DirectoryIterator($path);

		foreach ($di as $item)
		{
			if ($item->isDot())
			{
				continue;
			}

			$toAdd = $item->getPathname();

			if (!empty($exclude))
			{
				foreach ($exclude as $excludePath)
				{
					if ($this->fsUtils->getPathInclusionType($excludePath, $toAdd) !== 0)
					{
						$type = $item->isDir() ? 'dir' : 'file';
						$type = $item->isLink() ? 'link' : $type;
						$this->output->writeln("<comment>Skipping $type $toAdd</comment>", OutputInterface::VERBOSITY_VERBOSE);
						continue 2;
					}
				}
			}

			call_user_func($callback, $toAdd);

			if ($item->isDir())
			{
				$this->scanPath($toAdd, $exclude, $callback);
			}
		}
	}
}