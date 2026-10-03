<?php

declare(strict_types=1);

namespace WebTigers\TldRules;

/**
 * TldRules\Catalog — a zero-dependency query API over the per-TLD registration rules catalog.
 *
 * Registries impose rules a reseller must satisfy to register a domain: residency/eligibility, extra
 * registrant fields ("extended attributes"), whether WHOIS privacy is offered, and the allowed
 * registration period. No registrar exposes these for interrogation, so they live as data here
 * (data/tld-rules.json) and this class answers questions about them.
 *
 * The extended-attribute field keys follow OpenSRS's `tld_data` naming (e.g. nexus.category for .us,
 * registrant_extra_info.legal_type for .ca), which is the most widely documented scheme; the data is
 * useful to anyone, OpenSRS or not.
 *
 * Usage:
 *   $cat = \WebTigers\TldRules\Catalog::default();
 *   $cat->privacySupported('example.us');     // false
 *   $cat->requiredFields('example.us');        // [ ['key'=>'category', …], … ]
 *   $cat->validate('example.us', ['category'=>'C11','app_purpose'=>'P3']);   // []  (ok)
 *
 * @license BSD-3-Clause
 */
final class Catalog
{
    /** @var array{defaults?:array,tlds?:array<string,array>} */
    private array $data;

    /** @param string|null $jsonPath Path to a tld-rules.json; defaults to the bundled catalog. */
    public function __construct(?string $jsonPath = null)
    {
        $path = $jsonPath ?? __DIR__ . '/../data/tld-rules.json';
        $raw  = is_file($path) ? (string) file_get_contents($path) : '';
        $decoded = $raw !== '' ? json_decode($raw, true) : null;
        $this->data = is_array($decoded) ? $decoded : ['defaults' => [], 'tlds' => []];
    }

    /** A shared instance over the bundled catalog. */
    public static function default(): self
    {
        static $instance = null;
        return $instance ??= new self();
    }

    /**
     * The resolved rules for a domain or bare TLD — longest registrable suffix wins (com.au before au).
     * Unknown TLDs fall back to the catalog defaults.
     *
     * @return array{tld:string,privacy:bool,min_period:int,max_period:int,tld_data:?array{group:string,fields:array}}
     */
    public function forDomain(string $domainOrTld): array
    {
        $defaults = array_merge(['privacy' => true, 'min_period' => 1, 'max_period' => 10], (array) ($this->data['defaults'] ?? []));
        $tlds     = (array) ($this->data['tlds'] ?? []);

        $parts = explode('.', strtolower(trim($domainOrTld, " \t.")));
        $rule = []; $matched = null;
        for ($n = min(3, count($parts)); $n >= 1; $n--) {
            $suffix = implode('.', array_slice($parts, -$n));
            if (isset($tlds[$suffix]) && is_array($tlds[$suffix])) { $rule = $tlds[$suffix]; $matched = $suffix; break; }
        }

        return [
            'tld'        => $matched ?? (string) end($parts),
            'privacy'    => array_key_exists('privacy', $rule) ? (bool) $rule['privacy'] : (bool) $defaults['privacy'],
            'min_period' => (int) ($rule['min_period'] ?? $defaults['min_period']),
            'max_period' => (int) ($rule['max_period'] ?? $defaults['max_period']),
            'tld_data'   => (isset($rule['tld_data']) && is_array($rule['tld_data'])) ? $rule['tld_data'] : null,
        ];
    }

    /** Alias of forDomain() for a bare TLD. */
    public function forTld(string $tld): array
    {
        return $this->forDomain($tld);
    }

    /** Whether this TLD offers WHOIS privacy. */
    public function privacySupported(string $domainOrTld): bool
    {
        return (bool) $this->forDomain($domainOrTld)['privacy'];
    }

    /** The allowed registration period as [min, max] years. */
    public function periodBounds(string $domainOrTld): array
    {
        $r = $this->forDomain($domainOrTld);
        return [(int) $r['min_period'], (int) $r['max_period']];
    }

    /** The OpenSRS tld_data group this TLD uses (e.g. "nexus"), or null when none. */
    public function group(string $domainOrTld): ?string
    {
        return $this->forDomain($domainOrTld)['tld_data']['group'] ?? null;
    }

    /**
     * The extended-attribute field specs this TLD requires ([] when none). Each:
     *   ['key','label','required'(bool),'type'('select|text|country|date'),'options'?,'required_if'?]
     */
    public function requiredFields(string $domainOrTld): array
    {
        return $this->forDomain($domainOrTld)['tld_data']['fields'] ?? [];
    }

    /** Does this TLD require any extended attributes? */
    public function hasRequirements(string $domainOrTld): bool
    {
        return $this->requiredFields($domainOrTld) !== [];
    }

    /**
     * Validate submitted extended-attribute values against a TLD's rules. Checks required, conditional
     * requirement (required_if), and select-option membership. Returns a list of human-readable error
     * strings — empty means valid (or the TLD has no requirements).
     *
     * @param array<string,mixed> $values key => submitted value
     * @return string[]
     */
    public function validate(string $domainOrTld, array $values): array
    {
        $r      = $this->forDomain($domainOrTld);
        $fields = $r['tld_data']['fields'] ?? [];
        if (!$fields) { return []; }

        $errors = [];
        foreach ($fields as $f) {
            $key = (string) ($f['key'] ?? '');
            if ($key === '') { continue; }
            $value    = isset($values[$key]) ? trim((string) $values[$key]) : '';
            $required = !empty($f['required']);

            if (!$required && !empty($f['required_if']) && is_array($f['required_if'])) {
                foreach ($f['required_if'] as $otherKey => $triggers) {
                    $otherVal = (string) ($values[$otherKey] ?? '');
                    if (in_array($otherVal, array_map('strval', (array) $triggers), true)) { $required = true; break; }
                }
            }

            $label = (string) ($f['label'] ?? $key);
            if ($required && $value === '') {
                $errors[] = $label . ' is required for .' . $r['tld'];
                continue;
            }
            if ($value !== '' && ($f['type'] ?? '') === 'select' && !empty($f['options']) && !array_key_exists($value, (array) $f['options'])) {
                $errors[] = $label . ' is not a valid choice';
            }
        }
        return $errors;
    }

    /** Every TLD that has an explicit entry in the catalog. @return string[] */
    public function tlds(): array
    {
        return array_keys((array) ($this->data['tlds'] ?? []));
    }

    /** The raw catalog data (defaults + tlds), e.g. to export or inspect. */
    public function all(): array
    {
        return $this->data;
    }
}
