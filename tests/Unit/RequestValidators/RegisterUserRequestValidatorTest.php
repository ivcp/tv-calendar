<?php

declare(strict_types=1);

namespace Tests\Unit\RequestValidators;

use App\Config;
use App\Contracts\UserProviderServiceInterface;
use App\Exception\ValidationException;
use App\RequestValidators\RegisterUserRequestValidator;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class RegisterUserRequestValidatorTest extends TestCase
{
    private UserProviderServiceInterface&MockObject $userProvider;
    private array $history = [];

    protected function setUp(): void
    {
        $this->userProvider = $this->createMock(UserProviderServiceInterface::class);
    }

    public function testAcceptsRegistrationWhenTurnstileSucceedsAndEmailIsFree(): void
    {
        $this->userProvider->expects($this->once())
            ->method('emailExists')
            ->with('user@example.com')
            ->willReturn(false);

        $data = $this->validator([new GuzzleResponse(200, [], '{"success":true}')])
            ->validate($this->validData());

        $this->assertSame('user@example.com', $data['email']);

        $request = $this->history[0]['request'];
        $body = json_decode((string) $request->getBody(), true);

        $this->assertSame('secret', $body['secret']);
        $this->assertSame('token', $body['response']);
    }

    public function testNormalizesTheEmailBeforeCheckingForDuplicatesAndReturningIt(): void
    {
        $this->userProvider->expects($this->once())
            ->method('emailExists')
            ->with('user@example.com')
            ->willReturn(false);

        $data = $this->validData();
        $data['email'] = ' User@Example.COM ';

        $validated = $this->validator([new GuzzleResponse(200, [], '{"success":true}')])->validate($data);

        $this->assertSame('user@example.com', $validated['email']);
    }

    public function testRejectsAnEmailThatAlreadyExistsRegardlessOfCasing(): void
    {
        $this->userProvider->expects($this->once())
            ->method('emailExists')
            ->with('user@example.com')
            ->willReturn(true);

        $data = $this->validData();
        $data['email'] = 'User@Example.com';

        try {
            $this->validator([new GuzzleResponse(200, [], '{"success":true}')])->validate($data);
            $this->fail('Expected a ValidationException.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('email', $e->errors);
        }
    }

    #[DataProvider('unacceptedTermsValues')]
    public function testRejectsRegistrationWhenTermsAreNotAccepted(string|int|null $termsCheck): void
    {
        $this->userProvider->method('emailExists')->willReturn(false);

        $data = $this->validData();
        if ($termsCheck === null) {
            unset($data['terms_check']);
        } else {
            $data['terms_check'] = $termsCheck;
        }

        try {
            $this->validator([new GuzzleResponse(200, [], '{"success":true}')])->validate($data);
            $this->fail('Expected a ValidationException.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('terms_check', $e->errors);
        }
    }

    public static function unacceptedTermsValues(): array
    {
        return [
            'missing'    => [null],
            'empty'      => [''],
            'integer 0'  => [0],
            'string 0'   => ['0'],
            'false'      => ['false'],
            'no'         => ['no'],
        ];
    }

    public function testRejectsRegistrationBeforeTouchingTheDatabaseWhenTheTokenIsMissing(): void
    {
        $this->userProvider->expects($this->never())->method('emailExists');

        $data = $this->validData();
        unset($data['cf-turnstile-response']);

        $this->assertTurnstileError($data, []);
    }

    public function testRejectsANonStringTokenWithoutCallingCloudflare(): void
    {
        $this->userProvider->expects($this->never())->method('emailExists');

        $data = $this->validData();
        $data['cf-turnstile-response'] = ['token'];

        $this->assertTurnstileError($data, []);
    }

    public function testTurnsAVerificationRequestFailureIntoAValidationError(): void
    {
        $this->userProvider->expects($this->never())->method('emailExists');

        $exception = new ConnectException(
            'Connection timed out',
            new Request('POST', 'https://challenges.cloudflare.com/turnstile/v0/siteverify')
        );

        $this->assertTurnstileError($this->validData(), [$exception]);
    }

    public function testTurnsAMalformedCloudflareResponseIntoAValidationError(): void
    {
        $this->userProvider->expects($this->never())->method('emailExists');

        $this->assertTurnstileError($this->validData(), [new GuzzleResponse(200, [], 'not json')]);
    }

    public function testRejectsAnUnsuccessfulVerification(): void
    {
        $this->userProvider->expects($this->never())->method('emailExists');

        $this->assertTurnstileError($this->validData(), [new GuzzleResponse(200, [], '{"success":false}')]);
    }

    public function testRejectsWhenNoSecretKeyIsConfigured(): void
    {
        $this->userProvider->expects($this->never())->method('emailExists');

        $validator = new RegisterUserRequestValidator(
            $this->userProvider,
            $this->client([]),
            new Config([])
        );

        $this->expectException(ValidationException::class);

        $validator->validate($this->validData());
    }

    public function testAcceptsAPasswordOfExactly72Bytes(): void
    {
        $this->userProvider->method('emailExists')->willReturn(false);

        $data = $this->validData();
        $data['password'] = $data['confirm_password'] = str_repeat('a', 72);

        $validated = $this->validator([new GuzzleResponse(200, [], '{"success":true}')])->validate($data);

        $this->assertSame(str_repeat('a', 72), $validated['password']);
    }

    public function testRejectsAPasswordLongerThan72Bytes(): void
    {
        $this->assertPasswordError(str_repeat('a', 73));
    }

    public function testRejectsAMultibytePasswordLongerThan72Bytes(): void
    {
        $password = str_repeat('ä', 40);

        $this->assertSame(40, mb_strlen($password));
        $this->assertSame(80, strlen($password));

        $this->assertPasswordError($password);
    }

    private function assertPasswordError(string $password): void
    {
        $this->userProvider->method('emailExists')->willReturn(false);

        $data = $this->validData();
        $data['password'] = $data['confirm_password'] = $password;

        try {
            $this->validator([new GuzzleResponse(200, [], '{"success":true}')])->validate($data);
            $this->fail('Expected a ValidationException.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('password', $e->errors);
        }
    }

    private function assertTurnstileError(array $data, array $responses): void
    {
        try {
            $this->validator($responses)->validate($data);
            $this->fail('Expected a ValidationException.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('turnstile', $e->errors);
        }
    }

    private function validator(array $responses): RegisterUserRequestValidator
    {
        return new RegisterUserRequestValidator(
            $this->userProvider,
            $this->client($responses),
            new Config(['turnstile' => ['secret_key' => 'secret']])
        );
    }

    private function client(array $responses): Client
    {
        $this->history = [];

        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));

        return new Client(['handler' => $stack]);
    }

    private function validData(): array
    {
        return [
            'email'                 => 'user@example.com',
            'password'              => 'password123',
            'confirm_password'      => 'password123',
            'terms_check'           => 'on',
            'cf-turnstile-response' => 'token',
        ];
    }
}
