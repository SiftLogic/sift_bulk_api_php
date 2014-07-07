<?php
require_once 'patched_pemftp/ftp_class.php';

/**
 * Handles all FTP based operations across the system. Unlike the Node.js scripts, this operates 
 * synchronously.
 */
class FtpOperations
{
  public $ftp;
  public $uploadFileName;

  /**
   * Just stores the ftp object this structure is needed due to testing issues.
   */
  public function __construct(Ftp $ftp) 
  {
    $this->ftp = $ftp;
  }

  /**
   * Initializes the ftp object and logs in. Then goes to passive mode.
   *
   * @param (username) The username to get into the ftp server.
   * @param (password) The password to get into the ftp server.
   * @param (host) The host to connect to.
   *
   * @return TRUE if operations could be initialized.
   */
  public function init($username, $password, $host, $port = 21)
  {
    $this->username = $username;
    $this->password = $password;
    $this->host = $host;
    $this->port = $port;
    if (empty($this->port)){
      $this->port = 21;
    }

    if ($this->ftp->SetServer($this->host, $this->port) === FALSE) {
        $this->ftp->quit();
        throw new RuntimeException("Could not set the server with $this->host:$this->port.\n");
    }

    if ($this->ftp->connect() === FALSE) {
      $this->ftp->quit();
      throw new RuntimeException("Cannot connect to $this->host:$this->port.\n");
    }

    if ($this->ftp->login($this->username, $this->password) === FALSE) {
      $this->ftp->quit();
      throw new RuntimeException(
        "Login failed with username:password $this->username:$this->password.\n"
      );
    }

    if ($this->ftp->SetType(FTP_AUTOASCII) === FALSE) {
      $this->ftp->quit();
      throw new RuntimeException("Could not set type to auto ASCII.\n");
    }

    if ($this->ftp->Passive(TRUE) === FALSE) {
      $this->ftp->quit();
      throw new RuntimeException("Could not change to passive mode.\n");
    }

    return TRUE;
  }

  /**
   * Changes to the upload directory then uploads the specified file.
   *
   * @param (file) The location of the file to upload.
   * @param (singleFile) If the file is uploaded in single file mode. Defaults to FALSE.
   *
   * @return An array of the format [<upload succeeded>, <message>].
   */
  public function upload($file, $singleFile = FALSE)
  {
    if (!file_exists($file))
    {
      return array(FALSE, "File Upload Error: " .trim($file). " does not exist\n");
    }

    $type = $singleFile ? 'default' : 'splitfile';
    $dir = "import_{$this->username}_{$type}_config";

    $formatted = explode('/', trim($file));
    $formatted = end($formatted);

    if($this->ftp->put($formatted, "$dir/$formatted")) {
      $response_message = $this->ftp->last_message();
      if (preg_match("/.* (.*)$/", $response_message, $parsed)) {
        $this->uploadFileName = trim($parsed[1]);

        return array(TRUE, "$formatted has been uploaded as {$parsed[1]}\n");
      } else {
        return array(FALSE, "Failed to extract filename from: $response_message\n");
      }
    } else {
      $message = $this->ftp->last_message();
      return array(FALSE, "\nFile Upload Error: $message\n");
    }
  }

 /**
   * Polls every pollEvery seconds until the last uploaded file can be downloaded. Then downloads.
   *
   * @param (location) The location to download the file to.
   * @param (pollEvery) Time in milleseconds to wait between each poll.
   * @param (removeAfter) If the results file should be removed after downloading.
   * @param (self) A new version of this class to use. Defaults to $this. (For testing purposes)
   *
   * @return An array [<download succeeded>, <message>].
   */
  public function download($location, $pollEvery, $removeAfter = FALSE, $self = '')
  {
    // So that waitAndDownload can be stubbed in the tests
    if(empty($self)){
      $self = $this;
    }

    $listing = $self->ftp->nlist('/complete');
    if ($listing === FALSE){
      return array(FALSE, "The /complete directory does not exist.\n");
    }

    $location = preg_replace('/\/$/', '', $location);// Remove trailing slash if present

    $formatted = $self->getDownloadFileName();
    if (array_search($formatted, $listing)){
      if($self->ftp->get("/complete/$formatted", "$location/$formatted") === FALSE){
        $message = $self->ftp->last_message();
        return array(FALSE, "\nFile Download Error: $message\n");
      };

      $message = "Downloaded into $location/$formatted.\n";
      if (!$removeAfter)
      {
        return array(TRUE, $message);
      }

      $result = $this->remove();
      $result[1] = $message .$result[1];

      return $result;
    } else {
      return $self->waitAndDownload($pollEvery, $formatted, $location, $removeAfter);
    }
  }

  /**
   * Wait the specified time then download the file. Useful for test stubbing.
   *
   * @param (time) The time in seconds to sleep for.
   * @param (file) The filename to download. Just need the filename, no path.
   * @param (location) The location to download the file to.
   * @param (removeAfter) If the results file should be removed after downloading.
   * @param (self) A new version of this class to use. Defaults to $this. (For testing purposes)
   *
   * @return Result of running downloads again.
   */
  public function waitAndDownload($time, $file, $location, $removeAfter = FALSE, $self = '')
  {
    // So that echoAndSleep can be stubbed in the tests
    if(empty($self)){
      $self = $this;
    }

    $self->echoAndSleep("Waiting for results file $file ...\n", $time);

    // We could have been kicked off due to inactivity...
    $self->ftp->quit();
    $details = $self->getConnectionDetails();

    if ($self->init($details['username'], $details['password'], $details['host'], $details['port'])
        === FALSE){
      return array(FALSE, "Could not reconnect to the server.\n");
    }

    return $self->download($location, $time, $removeAfter);
  }

  // (so echo and sleep can be stubbed)
  public function echoAndSleep($message, $time)
  {
    echo($message);
    sleep($time);
  }

  /**
   * Removes the results file from the server.
   *
   * @return An array [<download succeeded>, <message>].
   */
  public function remove()
  {
    $formatted = $this->getDownloadFileName();
    if($this->ftp->delete("/complete/$formatted") === FALSE){
      return array(FALSE, "Could not remove $formatted from the server.\n");
    }
    return array(TRUE, "");
  }

  /**
   * Closes the FTP connection properly. This should always be called at the end of a program using
   * this class.
   */
  public function quit()
  {
    $this->ftp->quit();
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
