#!/usr/bin/env php
<?php
/**
 * Demonstrates how the Operations class can be used. It is better to require the Operations.php file
 * your code directly for increased flexibility.
 * 1. Uploads the specified file as a multiline type (unless otherwise specified).
 * 2. Polls the server until the results are complete.
 * 3. Downloads the results to the specified location.
 */
error_reporting(-1);

require_once 'vendor/autoload.php';
require_once 'Operations.php';
require_once 'patched_pemftp/ftp_class.php';

// Define CLI options
$argv = new Commando\Command();
$argv->setHelp("" .
  "Usage: ./main.php -f [file name] -l [download location] -u [username] -p [password]\n" .
  "Example: ./main.php -f ../test.csv -l /tmp -u TestKey -p e261742d-fe2f-4569-95e6-312689d049 --poll 10\n" .
  "         Upload test.csv, process it and download the results to /tmp, poll every 10s"
  ) 
->option('f')
    ->require()
    ->describedAs('The file path of the upload file')
->option('l')
    ->require()
    ->describedAs('The location of where the results file should be placed')
->option('u')
    ->require()
    ->describedAs('The username defined in the manage api keys section')
->option('p')
    ->require()
    ->describedAs('The password defined in the manage api keys section')
->option('poll')
    ->describedAs('The number of seconds to poll for (default 300)')
->option('host')
    ->describedAs('The host to connect to (default localhost)')
->option('port')
    ->describedAs('The port to connect to (default 21)')
->option('singleFile')
    ->describedAs('Whether to run in single file mode (default false)');

// Do not run any code while in help mode
if (!empty($argv['u'])){
  $operations = new Operations(new Ftp(FALSE), $argv['u'], $argv['p'], 
                               $argv['host'], $argv['port'], $argv['poll']);
  $operations->init();

  // Upload the file.
  list($err, $message) = $operations->upload($argv['f'], $argv['singleFile']);
  if (!$err){
    throw new RuntimeException($message);
  }
  echo($message);

  // Download when it is done.
  list($err, $message) = $operations->download($argv['l']);
  if (!$err){
    throw new RuntimeException($message);
  }
  echo($message);

  // Always close the FTP connection properly once done with it.
  $operations->quit();
}
?>