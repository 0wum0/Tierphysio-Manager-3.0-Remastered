<?php

declare(strict_types=1);

namespace Plugins\GoogleCalendarSync;

use App\Core\Database;
use PDO;

/**
 * Core sync logic: create / update / delete Google Calendar events.
 * All events are pushed with colorId = "3" (Grape / Lila).
 * Errors are caught and logged — the app never crashes on sync failure.
 */
class GoogleSyncService
{
    public function __construct(
        private readonly GoogleCalendarRepository $repo,
        private readonly GoogleApiService $api,
        private readonly Database $db
    ) {}

    /* ─── Sync a newly created appointment ─── */

    public function syncCreated(int $appointmentId): void
    {
        $connection = $this->repo->getConnection();
        if (!$this->shouldSync($connection)) return;

        $appointment = $this->fetchAppointmentFull($appointmentId);
        if (!$appointment) return;

        if ($connection['skip_waitlist'] && ($appointment['status'] ?? '') === 'waitlist') return;

        try {
            $calendarId  = $connection['calendar_id'] ?: 'primary';
            $eventPayload = $this->api->buildEventPayload($appointment);
            $googleEvent  = $this->api->createEvent($connection, $calendarId, $eventPayload);

            $this->repo->createSyncEntry([
                'appointment_id'     => $appointmentId,
                'connection_id'      => (int)$connection['id'],
                'google_event_id'    => $googleEvent['id'],
                'google_calendar_id' => $calendarId,
                'sync_status'        => 'synced',
            ]);

            $this->repo->log(
                (int)$connection['id'], 'create', true,
                "Termin #{$appointmentId} → Google Event {$googleEvent['id']}",
                $appointmentId, $googleEvent['id']
            );
        } catch (\Throwable $e) {
            $this->logError($connection, 'create', $appointmentId, $e->getMessage());
        }
    }

    /* ─── Sync an updated appointment ─── */

    public function syncUpdated(int $appointmentId): void
    {
        $connection = $this->repo->getConnection();
        if (!$this->shouldSync($connection)) return;

        $appointment = $this->fetchAppointmentFull($appointmentId);
        if (!$appointment) return;

        $syncEntry = $this->repo->getSyncEntryByAppointment($appointmentId);

        /* If no sync entry exists yet, create it */
        if (!$syncEntry || $syncEntry['sync_status'] === 'deleted') {
            $this->syncCreated($appointmentId);
            return;
        }

        if ($syncEntry['sync_status'] === 'failed') {
            /* Retry create if it never made it to Google */
            if (empty($syncEntry['google_event_id'])) {
                $this->syncCreated($appointmentId);
                return;
            }
        }

        try {
            $calendarId   = $syncEntry['google_calendar_id'];
            $googleEventId = $syncEntry['google_event_id'];
            $eventPayload  = $this->api->buildEventPayload($appointment);

            $this->api->updateEvent($connection, $calendarId, $googleEventId, $eventPayload);

            $this->repo->updateSyncEntry((int)$syncEntry['id'], [
                'sync_status'   => 'synced',
                'last_synced_at'=> date('Y-m-d H:i:s'),
                'last_error'    => null,
            ]);

            $this->repo->log(
                (int)$connection['id'], 'update', true,
                "Termin #{$appointmentId} aktualisiert → Google Event {$googleEventId}",
                $appointmentId, $googleEventId
            );
        } catch (\Throwable $e) {
            $this->repo->markSyncFailed((int)$syncEntry['id'], $e->getMessage());
            $this->logError($connection, 'update', $appointmentId, $e->getMessage());
        }
    }

    /* ─── Sync a deleted/cancelled appointment ─── */

    public function syncDeleted(int $appointmentId): void
    {
        $connection = $this->repo->getConnection();
        if (!$this->shouldSync($connection)) return;

        $syncEntry = $this->repo->getSyncEntryByAppointment($appointmentId);
        if (!$syncEntry || empty($syncEntry['google_event_id'])) return;
        if ($syncEntry['sync_status'] === 'deleted') return;

        try {
            $this->api->deleteEvent(
                $connection,
                $syncEntry['google_calendar_id'],
                $syncEntry['google_event_id']
            );

            $this->repo->updateSyncEntry((int)$syncEntry['id'], [
                'sync_status'   => 'deleted',
                'last_synced_at'=> date('Y-m-d H:i:s'),
                'last_error'    => null,
            ]);

            $this->repo->log(
                (int)$connection['id'], 'delete', true,
                "Termin #{$appointmentId} gelöscht in Google (Event {$syncEntry['google_event_id']})",
                $appointmentId, $syncEntry['google_event_id']
            );
        } catch (\Throwable $e) {
            $this->repo->markSyncFailed((int)$syncEntry['id'], $e->getMessage());
            $this->logError($connection, 'delete', $appointmentId, $e->getMessage());
        }
    }

    /* ─── Bulk sync all unsynced appointments ─── */

    public function bulkSyncAll(): array
    {
        $connection = $this->repo->getConnection();
        if (!$this->shouldSync($connection)) {
            return ['success' => 0, 'skipped' => 0, 'failed' => 0, 'error' => 'Sync not enabled or no connection'];
        }

        $stmt = $this->db->query(
            'SELECT a.id FROM appointments a
             LEFT JOIN google_calendar_sync_map m ON m.appointment_id = a.id
             WHERE m.id IS NULL
               AND a.status NOT IN (\'cancelled\',\'noshow\')
               AND a.start_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             LIMIT 100'
        );
        $unsynced = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $success = 0;
        $failed  = 0;
        $skipped = 0;

        foreach ($unsynced as $row) {
            try {
                $this->syncCreated((int)$row['id']);
                $success++;
            } catch (\Throwable) {
                $failed++;
            }
        }

        return ['success' => $success, 'skipped' => $skipped, 'failed' => $failed];
    }

    /* ─── Test sync (single test event) ─── */

    public function testSync(): array
    {
        $connection = $this->repo->getConnection();
        if (!$connection) {
            return ['success' => false, 'message' => 'Kein Google Konto verbunden.'];
        }

        try {
            $calendarId = $connection['calendar_id'] ?: 'primary';
            $testPayload = [
                'summary'     => '✅ Tierphysio Test-Sync',
                'description' => 'Dieser Test-Termin wurde von Tierphysio Manager erstellt und wird automatisch gelöscht.',
                'colorId'     => GoogleApiService::COLOR_ID_GRAPE,
                'start'       => ['dateTime' => date(\DateTime::RFC3339, strtotime('+1 hour')), 'timeZone' => 'Europe/Berlin'],
                'end'         => ['dateTime' => date(\DateTime::RFC3339, strtotime('+2 hours')), 'timeZone' => 'Europe/Berlin'],
            ];

            $event = $this->api->createEvent($connection, $calendarId, $testPayload);
            /* Delete it right away */
            $this->api->deleteEvent($connection, $calendarId, $event['id']);

            $this->repo->log((int)$connection['id'], 'test', true, 'Test-Sync erfolgreich: Event ' . $event['id'] . ' erstellt und gelöscht.');

            return ['success' => true, 'message' => 'Test-Sync erfolgreich! Lila Event wurde kurz erstellt und wieder gelöscht.'];
        } catch (\Throwable $e) {
            $this->repo->log((int)($connection['id'] ?? 0), 'test', false, $e->getMessage());
            return ['success' => false, 'message' => 'Test fehlgeschlagen: ' . $e->getMessage()];
        }
    }

    /* ─── Pull Google → Tierphysio (2-way sync) ─── */

    public function pullFromGoogle(?string $timeMin = null): array
    {
        $connection = $this->repo->getConnection();
        if (!$connection || empty($connection['access_token'])) {
            return ['success' => false, 'message' => 'Kein Google Konto verbunden.', 'imported' => 0, 'updated' => 0, 'deleted' => 0];
        }

        $calendarId = $connection['calendar_id'] ?: 'primary';
        $syncToken  = !empty($connection['sync_token']) ? $connection['sync_token'] : null;

        $imported = 0;
        $updated  = 0;
        $deleted  = 0;

        try {
            $result = $this->api->listEvents($connection, $calendarId, $syncToken, $timeMin);
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'SYNC_TOKEN_EXPIRED') {
                /* syncToken expired — clear it and do a full re-sync */
                $this->repo->clearSyncToken((int)$connection['id']);
                $this->repo->log((int)$connection['id'], 'pull', true, 'SyncToken abgelaufen, führe vollständige Synchronisation durch.');
                return $this->pullFromGoogle($timeMin);
            }
            if ($e->getMessage() === 'TOKEN_REVOKED') {
                $msg = 'Google-Zugang wurde widerrufen. Bitte unter Einstellungen → Google Kalender erneut verbinden.';
                $this->repo->log((int)$connection['id'], 'error', false, $msg);
                return ['success' => false, 'message' => $msg, 'imported' => 0, 'updated' => 0, 'deleted' => 0];
            }
            $this->repo->log((int)$connection['id'], 'error', false, 'Pull fehlgeschlagen: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage(), 'imported' => 0, 'updated' => 0, 'deleted' => 0];
        } catch (\Throwable $e) {
            $this->repo->log((int)$connection['id'], 'error', false, 'Pull fehlgeschlagen: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage(), 'imported' => 0, 'updated' => 0, 'deleted' => 0];
        }

        foreach ($result['items'] as $event) {
            $googleEventId = $event['id'] ?? null;
            if (!$googleEventId) continue;

            /* Skip events originally pushed by Tierphysio to avoid duplicates */
            $source = $event['extendedProperties']['private']['tierphysio_source'] ?? null;
            if ($source === 'tierphysio-manager') continue;

            /* Handle cancellations / deletions */
            if (($event['status'] ?? '') === 'cancelled') {
                $this->repo->deleteImportedEvent($googleEventId, (int)$connection['id']);
                $deleted++;
                continue;
            }

            /* Parse start/end — support both dateTime and all-day date */
            $isAllDay  = isset($event['start']['date']) && !isset($event['start']['dateTime']);
            $startRaw  = $event['start']['dateTime'] ?? ($event['start']['date'] ?? null);
            $endRaw    = $event['end']['dateTime']   ?? ($event['end']['date']   ?? null);

            if (!$startRaw) continue;

            try {
                $startDt = new \DateTime($startRaw);
                $endDt   = $endRaw ? new \DateTime($endRaw) : (clone $startDt)->modify('+1 hour');
            } catch (\Throwable) {
                continue;
            }

            $existing = $this->repo->getImportedEventByGoogleId($googleEventId, (int)$connection['id']);

            $this->repo->upsertImportedEvent([
                'connection_id'      => (int)$connection['id'],
                'google_event_id'    => $googleEventId,
                'google_calendar_id' => $calendarId,
                'appointment_id'     => $existing['appointment_id'] ?? null,
                'event_title'        => mb_substr($event['summary'] ?? '(Kein Titel)', 0, 500),
                'event_start'        => $startDt->format('Y-m-d H:i:s'),
                'event_end'          => $endDt->format('Y-m-d H:i:s'),
                'event_description'  => mb_substr($event['description'] ?? '', 0, 65535),
                'is_all_day'         => $isAllDay ? 1 : 0,
                'google_status'      => $event['status'] ?? 'confirmed',
                'raw_json'           => json_encode($event),
            ]);

            if ($existing) {
                $updated++;
            } else {
                $imported++;
            }
        }

        /* Save the new syncToken for next incremental pull */
        if (!empty($result['nextSyncToken'])) {
            $this->repo->saveSyncToken((int)$connection['id'], $result['nextSyncToken']);
        }

        $total = $imported + $updated + $deleted;
        $this->repo->log(
            (int)$connection['id'], 'pull', true,
            "Pull abgeschlossen: {$imported} neu, {$updated} aktualisiert, {$deleted} gelöscht. Gesamt: {$total} Events."
        );

        return [
            'success'  => true,
            'message'  => "Pull erfolgreich: {$imported} neue, {$updated} aktualisierte, {$deleted} gelöschte Events.",
            'imported' => $imported,
            'updated'  => $updated,
            'deleted'  => $deleted,
        ];
    }

    /* ─── Helpers ─── */

    private function shouldSync(?array $connection): bool
    {
        return $connection !== null
            && !empty($connection['sync_enabled'])
            && !empty($connection['auto_sync'])
            && !empty($connection['access_token']);
    }

    private function fetchAppointmentFull(int $id): ?array
    {
        try {
            $stmt = $this->db->query(
                'SELECT a.*,
                        p.name AS patient_name,
                        o.first_name, o.last_name,
                        tt.name AS treatment_type_name
                 FROM appointments a
                 LEFT JOIN patients p ON p.id = a.patient_id
                 LEFT JOIN owners o ON o.id = a.owner_id
                 LEFT JOIN treatment_types tt ON tt.id = a.treatment_type_id
                 WHERE a.id = ? LIMIT 1',
                [$id]
            );
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function logError(?array $connection, string $action, int $appointmentId, string $error): void
    {
        $connectionId = $connection ? (int)$connection['id'] : 0;
        error_log("[GoogleCalendarSync] {$action} failed for appointment #{$appointmentId}: {$error}");
        $this->repo->log($connectionId, 'error', false, $error, $appointmentId);
    }
}
