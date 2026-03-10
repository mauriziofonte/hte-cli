<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Tests for global helper functions (validate_domain, answer_to_bool).
 *
 * Only pure functions with no side effects remain in helpers.php.
 * Filesystem and process helpers have been moved to service classes.
 */
class HelpersTest extends TestCase
{
    /**
     * @dataProvider validDomainProvider
     */
    public function testValidateDomainAcceptsValidDomains(string $domain, string $expected): void
    {
        $result = validate_domain($domain);
        $this->assertNotNull($result);
        $this->assertEquals($expected, $result);
    }

    public function validDomainProvider(): array
    {
        return [
            'simple domain' => ['example.test', 'example.test'],
            'subdomain' => ['sub.example.test', 'sub.example.test'],
            'multiple subdomains' => ['deep.sub.example.test', 'deep.sub.example.test'],
            'uppercase domain' => ['EXAMPLE.TEST', 'example.test'],
            'mixed case' => ['Example.Test', 'example.test'],
            'domain with numbers' => ['example123.test', 'example123.test'],
            'domain with hyphens' => ['my-example.test', 'my-example.test'],
            'ipv4 address' => ['127.0.0.1', '127.0.0.1'],
            'ipv4 public' => ['192.168.1.1', '192.168.1.1'],
            'real tld' => ['example.com', 'example.com'],
            'longer tld' => ['example.localhost', 'example.localhost'],
        ];
    }

    /**
     * @dataProvider invalidDomainProvider
     */
    public function testValidateDomainRejectsInvalidDomains(string $domain): void
    {
        $result = validate_domain($domain);
        $this->assertNull($result);
    }

    public function invalidDomainProvider(): array
    {
        return [
            'starts with hyphen' => ['-example.test'],
            'ends with hyphen' => ['example-.test'],
            'single label' => ['localhost'],
            'double dots' => ['example..test'],
            'empty string' => [''],
            'spaces' => ['example .test'],
            'special chars' => ['example!@#.test'],
            'underscore' => ['example_test.com'],
        ];
    }

    /**
     * @dataProvider answerToBoolTrueProvider
     */
    public function testAnswerToBoolReturnsTrue($value): void
    {
        $result = answer_to_bool($value);
        $this->assertTrue($result);
    }

    public function answerToBoolTrueProvider(): array
    {
        return [
            'boolean true' => [true],
            'string yes' => ['yes'],
            'string YES' => ['YES'],
            'string y' => ['y'],
            'string Y' => ['Y'],
            'string 1' => ['1'],
            'string true' => ['true'],
            'string TRUE' => ['TRUE'],
            'integer 1' => [1],
        ];
    }

    /**
     * @dataProvider answerToBoolFalseProvider
     */
    public function testAnswerToBoolReturnsFalse($value): void
    {
        $result = answer_to_bool($value);
        $this->assertFalse($result);
    }

    public function answerToBoolFalseProvider(): array
    {
        return [
            'boolean false' => [false],
            'integer 0' => [0],
        ];
    }

    /**
     * @dataProvider answerToBoolNullProvider
     */
    public function testAnswerToBoolReturnsNull($value): void
    {
        $result = answer_to_bool($value);
        $this->assertNull($result);
    }

    public function answerToBoolNullProvider(): array
    {
        return [
            'string no' => ['no'],
            'string n' => ['n'],
            'string 0' => ['0'],
            'string false' => ['false'],
            'empty string' => [''],
            'random string' => ['maybe'],
        ];
    }
}
