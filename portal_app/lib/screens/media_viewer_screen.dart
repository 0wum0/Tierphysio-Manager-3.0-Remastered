import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';
import '../services/portal_auth_service.dart';

class MediaViewerScreen extends StatelessWidget {
  final String url;
  final String title;
  final String kind; // 'image' | 'document' | 'video' | 'file'

  const MediaViewerScreen({
    super.key,
    required this.url,
    required this.title,
    this.kind = 'image',
  });

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.black,
      appBar: AppBar(
        backgroundColor: Colors.black,
        foregroundColor: Colors.white,
        title: Text(title, style: const TextStyle(fontSize: 15)),
        actions: [
          IconButton(
            icon: const Icon(Icons.open_in_browser_rounded),
            tooltip: 'Im Browser öffnen',
            onPressed: () async {
              final uri = Uri.tryParse(url);
              if (uri != null) {
                await launchUrl(uri, mode: LaunchMode.externalApplication);
              }
            },
          ),
        ],
      ),
      body: kind == 'image' ? _ImageView(url: url) : _DocView(url: url, kind: kind),
    );
  }
}

class _ImageView extends StatelessWidget {
  final String url;
  const _ImageView({required this.url});

  @override
  Widget build(BuildContext context) {
    final token = context.read<PortalAuthService>().token ?? '';
    return InteractiveViewer(
      minScale: 0.5,
      maxScale: 5.0,
      child: Center(
        child: CachedNetworkImage(
          imageUrl: url,
          httpHeaders: token.isNotEmpty ? {'Authorization': 'Bearer $token'} : {},
          fit: BoxFit.contain,
          placeholder: (_, __) => const Center(child: CircularProgressIndicator(color: Colors.white)),
          errorWidget: (_, __, ___) => const Center(child: Icon(Icons.broken_image_rounded, color: Colors.white54, size: 64)),
        ),
      ),
    );
  }
}

class _DocView extends StatelessWidget {
  final String url;
  final String kind;
  const _DocView({required this.url, required this.kind});

  @override
  Widget build(BuildContext context) {
    final icon = kind == 'document' ? Icons.description_rounded : Icons.insert_drive_file_rounded;
    return Center(child: Padding(
      padding: const EdgeInsets.all(32),
      child: Column(mainAxisSize: MainAxisSize.min, children: [
        Icon(icon, size: 80, color: Colors.white54),
        const SizedBox(height: 24),
        Text(
          kind == 'document' ? 'Dokument' : 'Datei',
          style: const TextStyle(color: Colors.white, fontSize: 20, fontWeight: FontWeight.w600),
        ),
        const SizedBox(height: 12),
        Text(
          'Tippe auf "Im Browser öffnen" um die Datei anzuzeigen.',
          style: const TextStyle(color: Colors.white60),
          textAlign: TextAlign.center,
        ),
        const SizedBox(height: 32),
        FilledButton.icon(
          onPressed: () async {
            final uri = Uri.tryParse(url);
            if (uri != null) await launchUrl(uri, mode: LaunchMode.externalApplication);
          },
          icon: const Icon(Icons.open_in_browser_rounded),
          label: const Text('Im Browser öffnen'),
        ),
      ]),
    ));
  }
}
