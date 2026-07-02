import 'dart:collection';
import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_inappwebview/flutter_inappwebview.dart';

import '../../services/api_service.dart';

/// 3D-Schmerzanalyse – vollständig offline in die App eingebettet.
///
/// Der bewährte Web-Viewer (Three.js + GLB-Modelle) liegt als App-Asset unter
/// `assets/3d/`. Ein lokaler HTTP-Server ([InAppLocalhostServer]) liefert diese
/// Assets über einen echten `http://localhost`-Origin aus – nötig, damit
/// ES-Module und der GLB-Loader funktionieren. Die Schmerzpunkte werden über
/// die Mobile-API (`/api/mobile/patients/{id}/schmerzpunkte`) mit dem
/// Bearer-Token der App gespeichert; die Modelle selbst werden nie
/// nachgeladen, sondern sind fest gebündelt.
class Schmerzanalyse3dView extends StatefulWidget {
  final int patientId;
  final String? species;

  const Schmerzanalyse3dView({
    super.key,
    required this.patientId,
    this.species,
  });

  @override
  State<Schmerzanalyse3dView> createState() => _Schmerzanalyse3dViewState();
}

/// Prozessweit ein einziger Localhost-Server für die 3D-Assets.
class _A3dAssetServer {
  static final InAppLocalhostServer _server =
      InAppLocalhostServer(documentRoot: 'assets/3d', port: 8747);
  static bool _started = false;

  static const int port = 8747;

  static Future<void> ensureStarted() async {
    if (_started) return;
    try {
      await _server.start();
    } catch (_) {
      // Bereits gestartet (z. B. zweiter Aufruf) – ignorieren.
    }
    _started = true;
  }
}

String _mapSpecies(String? raw) {
  final v = (raw ?? '').toLowerCase();
  if (v.contains('katz') || v.contains('cat') || v.contains('feli')) {
    return 'cat';
  }
  if (v.contains('pferd') ||
      v.contains('horse') ||
      v.contains('pony') ||
      v.contains('equ')) {
    return 'horse';
  }
  return 'dog';
}

class _Schmerzanalyse3dViewState extends State<Schmerzanalyse3dView>
    with AutomaticKeepAliveClientMixin {
  bool _ready = false;
  String? _error;
  UserScript? _configScript;

  @override
  bool get wantKeepAlive => true;

  @override
  void initState() {
    super.initState();
    _prepare();
  }

  Future<void> _prepare() async {
    try {
      await _A3dAssetServer.ensureStarted();
      final token = await ApiService.getToken() ?? '';
      final cfg = jsonEncode({
        'apiBase': ApiService.baseUrl,
        'token': token,
        'patientId': widget.patientId,
        'animalType': _mapSpecies(widget.species),
      });
      _configScript = UserScript(
        source: 'window.__A3D_CONFIG__ = $cfg;',
        injectionTime: UserScriptInjectionTime.AT_DOCUMENT_START,
      );
      if (mounted) setState(() => _ready = true);
    } catch (e) {
      if (mounted) setState(() => _error = e.toString());
    }
  }

  @override
  Widget build(BuildContext context) {
    super.build(context);

    if (_error != null) {
      return _ErrorState(message: _error!, onRetry: () {
        setState(() {
          _error = null;
          _ready = false;
        });
        _prepare();
      });
    }

    if (!_ready || _configScript == null) {
      return const Center(child: CircularProgressIndicator());
    }

    return InAppWebView(
      initialUrlRequest: URLRequest(
        url: WebUri('http://localhost:${_A3dAssetServer.port}/viewer.html'),
      ),
      initialUserScripts:
          UnmodifiableListView<UserScript>(<UserScript>[_configScript!]),
      initialSettings: InAppWebViewSettings(
        transparentBackground: true,
        mediaPlaybackRequiresUserGesture: false,
        allowsInlineMediaPlayback: true,
        supportZoom: false,
        allowFileAccessFromFileURLs: true,
        allowUniversalAccessFromFileURLs: true,
      ),
      onConsoleMessage: (controller, msg) {
        debugPrint('[3D-Viewer] ${msg.message}');
      },
      onReceivedError: (controller, request, error) {
        debugPrint('[3D-Viewer] load error: ${error.description}');
      },
    );
  }
}

class _ErrorState extends StatelessWidget {
  final String message;
  final VoidCallback onRetry;

  const _ErrorState({required this.message, required this.onRetry});

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.view_in_ar_rounded,
                size: 56, color: Colors.grey.shade400),
            const SizedBox(height: 12),
            Text(
              '3D-Schmerzanalyse konnte nicht geladen werden.',
              textAlign: TextAlign.center,
              style: TextStyle(color: Colors.grey.shade600, fontSize: 15),
            ),
            const SizedBox(height: 8),
            Text(
              message,
              textAlign: TextAlign.center,
              style: TextStyle(color: Colors.grey.shade400, fontSize: 12),
            ),
            const SizedBox(height: 16),
            FilledButton.icon(
              onPressed: onRetry,
              icon: const Icon(Icons.refresh_rounded),
              label: const Text('Erneut versuchen'),
            ),
          ],
        ),
      ),
    );
  }
}
