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
}

