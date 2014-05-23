<?php

// Change a cached path.
function cacheChangePath($type, $mysqli, $username, $oldpath, $metadata)
{
   $newpath = $metadata['path'];
   appLog("DEBUG-WD4D", "$type - cacheChangePath(): $oldpath -> $newpath");
   // Get metadata of parent object.
   $parent = mysqlGetFromCache($mysqli, $username, dirname($oldpath));
   if ( $parent['result'] == true )
   {
      $i = 0;
      foreach ( $parent['cachedata']['contents'] as $item )
      {
         if ( $item['path'] == $oldpath )
            $parent['cachedata']['contents'][$i] = $metadata;
         $i++;
      }
      appLog("DEBUG-WD4D", "$type - cacheChangePath(): Updating cache");
      $result = mysqlUpdateCache($mysqli, $username, dirname($oldpath), $parent['cachedata']);
      if ( $type == "DropboxDirectory" )
         $result = mysqlUpdateCachePath($mysqli, $username, $oldpath, $newpath);
   }
}

// Add a folder to cache.
function cacheAddItem($type, $mysqli, $username, $path, $oldmetadata, $metadata)
{
   appLog("DEBUG-WD4D", "$type - cacheAddItem(". $metadata['path'] .")");
   array_push($oldmetadata['contents'], $metadata);
   $result = mysqlUpdateCache($mysqli, $username, $path, $oldmetadata);
}

// Remove an item from cache.
function cacheRemoveItem($type, $mysqli, $username, $path)
{
   appLog("DEBUG-WD4D", "$type - cacheRemoveItem() for: ". $path);
   $parent = mysqlGetFromCache($mysqli, $username, dirname($path));
   $contents = array();
   if ( $parent['result'] == true )
   {
      $i = 0;
      foreach ( $parent['cachedata']['contents'] as $item )
      {
         if ( $item['path'] != $path )
            array_push($contents, $item);
         $i++;
      }
      $parent['cachedata']['contents'] = $contents;
      appLog("DEBUG-WD4D", "$type - cacheRemoveItem for: $path - Updating cache");
      $result = mysqlUpdateCache($mysqli, $username, dirname($path), $parent['cachedata']);
      if ( $type == "DropboxDirectory" )
         $result = mysqlDeleteFromCache($mysqli, $username, $path);
   }
}

// Connect to MySQL server.
function appMysqlConnect()
{
   global $mysql_host, $mysql_user, $mysql_db, $mysql_pass;
   appLog("MYSQL-WD4D", "appMysqlConnect($mysql_host, $mysql_user, $mysql_pass , $mysql_db)");
   $mysqli = new mysqli($mysql_host, $mysql_user, $mysql_pass, $mysql_db);
   if ( !mysqli_connect_errno() )
   {
      appLog("MYSQL-MAIN", "Successfully connected to mysql server");
      return $mysqli;
   } else
   {
      return returnInternalError('900', mysqli_connect_error());
   }
}

// Add object to cache.
function mysqlAddToCache($username, $path, $cachedata)
{
   appLog("MYSQL-WD4D", "mysqlAddToCache($username, $path)");
   $mysqli = appMysqlConnect();

   // Add cache record to database.
   $query = "INSERT INTO wd4d_cache (username, path, cachedata, timestamp) VALUES ".
            "(?,?,?,?) ON DUPLICATE KEY UPDATE cachedata=?,timestamp=?";
   $stmt = $mysqli->prepare($query);
   if ( !$stmt )
   {
      $errmsg = $mysqli->error;
      mysqli_close($mysqli);
      returnInternalError('900', $errmsg);
   }
   $time = time();
   $sercachedata = serialize($cachedata);
   $stmt->bind_param("sssisi", $username, $path, $sercachedata, $time, $sercachedata, $time);
   if ( $stmt->execute() )
   {
      $stmt->close();
      mysqli_close($mysqli);
      appLog("MYSQL-WD4D", "Successfully added to cache: $path");
      return array('result' => true);
   } else
   {
      $errmsg = $stmt->error;
      $stmt->close();
      mysqli_close($mysqli);
      return returnInternalError('900', $errmsg);
   }
}

// Get object from cache.
function mysqlGetFromCache($username, $path)
{
   appLog("MYSQL-WD4D", "mysqlGetFromCache($username, $path)");
   $mysqli = appMysqlConnect();

   $query = "SELECT cachedata, timestamp FROM wd4d_cache WHERE username=? and path=?";
   $stmt = $mysqli->prepare($query);
   if ( !$stmt )
   {
      $errmsg = $mysqli->error;
      mysqli_close($mysqli);
      returnInternalError('900', $errmsg);
   }
   $stmt->bind_param("ss", $username, $path);
   $stmt->execute();
   if ( !$stmt->execute() )
   {
      $errmsg = $mysqli->error;
      $stmt->close();
      mysqli_close($mysqli);
      return returnInternalError('900', $errmsg);
   }
   $stmt->store_result();
   if ( $stmt->num_rows == 1 )
   {
      $stmt->bind_result($cachedata, $timestamp);
      $stmt->fetch();
      $stmt->close();
      mysqli_close($mysqli);
      appLog("MYSQL-WD4D", "Successfully fetched cache record for user $username: $path)");
      return array(
         'result' => true,
         'cachedata' => unserialize($cachedata),
         'timestamp' => $timestamp);
   } else
   {
      // Error fetching userdata.
      if ( $stmt->num_rows == 0 )
      {
         $stmt->close();
         mysqli_close($mysqli);
         return array('result' => false);
      } else
      {
         $stmt->close();
         mysqli_close($mysqli);
         return returnInternalError('800', 'More than one cache record found');
      }
   }
}

// Update cache data.
function mysqlUpdateCache($username, $path, $cachedata)
{
   appLog("MYSQL-WD4D", "mysqlUpdateCache($username, $path)");
   $mysqli = appMysqlConnect();

   $query = "UPDATE wd4d_cache SET cachedata=?, timestamp=? WHERE username=? and path=?";
   $stmt = $mysqli->prepare($query);
   if ( !$stmt )
   {
      $errmsg = $mysqli->error;
      mysqli_close($mysqli);
      returnInternalError('900', $errmsg);
   }
   $time = time();
   $sercachedata = serialize($cachedata);
   $stmt->bind_param("siss", $sercachedata, $time, $username, $path);
   $stmt->execute();
   if ( $stmt->execute() )
   {
       $stmt->close();
       mysqli_close($mysqli);
       appLog("MYSQL-WD4D", "Cache successfully updated for user: $username ($path)");
       return array('result' => true);
   } else
   {
      $errmsg = $stmt->error;
      $stmt->close();
      mysqli_close($mysqli);
      return returnInternalError('900', $errmsg);
   }
}

// Update path within cache data.
function mysqlUpdateCachePath($username, $oldpath, $newpath)
{
   appLog("MYSQL-WD4D", "mysqlUpdateCachePath($username, $oldpath, $newpath)");
   $mysqli = appMysqlConnect();

   $query = "UPDATE wd4d_cache SET path=?,timestamp=? WHERE username=? and path=?";
   $stmt = $mysqli->prepare($query);
   if ( !$stmt )
   {
      $errmsg = $mysqli->error;
      mysqli_close($mysqli);
      returnInternalError('900', $errmsg);
   }
   $time = time();
   $stmt->bind_param("siss", $newpath, $time, $username, $oldpath);
   $stmt->execute();
   if ( $stmt->execute() )
   {
       $stmt->close();
       mysqli_close($mysqli);
       appLog("MYSQL-WD4D", "Path in cache successfully updated for user: $username ($oldpath -> $newpath)");
       return array('result' => true);
   } else
   {
      $errmsg = $stmt->error;
      $stmt->close();
      mysqli_close($mysqli);
      return returnInternalError('900', $errmsg);
   }
}

// Delete object from cache.
function mysqlDeleteFromCache($username, $path)
{
   appLog("MYSQL-WD4D", "mysqlDeleteFromCache($username, $path)");
   $mysqli = appMysqlConnect();

   // Remove cache record from database.
   $query = "DELETE FROM wd4d_cache WHERE username = ? and path = ?";
   $stmt = $mysqli->prepare($query);
   if ( !$stmt )
   {
      $errmsg = $mysqli->error;
      mysqli_close($mysqli);
      returnInternalError('900', $errmsg);
   }
   $stmt->bind_param("ss", $username, $path);
   if ( $stmt->execute() )
   {
      $stmt->close();
      mysqli_close($mysqli);
      appLog("MYSQL-WD4D", "Successfully deleted cache record '$path' for username: $username");
      return array('result' => true);
   } else
   {
      $errmsg = $stmt->error;
      $stmt->close();
      mysqli_close($mysqli);
      return returnInternalError('900', $errmsg);
   }
}

?>
