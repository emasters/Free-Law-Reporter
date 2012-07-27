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

// Baseclass for FastcaseVolumeIndex and FastcaseEditionIndex
abstract class FastcaseIndex
{
  protected $publication; // str, name of pub, i.e. The Federal Reporter
  protected $description; // str, desc of pub, i.e. Decisions of the Federal Courts of Appeals
  protected $edition = NULL; // str, name of Edition, i.e. "3d Edition"
  protected $items; // array of case/volume arrays created by scanDir()
  protected $order; // array of keys of $items that defines the order they should be listed in

  protected $dirpath; // directory path to scan for Volumes or Cases
  protected $outputname = 'index.html';
  protected $verbose = TRUE; // If TRUE, echoes out various status/debug messages
  protected $dir; // DirectoryIterator object
  protected $dom; // ProDOMDocument object
  protected $template; // XHTML template string

  public function __construct($dirpath, $verbose = TRUE) {
    $this->dir = new DirectoryIterator($dirpath);
    $this->setTemplate(); // Set to default
    $this->verbose = $verbose;
    $this->dirpath = $dirpath;
    $this->setEdition();
  }

  // Maps US, F2 and F3 path components to values for $publication, $description and $edition
  protected function setEdition() {
    $p = realpath($this->dirpath);
    $this->publication = "The Federal Reporter";
    $this->description = "Decisions of the Federal Courts of Appeals";
    if (strpos($p, '/US') !== FALSE) {
      $this->publication = "United States Reports";
      $this->description = "Decisions of the United States Supreme Court";
    } else if (strpos($p, '/F2') !== FALSE) {
      $this->edition = '2d Edition';
    } else {
      $this->edition = '3d Edition';
    }
  }

  // Sets XHTML template $str. Should implement default to be used if $str not provided at runtime.
  abstract public function setTemplate($str = NULL);

  // Iterates over $dir and populates $items and $order
  abstract public function scanDir();

  // Iterates over $items using $order and builds $dom document
  abstract protected function build();

  // Saves $dom document as xhtml file $outputname
  public function save() {
    if (!$this->dom)
      $this->build();
    $f = $this->dirpath.'/'.$this->outputname;
    // Custom RDFa doctype since we're using the "about" attrib
    $str = $this->dom->saveXHTML(NULL, NULL, 
           '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML+RDFa 1.0//EN" '.
           '"http://www.w3.org/MarkUp/DTD/xhtml-rdfa-1.dtd">');

    file_put_contents($f, $str);
    
    if ($this->verbose)
      echo "Saved $f.\n";
  }

  // Helper method to trim and compress whitespace
  protected function trimString($str) {
    return trim(preg_replace('#[\n\s]+#', ' ', $str), ". \t\n\r\0\x0B");
  }

  // Returns 2-element array of date_parse arrays for range of items being indexed
  abstract public function getDateRange();

  // Returns string of date range, i.e. "Month, Year - Month, Year" or "Month-Month, Year"
  // This method would be 10x simpler if DateTime wasn't broken 
  public function getDateRangeString() {
    list($first, $last) = $this->getDateRange();
    if ($first and $last) {
      $fobj = new DateTime('00:00:00 GMT');
      $lobj = new DateTime('00:00:00 GMT');
      if ($first['year'] == $last['year']) {
        if ($first['month'] and $last['month']) {
          $fobj->setDate($first['year'], $first['month'], 1);
          $lobj->setDate($last['year'], $last['month'], 1);
        } else {
          return $first['year'];
        }
        if ($first['month'] == $last['month'])
          return $fobj->format("F, Y");
        else
          return $fobj->format("F").' - '.$lobj->format("F, Y");
      } else {
        if ($first['month']) {
          $fobj->setDate($first['year'], $first['month'], 1);
          $f = $fobj->format("F, Y");
        } else {
          $f = $first['year'];
        }
        if ($last['month']) {
          $lobj->setDate($last['year'], $last['month'], 1);
          $l = $lobj->format("F, Y");
        } else {
          $l = $last['year'];
        }
        return "$f - $l";
      }
    } else if ($first or $last) {
      if ($first)
        $d = $first;
      else
        $d = $last;
      if ($d['month']) {
        $dobj = new DateTime('00:00:00 GMT');
        $dobj->setDate($d['year'], $d['month'], 1);
        return $dobj->format("F, Y");
      } else
        return $d['year'];
    } else
      return '';
  }

}

?>
