<?php
/**
 * @package info.ajaxplorer.plugins
 * 
 * Copyright 2011-2012 Daniel Lo Nigro <daniel@dan.cx>
 * This file is part of AjaXplorer-Azure
 * 
 * This program is published under the LGPL GNU Lesser General Public License.
 * You should have received a copy of the license along with AjaXplorer.
 * 
 * The main conditions are as follows: 
 *
 * You must conspicuously and appropriately publish on each copy distributed 
 * an appropriate copyright notice and disclaimer of warranty and keep intact 
 * all the notices that refer to this License and to the absence of any warranty; 
 * and give any other recipients of the Program a copy of the GNU Lesser General 
 * Public License along with the Program. 
 * 
 * If you modify your copy or copies of the library or any portion of it, you may 
 * distribute the resulting library provided you do so under the GNU Lesser 
 * General Public License. However, programs that link to the library may be 
 * licensed under terms of your choice, so long as the library itself can be changed. 
 * Any translation of the GNU Lesser General Public License must be accompanied by the 
 * GNU Lesser General Public License.
 * 
 * If you copy or distribute the program, you must accompany it with the complete 
 * corresponding machine-readable source code or with a written offer, valid for at 
 * least three years, to furnish the complete corresponding machine-readable source code. 
 * 
 * Any of the above conditions can be waived if you get permission from the copyright holder.
 * AjaXplorer is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; 
 * without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * 
 * Description: Access wrapper for Windows Azure blob storage
 */
 
defined('AJXP_EXEC') or die('Access not allowed');

class azureAccessWrapper implements AjxpWrapper
{
	protected static $wrappers = array();
	protected $handle;
	
	/**
	 * Create an Azure protocol wrapper if needed, and return a path that will work with this
	 * wrapper.
	 * @param	String	Internal AjaXplorer path
	 * @return	String	Path to use for file access, registered with the Azure stream handler
	 */
	protected static function initPath($path)
	{
		$url = parse_url($path);
		$repoId = $url['host'];
		$wrapperName = 'azure-' . $repoId;
		
		// Does this repository not already have a wrapper?
		if (!isset(self::$wrappers[$repoId]))
		{
			$repo = ConfService::getRepositoryById($repoId);
			if (!isset($repo)) 
				throw new Exception('Cannot find repository with id ' . $repoId);
				
			// Using dev storage?
			if ($repo->getOption('AZURE_USE_DEV'))
			{
				$storage = new Microsoft_WindowsAzure_Storage_Blob();
			}
			else
			{
				$host = $repo->getOption('AZURE_HOST');
				$account = $repo->getOption('AZURE_ACCOUNT');
				$key = $repo->getOption('AZURE_ACCESS_KEY');
				$storage = new Microsoft_WindowsAzure_Storage_Blob($host, $account, $key);
			}
			
			$storage->registerStreamWrapper($wrapperName);
			// Mark this as having a wrapper
			self::$wrappers[$repoId] = true;
		}		
		
		return $wrapperName . ':/' . $url['path'];
	}
	
	public static function isRemote()
	{
		return true;
	}
	
	public function url_stat($path, $flags)
	{
		$path = self::initPath($path);
		return stat($path);
	}
	
	public static function getRealFSReference($path, $persistent = FALSE)
	{
		// TODO: Should this do more here?
		return self::initPath($path);
	}
	
	public function stream_open($url, $mode, $options, &$context)
	{
		$url = self::initPath($url);
		$this->handle = fopen($url, $mode);		
		return $this->handle !== false;
	}
	
	public function stream_flush()
	{
		return fflush($this->handle);
	}
	
	public function stream_eof()
	{
		return feof($this->handle);
	}
	
	public function stream_read($count)
	{
		return fread($this->handle, $count);
	}
	
	public function stream_close()
	{
		return fclose($this->handle);
		$this->handle = null;
	}
	
	public static function copyFileInStream($path, $stream)
	{
		$handle = fopen(self::initPath($path), "rb");
		while (!feof($handle))
		{
			$data = fread($handle, 4096);
			fwrite($stream, $data, strlen($data));
		}
		fclose($handle);
	}
	
	////////////////////////////////////////////////////////////////////////////////////////////////
	// Not implemented
	////////////////////////////////////////////////////////////////////////////////////////////////

	public function stream_stat()
	{
		throw new AJXP_Exception('Not implemented: stream_stat');
	}	

	public function stream_seek($offset , $whence = SEEK_SET)
	{
		throw new AJXP_Exception('Not implemented: stream_seek');
	}

	public function stream_tell()
	{
		throw new AJXP_Exception('Not implemented: stream_tell');
	}	

	public function stream_write($data)
	{
		throw new AJXP_Exception('Not implemented: getRealFSReference');
	}

	public static function changeMode($path, $chmodValue)
	{
		throw new AJXP_Exception('Not implemented: changeMode');
	}    

	public function unlink($url)
	{
		throw new AJXP_Exception('Not implemented: unlink');
	}

	public function rmdir($url, $options)
	{
		throw new AJXP_Exception('Not implemented: rmdir');
	}

	public function mkdir($url, $mode, $options){
		throw new AJXP_Exception('Not implemented: mkdir');
	}

	public function rename($from, $to)
	{
		throw new AJXP_Exception('Not implemented: rename');
	}

	public function dir_opendir($url, $options)
	{
		throw new AJXP_Exception('Not implemented: dir_opendir');
	}

	public function dir_closedir()
	{
		throw new AJXP_Exception('Not implemented: dir_closedir');
	}	

	public function dir_readdir()
	{
		throw new AJXP_Exception('Not implemented:  dir_readdir');
	}	

	public function dir_rewinddir()
	{
		throw new AJXP_Exception('Not implemented:  dir_rewinddir');
	}
	
	protected function parseUrl($url)
	{
		throw new AJXP_Exception('Not implemented: parseUrl');
	}

	protected function createHttpClient()
	{
		throw new AJXP_Exception('Not implemented: createHttpClient'); 	
	}	
}
?>
