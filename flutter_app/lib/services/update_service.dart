import 'dart:convert';
import 'dart:io';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:http/http.dart' as http;
import 'package:package_info_plus/package_info_plus.dart';
import 'package:path_provider/path_provider.dart';

/// Checks GitHub Releases for a newer version and offers an in-app install.
/// Repository: configured via [repoOwner] / [repoName].
class UpdateService {
  static const repoOwner = '0wum0';
  static const repoName  = 'Tierphysio-Manager-3.0-Remastered';

  static const _channel = MethodChannel('de.tierphysio.manager/install');

  // ── Public API ─────────────────────────────────────────────────────────────

  /// Silently checks for updates. If one is found shows the install dialog.
  /// Call this from your shell/dashboard initState.
  static Future<void> checkForUpdate(BuildContext context) async {
    try {
      final info   = await PackageInfo.fromPlatform();
      final latest = await _fetchLatestRelease();
      if (latest == null) return;

      final latestTag    = latest['tag_name'] as String? ?? '';
      final releaseNotes = latest['body']     as String? ?? '';
      final assets       = latest['assets']   as List<dynamic>? ?? [];

      // Find the APK asset
      final apkAsset = assets.firstWhere(
        (a) => (a['name'] as String? ?? '').endsWith('.apk'),
        orElse: () => null,
      );
      if (apkAsset == null) return;

      final downloadUrl = apkAsset['browser_download_url'] as String? ?? '';
      if (downloadUrl.isEmpty) return;

      // Compare semantic versions (e.g. v1.0.3 vs 1.0.3)
      final currentVersion = info.version; // e.g. "1.0.3"
      final latestVersion  = latestTag.replaceAll(RegExp(r'^[vV]'), '').split('+').first;
      if (!_isNewerVersion(latestVersion, currentVersion)) return;

      if (!context.mounted) return;
      _showUpdateDialog(context, latestTag, releaseNotes, downloadUrl, info.version);
    } catch (_) {
      // Silent fail — never crash the app for an update check
    }
  }

  // ── GitHub API ─────────────────────────────────────────────────────────────

  static Future<Map<String, dynamic>?> _fetchLatestRelease() async {
    final uri = Uri.parse(
      'https://api.github.com/repos/$repoOwner/$repoName/releases/latest',
    );
    final res = await http.get(uri, headers: {'Accept': 'application/vnd.github+json'})
        .timeout(const Duration(seconds: 10));
    if (res.statusCode != 200) return null;
    return jsonDecode(res.body) as Map<String, dynamic>;
  }

  /// Returns true if [latest] is strictly newer than [current].
  /// Both strings must be in "major.minor.patch" format.
  static bool _isNewerVersion(String latest, String current) {
    List<int> parse(String v) =>
        v.split('.').map((p) => int.tryParse(p.replaceAll(RegExp(r'[^0-9]'), '')) ?? 0).toList();

    final l = parse(latest);
    final c = parse(current);
    final len = l.length > c.length ? l.length : c.length;
    for (int i = 0; i < len; i++) {
      final lv = i < l.length ? l[i] : 0;
      final cv = i < c.length ? c[i] : 0;
      if (lv > cv) return true;
      if (lv < cv) return false;
    }
    return false; // equal
  }

  // ── UI Dialog ──────────────────────────────────────────────────────────────

  static void _showUpdateDialog(
    BuildContext context,
    String version,
    String notes,
    String downloadUrl,
    String currentVersion,
  ) {
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (_) => _UpdateDialog(
        newVersion: version,
        releaseNotes: notes,
        downloadUrl: downloadUrl,
        currentVersion: currentVersion,
      ),
    );
  }
}

// ── Update Dialog Widget ──────────────────────────────────────────────────────

class _UpdateDialog extends StatefulWidget {
  final String newVersion;
  final String releaseNotes;
  final String downloadUrl;
  final String currentVersion;

  const _UpdateDialog({
    required this.newVersion,
    required this.releaseNotes,
    required this.downloadUrl,
    required this.currentVersion,
  });

  @override
  State<_UpdateDialog> createState() => _UpdateDialogState();
}

class _UpdateDialogState extends State<_UpdateDialog> {
  _Phase _phase = _Phase.idle;
  double _progress = 0;
  String? _errorMsg;

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return PopScope(
      canPop: _phase == _Phase.idle || _phase == _Phase.done || _phase == _Phase.error,
      child: AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        title: Row(children: [
          Container(
            width: 40, height: 40,
            decoration: BoxDecoration(
              color: cs.primary.withValues(alpha: 0.12),
              borderRadius: BorderRadius.circular(10),
            ),
            child: Icon(Icons.system_update_rounded, color: cs.primary, size: 22),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              const Text('Update verfügbar', style: TextStyle(fontSize: 17, fontWeight: FontWeight.w700)),
              Text('${widget.currentVersion} → ${widget.newVersion}',
                style: TextStyle(fontSize: 12, color: cs.onSurfaceVariant, fontWeight: FontWeight.w400)),
            ]),
          ),
        ]),
        content: SizedBox(
          width: double.maxFinite,
          child: _buildContent(cs),
        ),
        actions: _buildActions(cs),
      ),
    );
  }

  Widget _buildContent(ColorScheme cs) {
    switch (_phase) {
      case _Phase.idle:
        return Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
          if (widget.releaseNotes.isNotEmpty) ...[
            Text('Was ist neu:', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: cs.onSurface)),
            const SizedBox(height: 6),
            Container(
              constraints: const BoxConstraints(maxHeight: 120),
              child: SingleChildScrollView(
                child: Text(
                  widget.releaseNotes,
                  style: TextStyle(fontSize: 13, color: cs.onSurfaceVariant, height: 1.5),
                ),
              ),
            ),
            const SizedBox(height: 12),
          ],
          Text(
            'Das Update wird automatisch heruntergeladen und installiert.',
            style: TextStyle(fontSize: 13, color: cs.onSurfaceVariant),
          ),
        ]);

      case _Phase.downloading:
        return Column(mainAxisSize: MainAxisSize.min, children: [
          const SizedBox(height: 8),
          LinearProgressIndicator(value: _progress > 0 ? _progress : null, borderRadius: BorderRadius.circular(8)),
          const SizedBox(height: 12),
          Text(
            _progress > 0 ? 'Herunterladen… ${(_progress * 100).toInt()}%' : 'Verbindung wird hergestellt…',
            style: TextStyle(fontSize: 13, color: cs.onSurfaceVariant),
          ),
        ]);

      case _Phase.installing:
        return Column(mainAxisSize: MainAxisSize.min, children: [
          const SizedBox(height: 8),
          const CircularProgressIndicator(),
          const SizedBox(height: 12),
          Text('Installation wird gestartet…',
            style: TextStyle(fontSize: 13, color: cs.onSurfaceVariant)),
        ]);

      case _Phase.done:
        return Column(mainAxisSize: MainAxisSize.min, children: [
          Icon(Icons.check_circle_rounded, color: Colors.green.shade600, size: 48),
          const SizedBox(height: 8),
          const Text('Installation gestartet.\nDie App startet nach der Installation neu.',
            textAlign: TextAlign.center),
        ]);

      case _Phase.error:
        return Column(mainAxisSize: MainAxisSize.min, children: [
          Icon(Icons.error_outline_rounded, color: Colors.red.shade600, size: 40),
          const SizedBox(height: 8),
          Text(_errorMsg ?? 'Unbekannter Fehler.',
            textAlign: TextAlign.center,
            style: TextStyle(fontSize: 13, color: cs.onSurfaceVariant)),
        ]);
    }
  }

  List<Widget> _buildActions(ColorScheme cs) {
    switch (_phase) {
      case _Phase.idle:
        return [
          TextButton(
            onPressed: () => Navigator.of(context).pop(),
            child: Text('Später', style: TextStyle(color: cs.onSurfaceVariant)),
          ),
          FilledButton.icon(
            onPressed: _startDownload,
            icon: const Icon(Icons.download_rounded, size: 18),
            label: const Text('Update installieren'),
            style: FilledButton.styleFrom(minimumSize: const Size(0, 44)),
          ),
        ];
      case _Phase.downloading:
      case _Phase.installing:
        return [
          TextButton(
            onPressed: null,
            child: Text('Abbrechen', style: TextStyle(color: cs.onSurfaceVariant.withValues(alpha: 0.4))),
          ),
        ];
      case _Phase.done:
      case _Phase.error:
        return [
          TextButton(
            onPressed: () => Navigator.of(context).pop(),
            child: const Text('Schließen'),
          ),
        ];
    }
  }

  Future<void> _startDownload() async {
    setState(() { _phase = _Phase.downloading; _progress = 0; });

    try {
      // Download APK to cache dir
      final cacheDir = await getTemporaryDirectory();
      final updateDir = Directory('${cacheDir.path}/updates');
      if (!updateDir.existsSync()) updateDir.createSync(recursive: true);
      final apkFile = File('${updateDir.path}/terapano_update.apk');
      if (apkFile.existsSync()) apkFile.deleteSync();

      final request  = http.Request('GET', Uri.parse(widget.downloadUrl));
      final response = await request.send().timeout(const Duration(minutes: 10));
      final total    = response.contentLength ?? 0;
      int received   = 0;

      final sink = apkFile.openWrite();
      await response.stream.forEach((chunk) {
        sink.add(chunk);
        received += chunk.length;
        if (total > 0 && mounted) {
          setState(() => _progress = received / total);
        }
      });
      await sink.flush();
      await sink.close();

      if (!mounted) return;
      setState(() => _phase = _Phase.installing);

      // Trigger Android install intent via platform channel
      await _installApk(apkFile.path);

      if (!mounted) return;
      setState(() => _phase = _Phase.done);
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _phase    = _Phase.error;
        _errorMsg = 'Fehler: $e';
      });
    }
  }

  static Future<void> _installApk(String apkPath) async {
    await UpdateService._channel.invokeMethod('installApk', {'path': apkPath});
  }
}

enum _Phase { idle, downloading, installing, done, error }
