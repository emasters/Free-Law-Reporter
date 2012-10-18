<?php
/**
 * Turning the result of a FLR search into an .epub eBook
 */
include_once("epub/EPub.php");
if (isset($_POST['q2'])){
    $query = str_replace(' ','+',$_POST['q2']);
    $now = date('m-d-Y');
    $serializedResult = file_get_contents('http://localhost:8983/solr/select?q='.$query.'&rows=500&fl=attr_stream_name&wt=phps');
    $result = unserialize($serializedResult);
    $book = new EPub();
    //add some meta data
    $book->setTitle("Free Law Reporter Results for $query");
    $book->setIdentifier("http://www.freelawreporter.org/custom/$query/", EPub::IDENTIFIER_URI); // Could also be the ISBN number, prefered for published books, or a UUID.
    $book->setLanguage("en"); // Not needed, but included for the example, Language is mandatory, but EPub defaults to "en". Use RFC3066 Language codes, such as "en", "da", "fr" etc.
    $book->setDescription("This is a custom voulume of the Free Law Reporter\nIt contains documents retreived with a the search \"$query\" on $now.");
    $book->setAuthor("CALI", "CALI"); 
    $book->setPublisher("eLangdell Free Law Reporter", "http://elangdell.cali.org/"); 
    $book->setDate(time()); 
    $book->setRights("CC0 Public Domain"); // As this is generated, this _could_ contain the name or licence information of the user who purchased the book, if needed. If this is used that way, the identifier must also be made unique for the book.
    $book->setSourceURL("http://www.freelawreporter.org/");
    //Style for the book
    $cssData = "body {\n  margin-left: .5em;\n  margin-right: .5em;\n  text-align: justify;\n}\n\np {\n  font-family: serif;\n  font-size: 10pt;\n  text-align: justify;\n  text-indent: 1em;\n  margin-top: 0px;\n  margin-bottom: 1ex;\n}\n\nh1, h2 {\n  font-family: sans-serif;\n  font-style: italic;\n  text-align: center;\n  background-color: #6b879c;\n  color: white;\n  width: 100%;\n}\n\nh1 {\n    margin-bottom: 2px;\n}\n\nh2 {\n    margin-top: -2px;\n    margin-bottom: 2px;\n}\n";

    $book->addCSSFile("styles.css", "css1", $cssData);
    //genric header for the HTML pages
    $content_start =
        "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n"
        . "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.1//EN\"\n"
        . "    \"http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd\">\n"
        . "<html xmlns=\"http://www.w3.org/1999/xhtml\">\n"
        . "<head>"
        . "<meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\" />\n"
        . "<link rel=\"stylesheet\" type=\"text/css\" href=\"styles.css\" />\n"
        . "<title>$now Free Law Reporter $query</title>\n"
        . "</head>\n"
        . "<body>\n";
    // Cover page and Forward 
    $cover = $content_start . "<h1>Free Law Reporter</h1>\n<h2>Results for</h2>\n<h2>\"$query\"</h2>"
        . "</body>\n</html>\n";
    $book->addChapter("Title", "Cover.html", $cover);
    //add a forward. Could include a donation prompt.
    $forward = $content_start."<h2>Forward</h2>"
        ."<p><a href=\"http://www.freelawreporter.org\">The Free Law Reporter (FLR)</a> is a project of "
        ."the <a href=\"http://www.cali.org/\">Center for Computer-Assisted Legal Instruction (CALI)</a> "
        ."and is built on the <a href=\"http://radar.oreilly.com/2010/12/the-report-of-current-opinions.html\">"
        ."Report of Current Opinions (RECOP)</a> project.</p>"
        ."This is a custom volume of the FLR containing documents retreived with a search of \"$query\" on $now. "
        ."The individual documents found in this volume are available on the web at "
        ."<a href=\"http://www.freelawreporter.org/\">http://www.freelawreporter.org/</a>."
        ."</body>\n</html>\n";
    $book->addChapter("Forward", "Forward.html", $forward);
    // add the cases from the search result
    foreach($result['response']['docs'] as $opinion){
        extract($opinion);
        $file = $attr_stream_name[0];
        $tags = get_meta_tags($file);
        $parties = str_replace("&", "&#38;", $tags['parties']);
        $courtname = $tags['courtname'];
        $docketnumber = $tags['docketnumber'];
        $decisiondate = $tags['decisiondate'];
        $chapter = tidy_repair_file($file);
        $book->addChapter($parties.' '.$docketnumber.' '.$courtname.' '.$decisiondate, $file, $chapter);
    }
    $book->finalize();
    $tsearch = str_replace(' ','-',$query);
    $zipData = $book->sendBook("$now-FLR-$tsearch");
} else {
    echo 'Oh, NO!';
}

?>