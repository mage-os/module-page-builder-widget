# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](http://semver.org/spec/v2.0.0.html).

# 1.5.0 - 2026-04-21
### Added
- Declare PHP 8.5 compatibility in composer.json (now explicitly supports PHP 8.1 through 8.5)
### Fixed
- Guard `preg_replace_callback` in `WidgetContentSettingsCleanup` against null/empty content to avoid PHP 8.5 deprecation on null subjects
- Harden `Build::sanitizeWidgetParams` so `preg_replace`/`escapeHtml` never receive non-string values (centralized in `sanitizeStringValue`)
- Loosen `Build::isTypeValid` signature to accept mixed input from the request and validate type safely
- Add missing `getCacheKey(): string` / `getCacheKeyInfo(): array` return types on `Block\Adminhtml\Widget\Preview\NewWidget` to match parent contracts
- Use typed class constants where safe (`HTML_ID_PLACEHOLDER`, `SCRIPT_REPLACE_REGEX`) and consolidate `use` imports

# 1.4.1
### Fixed
- Fix getCacheKey() return type from array to string matching parent contract

# 1.4.0
## Updated
- Update composer JSON making the module installable for Magento Opensource also

# 1.3.2
### Fixed
- Fix CSS issue hiding other modal form labels by @melindash (#10)

# 1.3.1
### Fixed
- Improve security and avoid script injection through admin widget preview builder controller

# 1.3.0
### Fixed
- Fix minor code syntax issues and dependencies declaration, add control on widget_type parameter passed for widget preview on adminhtml

# 1.2.0
### Fixed
- Fix not useful and broken validation on pagebuilder widget form ui component

## 1.1.1
### Updated
- Remove content_settings attribute on frontend for pages and blocks widgets placed into content

## 1.1.0
### Fixed
- Fix error for xml compilation on developer mode

## 1.0.0
### Added
- First Commit, now is possible to use CMS widgets with own previews inside pagebuilder with a dedicated component!
