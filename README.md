PHP Bulk API
============

This demonstrates how to connect to the bulk server with PHP. Unlike FTP, HTTP requires no username just the password (auth token). The apikey and password/auth token are found in the UI: API Keys -\> Manage -\> Actions -\> Access Details. Once you have that you can try the main.php file for an upload demo. For example, using HTTP:
<pre>
  <code>
    ./main.php -f test.csv -l /tmp -p e261742d-fe2f-4569-95e6-312689d04903 --poll 10
  </code>
</pre>
The CLI is described in more detail with <code>./main.php --help</code>

It is recommended to require the Operations file and use the methods in there to customize your process. The methods are described in file.

Licensing
=========

Copyright 2014 SiftLogic LLC

SiftLogic LLC hereby grants to SiftLogic Customers a revocable, non-exclusive, non-transferable, limited license for use of SiftLogic sample code for the sole purpose of integration with the SiftLogic platform.

Please see README.md in folder patched_pemftp for 3rd party License details.

Installation
============
Make sure PHP \>= <b>5.5</b> is installed (5.0 should work, but you may encounter issues) and a [composer.phar file](https://github.com/composer/composer#installation--usage), then: 
<pre>
  <code>
    php composer.phar install
  </code>
</pre>

If you want to run the tests (<code>phpunit --strict tests</code>):

<pre>
  <code>
    sudo apt-get install phpunit# Substitute apt-get for your systems package manager
  </code>
</pre>

Files And Folders
=================
* **main.php:** Example CLI that uploads a file, polls for it to complete, then downloads it.
* **Operations.php:** Class that controls server connections.
* **ftpOperations.js:** Object that provides an FTP interface to the server.
* **httpOperations.js:** Object that provides an HTTP interface to the server.
* **/tests:** Unit tests of API functionality. It is recommended that you update these if you want to customize operations.js.
* **composer.json:** Standard [Composer](https://getcomposer.org/doc/01-basic-usage.md) specification file.
* **vendor:** Standard location of composer packages.
* **patched_pemftp:** FTP library. Only a few work, and there were a few customizations. See that README.
* **test.csv:** A small sample records file. 
