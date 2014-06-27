<?php
require_once 'vendor/autoload.php';

/**
 * Handles all HTTP based operations across the system.
 */
class HttpOperations
{
  public $baseUrl;
  public $statusUrl;
  public $apikey;

  /**
   * Empty constructor until httpful tests are needed.
   */
  public function __construct() {}

  /**
   * Setups up the baseUrl and apikey
   *
   * @return TRUE if operations could be initialized.
   */
  public function init($password, $host, $port = 80)
  {
    if(empty($port))
    {
      $port = 80;
    }

    $this->baseUrl = "http://$host:$port/api/live/bulk/";
    $this->apikey = $password;

    return TRUE;
  }

  /**
   * Uploads the specified file.
   *
   * @param (file) The location of the file to upload. Absolute path must be used.
   * @param (singleFile) If the file is uploaded in single file mode. Defaults to FALSE.
   *
   * @return An array of the format [<upload succeeded>, <message>].
   */
  public function upload($file, $singleFile = FALSE, $request = NULL)
  {
    // For testing purposes
    if ($request === NULL)
    {
      $request = \Httpful\Request::post($this->baseUrl);
    }

    $exportType = ($singleFile) ? "single" : "multi";
    $response = $request->addHeader('Accept', 'application/json')
                        ->addHeader('x-authorization', $this->apikey)
                        ->sendTypes(\Httpful\Mime::FORM)
                        ->body(["export_type" => $exportType])
                        ->attach(['file' => ($file)])
                        ->send();

    if (empty($response->body->status) === TRUE)
    {
      return [FALSE, $response->body];
    } 
    else if ($response->body->status === 'error')
    {
      return [FALSE, $response->body->msg];
    }

    $this->statusUrl = $response->body->status_url;
    return [TRUE, "$file was uploaded.\n"];
  }

 /**
   * Polls every pollEvery seconds until the last uploaded file can be downloaded. Then downloads.
   *
   * @param (location) The location to download the file to.
   * @param (pollEvery) The time in seconds to sleep for.
   * @param (removeAfter) If the results file should be removed after downloading.
   * @param (self) A new version of this class to use. Defaults to $this. (For testing purposes)
   *
   * @return An array [<download succeeded>, <message>].
   */
  public function download($location, $pollEvery, $removeAfter = FALSE, $self = '')
  {
    return $this->watchUpload($pollEvery);

    // // So that waitAndDownload can be stubbed in the tests
    // if(empty($self)){
    //   $self = $this;
    // }

    // $listing = $self->ftp->nlist('/complete');
    // if ($listing === FALSE){
    //   return array(FALSE, "The /complete directory does not exist.\n");
    // }

    // $location = preg_replace('/\/$/', '', $location);// Remove trailing slash if present

    // $formatted = $self->getDownloadFileName();
    // if (array_search($formatted, $listing)){
    //   if($self->ftp->get("/complete/$formatted", "$location/$formatted") === FALSE){
    //     $message = $self->ftp->last_message();
    //     return array(FALSE, "\nFile Download Error: $message\n");
    //   };

    //   $message = "Downloaded into $location/$formatted.\n";
    //   if (!$removeAfter)
    //   {
    //     return array(TRUE, $message);
    //   }

    //   $result = $this->remove();
    //   $result[1] = $message .$result[1];

    //   return $result;
    // } else {
    //   return $self->waitAndDownload($pollEvery, $formatted, $location, $removeAfter);
    // }
  }

  /**
   * @description
   * Calls the callback once the last uploaded file (indicated by status url) has been loaded or 
   * there is an error.
   *
   * @param {integer} pollEvery The number of milleseconds to poll for.
   * @return An array [<watchUpload succeeded>, <message>].
   */
  public function watchUpload($pollEvery, $request = NULL, $self = '') {
    if ($request === NULL)
    {
      $request = \Httpful\Request::get($this->statusUrl);
    }
    // So that waitAndDownload can be stubbed in the tests
    if(empty($self)){
      $self = $this;
    }

    $response = $request->addHeader('Accept', 'application/json')
                        ->addHeader('x-authorization', $this->apikey)
                        ->send();

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
      return [TRUE, ''];
    }
    else
    {
      return $self->waitAndDownload($pollEvery, $response->body->job);
    }
  }

  /**
   * Wait the specified time then download the file. Useful for test stubbing.
   *
   * @param (time) The time in seconds to sleep for.
   * @param (file) The filename to download. Just need the filename, no path.
   * @param (self) A new version of this class to use. Defaults to $this. (For testing purposes)
   *
   * @return Result of running downloads again.
   */
  public function waitAndDownload($time, $file, $self = '')
  {
    // So that echoAndSleep can be stubbed in the tests
    if(empty($self)){
      $self = $this;
    }

    $self->echoAndSleep("Waiting for results file $file ...\n", $time);

    return $self->watchUpload($time);
  }

  // (so echo and sleep can be stubbed)
  public function echoAndSleep($message, $time)
  {
    echo($message);
    sleep($time);
  }
}
?>
