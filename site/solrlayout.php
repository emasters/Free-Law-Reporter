
<h3>Search</h3>
<form action="<?php echo htmlentities($_SERVER['PHP_SELF']); ?>" method="post">
    Enter search term <input id="query" name="query" type="text" />
    <input id="start" name="start" type="hidden" value="0" />
    <input id="submit" name="submit" type="submit" value="Search" />
</form>
<?php
if(isset($_POST['submit'])){
    
    $query = str_replace(' ','+',$_POST['query']);
    $start = $_POST['start'];
    /*$code = file_get_contents('http://localhost:8983/solr/select?q='.$query.'&fl=score+id+courtname+parties+epuburl+attr_stream_name&hl=true&hl.fl=body&hl.snippets=3&wt=php');
    eval("\$result = " . $code . ";");
    echo "<pre>";
    print_r($result);
    echo "</pre>";*/
    $serializedResult = file_get_contents('http://localhost:8983/solr/select?q='.$query.'&fl=score+id+title+decisondate+docketnumber+courtname+parties+epuburl+jurisdiction+decisiondate+attr_stream_name&hl=true&hl.fl=body&hl.snippets=3&facet=true&facet.limit=-1&facet.field=jurisdiction&facet.field=decisiondate&facet.field=courtname&facet.mincount=1&start='.$start.'&wt=phps');
    $result = unserialize($serializedResult);
    echo "<pre>";
    print_r($result);
    echo "</pre>";
}
?>