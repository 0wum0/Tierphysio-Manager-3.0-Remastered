<?php

declare(strict_types=1);

namespace Saas\Migrations;

use Saas\Core\Database;

class UpdateCalendarAppointments
{
    public function __construct(private readonly Database $db)
    {
    }

    public function up(): void
    {
        // Alle aktiven Tenants abrufen
        $tenants = $this->db->fetchAll("SELECT db_name FROM tenants WHERE status IN ('active','trial') AND db_name IS NOT NULL");

        foreach ($tenants as $tenant) {
            $prefix = rtrim((string)($tenant['db_name'] ?? ''), '_') . '_';
            $appointmentsTable = $prefix . 'appointments';

            try {
                // Fehlende Spalten hinzufügen (alle potenziell fehlenden Spalten)
                $columns = [
                    "ALTER TABLE `{$appointmentsTable}` ADD COLUMN IF NOT EXISTS `recurrence_rule`       VARCHAR(512) NULL DEFAULT NULL AFTER `user_id`",
                    "ALTER TABLE `{$appointmentsTable}` ADD COLUMN IF NOT EXISTS `recurrence_parent`     INT UNSIGNED NULL DEFAULT NULL AFTER `recurrence_rule`",
                    "ALTER TABLE `{$appointmentsTable}` ADD COLUMN IF NOT EXISTS `patient_email`         VARCHAR(255) NULL DEFAULT NULL AFTER `owner_id`",
                    "ALTER TABLE `{$appointmentsTable}` ADD COLUMN IF NOT EXISTS `send_patient_reminder` TINYINT(1) NOT NULL DEFAULT 0",
                    "ALTER TABLE `{$appointmentsTable}` ADD COLUMN IF NOT EXISTS `patient_reminder_sent` TINYINT(1) NOT NULL DEFAULT 0",
                ];
                foreach ($columns as $sql) {
                    try { $this->db->query($sql, []); } catch (\Throwable $inner) {}
                }

                // Standard-Erinnerung aktualisieren
                $this->db->query("UPDATE `{$appointmentsTable}` SET `reminder_minutes` = 1440
                    WHERE `reminder_minutes` = 60 OR `reminder_minutes` IS NULL", []);

                echo "Updated appointments table for tenant: {$tenant['db_name']}\n";
            } catch (\Throwable $e) {
                echo "Error updating appointments table for tenant {$tenant['db_name']}: " . $e->getMessage() . "\n";
            }
        }

        echo "Calendar appointments migration completed.\n";
    }

    public function down(): void
    {
        // Rollback ist für diese Migration nicht erforderlich
        echo "Rollback not implemented for this migration.\n";
    }
}
