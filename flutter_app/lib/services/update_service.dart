import 'dart:convert';
import 'dart:io';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:package_info_plus/package_info_plus.dart';
import 'package:path_provider/path_provider.dart';
import 'package:url_launcher/url_launcher.dart';

class UpdateInfo {
  final String version;
  final String downloadUrl;
  final String notes;
  final String fileName;
  final bool isAndroid;

  UpdateInfo({
    required this.version,
    required this.downloadUrl,
    required this.notes,
    required this.fileName,
    required this.isAndroid,
  });
}

/// Service to handle automatic update checks via GitHub Releases.
/// Supports Android (.apk) and Windows (.exe).
class UpdateService {
  static const String _owner = '0wum0';
  static const String _repo  = 'Tierphysio-Manager-3.0-Remastered';

  static final ValueNotifier<UpdateInfo?> updateNotifier = ValueNotifier(null);
  static final ValueNotifier<double> downloadProgress = ValueNotifier(0.0);
  static final ValueNotifier<bool> isDownloading = ValueNotifier(false);

  static bool get _isSupported => Platform.isAndroid || Platform.isWindows;

  /// Prüft ob ein Update verfügbar ist. Wird im Dashboard nach 3 Sek. aufgerufen.
  static Future<void> checkForUpdate(BuildContext context) async {
    if (!_isSupported) return;

    try {
      final PackageInfo info = await PackageInfo.fromPlatform();
      final String currentVersion = info.version;

      final response = await http.get(
        Uri.parse('https://api.github.com/repos/$_owner/$_repo/releases/latest'),
        headers: {'Accept': 'application/vnd.github+json'},
      ).timeout(const Duration(seconds: 10));

      if (response.statusCode != 200) return;

      final data = jsonDecode(response.body) as Map<String, dynamic>;
      final String latestTag = data['tag_name'] as String? ?? '';
      final String notes = data['body'] as String? ?? '';
      final List assets = data['assets'] as List? ?? [];

      // v-Präfix entfernen
      final String latestVersion = latestTag.replaceAll(RegExp(r'^[vV]'), '');

      if (!_isNewer(latestVersion, currentVersion)) return;

      // Plattform-spezifisches Asset suchen
      final String ext = Platform.isAndroid ? '.apk' : '.exe';
      final asset = assets.firstWhere(
        (a) => (a['name'] as String).toLowerCase().endsWith(ext),
        orElse: () => null,
      );

      if (asset != null) {
        updateNotifier.value = UpdateInfo(
          version: latestVersion,
          downloadUrl: asset['browser_download_url'] as String,
          notes: notes,
          fileName: asset['name'] as String,
          isAndroid: Platform.isAndroid,
        );
      }
    } catch (e) {
      debugPrint('Update-Check fehlgeschlagen: $e');
    }
  }

  /// Lädt das Update herunter und startet die Installation.
  /// Android: öffnet den Browser zum Herunterladen der APK.
  /// Windows: In-App-Download + Installer starten.
  static Future<void> downloadAndInstall() async {
    final info = updateNotifier.value;
    if (info == null) return;

    if (Platform.isAndroid) {
      // Android: Direktlink im Browser öffnen → System-Installer übernimmt
      final uri = Uri.parse(info.downloadUrl);
      if (await canLaunchUrl(uri)) {
        await launchUrl(uri, mode: LaunchMode.externalApplication);
      }
      return;
    }

    // Windows: In-App-Download + Installer starten
    isDownloading.value = true;
    downloadProgress.value = 0.0;

    try {
      final tempDir = await getTemporaryDirectory();
      final installFile = File('${tempDir.path}/${info.fileName}');

      if (await installFile.exists()) await installFile.delete();

      final request = http.Request('GET', Uri.parse(info.downloadUrl));
      final response = await request.send();
      final totalLength = response.contentLength ?? 0;
      int receivedLength = 0;

      final sink = installFile.openWrite();
      await response.stream.forEach((chunk) {
        sink.add(chunk);
        receivedLength += chunk.length;
        if (totalLength > 0) {
          downloadProgress.value = receivedLength / totalLength;
        }
      });

      await sink.flush();
      await sink.close();

      await Process.start(installFile.path, const []);

      Future.delayed(const Duration(seconds: 1), () => exit(0));
    } catch (e) {
      debugPrint('Download fehlgeschlagen: $e');
      isDownloading.value = false;
    }
  }

  static bool _isNewer(String latest, String current) {
    final latestParts = latest.split('.').map((e) => int.tryParse(e) ?? 0).toList();
    final currentParts = current.split('.').map((e) => int.tryParse(e) ?? 0).toList();

    for (int i = 0; i < latestParts.length; i++) {
      if (i >= currentParts.length) return true;
      if (latestParts[i] > currentParts[i]) return true;
      if (latestParts[i] < currentParts[i]) return false;
    }
    return false;
  }
}
