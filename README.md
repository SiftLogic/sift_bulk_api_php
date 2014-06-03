PHP FTP API
===========

This demonstrates how to connect to the ftp server with PHP. You will need to know your apikey and password which can be found in the UI: API Keys -\> Manage -\> Actions -\> Access Details. Once you have that you can try the main.php file for an upload demo. For example:
<pre>
  <code>
    ./main.php -f test.csv -l /tmp -u aUsername -p e261742d-fe2f-4569-95e6-312689d04903 --poll 10
  </code>
</pre>
The CLI is described in more detail with <code>./main.php --help</code>

It is recommended to require the Operations file and use the methods in there to customize your process. The methods are described in file. If you want to cut down on code, this file only requires the files in patched_pemftp.

Licensing
=========

Copyright 2014 SiftLogic LLC

SiftLogic LLC hereby grants to SiftLogic Customers a revocable, non-exclusive, non-transferable, limited license for use of SiftLogic sample code for the sole purpose of integration with the SiftLogic platform.

Please see README.md in folder patched_pemftp for further License details.

Installation
============
Make sure PHP \>= <b>5.0</b> is installed and a [composer.phar file](https://github.com/composer/composer#installation--usage), then: 
<pre>
  <code>
    php composer.phar install
  </code>
</pre>

If you want to run the tests (<code>phpunit --strict OperationsTest</code>):

<pre>
  <code>
    sudo apt-get install phpunit# Substitute apt-get for your systems package manager
  </code>
</pre>

Files And Folders
=================
* **main.php:** Example CLI that uploads a file, polls for it to complete, then downloads it.
* **Operations.php:** Class that controls server connections.
* **OperationsTest.php:** 100% code coverage unit tests of operations.js. It is recommended that you update this if you want to customize operations.js.
* **composer.json:** Standard [Composer](https://getcomposer.org/doc/01-basic-usage.md) specification file.
* **vendor:** Standard location of composer packages.
* **patched_pemftp:** FTP library. Only a few work, and there were a few customizations. See that README.
* **test.csv:** A small sample records file. 