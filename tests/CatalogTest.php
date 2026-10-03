<?php

declare(strict_types=1);

namespace WebTigers\TldRules\Tests;

use PHPUnit\Framework\TestCase;
use WebTigers\TldRules\Catalog;

final class CatalogTest extends TestCase
{
    private Catalog $cat;

    protected function setUp(): void
    {
        $this->cat = new Catalog();
    }

    public function testPrivacyGating(): void
    {
        $this->assertFalse($this->cat->privacySupported('example.us'), '.us has no WHOIS privacy');
        $this->assertTrue($this->cat->privacySupported('example.com'));
        $this->assertTrue($this->cat->privacySupported('example.unknown-tld-xyz'), 'unknown → default privacy');
    }

    public function testPeriodBounds(): void
    {
        $this->assertSame([1, 10], $this->cat->periodBounds('example.com'));
    }

    public function testLongestSuffixMatch(): void
    {
        // .co.uk is not a distinct entry; it resolves to the .uk ruleset.
        $this->assertSame('uk', $this->cat->forDomain('example.co.uk')['tld']);
        // com.au IS distinct and must win over au.
        $this->assertSame('com.au', $this->cat->forDomain('shop.com.au')['tld']);
    }

    public function testUsNexusFields(): void
    {
        $this->assertSame('nexus', $this->cat->group('example.us'));
        $fields = $this->cat->requiredFields('example.us');
        $keys   = array_column($fields, 'key');
        $this->assertContains('category', $keys);
        $this->assertContains('app_purpose', $keys);
    }

    public function testCaHasLegalType(): void
    {
        $this->assertSame('registrant_extra_info', $this->cat->group('example.ca'));
        $fields = $this->cat->requiredFields('example.ca');
        $this->assertSame('legal_type', $fields[0]['key']);
        $this->assertArrayHasKey('CCT', $fields[0]['options']);
    }

    public function testValidateRequired(): void
    {
        $errors = $this->cat->validate('example.us', []);
        $this->assertNotEmpty($errors);
        $this->assertCount(2, $errors, 'category + app_purpose required, validator is conditional');
    }

    public function testValidatePassesWhenComplete(): void
    {
        $this->assertSame([], $this->cat->validate('example.us', ['category' => 'C11', 'app_purpose' => 'P3']));
    }

    public function testValidateConditionalRequiredIf(): void
    {
        // category C31 makes `validator` (country of citizenship) required.
        $errors = $this->cat->validate('example.us', ['category' => 'C31', 'app_purpose' => 'P1']);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('citizenship', strtolower(implode(' ', $errors)));
    }

    public function testValidateRejectsBadOption(): void
    {
        $errors = $this->cat->validate('example.us', ['category' => 'NOPE', 'app_purpose' => 'P3']);
        $this->assertNotEmpty($errors);
    }

    public function testNoRequirementsForPlainGtld(): void
    {
        $this->assertFalse($this->cat->hasRequirements('example.com'));
        $this->assertSame([], $this->cat->validate('example.com', []));
    }

    public function testTldsListNonEmpty(): void
    {
        $this->assertContains('us', $this->cat->tlds());
        $this->assertGreaterThan(40, count($this->cat->tlds()));
    }
}
