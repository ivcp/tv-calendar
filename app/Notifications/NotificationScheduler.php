<?php

declare(strict_types=1);

namespace App\Notifications;

use SplObserver;
use SplSubject;

class NotificationScheduler implements SplSubject
{

    private int $messagesQueued = 0;
    private int $errorsSchedulingMessage = 0;

    /** @var SplObserver[] $observers */
    private array $observers = [];

    public function attach(SplObserver $observer): void
    {
        if (!in_array($observer, $this->observers, true)) {
            $this->observers[] = $observer;
        }
    }

    public function detach(SplObserver $observer): void
    {
        $key = array_search($observer, $this->observers);
        if ($key !== false) {
            unset($this->observers[$key]);
        }
    }

    public function notify(): void
    {
        foreach ($this->observers as $observer) {
            $observer->update($this);
        }
    }



    public function getMessagesQueued(): int
    {
        return $this->messagesQueued;
    }


    public function incrementMessagesCount(): void
    {
        $this->messagesQueued += 1;
    }


    public function getErrorsSchedulingMessage(): int
    {
        return $this->errorsSchedulingMessage;
    }

    public function incrementErrorCount(): void
    {
        $this->errorsSchedulingMessage += 1;
    }
}
