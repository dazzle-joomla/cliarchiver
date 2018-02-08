<?php
/**
 * @package   CLIArchiver
 * @copyright Copyright (c) 2017-2018 Akeeba Ltd / Nicholas K. Dionysopoulos
 * @license   GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Akeeba\CLIArchiver\Archiver;

use Akeeba\CLIArchiver\Exception\ErrorException;
use Akeeba\CLIArchiver\Exception\WarningException;
use Akeeba\CLIArchiver\Util\FileSystem;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Abstract parent class of all archiver engines
 */
abstract class Base extends BaseObject
{
	/** @var Filesystem Filesystem utilities object */
	protected $fsUtils = null;

	/** @var   string  The archive's comment. It's currently used ONLY in the ZIP file format */
	protected $archiveComment;

	/** @var OutputInterface */
	protected $output;

	/**
	 * Public constructor
	 */
	public function __construct()
	{
		$this->__bootstrap_code();
	}

	/**
	 * Common code which gets called on instance creation or wake-up (unserialization)
	 *
	 * @codeCoverageIgnore
	 *
	 * @return  void
	 */
	protected function __bootstrap_code(): void
	{
		$this->fsUtils = new FileSystem();
	}

	/**
	 * Get a reference to the Symfony output object. If none is specified it creates a new ConsoleOutput object.
	 *
	 * @return OutputInterface
	 */
	public function getOutput(): OutputInterface
	{
		if (empty($this->output))
		{
			$this->output = new ConsoleOutput();
		}

		return $this->output;
	}

	/**
	 * Set the output object
	 *
	 * @param   OutputInterface $output The output object to use with this object
	 *
	 * @return $this for chaining
	 */
	public function setOutput(OutputInterface $output)
	{
		$this->output = $output;

		return $this;
	}

	/**
	 * Adds a file to the archive, given the stored name and its contents
	 *
	 * @param   string $fileName       The base file name
	 * @param   string $addPath        The relative path to prepend to file name
	 * @param   string $virtualContent The contents of the file to be archived
	 *
	 * @return  bool
	 */
	public function addVirtualFile(string $fileName, string $addPath = '', string &$virtualContent): bool
	{
		$mb_encoding = '8bit';

		$storedName = $this->fsUtils->addRemovePaths($fileName, '', $addPath);

		if (function_exists('mb_internal_encoding'))
		{
			$mb_encoding = mb_internal_encoding();
			mb_internal_encoding('ISO-8859-1');
		}

		try
		{
			$ret = $this->addFileEntry(true, $virtualContent, $storedName);
		}
		catch (WarningException $e)
		{
			$this->setWarning($e->getMessage());
			$ret = false;
		}
		catch (ErrorException $e)
		{
			$this->setError($e->getMessage());
			$ret = false;
		}

		if (function_exists('mb_internal_encoding'))
		{
			mb_internal_encoding($mb_encoding);
		}

		return $ret;
	}

	/**
	 * The most basic file transaction: add a single entry (file or directory) to
	 * the archive.
	 *
	 * @param   boolean $isVirtual        If true, the next parameter contains file data instead of a file name
	 * @param   string  $sourceNameOrData Absolute file name to read data from or the file data itself is $isVirtual is
	 *                                    true
	 * @param   string  $targetName       The (relative) file name under which to store the file in the archive
	 *
	 * @return  boolean  True on success, false otherwise. DEPRECATED: Use exceptions instead.
	 *
	 * @throws  WarningException  When there's a warning (the backup integrity is NOT compromised)
	 * @throws  ErrorException    When there's an error (the backup integrity is compromised – backup dead)
	 */
	abstract protected function addFileEntry(bool $isVirtual, string &$sourceNameOrData, string $targetName): bool;

	/**
	 * Adds a list of files into the archive, removing $removePath from the
	 * file names and adding $addPath to them.
	 *
	 * @param   array  $fileList   A simple string array of filepaths to include
	 * @param   string $removePath Paths to remove from the filepaths
	 * @param   string $addPath    Paths to add in front of the filepaths
	 *
	 * @return  boolean  True on success
	 */
	public function addFileList(array &$fileList, string $removePath = '', string $addPath = ''): bool
	{
		if (!is_array($fileList))
		{
			$this->setWarning('addFileList called without a file list array');

			return false;
		}

		foreach ($fileList as $file)
		{
			if (!$this->addFile($file, $removePath, $addPath))
			{
				return false;
			}
		}

		return true;
	}

	/**
	 * Adds a single file in the archive
	 *
	 * @param   string $file       The absolute path to the file to add
	 * @param   string $removePath Path to remove from $file
	 * @param   string $addPath    Path to prepend to $file
	 *
	 * @return  boolean
	 */
	public function addFile(string $file, string $removePath = '', string $addPath = ''): bool
	{
		$storedName = $this->fsUtils->addRemovePaths($file, $removePath, $addPath);

		return $this->addFileRenamed($file, $storedName);
	}

	/**
	 * Adds a file to the archive, with a name that's different from the source
	 * filename
	 *
	 * @param   string $sourceFile Absolute path to the source file
	 * @param   string $targetFile Relative filename to store in archive
	 *
	 * @return  boolean
	 */
	public function addFileRenamed(string $sourceFile, string $targetFile): bool
	{
		$mb_encoding = '8bit';

		if (function_exists('mb_internal_encoding'))
		{
			$mb_encoding = mb_internal_encoding();
			mb_internal_encoding('ISO-8859-1');
		}

		try
		{
			$ret = $this->addFileEntry(false, $sourceFile, $targetFile);
		}
		catch (WarningException $e)
		{
			$this->setWarning($e->getMessage());
			$ret = false;
		}
		catch (ErrorException $e)
		{
			$this->setError($e->getMessage());
			$ret = false;
		}

		if (function_exists('mb_internal_encoding'))
		{
			mb_internal_encoding($mb_encoding);
		}

		return $ret;
	}

	/**
	 * Initialises the archiver class, creating the archive from an existent
	 * installer's JPA archive. MUST BE OVERRIDEN BY CHILDREN CLASSES.
	 *
	 * @param    string $targetArchivePath Absolute path to the generated archive
	 * @param    array  $options           A named key array of options (optional)
	 *
	 * @return  void
	 */
	abstract public function initialize(string $targetArchivePath, array $options = []): void;

	/**
	 * Makes whatever finalization is needed for the archive to be considered
	 * complete and useful (or, generally, clean up)
	 *
	 * @return  void
	 */
	abstract public function finalize(): void;

	/**
	 * Returns a string with the extension (including the dot) of the files produced
	 * by this class.
	 *
	 * @return  string
	 */
	abstract public function getExtension(): string;
}
