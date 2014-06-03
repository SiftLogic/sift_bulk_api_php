<?php
require_once 'Operations.php';
require_once 'patched_pemftp/ftp_class.php';
require_once 'vendor/autoload.php';

class OperationsTest extends PHPUnit_Framework_TestCase
{
  private $username;
  private $password;
  private $host;
  private $port;
  private $ftp;

  private $root;
  private $file;

  protected function setUp() 
  {
    $this->username = 'TestKey';
    $this->password = 'e261742d-fe2f-4569-95e6-312689d04903';
    $this->host = 'localhost';
    $this->port = 9871;
    $this->polling = 0.1;
    $this->ftp = new Ftp(FALSE);

    $this->file = 'test.csv';

    $this->operations = new Operations($this->ftp, $this->username, $this->password, $this->host, 
                                       $this->port, $this->polling);

    $dir = array();
    $dir[$this->file] = '';

    $this->root = org\bovigo\vfs\vfsStream::setup('root', null, $dir);
    // $this->root = org\bovigo\vfs\vfsStream::create(array(
    //   '/' => $dir
    // ));
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

  public function testCorrectVariablesSetOnConstruction() 
  {
    $details = $this->operations->getConnectionDetails();

    $this->assertEquals($details['username'], $this->username);
    $this->assertEquals($details['password'], $this->password);
    $this->assertEquals($details['host'], $this->host);
    $this->assertEquals($details['port'], $this->port);

    $this->assertEquals($this->operations->ftp, $this->ftp);
    $this->assertEquals($this->operations->pollEvery, $this->polling);
  }

  public function testVariablesSetNonDefaultsOnConstruction() {
    $operations = new Operations($this->ftp, $this->username, $this->password);
    $details = $operations->getConnectionDetails();

    $this->assertEquals($details['host'], 'localhost');
    $this->assertEquals($details['port'], 21);
    $this->assertEquals($operations->pollEvery, 300);

    $operations = new Operations($this->ftp, $this->username, $this->password, '', '', '');
    $details = $operations->getConnectionDetails();

    $this->assertEquals($details['host'], 'localhost');
    $this->assertEquals($details['port'], 21);
    $this->assertEquals($operations->pollEvery, 300);
  }

  // init

  public function testInitSetServerError() {
    $this->setExpectedException('RuntimeException');

    $this->operations = new Operations($this->stubObjectWithOnce('Ftp', array(
      "SetServer" => FALSE,
      "quit" => TRUE
    )), $this->username, $this->password, $this->host, $this->port);

    $this->operations->init();
  }

  public function testInitConnectError() {
    $this->setExpectedException('RuntimeException');

    $this->operations = new Operations($this->stubObjectWithOnce('Ftp', array(
      "SetServer" => TRUE,
      "quit" => TRUE,
      "connect" => FALSE
    )), $this->username, $this->password, $this->host, $this->port);

    $this->operations->init();
  }

  public function testInitLoginError() {
    $this->setExpectedException('RuntimeException');

    $this->operations = new Operations($this->stubObjectWithOnce('Ftp', array(
      "SetServer" => TRUE,
      "quit" => TRUE,
      "connect" => TRUE,
      "login" => FALSE
    )), $this->username, $this->password, $this->host, $this->port);

    $this->operations->init();
  }

  public function testInitSetTypeError() {
    $this->setExpectedException('RuntimeException');

    $this->operations = new Operations($this->stubObjectWithOnce('Ftp', array(
      "SetServer" => TRUE,
      "quit" => TRUE,
      "connect" => TRUE,
      "login" => TRUE,
      "SetType" => FALSE
    )), $this->username, $this->password, $this->host, $this->port);

    $this->operations->init();
  }

  public function testInitPassiveError() {
    $this->setExpectedException('RuntimeException');

    $this->operations = new Operations($this->stubObjectWithOnce('Ftp', array(
      "SetServer" => TRUE,
      "quit" => TRUE,
      "connect" => TRUE,
      "login" => TRUE,
      "SetType" => TRUE,
      "Passive" => FALSE
    )), $this->username, $this->password, $this->host, $this->port);

    $this->operations->init();
  }

  public function testInitSuccess() {
    $stub = $this->stubObjectWithOnce('Ftp', array(
      "SetServer" => TRUE,
      "connect" => TRUE,
      "login" => TRUE,
      "SetType" => TRUE,
      "Passive" => TRUE
    ));

    $stub->expects($this->once())
         ->method('SetServer')
         ->with($this->host, $this->port);
    $stub->expects($this->once())
         ->method('login')
         ->with($this->username, $this->password);
    $stub->expects($this->once())
         ->method('SetType')
         ->with(FTP_AUTOASCII);
    $stub->expects($this->once())
         ->method('Passive')
         ->with(TRUE);

    $this->operations = new Operations($stub, $this->username, $this->password, $this->host, 
                                       $this->port);

    $this->assertEquals($this->operations->init(), TRUE);
  }

  // upload

  public function testUploadNoFileError() 
  {
    $message = "File Upload Error: other.csv does not exist\n";
    $this->assertEquals($this->operations->upload('other.csv'), array(FALSE, $message));
  }

  public function testUploadFileUploadErrorWithMultiFile() 
  {
    $dir = "import_{$this->username}_splitfile_config";

    $stub = $this->stubObjectWithOnce('Ftp', array(
      "put" => FALSE,
      "last_message" => 'An Error'
    ));

    $stub->expects($this->once())
         ->method('put')
         ->with($this->file, "$dir/$this->file");
    $this->operations = new Operations($stub, $this->username, $this->password);

    $message = "\nFile Upload Error: An Error\n";
    $this->assertEquals($this->operations->upload($this->file), array(FALSE, $message));
  }

  public function testUploadFileUploadErrorWithSingleFile() 
  {
    $dir = "import_{$this->username}_default_config";

    $stub = $this->stubObjectWithOnce('Ftp', array(
      "put" => FALSE,
      "last_message" => 'An Error'
    ));

    $stub->expects($this->once())
         ->method('put')
         ->with($this->file, "$dir/$this->file");
    $this->operations = new Operations($stub, $this->username, $this->password);

    $message = "\nFile Upload Error: An Error\n";
    $this->assertEquals($this->operations->upload($this->file, TRUE), array(FALSE, $message));
  }

  public function testUploadFileNameExtractionError() 
  {
    $stub = $this->stubObjectWithOnce('Ftp', array(
      "put" => TRUE,
      "last_message" => 'source_test_data_20140523_0012.csv'
    ));
    $this->operations = new Operations($stub, $this->username, $this->password);

    $message = "Failed to extract filename from: source_test_data_20140523_0012.csv\n";
    $this->assertEquals($this->operations->upload($this->file), array(FALSE, $message));
  }

  public function testUploadSuccess() 
  {
    $file = '/tmp/test.csv';

    $lastMessage = array(
      '226 closing data connection;',
      'File upload success;',
      'source_test_data_20140523_0015.csv'
    );

    $stub = $this->stubObjectWithOnce('Ftp', array(
      "put" => TRUE,
      "last_message" => implode(' ', $lastMessage)
    ));
    $this->operations = new Operations($stub, $this->username, $this->password);

    $message = "test.csv has been uploaded as {$lastMessage[2]}\n";
    $this->assertEquals($this->operations->upload('test.csv'), array(TRUE, $message));
    $this->assertEquals($this->operations->uploadFileName, $lastMessage[2]);
  }

  // getDownloadFileName

  public function testGetDownloadFileNameNoModify()
  {
    $this->operations->uploadFileName = '';
    $this->assertEquals($this->operations->getDownloadFileName(), '');

    $this->operations->uploadFileName = 'test_test.doc';
    $this->assertEquals($this->operations->getDownloadFileName(), 'test_test.doc');
  }

  public function testToDownloadFormatModify()
  {
    $this->operations->uploadFileName = 'source_test.doc';
    $this->assertEquals($this->operations->getDownloadFileName(), 'archive_test.doc');
    
    $this->operations->uploadFileName = 'source_source_test.csv.csv';
    $this->assertEquals($this->operations->getDownloadFileName(), 'archive_source_test.csv.zip');

    $this->operations->uploadFileName = 'source_source_test.txt.txt';
    $this->assertEquals($this->operations->getDownloadFileName(), 'archive_source_test.txt.zip');

    $this->operations->uploadFileName = 'source_source_test.csv.txt';
    $this->assertEquals($this->operations->getDownloadFileName(), 'archive_source_test.csv.zip');
  }

  // download

  public function testDownloadListError()
  {
    $stub = $this->stubObjectWithOnce('Ftp', array(
      "nlist" => FALSE
    ));
    $this->operations = new Operations($stub, $this->username, $this->password);

    $message = "The /complete directory does not exist.\n";
    $this->assertEquals($this->operations->download(''), array(FALSE, $message));
  }

  public function testDownloadFileNotComplete()
  {
    $ftpStub = $this->stubObjectWithOnce('Ftp', array(
      "nlist" => array('not here')
    ));

    $operationsStub = $this->getMock('Operations', array('waitAndDownload'),
      array($ftpStub, $this->username, $this->password));
    $operationsStub->expects($this->once())
                   ->method('waitAndDownload')
                   ->will($this->returnValue(array(FALSE, 'An error')));

    $this->assertEquals($operationsStub->download('', $operationsStub), array(FALSE, 'An error'));
  }

  public function testDownloadFileCompleteNoDownloadError()
  {
    $formatted = $this->operations->getDownloadFileName();
    $location = '/tmp';

    $stub = $this->stubObjectWithOnce('Ftp', array(
      "nlist" => array(
        'not here',
        'or here',
        $formatted,
        'or or here'
      ),
      "get" => FALSE,
      "last_message" => 'An Error'
    ));
    $stub->expects($this->once())
         ->method('get')
         ->with("/complete/$formatted", "$location/$formatted");

    $this->operations = new Operations($stub, $this->username, $this->password);

    $message = "\nFile Download Error: An Error\n";
    $this->assertEquals($this->operations->download($location), array(FALSE, $message));
  }

  public function testDownloadFileCompleteAndDownload()
  {
    $formatted = $this->operations->getDownloadFileName();
    $location = '/tmp';

    $stub = $this->stubObjectWithOnce('Ftp', array(
      "nlist" => array(
        'not here',
        'or here',
        $formatted,
        'or or here'
      ),
      "get" => TRUE
    ));

    $this->operations = new Operations($stub, $this->username, $this->password);

    $message = "$formatted downloaded to $location.\n";
    $this->assertEquals($this->operations->download($location), array(TRUE, $message));
  }

  // waitAndDownload

  private function setupWaitAndDownload($mockDownload = FALSE, $mockInit = FALSE)
  {
    $ftpStub = $this->stubObjectWithOnce('Ftp', array(
      "quit" => TRUE
    ));

    $this->operations = new Operations($ftpStub, $this->username, $this->password);

    $operationsStub = $this->getMock('Operations', array('echoAndSleep', 'download', 'init'),
      array($ftpStub, $this->username, $this->password));
    $operationsStub->expects($this->once())
                   ->method('echoAndSleep');
    if ($mockDownload){
      $operationsStub->expects($this->once())
                     ->method('download');
    }
    if ($mockInit){
      $operationsStub->expects($this->once())
                     ->method('init')
                     ->will($this->returnValue(TRUE));
    }

    return $operationsStub;
  }

  public function testWaitAndDownloadPrintsAndSleeps()
  {
    $stub = $this->setupWaitAndDownload(TRUE, TRUE);
    $stub->expects($this->once())
         ->method('echoAndSleep')
         ->with("Waiting for results file test.csv ...\n", 1000);

    $this->operations->waitAndDownload(1000, 'test.csv', '/tmp', $stub);
  }

  public function testWaitAndDownloadReturnsFalseOnNoReconnect()
  {
    $stub = $this->setupWaitAndDownload();
    $stub->expects($this->once())
         ->method('init')
         ->will($this->returnValue(FALSE));
    $stub->expects($this->never())
         ->method('download');

    $result = $this->operations->waitAndDownload(1000, 'test.csv', '/tmp', $stub);
    $this->assertEquals($result, array(FALSE, "Could not reconnect to the server.\n"));
  }

  public function testWaitAndDownloadReturnsDownload()
  {
    $stub = $this->setupWaitAndDownload(FALSE, TRUE);
    $stub->expects($this->once())
         ->method('download')
         ->with('/tmp')
         ->will($this->returnValue(array(TRUE, 'test message')));

    $result = $this->operations->waitAndDownload(1000, 'test.csv', '/tmp', $stub);
    $this->assertEquals($result, array(TRUE, 'test message'));
  }

  // quit

  public function testQuit()
  {
    $stub = $this->stubObjectWithOnce('Ftp', array(
      "quit" => TRUE
    ));
    $this->operations = new Operations($stub, $this->username, $this->password);

    $this->operations->quit();
  } 
}
?>
