/* 
 * By Public.Resource.Org, Inc.
 * No Rights Reserved.
 *
 * Author: Joel Hardi <joel -at- hardi.org>
 *
 * Status: Stable
 * Revision: 0.116
 * Last modified: Fri Mar 14 2008 17:09 PDT
 */

Transformation code overview:
-----------------------------
Included here is all source code used to transform raw source HTML as received
from Fastcase into our semantic, machine-readable XML format.

The code is currently stable and will transform all Fastcase source data, and
can also be used in a round-trip fashion, by loading from our XHTML files
(in fact, this is what our index-building code does). FastcaseCase offers
a few accessor methods that make it slightly preferable to plain DOM
manipulation, but in general this code is an ugly hairball to transform A into
B, and your choice of language/platform will better dictate what you use to 
manipulate B.

We use the PHP DOMDocument libxml2 interface and depend on its LoadHTML()
method to load invalidly formed HTML source; however we must also do 
significant cleanup on raw source before attempting to load it into a
DOMDocument object (which you'll see pretty quickly if you dip into the code).

The basic process for downloading our raw source files (exactly as received by
us from Fastcase) and running our transformation code yourself are:

  1. Download all code from http://bulk.resource.org/courts.gov/c/raw/code/
  2. Download all raw source files from
     http://bulk.resource.org/courts.gov/c/raw/
  3. Run unpack-all.sh, it will unpack all source files into this dir structure:
       raw/F2 (468,735 files)
       raw/F3 (172,227 files)
       raw/US (61,745 files)
  4. Transform these files using run.php (or run-logger.sh)
  5. Index transformed cases using index.php

We have tested all code using PHP 5.2.5 on Solaris 10 and OS X 10.4, but any
PHP 5.2+ on Linux/BSD with standard GNU userspace tools like grep should work.

Contained here are:

Lists of files to exclude from processing, and utility shell script 
that explodes, integrates and dedupes contents of all raw tarballs:
-------------------------------------------------------------------
F3_exclude.txt
US_exclude.txt
unpack-all.sh

Conversion scripts:
-------------------
fastcase2xhtml.php - single-file conversion
run.php - batch processing including creating directories per volume
batch.php - batch processing to a single target directory
run-logger.sh - wrapper shell script for run.php, adds logging and recovery 
  from fatal crashes (of which there are normally zero)
index.php - script for generating all index.html pages
volindex.php - debugging version for creating volume index.html pages only

Application classes:
--------------------
FastcaseCase.php - main law case class, including adapters
FootnoteParser.php - Paragraph/footnote parser
FastcaseCase2HTML.php - conversion application class
FastcaseEditionIndex.php - edition (list of volumes) index class
FastcaseVolumeIndex.php - volume (list of cases) index class
FastcaseIndex.php - base class to edition and volume indexes
ProDOMDocument.php - DOMDocument subclass, parent class to FastcaseCase

CSS/JS:
-------
case.css is well-commented and serves to document our XHTML standard in lieu of
a DTD or other formal documentation

Utility scripts:
----------------
countvolume.php - simple CLI script to generate a report on number of volumes 
  and cases in a directory


Release Notes - Stable 0.115 Release Fri Mar 14 2008
----------------------------------------------------
With this release, our transformed caselaw is officially "stable." We won't
rule out future bug fixes, but aren't planning further releases at this time.

This release contains 29,029 new cases received from Fastcase and a handful
of cases that were previously left out because of duplicate filenames (details
follow). It also includes a number of parsing fixes and an improvement to
include more unique cases in the volume index.html pages.

New cases, tarball-exploding procedure for raw source files
-----------------------------------------------------------
We received and integrated some 29,029 new and replacement cases from Fastcase,
which are available in raw form in /raw:
  F3d20080307.tar.bz2
  US20080307.tar.bz2

There is also now a file US_exclude.txt listing outdated US files to exclude
from future XML transform processing.

In addition, we retested and found 8 F3 cases and 1 US case whose filenames are
the same as other cases (even though their contents are unique), so it is no
longer a simple matter of untarring everything in /raw into one directory to
get a collection of source files to process. 

To cleanly "document" how to explode all the raw source tarballs to get a clean
set of raw source files, there's now a shell script, unpack-all.sh, that does
it for you. So, steps for transforming cases yourself using are code are:

  1. Download all code from http://bulk.resource.org/courts.gov/c/raw/code/
  2. Download all raw source files from
     http://bulk.resource.org/courts.gov/c/raw/
  3. Run unpack-all.sh, it will unpack all source files into this dir structure:
       raw/F2 (468,735 files)
       raw/F3 (172,227 files)
       raw/US (61,745 files)
  4. Transform these files using run.php
  5. Index transformed cases using index.php

NOTE: Because we had to rename source files to avoid filename collisions, if you
are using the source filenames/FC IDs for any kind of deduping, be aware that
these 9 cases may or may not be the same as the previously published case with 
the same ID:
  F3/1121100556.html
  F3/130426720.html
  F3/167678013.html
  F3/1740241416.html
  F3/1987112105.html
  F3/434197591.html
  F3/540310038.html
  F3/714067156.html
  US/656836554.html

Other fixes/changes:
--------------------
 * Volume indexes now list all cases corresponding to a given page number cite
   that have unique docket numbers, instead of simply listing only one case
   per citation. F2/184 is a good example directory that has multiple valid
   cases for some citations, and also duplicate versions of cases that ought not
   be listed in indexes (as stated before, *all* cases are transformed and 
   included in our case directories/tarballs, we're only talking here about what
   gets listed in the index.html volume indexes).
 * Thanks to Stuart at Altlaw for pointing to a parsing issue with recognizing
   court names, as a result several thousand cases now have more accurate court
   and date fields.
 * Fix to identify more outlier court names
 * Fix to sanitize whitespace inside citations, so page <title>s and index
   listings are more accurate
 * Fixes to FastcaseCase::getValue() and getValues() so that citations and 
   parties are formatted more correctly in page <title>s and index listings
 * Fix to FastcaseCase2HTML::filenamer() so files are no longer saved with
   extra periods in the filenames
 * Bugfix to strip additional HTML comments from source files (these caused a
   parsing failure in F3)


Release Notes - 0.99 Release Tue Mar 4 2008
--------------------------------------------
This is largely a bugfix release, and the parsing and rendering of cases is
largely unchanged. The primary changes are that output format is now more
compatible with XML parsers, some duplicate F3 files have been excluded from
processing, and volume index.html files now list only one case per citation
even when there are duplicate versions of these cases.

XML compatibility:
------------------
We are now favoring compliant XML parsers over legacy browsers (cases are tested 
in IE 6+, Firefox 2+, Safari 2+ and Opera 9+). Some users reported issues with
our markup format with some DOM parsers, and we decided it is more important to
provide fully compliant XML than support outdated HTML clients. As a result:
 * We are no longer using HTML entities such as "&mdash;" and are using only
   UTF-8 for extended characters.
 * The <?xml ?> prolog has been restored. (Since we are using UTF-8, the prolog
   is not a requirement for XML 1.0, but it is good practice to include it.)
 * The http-equiv <meta> tag is, removed since it is not part of the XHTML 1.0
   specification and is ignored by XHTML 1.0-compliant user agents. We had
   included the tag for backwards compatibility but it is superfluous if we
   are not supporting legacy browsers.

NOTE: For compatibility with IE 6/7, files served at the bulk.resource.org
domain are sent over the wire with the text/html content type rather than
application/xhtml+xml, which is the most strictly correct type according to
current RFCs (generic XML types such as text/xml are also acceptable). As a
result, documents served at the bulk.resource domain may be interpreted as HTML,
and not XML, by some user agents (in particular, current Firefox 3 prereleases).
This is merely our current choice of configuration for our web server; the
documents themselves are fully compliant XHTML 1.0 strict, and it is a simple
configuration change for us (or you) to make to send them as the most correct
type, application/xhtml+xml.

Duplicate F3 cases:
-------------------
In /raw we provide all source documents as received from Fastcase. The file
F3d_Updated.tar.bz2 includes new versions of files included in the other
tarballs; however, the filenames used are *not* consistent, so our prior 
release contained transformed versions of both old and new versions of these
files. We determined which of the files in the original tarballs are "bad"
versions of the cases in F3d_Updated.tar.bz2, and list these 2,478 cases in
F3_exclude.txt (we opted to do this rather than regenerate the source
tarballs because of the possibility of error in determining which files to
exclude).

So, the current set of transformed cases consists of all the files in the the
/raw tarballs, minus the 2,478 files listed in F3_exclude.txt, and if you
are processing the raw cases yourself, you should also exclude these files from
your source files. ("cat F3_exclude.txt | xargs rm" will do the trick.)

We will follow this same procedure in future, in the event we receive any more
updated sets of files from Fastcase.

(As an aside, we presume there's a possibility of filename collision in each
updated set of files from Fastcase, in which case we will rename the new files
received to keep them from overwriting old files and so that the FC ID in each
transformed case remains unique. In this case there were no such collisions.)

Bug fixes:
----------
 * The <script /> tag is changed to <script></script> for compatibility with 
   IE 6/7.
 * The HTML <title> element now includes only the primary case citation, as
   returned by the getMainCite() accessor; previously the nodeValue of the first
   <p class="case_cite"> was used even when it contained multiple citations.
 * A handful of extended characters not previously parsed by the transform
   scripts are now fixed.
 * A small number of documents have SGML-style tag fragments which look to be 
   left over from some previous batch transformation -- most of these are just 
   words/word fragments that don't correspond to any common DTD, such as 
   DocBook, and clearly should be stripped out, while a few (such as <row> and 
   <col>) carry meaning but are still fragmented and undefined (and these few 
   occur in only a handful of cases). To avoid validation/parsing problems, we
   are just removing entirely all of these tags that we could identify from
   warning messages. Please report as bugs any non-XHTML tags that you
   discover (in most cases, documents should still parse with them present, but
   they are still incorrect). The list of tags we are removing is an array
   defined within the FastcaseCase::removeBoldUBadTags() method.

Other changes:
--------------
 * Citation recognition improved in FastcaseCase::getMainCite() so more cases
   are named correctly and assigned to the correct volume.
 * Text date to date conversion improved (so dates in case <meta> tag and 
   indexes are more accurate).
 * Identification of internal text headings improved (so paragraph numbering
   more precisely conforms to AALL principles we're following).
 * Volume indexes now list only one case per citation. This is only a cosmetic
   change for the indexes -- all cases received from Fastcase and included in
   the source tarballs are still transformed and included in our output
   directories/tarballs. We could not determine a simple programmatic way of
   deciding which version of a case is "best" so there's no logic underlying
   which version of a case appears in our index.

"Known issues" in previous release notes below continue to apply.


Release Notes - 0.90 Release Thu Feb 21 2008
--------------------------------------------
With this release, all case filenames/locations have changed. The case
tarballs/directories now include transformed versions of *all* caselaw received
from Fastcase (in the initial Feb 11 release, several hundred cases with parsing
problems were left out).

The quality of the transformed cases contained in this release is vastly 
improved over the Feb 11 cases, and we recommend anyone using our scripting code
and/or caselaw replace old copies with the current versions.

Many thanks to gnu@toad.com, Tim, Dan, Nick and Vasu at Justia, and Dana Powers
for identifying missing cases (and providing them!), making suggestions and
contributing patches. 

The transformation code is now "beta" status, and further changes/bug fixes
will be driven primarily by user feedback. We anticipate making another "plain
transformed data" release in the coming weeks to address any bugs found in this
one, and that we expect will conclude the initial XHTML transform project.

Known issues:
-------------
 * Identification of header fields (citation, parties, dockets etc.) is 
   dependent on order; we aren't trying to deal with out-of-order fields in
   this transformation. These cases tend to have other editorial issues that
   are beyond the scope of an automated batch process.
 * Some footnotes are not marked in the source files (i.e., they're just
   naked numerals interspersed in the text). We are only aiming to identify
   note references that have been identified using <a>, <sup> or pseudo-markup
   such as [FN1] or *fn1. (This pertains to note references in text, not the
   notes themselves; when valid note references exist, we attempt to identify
   and link the corresponding footnotes even if these are untagged.)
 * Many cases starting around 2000 and later are from preliminary source data
   that is not the final version published in West's reporters. Some of these
   cases have notices prepended identifying them as such; we are not attempting
   to parse these notices or flag these cases in any way. 529 U.S. 61 and
   529 U.S. 120 are examples of cases containing such notices.
 * Approximately 2,000 cases have been identified as missing (thanks again!).
   We haven't yet gone through the vast majority of these, while we've been 
   focusing on getting the transformation code shaped up.
 * There are a handful of cases missing citations, these go into the "other"
   volume listing for their edition (US, F2 or F3).

Markup additions:
-----------------
The core X(HT)ML markup is unchanged from the initial release and our 2007
caselaw release. The markup is still machine-generated, machine-readable
XML (if it's not, that's a bug) -- see case.css for an overview of the various 
elements. In the document <head>, however, we have made 2 minor metadata-
related additions:
 * The document date (the most recent of parsable document date fields) is
   in a date meta tag in ISO 8601 format
 * A source ID has been added as an XML comment FC:XXXXXX where XXXXXX 
   corresponds to a source file received from Fastcase (the files in /raw).
   This may be useful in future for de-duping/source identification.

Output format changes:
----------------------
 * Paragraph numbering. Per Carl's post, we have renumbered paragraphs according
   to AALL principles:
   http://groups.google.com/group/open-case-law/browse_thread/thread/c0ab37038dec82f
   The main change from the previous release here is we are now skipping 
   internal headings and roman numerals that we could identify algorithmically 
   from the source data.

 * Filenames. Filenames are still the case citation and docket number
   concatenated; however, we've made several fixes/improvements and have never
   explained the filename convention so I'll do so here. And in the first
   release, this was seriously broken! The filenames are now composed of:

      [cite].[docket1].[docket2]...[docket5].html where:
        [cite] is the first U.S. or F. citation identified in the case
        [docket] is the numeric portion of the first through fifth (if there
                 are that many) dockets listed in the case, numerically sorted

   The goal here is merely that the filenames be consistent each time the source 
   data is reparsed -- *not* that they represent anything of semantic 
   significance or be used for anything.

   When two files thus identified have the same name, and underscore and
   integer counter are appended. Our goal here is to transform *all* of our
   source data for your use; if there are multiple copies of a case we aren't
   making judgments about which is the "real" or "newest" version. (And
   given that our parsing is imperfect and the quality of our source data
   highly variable, don't take anything for granted!)

Bug fixes/improvements:
-----------------------
Bug fixes/parsing improvements number in the hundreds from the initial release 
and it would be pointless to try to list them all here. Major changes are:

 * Replaced 16 volumes of F3 with updated source data from Fastcase. The file
   F3d_Updated.tar.bz2 in raw/ contains the updated source files.

 * Footnotes. The footnote/paragraph parsing is now broken out in the source
   code as a separate utility class. All machine-identifiable footnotes should 
   now be marked and cross-linked. This includes footnotes referenced from 
   within footnotes (recursive!) and backward-pointing footnote references 
   (there are some). For instance, 14 U.S. 46 is a wacky example that is 
   actually correctly parsed from the source data.

 * Whitespace should be more consistent in XML output. Although whitespace is
   significant in XML, it is not in HTML, so in general leading and trailing
   whitespace within elements is truncated and empty document elements deleted.

 * Dates are now parsed and sorted into date_parse arrays (close to DateTime
   objects), and there are accessor methods in FastcaseCase for retrieving case
   date(s). The indexes use these semantic dates to standardize listings.
 
 * Similarly, there is an accessor method in FastcaseCase for returning the
   canonical citation for a case (important for knowing what volume folder to
   put it in, when the first volume listed is not the U.S. or F. cite).
