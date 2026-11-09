<?php

declare(strict_types=1);

namespace Tests\Unit\RequestValidators;

use App\Exception\ValidationException;
use App\RequestValidators\UpdatePasswordRequestValidator;
use PHPUnit\Framework\TestCase;

class UpdatePasswordRequestValidatorTest extends TestCase
{
    public function testAcceptsAValidNewPassword(): void
    {
        $validator = new UpdatePasswordRequestValidator();

        $data = $validator->validate($this->data('newpassword123'));

        $this->assertSame('newpassword123', $data['new_password']);
    }

    public function testAcceptsANewPasswordOfExactly72Bytes(): void
    {
        $validator = new UpdatePasswordRequestValidator();

        $data = $validator->validate($this->data(str_repeat('a', 72)));

        $this->assertSame(str_repeat('a', 72), $data['new_password']);
    }

    public function testRejectsANewPasswordLongerThan72Bytes(): void
    {
        $this->assertRejected(str_repeat('a', 73));
    }

    public function testRejectsAMultibyteNewPasswordLongerThan72Bytes(): void
    {
        $password = str_repeat('ä', 40);

        $this->assertSame(40, mb_strlen($password));
        $this->assertSame(80, strlen($password));

        $this->assertRejected($password);
    }

    private function assertRejected(string $newPassword): void
    {
        $validator = new UpdatePasswordRequestValidator();

        try {
            $validator->validate($this->data($newPassword));
            $this->fail('Expected a ValidationException.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('new_password', $e->errors);
        }
    }

    private function data(string $newPassword): array
    {
        return [
            'password'     => 'currentpassword',
            'new_password' => $newPassword,
        ];
    }
}
