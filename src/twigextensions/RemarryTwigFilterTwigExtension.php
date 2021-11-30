<?php
/**
 * Remarry Twig Filter plugin for Craft CMS 3.x
 *
 * Replaces the space character with a non-breaking space between the last to words of the given content.
 *
 * @link      http://www.belniakmedia.com
 * @copyright Copyright (c) 2017 Belniak Media Inc.
 */

namespace belniakmedia\remarrytwigfilter\twigextensions;


use Craft;
use DOMDocument;
use DOMNode;
use DOMText;

/**
 * @author    Belniak Media Inc.
 * @package   RemarryTwigFilter
 * @since     1.0.0
 */
class RemarryTwigFilterTwigExtension extends \Twig_Extension
{

	private DOMDocument $dom;
	private int $numWords = 2;
	private int $minimumWordCount;
	private bool $removeExtraSpaces;
	private bool $preventHyphenBreaks;


	// List of inline/inline-block elements from MDN
	// https://developer.mozilla.org/en-US/docs/Web/HTML/Inline_elements
	private $inlineElements = [
		'a',
		'abbr',
		'acronym',
		'audio',
		'b',
		'bdi',
		'bdo',
		'big',
		//'br', // We treat <br> similar to a block level element, add br to inline override to disable.
		'button',
		'canvas',
		'cite',
		'code',
		'data',
		'datalist',
		'del',
		'dfn',
		'em',
		'embed',
		'i',
		'iframe',
		'img',
		'input',
		'ins',
		'kbd',
		'label',
		'map',
		'mark',
		'meter',
		'noscript',
		'object',
		'output',
		'picture',
		'progress',
		'q',
		'ruby',
		's',
		'samp',
		'script',
		'select',
		'slot',
		'small',
		'span',
		'strong',
		'sub',
		'sup',
		'svg',
		'template',
		'textarea',
		'time',
		'u',
		'tt',
		'var',
		'video',
		'wbr'
	];

	// Public Methods
	// =========================================================================

	/**
	 * @inheritdoc
	 */
	public function getName()
	{
		return 'RemarryTwigFilter';
	}

	/**
	 * @inheritdoc
	 */
	public function getFilters()
	{
		return [
			new \Twig_SimpleFilter('remarry', [$this, 'reMarry'], ['pre_escape' => 'html', 'is_safe' => array('html')]),
		];
	}


	public function reMarry($content = "", $numWords = 2, $preventHyphenBreaks = true, $minimumWordCount = 4, $removeExtraSpaces = true, $overrideInlineElements = null) {

		// Set options as class props
		if(intval($numWords) > 2) { $this->numWords = (int) $numWords; }
		$this->preventHyphenBreaks = (bool) $preventHyphenBreaks;
		$this->minimumWordCount = (int) $minimumWordCount;
		$this->removeExtraSpaces = (bool) $removeExtraSpaces;

		// Override inline element list property if provided
		if(is_array($overrideInlineElements)) {
			$this->inlineElements = $overrideInlineElements;
		}

		// If there is HTML then we process HTML
		if(strip_tags($content) != $content) {

			// Initialize dom object with faked body root element and the the provided content within
			$this->dom = new DOMDocument;
			@$this->dom->loadHTML(
				'<body>' . mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8') . '</body>',
				LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

			// Process the dom tree adding non-breaking spaces to all text nodes per the provided parameters
			$this->processChildNodes($this->dom->documentElement);

			$result = $this->dom->saveHTML($this->dom->documentElement->getElementsByTagName('body')[0]);

			// remove temporary body tag from processed HTML
			$result = preg_replace('/^<body>/', '', $result);
			$result = preg_replace('/<\\\body>$/', '', $result);

			return $result;

		} else {

			// Otherwise we're processing plain text:

			// Remove multiple spaces (if allowed)
			if($this->removeExtraSpaces) {
				$content = trim(preg_replace('/\s+/', ' ', $content));
			}


			// if we have enough words, add the non-breaking-spaces:
			$words = preg_split('/\s/', $content);


			if(sizeof($words) >= $this->minimumWordCount) {


				$startAt = sizeof($words) - $this->numWords + 1;

				// Dont allow a negative start index
				if($startAt < 0) { $startAt = 0; }

				// Strip out non processable text
				$content = [];
				if($base_text = implode(' ', array_splice($words, 0, $startAt, []))) {

					$content[] = $base_text . "";

				}

				foreach($words as $idx => $word) {

					if(sizeof($content) || $idx > 0) {
						$content[] = '&nbsp;';
					}

					if($this->preventHyphenBreaks && strstr($word, '-') !== false) {
						$content[] = '<span style="white-space: nowrap;">' . $word . '</span>';
					} else {
						$content[] = $word;
					}

				}

				$content = implode('', $content);

			}

			return $content;

		}


	}

	private function processInlineBuffer(&$buffer, $node, $finalPass = false) {

		// Create fragment from inline buffer and process it as a collection
		$collection = $this->dom->createDocumentFragment();
		$collection_map = [];
		$collectionIndex = 0;
		foreach($buffer as $bufferIndex => $buffered_node) {
			$collection->appendChild($buffered_node->cloneNode(true));
			$collection_map[$collectionIndex] = $bufferIndex;
			$collectionIndex++;
		}

		// process collection nodes
		if(!$finalPass) {

			$this->processChildNodes($collection);

		} else {

			// get all nested text nodes into a single array of words
			$words = $this->getNestedText($collection);

			// only proceed if we have enough words within this collection of nodes.
			if (sizeof($words) >= $this->minimumWordCount) {

				if ($this->removeExtraSpaces) {

					// Trim first white space from first text node
					$this->replaceInCollection($collection, 1, 'asc', true);

					// Trim ending white space from the last text node in collection
					$this->replaceInCollection($collection, 1, 'desc', true);
				}

				// Work backwards through child list applying entity replacements
				$this->replaceInCollection($collection, $this->numWords, 'desc');

			}
		}

		// Write collection nodes back to parent node source based on index map
		foreach($collection->childNodes as $idx => $collectionNode) {

		}

		$node->replaceChild($collection, $node->childNodes->item($collection_map[$idx]));

		// reset buffer
		$buffer = [];

	}


	private function processChildNodes(DOMNode $element, $brTagPassThru = false) {

		foreach ($element->childNodes as $node) {

			if($node->hasChildNodes()) {

				// Treat <br> tags as "block level" element of sorts where content on either side of a <br>
				// tag will be processed as its own widow protection just like individual <li> tags insid of a <ul>
				// This behavior can be disabled by adding 'br' to the $overrideInlineElements setting.

				// Check if all child nodes are text nodes and
				// inline elements that we're ignoring

				$buffer = [];

				foreach($node->childNodes as $nodeIndex => $childNode) {

					// Guard clause to immeidately buffer DOMText elements.
					if($childNode instanceof DOMText) {
						$buffer[$nodeIndex] = $childNode;
						continue;
					}


					// TODO - WE NEED TO BUILD A NEW ELEMENT AS WE GO
					//      BUFFER UNTIL HIT BLOCK ELEMENT
					//			THEN PROCESS BUFFER AND SAVE NODES TO NEW ELEMENT
					//			THEN FLUSH BUFFER
					//			THEN PROCESS THE BLOCK ELEMENT AND SAVE NODE TO NEW ELEMENT
					// 		BUFFER UNTIL BR CONDITION
					// 			THEN PROCESS BUFFER AND SAVE NODES TO NEW ELEMENT
					//			THEN ADD BR TAG TO NEW ELEMENT
					//			THEN FUSH BUFFER
					//      BUFFER UNTIL OUT OF NODES
					//			THEN PROCESS BUFFER AND SAVE NODES TO NEW ELEMENT
					//			THEN FLUSH THE BUFFER
					//		FINALLY
					//			OVERWRITE $NODE WITH NEW ELEMENT



					// If this is not an inline element and its not a <br> tag we must treat this node list as objects
					// rather than text.
					if(!in_array($childNode->nodeName, $this->inlineElements) && $childNode->nodeName != 'br') {

						// Process block element via recursion if it has children.
						if($childNode->hasChildNodes()) {
							$this->processChildNodes($childNode);
						}

					} else {


						// If 'br' is not listed in the inlineElements list then we are treating <br> tags as a "block style
						// element. If this is a br tag and we are not on a pass thru run, connvert to the buffer into
						// fragment list of elements, process via recursion and then replace nodes in the buffer references.

						if(!$brTagPassThru && !in_array('br', $this->inlineElements)
							&& $childNode->nodeName == 'br' && sizeof($inline_buffer)) {

							$this->processInlineBuffer($inline_buffer, $node);

						} else {

							// implicit add to inline buffer for all other instances
							$inline_buffer[$nodeIndex] = $childNode;
						}
					}
				}


				// Process remainin inline buffer as text collection
				if(sizeof($inline_buffer)) {
					// get node collection from buffer
					$this->processInlineBuffer($inline_buffer, $node, true);
				}

			} else if ($node instanceof DOMText) {

				// Handle text node by adding non breaking spaces where applicable
				// If a nodelist is not retunred, do nothing.

				// remove white space from beginning and end since this is a solo text node
				// but only if removing extra white space is allowed
				if($this->removeExtraSpaces) {
					$text = trim($node->nodeValue);
				} else {
					$text = $node->nodeValue;
				}

				// Get the text as compiled node list if the content was processable
				list($node_list, $count) = $this->getNodeListFromText($text, $this->numWords);
				if($node_list) {
					// Content was replaced so lets replace original node with the new node list
					$node->parentNode->replaceChild($node_list, $node);

				}

			}
		}

	}

	private function replaceInCollection(DOMNode $element, $replacementsRemaining, $direction = 'desc', $trimWhiteSpaceMode = false) {

		if(!$replacementsRemaining) { return 0; }

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

			throw new \Exception("Invalid \$direction parameter.");
		}


		for($x = $startAt; $shouldContinue($x, $stopAt); $x = $iterate($x)) {

			$node = $element->childNodes->item($x);

			if($node instanceof DOMText) {

				if($trimWhiteSpaceMode) {

					// Trim white space of first text node found. In ascending direction we trim the beginnig, and
					// we trim then for descending direction.

					if($direction === 'asc') {
						$node->nodeValue = preg_replace('/^\s*/', '', $node->nodeValue);
					} else if ($direction === 'desc') {
						$node->nodeValue = preg_replace('/\s*$/', '', $node->nodeValue);
					}

					// Force loop exit. Our work here is done.
					return 0;

				} else {

					// Process the goods
					list($node_list, $count) = $this->getNodeListFromText($node->nodeValue, $replacementsRemaining, true);

					if($node_list) {

						// Content was replaced so lets replace original node with the new node list
						$node->parentNode->replaceChild($node_list, $node);

						// Update replacements remaining
						$replacementsRemaining -= $count;

					}
				}



			} elseif($node->hasChildNodes()) {

				// Recursion to get child text nodes in present direciton
				// & Update replacementsRemaining with response
				$replacementsRemaining = $this->replaceInCollection($node, $replacementsRemaining, $direction, $trimWhiteSpaceMode);

			}

			// If no more replacements are remaining, return 0 to cascade back to original caller
			if($replacementsRemaining <= 0) {
				return 0;
			}

		}

		return $replacementsRemaining;

	}

	private function getNestedText(DOMNode $element, $words = []) {

		foreach($element->childNodes as $node) {

			if($node instanceof DOMText) {

				$text = $node->nodeValue;

				// Remove multiple spaces (if allowed)
				if($this->removeExtraSpaces) {
					$text = preg_replace('/\s+/', ' ', $text);
				}

				$words = array_merge($words, explode(' ', $text));

			} elseif($node->hasChildNodes()) {

				$words = $this->getNestedText($node, $words);

			}
		}

		return $words;

	}

	private function getNodeListFromText($text, $numWords, $skipWordCount = false) {


		// Remove multiple spaces (if allowed)
		// Funkiness may occur here if $removeExtraSpaces is disabled and there are extra spaces in the content.
		// Disable at your own risk!
		if($this->removeExtraSpaces) {
			$text = preg_replace('/\s+/', ' ', $text);
		}

		// Trim and log white space characters from the beginning of the text content
		// which will be restored later in certain instances noted below.
		preg_match('/^(\s*)\S.*$/', $text, $matches);
		$prepend = $matches[1] ?? "";
		$text = ltrim($text);

		// Explode all words into an array for counting and processing.
		$words = preg_split('/\s/', $text);

		// Only word count enforcment is not skipped, the word count must at leat $minimumWordCount or the
		// content will not be processed.
		if($skipWordCount || sizeof($words) >= $this->minimumWordCount) {

			// Based on the number of words to keep together (specified by $numWords), subtract $numWords from
			// the number of words to get the index of which to start replacing spaces with non-breaking spaces.
			// We add + 1 to the index due to the way splice indexing works.
			$startAt = sizeof($words) - $numWords + 1;

			// Dont allow a negative start index
			if($startAt < 0) { $startAt = 0; }

			// Get base text up to the first position where non-breaking spaces are to be added.
			// Additionally, restore the removed white space to the beginning of $base_text
			$base_text = $prepend . implode(' ', array_splice($words, 0, $startAt, []));


			// Remaining "words" are to be appended with entity reference elements proceeding each word rather than a space.
			// We are going to add the new nodes to an array and then compile the dom fragment at the end because we need
			// to calculate whether or not to rtrim the base_text before adding it first to the node list and we cant do
			// that until after the loop.

			$count = 0;
			$newNodes = [];
			foreach($words as $idx => $word) {


				// Only insert the nbsp entity if there white space at the end of base text on the first loop iteration.
				// This would only be the case if there was no base text but prepended white space was restored.
				if($base_text !== "" || $idx > 0) {
					$count++;

					// Add nonbreaking space entity
					$newNodes[] = $this->dom->createEntityReference('nbsp');
				}

				// If $preventHyphenBreaks is enabled and a hyphen is present in the word,
				// we will inject the hyphenated word as a span element. Otherwise we will inject the word
				// normally as a text node.

				if($this->preventHyphenBreaks && strstr($word, '-') !== false) {

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


			// In the case where there is actual $base_text characters, nothing will be removed from $base_text since
			// the right most character of $base_text will not be white space. This is desired since the space that was
			// there was removed already from the "explosion" used to create the words array in the first place.

			// If $base_text is only white space due to having $prepend white-space restored after the construction of
			// the words array, we will remove the right most white space character to make up for adding the non
			// breaking space to the beginning of the word list as in this case the character the "nbsp" is replacing
			// has not yet been removed.

			if($count === sizeof($words)) {
				$base_text = preg_replace('/\s$/', '', $base_text);
			}

			// If base_text has a value, add it as a text node to the new node list
			if($base_text) {
				$node_list->appendChild($this->dom->createTextNode($base_text));
			}

			// Add the rest of the nodes to the node list
			foreach($newNodes as $newNode) {
				$node_list->appendChild($newNode);
			}

			return [$node_list, $count];
		}

		return [null, 0];
	}

}
