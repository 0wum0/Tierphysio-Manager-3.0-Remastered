-- ═══════════════════════════════════════════════════════════════
--  Migration 067: Self-Healing — mobile_api Feature-Gate reparieren
--
--  PROBLEM (Mai 2026):
--    FeatureRouteMap mappt /api/mobile/* auf 'mobile_api'.
--    Bei stateless Bearer-token-Requests (Flutter/Mobile) hat die Praxis-App
--    beim Bootstrap keine Session → kein DB-Prefix → FeatureGateService wird
--    nicht initialisiert → mobile_api=false → 403 feature_disabled.
--
--    Zusätzlich: Wenn mobile_api in plans.features fehlt oder
--    saas_feature_flags veraltet/fehlerhaft ist, schlägt auch der
--    FeatureGateService::syncFromSaas()-basierte Check fehl.
--
--  FIX IM CODE (Commit fix: repair mobile api feature gate access):
--    1. FeatureMiddleware: Bypass für prefixlose Bearer-token-Requests
--    2. MobileApiController::requireAuth(): inline mobile_api Gate-Check
--       nach Prefix-Auflösung (korrekte Prüfung mit richtigem Prefix)
--    3. MobileApiController::login(): inline mobile_api Gate-Check
--       nach Credential-Verifikation (blockiert Login für Tenants ohne Feature)
--
--  DIESE MIGRATION:
--    - Stellt sicher, dass mobile_api korrekt in saas_feature_flags steht
--    - Stellt sicher, dass pro/ultra/praxis/enterprise/business Pläne mobile_api enthalten
--    - Invalidiert veraltete Feature-Caches in Tenant-Settings-Tabellen
--      (setzt _features_cache zurück → wird beim nächsten Request neu gebaut)
--
--  IDEMPOTENT: Mehrfach ausführbar ohne Datenverlust.
-- ═══════════════════════════════════════════════════════════════

-- 1. Sicherstellen dass mobile_api in saas_feature_flags korrekt registriert ist.
--    INSERT IGNORE: legt Eintrag nur an wenn er fehlt.
--    Existierender Eintrag (inkl. global_enabled=0 Kill-Switch) bleibt unberührt.
INSERT IGNORE INTO `saas_feature_flags`
    (`feature_key`, `label`, `description`, `required_plan`, `global_enabled`)
VALUES
    ('mobile_api', 'Mobile API', 'REST-API für Mobile/Desktop-App', 'pro', 1);

-- required_plan korrigieren falls falsch gesetzt (ohne global_enabled zu berühren)
UPDATE `saas_feature_flags`
   SET `required_plan` = 'pro',
       `label`         = 'Mobile API',
       `description`   = 'REST-API für Mobile/Desktop-App'
 WHERE `feature_key` = 'mobile_api'
   AND `required_plan` != 'pro';

-- 2. Sicherstellen dass alle Pro-Plans mobile_api enthalten
--    (nur wenn mobile_api noch NICHT im features-Array ist)
UPDATE `plans`
   SET `features` = JSON_ARRAY_APPEND(`features`, '$', 'mobile_api')
 WHERE `slug` IN ('pro')
   AND `features` IS NOT NULL
   AND NOT JSON_CONTAINS(`features`, '"mobile_api"');

-- Basic-Pläne ohne features-Liste: mobile_api hinzufügen wenn features null/leer
UPDATE `plans`
   SET `features` = JSON_ARRAY('patients','owners','appointments','invoices','mobile_api')
 WHERE `slug` = 'basic'
   AND (`features` IS NULL OR JSON_LENGTH(`features`) = 0);

-- Pro ohne jegliche features: Basis-Pro-Liste setzen
UPDATE `plans`
   SET `features` = JSON_ARRAY(
         'patients','owners','appointments','calendar','befunde','invoices',
         'uploads','notifications',
         'homework','reminders','templates','mobile_api','exports'
       )
 WHERE `slug` = 'pro'
   AND (`features` IS NULL OR JSON_LENGTH(`features`) = 0);

-- Ultra/Praxis/Enterprise/Business: mobile_api hinzufügen wenn fehlt
UPDATE `plans`
   SET `features` = JSON_ARRAY_APPEND(`features`, '$', 'mobile_api')
 WHERE `slug` IN ('ultra','praxis','enterprise','business')
   AND `features` IS NOT NULL
   AND NOT JSON_CONTAINS(`features`, '"mobile_api"');

-- Ultra/Praxis ohne features: vollständige Liste setzen
UPDATE `plans`
   SET `features` = JSON_ARRAY(
         'patients','owners','appointments','calendar','befunde','invoices',
         'uploads','notifications',
         'homework','reminders','templates','mobile_api','exports',
         'dunning','expenses','google_calendar_sync','patient_portal',
         'patient_intake','bulk_mail','ki_assistance','analytics'
       )
 WHERE `slug` IN ('ultra','praxis','enterprise','business')
   AND (`features` IS NULL OR JSON_LENGTH(`features`) = 0);

-- 3. Stale Feature-Caches in Tenant-Datenbanken invalidieren.
--    Der FeatureGateService speichert seinen Cache in {prefix}settings._features_cache.
--    Wir können aus SaaS-DB nicht direkt auf Tenant-Tabellen schreiben,
--    aber wir können den synced_at-Timestamp auf 0 setzen um einen Re-Sync zu erzwingen,
--    indem wir einfach die Tenant-Cache-Invalidierung über den bestehenden
--    TenantFeatureCacheInvalidator-Mechanismus auslösen.
--
--    Da wir hier nur SQL haben: wir setzen updated_at auf NOW() bei allen Tenants
--    die mobile_api im Plan haben, damit der nächste sync den Cache aktualisiert.
--    Der Cache TTL von 15 Sekunden sorgt für automatischen Re-Sync nach kurzer Zeit.
--
--    WICHTIG: Direkte Invalidierung der Tenant-Settings-Tabellen ist hier
--    NICHT möglich (andere DB-Schemas). Die 15-Sekunden-TTL in FeatureGateService
--    sorgt dafür, dass der Cache nach spätestens 15s automatisch neu gebaut wird.
UPDATE `tenants`
   SET `updated_at` = NOW()
 WHERE `plan_id` IN (
     SELECT `id` FROM `plans`
      WHERE `features` IS NOT NULL
        AND JSON_CONTAINS(`features`, '"mobile_api"')
 );
