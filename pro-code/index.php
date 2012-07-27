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
require_once('FastcaseEditionIndex.php');
require_once('FastcaseVolumeIndex.php');

#
# Begin script execution, if run from command line

if (basename(__FILE__) == basename($argv[0]))
{
  set_time_limit(0);
  if (count($argv) > 1 and is_dir($argv[1]))
  {
    $dir = $argv[1];
  } else
  {
    echo "USAGE: php index.php [directory of volume directories to index]\n";
    exit;
  }

  $edition = new FastcaseEditionIndex($dir, TRUE); // last arg is verbose=TRUE
  $edition->scanDir();
  $edition->save();

  echo "Done!\n";
}

?>
