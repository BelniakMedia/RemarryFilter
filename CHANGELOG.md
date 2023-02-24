# remarry Twig Filter Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) and this project adheres to [Semantic Versioning](http://semver.org/).

## 2.1.1 - 2023-02-24
### Fixed
- Fixed bug where calls to `DomElement::appendChild(childNode)` while iterating over `DomElement::childNodes` would mutate the node list being iterated. This could sometimes cause the remaining dom elements in the original child node list to be skipped. This had the potential to resulting in lost content. We now call `cloneNode()` on the `childNode` reference, inside the loop, prior to processing it.

## 2.1.0 - 2022-09-22
### Updated
- Updated plugin meta data for Craft 4.0 support / requirements.
- Now requires Craft 4.0+ and PHP 8.0.2+

## 2.0.4 - 2022-04-27
### Fixed
- Fixed a bug where redactor (and possibly other field types) were passing in an object as the `$content` rather than the expected text/html string. The code now will attempt to cast any non-string `$content` passed in to a string. As long as the field object has a good `__toString()` implementation then it works correctly. Unexpected results may occur if you use this with a field type that does not provide the desired output when cast to a string. Note that redactor field type does work correcly with this implementation now.

## 2.0.3 - 2022-04-13
### Fixed
- Fixed a bug where a `<body>` tag was wrapping the output when processing HTML strings.

## 2.0.2 - 2022-01-30
### Fixed
- Fixed internal method naming of the `addInline()` and `rmInline()` methods as they are routed as `addElement()` and `remElement()` in the twig function declarations. This bug would have prevented a user from using the `remarryAddELement()` and `remarryRemElement()` twig functions as described in the documentation. This fixes that issue.

## 2.0.1 - 2022-01-04
### Fixed
- Fixed a bug where applying filter to an empty field would cause a template error to be thrown. Removed string parameter requirement and added code to ensure the `$content` parameter is a string by setting it to an empty string if is not a string.

## 2.0.0 - 2021-12-01
### Added
- Complete rework of the plugin.
  - Filter parameter for `numWords` now also accepts a settings object.
  - Works with basic text and html documents.
  - Works with complex HTML and fully traverses the dom try applying widow protections not just to individual text nodes, but intelligently builds collections of text nodes and inline elements to smartly add non-breaking spaces everywhere you want them and nowhere that you don't.
  - See README for full feature list and documentation.
- Updated plugin icon.

## 1.3 - 2021-04-30
### Fixed
- Fixed a bug where not checking if a node had childNodes before appending it to the parent which could cause an error.

## 1.2 - 2020-03-10
### Fixed
- Fixed a bug when filtering HTML nodes where DOMText elements would be present causing an error.
### Added
- Added a .gitignore file.

## 1.1 - 2020-02-24
### Fixed
- Fix to attribute handling for HTML tags where $attr was not being set in some scenarios causing an error.

## 1.0.0 - 2017-12-04
### Added
- Initial release
