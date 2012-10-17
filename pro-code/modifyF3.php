#!/usr/bin/php
<?php
/**
 * Load an HTML opinion file, tweak the courtname.
 * I'm starting wtih SCOTUS
 * 1: Open DOMdocument
 * 2: Open <meta content="" name="courtname">
 * 3: Set content="Supreme Court of the United States"
 * 4: Save updated DOMdocument
 * 
 */
 include('includes/simple_html_dom.php');
 include_once('includes/lib.uuid.php'); //to generate a uuid for each document
 
 $casedir = $argv[1]; // the directory to start in
 
 if ($dh = opendir($casedir)){
    while (false !== ($file = readdir($dh))) {
        if ($file != "." && $file != "..") {
            if (is_dir($casedir.'/'.$file)){
                $curdir = $file;
                if ($fdh = opendir($casedir.'/'.$curdir)){
                    while (false !== ($file = readdir($fdh))) {
                        if ($file != "." && $file != ".." && $file != "index.html") {
                            //get a case
                            $htmlfile = $casedir.'/'.$curdir.'/'.$file;
                            
                            // Set URL to use for creating UUID. Should be on your site
                            $url = 'http://www.freelawreporter.org/flr3d/f3d/'.$curdir.'/'.$file;
                            $uuid = UUID::mint(5,$url,UUID::nsURL);
                            echo $uuid.'\n';
                            echo $url;
                            // 1: Open DOMdocument
                            $html = file_get_html($htmlfile);
                            
                            //find the title and make note of it
                            $t = $html->find('title',0);
                            $citetitle = $t->innertext;
                            
                            //search meta tags for useful bits
                            foreach($html->find('meta') as $element){
                               
                               // Assign parties to variable for use in title
                               if ($element->name == 'parties'){
                                   $casename = $element->content;   
                               }      
                            }
                            
                            //create a new title with cite and parties
                            $t->innertext=$citetitle.' - '.$casename;
                            
                            // 4: Save updated DOMdocument
                            $html->save($htmlfile);
                            
                            //fin!
                            $html->clear();
                            unset($html);
                            
                            //trying to use real DOM here
                            $html = new DOMDocument;
                            $html->loadHTMLFile($htmlfile);
                            $head = $html->documentElement->firstChild;
                            
                            $muuid = $html->createElement('meta');
                            $muuid->setAttribute('name', 'uuid');
                            $muuid->setAttribute('content', $uuid);
                            $head->insertBefore($muuid, $head->firstChild->nextSibling->nextSibling);
                            
                            /*$crtabrev = $html->createElement('meta');
                            $crtabrev->setAttribute('name', 'courtabbreviation');
                            $crtabrev->setAttribute('content', 'U.S.');
                            $head->insertBefore($crtabrev, $head->firstChild->nextSibling->nextSibling->nextSibling);*/
                            
                            $rptser = $html->createElement('meta');
                            $rptser->setAttribute('name', 'reporterseries');
                            $rptser->setAttribute('content', 'Federal Reporter, Third Series');
                            $head->insertBefore($rptser, $head->firstChild->nextSibling->nextSibling->nextSibling);
                            
                            $html->saveHTMLFile($htmlfile);
                            
                            /**
                            * use curl to send XHTML files to Solr for indexing.
                            */
                           $ch = curl_init();
                          
                           $data = array('name' => $file, 'file' => '@'.$htmlfile);
                               
                           curl_setopt($ch, CURLOPT_URL, 'http://localhost:8983/solr/update/extract?literal.id='.$uuid.'&uprefix=attr_&fmap.content=body&commit=true');
                           curl_setopt($ch, CURLOPT_POST, 1);
                           curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                               
                           curl_exec($ch);
                        }
                    }
                }
            }
        }
    }
 }
?>