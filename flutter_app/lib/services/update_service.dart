import 'dart:convert';
import 'dart:io';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:open_file/open_file.dart';
import 'package:package_info_plus/package_info_plus.dart';
import 'package:path_provider/path_provider.dart';

class UpdateInfo {
  final String version;
  final String downloadUrl;
  final String notes;
  final String fileName;

  const UpdateInfo({
    required this.version,
    required this.downloadUrl,
    required this.notes,
    required this.fileName,
  });
}

/// Service to handle automatic update checks via GitHub Releases.
class UpdateService {
  static const String _owner = '0wum0';
  static const String _repo  = 'Tierphysio-Manager-3.0-Remastered';

  static final ValueNotifier<UpdateInfo?> updateNotifier = ValueNotifier(null);
  static final ValueNotifier<double>      downloadProgress = ValueNotifier(0.0);
  static final ValueNotifier<bool>        isDownloading = ValueNotifier(false);

  static Future<void> checkForUpdate() async {
    if (!Platform.isAndroid && !Platform.isWindows) return;

    try {
      final PackageInfo info = await PackageInfo.fromPlatform();
      final String currentVersion = info.version;

      final response = await http.get(
        Uri.parse('https://api.github.com/repos/$_owner/$_repo/releases/latest'),
        headers: {'Accept': 'application/vnd.github+json'},
      ).timeout(const Duration(seconds: 15));

      if (response.statusCode != 200) return;

      final data = jsonDecode(response.body) as Map<String, dynamic>;
      final String latestTag = data['tag_name'] as String? ?? '';
      final String notes     = data['body']     as String? ?? '';
      final List   assets    = data['assets']   as List?   ?? [];

      final String latestVersion = latestTag.replaceAll(RegExp(r'^[vV]'), '');
      if (!_isNewer(latestVersion, currentVersion)) return;

      final String ext = Platform.isAndroid ? '.apk' : '.exe';
      final asset = assets.firstWhere(
        (a) => (a['name'] as String).toLowerCase().endsWith(ext),
        orElse: () => null,
      );
      if (asset == null) return;

      updateNotifier.value = UpdateInfo(
        version:     latestVersion,
        downloadUrl: asset['browser_download_url'] as String,
        notes:       notes,
        fileName:    asset['name'] as String,
      );
    } catch (e) {
      debugPrint('[UpdateService] check failed: $e');
    }
  }

  static Future<void> downloadAndInstall() async {
    final info = updateNotifier.value;
    if (info == null || isDownloading.value) return;

    isDownloading.value   = true;
    downloadProgress.value = 0.0;

    try {
      Directory dir;
      if (Platform.isAndroid) {
        final cache = await getTemporaryDirectory();
        dir = Directory('${cache.path}/updates');
        if (!await dir.exists()) await dir.create(recursive: true);
      } else {
        dir = await getTemporaryDirectory();
      }

      final installFile = File('${dir.path}/${info.fileName}');
      if (await installFile.exists()) await installFile.delete();

      final request  = http.Request('GET', Uri.parse(info.downloadUrl));
      final response = await request.send();
      final total    = response.contentLength ?? 0;
      int received   = 0;

      final sink = installFile.openWrite();
      await response.stream.forEach((chunk) {
        sink.add(chunk);
        received += chunk.length;
        if (total > 0) downloadProgress.value = received / total;
      });
      await sink.flush();
      await sink.close();

      downloadProgress.value = 1.0;
      await Future.delayed(const Duration(milliseconds: 200));

      if (Platform.isAndroid) {
        await OpenFile.open(installFile.path);
      } else {
        await Process.start(installFile.path, const []);
        Future.delayed(const Duration(seconds: 1), () => exit(0));
      }
    } catch (e) {
      debugPrint('[UpdateService] download failed: $e');
    } finally {
      isDownloading.value = false;
    }
  }

  static bool _isNewer(String latest, String current) {
    final l = latest.split('.').map((e) => int.tryParse(e) ?? 0).toList();
    final c = current.split('.').map((e) => int.tryParse(e) ?? 0).toList();
    for (int i = 0; i < l.length; i++) {
      if (i >= c.length) return true;
      if (l[i] > c[i]) return true;
      if (l[i] < c[i]) return false;
    }
    return false;
  }
}
