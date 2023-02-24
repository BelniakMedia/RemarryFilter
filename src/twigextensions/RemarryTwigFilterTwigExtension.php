<?php
/**
 * Remarry Twig Filter plugin for Craft CMS 4.x
 *
 * Replaces the space character with a non-breaking space between the last N words of the given content.
 *
 * @link      http://www.belniakmedia.com
 * @copyright Copyright (c) 2017 Belniak Media Inc.
 */

namespace belniakmedia\remarrytwigfilter\twigextensions;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use Exception;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * @author    Belniak Media Inc.
 * @package   RemarryTwigFilter
 * @since     2.0.0
 */
class RemarryTwigFilterTwigExtension extends AbstractExtension
{

	private DOMDocument $dom;

	private int $numWords = 2;

	private int $minimumWordCount;

	private bool $removeExtraSpaces;

	private bool $preventHyphenBreaks;

	/**
	 * A curated list of inline/inline-block elements derived from MDN that would reasonably be part a text collection.
	 * https://developer.mozilla.org/en-US/docs/Web/HTML/Inline_elements
	 *
	 * Note: 'br' is not listed because We treat <br> similar to a block level element, add br to inline override to disable.
	 * The system does not parse CSS so if you have a block level element behaving as an inline element, and you want it
	 * processed as such, you'll need to use the override setting to add that element tag name to this list.
	 *
	 * @var string[] $inlineElements
	 */
	private array $inlineElements = [
		'a',
		'abbr',
		'acronym',
		'b',
		'bdi',
		'bdo',
		'big',
		'cite',
		'data',
		'del',
		'dfn',
		'em',
		'i',
		'ins',
		'kbd',
		'label',
		'mark',
		'q',
		's',
		'samp',
		'small',
		'span',
		'strong',
		'time',
		'u',
		'tt',
		'var',
	];

	/**
	 * A curated list of inline/inline-block elements derived from MDN that should be ignored and passed through as-is
	 * when found within collection of actionable nodes/elements
	 *
	 * https://developer.mozilla.org/en-US/docs/Web/HTML/
	 *
	 * @var string[] $ignoredInlineElements
	 */
	private array $ignoredInlineElements = [
		'audio',
		'button',
		'canvas',
		'code',
		'datalist',
		'img',
		'math',
		'meter',
		'noscript',
		'object',
		'output',
		'param',
		'picture',
		'pre',
		'progress',
		'ruby',
		'script',
		'select',
		'sub',
		'sup',
		'svg',
		'textarea',
		'track',
		'video',
		'wbr',
		'xmp',
	];

	// Public Methods
	// =========================================================================

	/**
	 * @inheritdoc
	 */
	public function getFilters(): array
	{
		return [
			new TwigFilter('remarry', [$this, 'remarry'], ['pre_escape' => 'html', 'is_safe' => array('html')]),
		];
	}

	/**
	 * @inheritdoc
	 */
	public function getFunctions(): array
	{
		return [
			new TwigFunction('remarryAddElement', [$this, 'addElement']),
			new TwigFunction('remarryRemElement', [$this, 'remElement']),
		];
	}

	/**
	 * Adds a list of element names to the provided $elementList.
	 *
	 * @param array $elements The list of elements to add to the list, e.g.: ['a', 'br', 'span']
	 * @param string|array $elementList 'inline', 'ignored' or Array of elements to start with. When a string is passed
	 * 								    in, the corresponding built-in list will be used. Array value should be a list
	 * 									of tag names, e.g.: ['a', 'br', 'span']
	 * @throws Exception
	 * @return array
	 * @noinspection DuplicatedCode
	 */
	public function addElement(array $elements, $elementList): array
	{
		// Validate $elements, make sure it has content.
		if(!sizeof($elements)) {
			throw new Exception("The \$elements parameter must be an array with at least one element.");
		}

		// Use built in list if one is not supplied
		if(!is_array($elementList)) {
			if($elementList === 'inline') $elementList = $this->inlineElements;
			if($elementList === 'ignored') $elementList = $this->ignoredInlineElements;
		}

		// Ensure $elementList is valid.
		if(!is_array($elementList)) {
			throw new Exception("The \$elementList parameter must be 'inline', 'ignored', or an array.");
		}

		// return list with elements added
		return array_merge($this->formatElementList($elementList), $this->formatElementList($elements));
	}

	/**
	 * Removes a list of element names from the provided $elementList.
	 *
	 * @param array $elements The list of elements to remove from $elementList, e.g.: ['a', 'br', 'span']
	 * @param string|array $elementList 'inline', 'ignored' or Array of elements to start with. When a string is passed
	 * 								    in, the corresponding built-in list will be used. Array value should be a list
	 * 									of tag names, e.g.: ['a', 'br', 'span']
	 * @return array
	 * @throws Exception
	 * @noinspection DuplicatedCode
	 */
	public function remElement(array $elements, $elementList = null): array
	{

		// Validate $elements, make sure it has content.
		if(!sizeof($elements)) {
			throw new Exception("The \$elements parameter must be an array with at least one element.");
		}

		// Use built in list if one is not supplied
		if(!is_array($elementList)) {
			if($elementList === 'inline') $elementList = $this->inlineElements;
			if($elementList === 'ignored') $elementList = $this->ignoredInlineElements;
		}

		// Ensure $elementList is valid.
		if(!is_array($elementList)) {
			throw new Exception("The \$elementList parameter must be 'inline', 'ignored', or an array.");
		}

		// Return list with elements removed.
		return array_diff($this->formatElementList($elementList), $this->formatElementList($elements));
	}

	/**
	 * Adds paragraph widow protection by replacing spaces at the end of actionable text content with non-breaking
	 * space entities. Will also wrap hyphenated words inside the widow protection zone with a span styled with a
	 * white-space: nowrap attribute when $preventHyphenBreaks is enabled. Actionable text content refers to any
	 * plain text value passed in, or if HTML is passed in, a group of concurrent text nodes and inline elements where
	 * the combined text content has more words than the $minimumWordCount setting.
	 *
	 * @param mixed $content The content the filter is being run on
	 *
	 * @param int|array $optsOrNumWords An associative array with options or an (int)number of words. Remaining parameters
	 * 									are ignored when option array is provided. Otherwise, number of words cannot be
	 * 									less than 2, and will be forced to 2 if a number lower is provided.
	 *
	 * Valid options for $optsOrNumWords as options[]
	 * int		  'numWords' 				Number of words to bind. A minimum value of 2 is enforced.
	 * boolean	  'preventHyphenBreaks' 	Whether to wrap hyphenated words within the protection zone of actionable content.
	 * int 		  'minimumWordCount'		Minimum number of words required in text content for processing to occur.
	 * boolean    'removeExtraSpaces'		It is recommended this be left on but can be turned off if needed. Will trim white
	 * 								    	space from the beginning and ending of actionable text collections, and also
	 * 								    	replace one or more consecutive white space characters with a single space before
	 * array|null 'overrideInlineElements'	An Array of element tag names to override the internal inline element list with.
	 * array|null 'overrideIgnoredElements' An Array of element tag names to override the internal ignored inline element list with.
	 *
	 * Default Options:
	 * 	[
	 * 		'numWords' => 2,
	 *		'preventHyphenBreaks' => true,
	 *		'minimumWordCount' => 4,
	 *		'removeExtraSpaces' => true,
	 *		'overrideInlineElements' => null,
	 *		'overrideIgnoredElements' => null,
	 *	]
	 *
	 * @return mixed
	 */
	public function remarry($content, $optsOrNumWords = 2)
	{

		// Ensure string is passed in. If not, hope the object has a good __toString() method implemented...
		if(!is_string($content)) {
			$content = (string)$content;
		}

		// Set default options
		$defaultOptions = [
			'numWords' => 2,
			'preventHyphenBreaks' => true,
			'minimumWordCount' => 4,
			'removeExtraSpaces' => true,
			'overrideInlineElements' => null,
			'overrideIgnoredElements' => null,
		];

		// Merge supplied options array over defaults
		// All other params are ignored if option array is supplied
		if(is_array($optsOrNumWords)) {
			$options = array_merge($defaultOptions, $optsOrNumWords);
		} else {
			$options = array_merge($defaultOptions, ['numWords' => $optsOrNumWords]);
		}

		// Enforce two word minimum
		if($options['numWords'] < 2) { $options['numWords'] = 2; }

		// Set options as class properties
		$this->numWords = $options['numWords'];
		$this->preventHyphenBreaks = $options['preventHyphenBreaks'];
		$this->minimumWordCount = $options['minimumWordCount'];
		$this->removeExtraSpaces = $options['removeExtraSpaces'];
		if($options['overrideInlineElements']) {
			$this->inlineElements = $options['overrideInlineElements'];
		}
		if($options['overrideIgnoredElements']) {
			$this->ignoredInlineElements = $options['overrideIgnoredElements'];
		}

		// If there is HTML then we process HTML
		if(strip_tags($content) != $content) {

			// Initialize dom object with faked body root element and the provided content within
			$this->dom = new DOMDocument;
			@$this->dom->loadHTML(
				'<body>' . mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8') . '</body>',
				LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

			// Process the dom tree adding non-breaking spaces where applicable (heavy recursion within)
			$documentElement = $this->processChildNodes($this->dom->documentElement);

			// Get the processed HTML to be returned
			$html = $this->dom->saveHTML($documentElement);
			$result = substr(substr($html, 6), 0, -7);

		} else {

			// Handle plain text (non HTML data)

			// Pre-store starting content as result in case nothing is processed.
			$result = $content;


			// Remove multiple spaces (if allowed)
			if($this->removeExtraSpaces) {
				$content = trim(preg_replace('/\s+/', ' ', $content));
			}

			// Only if we have enough words, add the non-breaking-spaces:
			$words = preg_split('/\s/', $content);


			if(sizeof($words) >= $this->minimumWordCount) {

				// Get the position of words where non-breaking spaces should start being applied
				$startAt = sizeof($words) - $this->numWords + 1;

				// Don't allow a negative start index
				if($startAt < 0) { $startAt = 0; }

				// Strip out non processable text
				$content = [];
				if($base_text = implode(' ', array_splice($words, 0, $startAt, []))) {

					// Re-append stripped out starting text
					// that will not be processed.
					$content[] = $base_text;

				}

				// Add non-breaking spaces before each word as
				// they are added back into the content array
				foreach($words as $idx => $word) {

					// Add the non-breaking space on the first iteration only
					// when there is content that proceeds the protected word set
					if(sizeof($content) || $idx > 0) {
						$content[] = '&nbsp;';
					}

					// Handle hyphen break protection when enabled, whthin the non-break protected word set
					// Or add the word as is if no hyphen is detected.
					if($this->preventHyphenBreaks && strstr($word, '-') !== false) {
						$content[] = '<span style="white-space: nowrap;">' . $word . '</span>';
					} else {
						$content[] = $word;
					}

				}

				// Store processed text as result to be returned
				$result = implode('', $content);

			}

		}

		return $result;

	}


	// Private Methods
	// =========================================================================

	/**
	 * Trims and converts to lower case all elements of supplied array.
	 * @param array $elements List of element names to format, e.g.: ['a', 'br', 'span']
	 * @return array
	 */
	private function formatElementList(array $elements): array
	{
		return array_map('trim', array_map('trim', $elements));
	}


	/**
	 * The main recursive function that traverses the HTML DOM tree of the supplied block level element to create
	 * actionable text content group through the use of a buffer array. When a block level element is encountered the
	 * previously buffered elements are processed together as a collection, then the block element is recursively passed
	 * back to this function for traversal. Normally this treats <br> tags the same as a block level element where all
	 * buffered nodes previous to it are processed as an actionable text collection. This behavior can be disabled by
	 * adding ['br'] to the $overrideInlineElements setting to force treating a <br> tag as a normal inline element.
	 *
	 * @param DOMElement $element The DOMElement object to traverse and process as a block level element.
	 * @return DOMElement
	 */
	private function processChildNodes(DOMElement $element): DOMElement {

		$newElement = $element->cloneNode(false);
		$buffer = [];

        foreach ($element->childNodes as $childNodeRef) {

            // Make clone of childNodeRef to prevent `appendChild` from mutating the loop while iterating it...
            $childNode = $childNodeRef->cloneNode(true);

            // Guard clause to immediately buffer DOMText elements and elements listed in the $ignoredInlineElements
			// array and continue
			if($childNode instanceof DOMText || in_array($childNode->nodeName, $this->ignoredInlineElements)) {
				$buffer[] = $childNode;
				continue;
			}

			// The $childNode is not an inline element, and it's not a <br> tag, so we must
			// treat this node list as a block level object rather than text/inline.
			// We will use recursion to handle this :)
			if(!in_array($childNode->nodeName, $this->inlineElements) && $childNode->nodeName != 'br') {

				// Before we process the block collection, check if we have buffered
				// inline/text elements, process it into newElement first to keep the
				// order of child elements correct in the replacement element object.

				if(sizeof($buffer)) {

					// Process inline/text node buffer an actionable text collection
					// (Also clears the buffer)
					$collection = $this->processNodeBuffer($buffer);

					// Write collection to the replacement element object
					$newElement->appendChild($collection);
				}

				// Process block element via recursion if it has children.
				if($childNode->hasChildNodes()) {

					// Process child node as a collection ($childNode is updated via reference)
					$collection = $this->processChildNodes($childNode);

					// Write processed childNode to the new node
					$newElement->appendChild($collection);

				} else {

					// Otherwise, we'll just add it directly back, so we don't lose it.
					$newElement->appendChild($childNode);

				}

			} else {

				// If this is a <br> tag, process the buffer as a collection and add it to the new replacement element
				// Additionally, manually add a new <br> element after the processed text since we do not include it
				// in the collection to be processed as to not lose it.

				if(!in_array('br', $this->inlineElements) && $childNode->nodeName == 'br') {

					// Treat text/inline nodes proceeding a <br> tag as an isolated instance
					// to be protected from widows. Since our goal is to prevent widows, it makes sense to do this.

					// To disable this feature, add 'br' to the $inlineElements list
					//via the $overrideInlineElements setting.

					// If there is buffered nodes, process them as a collection
					if(sizeof($buffer)) {

						// Process buffer into newNode first to keep the
						// order of elements correct (Also clears the buffer)
						$collection = $this->processNodeBuffer($buffer);

						// Write collection to the new node
						$newElement->appendChild($collection);

					}

					// Add <br> tag implicitly
					$newElement->appendChild($this->dom->createElement('br'));

				} else {

					// If 'br' is listed in inline elements, then it will be treated here like all other inline
					// elements and will not specifically have widow protection on text that proceeds them.

					// All inline elements: Add to node buffer to be processed as part of an actionable text collection.
					$buffer[] = $childNode;

				}
			}
		}


		// Process remaining buffered elements as text collection
		if(sizeof($buffer)) {

			// Get the processed node collection from buffer (Also clears the buffer)
			$collection = $this->processNodeBuffer($buffer);

			// Write collection to the new node
			$newElement->appendChild($collection);

		}

		// Return the new replacement element as all children have been processed.
		return $newElement;

	}

	/**
	 * Takes a buffer (array) of DOMText and Inline DOMElement nodes. Creates a new document fragment from them,
	 * processes the fragment as an actionable text collection, clears the buffer array and returns the processed fragment.
	 *
	 * @param DOMNode[] $buffer An array of buffered DOMNodes to be processed as a text collection
	 * @return DOMNode
	 */
	private function processNodeBuffer(array &$buffer): DOMNode {

		// Create fragment/domNodeList from text node & inline elements buffer
		$collection = $this->dom->createDocumentFragment();
		foreach($buffer as $buffered_node) {
			$collection->appendChild($buffered_node);
		}

		// get all nested text nodes into a single array of words
		$words = $this->getNestedText($collection);

		// Only proceed if we have enough words within this collection of nodes.
		if (sizeof($words) >= $this->minimumWordCount) {

			// If allowed in settings, remove all starting white space from first text node in collection,
			// and all ending white space in the last text node in the collection.
			if ($this->removeExtraSpaces) {

				// Trim first white space from first text node
				$this->replaceInCollection($collection, 2, 'asc', true);

				// Trim ending white space from the last text node in collection
				$this->replaceInCollection($collection, 2, 'desc', true);
			}

			// Work backwards through child list applying entity replacements
			$this->replaceInCollection($collection, $this->numWords);

		}

		// Reset the node buffer
		$buffer = [];

		// Return processed collection
		return $collection;

	}

	/**
	 * The main workhorse of the filter. This is called by the buffer processing function to mutate the actionable
	 * text collection (node list). It treats the combined text of all nodes as a full text and adds the non-breaking
	 * spaces to the end of the collection which means that the non-breaking spaces can work across multiple inline
	 * elements and text nodes alike.
	 *
	 * @param DOMNode $element The actionable collection (childNodes of this) to process.
	 * @param int $replacementsRemaining Number of space -> &nbsp; conversions remaining for the collection.
	 * 									 (Used to determine need for recursion)
	 * @param string $direction Set to 'asc' to work front to back. Set to 'desc' to work back to front.
	 * @param false $trimWhiteSpaceMode Enables an alternate processing mode where all white space is trimmed from the
	 * 									beginning of the first detected DOMText node when $direction is 'asc' and removes
	 * 									all white space from the end of the first detected DOMText (last actual) when
	 * 									$direction is 'desc'
	 * @return int
	 */
	private function replaceInCollection(DOMNode $element, int $replacementsRemaining, string $direction = 'desc', bool $trimWhiteSpaceMode = false): int {

		// Guard clause - Cascade back to caller when no more replacements are needed.
		if(!$replacementsRemaining) { return 0; }

		// Set up for loop signature closures based on $direction parameter.
		if($direction === 'desc') {

			$startAt = sizeof($element->childNodes) - 1;
			$stopAt = 0;
			$shouldContinue = function($x, $stopAt) {
				if($x >= $stopAt) { return true; }
				return false;
			};
			$iterate = function($x) {
				return $x - 1;
			};

		} elseif($direction === 'asc') {

			$startAt = 0;
			$stopAt = sizeof($element->childNodes);
			$shouldContinue = function($x, $stopAt) {
				if($x < $stopAt) {
					return true;
				}
				return false;
			};
			$iterate = function($x) {
				return $x + 1;
			};

		} else {

			// This is only here to prevent IDE inspection from flagging the for loop signature below.
			// As this is private function $direction will always either be 'asc' or 'desc'.
			return 0;

		}


		// Process the collection in the direction specified
		for($x = $startAt; $shouldContinue($x, $stopAt); $x = $iterate($x)) {

			// Get current node in collection
			$node = $element->childNodes->item($x);

			// We only process DOMText nodes here, if other node type is detected recursion will occur to look
			// for more DOMText nodes
			if($node instanceof DOMText) {

				// Handles $trimWhiteSpaceMode Enabled
				if ($trimWhiteSpaceMode) {
					// Trim white space of first text node found. In ascending direction we trim the beginning, and
					// we trim then for descending direction.

					if ($direction === 'asc') {
						$node->nodeValue = preg_replace('/^\s*/', '', $node->nodeValue);
					} else if ($direction === 'desc') {
						$node->nodeValue = preg_replace('/\s*$/', '', $node->nodeValue);
					}

					// Break loop and force recursion exit.
					// Our work here is done.
					return 0;


					// Normal processing (where the magic happens)
				} else {

					// Get the node text content
					$text = $node->nodeValue;

					// Remove multiple spaces (if allowed) - Disable at your own risk!
					// Funkiness may occur here if $removeExtraSpaces is disabled and there are extra spaces in the
					// content. Concurrent spaces will count as words beyond this point.
					if ($this->removeExtraSpaces) {
						$text = preg_replace('/\s+/', ' ', $text);
					}

					// Explode all words into an array for counting and processing.
					$words = preg_split('/\s/', $text);

					// Convert empty word elements to spaces
					foreach($words as $k => $v) { if($v === "") { $words[$k] = " "; }}

					// Based on the number of words to keep together (specified by $replacementsRemaining), subtract
					// $replacementsRemaining from the number of words to get the index of which to start replacing
					// spaces with non-breaking spaces. We add + 1 to the index due to the way splice indexing works.
					$protectionStart = sizeof($words) - $replacementsRemaining + 1;

					// Don't allow a negative start index
					if ($protectionStart < 0) {
						$protectionStart = 0;
					}

					// Get base text up to the first position where non-breaking spaces are to be added.
					$base_text = implode(' ', array_splice($words, 0, $protectionStart));

					// Remaining "words" are to be appended with entity reference elements proceeding each word rather than a space.
					// We are going to add the new nodes to an array and then compile the dom fragment at the end because we need
					// to calculate whether to rtrim the base_text before adding it first to the node list, and we can't do
					// that until after the loop.

					$count = 0;
					$newNodes = [];
					foreach ($words as $idx => $word) {

						// Only add the nbsp entity if $base_text is not blank on the first iteration. Additionally,
						// count the insertion, so we can determine if all replacements were made after the loop completes.
						if ($base_text !== "" || $idx > 0) {

							// Count it
							$count++;

							// Add non-breaking space entity
							$newNodes[] = $this->dom->createEntityReference('nbsp');
						}

						// If $preventHyphenBreaks is enabled and a hyphen is present in the word,
						// we will inject the hyphenated word as a span element. Otherwise, we will inject the word
						// normally as a text node.

						if ($this->preventHyphenBreaks && strstr($word, '-') !== false) {

							// Create <span style="white-space: nowrap"> element and place hyphenated word inside
							$span = $this->dom->createElement('span', $word);
							$span->setAttribute('style', 'white-space: nowrap;');

							// Add the span element to the node list
							$newNodes[] = $span;

						} else {

							// Add the word as a text node to the node list
							$newNodes[] = $this->dom->createTextNode($word);

						}
					}


					// Create new node list
					$node_list = $this->dom->createDocumentFragment();

					// When $count === sizeof($words), the first element of $newNodes will be a nbsp entity. Therefore,
					// we need to make sure that if the right most character of the $base_text is a white space character
					// it will be removed so the added nbsp acts as a replacement rather than an insertion when the
					// nodes are saved back out has an HTML document.
					if ($count === sizeof($words)) {
						$base_text = preg_replace('/\s$/', '', $base_text);
					}

					// First node in the new fragment will be the $base_text value if it has a value.
					if ($base_text) {
						$node_list->appendChild($this->dom->createTextNode($base_text));
					}

					// Add the nbsp/word nodes to the new fragment in order.
					foreach ($newNodes as $newNode) {
						$node_list->appendChild($newNode);
					}


					// Replace the parent node with the "protected" fragment if we have processed data.
					if ($node_list->hasChildNodes()) {

						// Replace original node with the new node list
						$node->parentNode->replaceChild($node_list, $node);

						// Update replacements remaining (count + 1) because replacements remain is based on
						// word concatenations, not space replacements. $replacementsRemaining of 2 means 2 words will
						// be linked and a single space will be replaced. Therefore, count will always be one less than
						// the number of words linked.
						$replacementsRemaining -= ($count + 1);

					}
				}


			// For non-DOMText nodes, process children via recursion when the element is not listed as an ignored element.
			} elseif(!in_array($node->nodeName, $this->ignoredInlineElements) && $node->hasChildNodes()) {

				// Recursion to get child nodes in current direction
				// & Update replacementsRemaining with response
				$replacementsRemaining = $this->replaceInCollection($node, $replacementsRemaining, $direction, $trimWhiteSpaceMode);

			}

			// If no more replacements are remaining, return 0 to cascade back to original caller
			if($replacementsRemaining <= 0) {
				return 0;
			}

		}

		// Indicate to possible recursive caller how many more replacements are needed if any.
		return $replacementsRemaining;

	}

	/**
	 * Recursively traverse the child nodes of the DOMNode $element object passed in to collect all words as an array
	 * from the entire sub dom tree of the element.
	 *
	 * @param DOMNode $element The DOMNode object to traverse to collect words from.
	 * @param array $words The previously collected words - Used by recursive calls only.
	 * @return array
	 */
	private function getNestedText(DOMNode $element, array $words = []): array {

		foreach($element->childNodes as $node) {

			if($node instanceof DOMText) {

				// Get the text value of the node.
				$text = $node->nodeValue;

				// Trim and remove multiple spaces (even if normally not allowed)
				// since the only purpose of this function is to count the words. Extra spaces will cause the word
				// count to be wrong.
				$text = trim(preg_replace('/\s+/', ' ', $text));

				// Add all words in text to the word list.
				$words = array_merge($words, explode(' ', $text));

			// For non-DOMText nodes, process children via recursion when the element is not listed as an ignored element.
			} elseif(!in_array($node->nodeName, $this->ignoredInlineElements) && $node->hasChildNodes()) {

				// Recursion to traverse within
				$words = $this->getNestedText($node, $words);

			}
		}

		return $words;

	}


}
