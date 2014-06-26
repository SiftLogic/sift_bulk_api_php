<?php
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
  
  public $protocol;
  public $uploadFileName;// Set on upload
  public $pollEvery;
  public $ftpOperations;

  /**
   * The constructor adds properties to the object which are used in init.
   *
   * @param (ftpOperations) An instance of FtpOperations to use.
   * @param (username) The username to get into the ftp server.
   * @param (password) The password to get into the ftp server.
   * @param (host) The host to connect to. Defaults to localhost.
   * @param (port) The port to connect to. Defaults to 21.
   * @param (polling) Number of seconds to poll for. Defaults to 300 (5 minutes) if falsey.
   * @param (protocol) What protocol to use to transfer data. Defaults to http.
   */
  public function __construct(FtpOperations $ftpOperations, $username, $password, $port,
                              $host = 'localhost', $pollEvery = 300, $protocol = 'http') 
  {
    $this->username = $username;
    $this->password = $password;
    $this->host = $host;
    $this->port = $port;
    $this->pollEvery = $pollEvery;
    $this->protocol = $protocol;

    if (empty($this->host)){
      $this->host = 'localhost';
    }
    if (empty($this->pollEvery)){
      $this->pollEvery = 300;
    }
    if (empty($this->protocol)){
      $this->protocol = 'http';
    }

    $this->ftpOperations = $ftpOperations;
  }

  /**
   * Initializes the connection with the connection options (username, key, host port).
   *
   * @return TRUE if operations could be initialized.
   */
  public function init()
  {
    return $this->ftpOperations->init($this->username, $this->password, $this->host, $this->port);
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
    return $this->ftpOperations->upload($file, $singleFile);
  }

  /**
   * Changes to the upload directory then uploads the specified file.
   *
   * @param (file) The location of the file to upload.
   * @param (singleFile) If the file is uploaded in single file mode.
   *
   * @return An array of the format [<upload succeeded>, <message>].
   */

  /**
   * @description
   * Downloads the last uploaded file (self.uploadFileName).
   *
   * @param (location) The location to download the file to.
   * @param (removeAfter) If the results file should be removed after downloading.
   * @return An array [<download succeeded>, <message>].
   */
  public function download($location, $removeAfter)
  {
    return $this->ftpOperations->download($location, $this->pollEvery, $removeAfter);
  }
  
  /**
   * Removes the results file from the server.
   *
   * @return An array [<download succeeded>, <message>].
   */
  public function remove()
  {
    return $this->ftpOperations->remove();
  }


  /**
   * Closes the FTP connection properly. This should always be called at the end of a program using
   * this class.
   */
  public function quit()
  {
    $this->ftpOperations->quit();
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
   * Retrieves the upload file name and transforms it to the download one. 
   *
   * @return The current download name of the current upload.
   */
  public function getDownloadFileName()
  {
    if (empty($this->uploadFileName)){
      return $this->uploadFileName;
    }

    $formatted = preg_replace('/source_/', 'archive_', $this->uploadFileName, 1);

    if (strpos($formatted, '.csv') || strpos($formatted, '.txt')){
      $formatted = substr($formatted, 0, -4) .'.zip';
    }

    return $formatted;
  }
}
?>
