<?php
/**
 * @package   CLIArchiver
 * @copyright Copyright (c) 2017-2019 Akeeba Ltd / Nicholas K. Dionysopoulos
 * @license   GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Akeeba\CLIArchiver\Encrypt\AesAdapter;

use Akeeba\CLIArchiver\Encrypt\RandomValue;

class Mcrypt extends AbstractAdapter implements AdapterInterface
{
	protected $cipherType = MCRYPT_RIJNDAEL_128;

	protected $cipherMode = MCRYPT_MODE_CBC;

	public function setEncryptionMode(string $mode = 'cbc', int $strength = 128): void
	{
		switch ((int) $strength)
		{
			default:
			case '128':
				$this->cipherType = MCRYPT_RIJNDAEL_128;
				break;

			case '192':
				$this->cipherType = MCRYPT_RIJNDAEL_192;
				break;

			case '256':
				$this->cipherType = MCRYPT_RIJNDAEL_256;
				break;
		}

		switch (strtolower($mode))
		{
			case 'ecb':
				$this->cipherMode = MCRYPT_MODE_ECB;
				break;

			default:
			case 'cbc':
				$this->cipherMode = MCRYPT_MODE_CBC;
				break;
		}

	}

	public function encrypt(string $plainText, string $key, ?string $iv = null): string
	{
		$iv_size = $this->getBlockSize();
		$key     = $this->resizeKey($key, $iv_size);
		$iv      = $this->resizeKey($iv, $iv_size);

		if (empty($iv))
		{
			$randVal = new RandomValue();
			$iv      = $randVal->generate($iv_size);
		}

		$cipherText = mcrypt_encrypt($this->cipherType, $key, $plainText, $this->cipherMode, $iv);
		$cipherText = $iv . $cipherText;

		return $cipherText;
	}

	public function getBlockSize(): int
	{
		return mcrypt_get_iv_size($this->cipherType, $this->cipherMode);
	}

	public function decrypt(string $cipherText, string $key): string
	{
		$iv_size    = $this->getBlockSize();
		$key        = $this->resizeKey($key, $iv_size);
		$iv         = substr($cipherText, 0, $iv_size);
		$cipherText = substr($cipherText, $iv_size);
		$plainText  = mcrypt_decrypt($this->cipherType, $key, $cipherText, $this->cipherMode, $iv);

		return $plainText;
	}

	public function isSupported(): bool
	{
		if (!function_exists('mcrypt_get_key_size'))
		{
			return false;
		}

		if (!function_exists('mcrypt_get_iv_size'))
		{
			return false;
		}

		if (!function_exists('mcrypt_create_iv'))
		{
			return false;
		}

		if (!function_exists('mcrypt_encrypt'))
		{
			return false;
		}

		if (!function_exists('mcrypt_decrypt'))
		{
			return false;
		}

		if (!function_exists('mcrypt_list_algorithms'))
		{
			return false;
		}

		if (!function_exists('hash'))
		{
			return false;
		}

		if (!function_exists('hash_algos'))
		{
			return false;
		}

		$algorightms = mcrypt_list_algorithms();

		if (!in_array('rijndael-128', $algorightms))
		{
			return false;
		}

		if (!in_array('rijndael-192', $algorightms))
		{
			return false;
		}

		if (!in_array('rijndael-256', $algorightms))
		{
			return false;
		}

		$algorightms = hash_algos();

		if (!in_array('sha256', $algorightms))
		{
			return false;
		}

		return true;
	}
}
