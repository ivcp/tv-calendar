<?php

declare(strict_types=1);

namespace App\Services;

use App\Config;
use DateTime;

/**
 * Removes local shows that no longer exist in TVMaze's database.
 *
 */
class ShowCleanupService
{
    /**
     * Stay below TVMaze's 20 requests / 10 seconds limit while verifying
     * candidates one by one.
     */
    private const TVMAZE_CHECK_DELAY_MICROSECONDS = 600_000;

    public function __construct(
        private readonly ShowService $showService,
        private readonly TvMazeService $tvMazeService,
        private readonly WebhookService $webhookService,
        private readonly Config $config,
        private readonly int $checkDelayMicroseconds = self::TVMAZE_CHECK_DELAY_MICROSECONDS
    ) {}

    /**
     * @return array{
     *     dryRun: bool,
     *     local: int,
     *     live: int,
     *     candidates: int,
     *     dead: int,
     *     kept: int,
     *     deleted: int,
     *     episodes: int,
     *     subscriptions: int,
     *     errors: int
     * }
     */
    public function run(bool $dryRun = true): array
    {
        $stats = [
            'dryRun' => $dryRun,
            'local' => 0,
            'live' => 0,
            'candidates' => 0,
            'dead' => 0,
            'kept' => 0,
            'deleted' => 0,
            'episodes' => 0,
            'subscriptions' => 0,
            'errors' => 0,
        ];

        $liveShowIds = $this->tvMazeService->getLiveShowIds();

        if ($liveShowIds === null) {
            $stats['errors']++;
            error_log('ERROR show cleanup: could not fetch the TVMaze show list, aborting');
            $this->report($stats);

            return $stats;
        }

        if (!$liveShowIds) {
            $stats['errors']++;
            error_log('ERROR show cleanup: TVMaze returned an empty show list, aborting');
            $this->report($stats);

            return $stats;
        }

        $liveShowIds = array_fill_keys($liveShowIds, true);
        $localShows = $this->showService->getTvMazeIdMap();

        $stats['local'] = count($localShows);
        $stats['live'] = count($liveShowIds);

        $candidates = [];
        foreach ($localShows as $tvMazeId => $localId) {
            if (!isset($liveShowIds[$tvMazeId])) {
                $candidates[$localId] = $tvMazeId;
            }
        }

        $stats['candidates'] = count($candidates);

        $details = [];
        foreach ($this->showService->getShowCleanupDetails(array_keys($candidates)) as $row) {
            $details[(int) $row['id']] = $row;
        }

        $deadShowIds = [];

        foreach ($candidates as $localId => $tvMazeId) {
            $gone = $this->tvMazeService->isShowGone($tvMazeId);
            $detail = $details[$localId] ?? null;

            if ($gone === true) {
                $deadShowIds[] = $localId;
                $stats['dead']++;
                $stats['episodes'] += (int) ($detail['episodes'] ?? 0);
                $stats['subscriptions'] += (int) ($detail['subscribers'] ?? 0);

                echo sprintf(
                    "%s local=%d tv_maze=%d \"%s\" episodes=%d subscribers=%d\n",
                    $dryRun ? 'WOULD DELETE' : 'DEAD',
                    $localId,
                    $tvMazeId,
                    $detail['name'] ?? '',
                    (int) ($detail['episodes'] ?? 0),
                    (int) ($detail['subscribers'] ?? 0)
                );
            } elseif ($gone === false) {
                $stats['kept']++;
                echo sprintf(
                    "KEPT (still live) local=%d tv_maze=%d \"%s\"\n",
                    $localId,
                    $tvMazeId,
                    $detail['name'] ?? ''
                );
            } else {
                $stats['errors']++;
                error_log(sprintf(
                    'ERROR show cleanup: could not verify tv_maze_id %d (local %d), keeping it',
                    $tvMazeId,
                    $localId
                ));
            }

            if ($this->checkDelayMicroseconds > 0) {
                usleep($this->checkDelayMicroseconds);
            }
        }

        if (!$dryRun && $deadShowIds) {
            try {
                $stats['deleted'] = $this->showService->deleteShows($deadShowIds);
            } catch (\Throwable $e) {
                $stats['errors']++;
                error_log('ERROR show cleanup delete: ' . $e->getMessage());
            }
        }

        $this->report($stats);

        if ($stats['errors'] > 0) {
            $this->webhookService->send(
                $this->config->get('webhook_url'),
                "Show cleanup: {$stats['errors']} error(s) occurred. Check the logs."
            );
        }

        return $stats;
    }

    /**
     * @param array{
     *     dryRun: bool,
     *     local: int,
     *     live: int,
     *     candidates: int,
     *     dead: int,
     *     kept: int,
     *     deleted: int,
     *     episodes: int,
     *     subscriptions: int,
     *     errors: int
     * } $stats
     */
    private function report(array $stats): void
    {
        $dateTime = (new DateTime())->format('Y-m-d H:i:s');
        $mode = $stats['dryRun'] ? 'DRY RUN (nothing deleted)' : 'APPLY';

        echo <<<REPORT
        --------------------------
        SHOW CLEANUP $dateTime
        MODE: $mode
        LOCAL SHOWS: {$stats['local']}
        TVMAZE SHOWS: {$stats['live']}
        CANDIDATES (MISSING FROM TVMAZE): {$stats['candidates']}
        CONFIRMED DEAD (404): {$stats['dead']}
        KEPT (STILL LIVE): {$stats['kept']}
        SHOWS DELETED: {$stats['deleted']}
        EPISODES AFFECTED: {$stats['episodes']}
        SUBSCRIPTIONS AFFECTED: {$stats['subscriptions']}
        ERRORS: {$stats['errors']}
        --------------------------\n
        REPORT;
    }
}
