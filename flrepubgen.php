#!/usr/bin/php
<?php

if ($argc != 3 || in_array($argv[1], array('--help', '-help', '-h', '-?'))) {
?>

This is a command line PHP script with 2 options.

  Usage:
  <?php echo $argv[0]; ?> <volume#> <RECOP archive date>

  <volume#> is the weekly volume number of the RECOP archive.
  <RECOP archive date> is the date of distribution of the RECOP archive.
  
<?php
} else {

/**
 * This generates an .epub file
 * from a directory full of HTML files in FLR.
 * 
 */   

include_once("includes/EPub.php");
$volnum = $argv[1];
//use $basedir to build a separate epub collection 
//$basedir = "/var/www/flr/volumes/"; 
//$voldir = $basedir.$volnum; 
$voldir = "/var/www/flr/$volnum"; 
$recop = $argv[2]; //date of the recop archive
if ($dh = opendir($voldir)){
    
    while (false !== ($file = readdir($dh))) {
         if ($file != "." && $file != "..") {
              if (is_dir($voldir.'/'.$file)){
                $book = new EPub();
                $curdir = $voldir.'/'.$file;
                if ($fdh = opendir($curdir)){
                    $jurisname = ucwords(str_replace('-',' ',$file));
                    $jurisname = str_replace('U.s.', 'U.S.', $jurisname);
                    $jurisname = str_replace('D.c', 'D.C.', $jurisname);
                    
                    $volname = $volnum.'FLR'.str_replace(' ','',$jurisname);
                    echo "<h4>Entering directory $jurisname ... </h4><br />";
                    //start creating an epub for the current directory
                    //create some meta data for the book. Change as necessary
                    // Title and Identifier are mandatory!
                    $book->setTitle("$volnum MY Law Reporter $jurisname");
                    $book->setIdentifier("http://www.example.com/$volnum/$file/", EPub::IDENTIFIER_URI); // Could also be the ISBN number, prefered for published books, or a UUID.
                    $book->setLanguage("en"); // Not needed, but included for the example, Language is mandatory, but EPub defaults to "en". Use RFC3066 Language codes, such as "en", "da", "fr" etc.
                    $book->setDescription("This is volume $volnum of the My Law Reporter.\nIt contains $jurisname opinions as included in the RECOP archive file of $recop.");
                    $book->setAuthor("Your Name", "Your Name"); 
                    $book->setPublisher("My Law Reporter", "http://www.example.com/"); 
                    $book->setDate(time()); 
                    $book->setRights("CC0 Public Domain"); // As this is generated, this _could_ contain the name or licence information of the user who purchased the book, if needed. If this is used that way, the identifier must also be made unique for the book.
                    $book->setSourceURL("http://www.example.com/$volnum/$file/");
                    
                    /**
                     * create a cover image for this volume.
                     * Requires wkhtmltoimage and a generic image to use as a background
                     * See createImage() below for more information
                     * If you use this, then you don't need the cover/title chapter page below.
                     */
                    //createImage($volnum,$jurisname,$curdir,$volname);
                    //$book->setCoverImage('/var/www/flr/images/'.$volname.'.png');
                    
                    $cssData = "body {\n  margin-left: .5em;\n  margin-right: .5em;\n  text-align: justify;\n}\n\np {\n  font-family: serif;\n  font-size: 10pt;\n  text-align: justify;\n  text-indent: 1em;\n  margin-top: 0px;\n  margin-bottom: 1ex;\n}\n\nh1, h2 {\n  font-family: sans-serif;\n  font-style: italic;\n  text-align: center;\n  background-color: red;\n  color: black;\n  width: 100%;\n}\n\nh1 {\n    margin-bottom: 2px;\n}\n\nh2 {\n    margin-top: -2px;\n    margin-bottom: 2px;\n}\n";

                    $book->addCSSFile("styles.css", "css1", $cssData);

                    $content_start =
                        "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n"
                        . "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.1//EN\"\n"
                        . "    \"http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd\">\n"
                        . "<html xmlns=\"http://www.w3.org/1999/xhtml\">\n"
                        . "<head>"
                        . "<meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\" />\n"
                        . "<link rel=\"stylesheet\" type=\"text/css\" href=\"styles.css\" />\n"
                        . "<title>$volnum Free Law Reporter $jurisname</title>\n"
                        . "</head>\n"
                        . "<body>\n";
                    //Simple cover w/o graphics. For something fancier, see above
                    $cover = $content_start . "<h1>Free Law Reporter</h1>\n<h2>$statename</h2>\n<h2>Volume $volnum</h2>"
                        . "</body>\n</html>\n";
                    $book->addChapter("Title", "Cover.html", $cover);
                    //Create a forward. Could be useful for letting the world know about you.
                    //change as necessary, but please keep a link to CALI
                    $forward = $content_start."<h2>Forward</h2>"
                        ."<p><a href=\"http://www.freelawreporter.org\">The Free Law Reporter (FLR)</a> is a project of "
                        ."the <a href=\"http://www.cali.org/\">Center for Computer-Assisted Legal Instruction (CALI)</a> "
                        ."and is built on the <a href=\"http://radar.oreilly.com/2010/12/the-report-of-current-opinions.html\">"
                        ."Report of Current Opinions (RECOP)</a> project.</p>"
                        ."This is volume $volnum of the FLR for $jurisname and it was part of the $recop RECOP archive. "
                        ."The individual documents found in this volume are available on the web at "
                        ."<a href=\"http://www.freelawreporter.org/$volnum/$file/\">http://www.freelawreporter.org/$volnum/$file/</a>."
                        ."</body>\n</html>\n";
                    $book->addChapter("Forward", "Forward.html", $forward);
                    
                     while (false !== ($file = readdir($fdh))) {
                         if ($file != "." && $file != "..") {
                             // get parties from meta tag to use as chapter title.
                             // feed html file in as string for chapter body.
                             $tags = get_meta_tags($curdir.'/'.$file);
                             $parties = str_replace("&", "&#38;", $tags['parties']);
                             $courtname = $tags['courtname'];
                             $docketnumber = $tags['docketnumber'];
                             $decisiondate = $tags['decisiondate'];
                             $chapter = tidy_repair_file($curdir.'/'.$file);
                            
                            $book->addChapter($parties.' '.$docketnumber.' '.$courtname.' '.$decisiondate, $file, $chapter);

                        }
                    }
                closedir($fdh);
                }
                $book->finalize();
                
                // build new structure for stashing epub files.
                // use section below to create a separate .epub collection.
                /*
                $dirname = str_replace(' ','-',strtolower($jurisname)); 
                if (!is_dir($basedir.$dirname)){
                    mkdir($basedir.$dirname);
                    }
                
                $handle = fopen($basedir.$dirname.'/'.$volname.'.epub','w');
                */
                //This drops the epub file into the same directory as the HTML files for the volume
                $handle = fopen($curdir.'/'.$volnum.'FLR'.str_replace(' ','',$jurisname).'.epub','w');
                $zipData = $book->getBook();
                fwrite($handle,$zipData);
                fclose($handle);
             }
        }
    }
    closedir($dh);
}
}
/**
 * Create a cover image to use in the epub.
 * Takes volume number, jurisdiction name, current directory, and volume name.
 * Requires a 600x800 PNG to use as a background for a temp HTML page
 * Uses wkhtmltoimage to render the temp HTML into a PNG that is used as the cover image.
 * Make sure background image path is correct.
 * Make sure path for temp HTML files is correct.
 * Make sure path to wkhtmltoimage is correct.
 */
function createImage($volnum,$jurisname,$curdir,$volname){
    $coverHTML = "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n"
                . "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.1//EN\"\n"
                . "    \"http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd\">\n"
                . "<html xmlns=\"http://www.w3.org/1999/xhtml\">\n"
                . "<head>"
                . "<meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\" />\n"
                ."<title>$volnum Mu Law Reporter $jurisname</title>\n"
                ."<style type=\"text/css\">\n"
                .".cover { \n"
                ."background-image:url(\"/var/www/flr/images/BlankCover.png\");\n"
                ."margin-top:366px;\n"
                ."margin-left:128px;\n"
                ."font-family:serif;\n"
                ."font-size:48px;\n"
                ."text-align:center;\n"
                ."}\n"
                ."</style>\n"
                ."</head>\n"
                ."<body class=\"cover\">\n"
                ."<p>$jurisname</p>\n"
                ."<p>Volume $volnum</p>"
                ."</body>"
                ."</html>";
    $fn = '/var/www/flr/images/'.$volname.'.html';
    $handle = fopen($fn, 'w');
    fwrite($handle, $coverHTML);
    fclose($handle);
    // uses wkhtmltoimage to create cover image. Set path for your server.
    // set width and height to match your image
    exec('/path/to/wkhtmltoimage-amd64 --height 800 --width 601 --quality 50 '.$fn.' /var/www/flr/images/'.$volname.'.png');
    return;
}
?>