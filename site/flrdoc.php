<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="content-type" content="text/html; charset=us-ascii">

    <title>CALI::The Free Law Reporter</title>
    <link rel="stylesheet" href="css/reset.css" type="text/css">
    <link rel="stylesheet" href="css/text.css" type="text/css">
    <link rel="stylesheet" href="css/960.css" type="text/css">
    <link rel="stylesheet" href="css/flr.css" type="text/css">
    <link rel="stylesheet" href="css/flrcase.css" type="text/css">
    <link type="text/css" href="css/smoothness/jquery-ui-1.8.11.custom.css" rel="stylesheet">
    <script type="text/javascript" src="js/jquery-1.5.2.min.js"></script>
    <script type="text/javascript" src="js/jquery-ui-1.8.11.custom.min.js"></script>
    <script type="text/javascript" src="css/case.js"></script>
    
    <script type="text/javascript">
                    $(function(){
                            // Tabs
                                $('#tabs').tabs();
                        });
    </script>
    <script type="text/javascript">

  var _gaq = _gaq || [];
  _gaq.push(['_setAccount', 'UA-442653-8']);
  _gaq.push(['_trackPageview']);

  (function() {
    var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
    ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
    var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
  })();

</script>
</head>
<body>
    <div class="container_12">
        <header>
            <div class="grid_4">
                <a href="http://www.cali.org/"><img src="images/CALI_LogoTagline_Black.png" width=150 height=59 class="toplogo"/></a>
            </div>
            <div class="grid_8">
                <h1>The Free Law Reporter</h1>
            </div>
        </header>
    <div class="clear"></div>
    
        <div class="grid_12">
<?php
$uuid = $_GET['uuid'];
$serializedResult = file_get_contents('http://localhost:8983/solr/select?q=id:'.$uuid.'&fl=attr_stream_name&wt=phps');
$result = unserialize($serializedResult);
$document = $result['response']['docs'][0];
extract($document);
$docbody = file_get_contents($attr_stream_name[0]);
echo $docbody;
?>
        </div>
        <div class="clear"></div>
        <footer>
            <div class="grid_12">
                <a href="http://www.cali.org/"><img src="images/CALI_LogoTagline_Black.png" width=150 height=59 class="footerlogo"/></a>
                <p class="c2">Contents Copyright The Center for Computer-Assisted Legal Instruction</p>
            </div>
        </footer>
    </div>
</body>
</html>