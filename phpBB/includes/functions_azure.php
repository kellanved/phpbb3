<?php
/**
*
* @package acp
* @version $Id$
* @copyright (c) 2010 phpBB Group
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

/**
* @ignore
*/

if (!defined('IN_PHPBB'))
{
	exit;
}
if (!defined('AZURE_INSTALL'))
{
	exit;
}

set_include_path(get_include_path() . PATH_SEPARATOR . $_SERVER["RoleRoot"] . "\\approot\\");

// require($phpbb_root_path . 'includes/Microsoft/WindowsAzure/Storage.' . $phpEx);
 require('Microsoft/WindowsAzure/Storage/Blob.' . $phpEx);
// require($phpbb_root_path . 'includes/Microsoft/WindowsAzure/Credentials.php');



function path_to_container($path)
{
	$path = strtolower($path);
	$path = str_replace('\\', '', $path);
	$path = str_replace('.', '', $path);
	$path =  str_replace('/', '', $path);
	$path = "phpbb" . $path . 'container';
	return $path;
}

/**
 * Store a file in the azure storage for sharing between instances
 * @param $path The path for the filename, used to determine the container
 * @param $filename The filename on the server, used for lookup
 * @param $physical_filename The actual physical filename, supposed to be in $path for consistency
 * @return unknown_type
 */
function store_file_azure($path, $filename)
{
	global $phpbb_root_path;
	
	$blob_storage = get_azure_client();
	$container = path_to_container($path);
	if (!$blob_storage->containerExists($container))
	{
		$blob_storage->createContainer($container);
		$blob_storage->setContainerAcl($container, Microsoft_WindowsAzure_Storage_Blob::ACL_PRIVATE);
	}
	$blob_storage->putBlob($container, $filename, $phpbb_root_path . $path . '/' . $filename);
}

/**
 * Retrieve a file from azure storage
 * @param $path
 * @param $filename
 * @return mixed file data
 */
function retrieve_file_azure($path, $filename)
{
	global $phpbb_root_path;
	
	$blob_storage = get_azure_client();
	$container = path_to_container($path);
	$blob_storage->getBlob($container, $filename, $phpbb_root_path . $path . '/' . $filename);
}


function get_azure_client()
{

  if (isset($_SERVER['USERDOMAIN']) && $_SERVER['USERDOMAIN'] == 'CIS')
  {   
    $client = new Microsoft_WindowsAzure_Storage_Blob(
                                    Microsoft_WindowsAzure_Storage::URL_CLOUD_BLOB,
                                    azure_getconfig('AzureCloudStorageAccountName'),
                                    azure_getconfig('AzureCloudStorageAccountKey'),
                                    true,
                                    Microsoft_WindowsAzure_RetryPolicy::retryN(10, 250)
                                    );
  }
  else
  {
    $client = new Microsoft_WindowsAzure_Storage_Blob();
  }
        
  return $client;
}

?>