<?php

declare(strict_types=1);

namespace Plugins\GoogleCalendarSync;

use App\Core\Database;
use PDO;

class GoogleCalendarRepository
{
    public function __construct(private readonly Database $db) {}

    /* ─── Connections ─── */

    public function getConnection(): ?array
    {
        $stmt = $this->db->query(
            'SELECT * FROM google_calendar_connections ORDER BY id ASC LIMIT 1'
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getConnectionById(int $id): ?array
    {
        $stmt = $this->db->query(
            'SELECT * FROM google_calendar_connections WHERE id = ? LIMIT 1',
            [$id]
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function upsertConnection(array $data): int
    {
        $existing = $this->getConnection();
        if ($existing) {
            $sets   = implode(', ', array_map(fn($k) => "`{$k}` = ?", array_keys($data)));
            $values = array_values($data);
            $values[] = $existing['id'];
            $this->db->query("UPDATE google_calendar_connections SET {$sets} WHERE id = ?", $values);
            return (int)$existing['id'];
        }

        $cols         = implode(', ', array_map(fn($k) => "`{$k}`", array_keys($data)));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $this->db->query(
            "INSERT INTO google_calendar_connections ({$cols}) VALUES ({$placeholders})",
            array_values($data)
        );
        return (int)$this->db->lastInsertId();
    }

    public function updateConnection(int $id, array $data): void
    {
        $sets   = implode(', ', array_map(fn($k) => "`{$k}` = ?", array_keys($data)));
        $values = array_values($data);
        $values[] = $id;
        $this->db->query("UPDATE google_calendar_connections SET {$sets} WHERE id = ?", $values);
    }

    public function deleteConnection(int $id): void
    {
        $this->db->query('DELETE FROM google_calendar_connections WHERE id = ?', [$id]);
    }

    /* ─── Sync Map ─── */

    public function getSyncEntry(int $appointmentId, int $connectionId): ?array
    {
        $stmt = $this->db->query(
            'SELECT * FROM google_calendar_sync_map WHERE appointment_id = ? AND connection_id = ? LIMIT 1',
            [$appointmentId, $connectionId]
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getSyncEntryByAppointment(int $appointmentId): ?array
    {
        $stmt = $this->db->query(
            'SELECT * FROM google_calendar_sync_map WHERE appointment_id = ? LIMIT 1',
            [$appointmentId]
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function createSyncEntry(array $data): int
    {
        $this->db->query(
            'INSERT INTO google_calendar_sync_map
             (appointment_id, connection_id, google_event_id, google_calendar_id, sync_status, last_synced_at)
             VALUES (?, ?, ?, ?, ?, NOW())',
            [
                $data['appointment_id'],
                $data['connection_id'],
                $data['google_event_id'],
                $data['google_calendar_id'],
                $data['sync_status'] ?? 'synced',
            ]
        );
        return (int)$this->db->lastInsertId();
    }

    public function updateSyncEntry(int $id, array $data): void
    {
        $sets   = implode(', ', array_map(fn($k) => "`{$k}` = ?", array_keys($data)));
        $values = array_values($data);
        $values[] = $id;
        $this->db->query("UPDATE google_calendar_sync_map SET {$sets} WHERE id = ?", $values);
    }

    public function markSyncFailed(int $syncMapId, string $error): void
    {
        $this->db->query(
            'UPDATE google_calendar_sync_map SET sync_status = ?, last_error = ?, updated_at = NOW() WHERE id = ?',
            ['failed', $error, $syncMapId]
        );
    }

    public function markSyncDeleted(int $appointmentId): void
    {
        $this->db->query(
            'UPDATE google_calendar_sync_map SET sync_status = ?, updated_at = NOW() WHERE appointment_id = ?',
            ['deleted', $appointmentId]
        );
    }

    public function getPendingSyncs(): array
    {
        $stmt = $this->db->query(
            'SELECT m.*, a.title, a.start_at, a.end_at, a.description, a.status,
                    a.patient_id, a.owner_id, a.treatment_type_id,
                    p.name AS patient_name,
                    o.first_name, o.last_name,
                    tt.name AS treatment_type_name
             FROM google_calendar_sync_map m
             JOIN appointments a ON a.id = m.appointment_id
             LEFT JOIN patients p ON p.id = a.patient_id
             LEFT JOIN owners o ON o.id = a.owner_id
             LEFT JOIN treatment_types tt ON tt.id = a.treatment_type_id
             WHERE m.sync_status = ?',
            ['pending']
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getRecentSyncedCount(int $hours = 24): int
    {
        $stmt = $this->db->query(
            'SELECT COUNT(*) FROM google_calendar_sync_map
             WHERE sync_status = ? AND last_synced_at > DATE_SUB(NOW(), INTERVAL ? HOUR)',
            ['synced', $hours]
        );
        return (int)$stmt->fetchColumn();
    }

    /* ─── Sync Log ─── */

    public function log(int $connectionId, string $action, bool $success, string $message, ?int $appointmentId = null, ?string $googleEventId = null): void
    {
        try {
            $this->db->query(
                'INSERT INTO google_calendar_sync_log (connection_id, action, appointment_id, google_event_id, message, success)
                 VALUES (?, ?, ?, ?, ?, ?)',
                [$connectionId, $action, $appointmentId, $googleEventId, $message, $success ? 1 : 0]
            );
        } catch (\Throwable) {
            /* Log failure must never crash the app */
        }
    }

    public function getRecentLogs(int $limit = 20): array
    {
        $stmt = $this->db->query(
            'SELECT * FROM google_calendar_sync_log ORDER BY created_at DESC LIMIT ?',
            [$limit]
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getLastSuccessfulSync(): ?string
    {
        $stmt = $this->db->query(
            'SELECT created_at FROM google_calendar_sync_log
             WHERE success = 1 AND action IN (\'create\',\'update\',\'delete\')
             ORDER BY created_at DESC LIMIT 1'
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['created_at'] ?? null;
    }

    public function getLastError(): ?array
    {
        $stmt = $this->db->query(
            'SELECT * FROM google_calendar_sync_log WHERE success = 0 ORDER BY created_at DESC LIMIT 1'
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
