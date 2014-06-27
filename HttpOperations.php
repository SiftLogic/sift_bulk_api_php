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
    return [TRUE, ''];
  }
}
?>
