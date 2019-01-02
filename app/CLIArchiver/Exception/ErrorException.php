<?php
/**
 * @package   CLIArchiver
 * @copyright Copyright (c) 2017-2019 Akeeba Ltd / Nicholas K. Dionysopoulos
 * @license   GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Akeeba\CLIArchiver\Exception;

// Protection against direct access
defined('AKEEBAENGINE') or die();

use RuntimeException;

/**
 * An exception which leads to an error (and complete halt) in the backup process
 */
class ErrorException extends RuntimeException
{

}
