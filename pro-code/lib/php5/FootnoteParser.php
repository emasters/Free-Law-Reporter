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

// Utility class for splitting footnote grafs from main text and adding <a> tags
// to footnote references *inside footnotes* (does not add tags to references in
// body text, they should be identified and added before using this class.)
class FootnoteParser {
  public $refClass = 'footnote'; // HTML class name of footnote ref <a> (tags in main text)
  public $fnClass = 'footnote'; // HTML class name of footnote <a> (tags next to footnote text)

  protected $dom; // DOMDocument, it is modified in place by this class
  protected $firstNode; // First node that may contain footnotes (footnote refs may be in previous grafs)
  protected $lastRefNode; // Last node found containing footnote references (is updated as refs/footnotes are parsed)

  protected $fnPs = array(); // list of nodes that are footnotes
  protected $refs = array(); // map of <a> footnote refs in main body text
  protected $notes = array(); // map of <a> footnote refs in footnote text

  public function __construct(ProDOMDocument $dom, DOMNode $startNode) {
    $this->dom = $dom;
    $this->firstNode = $startNode;
  }

  public function parse() {
    // Do initial count of <a class="footnotes"> in the doc
    $this->updateRefs();

    // Find most obvious footnotes, those marked in the text!
    // Identify based on <small>, <p>----+</p>
    // Also promote all nodes inside <small> to equal level with other <p>s
    $this->findSmallTags();
    $this->findMarked();
    $this->updateLinkedNotes();
    // Update count of <a class="footnote"> outside footnotes (in main text)
    $this->updateRefs();

    // If refs and notes match up, we're done!
    if ($this->compareRefsNotes())
      return TRUE; 

    // Now we look for <a> tags at the beginning of grafs, or "^Notes:$", to find unmarked footnote sections
    $this->findUnmarkedTagged();
    $this->updateLinkedNotes();
    $this->updateRefs();

    // If refs and notes match up, we're done!
    if ($this->compareRefsNotes())
      return TRUE; 

    // Possibly the first step, findSmallTags and findMarked found footnote sections but the
    // footnote references in the footnotes themselves were not linked. So do updateNotes
    // instead of updateLinkedNotes in the sections we've already identified.
    $this->updateNotes();
    $this->updateRefs();

    if ($this->compareRefsNotes())
      return TRUE; 

    // Now do regex parsing of all paragraphs for unmarked footnote sections
    $this->findUnmarkedRegex();
    $this->updateNotes();
    $this->updateRefs();

    if ($this->compareRefsNotes())
      return TRUE; 

    echo "Refs: ".preg_replace("#[\n\s]+#", ' ', print_r($this->refs, TRUE))."\n";
    return FALSE;
  }

  // Return first node after prelims (if any prelims)
  public function getFirstNode() {
    return $this->firstNode;
  }

  // Return TRUE/FALSE if node $p is in $fnPs
  public function isFootnote($p) {
    // For some reason in_array doesn't work, even with 3rd param = TRUE
    //return in_array($p, $this->fnPs);
    foreach ($this->fnPs as $fnp) {
      if ($p === $fnp)
        return TRUE;
    }
    return FALSE;
  }

  // Return TRUE/FALSE if there is a footnote <a> tag at the start of node $p
  public function hasFootnoteRefTag($p) {
    if (!$p)
      return FALSE;
    if ($p->firstChild->nodeType == 1 and
        $p->firstChild->tagName == 'a' and
        $p->firstChild->getAttribute('class') == $this->fnClass)
      return TRUE;
    else
      return FALSE;
  }

  // Helper method to change an <a> element in place to <sup>
  public function footnote2Super($node) {
    $s = $this->dom->createElement('sup');
    $s->nodeValue = $node->nodeValue;
    $node->parentNode->insertBefore($s, $node);
    $node->parentNode->removeChild($node);
    return $s;
  }

  // Compare $refs and $notes, return TRUE if they match, FALSE if not.
  protected function compareRefsNotes() {
    // Not sure how much this comparison can be relied upon, especially in large
    // documents, given that a single unrecognized footnote ref (particularly a weird
    // one like "****") will throw it off.
    //
    // Important to iterate over $refs rather than $notes, we don't care as much about
    // the reverse footnote lookup as the forward lookup
    
    ## debug
    echo "Refs: ".count($this->refs).", Notes: ".count($this->notes)."\n";
    ##print_r(array($this->refs, $this->notes));

    foreach ($this->refs as $nodevalue => $ref) {
      if ($ref != $this->notes[$nodevalue])
        return FALSE;
    }
    return TRUE;
  }

  // Parse current $fnPs and standardize existing <a> tags only. Update $notes. 
  protected function updateLinkedNotes() {
    $notes = array();
    foreach ($this->fnPs as $p) {
      $fc = $p->firstChild;
      if ($fc->nodeType == 1 and $fc->tagName == 'a') {
        $fc->setAttribute('class', $this->fnClass);
        $notes[] = $fc;
      }
    }
    $this->notes = $this->countValues($notes);
  }

  // Parse current $fnPs and insert/standardize <a> tags. Update $notes.
  protected function updateNotes() {
    $notes = array();
    $lastnote = FALSE;
    foreach ($this->fnPs as $p) {
      $fc = $p->firstChild;
      if ($fc->nodeType == 3) {
        $ref = $this->stripFootnoteRef($fc->nodeValue);
        if ($ref !== FALSE) {
          // Don't want 1-letter words like A and I being flagged as footnotes.
          if (!(is_numeric($lastnote) and !preg_match('#[0-9\*]#', $ref) and
                !$this->isNoteMissing(trim($ref, " .\t\n\r"))))
          {
            $fc->nodeValue = ' '.trim(substr($fc->nodeValue, strlen($ref)), ". \t\n\r");
            $a = $this->dom->createElement('a');
            $a->setAttribute('class', $this->fnClass);
            $a->nodeValue = trim($ref, " .\t\n\r");
            $p->insertBefore($a, $fc);
            $notes[] = $a;
            $lastnote = $a->nodeValue;
          }
        }
      } else if ($fc->nodeType == 1 and $fc->tagName == 'a') {
        $fc->setAttribute('class', $this->fnClass);
        $notes[] = $fc;
        $lastnote = $fc->nodeValue;
      }
    }
    $this->notes = $this->countValues($notes);
  }

  // Update $refs
  protected function updateRefs() {
    $refs = array();
    foreach ($this->dom->getElementsByTagName('a') as $a) {
      if (!$this->isFootnote($a->parentNode) and
          $a->hasAttribute('class') and 
          strpos($a->getAttribute('class'), $this->refClass) !== FALSE and
          $a->parentNode->firstChild !== $a) {
        $this->lastRefNode = $a->parentNode;
        $refs[] = $a;
      }
    }
    $this->refs = $this->countValues($refs);
  }

  // Maps array of <a> nodes to an associative array of value => count, i.e.:
  // array( '1' => 2, '2' => 1, '*' => 1)
  protected function countValues($nodes) {
    $map = array();
    foreach ($nodes as $a) {
      if ($map[$a->nodeValue])
        $map[$a->nodeValue]++;
      else
        $map[$a->nodeValue] = 1;
    }
    return $map;
  }

  // Returns TRUE/FALSE if $refs[$val] > $notes[$val]
  protected function isNoteMissing($val) {
    if (isset($this->refs[$val]) and 
        (!isset($this->notes[$val]) or $this->refs[$val] > $this->notes[$val]))
      return TRUE;
    else
      return FALSE;
  }

  // Strip footnote reference off beginning of string. Return FALSE if no match.
  protected function stripFootnoteRef($string) {
    if (preg_match('#^(\s*(?:[a-z]|\*+|[a-z]{0,2}[0-9]{1,3}))(?:[\.\s]+(?!(?:U\.S\.|F\.[0-9]|\s*\*\s+\*))|$)#i', $string, $matches))
      return $matches[1];
    else
      return FALSE;
  }

  // Helper method to add a node to $fnPs, but only if it's an element
  protected function setNote($node) {
    if ($node->nodeType == 1 and !$this->isFootnote($node)) {
      ##echo "setting as footnote: ".substr($node->nodeValue, 0, 20)."\n";
      $this->fnPs[] = $node;
    }
  }

  // Look for footnote sections using stripFootnoteRef regex. Also require that a
  // footnote link to an unmatched entry in $this->refs before a new fnsection is started
  protected function findUnmarkedRegex() {
    $existingfnsection = FALSE;
    $newfnsection = FALSE; // Track state, once an fnsection starts, trailing p's are also footnotes
    $pastlastrefnode = FALSE;
    $node = $this->firstNode;
    while ($node) {
      if ($node === $this->lastRefNode)
        $pastlastrefnode = TRUE;

      if ($this->isFootnote($node)) {
        $existingfnsection = TRUE;
      } else {
        if ($ref = $this->stripFootnoteRef($node->nodeValue)) {
          if ($newfnsection or $this->isNoteMissing($node->nodeValue)) {
            // As hack for early SCOTUS cases with lowercase alphabetic footnote references, where
            // ends of footnotes aren't marked, do not set trailing footnote grafs with these [a-z] refs
            if (!preg_match('#[a-z]#', $ref))
              $newfnsection = TRUE;
            $this->setNote($node);
          }
        } else if ($newfnsection and !$existingfnsection and $this->hasTrailingNotes($node, TRUE)) {
          // (we do not append trailing grafs as notes if there are still more
          // refs [i.e. more main body text] to come, or if we just passed
          // a previously set "footnote section end" point.)
          $this->setNote($node);
        } else {
          $newfnsection = FALSE;
        }
      }
      $node = $node->nextSibling;
    }
  }

  // Look for <a> tags or "^Notes:$" to identify footnote sections
  protected function findUnmarkedTagged() {
    $existingfnsection = FALSE;
    $newfnsection = FALSE; // Track state, once an fnsection starts, trailing p's are also footnotes
    $pastlastrefnode = FALSE;
    $node = $this->firstNode;
    while ($node) {
      if ($node === $this->lastRefNode)
        $pastlastrefnode = TRUE;

      if ($this->isFootnote($node)) {
        $existingfnsection = TRUE;
      } else {
        if ($this->hasFootnoteRefTag($node)) {
          // As hack for early SCOTUS cases with lowercase alphabetic footnote references, where
          // ends of footnotes aren't marked, do not set trailing footnote grafs with these [a-z] refs
          if ($node->firstChild->nodeType != 1 or !preg_match('#[a-z]#', $node->firstChild->nodeValue))
            $newfnsection = TRUE;
          $this->setNote($node);
        } else if ($this->isHeader($node)) {
          $newfnsection = TRUE;
          $this->setNote($node);
        } else if ($newfnsection and !$existingfnsection and $this->hasTrailingNotes($node)) {
          // (we do not append trailing grafs as notes if there are still more
          // refs [i.e. more main body text] to come, or if we just passed
          // a previously set "footnote section end" point.)
          $this->setNote($node);
        } else {
          $newfnsection = FALSE;
        }
      }
      $node = $node->nextSibling;
    }
  }

  // Return TRUE/FALSE if any of next 3 grafs is a footnote
  protected function hasTrailingNotes($node, $tryStrip = FALSE) {
    $tried = 0;
    while (($next = $node->nextSibling) and $tried < 3) {
      if ($this->hasFootnoteRefTag($next))
        return TRUE;
      else if ($tryStrip and $this->stripFootnoteRef($next->nodeValue))
        return TRUE;
      $tried++;
      $node = $next;
    }
    return FALSE;
  }

  // Use markers like <p>----+</p> to identify footnote sections
  protected function findMarked() {
    $fnsection = FALSE; // Track state, once an fnsection starts, trailing p's are also footnotes
    $pastlastrefnode = FALSE;
    $node = $this->firstNode;
    while ($node) {
      $next = $node->nextSibling;

      if ($node === $this->lastRefNode)
        $pastlastrefnode = TRUE;

      if ($node->tagName == 'p') {
        // Require ------- dividers to not be centered (unless the next sib is a footnote), 
        // and require there be at least one footnote ref in the doc.
        if ($this->isDivider($node) and count($this->refs) > 0 and 
            ($node->getAttribute('class') != 'center' or $this->hasFootnoteRefTag($next))) {
          $node->parentNode->removeChild($node);
          if ($fnsection)
            $fnsection = FALSE;
          else
            $fnsection = TRUE;
        } else if ($fnsection) {
          $this->setNote($node);
        }
      } else {
        // Anything else (i.e. <pre>), also mark it if we are in an fnsection
        if ($fnsection)
          $this->setNote($node);
      }
      $node = $next;
    }
  }

  // Marks anything inside a <small> tag as a fn graf
  // Promotes these child elements to peer level and removes the <small> element
  protected function findSmallTags() {
    $node = $this->firstNode;
    while ($node) {
      $next = $node->nextSibling;
      if ($node->tagName == 'small') {
        // Add any children to list of fnPs
        if ($node->hasChildNodes()) {
          $this->dom->wrapTextNodes($node, 'p');
          $c = $node->firstChild;
          while ($c) {
            $this->fnPs[] = $c;
            $c = $c->nextSibling;
          }
          // Promote child nodes to be next siblings of <small>; remove $node
          $this->replaceWithChildren($node);
        } else {
          // No children, so just delete <small>
          $node->parentNode->removeChild($node);
        }
      }
      $node = $next;
    }
  }


  // Return TRUE/FALSE if node ia a "Notes:" footnote header
  protected function isHeader($node) {
    return preg_match('#^[\s\n]*Notes[\.:]?[\s\n]*$#i', $node->nodeValue);
  }

  // Return TRUE/FALSE if node is a <p>---------------</p> footnote divider
  protected function isDivider($node) {
    return preg_match('#^\s*[_-]{5,}\s*$#', $node->nodeValue);
  }

  // Helper function to flatten the DOM tree by deleting $node, replacing it with 
  // any child nodes it had.
  // Returns first of these children or, if $node was empty, whatever node follows it.
  protected function replaceWithChildren($node) {
    // Check to see if we are replacing and removing firstNode
    if ($node === $this->firstNode)
      $replaceFirstNode = TRUE;

    if ($node->hasChildNodes()) {
      if ($node->nextSibling) {
        while ($node->hasChildNodes())
          $node->parentNode->insertBefore($node->lastChild, $node->nextSibling);
      } else {
        while ($node->hasChildNodes())
          $node->parentNode->appendChild($node->firstChild);
      }
    }
    $next = $node->nextSibling;
    $node->parentNode->removeChild($node);
    if ($replaceFirstNode)
      $this->firstNode = $next;
    return $next;
  }

}
?>


