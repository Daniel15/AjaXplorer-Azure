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
 * Description: Driver for access to Windows Azure blob storage
 */
 
defined('AJXP_EXEC') or die('Access not allowed');

// Add azuresdk directory to include_path
set_include_path(get_include_path() . PATH_SEPARATOR . __DIR__ . '/azuresdk');
require 'Microsoft/WindowsAzure/Storage/Blob.php';

/**
 * Implements Azure Blob storage access
 */
class azureAccessDriver extends AbstractAccessDriver
{
	private $http_cdn_base;
	private $https_cdn_base;
	
	/**
	 * Initialise this repository
	 */
	public function initRepository()
	{
		// Using dev storage?
		if ($this->repository->getOption('AZURE_USE_DEV'))
		{
			$this->storage = new Microsoft_WindowsAzure_Storage_Blob();
		}
		else
		{
			$host = $this->repository->getOption('AZURE_HOST');
			$account = $this->repository->getOption('AZURE_ACCOUNT');
			$key = $this->repository->getOption('AZURE_ACCESS_KEY');
			$this->storage = new Microsoft_WindowsAzure_Storage_Blob($host, $account, $key);
		}
		
		// CDN base URLs
		$this->http_cdn_base = $this->repository->getOption('AZURE_CDN_HTTP');
		$this->https_cdn_base = $this->repository->getOption('AZURE_CDN_HTTPS');
		
		// Register the Azure stream wrapper
		$this->storage->registerStreamWrapper();
		//$this->wrapperClassName = 'azureAccessWrapper';
	}
	
	/**
	 * Perform the specified action
	 */
	public function switchAction($action, $httpVars, $fileVars)
	{
		parent::accessPreprocess($action, $httpVars, $fileVars);
		
		// Ensure directory is valid
		$dir = !empty($httpVars['dir']) ? $httpVars['dir'] : '';
		$dir = AJXP_Utils::securePath($dir);
		if ($dir == '/')
			$dir = '';
		$dir = AJXP_Utils::decodeSecureMagic($dir);
		
		// Populate the user's selection	
		$selection = new UserSelection();
		$selection->initFromHttpVars($httpVars);
		
		// Ensure the method exists
		if (!method_exists($this, $action))
		{
			throw new AJXP_Exception('Action not implemented: ' . $action);
		}
		
		// Call the method
		return $this->$action($httpVars, $fileVars, $dir, $selection);
	}
	
	/**
	 * Redirect to the download for a particular file
	 * @param	String	The full path to the file, including the container
	 */
	private function download($httpVars)
	{
		return $this->returnFile($httpVars['file'], 'application/octet-stream');
	}
	
	/**
	 * Load a directory listing for a specified directory
	 * @param	String		Directory to get the listing for
	 */
	private function ls($httpVars, $fileVars, $dir)
	{
		// If we're at the root, render the list of containers instead
		if ($dir == '')
		{
			$this->containerListing();
			return;
		}
		
		$fullList = array('dirs' => array(), 'files' => array());
		
		// Remove the trailing slash
		$dir = ltrim($dir, '/');
		$containerPos = strpos($dir, '/');
		
		// If there's no slash, we're at the root of a container
		if ($containerPos === false)
		{
			$container = $dir;
			$subDir = '';
		}
		else
		{
			$container = substr($dir, 0, $containerPos);
			$subDir = substr($dir, $containerPos + 1) . '/';
		}
		
		AJXP_XMLWriter::renderHeaderNode(
			AJXP_Utils::xmlEntities($dir, true), 
			AJXP_Utils::xmlEntities(rtrim($subDir, '/'), true), 
			false, 
			array());
		
		// Get all the blobs in this container
		$blobs = $this->storage->listBlobs($container, $subDir, '/');
		foreach ($blobs as $blob)
		{
			// Add this file to the correct array (dirs or files)
			$fullList[$blob->isprefix ? 'dirs' : 'files'][] = $this->getNode($blob);
		}
		
		// Render all the nodes
		array_map(array('AJXP_XMLWriter', 'renderNodeArray'), $fullList['dirs']);
		array_map(array('AJXP_XMLWriter', 'renderNodeArray'), $fullList['files']);

		AJXP_XMLWriter::close();
	}
	
	/**
	 * Get a node for the directory listing
	 * @param	Microsoft_WindowsAzure_Storage_BlobInstance		Blob to add as a node
	 * @return	Array	Node information
	 */
	private function getNode(Microsoft_WindowsAzure_Storage_BlobInstance $blob)
	{
		$filename = rtrim($blob->name, '/');
		
		// If the blob is in a subdirectory, get its filename
		if (($dirPos = strpos($filename, '/')) !== false)
		{
			$filename = substr($filename, $dirPos + 1);
		}
		
		$metaData = array(
			'icon' => AJXP_Utils::mimetype($blob->name, 'image', $blob->isprefix),
			'bytesize' => $blob->size,
			'filesize' => AJXP_Utils::roundSize($blob->size),
			'ajxp_modiftime' => @strtotime($blob->lastmodified),
			'mimestring' => AJXP_Utils::mimetype($blob->name, 'type', $blob->isprefix),
			'bloburl' => $blob->url,
		);
		
		// Are there CDN URLs set?
		if (!empty($this->http_cdn_base))
			$metaData['cdn'] = $this->http_cdn_base . $blob->container . '/' . $blob->name;
		if (!empty($this->https_cdn_base))
			$metaData['cdn_ssl'] = $this->https_cdn_base . $blob->container . '/' . $blob->name;
		
		return array(
			AJXP_Utils::xmlEntities('/' . $blob->container . '/' . $blob->name, true), 	// nodeName
			AJXP_Utils::xmlEntities($filename, true),									// nodeLabel
			!$blob->isprefix,															// isLeaf (is a file)
			$metaData
		);
	}
	
	/**
	 * Get a listing of all the containers in Azure blob storage
	 */
	private function containerListing()
	{
		AJXP_XMLWriter::renderHeaderNode(
			'', 
			'', 
			false, 
			array());
			
		// Get a list of all the Azure containers
		$containers = $this->storage->listContainers();
		foreach ($containers as $container)
		{
			$metaData = array(
				'icon' => 'folder.png'
			);
			
			$nodeName = AJXP_Utils::xmlEntities('/' . $container->name);
			$nodeLabel = AJXP_Utils::xmlEntities($container->name);
			
			// Write this container to the XML document
			AJXP_XMLWriter::renderNode($nodeName, $nodeLabel, false, $metaData);
		}
		AJXP_XMLWriter::close();
	}
	
	/**
	 * Rename a file
	 */
	private function rename($httpVars)
	{
		$file = AJXP_Utils::decodeSecureMagic($httpVars['file']);
		$filename_new = AJXP_Utils::decodeSecureMagic($httpVars['filename_new']);
		
		$pathinfo = self::splitContainerNamePathFile($file);
		
		// Actually do the rename. The only way to rename on Azure is to copy and then delete the
		// old version.
		$this->storage->copyBlob(
			// Old container and path
			$pathinfo->container, $pathinfo->path, 
			// New container and path
			$pathinfo->container, $pathinfo->subDir . '/' . $filename_new);
			
		// Delete the old version
		$this->storage->deleteBlob($pathinfo->container, $pathinfo->path);
		
		// Log it
		AJXP_Logger::logAction('Rename', array('original' => $file, 'new' => $filename_new));		
		$logMessage = SystemTextEncoding::toUTF8($file) . ' was renamed to ' . SystemTextEncoding::toUTF8($filename_new);
		
		return 
			AJXP_XMLWriter::sendMessage($logMessage, null, false) . 
			AJXP_XMLWriter::reloadDataNode('', $filename_new, false);
	}
	
	/**
	 * Delete a file
	 */
	private function delete($httpVars, $fileVars, $dir, $selection)
	{
		// Ensure something is selected
		if ($selection->isEmpty())
			throw new AJXP_Exception('', 113);
		
		$logMessages = array();
		
		foreach ($selection->getFiles() as $selectedFile)
		{
			// Actually do the delete
			$pathinfo = self::splitContainerNamePath($selectedFile);
			$this->storage->deleteBlob($pathinfo->container, $pathinfo->path);
			AJXP_Controller::applyHook("move.metadata", array($fileToDelete));
			
			// Log it
			$logMessages[] = 'Deleted ' . SystemTextEncoding::toUTF8($selectedFile);
		}
		
		AJXP_Logger::logAction('Delete', array('files' => $selection));
		
		return
			AJXP_XMLWriter::sendMessage(implode("\n", $logMessages), null, false) .
			AJXP_XMLWriter::reloadDataNode('', '', false);
	}
	
	/**
	 * Online edit (eg. CodeMirror) - Load file
	 */
	private function get_content($httpVars, $fileVars, $dir, $selection)
	{
		$file = $selection->getUniqueFile($httpVars['file']);
		$this->returnFile($file, 'text/plain');
	}
	
	/**
	 * Online edit (eg. CodeMirror) - Save file
	 */
	private function put_content($httpVars, $fileVars, $dir, $selection)
	{
		// No content? What?
		if (!isset($httpVars['content']))
			return;
		
		$file = $selection->getUniqueFile($httpVars['file']);
		$pathinfo = self::splitContainerNamePathFile($file);
		
		// Get the code
		$code = $httpVars['content'];
		if (isset($httpVars['encode']) && $httpVars['encode'] == 'base64')
		{
			$code = base64_decode($code);
		}
		else
		{
			$code = SystemTextEncoding::magicDequote($code);
			$code = str_replace('&lt;', '<', $code);
		}
		
		AJXP_Logger::logAction('Edited online', array('file' => $file));
		
		// Save the content
		$this->storage->putBlobData($pathinfo->container, $pathinfo->path, $code);
		
		header('Content-Type: text/plain');
		echo 'Saved.';
		//return AJXP_XMLWriter::sendMessage('Saved.', null, false);
	}
	
	private function copy($httpVars, $fileVars, $dir, $selection)
	{
		return $this->copyFile($httpVars, $selection, false);
	}
	
	private function move($httpVars, $fileVars, $dir, $selection)
	{
		return $this->copyFile($httpVars, $selection, true);
	}
	
	/**
	 * Copy a file
	 * @param	Array	http variables
	 * @param	Array	Selected files
	 * @param	Boolean	True to move the file, false to copy it
	 */
	private function copyFile($httpVars, $selection, $move)
	{
		if ($selection->isEmpty())
			throw new AJXP_Exception('', 113);
			
		$dest = AJXP_Utils::decodeSecureMagic($httpVars['dest']);
		$destinfo = self::splitContainerNamePath($dest);
		$success = array();
		
		// If it's going into a subdirectory, ensure there's a slash at the end
		if (!empty($destinfo->path))
			$destinfo->path .= '/';
		
		foreach ($selection->getFiles() as $selectedFile)
		{
			$pathinfo = self::splitContainerNamePathFile($selectedFile);
			$destFile = $destinfo->path . $pathinfo->filename;
			$destPath = $destinfo->container . '/' . $destFile;
			
			$this->storage->copyBlob(
				// Source container and path
				$pathinfo->container, $pathinfo->path, 
				// Destination container and path
				$destinfo->container, $destFile);
				
			// If moving, delete the original
			if ($move)
			{
				$this->storage->deleteBlob($pathinfo->container, $pathinfo->path);
			}
				
			AJXP_Controller::applyHook('move.metadata', array($selectedFile, $destFile, false));
			$success[] = ($move ? 'Moved ' : 'Copied ') . $selectedFile . ' to '. $destPath;
		}
		
		AJXP_Logger::logAction('Copy', array('files' => $selection, 'destination' => $dest));
		
		return
			AJXP_XMLWriter::sendMessage(implode("\n", $success), null, false) .
			// Reload current directory
			AJXP_XMLWriter::reloadDataNode('', '', false) .
			// Reload destination directory
			AJXP_XMLWriter::reloadDataNode($dest, '', false);
	}

	/**
	 * Upload a file
	 */
	private function upload($httpVars, $fileVars, $dir, $selection)
	{
		$pathinfo = self::splitContainerNamePath($dir);
		// If it's going into a subdirectory, ensure there's a slash at the end
		if (!empty($pathinfo->path))
			$pathinfo->path .= '/';
			
		foreach ($fileVars as $boxName => $boxData)
		{
			// Skip data that isn't files we want
			if (substr($boxName, 0, 9) != 'userfile_')
				continue;
				
			// Check for errors in this file
			$err = AJXP_Utils::parseFileDataErrors($boxData);
			if ($err != null)
				return array('ERROR' => array('CODE' => $err[0], 'MESSAGE' => $err[1]));
				
			if (isset($boxData['input_upload']))
				throw new Exception('Not implemented: input_upload mode');
			
			$filename = AJXP_Utils::sanitize(SystemTextEncoding::magicDequote($boxData['name']), AJXP_SANITIZE_HTML_STRICT);
			
			// Save the file into blob storage
			$this->storage->putBlob($pathinfo->container, $pathinfo->path . $filename, $boxData['tmp_name']);
		}
	}
	
	/**
	 * Create a new blank file
	 */
	private function mkfile($httpVars, $fileVars, $dir)
	{
		$filename = AJXP_Utils::decodeSecureMagic($httpVars['filename'], AJXP_SANITIZE_HTML_STRICT);
		$pathinfo = self::splitContainerNamePath($dir);
		
		// If it's going into a subdirectory, ensure there's a slash at the end
		if (!empty($pathinfo->path))
			$pathinfo->path .= '/';
		
		// Create a blank blob
		$this->storage->putBlobData($pathinfo->container, $pathinfo->path . $filename, '');
		AJXP_Logger::logAction('Create File', array('file' => $dir . '/' . $filename));
		
		return
			AJXP_XMLWriter::sendMessage('Created new file ' . $dir . '/' . $filename, null, false) .
			// Reload current directory
			AJXP_XMLWriter::reloadDataNode('', $dir . '/' . $file, false);
	}
	
	////////////////////////////////////////////////////////////////////////////////////////////////
	// Helper methods
	////////////////////////////////////////////////////////////////////////////////////////////////
	
	/**
	 * Outputs a particular file
	 * @param	String		Path to the file
	 * @param	String		Mime type of download
	 */
	private function returnFile($path, $mimeType)
	{
		$pathinfo = self::splitContainerNamePathFile($path);
		
		AJXP_Logger::logAction('Download', array('files' => $file));
		
        set_exception_handler('download_exception_handler');
        set_error_handler('download_exception_handler');
		@register_shutdown_function("restore_error_handler");
        // Required for IE, otherwise Content-disposition is ignored
		if (ini_get('zlib.output_compression'))
			ini_set('zlib.output_compression', 'Off');
		
		// Stream the blob to the user
		/*$handle = fopen('azure://' . $container . '/' . $path, 'r');
		fpassthru($handle);
		fclose($handle);*/
		
		// Load the blob
		$data = $this->storage->getBlobData($pathinfo->container, $pathinfo->path);
		header('Expires: 0');
		header('Cache-Control: no-cache, must-revalidate');
		header('Content-Type: ' . $mimeType);
		header('Content-Disposition: attachment; filename=' . $pathinfo->filename);
		header('Content-Length: ' . strlen($data));
		echo $data;
		die();
	}
	
	/**
	 * Split a full path into a container name and the path
	 * @param	String		Full path (/containername/path or /containername/path/file.txt)
	 * @return	Object containing 'container' and 'path' properties
	 */
	private static function splitContainerNamePath($path)
	{
		// Remove trailing slash
		$path = ltrim($path, '/');
		// Separate container and file names
		$containerPos = strpos($path, '/');
		
		// If there's no slash, we're at the root of a container
		if ($containerPos === false)
		{
			$container = $path;
			$path = '';
		}
		else
		{
			$container = substr($path, 0, $containerPos);
			$path = substr($path, $containerPos + 1);
		}
		
		return (object)array(
			'container' => $container,
			'path' => $path
		);
	}
	
	/**
	 * Split a full path into a container name, path, and filename
	 * @param	String		Full path (/containername/path or /containername/path/file.txt)
	 * @return	Object containing 'container', 'path' and 'filename' properties
	 */
	private static function splitContainerNamePathFile($path)
	{
		// First split into container and path
		$pathinfo = self::splitContainerNamePath($path);
		// Now split the file out from that
		$pathPos = strrpos($pathinfo->path, '/');
		$pathinfo->filename = substr($pathinfo->path, $pathPos + 1);
		$pathinfo->subDir = substr($pathinfo->path, 0, $pathPos);
		
		return $pathinfo;
	}
}
?>
