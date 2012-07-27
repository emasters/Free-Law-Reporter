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


#
# Begin script execution, if run from command line

if (basename(__FILE__) == basename($argv[0]))
{
  set_time_limit(0);
  if (count($argv) > 1 and is_dir($argv[1]))
  {
    $dirpath = $argv[1];
  } else
  {
    echo "USAGE: php countvolume.php [directory of volume directories to count]\n";
    exit;
  }

  // Scan dirs
  $dir = new DirectoryIterator($dirpath);
  foreach ($dir as $file) {
    if ($file->isDir() and !$file->isDot() and is_numeric($file->getFilename())) {
      $path = $file->getPathname();
      $vol = intval($file->getFilename());
      $count = 0;
      $subdir = new DirectoryIterator($path);
      foreach ($subdir as $sub) {
        if ($sub->isFile() and $sub->getFilename() != 'index.html')
          $count++;
      }
      $vols[$vol] = $count;
    }
  }
  ksort($vols);

  // Print stats
  $first = FALSE;
  $listing = "Vol\tCount\n";
  foreach ($vols as $vol => $count) {
    if ($first === FALSE)
      $first = $vol;
    $last = $vol;
    $listing .= "$vol\t$count\n";
  }
  echo "\nVolume and case counts for $dirpath\n";
  echo "First volume is: $first\nLast volume is: $last\n";
  $haveMissing = FALSE;
  foreach (range($first, $last) as $num) {
    if (!isset($vols[$num])) {
      if (!$haveMissing) {
        $haveMissing = TRUE;
        echo "Missing volumes:\n";
      }
      echo "$num\n";
    }
  }
  if (!$haveMissing)
    echo "No missing volumes in range.\n";

  // Print listing
  echo "\nCase counts per volume (tab-separated):\n$listing";
}

?>
