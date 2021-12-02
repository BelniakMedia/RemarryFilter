# Remarry Twig Filter Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) and this project adheres to [Semantic Versioning](http://semver.org/).

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
- Fix to attribute hundling for HTML tags where $attr was not being set in some scenarios causing an error.

## 1.0.0 - 2017-12-04
### Added
- Initial release
