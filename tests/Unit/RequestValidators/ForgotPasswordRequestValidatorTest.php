<?php

declare(strict_types=1);

namespace Tests\Unit\RequestValidators;

use App\RequestValidators\ForgotPasswordRequestValidator;
use PHPUnit\Framework\TestCase;

class ForgotPasswordRequestValidatorTest extends TestCase
{
    public function testNormalizesTheEmail(): void
    {
        $validator = new ForgotPasswordRequestValidator();

        $data = $validator->validate(['email' => ' User@Example.COM ']);

        $this->assertSame('user@example.com', $data['email']);
    }
}
