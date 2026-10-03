# AGENTS.md — webtigers/tld-rules

A tiny, **public, BSD-3** PHP library: a catalog of per-TLD domain-registration rules + a query API.
Part of WebTigers' work to make the web more free and open. Keep it that way — generic, dependency-free,
useful to anyone, not Tiger-specific.

## What it is
- **Data is the product.** `data/tld-rules.json` is language-neutral; the PHP class is a thin convenience
  wrapper. Keep both in sync; never add logic that only the PHP side understands.
- **Zero runtime dependencies.** PHP 8.1+. Do not add a `require` to `composer.json`.
- Extended-attribute field keys follow OpenSRS `tld_data` naming (the best-documented scheme). The data is
  still useful to non-OpenSRS consumers; don't couple the library to any registrar's transport.

## Conventions
- `src/Catalog.php` is the only class. Pure functions over the loaded data; no I/O beyond reading the JSON.
- Longest registrable suffix wins (`com.au` before `au`). Unknown TLDs return permissive defaults.
- `validate()` returns human-readable strings, never throws.
- Tests (`composer test`) must cover any catalog or logic change. CI lints every PHP file + validates the JSON.

## Changing the catalog
Add/fix a TLD in `data/tld-rules.json`:
```jsonc
"<suffix>": {
  "privacy": false,                     // omit to inherit the default (true)
  "min_period": 1, "max_period": 10,    // optional
  "tld_data": { "group": "<opensrs group>", "fields": [
    { "key": "...", "label": "...", "required": true, "type": "select|text|country|date",
      "options": { "VALUE": "Label" }, "required_if": { "otherKey": ["VALUE"] } }
  ]}
}
```
Bump `CHANGELOG.md` and tag `vX.Y.Z` (SemVer). Privacy flags are policy-encoded (no API returns them) —
correct them freely with a note.
