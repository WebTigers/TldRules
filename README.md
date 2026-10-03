# TldRules

**A zero-dependency catalog of per-TLD domain-registration rules, with a small query API.**

Registries impose rules a reseller must satisfy to register a domain — residency/eligibility, extra
registrant fields ("extended attributes"), whether WHOIS privacy is offered, and the allowed
registration period. No registrar API lets you *interrogate* these, so they end up hardcoded per
project, over and over. This library collects them as **plain, language-neutral JSON** and wraps them in
a tiny PHP class.

- **Data:** [`data/tld-rules.json`](data/tld-rules.json) — use it from any language.
- **No dependencies**, PHP 8.1+.
- **BSD-3-Clause.** Free for anyone, any project.

The extended-attribute field keys follow OpenSRS's [`tld_data`](https://domains.opensrs.guide/docs/tld)
naming (e.g. `nexus.category` for `.us`, `registrant_extra_info.legal_type` for `.ca`) — the most widely
documented scheme — but the data is useful whatever registrar you use.

## Install

```bash
composer require webtigers/tld-rules
```

## Use

```php
use WebTigers\TldRules\Catalog;

$cat = Catalog::default();                       // the bundled catalog

$cat->privacySupported('example.us');            // false — .us has no WHOIS privacy
$cat->privacySupported('example.com');           // true
$cat->periodBounds('example.com');               // [1, 10]

$cat->hasRequirements('example.ca');             // true
$cat->requiredFields('example.us');
// [
//   ['key'=>'category',    'label'=>'Nexus category', 'required'=>true, 'type'=>'select', 'options'=>[...]],
//   ['key'=>'app_purpose', 'label'=>'Purpose',        'required'=>true, 'type'=>'select', 'options'=>[...]],
//   ['key'=>'validator',   'label'=>'Country of citizenship', 'required'=>false, 'type'=>'country',
//      'required_if'=>['category'=>['C31','C32']]],
// ]

// Validate a buyer's submitted extended attributes before you try to register:
$errors = $cat->validate('example.us', ['category' => 'C11', 'app_purpose' => 'P3']);
// []  — valid

$errors = $cat->validate('example.us', []);
// ['Nexus category is required for .us', 'Purpose is required for .us']

$cat->group('example.ca');                       // 'registrant_extra_info'
$cat->tlds();                                     // ['us','ca','uk','eu', …]
```

Longest registrable suffix wins: `example.com.au` resolves the `com.au` rules, `example.co.uk` resolves
the `.uk` ruleset. Unknown TLDs return the catalog defaults (privacy available, 1–10 year period, no
extra fields).

### Loading your own data

```php
$cat = new Catalog('/path/to/your/tld-rules.json');
```

## Data shape

```jsonc
{
  "defaults": { "privacy": true, "min_period": 1, "max_period": 10 },
  "tlds": {
    "us": {
      "privacy": false,
      "tld_data": {
        "group": "nexus",
        "fields": [
          { "key": "category", "label": "Nexus category", "required": true, "type": "select",
            "options": { "C11": "US citizen", "C21": "US-incorporated entity", "C31": "…" } },
          { "key": "validator", "label": "Country of citizenship", "required": false, "type": "country",
            "required_if": { "category": ["C31", "C32"] } }
        ]
      }
    }
  }
}
```

`type` is one of `select` | `text` | `country` | `date`. `required_if` makes a field required when another
field holds one of the listed values.

## Coverage

TLDs with explicit rules include `.us`, `.ca`, `.uk`, `.eu`, `.au` (+ `com/net/org/asn/id.au`), `.it`,
`.de`, `.es`, `.fr` (+ French overseas), `.nl`, `.no`, `.se`, `.nu`, `.ru`, `.hk`, `.my`, `.sg`, `.in`
family, `.mx`, `.com.br`, `.com.ar`, `.cl`, `.co.za`, `.ie`, `.pro`, `.travel`, `.aero`, `.coop`, `.jobs`,
`.law`, `.abogado`, `.xxx`, and more. Anything not listed inherits the permissive defaults.

Privacy-availability flags are encoded from registry policy (no API returns them); corrections welcome.

## Contributing

The catalog is **data** — add or fix a TLD in `data/tld-rules.json` and send a PR. Run `composer test`.

## License

BSD-3-Clause © WebTigers. Part of making the web more free and open.
