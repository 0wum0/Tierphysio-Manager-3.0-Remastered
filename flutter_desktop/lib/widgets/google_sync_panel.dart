import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../services/api_service.dart';
import '../core/theme.dart';

class GoogleSyncPanel extends StatefulWidget {
  final VoidCallback? onPullDone;
  const GoogleSyncPanel({super.key, this.onPullDone});

  @override
  State<GoogleSyncPanel> createState() => _GoogleSyncPanelState();
}

class _GoogleSyncPanelState extends State<GoogleSyncPanel>
    with SingleTickerProviderStateMixin {
  final _api = ApiService();

  Map<String, dynamic>? _status;
  bool _loading   = true;
  bool _pulling   = false;
  bool _pushing   = false;
  String? _error;

  late AnimationController _spinCtrl;

  static const _googleBlue   = Color(0xFF4285F4);
  static const _googleGreen  = Color(0xFF34A853);
  static const _googleOrange = Color(0xFFFBBC04);

  @override
  void initState() {
    super.initState();
    _spinCtrl = AnimationController(
      vsync: this, duration: const Duration(seconds: 2));
    _loadStatus();
  }

  @override
  void dispose() { _spinCtrl.dispose(); super.dispose(); }

  Future<void> _loadStatus() async {
    setState(() { _loading = true; _error = null; });
    try {
      final s = await _api.googleSyncStatus();
      if (mounted) setState(() { _status = s; _loading = false; });
    } catch (e) {
      if (mounted) setState(() { _error = e.toString(); _loading = false; });
    }
  }

  Future<void> _runSync(Future<Map<String, dynamic>> Function() call) async {
    _spinCtrl.repeat();
    try {
      final result = await call();
      if (mounted) {
        _spinCtrl.stop(); _spinCtrl.reset();
        final ok = result['success'] as bool? ?? false;
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(
          content: Row(children: [
            Icon(ok ? Icons.check_circle_rounded : Icons.error_outline_rounded,
              color: Colors.white, size: 16),
            const SizedBox(width: 8),
            Expanded(child: Text(result['message'] as String? ?? 'Fertig')),
          ]),
          backgroundColor: ok ? AppTheme.success : AppTheme.danger,
          behavior: SnackBarBehavior.floating,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
          margin: const EdgeInsets.all(12),
        ));
        widget.onPullDone?.call();
        _loadStatus();
      }
    } catch (e) {
      if (mounted) {
        _spinCtrl.stop(); _spinCtrl.reset();
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(
          content: Text(e.toString()),
          backgroundColor: AppTheme.danger,
          behavior: SnackBarBehavior.floating,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
          margin: const EdgeInsets.all(12),
        ));
      }
    }
  }

  Future<void> _pull() async {
    setState(() => _pulling = true);
    await _runSync(_api.googleSyncPull);
    if (mounted) setState(() => _pulling = false);
  }

  Future<void> _push() async {
    setState(() => _pushing = true);
    await _runSync(_api.googleSyncPush);
    if (mounted) setState(() => _pushing = false);
  }

  String _fmtDate(String? s) {
    if (s == null) return '–';
    try { return DateFormat('dd.MM. HH:mm', 'de_DE').format(DateTime.parse(s)); }
    catch (_) { return s; }
  }

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      top: false,
      child: ConstrainedBox(
        constraints: BoxConstraints(
          maxHeight: MediaQuery.of(context).size.height * 0.85,
        ),
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          Container(
            width: 40, height: 4,
            margin: const EdgeInsets.only(top: 12, bottom: 8),
            decoration: BoxDecoration(
              color: Colors.grey.shade300,
              borderRadius: BorderRadius.circular(2)),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 4, 8, 10),
            child: Row(children: [
              Container(
                width: 36, height: 36,
                decoration: BoxDecoration(
                  color: _googleBlue.withValues(alpha: 0.12),
                  shape: BoxShape.circle),
                child: const Icon(Icons.sync_rounded,
                  color: _googleBlue, size: 20),
              ),
              const SizedBox(width: 12),
              Expanded(child: Column(
                crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text('Google Kalender Sync',
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w700)),
                const Text('2-Wege-Synchronisation',
                  style: TextStyle(fontSize: 11, color: _googleBlue,
                    fontWeight: FontWeight.w600)),
              ])),
              IconButton(
                icon: const Icon(Icons.refresh_rounded),
                onPressed: _loading ? null : _loadStatus,
                tooltip: 'Neu laden',
              ),
            ]),
          ),
          const Divider(height: 1),
          Flexible(
            child: _loading
                ? const Center(child: Padding(
                    padding: EdgeInsets.all(40),
                    child: CircularProgressIndicator()))
                : _error != null
                    ? _buildError()
                    : _buildContent(),
          ),
        ]),
      ),
    );
  }

  Widget _buildError() => Padding(
    padding: const EdgeInsets.all(24),
    child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
      const Icon(Icons.error_outline_rounded, size: 40, color: Colors.red),
      const SizedBox(height: 12),
      Text(_error!, textAlign: TextAlign.center,
        style: const TextStyle(fontSize: 13)),
      const SizedBox(height: 16),
      FilledButton.icon(
        icon: const Icon(Icons.refresh_rounded, size: 16),
        label: const Text('Nochmal'),
        onPressed: _loadStatus,
      ),
    ]),
  );

  Widget _buildContent() {
    final cs        = Theme.of(context).colorScheme;
    final s         = _status!;
    final connected = s['connected'] as bool? ?? false;
    final enabled   = s['sync_enabled'] as bool? ?? false;
    final autoSync  = s['auto_sync'] as bool? ?? false;
    final email     = s['google_email'] as String? ?? '';
    final calName   = s['calendar_name'] as String? ?? 'Primär';
    final lastErr   = s['last_error'] as Map?;
    final logs      = (s['recent_logs'] as List? ?? []);

    // Push stats
    final pushLastAt  = s['push_last_at']  as String?;
    final pushPending = s['push_pending']  as int? ?? 0;
    final pushToday   = s['push_today']    as int? ?? 0;

    // Pull stats
    final pullLastAt = s['pull_last_at'] as String?;
    final pullToday  = s['pull_today']   as int? ?? 0;

    return SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [

        // ── Verbindungsstatus ──
        _StatusCard(
          connected: connected,
          enabled: enabled,
          email: email,
          autoSync: autoSync,
          calName: calName,
        ),

        if (!connected) ...[
          const SizedBox(height: 12),
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: cs.surfaceContainerHighest,
              borderRadius: BorderRadius.circular(10)),
            child: const Row(crossAxisAlignment: CrossAxisAlignment.start,
              children: [
              Icon(Icons.info_outline_rounded, size: 16, color: Colors.blue),
              SizedBox(width: 8),
              Expanded(child: Text(
                'Google Sync einrichten: Im Browser unter Einstellungen → Google Kalender Sync.',
                style: TextStyle(fontSize: 12))),
            ]),
          ),
        ],

        if (connected) ...[
          const SizedBox(height: 16),

          // ── 2-Wege-Sync Übersicht ──
          Row(children: [
            const Icon(Icons.swap_horiz_rounded, size: 16, color: _googleBlue),
            const SizedBox(width: 6),
            Text('2-Wege-Synchronisation',
              style: Theme.of(context).textTheme.labelLarge?.copyWith(
                fontWeight: FontWeight.w700, color: _googleBlue)),
          ]),
          const SizedBox(height: 10),

          // Push-Karte (TheraPano → Google)
          _SyncDirectionCard(
            icon: Icons.upload_rounded,
            color: _googleGreen,
            title: 'TheraPano → Google',
            subtitle: 'Termine aus TheraPano in Google Kalender übertragen',
            lastAt: _fmtDate(pushLastAt),
            todayCount: pushToday,
            pendingCount: pushPending,
            pendingLabel: 'ausstehend',
            busy: _pushing,
            busyLabel: 'Wird übertragen…',
            buttonLabel: 'Jetzt nach Google pushen',
            spinCtrl: _spinCtrl,
            onTap: (_pulling || _pushing) ? null : _push,
          ),

          const SizedBox(height: 10),

          // Pull-Karte (Google → TheraPano)
          _SyncDirectionCard(
            icon: Icons.download_rounded,
            color: _googleBlue,
            title: 'Google → TheraPano',
            subtitle: 'Neue/geänderte Termine aus Google in TheraPano importieren',
            lastAt: _fmtDate(pullLastAt),
            todayCount: pullToday,
            pendingCount: null,
            pendingLabel: '',
            busy: _pulling,
            busyLabel: 'Wird importiert…',
            buttonLabel: 'Jetzt von Google pullen',
            spinCtrl: _spinCtrl,
            onTap: (_pulling || _pushing) ? null : _pull,
          ),

          // ── Letzter Fehler ──
          if (lastErr != null) ...[
            const SizedBox(height: 14),
            Container(
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: Colors.red.shade50,
                borderRadius: BorderRadius.circular(10),
                border: Border.all(color: Colors.red.shade200)),
              child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                const Icon(Icons.warning_amber_rounded,
                  color: Colors.red, size: 16),
                const SizedBox(width: 8),
                Expanded(child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start, children: [
                  const Text('Letzter Fehler',
                    style: TextStyle(fontWeight: FontWeight.w700,
                      fontSize: 12, color: Colors.red)),
                  Text(lastErr['message'] as String? ?? '',
                    style: const TextStyle(fontSize: 11, color: Colors.red),
                    maxLines: 3, overflow: TextOverflow.ellipsis),
                  Text(_fmtDate(lastErr['created_at'] as String?),
                    style: TextStyle(fontSize: 10,
                      color: Colors.red.shade400)),
                ])),
              ]),
            ),
          ],

          // ── Sync-Log ──
          if (logs.isNotEmpty) ...[
            const SizedBox(height: 18),
            Text('Letzte Sync-Aktivitäten',
              style: Theme.of(context).textTheme.labelLarge?.copyWith(
                fontWeight: FontWeight.w700,
                color: cs.onSurfaceVariant)),
            const SizedBox(height: 8),
            ...logs.map((l) {
              final log    = l as Map;
              final ok     = (log['success'] == true || log['success'] == 1);
              final action = log['action'] as String? ?? '';
              final msg    = log['message'] as String? ?? '';
              final dt     = _fmtDate(log['created_at'] as String?);
              final isPull = action == 'pull';
              final dirColor = isPull ? _googleBlue : _googleGreen;
              return Padding(
                padding: const EdgeInsets.only(bottom: 6),
                child: Row(crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                  Icon(ok ? Icons.check_circle_rounded
                           : Icons.error_outline_rounded,
                    size: 14,
                    color: ok ? Colors.green : Colors.red),
                  const SizedBox(width: 8),
                  Expanded(child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Row(children: [
                      Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 6, vertical: 1),
                        decoration: BoxDecoration(
                          color: dirColor.withValues(alpha: 0.12),
                          borderRadius: BorderRadius.circular(4)),
                        child: Row(mainAxisSize: MainAxisSize.min, children: [
                          Icon(isPull
                            ? Icons.download_rounded
                            : Icons.upload_rounded,
                            size: 10, color: dirColor),
                          const SizedBox(width: 3),
                          Text(action,
                            style: TextStyle(fontSize: 10,
                              fontWeight: FontWeight.w700,
                              color: dirColor)),
                        ]),
                      ),
                      const SizedBox(width: 6),
                      Text(dt,
                        style: TextStyle(fontSize: 10,
                          color: cs.onSurfaceVariant)),
                    ]),
                    if (msg.isNotEmpty)
                      Text(msg,
                        style: const TextStyle(fontSize: 11),
                        maxLines: 2, overflow: TextOverflow.ellipsis),
                  ])),
                ]),
              );
            }),
          ],
        ],
      ]),
    );
  }
}

// ── Connection status card ─────────────────────────────────────────────────────

class _StatusCard extends StatelessWidget {
  final bool connected, enabled, autoSync;
  final String email, calName;
  const _StatusCard({
    required this.connected, required this.enabled,
    required this.email, required this.autoSync, required this.calName,
  });

  @override
  Widget build(BuildContext context) {
    final active = connected && enabled;
    final color  = active ? Colors.green : connected ? Colors.orange : Colors.red;
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: color.shade50,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: color.shade200)),
      child: Column(children: [
        Row(children: [
          Icon(
            active ? Icons.check_circle_rounded
              : connected ? Icons.pause_circle_rounded
              : Icons.link_off_rounded,
            color: color, size: 22),
          const SizedBox(width: 10),
          Expanded(child: Column(
            crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(
              active ? 'Sync aktiv'
                : connected ? 'Verbunden – Sync deaktiviert'
                : 'Nicht verbunden',
              style: TextStyle(fontWeight: FontWeight.w700,
                fontSize: 14, color: color.shade700)),
            if (email.isNotEmpty)
              Text(email,
                style: TextStyle(fontSize: 12,
                  color: Colors.grey.shade600)),
          ])),
        ]),
        if (connected) ...[
          const SizedBox(height: 8),
          const Divider(height: 1),
          const SizedBox(height: 8),
          Row(children: [
            const Icon(Icons.calendar_today_rounded, size: 13,
              color: Colors.grey),
            const SizedBox(width: 6),
            Expanded(child: Text(calName,
              style: const TextStyle(fontSize: 12))),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2),
              decoration: BoxDecoration(
                color: autoSync
                  ? Colors.green.withValues(alpha: 0.12)
                  : Colors.grey.withValues(alpha: 0.12),
                borderRadius: BorderRadius.circular(8)),
              child: Text(autoSync ? 'Auto-Sync AN' : 'Auto-Sync AUS',
                style: TextStyle(
                  fontSize: 10, fontWeight: FontWeight.w700,
                  color: autoSync ? Colors.green.shade700
                    : Colors.grey.shade600))),
          ]),
        ],
      ]),
    );
  }
}

// ── Direction card (Push or Pull) ─────────────────────────────────────────────

class _SyncDirectionCard extends StatelessWidget {
  final IconData icon;
  final Color color;
  final String title, subtitle, lastAt, busyLabel, buttonLabel;
  final int todayCount;
  final int? pendingCount;
  final String pendingLabel;
  final bool busy;
  final AnimationController spinCtrl;
  final VoidCallback? onTap;

  const _SyncDirectionCard({
    required this.icon, required this.color,
    required this.title, required this.subtitle,
    required this.lastAt, required this.todayCount,
    required this.pendingCount, required this.pendingLabel,
    required this.busy, required this.busyLabel, required this.buttonLabel,
    required this.spinCtrl, required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.06),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: color.withValues(alpha: 0.25))),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          Container(
            width: 30, height: 30,
            decoration: BoxDecoration(
              color: color.withValues(alpha: 0.15),
              shape: BoxShape.circle),
            child: Icon(icon, color: color, size: 16)),
          const SizedBox(width: 10),
          Expanded(child: Column(
            crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(title,
              style: TextStyle(fontWeight: FontWeight.w700,
                fontSize: 13, color: color)),
            Text(subtitle,
              style: TextStyle(fontSize: 11,
                color: Colors.grey.shade600)),
          ])),
        ]),
        const SizedBox(height: 10),
        Row(children: [
          _MiniStat(
            label: 'Letzter Sync', value: lastAt, color: color),
          const SizedBox(width: 12),
          _MiniStat(
            label: 'Heute', value: '$todayCount×', color: color),
          if (pendingCount != null) ...[
            const SizedBox(width: 12),
            _MiniStat(
              label: pendingLabel,
              value: '$pendingCount ausstehend',
              color: pendingCount! > 0 ? Colors.orange : color),
          ],
        ]),
        const SizedBox(height: 10),
        SizedBox(
          width: double.infinity,
          child: FilledButton.icon(
            icon: busy
                ? RotationTransition(
                    turns: spinCtrl,
                    child: Icon(icon, size: 16))
                : Icon(icon, size: 16),
            label: Text(busy ? busyLabel : buttonLabel),
            onPressed: onTap,
            style: FilledButton.styleFrom(
              backgroundColor: color,
              foregroundColor: Colors.white,
              padding: const EdgeInsets.symmetric(vertical: 10)),
          ),
        ),
      ]),
    );
  }
}

class _MiniStat extends StatelessWidget {
  final String label, value;
  final Color color;
  const _MiniStat({required this.label, required this.value,
    required this.color});

  @override
  Widget build(BuildContext context) {
    return Expanded(child: Column(
      crossAxisAlignment: CrossAxisAlignment.start, children: [
      Text(label,
        style: TextStyle(fontSize: 9, color: Colors.grey.shade500,
          fontWeight: FontWeight.w600)),
      Text(value,
        style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700,
          color: color),
        overflow: TextOverflow.ellipsis),
    ]));
  }
}
