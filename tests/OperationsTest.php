<?php
require_once 'Operations.php';
require_once 'FtpOperations.php';
require_once 'patched_pemftp/ftp_class.php';
require_once 'vendor/autoload.php';

class OperationsTest extends PHPUnit_Framework_TestCase
{
  private $username;
  private $password;
  private $host;
  private $port;
  private $ftpOperations;

  private $notify;
  private $root;
  private $file;

  protected function setUp() 
  {
    $this->username = 'TestKey';
    $this->password = 'e261742d-fe2f-4569-95e6-312689d04903';
    $this->host = 'localhost';
    $this->port = 9871;
    $this->polling = 0.1;
    $this->ftpOperations = Operations::ftp();
    $this->notify = 'test@test.com';

    $this->file = 'test.csv';

    $this->operations = new Operations($this->ftpOperations, $this->username, $this->password,
                                       $this->port, $this->host,$this->polling,'ftp',$this->notify);

    $dir = array();
    $dir[$this->file] = '';

    $this->root = org\bovigo\vfs\vfsStream::setup('root', null, $dir);
  }

  private function stubObjectWithOnce($name, $methods)
  {
    $stub = $this->getMock($name);

    foreach($methods as $key => $value){
      $stub->expects($this->once())
           ->method($key)
           ->will($this->returnValue($value));
    }

    return $stub;
  }

  private function stubFtpOperationsCall()
  {
    return $this->getMockBuilder('FtpOperations')
                ->disableOriginalConstructor()
                ->getMock();
  }

  private function stubHttpOperationsCall()
  {
    return $this->getMockBuilder('HttpOperations')
                ->disableOriginalConstructor()
                ->getMock();
  }

  public function testCorrectVariablesSetOnConstruction() 
  {
    $details = $this->operations->getConnectionDetails();

    $this->assertEquals($details['username'], $this->username);
    $this->assertEquals($details['password'], $this->password);
    $this->assertEquals($details['host'], $this->host);
    $this->assertEquals($details['port'], $this->port);

    $this->assertEquals($this->operations->notify, $this->notify);
    $this->assertEquals($this->operations->protocol, 'ftp');
    $this->assertEquals($this->operations->ftpOperations, $this->ftpOperations);
    $this->assertEquals($this->operations->pollEvery, $this->polling);
  }

  public function testVariablesSetNonDefaultsOnConstruction() {
    $operations = new Operations($this->ftpOperations, $this->username,$this->password,$this->port);
    $details = $operations->getConnectionDetails();

    $this->assertEquals($details['host'], 'localhost');
    $this->assertEquals($operations->notify, null);
    $this->assertEquals($operations->protocol, 'http');
    $this->assertEquals($operations->pollEvery, 300);

    $operations = new Operations($this->ftpOperations,$this->username, $this->password, $this->port,
                                 '', '', '');
    $details = $operations->getConnectionDetails();

    $this->assertEquals($operations->notify, null);
    $this->assertEquals($operations->protocol, 'http');
    $this->assertEquals($details['host'], 'localhost');
    $this->assertEquals($details['port'], 9871);
    $this->assertEquals($operations->pollEvery, 300);
  }

  // init

  public function testInitCallsFtp() {
    $this->ftpOperations = $this->stubFtpOperationsCall();
    $this->ftpOperations->expects($this->once())
         ->method('init')
         ->will($this->returnValue(TRUE))
         ->with($this->username, $this->password, $this->host, $this->port);

    $this->operations = new Operations($this->ftpOperations, $this->username, $this->password,
                                       $this->port, $this->host, 300, 'ftp');

    $this->assertEquals($this->operations->init(), TRUE);
  }

  public function testInitCallsHttp() {
    $this->httpOperations = $this->stubHttpOperationsCall();
    $this->httpOperations->expects($this->once())
         ->method('init')
         ->will($this->returnValue(TRUE))
         ->with($this->password, $this->host, $this->port);

    $this->operations = new Operations($this->httpOperations, $this->username, $this->password,
                                       $this->port, $this->host, 300, 'http');

    $this->assertEquals($this->operations->init(), TRUE);
  }

  // upload

  public function testUploadCallsFtp() {
    $this->ftpOperations = $this->stubFtpOperationsCall();
    $this->ftpOperations->expects($this->once())
         ->method('upload')
         ->will($this->returnValue(TRUE))
         ->with('test.csv', FALSE);

    $this->operations = new Operations($this->ftpOperations, $this->username, $this->password,
                                       $this->port, $this->host);

    $this->assertEquals($this->operations->upload('test.csv', FALSE), TRUE);
  }

  public function testUploadCallsHttp() {
    $this->httpOperations = $this->stubHttpOperationsCall();
    $this->httpOperations->expects($this->once())
         ->method('upload')
         ->will($this->returnValue(TRUE))
         ->with('test.csv', FALSE);

    $this->operations = new Operations($this->httpOperations, $this->username, $this->password,
                                       $this->port, $this->host);

    $this->assertEquals($this->operations->upload('test.csv', FALSE, $this->notify), TRUE);
  }

  // download

  public function testDownloadCallsFtp() {
    $this->ftpOperations = $this->stubFtpOperationsCall();
    $this->ftpOperations->expects($this->once())
         ->method('download')
         ->will($this->returnValue(TRUE))
         ->with('/tmp', 300, FALSE);

    $this->operations = new Operations($this->ftpOperations, $this->username, $this->password,
                                       $this->port, $this->host, 300, 'ftp');

    $this->assertEquals($this->operations->download('/tmp', FALSE), TRUE);
  }

    public function testDownloadCallsHttp() {
    $this->httpOperations = $this->stubHttpOperationsCall();
    $this->httpOperations->expects($this->once())
         ->method('download')
         ->will($this->returnValue(TRUE))
         ->with('/tmp', 300, FALSE);

    $this->operations = new Operations($this->httpOperations, $this->username, $this->password,
                                       $this->port, $this->host, 300);

    $this->assertEquals($this->operations->download('/tmp', FALSE), TRUE);
  }

  // remove

  public function testRemoveCallsFtp() {
    $this->ftpOperations = $this->stubFtpOperationsCall();
    $this->ftpOperations->expects($this->once())
         ->method('remove')
         ->will($this->returnValue(TRUE));

    $this->operations = new Operations($this->ftpOperations, $this->username, $this->password,
                                       $this->port, $this->host, 300, 'ftp');

    $this->assertEquals($this->operations->remove(), TRUE);
  }

  public function testRemoveCallsHttp() {
    $this->httpOperations = $this->stubHttpOperationsCall();
    $this->httpOperations->expects($this->once())
         ->method('remove')
         ->with(NULL)
         ->will($this->returnValue(TRUE));

    $this->operations = new Operations($this->httpOperations, $this->username, $this->password,
                                       $this->port, $this->host, 300, 'http');

    $this->assertEquals($this->operations->remove(), TRUE);
  }

  // quit

  public function testQuitCallsFtp() {
    $this->ftpOperations = $this->stubFtpOperationsCall();
    $this->ftpOperations->expects($this->once())
         ->method('quit')
         ->will($this->returnValue(TRUE));

    $this->operations = new Operations($this->ftpOperations, $this->username, $this->password,
                                       $this->port, $this->host, 300, 'ftp');

    $this->operations->quit();
  }

  public function testQuitCallsHttp() {
    $this->operations = new Operations(Operations::http(), $this->username, $this->password,
                                       $this->port, $this->host, 300, 'http');

    try {
      $this->operations->quit();
      $this->fail('Calling quit with http should be raising an exception');
    }
    catch (Exception $e)
    {
      $this->assertEquals($e->getMessage(), "The http protocol does not support quit.");
    }
  }
}
?>
