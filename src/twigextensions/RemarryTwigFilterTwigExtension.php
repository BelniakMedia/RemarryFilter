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

use DOMDocument;
use DOMNode;
use DOMText;
use Exception;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * @author    Belniak Media Inc.
 * @package   RemarryTwigFilter
 * @since     1.0.0
 */
class RemarryTwigFilterTwigExtension extends AbstractExtension
{

	private DOMDocument $dom;
	private int $numWords = 2;
	private int $minimumWordCount;
	private bool $removeExtraSpaces;
	private bool $preventHyphenBreaks;


	// List of inline/inline-block elements from MDN
	// https://developer.mozilla.org/en-US/docs/Web/HTML/Inline_elements

	// We treat <br> similar to a block level element, add br to inline override to disable.
	// The system does not parse CSS so if you have a block level element behaving
	// as an inline element, and you want it processed as such, you'll need to use the override
	// setting to add that element tag name to this list.

	private $inlineElements = [
		'a',
		'abbr',
		'acronym',
		'audio',
		'b',
		'bdi',
		'bdo',
		'big',
		//'br',
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
			new TwigFilter('remarry', [$this, 'reMarry'], ['pre_escape' => 'html', 'is_safe' => array('html')]),
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

			// Process the dom tree adding non-breaking spaces where applicable (heavy recursion within)
			$documentElement = $this->processChildNodes($this->dom->documentElement);

			// Get the processed HTML to be returned
			$result = $this->dom->saveHTML($documentElement);

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

				// Get the position of words where non breaking spaces should start being applied
				$startAt = sizeof($words) - $this->numWords + 1;

				// Dont allow a negative start index
				if($startAt < 0) { $startAt = 0; }

				// Strip out non processable text
				$content = [];
				if($base_text = implode(' ', array_splice($words, 0, $startAt, []))) {

					// Reappend stripped out starting text
					// that will not be processed.
					$content[] = $base_text;

				}

				// Add non breaking spaces before each word as
				// they are added back into the content array
				foreach($words as $idx => $word) {

					// Add the nonbreaking space on the first iteration only
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

	private function processNodeBuffer(&$buffer) {

		// Create fragment/domNodeList from text node & inline elements buffer
		$collection = $this->dom->createDocumentFragment();
		foreach($buffer as $buffered_node) {
			$collection->appendChild($buffered_node);
		}

		// Process collection (main program function)

		// get all nested text nodes into a single array of words
		$words = $this->getNestedText($collection);

		// Only proceed if we have enough words within this collection of nodes.
		if (sizeof($words) >= $this->minimumWordCount) {

			if ($this->removeExtraSpaces) {

				// Trim first white space from first text node
				$this->replaceInCollection($collection, 2, 'asc', true);

				// Trim ending white space from the last text node in collection
				$this->replaceInCollection($collection, 2, 'desc', true);
			}

			// Work backwards through child list applying entity replacements
			$this->replaceInCollection($collection, $this->numWords, 'desc');

		}

		// Reset the node buffer
		$buffer = [];

		// Return processed collection
		return $collection;

	}

	private function processChildNodes(DOMNode $element): DOMNode {

		$newElement = $element->cloneNode(false);
		$buffer = [];

		foreach ($element->childNodes as $childNode) {

			// Guard clause to immediately buffer DOMText elements and continue
			if($childNode instanceof DOMText) {
				$buffer[] = $childNode;
				continue;
			}

			// The $childNode is not an inline element and its not a <br> tag, so we must
			// treat this node list as a block level object rather than text/inline.
			// We will use recursion to handle this :)

			if(!in_array($childNode->nodeName, $this->inlineElements) && $childNode->nodeName != 'br') {

				// Before we process the block collection, check if we have buffered
				// inline/text elements, process it into newElement first to keep the
				// order of child elements correct in the replacement element object.

				if(sizeof($buffer)) {

					// Process inline/text node buffer as collection
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

				// Catch code to treate text on either side of <br> tags as an isolated instance to be protected
				// from widows - Since our goal is to prevent widows, it makes sens to do this for <br> tags too.

				// To disable this feature, add 'br' to the $inlineElements list via the $overrideInlineElements setting.

				// If this is a <br> tag, process the buffer as a collection and add it to the new replacement element
				// Additionally, manually add a new <br> element after the processed text since we do not include it
				// in the collection to be processed.

				if(!in_array('br', $this->inlineElements) && $childNode->nodeName == 'br') {

					// If there is buffered nodes, process them as a collection

					if(sizeof($buffer)) {

						// Process buffer into newNode first to keep the
						// order of elements correct (Also clears the buffer)
						$collection = $this->processNodeBuffer($buffer);

						// Write collection to the new node
						$newElement->appendChild($collection);

					}

					// Add BR tag implicitly
					$newElement->appendChild($this->dom->createElement('br'));

				} else {

					// If 'br' is listed in inline elements, then it will be treated here like all other inline
					// elements and will not have widow protection on text that proceeds them.

					// All inline elements, add to node buffer to be processed as a collection.
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

			throw new Exception("Invalid `\$direction` parameter.");

		}


		for($x = $startAt; $shouldContinue($x, $stopAt); $x = $iterate($x)) {

			$node = $element->childNodes->item($x);

			if($node instanceof DOMText) {

				if($trimWhiteSpaceMode) {

					// Trim white space of first text node found. In ascending direction we trim the beginning, and
					// we trim then for descending direction.

					if($direction === 'asc') {
						$node->nodeValue = preg_replace('/^\s*/', '', $node->nodeValue);
					} else if ($direction === 'desc') {
						$node->nodeValue = preg_replace('/\s*$/', '', $node->nodeValue);
					}

					// Force loop exit. Our work here is done.
					return 0;

				} else {

					// Process the goods (where the magic happens)

					$text = $node->nodeValue;

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


					// Based on the number of words to keep together (specified by $numWords), subtract $numWords from
					// the number of words to get the index of which to start replacing spaces with non-breaking spaces.
					// We add + 1 to the index due to the way splice indexing works.
					$protectionStart = sizeof($words) - $replacementsRemaining + 1;

					// Don't allow a negative start index
					if($protectionStart < 0) { $protectionStart = 0; }

					// Get base text up to the first position where non-breaking spaces are to be added.
					// Additionally, restore the removed white space to the beginning of $base_text
					$base_text = $prepend . implode(' ', array_splice($words, 0, $protectionStart, []));


					// Remaining "words" are to be appended with entity reference elements proceeding each word rather than a space.
					// We are going to add the new nodes to an array and then compile the dom fragment at the end because we need
					// to calculate whether to rtrim the base_text before adding it first to the node list, and we can't do
					// that until after the loop.

					$count = 0;
					$newNodes = [];
					foreach($words as $idx => $word) {

						// Only insert the nbsp entity if their white space at the end of base text on the first loop iteration.
						// This would only be the case if there was no base text but prepended white space was restored.
						if($base_text !== "" || $idx > 0) {
							$count++;

							// Add nonbreaking space entity
							$newNodes[] = $this->dom->createEntityReference('nbsp');
						}

						// If $preventHyphenBreaks is enabled and a hyphen is present in the word,
						// we will inject the hyphenated word as a span element. Otherwise, we will inject the word
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
					// the words array, we will remove the right most white space character to make up for adding the non-breaking
					// space to the beginning of the word list as in this case the character the "nbsp" is replacing
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


					if($node_list->hasChildNodes()) {

						// Content was replaced so lets replace original node with the new node list
						$node->parentNode->replaceChild($node_list, $node);

						// Update replacements remaining (count + 1) because replacements remain is based on
						// word concatenations, not space replacements... numWords of 2 means 2 words will be linked
						// and a single space will be replaced. Therefore, count will always be one less than the
						// number of words linked.
						$replacementsRemaining -= ($count + 1);

					}
				}



			} elseif($node->hasChildNodes()) {

				// Recursion to get child text nodes in present direction
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


}
