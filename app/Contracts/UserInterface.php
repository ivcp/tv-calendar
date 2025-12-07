<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Enum\NotificationTime;
use DateTime;

interface UserInterface
{
    public function getId(): int;
    public function getPassword(): ?string;
    public function setPassword(?string $password): self;
    public function getEmail(): string;
    public function getVerifiedAt(): ?DateTime;
    public function setVerifiedAt(DateTime $date): self;
    public function setUpdatedAt(DateTime $date): self;
    public function getStartOfWeekSunday(): bool;
    public function getNtfyTopic(): ?string;
    public function setNtfyTopic(?string $ntfyTopic): self;
    public function getNotificationTime(): NotificationTime;
    public function setNotificationTime(NotificationTime $notificationTime): self;
}
