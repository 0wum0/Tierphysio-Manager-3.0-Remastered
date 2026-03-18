import 'package:flutter/material.dart';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:video_player/video_player.dart';

/// Thumbnail shown in the timeline card — tappable to full-screen
class MediaThumbnail extends StatelessWidget {
  final String url;
  final bool isVideo;

  const MediaThumbnail({super.key, required this.url, required this.isVideo});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: () => Navigator.push(
        context,
        MaterialPageRoute(builder: (_) => MediaViewerScreen(url: url, isVideo: isVideo)),
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
  }
}

/// Full-screen viewer for image or video
class MediaViewerScreen extends StatefulWidget {
  final String url;
  final bool isVideo;

  const MediaViewerScreen({super.key, required this.url, required this.isVideo});

  @override
  State<MediaViewerScreen> createState() => _MediaViewerScreenState();
}

class _MediaViewerScreenState extends State<MediaViewerScreen> {
  VideoPlayerController? _vpc;
  bool _initialized = false;

  @override
  void initState() {
    super.initState();
    if (widget.isVideo) {
      _vpc = VideoPlayerController.networkUrl(Uri.parse(widget.url))
        ..initialize().then((_) {
          if (mounted) setState(() => _initialized = true);
          _vpc!.play();
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
        fit: BoxFit.contain,
        placeholder: (_, __) => const CircularProgressIndicator(color: Colors.white),
        errorWidget: (_, __, ___) => const Icon(Icons.broken_image_rounded, color: Colors.white, size: 64),
      ),
    );
  }

  Widget _buildVideo() {
    if (!_initialized || _vpc == null) {
      return const CircularProgressIndicator(color: Colors.white);
    }
    return GestureDetector(
      onTap: () {
        setState(() {
          _vpc!.value.isPlaying ? _vpc!.pause() : _vpc!.play();
        });
      },
      child: Stack(alignment: Alignment.center, children: [
        AspectRatio(
          aspectRatio: _vpc!.value.aspectRatio,
          child: VideoPlayer(_vpc!),
        ),
        AnimatedOpacity(
          opacity: _vpc!.value.isPlaying ? 0.0 : 1.0,
          duration: const Duration(milliseconds: 300),
          child: Container(
            decoration: BoxDecoration(
              color: Colors.black.withValues(alpha: 0.4),
              shape: BoxShape.circle,
            ),
            padding: const EdgeInsets.all(16),
            child: const Icon(Icons.play_arrow_rounded, color: Colors.white, size: 48),
          ),
        ),
        Positioned(
          bottom: 0, left: 0, right: 0,
          child: VideoProgressIndicator(_vpc!, allowScrubbing: true,
            colors: const VideoProgressColors(playedColor: Colors.white)),
        ),
      ]),
    );
  }
}
