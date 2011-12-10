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
	public static function getRealFSReference($path, $persistent = FALSE){
		throw new AJXP_Exception('Not implemented: getRealFSReference');
	}	

	public static function isRemote()
	{
		return true;
	}

	public static function copyFileInStream($path, $stream)
	{
		throw new AJXP_Exception('Not implemented: copyFileInStream');
	}

	public function stream_open($url, $mode, $options, &$context)
	{		
		throw new AJXP_Exception('Not implemented: stream_open');
	}

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

	public function stream_read($count)
	{    	
		throw new AJXP_Exception('Not implemented:  stream_read');
	}

	public function stream_write($data)
	{
		throw new AJXP_Exception('Not implemented: getRealFSReference');
	}

	public function stream_eof()
	{
		throw new AJXP_Exception('Not implemented: stream_eof');
	}

	public function stream_close()
	{
		throw new AJXP_Exception('Not implemented: stream_close');
	}

	public function stream_flush()
	{
		throw new AJXP_Exception('Not implemented: stream_flush');
	}

	public function url_stat($path, $flags)
	{
		throw new AJXP_Exception('Not implemented: url_stat');
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
