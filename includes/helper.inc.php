<?php

/*
  Copyright (c) 2014 Sascha Schmidt <sascha@schmidt.ps> (author)
  http://www.schmidt.ps

  Permission to use, copy, modify, and distribute this software for any
  purpose with or without fee is hereby granted, provided that the above
  copyright notice and this permission notice appear in all copies.

  THE SOFTWARE IS PROVIDED "AS IS" AND THE AUTHOR DISCLAIMS ALL WARRANTIES
  WITH REGARD TO THIS SOFTWARE INCLUDING ALL IMPLIED WARRANTIES OF
  MERCHANTABILITY AND FITNESS. IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR
  ANY SPECIAL, DIRECT, INDIRECT, OR CONSEQUENTIAL DAMAGES OR ANY DAMAGES
  WHATSOEVER RESULTING FROM LOSS OF USE, DATA OR PROFITS, WHETHER IN AN
  ACTION OF CONTRACT, NEGLIGENCE OR OTHER TORTIOUS ACTION, ARISING OUT OF
  OR IN CONNECTION WITH THE USE OR PERFORMANCE OF THIS SOFTWARE.
*/

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
