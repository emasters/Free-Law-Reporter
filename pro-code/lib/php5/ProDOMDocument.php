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

// Subclass of DOMDocument, adds various helper methods and defaults
class ProDOMDocument extends DOMDocument
{
  public function __construct($version = NULL, $encoding = NULL) {
    // DOM settings
    parent::__construct($version, $encoding);
    $this->preserveWhiteSpace = FALSE;
    $this->formatOutput = TRUE;
  }

  // Helper function to get next sibling that is an element
  public function nextElement($node) {
    $next = $node->nextSibling;
    if ($next and ($next->nodeType != 1))
      return $this->nextElement($next);
    return $next;
  }

  // Searches $node's children for any plain text nodes.
  // Text nodes are inserted into a new element of type $tag in the same location.
  // Empty text nodes and br nodes are removed.
  public function wrapTextNodes($parent, $tag) {
    $node = $parent->firstChild;
    while ($node) {
      $next = $node->nextSibling;

      if ($node->nodeType == 3) {
        $test = trim(str_replace('&nbsp;', ' ', $node->nodeValue));
        if (strlen($test) > 0) {
          $p = $this->createElement($tag);
          $node->parentNode->insertBefore($p, $node);
          $p->appendChild($node);
        } else {
          $node->parentNode->removeChild($node);
        }
      } else if ($node->nodeType == 1 and $node->tagName == 'br') {
        $node->parentNode->removeChild($node);
      }
      $node = $next;
    }
  }

  // Removes any/all comment nodes that are children of $parentnode
  public function stripCommentNodes($parentnode = NULL) {
    if (!$parentnode)
      $parentnode = $this->documentElement;
    $node = $parentnode->firstChild;
    while ($node) {
      $next = $node->nextSibling;
      if ($node->nodeType == 1 and $node->hasChildNodes())
        $this->stripCommentNodes($node);
      else if ($node->nodeType == 8)
        $node->parentNode->removeChild($node);
      $node = $next;
    }
  }

  public function saveXML($node = NULL, $options = NULL) {
    // As of 2/26/2008, just return UTF-8 XML for compatibility with XML parsers
    return parent::saveXML($node, $options);
    //$xml = parent::saveXML($node, $options);
    //return mb_convert_encoding($xml, "HTML-ENTITIES", "UTF-8");
  }

  public function saveXHTML($node = NULL, $options = NULL, $doctype = NULL) {
    if (!$doctype) {
      $doctype = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" '.
                 '"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">';
    }
    $xml = $this->saveXML($node, $options);

    // <script /> => <script></script> for IE
    $xml = preg_replace('#<script ([^>]+)/>#i', "<script $1></script>", $xml);

    // We are now including the xml prolog and inserting the doctype after it
    $xmlprolog = '<?xml version="1.0" encoding="UTF-8"?>';
    $html_pos = strpos($xml, '<html');
    return "$xmlprolog\n$doctype\n".substr($xml, $html_pos);
  }

  // Append our public.resource.org footer text, inside body, only if it isn't already there
  public function appendFooter() {
    if (!$this->getElementById('footer')) {
      $body = $this->documentElement->firstChild->nextSibling;
      $div = $this->quickElement('div', NULL, array('id' => 'footer'));
      $body->appendChild($div);
      $p = $this->quickElement('p');
      $div->appendChild($p);
      $p->appendChild($this->quickElement('a', "CC\xe2\x88\x85",
        array('rel' => 'license', 'href' => 'http://labs.creativecommons.org/licenses/zero-assert/1.0/us/')));
      $p->appendChild($this->createTextNode(' | Transformed by the '));
      $p->appendChild($this->quickElement('a', 'Center for Computer-Assisted Legal Instruction', 
        array('href' => 'http://www.cali.org')));
      $p->appendChild($this->createTextNode(' for the '));
      $p->appendChild($this->quickElement('a','Free Law Reporter', array('href'=>'http://www.freelawreporter.org/')));
	  $p->appendChild($this->createTextNode(' from '));
	  $p->appendChild($this->quickElement('a', 'Public.Resource.Org, Inc.', 
        array('href' => 'http://public.resource.org/')));
	  $p->appendChild($this->createTextNode(' sources.'));
    }
  }

  // Helper to create new DOM element
  public function quickElement($name, $value = NULL, $attribs = NULL) {
    $e = $this->createElement($name);
    if ($value)
      $e->nodeValue = $value;
    if ($attribs) {
      foreach ($attribs as $key => $val)
        $e->setAttribute($key, $val);
    }
    return $e;
  }
}

?>
