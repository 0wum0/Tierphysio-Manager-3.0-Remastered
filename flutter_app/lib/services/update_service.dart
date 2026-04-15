import 'dart:convert';
import 'dart:io';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:package_info_plus/package_info_plus.dart';
import 'package:path_provider/path_provider.dart';
import 'package:open_file_plus/open_file_plus.dart';

class UpdateInfo {
  final String version;
  final String downloadUrl;
  final String notes;
  final String fileName;

  UpdateInfo({
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
  
  // Notifier to let the UI know when an update is found
  static final ValueNotifier<UpdateInfo?> updateNotifier = ValueNotifier(null);
  static final ValueNotifier<double> downloadProgress = ValueNotifier(0.0);
  static final ValueNotifier<bool> isDownloading = ValueNotifier(false);

  /// Entry point for checking updates. 
  /// Usually called in initState of Dashboard or Shell.
  static Future<void> checkForUpdate(BuildContext context) async {
    if (!Platform.isWindows) return;

    try {
      final PackageInfo info = await PackageInfo.fromPlatform();
      final String currentVersion = info.version;
      
      final response = await http.get(
        Uri.parse('https://api.github.com/repos/$_owner/$_repo/releases/latest'),
        headers: {'Accept': 'application/vnd.github+json'},
      );

      if (response.statusCode != 200) return;

      final data = jsonDecode(response.body) as Map<String, dynamic>;
      final String latestTag = data['tag_name'] as String? ?? '';
      final String notes = data['body'] as String? ?? '';
      final List assets = data['assets'] as List? ?? [];

      // Remove 'v' prefix if present
      final String latestVersion = latestTag.replaceAll(RegExp(r'^[vV]'), '');

      if (_isNewer(latestVersion, currentVersion)) {
        // Look for .exe asset
        final exeAsset = assets.firstWhere(
          (a) => (a['name'] as String).toLowerCase().endsWith('.exe'),
          orElse: () => null,
        );

        if (exeAsset != null) {
          updateNotifier.value = UpdateInfo(
            version: latestVersion,
            downloadUrl: exeAsset['browser_download_url'] as String,
            notes: notes,
            fileName: exeAsset['name'] as String,
          );
        }
      }
    } catch (e) {
      debugPrint('Update check failed: $e');
    }
  }

  /// Downloads the update and starts the installer.
  static Future<void> downloadAndInstall() async {
    final info = updateNotifier.value;
    if (info == null) return;

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

      // Launch the installer
      // Silent install? Typically Inno Setup uses /VERYSILENT /SUPPRESSMSGBOXES
      await OpenFile.open(installFile.path);

      // Close the app to allow the installer to overwrite files
      Future.delayed(const Duration(seconds: 1), () {
        exit(0);
      });
    } catch (e) {
      debugPrint('Download failed: $e');
      isDownloading.value = false;
    }
  }

  static bool _isNewer(String latest, String current) {
    List<int> latestParts = latest.split('.').map((e) => int.tryParse(e) ?? 0).toList();
    List<int> currentParts = current.split('.').map((e) => int.tryParse(e) ?? 0).toList();

    for (int i = 0; i < latestParts.length; i++) {
      if (i >= currentParts.length) return true;
      if (latestParts[i] > currentParts[i]) return true;
      if (latestParts[i] < currentParts[i]) return false;
    }
    return false;
  }
}
