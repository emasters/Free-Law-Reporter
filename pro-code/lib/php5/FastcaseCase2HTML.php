<?php

/* 
 *
 * By Public.Resource.Org, Inc.
 * No Rights Reserved.
 *
 * Author: Joel Hardi <joel -at- hardi.org>
 *
 * Status: Stable
 * Revision: 0.115
 *
 * See readme.txt in the root of this directory tree.
 *
 */

// Main application/controller class for conversion
class FastcaseCase2HTML
{
  public $makeDirStructure = FALSE;
  protected $case; // FastcaseCase object
  protected $verbose = TRUE; // If TRUE, echoes out various status/debug messages

  public function __construct(FastcaseCase $case, $verbose = TRUE)
  {
    $this->case = $case;
    $this->verbose = $verbose;
  }

  // Saves html version
  public function saveProXHTML($output_dir)
  {
    $output = $this->case->saveProXHTML();
    $name = $this->filenamer('html', $output_dir);
    $this->saveFile($output, $name);
  }

  protected function saveFile($str, $filepath)
  {
    file_put_contents($filepath, $str);
    
    if ($this->verbose)
      echo "Saved ".basename($filepath).". ";
  }

  // Returns full pathname of file to save for current LawCase, file extension and output dir
  protected function filenamer($fileext, $dir)
  {
    // tranform cite to 111.FXd.222 format
    $cite = $this->case->getMainCite();
    if ($cite = $this->case->cleanCiteString($cite))
      $cite = str_replace('_', 'x', str_replace(' ', '.', str_replace('.', '', $cite)));
    else
      $cite = 'other';

    // Make subdir based on volume if is set
    if ($this->makeDirStructure) {
      // subdir is volume number, i.e. first digits of cite before space
      if (preg_match('#[0-9]+#', $cite, $matches))
        $dir = $dir.'/'.$matches[0];
      else
        $dir = $dir.'/other';

      if (!is_dir($dir))
        mkdir($dir);
    }

    // append numeric portions of docket numbers to filename
    $dockets = $this->case->getDocketsNumeric();
    if (is_array($dockets)) {
      // Only append up to first 5 dockets
      while (count($dockets) > 5)
        array_pop($dockets);
      $docket = ".".implode(".", $dockets);
    } else {
      $docket = '';
    }

    $filebase = substr(preg_replace('#\.{2,}#', '.', $cite.$docket.'.'), 0, -1);

    // check to make sure file doesn't already exist, if it does, increment filename
    while (file_exists($dir.'/'.$filebase.'.'.$fileext))
    {
      if (($pos = strpos($filebase, '_')) === FALSE)
      {
        $pos = strlen($filebase);
        $current = 0;
      } else
        $current = substr($filebase, $pos + 1);

      $current++;
      $filebase = substr($filebase, 0, $pos).'_'.$current;
    }

    return $dir.'/'.$filebase.'.'.$fileext;
  }
}

?>
