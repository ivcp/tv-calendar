<?php

declare(strict_types=1);

namespace Tests\Unit\RequestValidators;

use App\Exception\ValidationException;
use App\RequestValidators\ResetPasswordRequestValidator;
use PHPUnit\Framework\TestCase;

class ResetPasswordRequestValidatorTest extends TestCase
{
    public function testAcceptsAValidPassword(): void
    {
        $validator = new ResetPasswordRequestValidator();

        $data = $validator->validate($this->data('password123'));

        $this->assertSame('password123', $data['password']);
    }

    public function testAcceptsAPasswordOfExactly72Bytes(): void
    {
        $validator = new ResetPasswordRequestValidator();

        $data = $validator->validate($this->data(str_repeat('a', 72)));

        $this->assertSame(str_repeat('a', 72), $data['password']);
    }

    public function testRejectsAPasswordLongerThan72Bytes(): void
    {
        $this->assertRejected(str_repeat('a', 73));
    }

    public function testRejectsAMultibytePasswordLongerThan72Bytes(): void
    {
        $password = str_repeat('ä', 40);

        $this->assertSame(40, mb_strlen($password));
        $this->assertSame(80, strlen($password));

        $this->assertRejected($password);
    }

    private function assertRejected(string $password): void
    {
        $validator = new ResetPasswordRequestValidator();

        try {
            $validator->validate($this->data($password));
            $this->fail('Expected a ValidationException.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('password', $e->errors);
        }
    }

    private function data(string $password): array
    {
        return [
            'password'         => $password,
            'confirm_password' => $password,
        ];
    }
}
