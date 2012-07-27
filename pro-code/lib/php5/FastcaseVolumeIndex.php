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

require_once('FastcaseIndex.php');

// Per-volume index (contains cases)
class FastcaseVolumeIndex extends FastcaseIndex
{
  protected $vol; // int, number of volume

  public function __construct($dirpath, $verbose = TRUE) {
    $this->setVolumeNumber(basename($dirpath));
    parent::__construct($dirpath, $verbose);
  }

  public function getVolumeNumber() {
    return $this->vol;
  }
  public function setVolumeNumber($vol) {
    $this->vol = intval($vol);
  }

  public function setTemplate($str = NULL) {
    $this->template = $str;
    if (!$str)
      $this->template = <<<EOT
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">
  <head>
    <title>Publication, Vol. 0</title>
    <link rel="stylesheet" type="text/css" href="http://bulk.resource.org/courts.gov/c/css/case.css"/>
    <link rel="stylesheet" type="text/css" href="http://bulk.resource.org/courts.gov/c/css/index.css"/>
    <link rel="stylesheet" type="text/css" href="http://bulk.resource.org/courts.gov/c/css/print.css" media="print"/>
  </head>

  <body>
    <p class="volume">Volume 000</p>
    <p class="publication"><a href="../">Publication</a></p>
    <p class="edition"><a href="../">Nd Edition</a></p>
    <p class="daterange">Month D, YYYY - Month D, YYYY</p>

    <table class="filelist index">
      <tbody>
        <tr>
          <th class="case_cite">Cite/Page</th>
          <th class="date">Date</th>
          <th class="hash">Hash of Approval<a class="footnote" href="#hash" id="hash_ref">*</a></th>
        </tr>
      </tbody>
    </table>

    <div class="footnotes">
      <div class="footnote">
        <p><a class="footnote" id="hash" href="#hash_ref">*</a> This is a SHA1 hash, computed for each file.</p>
      </div>
    </div>
  </body>
</html>
EOT;
  }

  public function scanDir() {
    $cites = array();
    foreach ($this->dir as $file) {
      // To save memory, we're not going to build a list of FastcaseCase objects,
      // but instead will just extract the metadata we need
      if ($file->isFile() and ($file->getFilename() != 'index.html') and 
          (strpos($file->getFilename(), '.html') !== FALSE)) {
        if ($this->verbose)
          echo "Loading ".$file->getFilename()."... ";
        $case = new FastcaseCase('1.0', 'utf-8');
        $case->loadProXHTML($file->getPathname());
        if ($this->verbose)
          echo "Loaded.\n";

        $a['file'] = $file->getFilename();
        if (preg_match('#.\.([0-9]+)#', $file->getFilename(), $matches))
          $a['page'] = intval($matches[1]);
        else
          $a['page'] = 9999;
        $a['hash'] = sha1_file($file->getPathname());
        $a['cite'] = $this->trimString($case->cleanCiteString($case->getMainCite()));
        $a['docketsnumeric'] = $case->getDocketsNumeric();
        $a['parties'] = $this->trimString($case->getValue('parties'));
        $a['dateS'] = $case->getFormattedDate(); // formatted string
        $a['dateA'] = $case->getSortedDate(); // date_parse array (or string or NULL)

        if (!in_array($a['cite'], $cites)) {
          $cites[] = $a['cite'];
          $this->items[] = $a;
        } else {
          $overlaps = FALSE;
          foreach ($this->items as $i) {
            if ($i['cite'] == $a['cite']) {
              if ($this->haveOverlap($i['docketsnumeric'], $a['docketsnumeric'])) {
                $overlaps = TRUE;
                break;
              }
            }
          }
          if (!$overlaps)
            $this->items[] = $a;
        }
      }
    }

    // Sort $items
    foreach ($this->items as $a)
      $pages[] = $a['page'];
    asort($pages);

    $this->order = array_keys($pages);
  }

  // Return true/false based on whether $a1 and $a2 are identical or are arrays with
  // any elements in common
  protected function haveOverlap($a1, $a2) {
    if ($a1 == $a2)
      return TRUE;
    else if (is_array($a1)) {
      foreach ($a1 as $one) {
        if (is_array($a2) and in_array($one, $a2))
            return TRUE;
        else if ($one == $a2)
          return TRUE;
      }
    } else if (is_array($a2))
      return $this->haveOverlap($a2, $a1);
    return FALSE;
  }

  protected function build() {
    $this->dom = new ProDOMDocument;
    $this->dom->preserveWhiteSpace = FALSE;
    $this->dom->formatOutput = TRUE;
    $this->dom->loadHTML(trim($this->template));

    foreach ($this->dom->getElementsByTagName('title') as $n)
      $n->nodeValue = $this->publication.", Vol. ".$this->vol;

    $delete = array();
    foreach ($this->dom->getElementsByTagName('p') as $n) {
      switch ($n->getAttribute('class')) {
        case 'volume':
          $n->nodeValue = "Volume ".$this->vol;
          break;
        case 'publication':
          $n->firstChild->nodeValue = $this->publication;
          break;
        case 'edition':
          if ($this->edition) {
            $n->firstChild->nodeValue = $this->edition;
          } else {
            $delete[] = $n;
          }
          break;
        case 'daterange':
          if ($dr = $this->getDateRangeString())
            $n->nodeValue = $dr;
          else
            $delete[] = $n;
      }
    }
    foreach ($delete as $n)
      $n->parentNode->removeChild($n);

    // Should only be one tbody in template
    foreach ($this->dom->getElementsByTagName('tbody') as $n)
      $tbody = $n;
    foreach ($this->order as $key) {
      $a = $this->items[$key];
      $urn = "urn:sha1:".$a['hash'];

      $tr = $this->dom->quickElement('tr', NULL, array('about' => $urn));
      $tbody->appendChild($tr);

      $td1 = $this->dom->quickElement('td', NULL, array('class' => 'case_cite'));
      $tr->appendChild($td1);
      $td2 = $this->dom->quickElement('td', NULL, array('class' => 'date'));
      $tr->appendChild($td2);
      $td3 = $this->dom->quickElement('td', NULL, array('class' => 'hash'));
      $tr->appendChild($td3);

      $td1->appendChild($this->dom->quickElement('a', $a['cite'], array('href' => $a['file'], 'title' => $a['parties'])));
      if ($a['dateS'])
        $td2->appendChild($this->dom->quickElement('a', $a['dateS'], array('href' => $a['file'], 'title' => $a['parties'])));
      $td3->appendChild($this->dom->quickElement('a', $a['hash'], array('about' => $urn, 'rel' => 'license', 
        'href' => 'http://labs.creativecommons.org/licenses/zero-assert/1.0/us/')));
    }

    $this->dom->appendFooter();
  }

  // Returns 2-element array of date_parse arrays
  public function getDateRange() {
    $first = NULL;
    $last = NULL;
    foreach ($this->order as $key) {
      $d = $this->items[$key]['dateA'];
      if (is_array($d)) {
        $first = $d;
        break;
      }
    }
    foreach (array_reverse($this->order) as $key) {
      $d = $this->items[$key]['dateA'];
      if (is_array($d)) {
        $last = $d;
        break;
      }
    }
    return array($first, $last);
  }

}

?>
