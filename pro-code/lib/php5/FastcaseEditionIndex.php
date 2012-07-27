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

// Per-edition index (contains volumes)
class FastcaseEditionIndex extends FastcaseIndex
{
  public function setTemplate($str = NULL) {
    $this->template = $str;
    if (!$str)
      $this->template = <<<EOT
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">
  <head>
    <title>Publication</title>
    <link rel="stylesheet" type="text/css" href="http://bulk.resource.org/courts.gov/c/css/case.css"/>
    <link rel="stylesheet" type="text/css" href="http://bulk.resource.org/courts.gov/c/css/index.css"/>
    <link rel="stylesheet" type="text/css" href="http://bulk.resource.org/courts.gov/c/css/print.css" media="print"/>
  </head>

  <body>
    <p class="publication"><a href="../">Publication</a></p>
    <p class="edition">Nd Edition</p>
    <p class="desc">United States Appellate Court Decisions</p>

    <table class="filelist index">
      <tbody>
        <tr>
          <th class="volume">Volume</th>
          <th class="date">Dates</th>
        </tr>
      </tbody>
    </table>

  </body>
</html>
EOT;
  }

  public function scanDir() {
    foreach ($this->dir as $file) {
      // To save memory, we're not going to build a list of FastcaseCase objects,
      // but instead will just extract the metadata we need
      if ($file->isDir() and !$file->isDot()) {
        if ($this->verbose)
          echo "Indexing ".$file->getFilename()."... ";
        $vol = new FastcaseVolumeIndex($file->getPathname(), FALSE); // verbosity = FALSE
        $vol->scanDir();
        $vol->save();
        if ($this->verbose)
          echo "Indexed.\n";

        $a['file'] = $file->getFilename();
        $a['volume'] = $file->getFilename(); // These are the same value now, but we could change later

        if (is_numeric($a['volume']))
          $a['page'] = intval($a['volume']);
        else
          $a['page'] = 9999;
        $a['dateS'] = $vol->getDateRangeString(); // formatted string
        $a['dateA'] = $vol->getDateRange(); // array of 2 date_parse arrays (or string or NULL)

        $this->items[] = $a;
      }
    }

    // Sort $items
    foreach ($this->items as $a)
      $pages[] = $a['page'];
    asort($pages);

    $this->order = array_keys($pages);
  }

  protected function build() {
    $this->dom = new ProDOMDocument;
    $this->dom->preserveWhiteSpace = FALSE;
    $this->dom->formatOutput = TRUE;
    $this->dom->loadHTML(trim($this->template));

    foreach ($this->dom->getElementsByTagName('title') as $n)
      $n->nodeValue = $this->publication;

    $delete = array();
    foreach ($this->dom->getElementsByTagName('p') as $n) {
      switch ($n->getAttribute('class')) {
        case 'publication':
          $n->firstChild->nodeValue = $this->publication;
          break;
        case 'edition':
          if ($this->edition) {
            $n->nodeValue = $this->edition;
          } else {
            $delete[] = $n;
          }
          break;
        case 'desc':
          if ($dr = $this->getDateRangeString())
            $n->nodeValue = $this->description.", $dr";
          else
            $n->nodeValue = $this->description;
          break;
      }
    }
    foreach ($delete as $n)
      $n->parentNode->removeChild($n);

    // Should only be one tbody in template
    foreach ($this->dom->getElementsByTagName('tbody') as $n)
      $tbody = $n;
    foreach ($this->order as $key) {
      $a = $this->items[$key];

      $tr = $this->dom->quickElement('tr');
      $tbody->appendChild($tr);

      $td1 = $this->dom->quickElement('td', NULL, array('class' => 'volume'));
      $tr->appendChild($td1);
      $td2 = $this->dom->quickElement('td', NULL, array('class' => 'date'));
      $tr->appendChild($td2);

      $td1->appendChild($this->dom->quickElement('a', $a['volume'], array('href' => $a['file'])));
      $td2->appendChild($this->dom->quickElement('a', $a['dateS'], array('href' => $a['file'])));
    }

    $this->dom->appendFooter();
  }

  // Returns 2-element array of date_parse arrays
  public function getDateRange() {
    $first = NULL;
    $last = NULL;
    foreach ($this->order as $key) {
      $d = $this->cases[$key]['dateA'][0];
      if (is_array($d)) {
        $first = $d;
        break;
      }
    }
    foreach (array_reverse($this->order) as $key) {
      $d = $this->cases[$key]['dateA'][1];
      if (is_array($d)) {
        $last = $d;
        break;
      }
    }
    return array($first, $last);
  }

}

?>
