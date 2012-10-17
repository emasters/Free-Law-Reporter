#!/usr/bin/php
<?php

if ($argc != 3 || in_array($argv[1], array('--help', '-help', '-h', '-?'))) {
?>

This is a command line PHP script with 2 options.
  Usage:
  <?php echo $argv[0]; ?> <path/to/xml> <volume#>

  <path/to/xml> is the directory where the XML we
  want to parse lives. For example /var/www/flr/2011-01-07/SlipOpinions.
  <volume#> is the weekly volume number of the RECOP archive.
  
<?php
} else {
/**
 * For parsing PRO RECOP slip opinions case XML into XHTML/HTML5
 *
 */
 
/**
 * descend a directory and parse XML into valid xhtml.
 */
 include_once("includes/lib.uuid.php"); //to generate a uuid for each document
 $casedir = $argv[1]; // the directory to start in
 $volnum = $argv[2]; // set the volume number to use for directory structure
 //create the directory to save this volume's files into.
 mkdir('/var/www/flr/volumes/'.$volnum); 
 
 if ($dh = opendir($casedir)){
      while (false !== ($file = readdir($dh))) {
        if ($file != "." && $file != "..") {
            if (is_dir($casedir.'/'.$file)){
                $curdir = $casedir.'/'.$file;
                echo "<h4>Entering directory $file ... </h4><br /><ol>";
                if ($fdh = opendir($curdir)){
                    $dirname = str_replace(' ','-',strtolower($file));
                    $structure = '/var/www/flr/volumes/'.$volnum.'/'.$dirname; 
                    echo $structure;
                    if (!mkdir($structure)) {
                        die ("failed to create file structures...");
                        };
                    $webdir = '/var/www/flr/volumes/'.$volnum.'/'.$dirname;
                    while (false !== ($file = readdir($fdh))) {
                    if ($file != "." && $file != "..") {
                    createHTML($curdir, $file, $webdir, $volnum);
                    }
                }
                closedir($fdh);
                }
                echo "</ol>";
            } else {
                echo "<b> $file is not a directory. Nothing to be done";
                }
        }
    }
    closedir($dh);
}
}
function createHTML($dir, $casefile, $webdir, $volnum){
 $fileinfo = pathinfo($dir.'/'.$casefile);
 if ($fileinfo['extension'] == "html"){
     return;
     }
 $xmlfile = $dir.'/'.$casefile;
 echo "<li>Processing XML file $xmlfile ...</li>";
 $xml = simplexml_load_file($xmlfile);
 if (!$xml){
     echo "Failed loading XML\n";
     foreach(libxml_get_errors() as $error) {
        echo "\t", $error->message;
        }
     } else {
 $fn = basename($casefile, '.xml');
 $fn2 = $webdir.'/'.$fn.'.html';
 $handle = fopen($fn2, 'w');
 echo "<ul><li>opening file $fn2 ...</li></ul>";
 
 $pathparts = explode("/", $webdir);
 $statedir = $pathparts[5];
 $statename = ucwords(str_replace('-',' ',$statedir));
 $createdate = gmdate('D, d M Y H:i:s \G\M\T');
 $tidyconfig = array('output-xhtml' => TRUE,
                                 'wrap' => 200,
                                 'clean' => TRUE,);
// Set URL to use for creating UUID. Should be on your site
$url = 'http://www.example.com/'.$volnum.'/'.$statedir.'/'.$fn.'.html';
$uuid = UUID::mint(5,$url,UUID::nsURL);
/**
 * Create HTML
 */
 $htmldoc = '<?xml version="1.0"?>
     <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN"
     "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">

     <html xmlns="http://www.w3.org/1999/xhtml">
     <head>
     <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
 <title>'.$xml->ShortName.'</title>
 <meta name="creation" content="Transformed by the Center for Computer-Assisted Legal Instruction from Public.Resource.Org, Inc. sources, on '.$createdate.'" />
 <meta name="collection" content="RECOP Slip Opinions" />
 <meta name="uuid" content="'.$uuid.'" />
 <meta name="jurisdiction" content="'.$statename.'" />
 <meta name="courtabbreviation" content="'.$xml->Jurisdiction->CourtAbbreviation.'" />
 <meta name="courtname" content="'.$xml->Jurisdiction->CourtName.'" />
 ';
 
 /**
 * Deal with docket numbers
 */
 foreach ($xml->xpath('//DocketNumber') as $docketnumber){
    $htmldoc .= '<meta name="docketnumber" content="'.$docketnumber.'" />';
 }
/**
 * Deal with decison dates
 */
 foreach ($xml->xpath('//DecisionDate') as $decisiondate){
     $ds = str_replace('-','/',$decisiondate);
     $ts = strtotime($ds);
     $tdate = date('Y-m-d', $ts) . 'T' . date('H:i:s', $ts) . 'Z';
    $htmldoc .= '<meta name="decisiondate" content="'.$tdate.'" />';
 }
/**
 * Deal with authors
 */
 foreach ($xml->xpath('//Author') as $author){
    $htmldoc .= '<meta name="author" content="'.$author.'" />';
 }
 // Set the epuburl to where you are saving .epub files
 $htmldoc .= '
 <meta name="parties" content="'.$xml->PartyHeader.'" />
 <meta name="lawyers" content="'.$xml->LawyerHeader.'" />
 <meta name="epuburl" content="http://www.example.com/'.$volnum.'/'.$statedir.'/'.$volnum.'FLR'.str_replace(' ','',$statename).'.epub" />
 </head>
 <body>
<div id="caption">'.$xml->HeaderHtml.'</div>
<div id="opinion">'.$xml->CaseHtml.'</div>
</body>
 </html>';
 $tidyhtmldoc = tidy_repair_string($htmldoc, $tidyconfig);
 fwrite($handle, $tidyhtmldoc);
 fclose($handle);
 /**
  * use curl to send XHTML files to Solr for indexing.
  */
 $ch = curl_init();

 $data = array('name' => $fn, 'file' => '@'.$fn2);
     
 curl_setopt($ch, CURLOPT_URL, 'http://some.solr.host:8888/solr/update/extract?literal.id='.$uuid.'&uprefix=attr_&fmap.content=body&commit=true');
 curl_setopt($ch, CURLOPT_POST, 1);
 curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
     
 curl_exec($ch);
 return;
 }
 }
 
?>
