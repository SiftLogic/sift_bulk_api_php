<?php
require_once 'vendor/autoload.php';
require_once 'HttpOperations.php';

/**
 * Phpunit overrides expects() for the class it mocks which is terrible. So I must create a custom
 * wrapper for testing purposes. Technique inspired by: http://stackoverflow.com/a/23254667
 */

// These will need to be manually reset for each test that uses them.
$postCalls = [];
$getCalls = [];
$deleteCalls = [];

class MockRequest extends \Httpful\Request
{
  public $addHeaderCalls = [];
  public $bodyCalls = [];
  public $attachCalls = [];
  public $sendCalls = [];

  public $sendReturn;

  public function __construct() {}

  public function addHeader($name, $value) 
  {
    array_push($this->addHeaderCalls, [$name, $value]);
    return $this;
  }

  public function body($payload, $mimeType = NULL)
  {
    array_push($this->bodyCalls, $payload);
    return $this;
  }

  public function attach($payload)
  {
    array_push($this->attachCalls, $payload);
    return $this;
  }

  public function send() 
  {
    array_push($this->sendCalls, []);
    return $this->sendReturn;
  }

  public function defineSendReturn($status, $message, $statusUrl,
                                   $jobName = NULL, $download_url = NULL)
  {
    $this->sendReturn = (object)array("body" => (object)array(
        "status" => $status,
        "msg" => $message,
        "status_url" => $statusUrl,
        "job" => $jobName,
        "download_url" => $download_url
      )
    );
  }

  public static function get($url, $payload = NULL, $mime = NULL)
  {
    array_push($GLOBALS['getCalls'], [$url]);
    return new MockRequest();
  }

  public static function post($url, $payload = NULL, $mime = NULL)
  {
    array_push($GLOBALS['postCalls'], [$url]);
    return new MockRequest();
  }

  public static function delete($url, $payload = NULL, $mime = NULL)
  {
    array_push($GLOBALS['deleteCalls'], [$url]);
    return new MockRequest();
  }
}

class MockRequestSendError extends MockRequest
{
  public function send() 
  {
    array_push($this->sendCalls, []);
    throw new Exception('Send Error');
  }

  public static function get($url, $payload = NULL, $mime = NULL)
  {
    parent::get($url, $payload, $mime);
    return new MockRequestSendError();
  }

  public static function post($url, $payload = NULL, $mime = NULL)
  {
    parent::post($url, $payload, $mime);
    return new MockRequestSendError();
  }

  public static function delete($url, $payload = NULL, $mime = NULL)
  {
    parent::delete($url, $payload, $mime);
    return new MockRequestSendError();
  }
}

class HttpOperationsTest extends PHPUnit_Framework_TestCase
{
  private $httpOperations;
  private $baseUrl;

  private $password;

  protected function setUp() 
  {
    $GLOBALS['postCalls'] = [];
    $GLOBALS['getCalls'] = [];
    $GLOBALS['deleteCalls'] = [];

    $password = '54321';

    $this->httpOperations = new HttpOperations();

    $this->httpOperations->init($this->password, 'localhost', 81);
    $this->baseUrl = $this->httpOperations->baseUrl;
  }

  // To be used with MockRequest
  private function calledWith($args, $values)
  {
    $this->assertEquals(count($args), count($values));

    for ($index = 0; $index < count($values); $index += 1) {
      $this->assertEquals($args[$index], $values[$index]);
    }
  }

  private function stubX($toStub)
  {
    $operationsStub = $this->getMock(
      'HttpOperations', 
      $toStub,
      [$this->password, 'localhost', 81]
    );

    return $operationsStub;
  }

  // init

  public function testInitSetBaseUrlAndApikey() 
  {
    $this->assertEquals($this->httpOperations->baseUrl, "http://localhost:81/api/live/bulk/");
    $this->assertEquals($this->httpOperations->apikey, $this->password);
  }

  public function testInitSetBaseUrlAndApikeyDefaultPort() 
  {
    $this->assertEquals($this->httpOperations->init($this->password, 'localhost'), TRUE);

    $this->assertEquals($this->httpOperations->baseUrl, "http://localhost:80/api/live/bulk/");
    $this->assertEquals($this->httpOperations->apikey, $this->password);
  }

  // upload

  public function testUploadSuccess()
  {
    $request = MockRequest::post($this->httpOperations->baseUrl);
    $this->httpOperations->apikey = '12345';
    $request->defineSendReturn('success', NULL, "http://localhost:80");

    $msg = "test.csv was uploaded.\n";
    $this->assertEquals($this->httpOperations->upload('test.csv', FALSE, NULL, $request), [TRUE, $msg]);

    $this->calledWith($GLOBALS['postCalls'], [[$this->httpOperations->baseUrl]]);
    $this->calledWith($request->addHeaderCalls, [
      ['Accept', 'application/json'],
      ['x-authorization', $this->httpOperations->apikey],
      ['Send-Types', 'application/x-www-form-urlencoded'],
    ]);
    $this->calledWith($request->bodyCalls, [['export_type' => 'multi', 'notify_email' => null]]);
    $this->calledWith($request->attachCalls, [['file' => 'test.csv']]);
    $this->assertEquals(count($request->sendCalls), 1);

    $this->assertEquals($this->httpOperations->statusUrl, "http://localhost:80");
  }

  public function testUploadSuccessSingleFile()
  {
    $request = MockRequest::post($this->httpOperations->baseUrl);
    $request->defineSendReturn('success', NULL, NULL);

    $msg = "test.csv was uploaded.\n";
    $this->assertEquals($this->httpOperations->upload('test.csv', TRUE,NULL,$request),[TRUE, $msg]);

    $this->calledWith($request->bodyCalls, [['export_type' => 'single', 'notify_email' => null]]);
  }

  public function testUploadSuccessNotifyEmail()
  {
    $request = MockRequest::post($this->httpOperations->baseUrl);
    $request->defineSendReturn('success', NULL, NULL);

    $msg = "test.csv was uploaded.\n";
    $this->assertEquals($this->httpOperations->upload('test.csv',False,NULL,$request),[TRUE, $msg]);

    $this->calledWith($request->bodyCalls, [['export_type' => 'multi', 'notify_email' => null]]);
  }

  public function testUploadServerError()
  {
    $request = MockRequest::post($this->httpOperations->baseUrl);
    $request->defineSendReturn('error', 'Err', NULL);

    $this->assertEquals($this->httpOperations->upload('test.csv', FALSE, NULL, $request),
                                                      [FALSE, 'Err']);

    // No parsable response body
    $request->sendReturn = (object)array('body' => '<h1>A funky error message</h1>');

    $this->assertEquals($this->httpOperations->upload('test.csv', FALSE, NULL, $request), 
                        [FALSE, '<h1>A funky error message</h1>']);
  }

  public function testUploadHttpError()
  {
    $request = MockRequestSendError::post($this->httpOperations->baseUrl);
    $request->defineSendReturn('error', 'Err', NULL);

    $this->assertEquals($this->httpOperations->upload('test.csv', FALSE, NULL, $request), 
                        [FALSE, 'Send Error']);
  }

  // handleServerDownloadError

  public function testHandleServerDownloadErrorNoErrorApplicationType()
  {
    $operationsStub = $this->stubX(['native']);
    $operationsStub->expects($this->once())
                   ->method('native')
                   ->with('curl_getinfo', ['handler', CURLINFO_CONTENT_TYPE])
                   ->will($this->returnValue('application/xml'));

    $this->assertEquals($this->httpOperations->handleServerDownloadError(
      'handler',
      'test.csv',
      $operationsStub
    ), [TRUE, '']);
  }

  public function testHandleServerDownloadErrorNoErrorBody()
  {
    $operationsStub = $this->stubX(['native']);
    $operationsStub->expects($this->at(0))
                   ->method('native')
                   ->with('curl_getinfo', ['handler', CURLINFO_CONTENT_TYPE])
                   ->will($this->returnValue('application/json'));

    $operationsStub->expects($this->at(1))
                   ->method('native')
                   ->with('file_get_contents', ['test.csv'])
                   ->will($this->returnValue('{"status": "success"}'));

    $this->assertEquals($this->httpOperations->handleServerDownloadError(
      'handler',
      'test.csv',
      $operationsStub
    ), [TRUE, '']);
  }

  public function testHandleServerDownloadError()
  {
    $operationsStub = $this->stubX(['native']);
    $operationsStub->expects($this->at(0))
                   ->method('native')
                   ->with('curl_getinfo', ['handler', CURLINFO_CONTENT_TYPE])
                   ->will($this->returnValue('application/json'));

    $operationsStub->expects($this->at(1))
                   ->method('native')
                   ->with('file_get_contents', ['test.csv'])
                   ->will($this->returnValue('{"status": "error", "msg": "An Error"}'));

    $this->assertEquals($this->httpOperations->handleServerDownloadError(
      'handler',
      'test.csv',
      $operationsStub
    ), [FALSE, 'An Error']);
  }

  // watchUpload

  public function testWatchUploadComplete()
  {
    $request = MockRequest::get($this->httpOperations->statusUrl);
    $this->httpOperations->apikey = '12345';
    $request->defineSendReturn('completed', NULL, NULL, NULL, 'download/location');

    $this->assertEquals($this->httpOperations->watchUpload(300, $request), [TRUE, '']);

    $this->calledWith($GLOBALS['getCalls'], [[$this->httpOperations->statusUrl]]);
    $this->calledWith($request->addHeaderCalls, [
      ['Accept', 'application/json'],
      ['x-authorization', $this->httpOperations->apikey]
    ]);
    $this->assertEquals(count($request->sendCalls), 1);
    $this->assertEquals($this->httpOperations->downloadUrl, 'download/location');
  }

  public function testWatchUploadNotComplete()
  {
    $request = MockRequest::get($this->httpOperations->statusUrl);
    $request->defineSendReturn('active', NULL, NULL, 'a_job');

    $operationsStub = $this->getMock(
      'HttpOperations', 
      ['waitAndDownload'],
      [$this->password, 'localhost', 81]
    );
    $operationsStub->expects($this->once())
                   ->method('waitAndDownload')
                   ->with(100, 'a_job')
                   ->will($this->returnValue([TRUE, 'test message2']));

    $this->assertEquals(
      $this->httpOperations->watchUpload(100, $request, $operationsStub),
      [TRUE, 'test message2']
    );
  }

  public function testWatchUploadServerError()
  {
    $request = MockRequest::get($this->httpOperations->statusUrl);
    $request->defineSendReturn('error', 'Err', NULL);

    $this->assertEquals($this->httpOperations->watchUpload(100, $request), [FALSE, 'Err']);

    //No parsable response body
    $request->sendReturn = (object)array('body' => '<h1>A funky error message</h1>');

    $this->assertEquals(
      $this->httpOperations->watchUpload(100, $request),
      [FALSE, '<h1>A funky error message</h1>']
    );
  }

  public function testWatchUploadHttpError()
  {
    $request = MockRequestSendError::get($this->httpOperations->statusUrl);
    $request->defineSendReturn('error', 'Err', NULL);

    $this->assertEquals($this->httpOperations->watchUpload(100, $request), [FALSE, 'Send Error']);
  }

  // waitAndDownload

  private function setupWaitAndDownload($mockWatchUpload = FALSE)
  {
    $operationsStub = $this->getMock(
      'HttpOperations', 
      ['echoAndSleep', 'watchUpload'],
      [$this->password, 'localhost', 81]
    );
    $operationsStub->expects($this->once())
                   ->method('echoAndSleep');

    if ($mockWatchUpload){
      $operationsStub->expects($this->once())
                     ->method('watchUpload');
    }

    return $operationsStub;
  }

  public function testWaitAndDownloadPrintsAndSleeps()
  {
    $stub = $this->setupWaitAndDownload(TRUE);
    $stub->expects($this->once())
         ->method('echoAndSleep')
         ->with("Waiting for results file test.csv ...\n", 1000);

    $this->httpOperations->waitAndDownload(1000, 'test.csv', $stub);
  }

  public function testWaitAndDownloadReturnsDownload()
  {
    $stub = $this->setupWaitAndDownload(FALSE);
    $stub->expects($this->once())
         ->method('watchUpload')
         ->with(1000)
         ->will($this->returnValue([TRUE, 'test message']));

    $result = $this->httpOperations->waitAndDownload(1000, 'test.csv', $stub);
    $this->assertEquals($result, [TRUE, 'test message']);
  }

  // download

  public function testDownloadWatchUploadError()
  {
    $operationsStub = $this->stubX(['watchUpload']);
    $operationsStub->expects($this->once())
                   ->method('watchUpload')
                   ->with(100)
                   ->will($this->returnValue([FALSE, 'An Error']));

    $this->assertEquals($this->httpOperations->download('/tmp', 100, FALSE, $operationsStub),
                        [FALSE, 'An Error']);
  }

  public function testDownloadFileCreationError()
  {
    $operationsStub = $this->stubX(['watchUpload', 'native']);
    $operationsStub->expects($this->once())
                   ->method('watchUpload')
                   ->with(100)
                   ->will($this->returnValue([TRUE, '']));

    $operationsStub->statusUrl = 'http://localhost:80/test_csv';

    $operationsStub->expects($this->once())
                   ->method('native')
                   ->with('fopen', ['/tmp/test_csv.zip', 'w+'])
                   ->will($this->returnValue(false));

    // $this->httpOperations->download('/tmp', 100, FALSE, $operationsStub);
    $this->assertEquals($this->httpOperations->download('/tmp', 100, FALSE, $operationsStub),
                        [FALSE, "Could not open '/tmp/test_csv.zip' to download into."]);
  }

  private function downloadFileNormalSetup()
  {
    $chHandler = 'ch_handler';
    $filePointer = 'file_pointer';

    $operationsStub = $this->stubX(['watchUpload', 'native', 'handleServerDownloadError','remove']);
    $operationsStub->expects($this->once())
                   ->method('watchUpload')
                   ->with(100)
                   ->will($this->returnValue([TRUE, '']));

    $operationsStub->statusUrl = 'http://localhost:80/test_csv';
    $operationsStub->downloadUrl = 'http://localhost:80/test_csv/download';
    $operationsStub->apikey = '12345';

    // Note that at increments for every mocked function call, it is not call specific
    $operationsStub->expects($this->at(1))
                   ->method('native')
                   ->with('fopen', ['/tmp/test_csv.zip', 'w+'])
                   ->will($this->returnValue($filePointer));
    $operationsStub->expects($this->at(2))
                   ->method('native')
                   ->with('curl_init', ['http://localhost:80/test_csv/download'])
                   ->will($this->returnValue($chHandler));
    $operationsStub->expects($this->at(3))
                   ->method('native')
                   ->with('curl_setopt', [$chHandler, CURLOPT_TIMEOUT, 100]);
    $operationsStub->expects($this->at(4))
                   ->method('native')
                   ->with('curl_setopt', [$chHandler, CURLOPT_FILE, $filePointer]);
    $operationsStub->expects($this->at(5))
                   ->method('native')
                   ->with('curl_setopt',[$chHandler,CURLOPT_HTTPHEADER,["x-authorization: 12345"]]);
    $operationsStub->expects($this->at(6))
                   ->method('native')
                   ->with('curl_exec', [$chHandler]);

    $operationsStub->expects($this->at(8))
                   ->method('native')
                   ->with('curl_close', [$chHandler]);
    $operationsStub->expects($this->at(9))
                   ->method('native')
                   ->with('fclose', [$filePointer]);

    return [$operationsStub, $chHandler];
  }

  public function testDownloadFileNormalCall()
  {
    list($operationsStub, $chHandler) = $this->downloadFileNormalSetup();
    $operationsStub->expects($this->once())
                   ->method('handleServerDownloadError')
                   ->with($chHandler, '/tmp/test_csv.zip')
                   ->will($this->returnValue([FALSE, 'An Error']));

    $this->assertEquals($this->httpOperations->download('/tmp', 100, FALSE, $operationsStub),
                        [FALSE, 'An Error']);
  }

  public function testDownloadFileNormalCallRemoveAfter()
  {
    list($operationsStub, $chHandler) = $this->downloadFileNormalSetup();

    $operationsStub->expects($this->once())
                   ->method('handleServerDownloadError')
                   ->with($chHandler, '/tmp/test_csv.zip')
                   ->will($this->returnValue([TRUE, '']));
    $operationsStub->expects($this->once())
                   ->method('remove')
                   ->will($this->returnValue([FALSE, 'A Message']));

    $this->assertEquals($this->httpOperations->download('/tmp', 100, TRUE, $operationsStub),
                        [FALSE, 'A Message']);
  }

  // remove

  public function testRemoveServerError()
  {
    $request = MockRequest::delete('http://localhost:80/');
    $request->defineSendReturn('error', 'Err', NULL);

    $this->assertEquals($this->httpOperations->remove($request), [FALSE, 'Err']);
  }

  public function testRemoveSuccess()
  {
    $request = MockRequest::delete('http://localhost:80/');
    $request->defineSendReturn('success', NULL, NULL);

    $this->assertEquals($this->httpOperations->remove($request), [TRUE, '']);

    $this->calledWith($GLOBALS['deleteCalls'], [['http://localhost:80/']]);
    $this->calledWith($request->addHeaderCalls, [
      ['Accept', 'application/json'],
      ['x-authorization', $this->httpOperations->apikey]
    ]);
  }

  public function testRemoveHttpError()
  {
    $request = MockRequestSendError::delete('http://localhost:80/');
    $request->defineSendReturn('error', 'Err', NULL);

    $this->assertEquals($this->httpOperations->remove($request), [FALSE, 'Send Error']);
  }
}
?>
