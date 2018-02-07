<?php
/**
 * @package   CLIArchiver
 * @copyright Copyright (c) 2017-2018 Akeeba Ltd / Nicholas K. Dionysopoulos
 * @license   GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Akeeba\CLIArchiver\Archiver;

/**
 * The base class of Akeeba Engine objects. Allows for error and warnings logging
 * and propagation. Largely based on the Joomla! 1.5 JObject class.
 */
abstract class BaseObject
{
	/** @var  array  The queue size of the $_errors array. Set to 0 for infinite size. */
	protected $maxErrorQueueSize = 0;

	/** @var  array  The queue size of the $_warnings array. Set to 0 for infinite size. */
	protected $maxWarningsQueueSize = 0;

	/** @var  array  An array of errors */
	private $errorQueue = array();

	/** @var  array  An array of warnings */
	private $warningsQueue = array();

	/**
	 * Get the most recent error message
	 *
	 * @param   integer $i Optional error index
	 *
	 * @return  string  Error message
	 */
	public function getError(?int $i = null): string
	{
		return $this->getItemFromArray($this->errorQueue, $i);
	}

	/**
	 * Returns the last item of a LIFO string message queue, or a specific item
	 * if so specified.
	 *
	 * @param   array $array An array of strings, holding messages
	 * @param   int   $i     Optional message index
	 *
	 * @return  string The message string, empty if the key doesn't exist
	 */
	protected function getItemFromArray(array $array, ?int $i = null): string
	{
		// Find the item
		if ($i === null)
		{
			// Default, return the last item
			return end($array);
		}

		if (!array_key_exists($i, $array))
		{
			// If $i has been specified but does not exist, return an empty string
			return '';
		}

		return $array[$i];
	}

	/**
	 * Get the most recent warning message
	 *
	 * @param   integer $i Optional warning index
	 *
	 * @return  string  Error message
	 */
	public function getWarning(?int $i = null): string
	{
		return $this->getItemFromArray($this->warningsQueue, $i);
	}

	/**
	 * Propagates errors and warnings to a foreign object. Propagated items will be removed from our own instance.
	 *
	 * @param   object $object The object to propagate errors and warnings to.
	 *
	 * @return  void
	 */
	public function propagateToObject(object &$object): void
	{
		// Skip non-objects
		if (!is_object($object))
		{
			return;
		}

		if (method_exists($object, 'setError'))
		{
			if (!empty($this->errorQueue))
			{
				foreach ($this->errorQueue as $error)
				{
					$object->setError($error);
				}

				$this->errorQueue = array();
			}
		}

		if (method_exists($object, 'setWarning'))
		{
			if (!empty($this->warningsQueue))
			{
				foreach ($this->warningsQueue as $warning)
				{
					$object->setWarning($warning);
				}

				$this->warningsQueue = array();
			}
		}
	}

	/**
	 * Propagates errors and warnings from a foreign object. Each propagated list is
	 * then cleared on the foreign object, as long as it implements resetErrors() and/or
	 * resetWarnings() methods.
	 *
	 * @param   object $object The object to propagate errors and warnings from
	 *
	 * @return  void
	 */
	public function propagateFromObject(object &$object): void
	{
		if (method_exists($object, 'getErrorQueue'))
		{
			$errors = $object->getErrorQueue();

			if (!empty($errors))
			{
				foreach ($errors as $error)
				{
					$this->setError($error);
				}
			}

			if (method_exists($object, 'resetErrors'))
			{
				$object->resetErrors();
			}
		}

		if (method_exists($object, 'getWarningsQueue'))
		{
			$warnings = $object->getWarningsQueue();

			if (!empty($warnings))
			{
				foreach ($warnings as $warning)
				{
					$this->setWarning($warning);
				}
			}

			if (method_exists($object, 'resetWarnings'))
			{
				$object->resetWarnings();
			}
		}
	}

	/**
	 * Return all errors, if any
	 *
	 * @return  array  Array of error messages
	 */
	public function getErrorQueue(): array
	{
		return $this->errorQueue;
	}

	/**
	 * Add an error message
	 *
	 * @param   string $error Error message
	 */
	public function setError(string $error)
	{
		if ($this->maxErrorQueueSize > 0)
		{
			if (count($this->errorQueue) >= $this->maxErrorQueueSize)
			{
				array_shift($this->errorQueue);
			}
		}

		array_push($this->errorQueue, $error);
	}

	/**
	 * Resets all error messages
	 *
	 * @return  void
	 */
	public function resetErrors(): void
	{
		$this->errorQueue = array();
	}

	/**
	 * Return all warnings, if any
	 *
	 * @return  array  Array of error messages
	 */
	public function getWarningsQueue(): array
	{
		return $this->warningsQueue;
	}

	/**
	 * Add a warning message
	 *
	 * @param   string $warning Warning message
	 *
	 * @return  void
	 */
	public function setWarning(string $warning): void
	{
		if ($this->maxWarningsQueueSize > 0)
		{
			if (count($this->warningsQueue) >= $this->maxWarningsQueueSize)
			{
				array_shift($this->warningsQueue);
			}
		}

		array_push($this->warningsQueue, $warning);
	}

	/**
	 * Resets all warning messages
	 *
	 * @return  void
	 */
	public function resetWarnings(): void
	{
		$this->warningsQueue = array();
	}

	/**
	 * Sets the size of the error queue (acts like a LIFO buffer)
	 *
	 * @param   int $newSize The new queue size. Set to 0 for infinite length.
	 *
	 * @return  void
	 */
	protected function setMaxErrorQueueSize(int $newSize = 0): void
	{
		$this->maxErrorQueueSize = (int) $newSize;
	}

	/**
	 * Sets the size of the warnings queue (acts like a LIFO buffer)
	 *
	 * @param   int $newSize The new queue size. Set to 0 for infinite length.
	 *
	 * @return  void
	 */
	protected function setMaxWarningsQueueSize(int $newSize = 0): void
	{
		$this->maxWarningsQueueSize = (int) $newSize;
	}
}
