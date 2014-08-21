<?php
require_once 'FtpOperations.php';
require_once 'HttpOperations.php';

/**
 * Contains all the operations to upload, poll and download files. Unlike the Node.js scripts, this
 * operates synchronously.
 */
class Operations
{
  private $username;
  private $password;
  private $host;
  private $port;
  
  public $notify;
  public $protocol;
  public $pollEvery;
  public $ftpOperations;
  public $httpOperations;

  /**
   * The constructor adds properties to the object which are used in init.
   *
   * @param (operations) An instance of a type of protocol operations to use. Needs to be sent in
   *                     for testing purposes.
   * @param (username) The username to get into the ftp server.
   * @param (password) The password to get into the ftp server.
   * @param (host) The host to connect to. Defaults to localhost.
   * @param (port) The port to connect to. Defaults to 21.
   * @param (polling) Number of seconds to poll for. Defaults to 300 (5 minutes) if falsey.
   * @param (protocol) What protocol to use to transfer data. Defaults to http.
   * @param (notify) The full email address to notify once an upload completes.
   */
  public function __construct($operations, $username, $password, $port,
                              $host='localhost', $pollEvery = 300, $protocol = 'http', $notify=null) 
  {
    $this->username = $username;
    $this->password = $password;
    $this->host = $host;
    $this->port = $port;
    $this->pollEvery = $pollEvery;
    $this->protocol = $protocol;
    $this->notify = $notify;

    if (empty($this->host)){
      $this->host = 'localhost';
    }
    if (empty($this->pollEvery)){
      $this->pollEvery = 300;
    }
    if (empty($this->protocol)){
      $this->protocol = 'http';
    }
    if (empty($this->notify)){
      $this->notify = null;
    }

    if ($protocol === 'ftp'){
      $this->ftpOperations = $operations;
    } else {
      $this->httpOperations = $operations;
    }
  }

  /**
   * Initializes the connection with the connection options (username, key, host port).
   *
   * @return TRUE if operations could be initialized.
   */
  public function init()
  {
    if ($this->protocol === 'ftp')
    {
      return $this->ftpOperations->init($this->username, $this->password, $this->host, $this->port);
    }
    else
    {
      return $this->httpOperations->init($this->password, $this->host, $this->port);
    }
  }

  /**
   * Changes to the upload directory then uploads the specified file.
   *
   * @param (file) The location of the file to upload.
   * @param (singleFile) If the file is uploaded in single file mode.
   *
   * @return An array of the format [<upload succeeded>, <message>].
   */
  public function upload($file, $singleFile)
  {
    if ($this->protocol === 'ftp')
    {
      return $this->ftpOperations->upload($file, $singleFile);
    }
    else
    {
      return $this->httpOperations->upload($file, $singleFile, $this->notify);
    }
  }

  /**
   * Downloads the last uploaded file's (self.uploadFileName) result file(s).
   *
   * @param (location) The location to download the file(s) to.
   * @param (removeAfter) If the results file should be removed after downloading.
   * @return An array [<download succeeded>, <message>].
   */
  public function download($location, $removeAfter)
  {
    if ($this->protocol === 'ftp')
    {
      return $this->ftpOperations->download($location, $this->pollEvery, $removeAfter);
    }
    else
    {
      return $this->httpOperations->download($location, $this->pollEvery, $removeAfter);
    }
  }
  
  /**
   * Removes the results file from the server.
   *
   * @param (url) The location to remove the file from. Used in HTTP removes. Defaults to NULL.
   *
   * @return An array [<download succeeded>, <message>].
   */
  public function remove($url = NULL)
  {
    if ($this->protocol === 'ftp')
    {
      return $this->ftpOperations->remove();
    }
    else
    {
      return $this->httpOperations->remove($url);
    }
  }


  /**
   * Closes the connection properly. This should always be called at the end of a program using this
   * class for protocols that support it. Currently, that is just FTP.
   */
  public function quit()
  {
    if ($this->protocol === 'ftp')
    {
      $this->ftpOperations->quit();
    } 
    else
    {
      throw new Exception("The $this->protocol protocol does not support quit.");
    }
  }

  /**
   * Returns the connections details. Namely, username, password, host and port.
   *
   * @return Associative array of the username, password, host and port.
   */
  public function getConnectionDetails() 
  {
    return array(
      "username" => $this->username,
      "password" => $this->password,
      "host" => $this->host,
      "port" => $this->port,
    );
  }

  /**
   * Creates an FtpOperations instance to be passed into this class
   *
   * @return FtpOperations instance to be passed into this class
   */
  public static function ftp() {
    return new FtpOperations(new Ftp(FALSE));
  }

  /**
   * Creates an HttpOperations instance to be passed into this class
   *
   * @return HttpOperations instance to be passed into this class
   */
  public static function http() {
    return new HttpOperations();
  }
}
?>
