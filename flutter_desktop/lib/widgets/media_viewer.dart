import 'package:flutter/material.dart';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:video_player/video_player.dart';
import '../services/api_service.dart';

/// Thumbnail shown in the timeline card — tappable to full-screen
class MediaThumbnail extends StatelessWidget {
  final String url;
  final bool isVideo;
  final bool isPdf;

  const MediaThumbnail({
    super.key,
    required this.url,
    required this.isVideo,
    this.isPdf = false,
  });

  @override
  Widget build(BuildContext context) {
    if (isPdf) {
      return GestureDetector(
        onTap: () async {
          final uri = Uri.parse(url);
          if (await canLaunchUrl(uri)) await launchUrl(uri, mode: LaunchMode.externalApplication);
        },
        child: Container(
          height: 64,
          decoration: BoxDecoration(
            color: Colors.red.shade50,
            borderRadius: BorderRadius.circular(10),
            border: Border.all(color: Colors.red.shade200),
          ),
          child: Row(mainAxisAlignment: MainAxisAlignment.center, children: [
            Icon(Icons.picture_as_pdf_rounded, color: Colors.red.shade700, size: 28),
            const SizedBox(width: 10),
            Text('PDF öffnen', style: TextStyle(color: Colors.red.shade700, fontWeight: FontWeight.w600)),
          ]),
        ),
      );
    }

    return FutureBuilder<String?>(
      future: ApiService.getToken(),
      builder: (context, snap) {
        final headers = snap.hasData && snap.data != null
            ? {'Authorization': 'Bearer ${snap.data}'}
            : const <String, String>{};
        return GestureDetector(
          onTap: () => Navigator.push(
            context,
            MaterialPageRoute(builder: (_) => MediaViewerScreen(url: url, isVideo: isVideo, httpHeaders: headers)),
          ),
          child: ClipRRect(
            borderRadius: BorderRadius.circular(10),
            child: Stack(children: [
              SizedBox(
                height: 140,
                width: double.infinity,
                child: isVideo
                    ? Container(
                        color: Colors.black,
                        child: const Center(child: Icon(Icons.play_circle_fill_rounded, color: Colors.white, size: 48)),
                      )
                    : CachedNetworkImage(
                        imageUrl: url,
                        httpHeaders: headers,
                        fit: BoxFit.cover,
                        placeholder: (_, __) => Container(color: Colors.grey.shade200),
                        errorWidget: (_, __, ___) => Container(
                          color: Colors.grey.shade200,
                          child: const Icon(Icons.broken_image_rounded, color: Colors.grey),
                        ),
                      ),
              ),
              if (isVideo)
                Positioned.fill(
                  child: Container(
                    decoration: BoxDecoration(
                      borderRadius: BorderRadius.circular(10),
                      color: Colors.black.withValues(alpha: 0.35),
                    ),
                    child: const Center(
                      child: Icon(Icons.play_circle_fill_rounded, color: Colors.white, size: 52),
                    ),
                  ),
                ),
            ]),
          ),
        );
      },
    );
  }
}

/// Full-screen viewer for image or video
class MediaViewerScreen extends StatefulWidget {
  final String url;
  final bool isVideo;
  final Map<String, String> httpHeaders;

  const MediaViewerScreen({
    super.key,
    required this.url,
    required this.isVideo,
    this.httpHeaders = const {},
  });

  @override
  State<MediaViewerScreen> createState() => _MediaViewerScreenState();
}

class _MediaViewerScreenState extends State<MediaViewerScreen> {
  VideoPlayerController? _vpc;

  @override
  void initState() {
    super.initState();
    if (widget.isVideo) {
      _vpc = VideoPlayerController.networkUrl(
        Uri.parse(widget.url),
        httpHeaders: widget.httpHeaders,
      );
      _vpc!.initialize().then((_) {
        if (mounted) _vpc!.play();
      });
    }
  }

  @override
  void dispose() {
    _vpc?.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.black,
      appBar: AppBar(
        backgroundColor: Colors.black,
        foregroundColor: Colors.white,
        elevation: 0,
        actions: [
          IconButton(
            icon: const Icon(Icons.close_rounded),
            onPressed: () => Navigator.pop(context),
          ),
        ],
      ),
      body: Center(
        child: widget.isVideo ? _buildVideo() : _buildImage(),
      ),
    );
  }

  Widget _buildImage() {
    return InteractiveViewer(
      child: CachedNetworkImage(
        imageUrl: widget.url,
        httpHeaders: widget.httpHeaders,
        fit: BoxFit.contain,
        placeholder: (_, __) => const CircularProgressIndicator(color: Colors.white),
        errorWidget: (_, __, ___) => const Icon(Icons.broken_image_rounded, color: Colors.white, size: 64),
      ),
    );
  }

  Widget _buildVideo() {
    final vpc = _vpc;
    if (vpc == null) return const CircularProgressIndicator(color: Colors.white);
    return ValueListenableBuilder<VideoPlayerValue>(
      valueListenable: vpc,
      builder: (_, value, __) {
        if (!value.isInitialized) {
          return const CircularProgressIndicator(color: Colors.white);
        }
        return GestureDetector(
          onTap: () => value.isPlaying ? vpc.pause() : vpc.play(),
          child: Stack(alignment: Alignment.center, children: [
            AspectRatio(
              aspectRatio: value.aspectRatio,
              child: VideoPlayer(vpc),
            ),
            if (!value.isPlaying)
              Container(
                decoration: BoxDecoration(
                  color: Colors.black.withValues(alpha: 0.4),
                  shape: BoxShape.circle,
                ),
                padding: const EdgeInsets.all(16),
                child: const Icon(Icons.play_arrow_rounded, color: Colors.white, size: 48),
              ),
            Positioned(
              bottom: 0, left: 0, right: 0,
              child: VideoProgressIndicator(vpc, allowScrubbing: true,
                colors: const VideoProgressColors(playedColor: Colors.white)),
            ),
          ]),
        );
      },
    );
  }
}
