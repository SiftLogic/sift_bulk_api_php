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

  private function setupDefaultDownload($getIs = TRUE, $lastMessage = FALSE)
  {
    $formatted = 'test.zip';
    $location = '/tmp';

    $init = array(
      "nlist" => array(
        'not here',
        'or here',
        $formatted,
        'or or here'
      ),
      "get" => $getIs
    );
    if ($lastMessage)
    {
      $init["last_message"] = 'An Error';
    }

    $stub = $this->stubObjectWithOnce('Ftp', $init);

    return array(
      "formatted" => $formatted,
      "location" => $location,
      "ftp" => $stub
    );
  }

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
                   ->with($operationsStub->pollEvery, 'test.zip', '/tmp', FALSE, '')
                   ->will($this->returnValue(array(FALSE, 'An error')));
    $operationsStub->uploadFileName = 'test.zip';

    $result = $operationsStub->download('/tmp', FALSE, $operationsStub);
    $this->assertEquals($result, array(FALSE, 'An error'));
  }

  public function testDownloadFileCompleteNoDownloadError()
  {
    $result = $this->setupDefaultDownload(FALSE, TRUE);

    $shouldBe = $result['location'] .'/'. $result['formatted'];
    $result['ftp']->expects($this->once())
                  ->method('get')
                  ->with('/complete/'.$result['formatted'], $shouldBe);

    $this->operations = new Operations($result['ftp'], $this->username, $this->password);
    $this->operations->uploadFileName = $result['formatted'];

    $message = "\nFile Download Error: An Error\n";
    $this->assertEquals($this->operations->download($result['location']), array(FALSE, $message));
  }

  public function testDownloadFileCompleteAndDownload()
  {
    $result = $this->setupDefaultDownload();

    $this->operations = new Operations($result['ftp'], $this->username, $this->password);
    $this->operations->uploadFileName = $result['formatted'];

    $message = $result['formatted'] .' downloaded to '. $result['location'] .".\n";
    $this->assertEquals($this->operations->download($result['location']), array(TRUE, $message));
  }

  public function testDownloadFileCompleteAndDownloadAndRemove()
  {
    $result = $this->setupDefaultDownload();

    $this->operations = new Operations($result['ftp'], $this->username, $this->password);
    $this->operations->uploadFileName = $result['formatted'];

    $operationsStub = $this->getMock('Operations', array('remove', 'getDownloadFileName'),
      array($result['ftp'], $this->username, $this->password));
    $operationsStub->expects($this->once())
                   ->method('remove')
                   ->will($this->returnValue(array(TRUE, "Successful download.\n")));
    $operationsStub->expects($this->once())
                   ->method('getDownloadFileName')
                   ->will($this->returnValue($result['formatted']));

    $message1 = $result['formatted']. ' downloaded to ' .$result['location']. ".\n";
    $message2 = "Successful download.\n";

    $result = $operationsStub->download($result['location'], TRUE, $operationsStub);
    $this->assertEquals($result, array(TRUE, $message1 .$message2));
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

    $this->operations->waitAndDownload(1000, 'test.csv', '/tmp', FALSE, $stub);
  }

  public function testWaitAndDownloadReturnsFalseOnNoReconnect()
  {
    $stub = $this->setupWaitAndDownload();
    $stub->expects($this->once())
         ->method('init')
         ->will($this->returnValue(FALSE));
    $stub->expects($this->never())
         ->method('download');

    $result = $this->operations->waitAndDownload(1000, 'test.csv', '/tmp', FALSE, $stub);
    $this->assertEquals($result, array(FALSE, "Could not reconnect to the server.\n"));
  }

  public function testWaitAndDownloadReturnsDownload()
  {
    $stub = $this->setupWaitAndDownload(FALSE, TRUE);
    $stub->expects($this->once())
         ->method('download')
         ->with('/tmp', TRUE)
         ->will($this->returnValue(array(TRUE, 'test message')));

    $result = $this->operations->waitAndDownload(1000, 'test.csv', '/tmp', TRUE, $stub);
    $this->assertEquals($result, array(TRUE, 'test message'));
  }

  // remove

  public function testRemoveUnsuccessful()
  {
    $formatted = 'test.zip';// No need for conversion...

    $stub = $this->getMock('Ftp');
    $stub->expects($this->once())
       ->method('delete')
       ->with("/complete/$formatted")
       ->will($this->returnValue(FALSE));

    $this->operations = new Operations($stub, $this->username, $this->password);
    $this->operations->uploadFileName = $formatted;

    $result = $this->operations->remove();
    $this->assertEquals($result, array(FALSE, "Could not remove $formatted from the server.\n"));
  }

  public function testRemoveSuccessful()
  {
    $formatted = 'test.zip';// No need for conversion...

    $stub = $this->getMock('Ftp');
    $stub->expects($this->once())
       ->method('delete')
       ->with("/complete/$formatted")
       ->will($this->returnValue(TRUE));

    $this->operations = new Operations($stub, $this->username, $this->password);
    $this->operations->uploadFileName = $formatted;

    $result = $this->operations->remove();
    $this->assertEquals($result, array(TRUE, ""));
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
