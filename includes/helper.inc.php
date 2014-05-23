<?php

// Return an internal error code and message.
function returnInternalError($code, $rawmessage='')
{
   $message = 'unknown error';

   switch($code) {
      case '800':
         $message = 'Function error';
         break;
      case '900':
         $message = 'Internal database error';
         break;
   }

   appLog("HELPER", "returnInternalError() - $code - $message - $rawmessage");
   $errobj = array('result' => false, 'appError' => $code, 'description' => $message, 'raw' => $rawmessage);
   return $errobj;
}

// Log message to logfile.
function appLog($system, $message, $raw = "")
{
   global $logfile, $debug, $debug_raw;

   // Check debug flag.
   if ( $debug == false )
      return; 

   $time = date("Y-m-d H:m:s", time());

   $logmessage = $time ." [". strtoupper($system) ."] - ". $message;
   $logmessage .= "\n";

   if ( $debug_raw == true )
      $logmessage .= print_r($raw, true) ."\n";

   file_put_contents($logfile, $logmessage, FILE_APPEND | LOCK_EX);
}

?>
