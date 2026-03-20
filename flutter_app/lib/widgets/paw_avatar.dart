import 'package:flutter/material.dart';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:shimmer/shimmer.dart';
import '../services/api_service.dart';
import '../core/theme.dart';

class PawAvatar extends StatelessWidget {
  final String? photoPath;
  final String? species;
  final String? name;
  final double radius;
  final Color? color;

  const PawAvatar({
    super.key,
    this.photoPath,
    this.species,
    this.name,
    this.radius = 28,
    this.color,
  });

  @override
  Widget build(BuildContext context) {
    final bg = color ?? _speciesColor(species);

    if (photoPath != null && photoPath!.isNotEmpty) {
      final url = ApiService.mediaUrl(photoPath!);
      return FutureBuilder<String?>(
        future: ApiService.getToken(),
        builder: (context, snap) {
          final headers = snap.hasData && snap.data != null
              ? {'Authorization': 'Bearer ${snap.data}'}
              : const <String, String>{};
          return ClipRRect(
            borderRadius: BorderRadius.circular(radius),
            child: SizedBox(
              width: radius * 2,
              height: radius * 2,
              child: CachedNetworkImage(
                imageUrl: url,
                httpHeaders: headers,
                fit: BoxFit.cover,
                placeholder: (_, __) => _shimmer(radius),
                errorWidget: (_, __, ___) => _fallback(bg, radius),
              ),
            ),
          );
        },
      );
    }
    return _fallback(bg, radius);
  }

  Widget _fallback(Color bg, double r) {
    return Container(
      width: r * 2,
      height: r * 2,
      decoration: BoxDecoration(
        color: bg.withValues(alpha: 0.15),
        borderRadius: BorderRadius.circular(r),
      ),
      child: Center(
        child: SvgPicture.asset(
          'assets/icons/paw.svg',
          width: r * 0.9,
          height: r * 0.9,
          colorFilter: ColorFilter.mode(bg, BlendMode.srcIn),
        ),
      ),
    );
  }

  static Widget _shimmer(double r) {
    return Shimmer.fromColors(
      baseColor: Colors.grey.shade300,
      highlightColor: Colors.grey.shade100,
      child: Container(
        width: r * 2,
        height: r * 2,
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(r),
        ),
      ),
    );
  }

  static Color _speciesColor(String? species) {
    switch (species?.toLowerCase()) {
      case 'hund': return AppTheme.primary;
      case 'katze': return AppTheme.secondary;
      case 'pferd': return AppTheme.warning;
      case 'vogel': return AppTheme.tertiary;
      case 'kaninchen':
      case 'hase': return AppTheme.success;
      default: return AppTheme.primary;
    }
  }
}
