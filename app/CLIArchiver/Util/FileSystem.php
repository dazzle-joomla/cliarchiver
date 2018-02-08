<?php
/**
 * @package   CLIArchiver
 * @copyright Copyright (c) 2017-2018 Akeeba Ltd / Nicholas K. Dionysopoulos
 * @license   GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Akeeba\CLIArchiver\Util;

/**
 * Utility functions related to filesystem objects, e.g. path translation
 */
class FileSystem
{
	/** @var bool Are we running under Windows? */
	private $isWindows = false;

	/**
	 * Initialise the object
	 */
	public function __construct()
	{
		$this->isWindows = (DIRECTORY_SEPARATOR == '\\');
	}

	/**
	 * Makes a Windows path more UNIX-like, by turning backslashes to forward slashes.
	 * It takes into account UNC paths, e.g. \\myserver\some\folder becomes
	 * \\myserver/some/folder.
	 *
	 * This function will also fix paths with multiple slashes, e.g. convert /var//www////html to /var/www/html
	 *
	 * @param   string $path The path to transform
	 *
	 * @return  string
	 */
	public function translateWinPath(string $path): string
	{
		$is_unc = false;

		if ($this->isWindows)
		{
			// Is this a UNC path?
			$is_unc = (substr($path, 0, 2) == '\\\\') || (substr($path, 0, 2) == '//');

			// Change potential windows directory separator
			if ((strpos($path, '\\') > 0) || (substr($path, 0, 1) == '\\'))
			{
				$path = strtr($path, '\\', '/');
			}
		}

		// Remove multiple slashes
		$path = str_replace('///', '/', $path);
		$path = str_replace('//', '/', $path);

		// Fix UNC paths
		if ($is_unc)
		{
			$path = '//' . ltrim($path, '/');
		}

		return $path;
	}

	/**
	 * Removes trailing slash or backslash from a pathname
	 *
	 * @param   string  $path  The path to treat
	 *
	 * @return  string  The path without the trailing slash/backslash
	 */
	public function trimTrailingSlash(string $path): string
	{
		$newpath = $path;

		if (substr($path, strlen($path) - 1, 1) == '\\')
		{
			$newpath = substr($path, 0, strlen($path) - 1);
		}

		if (substr($path, strlen($path) - 1, 1) == '/')
		{
			$newpath = substr($path, 0, strlen($path) - 1);
		}

		return $newpath;
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
	public function addRemovePaths(string $fullPathname, string $removePath, string $addPath): string
	{
		$fullPathname = $this->translateWinPath($fullPathname);
		$removePath   = ($removePath == '') ? '' :
			$this->translateWinPath($removePath); //should fix corrupt backups, fix by nicholas

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
	public function getPathInclusionType(string $rootPath, string $pathToCheck): int
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


}

