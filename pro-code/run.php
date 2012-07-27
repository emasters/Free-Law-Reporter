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

#
# Begin script execution, if run from command line

if (basename(__FILE__) == basename($argv[0]))
{
  set_time_limit(0);
  if (count($argv) > 2 and file_exists($argv[1]) and is_dir($argv[2]))
  {
    $in_dir = $argv[1];
    $out_dir = $argv[2];
    if ($argv[3])
      $skippast = $argv[3];
  } else
  {
    echo "USAGE: php run.php [indir] [outputdir] [skip - optional]\n\n";
    echo "[skip] is an optional filename (no path) that you want to skip up to and past, to resume a job\n";
    exit;
  }

  $active = TRUE;
  if (isset($skippast) and $skippast)
    $active = FALSE;

  $dir = new DirectoryIterator($in_dir);
  foreach ($dir as $file)
  {
    $textfile = $file->getPathname();
    if ((strpos($textfile, '.htm') !== FALSE) and $active) {

      // Load case
      $case = new FastcaseCase('1.0', 'utf-8');
      echo "Loading $textfile ... ";
      $case->loadFCHTML($textfile);
      echo "Loaded.\n";
 
      // Save our html version
      $fc2html = new FastcaseCase2HTML($case);
      $fc2html->makeDirStructure = TRUE;
      $fc2html->saveProXHTML($out_dir);

      $case->__destruct();
      unset($case);
      unset($fc2html);
      echo "\n";
    } else {
      if (!$active and ($textfile == "$in_dir/$skippast"))
        $active = TRUE;
    }
  }

  echo "Done!\n";
}

?>
