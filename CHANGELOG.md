# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](http://semver.org/spec/v2.0.0.html).


## [Unreleased](https://github.com/inspirum/balikobot-php-symfony/compare/v1.2.0...master)


## [v1.2.0 (2023-05-29)](https://github.com/inspirum/balikobot-php-symfony/compare/v1.1.0...v1.2.0)
### Changed
- Adjusted `guzzlehttp/psr7` version constraint to `^1.5 || ^2.0`
- Adjusted `psr/http-message` version constraint to `^1.1 || ^2.0`
### Removed
- Remove unused composer requirements


## [v1.1.0 (2023-05-15)](https://github.com/inspirum/balikobot-php-symfony/compare/v1.0.0...v1.1.0)
### Added
- Added support for multiple client connection configuration with [**ServiceContainerRegistry**](https://github.com/inspirum/balikobot-php/blob/master/src/Service/Registry/ServiceContainerRegistry.php) service


## v1.0.0 (2022-08-19)
### Added
- Support [`inspirum/balikobot`](https://github.com/inspirum/balikobot-php) `^7.0` for Symfony `^6.1`
