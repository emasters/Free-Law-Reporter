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

require_once('ProDOMDocument.php');
require_once('FootnoteParser.php');

// Main Lawcase import/export object, includes methods to 
// initialize from Fastcase HTML source and our ProXHTML (roundtrip)
class FastcaseCase extends ProDOMDocument
{
  public $raw_HTML = NULL; // raw Fastcase HTML
  public $cleaned_HTML = NULL; // Fastcase HTML after cleaning
  protected $nodes = array(); // various DOM nodes, like cites, parties, docket
  protected $parser; // FootnoteParser object
  protected $fcid; // Original Fastcase ID (filename with .html truncated)
  protected $dates; // Sorted array of date_parse arrays of dates in case, newest first
  protected $series = NULL; // string, either F2, F3 or US

  public function __construct($version = NULL, $encoding = NULL) {
    // DOM settings
    parent::__construct($version, $encoding);
    $this->preserveWhiteSpace = FALSE;
    $this->formatOutput = TRUE;
  }

  public function __destruct() {
    // explicitly unset parser to free its memory since it is instantiated from within this class
    unset($this->parser);
  }

  // Initialize from a Fastcase HTML file
  public function loadFCHTML($filepath) {
    $this->raw_HTML = file_get_contents($filepath);
    $this->setSeries($filepath);
    if (preg_match('#^[0-9]+#', basename($filepath), $matches))
      $this->fcid = $matches[0];
    $this->clean();
    ##echo "cleaned.\n";
    // $answer will be false if DOM loadHTML method fails
    ##file_put_contents('cleaned.html', $this->cleaned_HTML);
    $answer = $this->loadHTML($this->cleaned_HTML); // Generally doesn't thrown exceptions if there are errors, just segfaults
    ##echo "loaded? $answer\n";
    $this->buildCase();
    ##echo "built.\n";
  }

  // Initialize from a well-formed public.resource.org XHTML file
  public function loadProXHTML($filepath) {
    // Need to write this method to load in one of our XHTML files,
    // parse it to populate $nodes. This completes the round-trip.
    $answer = $this->loadHTML(file_get_contents($filepath)); // Generally doesn't thrown exceptions if there are errors, just segfaults
    ##echo "loaded? $answer\n";
    $this->setSeries($filepath);
    $ps = $this->getElementsByTagName('p');

    // case_name => name
    if ($m = $this->getNodesMatchingAttrib($ps, 'class', 'case_name'))
      $this->nodes['name'] = $m[0];

    // case_cite => cite
    if ($m = $this->getNodesMatchingAttrib($ps, 'class', 'citation'))
      $this->nodes['cite'] = $m;
    
    if ($m = $this->getNodesMatchingAttrib($ps, 'class', 'parties'))
      $this->nodes['parties'] = $m[0];

    if ($m = $this->getNodesMatchingAttrib($ps, 'class', 'docketnumber'))
      $this->nodes['docket'] = $m;
    
    if ($m = $this->getNodesMatchingAttrib($ps, 'class', 'courtname'))
      $this->nodes['court'] = $m;
    
    if ($m = $this->getNodesMatchingAttrib($ps, 'class', 'date'))
      $this->nodes['date'] = $m;

    // Determine dates of case, build $this->dates list
    $this->setSortedDates();
  }

  public function saveProXHTML() {
    $head = $this->documentElement->firstChild;

    // Set meta description tag
    // Date is RFC 1123 format, not that it matters, this isn't an HTTP header
    $head->firstChild->nextSibling->setAttribute(
      'content', 'Transformed by the Center for Computer-Assisted Legal Instruction for the Free Law Reporter from Public.Resource.Org, Inc. sources, at '.gmdate('D, d M Y H:i:s \G\M\T'));
    $this->appendFooter();

    // Insert meta date tag with actual date of case
    // Use ISO 8601 standard per http://www.w3.org/TR/html401/struct/global.html#h-7.4.4.3
    if (is_array($this->dates)) {
      foreach ($this->dates as $d) {
        if (is_array($d)) {
          $dobj = new DateTime('00:00:00 GMT');
          if ($d['month'] and $d['day'])
            $dobj->setDate($d['year'], $d['month'], $d['day']);
          else if ($d['month'])
            $dobj->setDate($d['year'], $d['month'], 1);
          else
            $dobj->setDate($d['year'], 12, 31);
          if ($d['year']) {
            $md = $this->createElement('meta');
            $md->setAttribute('name', 'decisiondate');
            $md->setAttribute('content', $dobj->format("c"));
            $head->insertBefore($md, $head->firstChild->nextSibling->nextSibling);
            break;
          }
        }
      }
    }
	// Adding meta tags for citation, parties, docket number, court
	if (is_array($this->getValue('cite'))){
	foreach($this->getValue('cite') as $mc){
	$mcite = $this->createElement('meta');
	$mcite->setAttribute('name', 'citation');
	$mcite->setAttribute('content', $mc);
	$head->insertBefore($mcite, $head->firstChild->nextSibling->nextSibling->nextSibling);
	}
	}
	if (is_array($this->getValue('parties'))){
		foreach($this->getValue('parties') as $mp){
			$mparties = $this->createElement('meta');
			$mparties->setAttribute('name', 'parties');
			$mparties->setAttribute('content', $mp);
			$head->insertBefore($mparties, $head->firstChild->nextSibling->nextSibling->nextSibling);
		} 
	} else {
		$mparties = $this->createElement('meta');
		$mparties->setAttribute('name', 'parties');
		$mparties->setAttribute('content', $this->getValue('parties'));
		$head->insertBefore($mparties, $head->firstChild->nextSibling->nextSibling->nextSibling);
	}
	if (is_array($this->getValue('docket'))){
		foreach($this->getValue('docket') as $md){
			$mdocket = $this->createElement('meta');
			$mdocket->setAttribute('name', 'docketnumber');
			$mdocket->setAttribute('content', $md);
			$head->insertBefore($mdocket, $head->firstChild->nextSibling->nextSibling->nextSibling->nextSibling);
		} 
	} else {
		$mdocket = $this->createElement('meta');
		$mdocket->setAttribute('name', 'docketnumber');
		$mdocket->setAttribute('content', $this->getValue('docket'));
		$head->insertBefore($mdocket, $head->firstChild->nextSibling->nextSibling->nextSibling->nextSibling);
	}
	if (is_array($this->getValue('court'))){
		foreach($this->getValue('court') as $mct){
			$mcourt = $this->createElement('meta');
			$mcourt->setAttribute('name', 'courtname');
			$mcourt->setAttribute('content', $mct);
			$head->insertBefore($mcourt, $head->firstChild->nextSibling->nextSibling->nextSibling->nextSibling->nextSibling);
		} 
	} else {
		$mcourt = $this->createElement('meta');
		$mcourt->setAttribute('name', 'courtname');
		$mcourt->setAttribute('content', $this->getValue('court'));
		$head->insertBefore($mcourt, $head->firstChild->nextSibling->nextSibling->nextSibling->nextSibling->nextSibling);
	}
	$mcollection = $this->createElement('meta');
        $mcollection->setAttribute('name','collection');
        $mcollection->setAttribute('content','PRO Federal Cases 2008');
        $head->insertBefore($mcollection, $head->firstChild->nextSibling->nextSibling->nextSibling->nextSibling->nextSibling->nextSibling);
   
    // Insert XML comment with Fastcase ID as FC:value
    $fcid = $this->createComment("FC:".$this->fcid);
    $head->appendChild($fcid);

    // Render and return XHTML
    return $this->saveXHTML();
  }

  // Returns most recent date of case's dates in clean "Month D, YYYY" format
  // (unless of course when only month and year, or only year are available)
  public function getFormattedDate() {
    $d = $this->getSortedDate();
    if (!is_array($d))
      return $d;
    else {
      $dobj = new DateTime('00:00:00 GMT');
      if ($d['month'] and $d['day']) {
        $dobj->setDate($d['year'], $d['month'], $d['day']);
        return $dobj->format("F j, Y");
      } else if ($d['month']) {
        $dobj->setDate($d['year'], $d['month'], 1);
        return $dobj->format("F, Y");
      } else {
        $dobj->setDate($d['year'], 1, 1);
        return $dobj->format("F");
      }
    }
  }

  // Returns sorted array of case's dates (most recent date first) in clean "Month D, YYYY" format
  // (unless of course when only month and year, or only year are available)
  public function getFormattedDates() {
    $dates = array();
    foreach ($this->dates as $d) {
      if (!is_array($d))
        $dates[] = $d;
      else {
        $dobj = new DateTime('00:00:00 GMT');
        if ($d['month'] and $d['day']) {
          $dobj->setDate($d['year'], $d['month'], $d['day']);
          $dates[] = $dobj->format("F j, Y");
        } else if ($d['month']) {
          $dobj->setDate($d['year'], $d['month'], 1);
          $dates[] = $dobj->format("F, Y");
        } else {
          $dobj->setDate($d['year'], 1, 1);
          $dates[] = $dobj->format("F");
        }
      }
    }
  }

  // Returns most recent date of date's cases that is available as a date_parse array.
  // If no date could be parsed into an array, the most recent available string date or NULL is returned
  public function getSortedDate() {
    foreach ($this->dates as $d) {
      if (is_array($d))
        return $d;
    }
    if (count($this->dates) > 0)
      return $this->dates[0];
    else
      return NULL;
  }

  // Returns sorted array of case's dates (most recent date first) as date_parse arrays
  // Dates that could not be parsed are left as strings.
  public function getSortedDates() {
    return $this->dates;
  }

  // Set $series to F2, F3 or US based on $filepath
  protected function setSeries($filepath) {
    $p = realpath($filepath);
    if (strpos($p, '/F2') !== FALSE)
      $this->series = 'F2';
    else if (strpos($p, '/US') !== FALSE)
      $this->series = 'US';
    else
      $this->series = 'F3';
  }

  // Build $this->dates from dates in DOM. Dates that cannot be parsed are left as strings.
  protected function setSortedDates() {
    $dates = $this->getValue('date');
    if (!is_array($dates)) {
      //$dates should always be an array, so this should always be FALSE, but check just in case
      if ($dates)
        $dates = array($dates);
      else
        $dates = array();
    }
    // Do some basic text cleanup first. These versions of the date strings will 
    // also be our fallback, if we cannot parse any dates.
    $strdates = array();
    foreach ($dates as $d) {
      ##$d = preg_replace('#[\[\(<\n].*$#', '', $d));
      // Compress all whitespace to single spaces only
      $d = trim(preg_replace('#[\n\s]+#', ' ', $d), ". \t\n\r\0\x0B");
      // There may be multiple dates, if so, split them out
      if (preg_match_all('#(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\S*(?: \w+)? (?:[0-9]+,? )+(?:and )?[1-2][0-9]{3}#i', $d, $matches)) {
        $ds = array_map(array($this, 'cleanDateString'), $matches[0]);
      } else if (preg_match_all('#[0-9]+ (?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\S*,? [1-2][0-9]{3}#i', $d, $matches)) {
        $ds = array_map(array($this, 'cleanDateString'), $matches[0]);
      } else if (preg_match_all('#[0-9]{1,2}[/-][0-9]{1,2}[/-][0-9]{1,4]#', $d, $matches)) {
        $ds = array_map(array($this, 'cleanDateString'), $matches[0]);
      } else {
        $ds = array($this->cleanDateString($d));
      }
      $strdates = array_merge($strdates, $ds);;
    }

    // Fancy new PHP DateTime object is broken, doesn't do comparisons and doesn't work
    // with partial dates (i.e. Year and Month but no Day), so we build our own 
    // assoc array of date_parse arrays with YYYYMMDD as key that we can sort.
    $dates = array();
    foreach ($strdates as $d) {
      if ($a = date_parse($d)) {
        // Year only and Year+Month only, the Year is misidentified as the time
        if ($a['hour'] and !$a['year'])
          $a['year'] = $a['hour'] * 100 + $a['minute'];
        // Require that things be sane, for instance "April 15, 16, 1908" makes year=2016
        if ($a['year'] < date("Y")) {
          // Format date string
          if (!$a['year'])
            $dates[] = $d; // Got nothing, just use string we started with
          else {
            $d = array('year' => $a['year'], 'month' => $a['month'], 'day' => $a['day']);
            if ($d['month'] and $d['day'])
              $dates[sprintf("%d%02d%02d", $d['year'], $d['month'], $d['day'])] = $d;
            else if ($d['month'])
              $dates[sprintf("%d%02d00", $d['year'], $d['month'])] = $d;
            else
              $dates[sprintf("%d0000", $d['year'])] = $d;
          }
        } else {
          $dates[] = $d; // Got nothing, just use string we started with
        }
      } else {
        $dates[] = $d; // Got nothing, just use string we started with
      }
    }
    // Sort by key YYYYMMDD in reverse order so newest date is first
    krsort($dates);

    $this->dates = array_values($dates);
  }

  // Do text cleanup on date string for usage in listings etc.
  public function cleanDateString($d) {
    /* old stuff
    // If match date pattern, strip any text after it
    if (preg_match('#^.*\S{2}.*[0-9]+[^0-9]{1,3}[0-9]{2,4}#', $d, $matches, PREG_OFFSET_CAPTURE))
      $d = substr($d, $matches[0][1], strlen($matches[0][0]));
     */
    // Strip off anything trailing YYYY
    if (preg_match('#[12][0-9]{3}#', $d, $matches, PREG_OFFSET_CAPTURE))
      $d = substr($d, 0, $matches[0][1] + strlen($matches[0][0]));
    // 20th Century jackasses! I guess they figured the Soviets would get us before 2000 rolled around
    $d = preg_replace('#([0-9]{1,2})[/-]([0-9]{1,2})[/-]([0-9]{2})$#', "$1/$2/19$3", $d); 
    // Strip words ending in ed
    $d = preg_replace('#[a-z]{2,}ed#i', '', $d);
    // If there is a chain of dates, keep last (i.e. Jan. 1, 2 and 3 => Jan. 3)
    $d = preg_replace('#(?:[0-9]+,? )+(?:and )?([0-9]{1,2}[, ])#i', "$1", $d);
    // Strip a bunch of random word fragments that show up often in dates
    $d = preg_replace('#(?:(?:[a-z]+ )?and| term|heard|on |:|page [0-9]+|rehearing)#i', '', $d);
    $s = array('#Jan.?\s#i', '#Feb.?\s#i', '#Mar.?\s#i', '#Apr.?\s#i', '#May.?\s#i', '#Jun.?\s#i', 
               '#Jul.?\s#i', '#Aug.?\s#i', '#Sept?.?\s#i', '#Oct.?\s#i', '#Nov.?\s#i', '#Dec.?\s#i');
    $r = array('January ', 'February ', 'March ', 'April ', 'May ', 'June ', 'July ', 
               'August ', 'September ', 'October ', 'November ', 'December ');
    $d = preg_replace($s, $r, $d);
    return trim($d, "][. \t\n\r\0\x0B");
  }

  // Do text cleanup on cite string for usage in filenames, listings etc.
  public function cleanCiteString($c) {
    // Deal with extra spaces: U. S. and F. 2/3
    $c = preg_replace('#([UF])\.\s+([S123])#i', "$1.$2", $c);
    if (preg_match('#[0-9]+\s+\w\S+\s+[0-9]+#', $c, $matches))
      return trim(preg_replace('#\s+#', ' ', $matches[0]));
    else if (preg_match('#^\s*[^\(\[,<\n]+#', $c, $matches))
      return trim(preg_replace('#\s+#', ' ', $matches[0]));
    else
      return NULL;
  }

  // Return first U.S. or F. citation in cites
  public function getMainCite() {
    $cites = $this->getCite();
    if (isset($this->series))
      $first_letter = substr($this->series, 0, 1);
    else
      $first_letter = '[uf]';
    if (is_array($cites)) {
      foreach ($cites as $c) {
        $c = $c->firstChild->nodeValue;
        if (preg_match('#^\s*[0-9]+\s*'.$first_letter.'\.#i', $c)) {
          $cite = $c;
          break;
        }
      }
      if (!isset($cite))
        $cite = $cites[0]->firstChild->nodeValue;
      return $cite;
    } else
      return NULL;
  }

  // Return array of numeric portions of dockets (dashes also allowed), i.e.:
  //   array('44-555', 121, '2999')
  public function getDocketsNumeric() {
    $dockets = $this->getValue('docket');
    if (is_array($dockets)) {
      $nums = array();
      foreach ($dockets as $d) {
        $d = str_replace("\xE2\x80\x94", '-', $d);
        if (preg_match_all('#[0-9\-]+#', $d, $matches)) {
          foreach ($matches[0] as $num)
            $nums[] = $num;
          // alternately we could get just the first or last docket number,
          // if we can figure out which one is always most significant
          //break;
        }
      }
      $dockets = array_unique($nums);
      sort($dockets, SORT_NUMERIC);
    }
    return $dockets;
  }

  // Return node for a node key such as 'cite'. If node key points to an array
  //  (for instance if there are multiple cites), then return array of nodes.
  public function getNode($key) {
    return $this->nodes[$key];
  }

  // Return value for a node key such as 'cite'. If node key points to an array
  //  (for instance if there are multiple cites), then return array of values.
  public function getValue($key) {
    $node = $this->getNode($key);
    list($value) = $this->getValues(array($node));
    return $value;
  }

  // Get all the data in $this->nodes, except as values like "210 F.3d 79",
  // not node references. Returns multi-dimensional associative array that
  // parallels $this->nodes
  public function getValues($nodes = NULL) {
    if ($nodes == NULL)
      $nodes = $this->nodes;
    foreach ($nodes as $key => $node) {
      if (is_array($node))
        $values[$key] = $this->getValues($node);
      else {
        if (!is_object($node))
          $values[$key] = NULL;
        else if (!$node->hasChildNodes())
          $values[$key] = trim($node->nodeValue);
        else {
          $fc = $node->firstChild;
          $values[$key] = trim($fc->nodeValue);
          while ($fc = $fc->nextSibling)
            $values[$key] .= ' '.trim($fc->nodeValue);
        }
      }
    }
    return $values;
  }

  // Magic method. Maps:
  //   getX => getNode('x'), i.e. getDocket => getNode('docket')
  public function __call($method, $args) {
    $nameparts = preg_split('#([A-Z][a-zA-Z0-9]+)#', $method, 2, PREG_SPLIT_DELIM_CAPTURE);
    if ($nameparts[0] == 'get')
      return call_user_func_array(array($this, getNode), array(strtolower($nameparts[1])));
  }

  public function addCite($node) {
    $node->setAttribute('class', 'citation');
    $this->nodes['cite'][] = $node;
    // Insert first cite into page <title></title>
    if ($c = $this->cleanCiteString($this->getMainCite()))
      $this->documentElement->firstChild->firstChild->nodeValue = $c;
  }

  public function addParties($node) {
    if ($existing = $this->getParties()) {
      // Merge nodes
      $existing->appendChild($this->createElement('br'));
      while ($node->hasChildNodes())
        $existing->appendChild($node->firstChild);
      $node->parentNode->removeChild($node);
    } else {
      $node->setAttribute('class', 'parties');
      $this->nodes['parties'] = $node;
    }
  }

  public function addDocket($node) {
    $node->setAttribute('class', 'docketnumber');
    $this->nodes['docket'][] = $node;
  }

  public function addCourt($node) {
    $node->setAttribute('class', 'courtname');
    $this->nodes['court'][] = $node;
  }

  public function addDate($node) {
    $node->setAttribute('class', 'date');
    $this->nodes['date'][] = $node;
  }

  public function setPrelims($firstnode, $lastnode) {
    // Create prelims node
    $prelims = $this->createElement('div');
    $prelims->setAttribute('class', 'prelims');
    $firstnode->parentNode->insertBefore($prelims, $firstnode);
    $this->nodes['prelims'] = $prelims;

    // Move paragraphs into $prelims
    $lastmoved = NULL;
    while ($lastmoved !== $lastnode) {
      $lastmoved = $prelims->nextSibling;
      $prelims->appendChild($lastmoved);
    }
    return $prelims;
  }

  // Helper method to take an array of $nodes, and return an array of nodes 
  // where the attribute $attrib == $value, or NULL if there are no matches
  protected function getNodesMatchingAttrib($nodes, $attrib, $value) {
    foreach ($nodes as $node) {
      if ($node->getAttribute($attrib) == $value)
        $return[] = $node;
    }
    return $return;
  }

  // Tranforms DOM tree created from cleaned_HTML into our XHTML structure,
  // populates $this->nodes
  protected function buildCase() {
    $body = $this->documentElement->firstChild->nextSibling;

    // First, make sure there are no plain text nodes at top level. Wrap them with <p>
    $this->wrapTextNodes($body, 'p');

    // Remove any commment nodes
    $this->stripCommentNodes();

    $next = $body->firstChild;
    if ($next and ($next->nodeType != 1))
      $next = $this->nextElement($next);

    // In the following sections, regex is assumed to be imperfect at best.
    // Each section is also assumed to be contiguous (i.e. there may be several dockets
    // or dates, but all the dockets come in a row, all the dates come in a row).
    //  - Cite is mandatory, somewhat easy to match with regex, and assumed to come first
    //  - Parties is mandatory but hard to regex match, 
    //    so isParties() is not reliable for initial discovery
    //  - Dockets are optional but possible to regex match
    //  - Courts are optional but possible to regex match
    //  (at minimum we expect either Court or Docket to show up, unless there is
    //   really nothing between Parties and dates)
    //  - At least one date should appear and is possible to regex match

    while ($next and $this->isCite($next)) {
      $this->addCite($next);
      $next = $this->nextElement($next);
    }

    if ($next) {
      $this->addParties($next);
      $next = $this->nextElement($next);
    }

    // Identify additional parties grafs (if any)
    while ( $next and 
            ( (!$this->isDocket($next) and !$this->isCourt($next) and !$this->isDate($next)) or
               ($this->isCourt($next) and
                ($this->isDocket($this->nextElement($next)) or 
                 $this->isDocket($this->nextElement($this->nextElement($next))))
               )
            ) ) {
      $afternext = $this->nextElement($next);
      $this->addParties($next);
      $next = $afternext;
    }

    // Identify dockets (if any)
    while ($next and $this->isDocket($next)) {
      $this->addDocket($next);
      $next = $this->nextElement($next);
    }

    // Identify courts (if any)
    while ($next and $this->isCourt($next)) {
      $this->addCourt($next);
      $next = $this->nextElement($next);
    }

    // Identify dates (if any)
    while ($next and $this->isDate($next)) {
      $this->addDate($next);
      $next = $this->nextElement($next);
    }

    // There are cases w/o a body, i.e. 
    // For these, skip further parsing, we're done.
    if ($next) {
      // Identify prelims
      $firstprelim = $next;
      $foundone = FALSE;
      $gap = 0;
      $counter = 0;
      while ($gap < 4 and $next->nextSibling) {
        if ($foundone or $counter > 40) // Searches max of 40 grafs for prelims
          $gap++;
        if ($this->isPrelim($next)) {
          $lastprelim = $next;
          $foundone = TRUE;
          $gap = 0;
          if ($this->isFinalJudgeLine($next))
            break;
        }
        if ($n = $this->nextElement($next)) {
          if ($this->isOpinion($n)) {
            $lastprelim = $next;
            $foundone = TRUE;
            break;
          }
        }

        $next = $this->nextElement($next);
        $counter++;
      }

      // Parse paragraphs and identify numbered paragraphs and footnote
      // Makes all footnotes and references <a class="footnotes">X</a>
      $this->parser = new FootnoteParser($this, $firstprelim);
      $this->parser->parse();
      $firstprelim = $this->parser->getFirstNode();

      // Put all numbered paragraphs into divs, all footnotes into divs. Wraps prelims in div.
      if ($foundone) {
        $next = $this->nextElement($lastprelim);
      } else {
        $next = $firstprelim;
      }
      $this->formatParagraphs($firstprelim, $next);

      /* Actually, there *are* recursive footnotes (footnotes within footnotes) in some cases!
       * So, we can't take this safety step. An example case is US/1012808397.html or 14 U.S. 46.
       * I don't know what the author was thinking. Instances of this seem to be all in old SCOTUS
       * decisions with alphabetic footnotes. Footnotes and references are also out of order in 
       * some of these cases (i.e. a footnote comes before the text it refers to.)
      // Change any <a> tags inside footnote <p>s back to <sup>
      foreach ($this->getElementsByTagName('a') as $a) {
        if ($a->parentNode->parentNode->getAttribute('class') == 'footnote')
          $this->parser->footnote2Super($a);
      }
      */

      // Link footnotes and references together
      $this->linkFootnotes();
    }

    // Change remaining unlinked <a> tags back to <sup>, unless they're in footnotes
    $as = array();
    foreach ($this->getElementsByTagName('a') as $a) {
      if ($a->getAttribute('class') == 'footnote' and 
          $a->parentNode->parentNode->getAttribute('class') != 'footnotes' and
          (!$a->hasAttribute('href') or strlen($a->getAttribute('href')) == 0)) {
        $as[] = $a;
      }
    }
    if ($this->parser) {
      foreach ($as as $a)
        $this->parser->footnote2Super($a);
    }

    // Determine dates of case, build $this->dates list
    $this->setSortedDates();
  }

  // Link footnotes and references together
  // Iterates over notes (at bottom of text) rather than refs (throughout text),
  // otherwise other superscripted things can be mistaken for footnote refs.
  protected function linkFootnotes() {
    $alla = $this->getElementsByTagName('a');
    foreach ($alla as $a) {
      if ($a->parentNode->getAttribute('class') == 'footnote')
        $notes[$a->nodeValue][] = $a;
      else if ($a->getAttribute('class') == 'footnote')
        $refs[$a->nodeValue][] = $a;
    }
    if (is_array($refs) and is_array($notes)) {
      foreach ($notes as $num => $note) {
        $snum = str_replace('*', '-s', $num); // "safe" version of num to use in HTML attribs
        foreach ($note as $anote) {
          if (is_array($refs[$num]) and ($aref = array_shift($refs[$num]))) {
            $counter = count($notes[$num]) - count($refs[$num]) - 1;
            if ($counter == 0)
              $counter = '';
            else
              $counter = "-$counter";
            $aref->setAttribute('href', "#fn$snum$counter");
            $aref->setAttribute('id', "fn$snum$counter".'_ref');
            $anote->setAttribute('href', "#fn$snum$counter".'_ref');
            $anote->parentNode->setAttribute('id', "fn$snum$counter");
            // delete name attribs if they exist
            $aref->removeAttribute('name');
            $anote->removeAttribute('name');
          }
        }
      }
    }
  }

  // Parse paragraphs, insert number divs for regular paragraphs and footnote divs for footnotes
  // Identify blockquotes? Not for now, Fastcase source does not mark them.
  // $node is the first paragraph node to parse, $firstnumbered is the first numbered node (after prelims)
  // (If there are no prelims, $node and $firstnumbered will be the same node)
  protected function formatParagraphs($node, $firstnumbered) {
    $fnsection = NULL; // container <div class="footnotes"> for blocks of footnotes
    $numbering = FALSE;
    $haveprelims = FALSE;
    $lastbefore = $node->previousSibling;

    while ($node) {
      $next = $node->nextSibling;

      if ($node === $firstnumbered) {
        $numbering = TRUE;
        $firstprelim = $lastbefore->nextSibling; // i.e., original value of $node or whatever div replaced it
        if ($firstprelim !== $node) {
          $lastprelim = $node->previousSibling;
          $haveprelims = TRUE;
        }
      }
      if ($this->parser->isFootnote($node)) {

        // Sections of footnotes go into <div class="footnotes">
        if (!$fnsection) {
          $fnsection = $this->createElement('div');
          $fnsection->setAttribute('class', 'footnotes');
          $node->parentNode->insertBefore($fnsection, $node);
        }
        // Each footnote goes into <div class="footnote">
        if ($this->parser->hasFootnoteRefTag($node) or !$fnsection->hasChildNodes()) {
          // New footnote, so add <div class="footnote">
          $div = $this->createElement('div');
          $div->setAttribute('class', 'footnote');
          $fnsection->appendChild($div);
        } else {
          // Additional footnote text, append to previous <div class="footnote">
          $div = $fnsection->lastChild;
        }
        // Move footnote reference <a> and footnote <p> into div
        if ($this->parser->hasFootnoteRefTag($node))
          $div->appendChild($node->firstChild);
        $div->appendChild($node);
      } else {
        if ($numbering) {
          // Add numbered paragraph
          $fnsection = NULL;
          $this->addNumberedParagraph($node);
        }
      }

      $node = $next;
    }
    if ($haveprelims)
      $this->setPrelims($firstprelim, $lastprelim);
  }

  // Insert a main body paragraph. Number it unless it is a section heading.
  protected function addNumberedParagraph($node) {
    if ($node->getAttribute('class') != 'center' and 
        !preg_match('#^\s*[A-Z0-9]{1,5}\.?(?:[^\n<]{1,40}[\w0-9:\)]$|$)#', $node->nodeValue)) {
      if (isset($this->nodes['num']))
        $num = count($this->nodes['num']) + 1;
      else
        $num = 1;
      // Create container div
      $div = $this->createElement('div');
      $div->setAttribute('class', 'num');
      $div->setAttribute('id', "p$num");
      $node->parentNode->insertBefore($div, $node);
      $this->nodes['num'][$num] = $div;
      // Create span for paragraph number
      $span = $this->createElement('span');
      $span->setAttribute('class', 'num');
      $span->nodeValue = $num;
      $div->appendChild($span);
      // Move $node into div
      $div->appendChild($node);
    }
  }

  // Return TRUE/FALSE if node contains a "X, Circuit Judge:" "Justice KENNEDY:" "X, J."
  // line possibly indicating the end of the prelims section and beginning of opinion
  protected function isPrelim($node) {
    if (!$node)
      return FALSE;
    $s = trim(strip_tags($node->nodeValue));
    if (preg_match('#(?:, (?:senior |chief )?(?:circuit )?judges?[:\.]|, (C\. )?J[:\.]|PER CURIAM[:\.]|justice [a-z\']{3,}).{0,160}$#i', $s) or
        preg_match('#for (?:respondent|petitioner)s?(?: in[^\n\.]{3,20})?\.?$#i', $s) or
        preg_match('#(?:vacat|remand)ed\.?$#i', $s)
       ) {
      if (!preg_match('#(?:Absent|Statement)\S*(?:\s+\S+){1,5}\s*$#i', $s))
        return TRUE;
    }
    return FALSE;
  }

  // Return TRUE if $node is definitive "the opinion has started" line
  protected function isOpinion($node) {
    if (!$node)
      return FALSE;
    return preg_match('#Denied\.$#', $node->nodeValue);
  }

  // Return TRUE if $node is definitive "the opinion is about to start" line
  protected function isFinalJudgeLine($node) {
    if (!$node)
      return FALSE;
    $s = strip_tags($node->nodeValue);
    return preg_match('#(?:delivered the opinion of the court|PER CURIAM)[:\.$]#i', $s);
  }

  // Return TRUE/FALSE if node is the parties
  protected function isParties($node) {
    if (!$node)
      return FALSE;
    if ($node->getAttribute('class') == 'parties')
      return TRUE;
    // If parties are split into multiple grafs, regex will probably fail
    return preg_match('#\S{3,}.*vs?\.?(?:\s|<br).*\S{3,}#i', $node->nodeValue);
  }

  // Return TRUE/FALSE if node is a court
  protected function isCourt($node) {
    if (!$node)
      return FALSE;
    if ($node->getAttribute('class') == 'court')
      return TRUE;
    if ($node->getAttribute('class') != 'indent') {
      #if (preg_match('#(?:United States|U\.S\.).+Court of#i', $node->nodeValue) or
      if (preg_match('#Court of#i', $node->nodeValue) or
          preg_match('#Supreme Court#i', $node->nodeValue) or
          preg_match('#Circuit Court#i', $node->nodeValue) or
          preg_match('#Common Pleas of#i', $node->nodeValue))
        return TRUE;
    }
    // Looser patterns for appending Court fragments that follow first Court line
    if ($node->previousSibling and $this->isCourt($node->previousSibling)) {
      if (preg_match('#Court|Circuit|Pleas#i', $node->nodeValue) and
          $node->getAttribute('class') != 'indent' and
          strlen($node->nodeValue) < 50)
        return TRUE;
    }
    return FALSE;
  }

  // Return TRUE/FALSE if node is a date
  protected function isDate($node) {
    if (!$node)
      return FALSE;
    if ($node->getAttribute('class') == 'date')
      return TRUE;
    if ($node->getAttribute('class') != 'indent') {
      if (preg_match('#\S{3,}.*\s[0-9]{4}#', $node->nodeValue) or
          preg_match('#[0-9]+/[0-9]+/[0-9]{2,}#', $node->nodeValue)) {
        // If it looks like a cite or certain other text, it isn't a date. But if it's date and a cite, then it's a date.
#        if ((!preg_match('#[0-9].*\s*(?:F\.[0-9]|U\.S)\S+\s*[0-9]#i', $node->nodeValue) and
#             !preg_match('#^(?:[^0-9\s]+\s+){4,}#i', $node->nodeValue)) or
#             preg_match('#[a-z]+\.? +[0-9]+, +[0-9]{4}|[0-9]+/[0-9]+/[0-9]{2,}#i', $node->nodeValue))
        if (!preg_match('#[0-9].*\s*(?:F\.[0-9]|U\.S|S\.Ct|\w+\.\w+)\S+\s*[0-9]#i', $node->nodeValue) or
             preg_match('#[a-z]+\.? +[0-9]+, +[0-9]{4}|[0-9]+/[0-9]+/[0-9]{2,}#i', $node->nodeValue))
          return TRUE;
      }
    }
    return FALSE;
  }

  // Return TRUE/FALSE if node is a docket
  protected function isDocket($node) {
    if (!$node)
      return FALSE;
    if ($node->getAttribute('class') == 'docket')
      return TRUE;
    if ($node->getAttribute('class') != 'indent') {
      if (preg_match('#(?:Docket|Misc|Customs Appeal|Nos?)\.?\s+[0-9_].*#i', $node->nodeValue) or
        preg_match('#[0-9]{2,}-[0-9]#', $node->nodeValue)) {
        if (!preg_match('#(?:v\.|et al\.|, Appellant|IN RE[\. ])#i', $node->nodeValue))
          return TRUE;
      }
    }
    return FALSE;
  }

  // Return TRUE/FALSE if node is a cite. This is a pretty loose regex, should only
  // be used for IDing cites at the beginning of cases.
  protected function isCite($node) {
    if (!$node)
      return FALSE;
    if ($node->getAttribute('class') == 'case_cite')
      return TRUE;
    if (preg_match('#[0-9]+.{0,3}\s.{0,6}\w\.? ?\S.{0,5}\s[0-9]#', $node->nodeValue) or
        preg_match('#(?:L\. Rep\.|Bus\.Disp\.Guide) [0-9]#i', $node->nodeValue) or 
        preg_match('#^[0-9]+.{0,20}\w.{0,20}[0-9]$#', $node->nodeValue) )
      return TRUE;
    return FALSE;
  }

  protected function clean() {
    // Strip page breaks, CRs from raw_HTML
    $search = array(chr(12), chr(13));
    $replace = array('', '');
    $this->cleaned_HTML = str_replace($search, $replace, $this->raw_HTML);
    $this->fixEntities();
//    $search = array('&#34;', '&#160;');
//    $replace = array('"', '&nbsp;');
//    $this->cleaned_HTML = str_replace($search, $replace, $this->cleaned_HTML);

    $this->sanitizeBody(); // Put markup inside simple <html><body></body></html> wrapper
    $this->removeBoldUBadTags(); // Remove <b> tags
    $this->closeBrTags(); // <br> => <br />

    // Some cases use <sup> in a block-level context to flag sections of footnotes, instead of small
    $this->cleaned_HTML = preg_replace('#(\n\s*|[^\n<>]{6}|</i>[^\n<>]*)</sup>\s*\n(?!</p)#i', "$1</p>\n</small>\n", $this->cleaned_HTML);
    $this->cleaned_HTML = preg_replace('#(\n\s*|[^\n<>]{6}|</i>[^\n<>]*)<sup>(\s*\n|[^\n<>]{6,}|[^\n<>]*<i>)#i', "$1</p>\n<small>\n<p>$2\n", $this->cleaned_HTML);
    $this->cleaned_HTML = preg_replace('#(\n\s*|[^\n<>]{6}|</i>[^\n<>]*)<(/?)sup>(\s*\n|[^\n<>]{6,}|[^\n<>]*<i>)#i', "\n$1<$2small>$3\n", $this->cleaned_HTML);

    // Some Fastcase source docs contain no <p> at all, only <br><br> combos. Paragraphize.
    $this->paragraphizePlain();

    // Sanitize paragraphs
    $this->setIndentAttribs(); // ID/standardize indented grafs
    $this->closeParagraphs(); // close paragraphs with </p>
    $this->setCenterAttribs(); // ID/standardize centered grafs
    $this->removeNestedPs(); // Remove double-nested paragraphs
    $this->removeNestedPs(); // Again in case of triple-nesting
    
    //Remove <p>Page ##</p>
    //These are not really useful in our brave new world
    //$this->cleaned_HTML = preg_replace('#<p>Page [0-9]</p>',"", this->cleaned_HTML);
    $this->cleaned_HTML = preg_replace('#<p>Page [0-9]</p>#i', '', $this->cleaned_HTML);
    
    // A few cases use single grafs for some/all of the case headers: parties, docket, court, dates
    // with only single <br> separators, i.e. F3/232938697.html
    // Use *strict* regex to repair at least some of these
    $this->cleaned_HTML = preg_replace('#([^\s>])(\s*\n?<br />\n?)([\w\.\s]{0,10}Nos?\.? [0-9]+(?:\-[0-9a-z\-]+,? ?)?\.?)(\s*\n?<br />\n?)([\w\s]{0,25}Court of Appeals(?:,<br />\n?)?[^\n]{4,15} Circuit\.?)(\n?<br />\n?\s*)([^\s<])#i', "$1</p>\n<p>$3</p>\n<p>$5</p>\n<p>$7", $this->cleaned_HTML);
    $this->cleaned_HTML = preg_replace('#([^\s>])(\s*\n?<br />\n?)([\w\s]{0,25}Court of Appeals(?:,<br />\n?)?[^\n]{4,15} Circuit\.?)(\n?<br />\n?\s*)([^\s<])#i', "$1</p>\n<p>$3</p>\n<p>$5", $this->cleaned_HTML);
    $this->cleaned_HTML = preg_replace('#(<p[^>]*>[\s\n]*)([\w\s]{0,25}Court of Appeals(?:,<br />\n?)?[^\n]{4,15} Circuit\.?)(\n?<br />\n?\s*)([^\s<])#i', "$1$2</p>\n<p>$4", $this->cleaned_HTML);
    $this->cleaned_HTML = preg_replace('#(<p[^>]*>[\s\n]*)([\w\s]{0,15}Supreme Court[\w\s]{0,25}\.?)(\n?<br />\n?\s*)([^\s<])#i', "$1$2</p>\n<p>$4", $this->cleaned_HTML);
    $this->cleaned_HTML = preg_replace('#([^\s>])(\s*\n?<br />\n?)([\w\.\s]{0,10}Nos?\.? [0-9]+(?:\-[0-9a-z\-]+,? ?)?\.?)\n?</p>\n?\s*#i', "$1</p>\n<p>$3</p>\n", $this->cleaned_HTML);

    // Cosmetic only, remove whitespace just before </p(re)> and after <p(re)>
    $this->cleaned_HTML = preg_replace('#[\s\n]+</p(re)?>#i', "</p$1>\n", $this->cleaned_HTML);
    $this->cleaned_HTML = preg_replace('#<pre>\s*\n+#i', '<pre>', $this->cleaned_HTML);
    $this->cleaned_HTML = preg_replace('#<p( [^>]*|)>(?:<br[^>]*>|[\s\n])+#i', "<p$1>", $this->cleaned_HTML);

    // Fix repeated tags except for <br>
    $this->cleaned_HTML = preg_replace('#(<(?!br)[a-z][^>]*>)((?:<[a-z][^>]*>|[\s\n])*)\1#i', "$2$1", $this->cleaned_HTML);
    $this->cleaned_HTML = preg_replace('#(</[a-z][^>]*>)((?:</[a-z][^>]*>|[\s\n])*)\1#i', "$2$1", $this->cleaned_HTML);

    $this->sanitizeFootnotes(); // <small><sup><a>1</a></sup></small> => <a class="footnote"></a>
    $this->cleaned_HTML = str_replace('<pre>', '</pre><pre>', $this->cleaned_HTML);
    $this->removeExtraCloseTags();
    $this->fixStraddledInlines();
    $this->cleaned_HTML = preg_replace('#<p(?: [^>]+)?>[\n\s]*</p>#i', '', $this->cleaned_HTML);
    ##echo $this->cleaned_HTML;
  }

  // <p><i>some</p><p>text</i></p> => <p><i>some</i></p><p><i>text</i></p>
  protected function fixStraddledInlines() {
    // Straddles 4 lines
    $c = preg_replace(
      '#(<p[^>]*>)([^<]*)<([^p\s>]+)([^>]*)>'. // 1 2 <34>
        '([^<]+)(</p>[\n\s]*<p[^>]*>)'. // 5 6
        '([^<]+)(</p>[\n\s]*<p[^>]*>)'. // 7 8
        '([^<]+)(</p>[\n\s]*<p[^>]*>)([^<]*)</\3#i', // 9 10 11 </3
      "$1$2<$3$4>$5</$3>$6<$3$4>$7</$3>$8<$3$4>$9</$3>$10<$3$4>$11</$3", $this->cleaned_HTML);
    // Straddles 3 lines
    $c = preg_replace(
      '#(<p[^>]*>)([^<]*)<([^p\s>]+)([^>]*)>'. // 1 2 <34>
        '([^<]+)(</p>[\n\s]*<p[^>]*>)'. // 5 6
        '([^<]+)(</p>[\n\s]*<p[^>]*>)([^<]*)</\3#i', // 7 8 9 </3
      "$1$2<$3$4>$5</$3>$6<$3$4>$7</$3>$8<$3$4>$9</$3", $c);
    // Straddles 2 lines
    $c = preg_replace(
      '#(<p[^>]*>)([^<]*)<([^p\s>]+)([^>]*)>([^<]+)(</p>[\n\s]*<p[^>]*>)([^<]*)</\3#i',
      "$1$2<$3$4>$5</$3>$6<$3$4>$7</$3", $c);
    // Any other orphaned halves of a tag pair, strip the tags out (except for <b* == <br />)
    $c = preg_replace(
      '#(<p[^>]*>)([^<]*)(</?[^/bp\s>]+[^>]*>)([^<]*</p>)#i',
      "$1$2$4", $c);
    if ($c)
      $this->cleaned_HTML = $c;
  }

  // Remove extra </X> tags
  protected function removeExtraCloseTags() {
    $tags = array();
    $frags = preg_split('#(<\S[^>]*>)#', $this->cleaned_HTML, -1, PREG_SPLIT_DELIM_CAPTURE);
    for ($i = 1; $i <= count($frags); $i = $i+2) {
      if (preg_match('#^<(/?)(\w[^>\s]*)([^>]*>)$#', $frags[$i], $matches)) {
        $tag = strtolower($matches[2]);
        if ($matches[1] == '/') {
          if ($tags[$tag] == 0)
            $frags[$i] = '';
          else
            $tags[$tag]--;
        } else {
          $tags[$tag]++;
        }
      }
    }
    $this->cleaned_HTML = implode($frags, '');
  }

  // \n...<br /><br /> => <p></p>
  protected function paragraphizePlain() {
    // Regex assumes <p> and <br /> tags have been lowercased already
    $this->cleaned_HTML = preg_replace('#(?:<br[^>]*>[\n\s]*){2,}#', "</p>\n<p>", $this->cleaned_HTML);
    $this->cleaned_HTML = preg_replace('#(<p[^>]*>)[\n\s]*<p>#', "$1", $this->cleaned_HTML);
    $this->cleaned_HTML = preg_replace('#(?:</p>[\n\s]*){2,}#', "</p>", $this->cleaned_HTML);
    $this->cleaned_HTML = preg_replace('#<p[^>]*>[\n\s]*</p>#', "", $this->cleaned_HTML);
  }

  // Change all inline <a>/<small>/<sup> tags to <a class="footnote">
  protected function sanitizeFootnotes() {
    // To simplify processing, all <sup> and <a> tags are treated as footnotes.
    // The one known exception is <sup> numbers that are exponents, but these are rare, and
    // should be changed back to <sup> later (all footnote references that fail to link
    // to footnotes are changed to <sup> in buildCase())

    // Handle any (FN1) style footnotes
    $this->cleaned_HTML = preg_replace('#\(FN([0-9\*]+)\)#', "<f>$1</f>", $this->cleaned_HTML);

    // Handle any *fn1 style footnotes
    $this->cleaned_HTML = preg_replace('#\*fn([0-9\*]+)#', "<f>$1</f>", $this->cleaned_HTML);

    // First handle well-formed tag pairs of <a>, <small>, <sup> that don't span lines
    $this->cleaned_HTML = preg_replace('#<(small|sup|a)[^>]*>([^<\n]*)</\1>#i', "<f>$2</f>", $this->cleaned_HTML);
    $this->cleaned_HTML = preg_replace('#<(small|sup|a)[^>]*>((?:[^<\n]|</?f)*)</\1>#i', "<f>$2</f>", $this->cleaned_HTML);
    $this->cleaned_HTML = preg_replace('#<(small|sup|a)[^>]*>((?:[^<\n]|</?f)*)</\1>#i', "<f>$2</f>", $this->cleaned_HTML);
    // Handle adjacent duplicate tags
    $this->cleaned_HTML = preg_replace('#<f>([^<]*)<f>#', "<f>$1", $this->cleaned_HTML);
    $this->cleaned_HTML = preg_replace('#<f>([^<]*)<f>#', "<f>$1", $this->cleaned_HTML);
    $this->cleaned_HTML = preg_replace('#</f>([^<]*)</f>#', "$1</f>", $this->cleaned_HTML);
    $this->cleaned_HTML = preg_replace('#</f>([^<]*)</f>#', "$1</f>", $this->cleaned_HTML);

    // Change all inline <a>/<small>/<sup> tags to <f>
    $this->cleaned_HTML = preg_replace('#(?:<small[^>]*>\s*|<sup[^>]*>\s*|<a[^>]*>\s*){1,3}([^<\s\n])#i', "<f>$1", $this->cleaned_HTML);
    $this->cleaned_HTML = preg_replace('#([^>\s\n]\.?)(?:\s*</small>|\s*</sup>|\s*</a>){1,3}#i', "$1</f>", $this->cleaned_HTML);
    // And remove all unmatched/badly nested tags
    $this->cleaned_HTML = preg_replace('#<f>([^<]*</?[abcdeghijklmnopqrstuvwxyz])#', "$1", $this->cleaned_HTML);
    $this->cleaned_HTML = preg_replace('#(</?[abcdeghijklmnopqrstuvwxyz][^>]*>[^<\n]*)</f>#', "$1", $this->cleaned_HTML);

    // Remove all remaining leftover sup and a tags (all well-formed tag pairs already replaced)
    // (we keep <small> for now because it is used in non-inline fashion to mark groups of <p>s
    // that are sections of footnotes)
    $this->cleaned_HTML = preg_replace('#</?sup[^>]*>#i', '', $this->cleaned_HTML);
    $this->cleaned_HTML = preg_replace('#</?a[^>]*>#i', '', $this->cleaned_HTML);
 
    // Fix <f>3</f>. => <f>3</f> and <f>3.</f> => <f>3</f>
    // 1.  after a sentence or just after an <open> tag, remove the period
    $this->cleaned_HTML = preg_replace('#(\.|<[a-zA-Z][^>]*>)\s*(<f>[^<\.]+)\.?\s*</f>(\s*)\.?#', "$1$2</f>$3", $this->cleaned_HTML);
    // 2. in the middle of a sentence, remove the period
    $this->cleaned_HTML = preg_replace('#(<f>[^<\.]+)\.?\s*</f>(\s*)\.?(\s*[a-z])#', "$1</f>$2$3", $this->cleaned_HTML);
    // 3. else, move the period
    $this->cleaned_HTML = preg_replace('#(<f>[^<\.]+)\.\s*</f>\s*\.?#', "$1</f>.", $this->cleaned_HTML);

    // Fix <f>*</f>* => <f>**</f>
    $this->cleaned_HTML = preg_replace('#\*</f>(\*+)#', "*$1</f>", $this->cleaned_HTML);
    $this->cleaned_HTML = preg_replace('#(\*+)<f>\*#', "<f>*$1", $this->cleaned_HTML);

    /* Don't need to do this kind of character matching here. Instead, we're applying it on the footnotes
     * themselves, then looping through the footnote refs, linking to footnotes, and then changing everything
     * that didn't link back to a <sup>
    // Change non-footnote superscripts identifed by first step back to <sup>
    // So our "footnote" regex is enforced by the second line
    $this->cleaned_HTML = preg_replace('#<f>([^<]*)</f>#', "<sup>$1</sup>", $this->cleaned_HTML);
    $this->cleaned_HTML = preg_replace('#<sup>(\s*(?:[a-z]{1,2}|[a-z]?[0-9]{1,3}|\*{1,5})\s*)</sup>#', "<f>$1</f>", $this->cleaned_HTML);
     */

    // Finally, <f> => <a class="footnote">. Remove leading and internal whitespace.
    $this->cleaned_HTML = preg_replace('#\s*<f>\s*#', '<a class="footnote">', $this->cleaned_HTML);
    $this->cleaned_HTML = preg_replace('#\s*</f>#', '</a>', $this->cleaned_HTML);

    // Fix "<p>*</p><p>footnote text" to "<p>* " ...
    $this->cleaned_HTML = preg_replace('#<p[^>]*>\s*[a1I\*]\s*</p>[\n\s]*<p([^>]*)>#i', "<p$1>* ", $this->cleaned_HTML);
  }

  // Remove <b> and (extremely few) <u> and a bunch of <sgmlish> (???) tags/fragments
  protected function removeBoldUBadTags() {
    $this->cleaned_HTML = preg_replace('#</?b(?:\s[^>]*)?>#i', '', $this->cleaned_HTML);
    $this->cleaned_HTML = preg_replace('#</?u(?:\s[^>]*)?>#i', '', $this->cleaned_HTML);

    // Remove SGML-style tags that are invalid HTML
    // These appear to be the vestige of some processing gone wrong; most are not known tags in 
    // any common DTD, such as docbook and should simply be deleted, a few carry some possible markup
    // meaning but are badly fragmented in text and certainly not machine parseable ("a few" == less
    // than a dozen in the entire corpus)
    $sgmlish = array(
       "ba",
       "bar",
       "be",
       "ber",
       "bvr",
       "col",
       "corroded",
       "dennison",
       "drawing",
       "emsupi",
       "emsupu",
       "in",
       "invalid",
       "iya",
       "less",
       "lier",
       "manners",
       "ment",
       "nbr",
       "nllop",
       "nr",
       "ntered",
       "ph",
       "pl",
       "pr",
       "pub",
       "r",
       "rb",
       "re",
       "row",
       "sume",
       "symbol",
       "t",
       "vete",
       "www");
    $this->cleaned_HTML = preg_replace('#</?(?:'.implode('|', $sgmlish).')(?:\s[^>]*)?>#i', ' ', $this->cleaned_HTML);
  }

  protected function closeBrTags() {
    $this->cleaned_HTML = preg_replace('#[\n\s]*<br[^>]*>\n*#i', '<br />', $this->cleaned_HTML);
  }

  protected function removeNestedPs() {
    // Consolidate nested tags
    $this->cleaned_HTML = preg_replace('#<p([^>\s]*)([^>]*)>([\n\s]*</?[^p][^>]*>)*<p\1[^>]*>#i', "<p$1$2>$3", $this->cleaned_HTML);
    // Close unclosed tags
    $this->cleaned_HTML = preg_replace('#<p([^>\s]*)([^>]*)>([^<\n]+)<p\1([^>]*)>#i', "<p$1$2>$3</p$1><p$1$4>", $this->cleaned_HTML);

    // Consolidate nested close tags. Also </pre></p> => </p> (p should not contain block-level elements)
    $this->cleaned_HTML = preg_replace('#</p([^\s>]+)?>[\n\s]*</p>#i', "</p$1>", $this->cleaned_HTML);
    $this->cleaned_HTML = preg_replace('#</p([^\s>]+)?>([^<]*[^\s<]+)</p>#i', "</p$1><p>$2</p>", $this->cleaned_HTML);
  }

  protected function fixEntities() {
    $search = array('&#8211;', '&#x2014;', '&#8212;', "\xe2\x80\x99", "\xe2\x80\x98", "\x80\x99", "\x80\x98", "\x1c", "\xc2\xa7", "\xe2\x80\x9c", "\xe2\x80\x9d");
    $replace = array('&ndash;', '&mdash;', '&mdash;', "'", "'", "'", "'", '"', '&sect;', '"', '"');
    $this->cleaned_HTML = str_replace($search, $replace, $this->cleaned_HTML);
    $frags = preg_split('#(<\S[^>]*>)#', $this->cleaned_HTML, -1, PREG_SPLIT_DELIM_CAPTURE);
    for ($i = 0; $i <= count($frags); $i = $i+2) {
      // convert numerical entities
      $frags[$i] = htmlentities(html_entity_decode($frags[$i]));
      // insert any custom text-only manipulations here
    }
    $this->cleaned_HTML = implode($frags, '');
    // Remove double entity encodes. &ndash; and &mdash; are known to be missed by above
    // decode/encode step and thus double-encode
    $this->cleaned_HTML = preg_replace('#&amp;([mn]dash|[0-9]+);#', "&$1;", $this->cleaned_HTML);
  }

  protected function sanitizeBody() {
    // Delete <head> and/or <title>
    $this->cleaned_HTML = preg_replace('#<head[^>]*>.*</head>#i', '', $this->cleaned_HTML);
    $this->cleaned_HTML = preg_replace('#<title[^>]*>.*</title>#i', '', $this->cleaned_HTML);

    // Remove any orphan <td> tags (appear in a handful of cases, artifact of Fastcase's
    // page-layout HTML on their site, where our case data is inside a table cell)
    if (!preg_match('#<table[^>]*>#i', $this->cleaned_HTML) or
        !preg_match('#<tr[^>]*>#i', $this->cleaned_HTML))
      $this->cleaned_HTML = preg_replace('#</?t(?:d|r|h|body|able)[^>]*>#i', '', $this->cleaned_HTML);

    // Remove everything up to first <body> tag (for instance in case <head> wasn't closed).
    // Or if no body, first <html> tag
    if (preg_match('#<body[^>]*>#i', $this->cleaned_HTML, $m, PREG_OFFSET_CAPTURE))
      $this->cleaned_HTML = substr($this->cleaned_HTML, strlen($m[0][0]) + $m[0][1]);
    else if (preg_match('#<html[^>]*>#i', $this->cleaned_HTML, $m, PREG_OFFSET_CAPTURE))
      $this->cleaned_HTML = substr($this->cleaned_HTML, strlen($m[0][0]) + $m[0][1]);

    // Remove all remaining <html><head><meta><title><link><body> tags
    $this->cleaned_HTML = preg_replace('#</?(?:html|head|meta|title|link|body)[^>]*>#i', '', $this->cleaned_HTML);

    // Set our own XHTML header structure here
    $css_loc = 'http://freelawreporter.org/css/';
    // actual <title> will be set later
    $this->cleaned_HTML = '<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">'.
      '<head><title>CALI Free Law Reporter</title>'.
      '<meta name="creation" />'.
      '<link rel="stylesheet" type="text/css" href="'.$css_loc.'procase.css" />'.
      '<link rel="stylesheet" type="text/css" href="'.$css_loc.'proprint.css" media="print" />'.
      '<script type="text/javascript" src="'.$css_loc.'procase.js"></script>'.
      '</head><body><p>'.$this->cleaned_HTML.'</p></body></html>'; // Note addition of initial <p> and trailing </p>, <p><p> and </p></p> situations will be fixed but subsequent clean methods
  }

  // <p>&nbsp;* => <p class="indent">
  protected function setIndentAttribs() {
    // Remove duplicates
    $this->cleaned_HTML = preg_replace('#(?:<p>\s*){2,}#i', '<p>', $this->cleaned_HTML);
    $this->cleaned_HTML = preg_replace('#(?:</p>\s*){2,}#i', "</p>\n", $this->cleaned_HTML);

    // 20 hard spaces is the cutoff for indent vs. center
    // (have seen indents of up to 15 spaces, 1 hard-spaced center of 26 spaces)
    $this->cleaned_HTML = preg_replace('#<p([^>]*)>(?:&nbsp;){21,}#i', "<p$1 class=\"center\">", $this->cleaned_HTML);
    $this->cleaned_HTML = preg_replace('#<p([^>]*)>(?:&nbsp;){1,20}#i', "<p$1 class=\"indent\">", $this->cleaned_HTML);
  }

  // Prepend </p> to all <p> and append to all </center> not already next to </p>, then remove adjacent </p></p>
  protected function closeParagraphs() {
    $this->cleaned_HTML = preg_replace('#(<p[^>]*>)#i', "</p>$1", $this->cleaned_HTML);
    $this->cleaned_HTML = preg_replace('#(?<!</p>)</center>[\s\n]*(?![\s\n]*</?p)#i', "</center></p><p>", $this->cleaned_HTML);
    // If adding additional </p> was unnecessary, remove
    $this->cleaned_HTML = preg_replace('#</(p|pre)>((?:[\n\s]|<[^>]+>)*)</p>#i', "</$1>$2", $this->cleaned_HTML);
    // Now append and remove <p> to </p(re)> that are not already followed by <p> or <pre> <small> or </anything>
    $this->cleaned_HTML = preg_replace('#</p(re)?>[\s\n]*(?![\s\n]*<(?:p|/|small))#i', "</p$1><p>", $this->cleaned_HTML);
    $this->cleaned_HTML = preg_replace('#<p>((?:[\n\s]|<[^>]+>)*)<p([^>]*)>#i', "$1<p$2>", $this->cleaned_HTML);
    // Remove leading </p> inserted into <body><p>
    $this->cleaned_HTML = str_replace('<body></p>', '<body>', $this->cleaned_HTML);
  }

  // Remove all <center> tags, make adjacent <p> class="center" or create it if it doesn't exist
  protected function setCenterAttribs() {
    // Remove duplicates
    $this->cleaned_HTML = preg_replace('#(?:\s*<center>\s*){1,}#i', '<center>', $this->cleaned_HTML);
    $this->cleaned_HTML = preg_replace('#(?:\s*</center>\s*){1,}#i', '</center>', $this->cleaned_HTML);

    // Correctly nested <center>...</center>
    $this->cleaned_HTML = preg_replace('#<center[^>]*><p[^>]*>([^<]*)</p></center>#i', "<p class=\"center\">$1</p>", $this->cleaned_HTML);
    $this->cleaned_HTML = preg_replace('#<center[^>]*>([^<]*)</center>#i', "<p class=\"center\">$1</p>", $this->cleaned_HTML);

    // Versions with <p><sometag><center> and <center><sometag><p>
    $this->cleaned_HTML = preg_replace('#<p[^>]*>((?:<[^>]*>)*)<center[^>]*>\s*#i', "<p class=\"center\">$1", $this->cleaned_HTML);
    $this->cleaned_HTML = preg_replace('#<center[^>]*>((?:<[^>]*>)*)<p[^>]*>\s*#i', "<p class=\"center\">$1", $this->cleaned_HTML);
    $this->cleaned_HTML = preg_replace('#\s*</center[^>]*>((?:<[^>]*>)*)</p[^>]*>#i', "$1</p>", $this->cleaned_HTML);
    $this->cleaned_HTML = preg_replace('#\s*</p[^>]*>((?:<[^>]*>)*)</center[^>]*>#i', "$1</p>", $this->cleaned_HTML);

    // All remaining <center> tags
    $this->cleaned_HTML = preg_replace('#((?:<[^/][^>]*>)*)<center[^>]*>\s*#i', "<p class=\"center\">$1", $this->cleaned_HTML);
    $this->cleaned_HTML = preg_replace('#\s*</center[^>]*>((?:</[^>]*>)*)#i', "$1</p>", $this->cleaned_HTML);
  }

}

?>
