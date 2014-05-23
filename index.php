<?php
require_once('includes/config.inc.php');
require_once('includes/caching.inc.php');
require_once('includes/helper.inc.php');
require_once "Dropbox/autoload.php";
include 'vendor/autoload.php';
use \Dropbox as dbx;
use Sabre\DAV;
use Sabre\HTTP;

// Change PHP error reporting.
error_reporting(E_ALL);
ini_set('display_errors', 'Off');
ini_set('error_log', $GLOBALS['logfile']);

// Get medtadata from given Dropbox path.
// Handle caching.
function getMetadataForPath($path)
{
	global $cachetime, $wd4d_app_key, $access_token, $username;
	$dbxclient = new dbx\Client($access_token, $wd4d_app_key);

	$result = mysqlGetFromCache($username, $path);
	if ( $result['result'] == false )
	{
		appLog("debug", "No cache record available for: $path");
		$metadata = $dbxclient->getMetadataWithChildren($path);
		$result = mysqlAddToCache($username, $path, $metadata);
	} else
	{
		appLog("debug", "Got cache record for: $path");
		$cachets = $result['timestamp'];
		$cacheexpire = $cachets+$cachetime;

		// Check whether the cache is expired.
		if ( $cacheexpire < time() )
		{
			appLog("debug", "Cache expired. Fetching new data");
			$metadata = $dbxclient->getMetadataWithChildren($path);
			$result_delete = mysqlDeleteFromCache($username, $path);
			$result_add = mysqlAddToCache($username, $path, $metadata);
		} else
		{
			appLog("debug", "Cache still valid.");
			$metadata = $result['cachedata'];
		}
	}
	return $metadata;
}

/* DropboxDirectory:
     __construct($path)
     getChildren()
     getChild($item)
     childExists($name)
     createDirectory($name)
     createFile($name,$data)
     delete()
     getName()
     setName($newName)
     getLastModified() */
class DropboxDirectory extends DAV\Collection
{
	private $path;
	private $access_token;
	private $username;
	private $dbxclient;
	private $metadata;
	private $create_array = array();

	// Constructor.
	function __construct($path)
	{
		appLog("debug", "Create: DropboxDirectory($path)");
		$this->path = $path; 
		$this->dropbox_client = $GLOBALS['wd4d_app_key'];
		$this->access_token = $GLOBALS['access_token'];
		$this->username = $GLOBALS['username'];
		$this->dbxclient = new dbx\Client($this->access_token, $this->dropbox_client);
	}

	// Returns an array of file and/or directory objects.
	function getChildren()
	{
		appLog("debug", "DropboxDirectory - getChildren() for: ". $this->path);
		$this->metadata = getMetadataForPath($this->path);
		$children = array();

		foreach ( $this->metadata['contents'] as $item )
		{
			if ( !array_key_exists('is_deleted', $item) || ( array_key_exists('is_deleted', $item) && $item['is_deleted'] == false ) ) 
				$children[] = $this->getChild(basename($item['path']));
		}
		return $children;
	}

	// Returns a file or directory object for the given child-node name.
	function getChild($item)
	{
		appLog("debug", "DropboxDirectory - getChild() for item: $item");

		// Check whether metadata object exists.
		if ( $this->metadata == null )
			$this->metadata = getMetadataForPath($this->path);

		foreach ( $this->metadata['contents'] as $tmp )
		{
			if ( basename($tmp['path']) == $item )
			{
				if ( $tmp['is_dir'] == true )
				{
					appLog("debug", "DropboxDirectory - getChild() - Returning: DirectoryItem: ". $tmp['path']);
					return new DropboxDirectory($tmp['path']);
				} else
				{
					appLog("debug", "DropboxDirectory - getChild() - Returning: FileItem: ". $tmp['path']);
					return new DropboxFile($tmp, $this->dbxclient);
				}
			}
		}
		// Throw an exception if the item wasn't found.
		appLog("debug", "DropboxDirectory - getChild() - Returning: DAV\Exception\NotFound");
		throw new DAV\Exception\NotFound($item .': No such file or directory');
	}

	// Returns true if a child node exists.
	function childExists($name)
	{
		appLog("debug", "DropboxDirectory - childExists() for: ". $name);

		// Check whether metadata object exists.
		if ( $this->metadata == null )
			$this->metadata = getMetadataForPath($this->path);

		foreach ( $this->metadata['contents'] as $item )
		{
			if ( basename($item['path']) == $name )
			{
				appLog("debug", "DropboxDirectory - childExists() result: FALSE");
				return true;
			}
		}
		appLog("debug", "DropboxDirectory - childExists() result: FALSE");
		return false;
	}

	// Creates a subdirectory with the given name.
	function createDirectory($name)
	{
		appLog("debug", "DropboxDirectory - createDirectory() with name: $name");
		$metadata = $this->dbxclient->createFolder($this->path ."/". $name);
		cacheAddItem("DropboxDirectory", $this->username, $this->path, $this->metadata, $metadata);
		appLog("debug", "DropboxDirectory - createDirectory() result: ", $metadata);
	}

	// Creates a new file with the given name.
	function createFile($name, $data = NULL)
	{
		appLog("debug", "DropboxDirectory - createFile() with name: $name");
		$metadata = $this->dbxclient->uploadFile(dirname($this->path) ."/". $name,
			dbx\WriteMode::force(), $data);
		cacheAddItem("DropboxDirectory", $this->username, $this->path, $this->metadata, $metadata);
		appLog("debug", "DropboxDirectory - createFile() result: ", $metadata);
	}

	// Deletes the given directory.
	function delete()
	{
		appLog("debug", "DropboxDirectory - delete() this folder: ". $this->path);
		$this->dbxclient->delete($this->path);
		cacheRemoveItem("DropboxDirectory", $this->username, $this->path);
	}

	// Returns the file/directory name.
	function getName()
	{
		appLog("debug", "DropboxDirectory - getName() for: ". $this->path ." (". basename($this->path) .")");
		return basename($this->path);
	}

	// Renames the directory.
	function setName($name)
	{
		appLog("debug", "DropboxDirectory - rename() this folder: ". $this->path ." to ". dirname($this->path) . $name);
		$metadata = $this->dbxclient->move($this->path, dirname($this->path) . $name);
		cacheChangePath("DropboxDirectory", $this->username, $this->path, $metadata);
	}

	// Returns the last modification time as a unix timestamp.
	function getLastModified()
	{
		appLog("debug", "DropboxDirectory - getLastModified() for folder: ". $this->path);

		// Check whether metadata object exists.
		if ( $this->metadata == null )
			$this->metadata = getMetadataForPath($this->path);

		// Check if there's a valid 'modified' timestamp.
		if ( array_key_exists('modified', $this->metadata) )
			$lastmodified = date_create_from_format('D, d M Y H:i:s P', $this->metadata['modified']);
		else
			$lastmodified = $datetime = new DateTime();
		appLog("debug", "DropboxDirectory - getLastModified() result: ". $lastmodified->getTimestamp());
		return $lastmodified->getTimestamp();
	}
}

/* DropboxFile:
     __construct($item)
     delete()
     getName()
     setName($name)
     getLastModified()
     put($data)
     get()
     getETag()
     getContentType()
     getSize() */
class DropboxFile extends DAV\File
{
	private $path;
	private $metadata;

	// Constructor.
	function __construct($metadata, $dbxclient)
	{
		appLog("debug", "Create DropboxFile(". $metadata['path'] .")");
		$this->metadata = $metadata;
		$this->path = $metadata['path'];
		$this->access_token = $GLOBALS['access_token'];
		$this->username = $GLOBALS['username'];
		$this->dbxclient = $dbxclient;
	}
	// Deletes the file.
	function delete()
	{
		appLog("debug", "DropboxFile - delete() this file: ". $this->path);
		$this->dbxclient->delete($this->path);
		cacheRemoveItem("DropboxFile", $this->username, $this->path);
	}

	// Returns the file/directory name.
	function getName()
	{
		appLog("debug", "DropboxFile - getName() for: ". $this->path);
		return basename($this->metadata['path']);
	}

	// Renames the file.
	function setName($name)
	{
		appLog("debug", "DropboxFile - rename() this file: ". $this->path ." to ". dirname($this->path) . $name);
		$metadata = $this->dbxclient->move($this->path, dirname($this->path) . $name);
		cacheChangePath("DropboxFile", $this->username, $this->path, $metadata);
	}

	// Returns the last modification time as a unix timestamp.
	function getLastModified()
	{
		appLog("debug", "DropboxFile - getLastModified() for file: ". $this->path);
		$lastmodified = date_create_from_format('D, d M Y H:i:s P', $this->metadata['modified']);
		appLog("debug", "DropboxFile - getLastModified() result: ". $lastmodified->getTimestamp());
		return $lastmodified->getTimestamp();
	}

	// Updates the data in the file.
	function put($data)
	{
		appLog("debug", "DropboxFile - put() for: ". $this->path);
		$result = $this->dbxclient->uploadFile($this->path,
			dbx\WriteMode::force(), $data);
                appLog("debug", "DropboxFile - put() result: ", $result);
	}

	// Returns the contents of the file.
	function get()
	{
		appLog("debug", "DropboxFile - get() for: ". $this->path);
		$url = $this->dbxclient->createTemporaryDirectLink($this->path);
		appLog("debug", "DropboxFile - get() result: ". $url[0]);
		$urlhandle = fopen($url[0], "r");
		return $urlhandle;
	}

	// Returns a unique identifier of the current state of the file. If the file changes,
	// so should the etag. Etags are surrounded by quotes.
	function getETag()
	{
		appLog("debug", "DropboxFile - getETag() for: ". $this->path);
		$etag = '"'. $this->metadata['rev'] .'"';
		appLog("debug", "DropboxFile - getETag() result: ". $etag);		
		return $etag; 
	}

	// Returns the mime-type of the file.
	function getContentType()
	{
		appLog("debug", "DropboxFile - getContentType() for file: ". $this->path);
		appLog("debug", "DropboxFile - getContentType() result: ". $this->metadata['mime_type']);
		return $this->metadata['mime_type'];
	}

	// Returns the size of a file in bytes.
	function getSize()
	{
		appLog("debug", "DropboxFile - getSize() for: ". $this->path);
		appLog("debug", "DropboxFile - getSize() result: ". $this->metadata['bytes']);
		return $this->metadata['bytes'];
	}
}

// Get username from authentication environment.
$username = $_SERVER['PHP_AUTH_USER'];
$access_token = "";

// Check if there's a configured accesstoken for the authenticated user.
if ( array_key_exists($username, $wd4d_users) )
{
	if ( $wd4d_users[$username] != "" )
	{
		$access_token = $wd4d_users[$username];

		// Let's start WebDAV.
		$initdirectory = new DropboxDirectory("/");
		$browserplugin = new \Sabre\DAV\Browser\Plugin(true, true);
		$server = new DAV\Server($initdirectory);
		$server->setBaseUri($GLOBALS['wd4d_root']);
		$server->addPlugin($browserplugin);
		$server->exec();
	} else
	{
		echo "Accesstoken missing for user: ". $username;
	}
} else
{
	echo "Configuration missing for user: ". $username;
}

?>
