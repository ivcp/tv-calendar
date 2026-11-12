<?php

declare(strict_types=1);

namespace Tests\Unit\Mail;

use DateTime;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

final class DeletionReminderTemplateTest extends TestCase
{
    private function render(int $showsCount): string
    {
        $twig = new Environment(
            new FilesystemLoader(dirname(__DIR__, 3) . '/resources/views')
        );

        $html = $twig->render('emails/deletion-reminder.html.twig', [
            'appName' => 'TV Calendar',
            'website' => 'https://tv-calendar.test',
            'activationLink' => 'https://tv-calendar.test/verify/42/abc',
            'expirationDate' => new DateTime('+2 days'),
            'showsCount' => $showsCount,
        ]);

        // Collapse the template's line breaks so text assertions stay readable.
        return (string) preg_replace('/\s+/', ' ', $html);
    }

    public function testItMentionsTheShowListWhenTheUserHasShows(): void
    {
        $html = $this->render(3);

        $this->assertStringContainsString('along with the 3 shows on your list', $html);
        $this->assertStringContainsString((new DateTime('+2 days'))->format('F j, Y'), $html);
        $this->assertStringContainsString('hours from now', $html);
        $this->assertStringContainsString('Confirm my email', $html);
    }

    public function testItUsesTheSingularForASingleShow(): void
    {
        $this->assertStringContainsString('the 1 show on your list', $this->render(1));
    }

    public function testItOnlyMentionsTheAccountWhenTheListIsEmpty(): void
    {
        $html = $this->render(0);

        $this->assertStringNotContainsString('show list', $html);
        $this->assertStringNotContainsString('and your shows', $html);
        $this->assertStringContainsString('scheduled for deletion on', $html);
    }
}
