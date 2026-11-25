<?php

namespace Tests\Unit;

use App\Services\PhoneNormalizationService;
use PHPUnit\Framework\TestCase;

class PhoneNormalizationTest extends TestCase
{
    protected PhoneNormalizationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PhoneNormalizationService();
    }

    public function test_normalizes_standard_local_format(): void
    {
        $input = '01712345678';
        $canonical = $this->service->normalize($input);
        $regNo = $this->service->toRegistrationNumber($canonical);

        $this->assertEquals('01712345678', $canonical);
        $this->assertEquals('1712345678', $regNo);
    }

    public function test_normalizes_with_country_code_plus(): void
    {
        $input = '+8801812345678';
        $canonical = $this->service->normalize($input);
        $regNo = $this->service->toRegistrationNumber($canonical);

        $this->assertEquals('01812345678', $canonical);
        $this->assertEquals('1812345678', $regNo);
    }

    public function test_normalizes_with_country_code_without_plus(): void
    {
        $input = '8801912345678';
        $canonical = $this->service->normalize($input);
        $regNo = $this->service->toRegistrationNumber($canonical);

        $this->assertEquals('01912345678', $canonical);
        $this->assertEquals('1912345678', $regNo);
    }

    public function test_normalizes_without_leading_zero(): void
    {
        $input = '1612345678';
        $canonical = $this->service->normalize($input);
        $regNo = $this->service->toRegistrationNumber($canonical);

        $this->assertEquals('01612345678', $canonical);
        $this->assertEquals('1612345678', $regNo);
    }

    public function test_strips_spaces_dashes_and_parentheses(): void
    {
        $input = '+88 (017) 12-345-678';
        $canonical = $this->service->normalize($input);
        $regNo = $this->service->toRegistrationNumber($canonical);

        $this->assertEquals('01712345678', $canonical);
        $this->assertEquals('1712345678', $regNo);
    }

    public function test_rejects_invalid_prefixes(): void
    {
        $this->assertNull($this->service->normalize('01012345678'));
        $this->assertNull($this->service->normalize('01112345678'));
        $this->assertNull($this->service->normalize('01212345678'));
    }

    public function test_rejects_invalid_length(): void
    {
        $this->assertNull($this->service->normalize('017123'));
        $this->assertNull($this->service->normalize('0171234567890'));
    }
}
