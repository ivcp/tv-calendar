<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\RequestService;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Unit\Support\InMemorySession;

class RequestServiceTest extends TestCase
{
    public function testItReturnsTheRefererWhenItIsOnTheSameHost(): void
    {
        $referer = $this->service()->getReferer(
            $this->request('/login')->withHeader('referer', 'https://example.test/discover')
        );

        $this->assertSame('https://example.test/discover', $referer);
    }

    public function testItReturnsThePreviousUrlWhenThereIsNoReferer(): void
    {
        $session = new InMemorySession();
        $session->put('previousUrl', 'https://example.test/shows/42-test-show');

        $referer = $this->service($session)->getReferer($this->request('/login'));

        $this->assertSame('https://example.test/shows/42-test-show', $referer);
    }

    public function testItIgnoresARefererFromAnotherHost(): void
    {
        $session = new InMemorySession();
        $session->put('previousUrl', 'https://example.test/showlist');

        $referer = $this->service($session)->getReferer(
            $this->request('/login')->withHeader('referer', 'https://evil.test/phish')
        );

        $this->assertSame('https://example.test/showlist', $referer);
    }

    public function testItFallsBackToTheHomepageWhenThereIsNoRefererAndNoPreviousUrl(): void
    {
        $referer = $this->service()->getReferer($this->request('/login'));

        $this->assertSame('/', $referer);
    }

    public function testItFallsBackToTheHomepageForAForeignRefererWithoutAPreviousUrl(): void
    {
        $referer = $this->service()->getReferer(
            $this->request('/login')->withHeader('referer', 'https://evil.test/phish')
        );

        $this->assertSame('/', $referer);
    }

    private function service(?InMemorySession $session = null): RequestService
    {
        return new RequestService($session ?? new InMemorySession());
    }

    private function request(string $path): \Psr\Http\Message\ServerRequestInterface
    {
        return (new ServerRequestFactory())->createServerRequest('POST', 'https://example.test' . $path);
    }
}