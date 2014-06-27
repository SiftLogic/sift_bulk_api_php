<?php
require_once 'vendor/autoload.php';
require_once 'HttpOperations.php';

/**
 * Phpunit overrides expects() for the class it mocks which is terrible. So I must create a custom
 * wrapper for testing purposes. Technique inspired by: http://stackoverflow.com/a/23254667
 */

// These will need to be manually reset for each test that uses them.
$postCalls = [];

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

  public function defineSendReturn($status, $message, $statusUrl)
  {
    $this->sendReturn = (object)array("body" => (object)array(
        "status" => $status,
        "msg" => $message,
        "status_url" => $statusUrl
      )
    );
  }

  public static function post($url, $payload = NULL, $mime = NULL)
  {
    array_push($GLOBALS['postCalls'], [$url]);

    return new MockRequest();
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

  public function testUploadCallSuccess()
  {
    $request = MockRequest::post($this->httpOperations->baseUrl);
    $this->httpOperations->apikey = '12345';
    $request->defineSendReturn('success', NULL, "http://localhost:80");

    $this->assertEquals($this->httpOperations->upload('test.csv', FALSE, $request), [TRUE, '']);

    $this->calledWith($GLOBALS['postCalls'], [[$this->httpOperations->baseUrl]]);
    $this->calledWith($request->addHeaderCalls, [
      ['Accept', 'application/json'],
      ['x-authorization', $this->httpOperations->apikey],
      ['Send-Types', 'application/x-www-form-urlencoded'],
    ]);
    $this->calledWith($request->bodyCalls, [['export_type' => "multi"]]);
    $this->calledWith($request->attachCalls, [['file' => 'test.csv']]);
    $this->assertEquals(count($request->sendCalls), 1);

    $this->assertEquals($this->httpOperations->statusUrl, "http://localhost:80");
  }

  public function testUploadCallSuccessSingleFile()
  {
    $request = MockRequest::post($this->httpOperations->baseUrl);
    $request->defineSendReturn('success', NULL, NULL);

    $this->assertEquals($this->httpOperations->upload('test.csv', TRUE, $request), [TRUE, '']);

    $this->calledWith($request->bodyCalls, [['export_type' => "single"]]);
  }

  public function testUploadCallError()
  {
    $request = MockRequest::post($this->httpOperations->baseUrl);
    $request->defineSendReturn('error', 'Err', NULL);

    $this->assertEquals($this->httpOperations->upload('test.csv', FALSE, $request), [FALSE, 'Err']);

    // No parsable response body
    $request = MockRequest::post($this->httpOperations->baseUrl);
    $request->sendReturn = (object)array('body' => '<h1>A funky error message</h1>');

    $this->assertEquals($this->httpOperations->upload('test.csv', FALSE, $request), 
                        [FALSE, '<h1>A funky error message</h1>']);
  }
}
?>
