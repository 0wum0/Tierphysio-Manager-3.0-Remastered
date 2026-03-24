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
  bool _loading = true;
  bool _pulling = false;
  String? _error;

  late AnimationController _spinCtrl;

  @override
  void initState() {
    super.initState();
    _spinCtrl = AnimationController(
      vsync: this,
      duration: const Duration(seconds: 2),
    );
    _loadStatus();
  }

  @override
  void dispose() {
    _spinCtrl.dispose();
    super.dispose();
  }

  Future<void> _loadStatus() async {
    setState(() { _loading = true; _error = null; });
    try {
      final s = await _api.googleSyncStatus();
      if (mounted) setState(() { _status = s; _loading = false; });
    } catch (e) {
      if (mounted) setState(() { _error = e.toString(); _loading = false; });
    }
  }

  Future<void> _pull() async {
    setState(() => _pulling = true);
    _spinCtrl.repeat();
    try {
      final result = await _api.googleSyncPull();
      if (mounted) {
        _spinCtrl.stop();
        _spinCtrl.reset();
        setState(() => _pulling = false);
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(
          content: Row(children: [
            Icon(
              (result['success'] as bool? ?? false)
                  ? Icons.check_circle_rounded
                  : Icons.error_outline_rounded,
              color: Colors.white, size: 16,
            ),
            const SizedBox(width: 8),
            Expanded(child: Text(result['message'] as String? ?? 'Sync abgeschlossen')),
          ]),
          backgroundColor: (result['success'] as bool? ?? false)
              ? AppTheme.success : AppTheme.danger,
          behavior: SnackBarBehavior.floating,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
          margin: const EdgeInsets.all(12),
        ));
        widget.onPullDone?.call();
        _loadStatus();
      }
    } catch (e) {
      if (mounted) {
        _spinCtrl.stop();
        _spinCtrl.reset();
        setState(() => _pulling = false);
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

  String _fmtDate(String? s) {
    if (s == null) return '–';
    try {
      return DateFormat('dd.MM.yyyy HH:mm', 'de_DE').format(DateTime.parse(s));
    } catch (_) { return s; }
  }

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    const googleBlue = Color(0xFF4285F4);

    return SafeArea(
      top: false,
      child: ConstrainedBox(
        constraints: BoxConstraints(
          maxHeight: MediaQuery.of(context).size.height * 0.80,
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            // Handle
            Container(
              width: 40, height: 4,
              margin: const EdgeInsets.only(top: 12, bottom: 8),
              decoration: BoxDecoration(
                color: Colors.grey.shade300,
                borderRadius: BorderRadius.circular(2),
              ),
            ),
            // Header
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 4, 16, 12),
              child: Row(children: [
                Container(
                  width: 36, height: 36,
                  decoration: BoxDecoration(
                    color: googleBlue.withValues(alpha: 0.12),
                    shape: BoxShape.circle,
                  ),
                  child: const Icon(Icons.sync_rounded, color: googleBlue, size: 20),
                ),
                const SizedBox(width: 12),
                Expanded(child: Text('Google Kalender Sync',
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w700))),
                IconButton(
                  icon: const Icon(Icons.refresh_rounded),
                  onPressed: _loading ? null : _loadStatus,
                  tooltip: 'Neu laden',
                ),
              ]),
            ),
            const Divider(height: 1),
            // Body
            Flexible(
              child: _loading
                  ? const Center(child: Padding(
                      padding: EdgeInsets.all(40),
                      child: CircularProgressIndicator(),
                    ))
                  : _error != null
                      ? _buildError()
                      : _buildContent(cs, googleBlue),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildError() {
    return Padding(
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
  }

  Widget _buildContent(ColorScheme cs, Color googleBlue) {
    final s = _status!;
    final connected  = s['connected'] as bool? ?? false;
    final enabled    = s['sync_enabled'] as bool? ?? false;
    final autoSync   = s['auto_sync'] as bool? ?? false;
    final email      = s['google_email'] as String? ?? '';
    final calName    = s['calendar_name'] as String? ?? 'Primär';
    final lastPull   = s['last_pull_at'] as String?;
    final lastOk     = s['last_success_at'] as String?;
    final lastErr    = s['last_error'] as Map?;
    final syncToday  = s['synced_today'] as int? ?? 0;
    final logs       = (s['recent_logs'] as List? ?? []);

    return SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(20, 12, 20, 24),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [

        // Status card
        Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: connected && enabled
                ? Colors.green.shade50
                : Colors.orange.shade50,
            borderRadius: BorderRadius.circular(14),
            border: Border.all(
              color: connected && enabled
                  ? Colors.green.shade200
                  : Colors.orange.shade200,
            ),
          ),
          child: Row(children: [
            Icon(
              connected && enabled
                  ? Icons.check_circle_rounded
                  : connected
                      ? Icons.pause_circle_rounded
                      : Icons.link_off_rounded,
              color: connected && enabled
                  ? Colors.green
                  : connected ? Colors.orange : Colors.red,
              size: 22,
            ),
            const SizedBox(width: 10),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(
                connected && enabled
                    ? 'Sync aktiv'
                    : connected
                        ? 'Verbunden, Sync deaktiviert'
                        : 'Nicht verbunden',
                style: TextStyle(
                  fontWeight: FontWeight.w700, fontSize: 14,
                  color: connected && enabled
                      ? Colors.green.shade700
                      : connected ? Colors.orange.shade700 : Colors.red.shade700,
                ),
              ),
              if (email.isNotEmpty)
                Text(email,
                  style: TextStyle(fontSize: 12, color: Colors.grey.shade600)),
            ])),
          ]),
        ),

        if (!connected)
          Padding(
            padding: const EdgeInsets.only(top: 12),
            child: Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: cs.surfaceContainerHighest,
                borderRadius: BorderRadius.circular(10),
              ),
              child: const Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Icon(Icons.info_outline_rounded, size: 16, color: Colors.blue),
                SizedBox(width: 8),
                Expanded(child: Text(
                  'Google Sync einrichten: Im Browser unter Einstellungen → Google Kalender Sync.',
                  style: TextStyle(fontSize: 12),
                )),
              ]),
            ),
          ),

        if (connected) ...[
          const SizedBox(height: 16),

          // Info rows
          _InfoRow(icon: Icons.calendar_today_rounded,
            label: 'Kalender', value: calName),
          _InfoRow(icon: Icons.sync_rounded,
            label: 'Auto-Sync', value: autoSync ? 'Aktiv' : 'Deaktiviert'),
          _InfoRow(icon: Icons.cloud_download_rounded,
            label: 'Letzter Pull', value: _fmtDate(lastPull)),
          _InfoRow(icon: Icons.check_rounded,
            label: 'Letzter Erfolg', value: _fmtDate(lastOk)),
          _InfoRow(icon: Icons.today_rounded,
            label: 'Heute synchronisiert', value: '$syncToday Einträge'),

          if (lastErr != null) ...[
            const SizedBox(height: 8),
            Container(
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: Colors.red.shade50,
                borderRadius: BorderRadius.circular(10),
                border: Border.all(color: Colors.red.shade200),
              ),
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
                    style: TextStyle(fontSize: 10, color: Colors.red.shade400)),
                ])),
              ]),
            ),
          ],

          const SizedBox(height: 20),

          // Pull button
          SizedBox(
            width: double.infinity,
            child: FilledButton.icon(
              icon: RotationTransition(
                turns: _spinCtrl,
                child: const Icon(Icons.sync_rounded, size: 18),
              ),
              label: Text(_pulling ? 'Synchronisiere…' : 'Jetzt von Google pullen'),
              onPressed: _pulling ? null : _pull,
              style: FilledButton.styleFrom(
                backgroundColor: googleBlue,
                foregroundColor: Colors.white,
              ),
            ),
          ),

          // Recent logs
          if (logs.isNotEmpty) ...[
            const SizedBox(height: 20),
            Text('Letzte Aktivitäten',
              style: Theme.of(context).textTheme.labelLarge?.copyWith(
                fontWeight: FontWeight.w700,
                color: cs.onSurfaceVariant,
              )),
            const SizedBox(height: 8),
            ...logs.map((l) {
              final log    = l as Map;
              final ok     = (log['success'] == true || log['success'] == 1);
              final action = log['action'] as String? ?? '';
              final msg    = log['message'] as String? ?? '';
              final dt     = _fmtDate(log['created_at'] as String?);
              return Padding(
                padding: const EdgeInsets.only(bottom: 6),
                child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Icon(
                    ok ? Icons.check_circle_rounded : Icons.error_outline_rounded,
                    size: 14,
                    color: ok ? Colors.green : Colors.red,
                  ),
                  const SizedBox(width: 8),
                  Expanded(child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Row(children: [
                      Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 6, vertical: 1),
                        decoration: BoxDecoration(
                          color: cs.surfaceContainerHighest,
                          borderRadius: BorderRadius.circular(4),
                        ),
                        child: Text(action,
                          style: const TextStyle(
                            fontSize: 10, fontWeight: FontWeight.w700)),
                      ),
                      const SizedBox(width: 6),
                      Text(dt,
                        style: TextStyle(
                          fontSize: 10, color: cs.onSurfaceVariant)),
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

class _InfoRow extends StatelessWidget {
  final IconData icon;
  final String label, value;
  const _InfoRow({required this.icon, required this.label, required this.value});

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Row(children: [
        Icon(icon, size: 15, color: cs.onSurfaceVariant),
        const SizedBox(width: 8),
        Expanded(child: Text(label,
          style: TextStyle(fontSize: 13, color: cs.onSurfaceVariant))),
        Text(value,
          style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600)),
      ]),
    );
  }
}
