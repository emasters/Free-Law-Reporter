#!/usr/bin/php
<?php
/**
 * run from CLI, adds uuid, epub meta
 * to PRO cases; inserts file into Solr
 * 
 */
include_once("includes/lib.uuid.php"); //to generate a uuid for each document
$filename = $argv[1]; //An HTML file to test against
// Set URL to use for creating UUID. Should be on your site
$url = 'http://www.freelawreporter.org/procases/US'.$filename;
$uuid = UUID::mint(5,$url,UUID::nsURL);

$tidyconfig = array('output-xhtml' => TRUE,
		     'preserve-entities' => TRUE,
                     'wrap' => 200,
                     'clean' => TRUE,);
// load the file and add some tags
//$handle = fopen($filename, 'r+');

$doc = new DOMDocument(1.0);

$doc->loadHTMLFile($filename);
$head = $doc->documentElement->firstChild;

$muuid = $doc->createElement('meta');
$muuid->setAttribute('name','uuid');
$muuid->setAttribute('content',$uuid);
$head->insertBefore($muuid, $head->firstChild->nextSibling->nextSibling->nextSibling->nextSibling->nextSibling->nextSibling);

$doc->xmlStandalone = false;
$doc->save($filename);

$tidy = new tidy();
$repaired = $tidy->repairfile($filename, $tidyconfig);
rename($filename, $filename . '.bak');

file_put_contents($filename, $repaired);

?>
