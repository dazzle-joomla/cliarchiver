<?php
/**
 * @package   CLIArchiver
 * @copyright Copyright (c) 2017-2018 Akeeba Ltd / Nicholas K. Dionysopoulos
 * @license   GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Akeeba\CLIArchiver\Util;


abstract class Conversions
{
	/**
	 * Converts a human formatted size to integer representation of bytes,
	 * e.g. 1M to 1024768
	 *
	 * @param   string $humanString The value in human readable format, e.g. "1M"
	 *
	 * @return  integer  The value in bytes
	 */
	public static function humanToIntegerBytes(string $humanString): int
	{
		$val  = trim($humanString);
		$last = strtolower($val{strlen($val) - 1});

		if (is_numeric($last))
		{
			return $humanString;
		}

		switch ($last)
		{
			case 't':
				$val *= 1024;
			case 'g':
				$val *= 1024;
			case 'm':
				$val *= 1024;
			case 'k':
				$val *= 1024;
		}

		return (int) $val;
	}

}