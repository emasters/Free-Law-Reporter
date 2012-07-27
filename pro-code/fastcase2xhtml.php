#!/usr/bin/php
<?php

/* 
 *
 * By Public.Resource.Org, Inc.
 * No Rights Reserved.
 *
 * Author: Joel Hardi <joel -at- hardi.org>
 *
 * Status: Stable
 * Revision: 0.116
 *
 * See readme.txt in the root of this directory tree.
 *
 */

ini_set('include_path', dirname(__FILE__).'/lib/php5:'.ini_get('include_path'));
require_once('FastcaseCase.php');
require_once('FastcaseCase2HTML.php');

# Begin tests/script execution, if run from command line
if (basename(__FILE__) == basename($argv[0]))
{
  set_time_limit(0);
  $textfile = $argv[1];
  if (!file_exists($textfile))
    die("You must specify a filename!\n");
  $out_html = $argv[2];

  // Load case
  $case = new FastcaseCase('1.0', 'utf-8');
  $case->loadFCHTML($textfile);

  if ($out_html)
    file_put_contents($out_html, $case->saveProXHTML());
  else
    echo $case->saveProXHTML();

  echo "\n";
}

?>
