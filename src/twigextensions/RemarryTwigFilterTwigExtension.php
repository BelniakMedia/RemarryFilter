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

use belniakmedia\remarrytwigfilter\RemarryTwigFilter;
use Craft;

/**
 * @author    Belniak Media Inc.
 * @package   RemarryTwigFilter
 * @since     1.0.0
 */
class RemarryTwigFilterTwigExtension extends \Twig_Extension
{
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
			new \Twig_SimpleFilter('rmtest', [$this, 'reMarryTest'], ['pre_escape' => 'html', 'is_safe' => array('html')]),
		];
	}

//    /**
//     * @inheritdoc
//     */
//    public function getFunctions()
//    {
//        return [
//            new \Twig_SimpleFunction('someFilter', [$this, 'someInternalFunction']),
//        ];
//    }

	/**
	 * @param null $text
	 *
	 * @return string
	 */
	public function reMarryOldDoNotUse($text = null, $numWords = null)
	{

		if(!$numWords) { $numWords = 2; }
		if(!is_int($numWords) || $numWords < 2) { $numWords = 2; }

		if(strip_tags($text) != $text) {

			$tags = ['a', 'span', 'p', 'li', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'];
			$dom = new \DOMDocument;
			@$dom->loadHTML('<body>' . mb_convert_encoding($text, 'HTML-ENTITIES', 'UTF-8') . '</body>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
			foreach($tags as $tag) {

				$elements = $dom->getElementsByTagName($tag);
				for ($i = $elements->length - 1; $i >= 0; $i --) {
					$liveElement = $elements->item($i);
					$updatedValue = $this->str_lreplace(' ', "&nbsp;", trim($liveElement->nodeValue), $numWords);
					$liveElement->nodeValue = "";
					$liveElement->nodeValue = $updatedValue;
				}
			}

			$result = str_replace('<body>', '', str_replace('</body>', '', $dom->saveHTML()));

			if(substr($result, -1) != ">") {
				$result = $this->str_lreplace(' ', "&nbsp;", trim($result), $numWords);
			}


		} else {

			$result = $this->str_lreplace(' ', "&nbsp;", trim($text), $numWords);

		}

		return $result;
	}

	/**
	 * @param $element \DOMElement
	 * @return string
	 */
	private function innerHTML($element): string
	{
		$fragment = $element->ownerDocument->createDocumentFragment();
		while ($element->hasChildNodes()) {
			$fragment->appendChild($element->firstChild);
		}
		$html = $element->ownerDocument->saveHTML($fragment);
		if($fragment->hasChildNodes()) {
			$element->appendChild($fragment);
		}
		return $html;
	}


	// todo - Handle basic strings with possible sub html elementms
	// currently only works with plan strings or full html document.
	public function reMarry($text = null, $numWords = null)
	{

		if(!$numWords) { $numWords = 2; }
		if(!is_int($numWords) || $numWords < 2) { $numWords = 2; }

		if(strip_tags($text) != $text) {

			$dom = new \DOMDocument;
			@$dom->loadHTML('<body>' . mb_convert_encoding($text, 'HTML-ENTITIES', 'UTF-8') . '</body>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

			$rootElements = [];

			foreach ($dom->documentElement->childNodes as $node) {

				// get root child element node
				if(!($node instanceof \DOMElement)) {
					continue;
				}
				$html = $this->innerHTML($node);

				// log all html tags by index.
				preg_match_all('/<\/?\w+((\s+\w+(\s*=\s*(?:".*?"|\'.*?\'|[\^\'">\s]+))?)+\s*|\s*)\/?\s*>*>/', $html, $matches);

				$matches = $matches[0];


				// replace tags with placeholders
				// we do this to remove spaces within tags from the equation later on.
				foreach($matches as $idx => $match) {
					$html = preg_replace('/' . preg_quote($match, '/') . '/', '||TAG' . $idx . '||', $html, 1);
				}

				// remove multiple spaces
				$html = preg_replace('/\s+/', ' ', $html);

				// replace last X spaces with non-breaking spaces
				$result = $this->str_lreplace(' ', "&nbsp;", trim($html), $numWords);

				// replace placeholders with corresponding tags
				foreach($matches as $idx => $match) {
					$result = str_replace('||TAG' . $idx . '||', $match, $result);
				}

				// get root element attributes string
				$attributes = [];
				if(is_array($node->attributes)) {
					foreach ($node->attributes as $attr) {
						$attributes[] = $attr->nodeName . "=\"{$attr->nodeValue}\"";
					}
				}

				if(sizeof($attributes)) {
					$attr = " " . implode(" ", $attributes);
				} else {
					$attr = "";
				}

				// replace html in node and prep for return
				$tn = isset($node->tagName) ? $node->tagName : 'Unknown';
				$rootElements[] = "<" . $tn . $attr . ">" . $result . "</" . $tn . ">";
			}

			// join update root element htmls.
			$result = implode("", $rootElements);


		} else {

			$result = $this->str_lreplace(' ', "&nbsp;", trim($text), $numWords);

		}

		return $result;
	}

	private function str_lreplace($search, $replace, $subject, $numWords)
	{
		for($x = 1; $x < $numWords; $x++) {
			$pos = strrpos($subject, $search);

			if ($pos !== false) {
				$subject = substr_replace($subject, $replace, $pos, strlen($search));
			}
		}

		return $subject;
	}
}
