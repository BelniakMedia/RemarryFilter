
![Plugin Icon](src/icon.svg)
# remarry Twig Filter plugin for Craft CMS 4.x
## Version 2.X.X

_See 2.0.x for Craft CMS 3.x support_

**NOTE: If you happen to be using a 1.x version of this plugin I highly recommend that you upgrade to the 2.x version. It _SHOULD_ be completely backwards compatible, and it has been completely rewritten. It is now much more robust and polished for public consumption.**

## Install Instructions

### Option 1: Install via the craft plugin store:
https://plugins.craftcms.com/

### Option 2: Composer
```
composer require belniakmedia/remarry-twig-filter
```

***Be sure to enable the plugin in the CP settings if you install this way***

## Usage Instructions

### remarry Twig Filter
The `remarry` filter can be used with no arguments. The filter takes one argument which is either an integer to indicate the number of words to bind together at the end of actionable content, or a twig _settings_ object. If no arguments are supplied the default value of `2` for `numWords` is assumed.

This plugin works on plain text and HTML documents. It will isolate text collections across `DOMText` nodes and `inline`/`inline-block` elements listed in our internal `$inlineElements` list. This means that even content like this `... there you have <b>it!</b>` would end up with `... there you have&nbsp;<b>it!</b>`. It also does not get tripped up by mid-paragraph elements. For example, `... this text <em>IS REALLY</em> important to me ...` does not end up with non-breaking spaces added before the `<em>` which would be undesirable. This is because tags like `<em>` are recognized and treated like another `DOMText` node and grouped with the previously detected nodes. Only when it hits the actual end of the collection or a `block` level element will the content be processed. This topic is elaborated further in the setting definitions below.

##### Example Settings Object
```
{
    numWords: 2,
    preventHyphenBreaks: true,
    minimumWordCount: 4,
    removeExtraSpaces: true,
    overrideInlineElements: null,
    overrideIgnoredElements: null,
}
```

#### Setting Details
###### numWords (_int_, default: 2)
- Cannot be set lower than 2. If you set a non-numeric value or a value lower than two it will be changed to 2 automatically. This is the number of words to bind together at the end of each actionable content.

###### preventHyphenBreaks (_bool_, default: true)
- If left enabled, this will replace any "word" that will be bound to another word with a non-breaking space character that also has a hyphen inside it, with a `<span style="white-space: nowrap;"></span>` node to prevent the word from breaking at the hyphen. Its recommended that you leave this on, but you can disable it if needed.

###### minimumWordCount (_int_, default: 4)
- The number of words an actionable text content must contain in order to be processed. This prevents adding widow protection to very short pieces of text where binding words with a non-breaking space could be problematic.

###### removeExtraSpaces (_bool_, default: true)
- **It is highly recommended that you leave this on,** but it can be disabled if you absolutely need to.
- This will trim the beginning white space from the first text node, and ending white space of the last text node within a collection of nodes being processed as actionable text content. Additionally, any instances of two or more consecutive white space characters `RegEx \s` is replaced with a single space. 
- Be advised that this means consecutive combinations of `\t \r \n` or `space` characters will be replaced with a single space but in practice this usually does not affect the rendering of the HTML and there is a real benefit to doing this. If left alone, consecutive spaces will result in extra "words" in our "word list" during parsing and may cause unexpected results like a `&nbsp;` connecting directly to a space instead of two words, which would not actually stop the word from breaking to a new line.
- In other words, its better write your HTML/CSS in way that does not depend on white space characters for formatting if you want to use this plugin.

###### overrideInlineElements (_null|string[]_, default: null)
- Array values must be HTML tag names without `<>` characters. E.g.: `['a', 'span', 'u']`
- Setting this to an array will replace our internal curated list of inline element tag names with your provided array of tag names. You may add or subtract elements from our internal list using the provided helper Twig Functions `remarryAddElement()` and `remarryRemElement()`. See the helper function documentation below for more info and usage examples.
- Element tag names listed in the inlineElements array will be treated as part of a collection of text nodes. This allows the filter to treat paragraph text with `<b>` `<strong>` `<a>` and other similar tags without getting tripped up on them.
- The `<br>` tag is intentionally left off of this list because of special `<br>` tag handling built in. More on this below, but you may add `'br'` to this array in order to disable our special handling of `<br>` tags.

###### overrideIgnoredElements (_null|string[]_, default: null)
- Array values must be HTML tag names without `<>` characters. E.g.: `['a', 'span', 'u']`
- Setting this to an array will replace our internal curated list of ignored inline element tag names with your provided array of tag names. You may add or subtract elements from our internal list using the provided helper Twig Functions `remarryAddElement()` and `remarryRemElement()`. See the helper function documentation below for more info and usage examples.
- Element tag names listed in the ignoredElements array will be allowed to exist as part an actionable text node collection but any child nodes, including `DOMText` child nodes of such elements are not processed as part of the actionable content. This means that text content to the left of such elements are treated as the end of an actionable text collection.

### Usage Examples

##### Example with no arguments
```
{{ entry.fieldName | remarry }}

// This will bind 2 words: 
// "I have a big jar of pickles"
// becomes
// "I have a big jar of&nbsp;pickles"
```


##### Example with integer numWords argument
```
{{ entry.fieldName | remarry(3) }}

// This will bind 3 words: 
// "I have a big jar of pickles"
// becomes
// "I have a big jar&nbsp;of&nbsp;pickles"
```

##### Example with full options argument
```
{{ entry.fieldName | remarry({
    numWords: 2,
    preventHyphenBreaks: true,
    minimumWordCount: 4,
    removeExtraSpaces: true,
    overrideInlineElements: null,
    overrideIgnoredElements: null,
}) }}
```

##### Example of reused settings object
One of the nice things about supporting a settings object is that you can define a settings object as a twig variable then re-use it throughout your code if you need to!
```
{% set remarrySettings = {
    numWords: 2,
    preventHyphenBreaks: true,
    minimumWordCount: 4,
    removeExtraSpaces: true,
    overrideInlineElements: null,
    overrideIgnoredElements: null,
} %}

{{ entry.fieldName | remarry(remarrySettings) }}
```

### remarryAddElement Twig Function
```
remarryAddElement(array $elements, $elementList): array
```
#### Arguments

| Argument | Type | Description
| -------- | ---- | ----------- 
| `$elements` | `array` | An array of element tag names you wish to add to the `$elementList` in argument #2. E.g.: `['a', 'span', 'strong']`
| `$elementList` | `'inline'` `'ignored'` or `array` | If you pass the string `'inline'` the included curated inline element list will be used as a base. If you pass the string `'ignored'` the included curated ignored inline element list will be used as a base. If you pass an array of tag names, that will be used as a base.

Normally you would be passing one of the string values to argument #2, however, it was deemed necessary to allow a custom array option in the event that you wanted to add new elements and remove existing elements from the curated list without needing to redefine the whole thing. Consider the following example:

```
{% set inlineElements = remarryAddElement(['br'], remarryRemElement(['span'], 'inline')) %}
```
This would result in `inlineElements` being set to the curated inline element list, minus the `'span'` element, plus the `'br'` element.

### remarryRemElement Twig Function
```
remarryRemElement(array $elements, $elementList): array
```
_See `remarryAddElement` definition_ above as this function works exactly the same way but removes an element rather than adds one and the function's name is slightly different.

## `<br>` Tag Special Handling Details
By default, this plugin treats `<br>` tags like `block` level elements in that all buffered `DOMText` nodes and `DOMElement` nodes matching the curated inline elements list are processed as collection once a `<br>` tag is encountered. The only difference is that unlike `block` level elements, the `<br>` elements are not traversed since they have no child nodes or content. The `<br>` tag is maintained in the output. This means that the following HTML would be processed in the following manner:
```
"... this is a long sentence<br>and this is more text after that!"
becomes:
"... this is a long&nbsp;sentence<br>and this is more text after&nbsp;that!"
```

If for some reason you need to disable this behavior, simply add the 'br' tag name to the `overrideInlineElements` setting to have the plugin treat that tag as a normal inline element:
```
{{ entry.fieldName | remarry({ overrideInlineElements: remarryAddElement(['br'], 'inline')}) }}
```
When the `'br'` element is added to the inline elements list, the above example would then output:
```
"... this is a long sentence<br>and this is more text after&nbsp;that!"
```

## Included Curated Inline Element List
The curated list of inline elements was derived from the [MDN Article on HTML Inline Elements](https://developer.mozilla.org/en-US/docs/Web/HTML/Inline_elements).

`[
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
]`

## Included Curated Ignored Inline Element List
The curated list of ignored inline elements was derived from the [MDN Article on HTML Inline Elements](https://developer.mozilla.org/en-US/docs/Web/HTML/Inline_elements).

`[
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
]`

## FAQs

#### What does this do?
- This plugin's name is a play on words. It fixes paragraph text widows for you in user provided (dynamic) content. Since it "fixes a widow" maybe that widow was re-married? Ha. ha. ha...

#### What is a paragraph widow anyway?
- Great question. A lot of web developers don't know about these types of things unless you were educated by someone with a "print design" background. A paragraph widow is when a single word at the end of a paragraph breaks to a new line all by itself leaving you with a single line with a single word on it.

#### Why should I care about this?
- Another great question. However, I don't really have a great answer. You are here though, so maybe you care a little? Basically it's just more pleasing to the eye and your websites will look more professional and clean if you don't have a bunch of paragraph widows throughout.

#### How do you "Fix" paragraph widows?
- The way you can prevent paragraph widows is by replacing the space between the last two words of every paragraph and some headlines with a non-breaking space entity `&nbsp;`. This is easy enough to do when you're working with statically programmed content. You just have to remember to do it.

#### Why do I need this plugin then?
- User supplied dynamic content that you do not control. You can't exactly expect your content editors to know how to, or remember to replace the last space in all of their content with `&nbsp;`, right? This plugin provides you with a twig filter so that you can apply this any string variable you want.

#### Shouldn't this just be a few lines of string replacement code?
- That's what I thought too when I first started this thing back in version 1.0.0. However, I quickly realized that I needed this to work on rich text fields as well. The problem with rich text fields is that there maybe many paragraphs that need to be handled so a simple string replacement was out of the question and a DOM parser had to be implemented. The 1.x branch only worked with root level elements but failed to correctly handle nested elements. A collection of `<p>` tags would be ok but the `<li>` tags inside a list element were out of luck. The new 2.x branch fixes all that and adds bunch of new features too!




Brought to you by [Belniak Media Inc.](http://www.belniakmedia.com)
