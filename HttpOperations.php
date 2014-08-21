<?php
require_once 'vendor/autoload.php';

/**
 * Handles all HTTP based operations across the system.
 */
class HttpOperations
{
  public $baseUrl;
  public $statusUrl;
  public $statusUrls;
  public $downloadUrl;
  public $apikey;

  /**
   * Empty constructor until httpful tests are needed.
   */
  public function __construct() {}

  /**
   * Setups up the baseUrl and apikey.
   *
   * @param (password) The password to get into the ftp server.
   * @param (host) The host to connect to.
   * @param (port) The port to connect to. Defaults to 8080.
   *
   * @return TRUE if operations could be initialized.
   */
  public function init($password, $host, $port = 8080)
  {
    if (empty($port))
    {
      $port = 8080;
    }

    $this->baseUrl = "http://$host:$port/api/live/bulk/";
    $this->apikey = $password;
    $this->statusUrls = [];

    return TRUE;
  }

  /**
   * Uploads the specified file.
   *
   * @param (file) The location of the file to upload. Absolute path must be used.
   * @param (singleFile) If the file is uploaded in single file mode. Defaults to FALSE.
   * @param (notify) The full email address to notify once an upload completes. If  an empty value
   *                 is sent no address will be contacted.
   * @param (request) A mocked httpful post request. (For Testing)
   *
   * @return An array of the format [<upload succeeded>, <message>].
   */
  public function upload($file, $singleFile = FALSE, $notify = NULL, $request = NULL)
  {
    // For testing purposes
    if ($request === NULL)
    {
      $request = \Httpful\Request::post($this->baseUrl);
    }

    $successMessage = "$file was uploaded.\n";
    $exportType = ($singleFile) ? "single" : "multi";

    try
    {
      $response = $request->addHeader('Accept', 'application/json')
                          ->addHeader('x-authorization', $this->apikey)
                          ->sendTypes(\Httpful\Mime::FORM)
                          ->body([
                              "export_type" => $exportType,
                              "notify_email" => $notify    
                          ])
                          ->attach(['file' => ($file)])
                          ->send();
    } 
    catch(Exception $e)
    {
      return [FALSE, $e->getMessage()];
    }

    if (empty($response->body->status) === TRUE)
    {
      return [FALSE, $response->body];
    } 
    else if ($response->body->status === 'error')
    {
      return [FALSE, $response->body->msg];
    }

    if (empty($response->body->status_url) !== TRUE)
    {
      $this->statusUrls = [$response->body->status_url];
    } else {
      if (count($response->body->jobs) > 1)
      {
        $successMessage = $response->body->jobs[0]->msg ."\n". $successMessage;
      }

      for ($i = 0; $i < count($response->body->jobs); $i += 1) {
        $this->statusUrls[] = $response->body->jobs[$i]->status_url;
      }
    }

    return [TRUE, $successMessage];
  }

  /**
   * Polls every pollEvery seconds until the last uploaded file's results file(s) can be downloaded.
   * Any errors will cause the download process for all files to be halted.
   *
   * @param (location) The location to download the file(s) to.
   * @param (pollEvery) The time in seconds to sleep for.
   * @param (removeAfter) If the results file should be removed after downloading.
   * @param (self) A new version of this class to use. Defaults to $this. (For testing purposes)
   *
   * @return An array [<download succeeded>, <messages seperated by \n>].
   */
  public function download($location, $pollEvery, $removeAfter = FALSE, $self = '')
  {
    if (empty($self))
    {
      $self = $this;
    }

    $messages = [];

    for ($i = 0; $i < count($self->statusUrls); $i += 1) {
      $result = $self->downloadFile(
        $location,
        $pollEvery,
        $self->statusUrls[$i],
        $removeAfter,
        $self
      );
      if (!$result[0])
      {
        return [FALSE, $result[1]];
      }

      $messages[] = $result[1];
    }

    return [TRUE, "\n". implode("\n", $messages)];
  }

 /**
  * Polls every pollEvery seconds until the file at the status url can be downloaded.
  *
  * @param (location) The location to download the file(s) to.
  * @param (pollEvery) The time in seconds to sleep for.
  * @param (statusUrl) the location to poll for the file.
  * @param (removeAfter) If the results file should be removed after downloading.
  * @param (self) A new version of this class to use. Defaults to $this. (For testing purposes)
  *
  * @return An array [<download succeeded>, <message>].
  */
  public function downloadFile($location, $pollEvery, $statusUrl, $removeAfter = FALSE, $self = '')
  {
    // So that watchUpload and handleServerDownloadError can be stubbed in the tests
    if (empty($self))
    {
      $self = $this;
    }

    list($err, $message) = $self->watchUpload($pollEvery, $statusUrl);
    if (!$err)
    {
      return [$err, $message];
    }

    $tmp = explode("/", $statusUrl);
    $newFile = end($tmp);
    $fullLocation = "$location/$newFile.zip";

    $filePointer = $self->native('fopen', [$fullLocation, 'w+']);
    if ($filePointer !== false)
    {
      try
      {
        $ch = $self->native('curl_init', [$self->downloadUrl]);
        $self->native('curl_setopt', [$ch, CURLOPT_TIMEOUT, 100]);
        $self->native('curl_setopt', [$ch, CURLOPT_FILE, $filePointer]);
        $self->native('curl_setopt', [$ch, CURLOPT_HTTPHEADER, ["x-authorization: $self->apikey"]]);
        $self->native('curl_exec', [$ch]);

        $result = $self->handleServerDownloadError($ch, $fullLocation);
        $self->native('curl_close', [$ch]);
        $self->native('fclose', [$filePointer]);

        if (!$result[0])
        {
          return $result;
        }

        if ($removeAfter !== FALSE)
        {
          $result = $self->remove($statusUrl);
          if (empty($result[1]))
          {
            $result[1] = $message;
          }
          return $result;
        }
        return [TRUE, $message];

      } 
      catch(Exception $e)
      {
        return [FALSE, $e->getMessage()];
      }

      return $result;
    } 
    else
    {
      return [FALSE, "Could not open '$fullLocation' to download into."];
    }
  }

 /**
  * Checks if the downloaded file is actually an error message.
  *
  * @param (curlHandler) The curl handler to check header stuff, from curl_init().
  * @param (filename) The full file name to check for errors with.
  * @param (self) A new version of this class to use. Defaults to $this. (For testing purposes)
  *
  * @return An array [<no error>, <message>].
  */
  public function handleServerDownloadError($curlHandler, $filename, $self = '') 
  {
    // So that wrapped native calls can be stubbed in the tests
    if(empty($self))
    {
      $self = $this;
    }

    if ($self->native('curl_getinfo', [$curlHandler, CURLINFO_CONTENT_TYPE]) === "application/json")
    {
      $contents = $self->native('file_get_contents', [$filename]);
      $decoded = json_decode($contents);
      if ($decoded->status === "error")
      {
        return [FALSE, $decoded->msg];
      }
    }
    return [TRUE, ''];
  }

  /**
   * @description
   * Calls the callback once the last uploaded file (indicated by status url) has been loaded or 
   * there is an error.
   *
   * @param (pollEvery) The number of milleseconds to poll for.
   * @param (statusUrl) the location to poll for the file.
   * @param (request) A mocked httpful post request. (For Testing)
   * @param (self) A new version of this class to use. Defaults to $this. (For testing purposes)
   * @return An array [<watchUpload succeeded>, <message>].
   */
  public function watchUpload($pollEvery, $statusUrl, $request = NULL, $self = '') {
    if ($request === NULL)
    {
      $request = \Httpful\Request::get($statusUrl);
    }
    // So that waitAndDownload can be stubbed in the tests
    if (empty($self)){
      $self = $this;
    }

    try
    {
      $response = $request->addHeader('Accept', 'application/json')
                          ->addHeader('x-authorization', $this->apikey)
                          ->send();
    } 
    catch(Exception $e)
    {
      return [FALSE, $e->getMessage()];
    }

    if (empty($response->body->status) === TRUE)
    {
      return [FALSE, $response->body];
    } 
    else if ($response->body->status === 'error')
    {
      return [FALSE, $response->body->msg];
    } 
    else if ($response->body->status === 'completed')
    {
      $this->downloadUrl = $response->body->download_url;
      return [TRUE, $response->body->job ." downloaded."];
    }
    else
    {
      return $self->waitAndDownload($pollEvery, $response->body->job, $statusUrl);
    }
  }

  /**
   * Wait the specified time then download the file. Useful for test stubbing.
   *
   * @param (time) The time in seconds to sleep for.
   * @param (file) The filename to download. Just need the filename, no path.
   * @param (statusUrl) the location to poll for the file.
   * @param (self) A new version of this class to use. Defaults to $this. (For testing purposes)
   *
   * @return Result of running downloads again.
   */
  public function waitAndDownload($time, $file, $statusUrl, $self = '')
  {
    // So that echoAndSleep can be stubbed in the tests
    if(empty($self))
    {
      $self = $this;
    }

    $self->echoAndSleep("Waiting for results file $file ...\n", $time);

    return $self->watchUpload($time, $statusUrl);
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
   * @param (statusUrl) the location of the file to delete.
   * @param (request) A mocked httpful post request. (For Testing)
   *
   * @return An array [<download succeeded>, <message>].
   */
  public function remove($statusUrl, $request = NULL)
  {
    if ($request === NULL)
    {
      $request = \Httpful\Request::delete($statusUrl);
    }

    try
    {
      $response = $request->addHeader('Accept', 'application/json')
                          ->addHeader('x-authorization', $this->apikey)
                          ->send();
    }
    catch(Exception $e)
    {
      return [FALSE, $e->getMessage()];
    }

    if ($response->body->status === 'error')
    {
      return [FALSE, $response->body->msg];
    }
    return [TRUE, ''];
  }

  /**
   * Calls the specified native function with the passed in arguments. Makes testing natives easy.
   *
   * @param (name) The name of the function to call.
   * @param (args) An array of arguments to pass to the function.
   *
   * @return The results of the native call
   **/
  public function native($name, $args)
  {
    return call_user_func_array($name, $args);
  }
}
?>
