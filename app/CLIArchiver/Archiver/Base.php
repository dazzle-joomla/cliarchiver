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

		$storedName = $this->addRemovePaths($fileName, '', $addPath);

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
	 * Removes the $p_remove_dir from $p_filename, while prepending it with $p_add_dir.
	 * Largely based on code from the pclZip library.
	 *
	 * @param   string $fullPathname The absolute file name to treat
	 * @param   string $removePath   The path to remove
	 * @param   string $addPath      The path to prefix the treated file name with
	 *
	 * @return  string  The treated file name
	 */
	private function addRemovePaths(string $fullPathname, string $removePath, string $addPath): string
	{
		$fullPathname = $this->fsUtils->translateWinPath($fullPathname);
		$removePath   = ($removePath == '') ? '' :
			$this->fsUtils->translateWinPath($removePath); //should fix corrupt backups, fix by nicholas

		$relativePathname = $fullPathname;

		if (!($removePath == ""))
		{
			if (substr($removePath, -1) != '/')
			{
				$removePath .= "/";
			}

			if ((substr($fullPathname, 0, 2) == "./") || (substr($removePath, 0, 2) == "./"))
			{
				if ((substr($fullPathname, 0, 2) == "./") && (substr($removePath, 0, 2) != "./"))
				{
					$removePath = "./" . $removePath;
				}

				if ((substr($fullPathname, 0, 2) != "./") && (substr($removePath, 0, 2) == "./"))
				{
					$removePath = substr($removePath, 2);
				}
			}

			$pathInclusionType = $this->getPathInclusionType($removePath, $fullPathname);

			if ($pathInclusionType > 0)
			{
				if ($pathInclusionType == 2)
				{
					$relativePathname = "";
				}
				else
				{
					$relativePathname =
						substr($fullPathname, (function_exists('mb_strlen') ? mb_strlen($removePath, '8bit') :
							strlen($removePath)));
				}
			}
		}
		else
		{
			$relativePathname = $fullPathname;
		}

		if (!($addPath == ""))
		{
			if (substr($addPath, -1) == "/")
			{
				$relativePathname = $addPath . $relativePathname;
			}
			else
			{
				$relativePathname = $addPath . "/" . $relativePathname;
			}
		}

		return $relativePathname;
	}

	/**
	 * This function indicates if the path $pathToCheck is under the $rootPath tree. Or,
	 * said in an other way, if the file or sub-dir $pathToCheck is inside the dir
	 * $rootPath.
	 * The function indicates also if the path is exactly the same as the dir.
	 * This function supports path with duplicated '/' like '//', but does not
	 * support '.' or '..' statements.
	 *
	 * Copied verbatim from pclZip library
	 *
	 * @codeCoverageIgnore
	 *
	 * @param   string $rootPath    Source tree
	 * @param   string $pathToCheck Check if this is part of $p_dir
	 *
	 * @return  int   0 Is not inside, 1 It is inside, 2 They are identical
	 */
	private function getPathInclusionType(string $rootPath, string $pathToCheck): int
	{
		$return = 1;

		// ----- Explode dir and path by directory separator
		$rootDirList      = explode("/", $rootPath);
		$rootDirListSize  = sizeof($rootDirList);
		$checkDirList     = explode("/", $pathToCheck);
		$checkDirListSize = sizeof($checkDirList);

		// ----- Study directories paths
		$i = 0;
		$j = 0;

		while (($i < $rootDirListSize) && ($j < $checkDirListSize) && ($return))
		{
			// ----- Look for empty dir (path reduction)
			if ($rootDirList[$i] == '')
			{
				$i++;

				continue;
			}

			if ($checkDirList[$j] == '')
			{
				$j++;

				continue;
			}

			// ----- Compare the items
			if (($rootDirList[$i] != $checkDirList[$j]) && ($rootDirList[$i] != '') && ($checkDirList[$j] != ''))
			{
				$return = 0;
			}

			// ----- Next items
			$i++;
			$j++;
		}

		// ----- Look if everything seems to be the same
		if ($return)
		{
			// ----- Skip all the empty items
			while (($j < $checkDirListSize) && ($checkDirList[$j] == ''))
			{
				$j++;
			}

			while (($i < $rootDirListSize) && ($rootDirList[$i] == ''))
			{
				$i++;
			}

			if (($i >= $rootDirListSize) && ($j >= $checkDirListSize))
			{
				// ----- There are exactly the same
				$return = 2;
			}
			elseif ($i < $rootDirListSize)
			{
				// ----- The path is shorter than the dir
				$return = 0;
			}
		}

		// ----- Return
		return $return;
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
		$storedName = $this->addRemovePaths($file, $removePath, $addPath);

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
