import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:shimmer/shimmer.dart';
import '../services/portal_auth_service.dart';

/// CachedNetworkImage with automatic Bearer auth header.
class AuthImage extends StatelessWidget {
  final String url;
  final double? width;
  final double? height;
  final BoxFit fit;
  final Widget? errorWidget;

  const AuthImage({
    super.key,
    required this.url,
    this.width,
    this.height,
    this.fit = BoxFit.cover,
    this.errorWidget,
  });

  @override
  Widget build(BuildContext context) {
    final token = context.read<PortalAuthService>().token ?? '';
    return CachedNetworkImage(
      imageUrl: url,
      httpHeaders: token.isNotEmpty ? {'Authorization': 'Bearer $token'} : {},
      width: width,
      height: height,
      fit: fit,
      placeholder: (_, __) => _shimmer(context),
      errorWidget: (_, __, ___) =>
          errorWidget ?? const Icon(Icons.broken_image_rounded, color: Colors.grey),
    );
  }

  Widget _shimmer(BuildContext context) {
    final base = Theme.of(context).colorScheme.surfaceContainerHighest;
    return Shimmer.fromColors(
      baseColor: base,
      highlightColor: base.withValues(alpha: 0.5),
      child: Container(color: base, width: width, height: height),
    );
  }
}

/// Circular avatar that uses Bearer auth. Falls back to icon when url is null.
class AuthAvatar extends StatelessWidget {
  final String? url;
  final double radius;
  final IconData fallbackIcon;
  final Color iconColor;
  final Color bgColor;

  const AuthAvatar({
    super.key,
    required this.url,
    this.radius = 32,
    this.fallbackIcon = Icons.pets_rounded,
    this.iconColor = Colors.grey,
    this.bgColor = const Color(0xFFE8F0FE),
  });

  @override
  Widget build(BuildContext context) {
    if (url == null || url!.isEmpty) {
      return CircleAvatar(
        radius: radius,
        backgroundColor: bgColor,
        child: Icon(fallbackIcon, color: iconColor, size: radius * 0.85),
      );
    }
    final token = context.read<PortalAuthService>().token ?? '';
    return CachedNetworkImage(
      imageUrl: url!,
      httpHeaders: token.isNotEmpty ? {'Authorization': 'Bearer $token'} : {},
      imageBuilder: (_, img) => CircleAvatar(radius: radius, backgroundImage: img),
      placeholder: (_, __) => CircleAvatar(
        radius: radius,
        backgroundColor: bgColor,
        child: Shimmer.fromColors(
          baseColor: bgColor,
          highlightColor: bgColor.withValues(alpha: 0.5),
          child: CircleAvatar(radius: radius, backgroundColor: bgColor),
        ),
      ),
      errorWidget: (_, __, ___) => CircleAvatar(
        radius: radius,
        backgroundColor: bgColor,
        child: Icon(fallbackIcon, color: iconColor, size: radius * 0.85),
      ),
    );
  }
}
