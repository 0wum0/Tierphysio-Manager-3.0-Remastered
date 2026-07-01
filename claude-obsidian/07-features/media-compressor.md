# Client-seitige Medien-Kompression

## Beschreibung
Browser-seitige Video-/Bildkomprimierung vor dem Upload via ffmpeg.wasm, ergänzt den serverseitigen `MediaOptimizerService`.

## Status
**implemented** (verifiziert 2026-07-01, Vollaudit)

## Relevante Dateien im Repo
- `public/assets/js/media-compressor.js` (~12 KB)

## Funktionsumfang
- Nutzt ffmpeg.wasm on-demand zur Kompression im Browser vor Upload
- Fallback-Kette bei fehlender WASM-Unterstützung
- Reduziert Upload-Traffic und Serverlast, bevor `MediaOptimizerService` serverseitig nachoptimiert

## Wichtige Regeln
- API-Verträge dürfen nicht breaking geändert werden.
- Große WASM-Payloads können auf schwacher Hardware/Mobile Ladezeit-Probleme verursachen — Fallback-Pfad testen.

## TODOs
- Fachlichen Soll-/Ist-Vergleich ergänzen.
- E2E-Flow dokumentieren.

## Verlinkungen
- [[07-features/video-feedback]]
- [[07-features/chat-media-system]]
