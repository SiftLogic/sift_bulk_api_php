<?php
require_once 'Operations.php';
require_once 'FtpOperations.php';
require_once 'patched_pemftp/ftp_class.php';

class FtpOperationsTest extends PHPUnit_Framework_TestCase
{
  private $username;
  private $password;
  private $host;
  private $port;
  private $ftp;
  private $ftpOperations;

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
    $this->ftpOperations = new FtpOperations($this->ftp);

    $this->file = 'test.csv';

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

  public function testFtpSetOnConstruction()
  {
    $this->assertEquals($this->ftpOperations->ftp, $this->ftp);
  }

  public function defaultInit()
  {
    return $this->ftpOperations->init($this->username, $this->password, $this->host, $this->port);
  }

  public function defaultInitAndDebug($beforeCreate = NULL, $toStubReturns = NULL)
  {
    $toStub = array(
      "SetServer" => TRUE,
      "connect" => TRUE,
      "login" => TRUE,
      "SetType" => TRUE,
      "Passive" => TRUE
    );

    if ($toStubReturns !== NULL)
    {
      $toStub = array_merge($toStub, $toStubReturns);
    }

    $stub = $this->stubObjectWithOnce('Ftp', $toStub);

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

    if ($beforeCreate !== NULL)
    {
      $beforeCreate($stub);
    }

    $this->ftpOperations = new FtpOperations($stub);

    return $this->defaultInit();
  }

  // init

  public function testInitSetServerError() {
    $this->setExpectedException('RuntimeException');

    $this->ftpOperations = new FtpOperations($this->stubObjectWithOnce('Ftp', array(
      "SetServer" => FALSE,
      "quit" => TRUE
    )), $this->username, $this->password, $this->host, $this->port);

    $this->defaultInit();
  }

  public function testInitConnectError() {
    $this->setExpectedException('RuntimeException');

    $this->ftpOperations = new FtpOperations($this->stubObjectWithOnce('Ftp', array(
      "SetServer" => TRUE,
      "quit" => TRUE,
      "connect" => FALSE
    )), $this->username, $this->password, $this->host, $this->port);

    $this->defaultInit();
  }

  public function testInitLoginError() {
    $this->setExpectedException('RuntimeException');

    $this->ftpOperations = new FtpOperations($this->stubObjectWithOnce('Ftp', array(
      "SetServer" => TRUE,
      "quit" => TRUE,
      "connect" => TRUE,
      "login" => FALSE
    )), $this->username, $this->password, $this->host, $this->port);

    $this->defaultInit();
  }

  public function testInitSetTypeError() {
    $this->setExpectedException('RuntimeException');

    $this->ftpOperations = new FtpOperations($this->stubObjectWithOnce('Ftp', array(
      "SetServer" => TRUE,
      "quit" => TRUE,
      "connect" => TRUE,
      "login" => TRUE,
      "SetType" => FALSE
    )), $this->username, $this->password, $this->host, $this->port);

    $this->defaultInit();
  }

  public function testInitPassiveError() {
    $this->setExpectedException('RuntimeException');

    $this->ftpOperations = new FtpOperations($this->stubObjectWithOnce('Ftp', array(
      "SetServer" => TRUE,
      "quit" => TRUE,
      "connect" => TRUE,
      "login" => TRUE,
      "SetType" => TRUE,
      "Passive" => FALSE
    )), $this->username, $this->password, $this->host, $this->port);

    $this->defaultInit();
  }

  public function testInitSuccess() {
    $this->assertEquals($this->defaultInitAndDebug(), TRUE);

    $details = $this->ftpOperations->getConnectionDetails();
    $this->assertEquals($details['username'], $this->username);
    $this->assertEquals($details['password'], $this->password);
    $this->assertEquals($details['host'], $this->host);
    $this->assertEquals($details['port'], $this->port);
  }

  // upload

  public function testUploadNoFileError() 
  {
    $this->assertEquals($this->defaultInitAndDebug(), TRUE);

    $message = "File Upload Error: other.csv does not exist\n";
    $this->assertEquals($this->ftpOperations->upload('other.csv'), array(FALSE, $message));
  }

  public function testUploadFileUploadErrorWithMultiFile() 
  {
    $this->assertEquals($this->defaultInitAndDebug(function($stub) {
      $dir = "import_{$this->username}_splitfile_config";

      $stub->expects($this->once())
           ->method('put')
           ->with($this->file, "$dir/$this->file");
    }, array(
      "put" => FALSE,
      "last_message" => 'An Error'
    )), TRUE);

    $message = "\nFile Upload Error: An Error\n";
    $this->assertEquals($this->ftpOperations->upload($this->file), array(FALSE, $message));
  }

  public function testUploadFileUploadErrorWithSingleFile() 
  {
    $this->assertEquals($this->defaultInitAndDebug(function($stub) {
      $dir = "import_{$this->username}_default_config";

      $stub->expects($this->once())
           ->method('put')
           ->with($this->file, "$dir/$this->file");
    }, array(
      "put" => FALSE,
      "last_message" => 'An Error'
    )), TRUE);

    $message = "\nFile Upload Error: An Error\n";
    $this->assertEquals($this->ftpOperations->upload($this->file, TRUE), array(FALSE, $message));
  }

  public function testUploadFileNameExtractionError() 
  {
    $this->assertEquals($this->defaultInitAndDebug(function($stub) {
      $dir = "import_{$this->username}_splitfile_config";

      $stub->expects($this->once())
           ->method('put')
           ->with($this->file, "$dir/$this->file");
    }, array(
      "put" => TRUE,
      "last_message" => 'source_test_data_20140523_0012.csv'
    )), TRUE);

    $message = "Failed to extract filename from: source_test_data_20140523_0012.csv\n";
    $this->assertEquals($this->ftpOperations->upload($this->file), array(FALSE, $message));
  }

  public function testUploadSuccess() 
  {
    $file = '/tmp/test.csv';
    $lastMessage = array(
      '226 closing data connection;',
      'File upload success;',
      'source_test_data_20140523_0015.csv'
    );

    $this->assertEquals($this->defaultInitAndDebug(function($stub) {
      $dir = "import_{$this->username}_splitfile_config";

      $stub->expects($this->once())
           ->method('put')
           ->with($this->file, "$dir/$this->file");
    }, array(
      "put" => TRUE,
      "last_message" => implode(' ', $lastMessage)
    )), TRUE);

    $message = "test.csv has been uploaded as {$lastMessage[2]}\n";
    $this->assertEquals($this->ftpOperations->upload('test.csv'), array(TRUE, $message));
    $this->assertEquals($this->ftpOperations->uploadFileName, $lastMessage[2]);
  }

  // getDownloadFileName

  public function testGetDownloadFileNameNoModify()
  {
    $this->assertEquals($this->defaultInitAndDebug(), TRUE);

    $this->ftpOperations->uploadFileName = '';
    $this->assertEquals($this->ftpOperations->getDownloadFileName(), '');

    $this->ftpOperations->uploadFileName = 'test_test.doc';
    $this->assertEquals($this->ftpOperations->getDownloadFileName(), 'test_test.doc');
  }

  public function testToDownloadFormatModify()
  {
    $this->assertEquals($this->defaultInitAndDebug(), TRUE);

    $this->ftpOperations->uploadFileName = 'source_test.doc';
    $this->assertEquals($this->ftpOperations->getDownloadFileName(), 'archive_test.doc');
    
    $this->ftpOperations->uploadFileName = 'source_source_test.csv.csv';
    $this->assertEquals($this->ftpOperations->getDownloadFileName(), 'archive_source_test.csv.zip');

    $this->ftpOperations->uploadFileName = 'source_source_test.txt.txt';
    $this->assertEquals($this->ftpOperations->getDownloadFileName(), 'archive_source_test.txt.zip');

    $this->ftpOperations->uploadFileName = 'source_source_test.csv.txt';
    $this->assertEquals($this->ftpOperations->getDownloadFileName(), 'archive_source_test.csv.zip');
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
    $this->assertEquals($this->defaultInitAndDebug(NULL, array(
      "nlist" => FALSE
    )), TRUE);

    $message = "The /complete directory does not exist.\n";
    $this->assertEquals($this->ftpOperations->download('', 300), array(FALSE, $message));
  }

  public function testDownloadFileNotComplete()
  {
    $ftpStub = $this->stubObjectWithOnce('Ftp', array(
      "nlist" => array('not here')
    ));

    $operationsStub = $this->getMock('FtpOperations', array('waitAndDownload'),
      array($ftpStub, $this->username, $this->password));
    $operationsStub->expects($this->once())
                   ->method('waitAndDownload')
                   ->with(300, 'test.zip', '/tmp', FALSE, '')
                   ->will($this->returnValue(array(FALSE, 'An error')));
    $operationsStub->uploadFileName = 'test.zip';

    $result = $operationsStub->download('/tmp', 300, FALSE, $operationsStub);
    $this->assertEquals($result, array(FALSE, 'An error'));
  }

  public function testDownloadFileCompleteNoDownloadError()
  {
    $result = $this->setupDefaultDownload(FALSE, TRUE);

    $shouldBe = $result['location'] .'/'. $result['formatted'];
    $result['ftp']->expects($this->once())
                  ->method('get')
                  ->with('/complete/'.$result['formatted'], $shouldBe);

    $this->operations = new ftpOperations($result['ftp'], $this->username, $this->password);
    $this->operations->uploadFileName = $result['formatted'];

    $message = "\nFile Download Error: An Error\n";
    $this->assertEquals($this->operations->download($result['location'], 300),
                        array(FALSE, $message));
  }

  public function testDownloadFileCompleteAndDownload()
  {
    $result = $this->setupDefaultDownload();

    $this->ftpOperations = new FtpOperations($result['ftp'], $this->username, $this->password);
    $this->ftpOperations->uploadFileName = $result['formatted'];

    $message = 'Downloaded into ' .$result['location']. "/" .$result['formatted']. ".\n";
    $this->assertEquals($this->ftpOperations->download($result['location'], 300),
                        array(TRUE, $message));
  }

  public function testDownloadFileCompleteAndDownloadAndRemove()
  {
    $result = $this->setupDefaultDownload();

    $this->ftpOperations = new FtpOperations($result['ftp'], $this->username, $this->password);
    $this->ftpOperations->uploadFileName = $result['formatted'];

    $operationsStub = $this->getMock('FtpOperations', array('remove', 'getDownloadFileName'),
      array($result['ftp'], $this->username, $this->password));
    $operationsStub->expects($this->once())
                   ->method('remove')
                   ->will($this->returnValue(array(TRUE, "Successful download.\n")));
    $operationsStub->expects($this->once())
                   ->method('getDownloadFileName')
                   ->will($this->returnValue($result['formatted']));

    $message1 = 'Downloaded into ' .$result['location']. "/" .$result['formatted']. ".\n";
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

    $this->operations = new FtpOperations($ftpStub, $this->username, $this->password);

    $operationsStub = $this->getMock('FtpOperations', 
                                  array('echoAndSleep', 'download', 'init', 'getConnectionDetails'),
      array($ftpStub, $this->username, $this->password));
    $operationsStub->expects($this->once())
                   ->method('echoAndSleep');


    $operationsStub->expects($this->once())
                   ->method('getConnectionDetails')
                   ->will($this->returnValue(array(
                      "username" => $this->username,
                      "password" => $this->password,
                      "host" => $this->host,
                      "port" => $this->port,
                    )));
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

    $this->ftpOperations->waitAndDownload(1000, 'test.csv', '/tmp', FALSE, $stub);
  }

  public function testWaitAndDownloadReturnsFalseOnNoReconnect()
  {
    $stub = $this->setupWaitAndDownload();
    $stub->expects($this->once())
         ->method('init')
         ->will($this->returnValue(FALSE));
    $stub->expects($this->never())
         ->method('download');

    $result = $this->ftpOperations->waitAndDownload(1000, 'test.csv', '/tmp', FALSE, $stub);
    $this->assertEquals($result, array(FALSE, "Could not reconnect to the server.\n"));
  }

  public function testWaitAndDownloadReturnsDownload()
  {
    $stub = $this->setupWaitAndDownload(FALSE, TRUE);
    $stub->expects($this->once())
         ->method('download')
         ->with('/tmp', TRUE)
         ->will($this->returnValue(array(TRUE, 'test message')));

    $result = $this->ftpOperations->waitAndDownload(1000, 'test.csv', '/tmp', TRUE, $stub);
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

    $this->ftpOperations = new FtpOperations($stub, $this->username, $this->password);
    $this->ftpOperations->uploadFileName = $formatted;

    $result = $this->ftpOperations->remove();
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

    $this->ftpOperations = new FtpOperations($stub, $this->username, $this->password);
    $this->ftpOperations->uploadFileName = $formatted;

    $result = $this->ftpOperations->remove();
    $this->assertEquals($result, array(TRUE, ""));
  } 

  // quit

  public function testQuit()
  {
    $stub = $this->stubObjectWithOnce('Ftp', array(
      "quit" => TRUE
    ));
    $this->ftpOperations = new FtpOperations($stub, $this->username, $this->password);

    $this->ftpOperations->quit();
  } 
}
?>
