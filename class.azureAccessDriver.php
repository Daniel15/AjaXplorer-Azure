<?php
defined('AJXP_EXEC') or die('Access not allowed');

// Add azuresdk directory to include_path
set_include_path(get_include_path() . PATH_SEPARATOR . __DIR__ . '/azuresdk');
require 'Microsoft/WindowsAzure/Storage/Blob.php';

class azureAccessDriver extends AbstractAccessDriver
{
	// TODO: Make this a setting
	const BLOB_URL = '127.0.0.1:10000';
	
	public function initRepository()
	{
		$this->storage = new Microsoft_WindowsAzure_Storage_Blob(self::BLOB_URL);
		$this->storage->registerStreamWrapper();
		$this->wrapperClassName = 'azureAccessWrapper';
	}
	
	public function switchAction($action, $httpVars, $fileVars)
	{
		$dir = !empty($httpVars['dir']) ? $httpVars['dir'] : '';
		$dir = AJXP_Utils::securePath($dir);
		if ($dir == '/')
			$dir = '';
		
		switch ($action)
		{
			case 'download':
				$this->download($httpVars['file']);
				return;
			case 'ls':
				$this->dirListing($dir);				
				return;
			default:
				throw new AJXP_Exception('Action not implemented: ' . $action);
		}
	}
	
	private static function splitContainerNamePath($path)
	{
		// Remove trailing slash
		$path = ltrim($path, '/');
		// Separate container and file names
		$containerPos = strpos($path, '/');
		$container = substr($path, 0, $containerPos);
		$path = substr($path, $containerPos + 1);
		
		return (object)array(
			'container' => $container,
			'path' => $path
		);
	}
	
	private static function splitContainerNamePathFile($path)
	{
		// First split into container and path
		$pathinfo = self::splitContainerNamePath($path);
		// Now split the file out from that
		$pathinfo->filename = substr($pathinfo->path, strrpos($pathinfo->path, '/') + 1);
		
		return $pathinfo;
	}
	
	/**
	 * Redirect to the download for a particular file
	 * @param	String	The full path to the file, including the container
	 */
	private function download($path)
	{
		$pathinfo = self::splitContainerNamePathFile($path);
		
		// Stream the blob to the user
		/*$handle = fopen('azure://' . $container . '/' . $path, 'r');
		fpassthru($handle);
		fclose($handle);*/
		
		// Load the blob
		$data = $this->storage->getBlobData($pathinfo->container, $pathinfo->path);
		header('Content-Type: application/octet-stream');
		header('Content-Disposition: attachment; filename=' . $pathinfo->filename);
		header('Content-Length: ' . strlen($data));
		echo $data;
		die();
	}
	
	/**
	 * Load a directory listing for a specified directory
	 * @param	String		Directory to get the listing for
	 */
	private function dirListing($dir)
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
				'mimestring' => AJXP_Utils::mimetype($blob->name, 'type', $blob->isprefix)
			);
			
			$renderNodeData = array(
				AJXP_Utils::xmlEntities('/' . $blob->container . '/' . $blob->name, true), 	// nodeName
				AJXP_Utils::xmlEntities($filename, true),								// nodeLabel
				!$blob->isprefix,														// isLeaf (is a file)
				$metaData
			);
			
			// Add this file to the correct array (dirs or files)
			$fullList[$blob->isprefix ? 'dirs' : 'files'][] = $renderNodeData;
		}
		
		// Render all the nodes
		array_map(array('AJXP_XMLWriter', 'renderNodeArray'), $fullList['dirs']);
		array_map(array('AJXP_XMLWriter', 'renderNodeArray'), $fullList['files']);

		AJXP_XMLWriter::close();
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
}
?>
