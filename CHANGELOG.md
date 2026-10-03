# Changelog

All notable changes to this project are documented here. This project adheres to
[Semantic Versioning](https://semver.org/).

## [1.0.0] - 2026-10-03

Initial release.

- Language-neutral catalog (`data/tld-rules.json`) of per-TLD registration rules: required registrant
  extended attributes (OpenSRS `tld_data` groups — `nexus`, `registrant_extra_info`, `au_registrant_info`,
  `it_registrant_info`, `professional_data`, `br_register_number`, `ie_cro_number`, `ipr_data`), WHOIS-privacy
  availability, and registration-period bounds. 59 TLD entries.
- `WebTigers\TldRules\Catalog`: `forDomain()`/`forTld()`, `privacySupported()`, `periodBounds()`, `group()`,
  `requiredFields()`, `hasRequirements()`, `validate()`, `tlds()`, `all()`. Longest-suffix matching
  (`com.au` before `au`); zero dependencies; PHP 8.1+.
