import 'dart:convert';
import 'dart:io';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:package_info_plus/package_info_plus.dart';
import 'package:url_launcher/url_launcher.dart';

/// Prüft GitHub Releases auf eine neuere Version und öffnet die Download-Seite.
class UpdateService {
  static const repoOwner = '0wum0';
  static const repoName  = 'Tierphysio-Manager-3.0-Remastered';

  // ── Public API ─────────────────────────────────────────────────────────────

  static Future<void> checkForUpdate(BuildContext context) async {
    try {
      final info   = await PackageInfo.fromPlatform();
      final latest = await _fetchLatestRelease();
      if (latest == null) return;

      final latestTag    = latest['tag_name'] as String? ?? '';
      final releaseNotes = latest['body']     as String? ?? '';
      final htmlUrl      = latest['html_url'] as String? ?? '';

      final currentVersion = info.version;
      final latestVersion  =
          latestTag.replaceAll(RegExp(r'^[vV]'), '').split('+').first;
      if (!_isNewerVersion(latestVersion, currentVersion)) return;

      if (!context.mounted) return;
      _showUpdateDialog(
          context, latestTag, releaseNotes, htmlUrl, currentVersion);
    } catch (_) {
      // Silent fail
    }
  }

  // ── GitHub API ─────────────────────────────────────────────────────────────

  static Future<Map<String, dynamic>?> _fetchLatestRelease() async {
    final uri = Uri.parse(
      'https://api.github.com/repos/$repoOwner/$repoName/releases/latest',
    );
    final res = await http
        .get(uri, headers: {'Accept': 'application/vnd.github+json'})
        .timeout(const Duration(seconds: 10));
    if (res.statusCode != 200) return null;
    return jsonDecode(res.body) as Map<String, dynamic>;
  }

  static bool _isNewerVersion(String latest, String current) {
    List<int> parse(String v) => v
        .split('.')
        .map((p) => int.tryParse(p.replaceAll(RegExp(r'[^0-9]'), '')) ?? 0)
        .toList();
    final l = parse(latest);
    final c = parse(current);
    final len = l.length > c.length ? l.length : c.length;
    for (int i = 0; i < len; i++) {
      final lv = i < l.length ? l[i] : 0;
      final cv = i < c.length ? c[i] : 0;
      if (lv > cv) return true;
      if (lv < cv) return false;
    }
    return false;
  }

  // ── Dialog ─────────────────────────────────────────────────────────────────

  static void _showUpdateDialog(
    BuildContext context,
    String version,
    String notes,
    String releaseUrl,
    String currentVersion,
  ) {
    showDialog(
      context: context,
      builder: (_) => _UpdateDialog(
        newVersion:     version,
        releaseNotes:   notes,
        releaseUrl:     releaseUrl,
        currentVersion: currentVersion,
      ),
    );
  }
}

// ── Update Dialog ─────────────────────────────────────────────────────────────

class _UpdateDialog extends StatelessWidget {
  final String newVersion;
  final String releaseNotes;
  final String releaseUrl;
  final String currentVersion;

  const _UpdateDialog({
    required this.newVersion,
    required this.releaseNotes,
    required this.releaseUrl,
    required this.currentVersion,
  });

  String get _platformLabel {
    if (Platform.isLinux)   return 'Linux';
    if (Platform.isWindows) return 'Windows';
    if (Platform.isMacOS)   return 'macOS';
    return 'Desktop';
  }

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return AlertDialog(
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
            const Text('Update verfügbar',
                style: TextStyle(fontSize: 17, fontWeight: FontWeight.w700)),
            Text('$currentVersion → $newVersion',
                style: TextStyle(
                    fontSize: 12,
                    color: cs.onSurfaceVariant,
                    fontWeight: FontWeight.w400)),
          ]),
        ),
      ]),
      content: SizedBox(
        width: 420,
        child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              if (releaseNotes.isNotEmpty) ...[
                Text('Was ist neu:',
                    style: TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w600,
                        color: cs.onSurface)),
                const SizedBox(height: 6),
                Container(
                  constraints: const BoxConstraints(maxHeight: 140),
                  child: SingleChildScrollView(
                    child: Text(
                      releaseNotes,
                      style: TextStyle(
                          fontSize: 13,
                          color: cs.onSurfaceVariant,
                          height: 1.5),
                    ),
                  ),
                ),
                const SizedBox(height: 12),
              ],
              Text(
                'Öffne die Releases-Seite und lade die neue $_platformLabel-Version herunter.',
                style: TextStyle(fontSize: 13, color: cs.onSurfaceVariant),
              ),
            ]),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.of(context).pop(),
          child: Text('Später',
              style: TextStyle(color: cs.onSurfaceVariant)),
        ),
        FilledButton.icon(
          onPressed: () async {
            Navigator.of(context).pop();
            final uri = Uri.parse(releaseUrl);
            if (await canLaunchUrl(uri)) await launchUrl(uri);
          },
          icon: const Icon(Icons.open_in_new_rounded, size: 16),
          label: const Text('Releases öffnen'),
          style: FilledButton.styleFrom(minimumSize: const Size(0, 44)),
        ),
      ],
    );
  }
}
