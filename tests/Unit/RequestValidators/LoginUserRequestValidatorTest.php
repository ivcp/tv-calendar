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
}
