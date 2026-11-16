<?php

declare(strict_types=1);

namespace Tests\Unit\RequestValidators;

use App\RequestValidators\LoginUserRequestValidator;
use PHPUnit\Framework\TestCase;

class LoginUserRequestValidatorTest extends TestCase
{
    public function testNormalizesTheEmail(): void
    {
        $validator = new LoginUserRequestValidator();

        $data = $validator->validate([
            'email'    => ' User@Example.COM ',
            'password' => 'password123',
        ]);

        $this->assertSame('user@example.com', $data['email']);
    }

    public function testRememberDefaultsToFalse(): void
    {
        $validator = new LoginUserRequestValidator();

        $data = $validator->validate([
            'email'    => 'user@example.com',
            'password' => 'password123',
        ]);

        $this->assertFalse($data['remember']);
    }

    public function testTheHiddenFieldMeansNoRemember(): void
    {
        $validator = new LoginUserRequestValidator();

        $data = $validator->validate([
            'email'    => 'user@example.com',
            'password' => 'password123',
            'remember' => '0',
        ]);

        $this->assertFalse($data['remember']);
    }

    public function testATickedBoxMeansRemember(): void
    {
        $validator = new LoginUserRequestValidator();

        // Checked forms send both values; PHP keeps the last one.
        $data = $validator->validate([
            'email'    => 'user@example.com',
            'password' => 'password123',
            'remember' => '1',
        ]);

        $this->assertTrue($data['remember']);
    }

    public function testAMalformedRememberValueMeansNoRemember(): void
    {
        $validator = new LoginUserRequestValidator();

        $data = $validator->validate([
            'email'    => 'user@example.com',
            'password' => 'password123',
            'remember' => ['1'],
        ]);

        $this->assertFalse($data['remember']);
    }
}
