<?php

declare(strict_types=1);

namespace App\Services;

use App\Config;
use App\Mail\DeletionReminderEmail;
use DateTime;
use Doctrine\ORM\EntityManager;

class AccountCleanupService
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly Config $config,
        private readonly DeletionReminderEmail $deletionReminderEmail
    ) {
    }

    public function run(): int
    {
        $now = new DateTime();

        $this->sendDeletionReminders($now);

        return $this->deleteUnverifiedAccounts($now);
    }

    /**
     * Warns unverified accounts whose deletion is within the configured lead
     * time, once per account.
     */
    public function sendDeletionReminders(?DateTime $now = null): int
    {
        $now = $now ?? new DateTime();
        [$ttlDays, $leadHours] = $this->retentionPolicy();

        $reminderCutoff = (clone $now)->modify(sprintf('-%d hours', $ttlDays * 24 - $leadHours));
        $deletionCutoff = (clone $now)->modify(sprintf('-%d hours', $ttlDays * 24));

        $users = $this->entityManager->createQuery(
            'SELECT u FROM App\Entity\User u
             WHERE u.verifiedAt IS NULL
               AND u.deletionNoticeSentAt IS NULL
               AND u.createdAt <= :reminderCutoff
               AND u.createdAt >= :deletionCutoff'
        )
            ->setParameter('reminderCutoff', $reminderCutoff)
            ->setParameter('deletionCutoff', $deletionCutoff)
            ->getResult();

        foreach ($users as $user) {
            $deletionDate = (clone $user->getCreatedAt())->modify(sprintf('+%d days', $ttlDays));

            // Marked before queueing: EmailSendingService flushes on this same
            // EntityManager, so the notice and the queued email are written by
            // the same flush, and a failure leaves the user eligible again.
            $user->setDeletionNoticeSentAt($now);
            $this->deletionReminderEmail->send($user, $deletionDate);
        }

        if ($users) {
            $this->entityManager->flush();
        }

        return count($users);
    }

    private function deleteUnverifiedAccounts(DateTime $now): int
    {
        [$ttlDays] = $this->retentionPolicy();

        $query = $this->entityManager->createQuery(
            'delete from App\Entity\User u where u.createdAt < :cutoff and u.verifiedAt is NULL'
        )->setParameter('cutoff', (clone $now)->modify(sprintf('-%d days', $ttlDays)));

        return (int) $query->execute();
    }

    /**
     * @return array{int, int} [unverified_ttl_days, deletion_notice_lead_hours]
     */
    private function retentionPolicy(): array
    {
        $ttlDays = (int) $this->config->get('account.unverified_ttl_days', 5);
        $leadHours = (int) $this->config->get('account.deletion_notice_lead_hours', 48);

        if ($ttlDays < 1 || $leadHours < 1 || $leadHours >= $ttlDays * 24) {
            error_log(sprintf(
                'Account cleanup: invalid policy (unverified_ttl_days=%d, deletion_notice_lead_hours=%d), clamping',
                $ttlDays,
                $leadHours
            ));
            $ttlDays = max(1, $ttlDays);
            $leadHours = min(max($leadHours, 1), $ttlDays * 24 - 1);
        }

        return [$ttlDays, $leadHours];
    }
}
