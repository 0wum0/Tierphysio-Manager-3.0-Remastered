import 'package:flutter/material.dart';
import '../core/theme.dart';

class GradientHeader extends StatelessWidget {
  final String title;
  final String? subtitle;
  final Widget? leading;
  final List<Widget>? actions;
  final Color? color;

  const GradientHeader({
    super.key,
    required this.title,
    this.subtitle,
    this.leading,
    this.actions,
    this.color,
  });

  @override
  Widget build(BuildContext context) {
    final c = color ?? AppTheme.primary;
    return Container(
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [c, Color.lerp(c, AppTheme.secondary, 0.6)!],
        ),
      ),
      child: SafeArea(
        bottom: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 20),
          child: Row(children: [
            if (leading != null) ...[leading!, const SizedBox(width: 14)],
            Expanded(child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: const TextStyle(
                  color: Colors.white,
                  fontSize: 22,
                  fontWeight: FontWeight.w800,
                  letterSpacing: -0.5,
                )),
                if (subtitle != null) ...[
                  const SizedBox(height: 2),
                  Text(subtitle!, style: TextStyle(
                    color: Colors.white.withValues(alpha: 0.8),
                    fontSize: 13,
                  )),
                ],
              ],
            )),
            if (actions != null) ...actions!,
          ]),
        ),
      ),
    );
  }
}

class SliverGradientHeader extends StatelessWidget {
  final String title;
  final String? subtitle;
  final Widget? avatar;
  final List<Widget>? actions;
  final Color? color;

  const SliverGradientHeader({
    super.key,
    required this.title,
    this.subtitle,
    this.avatar,
    this.actions,
    this.color,
  });

  @override
  Widget build(BuildContext context) {
    final c = color ?? AppTheme.primary;
    return SliverToBoxAdapter(
      child: Stack(children: [
        // Gradient background
        Container(
          height: 160,
          decoration: BoxDecoration(
            gradient: LinearGradient(
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
              colors: [c, Color.lerp(c, AppTheme.secondary, 0.55)!],
            ),
          ),
        ),
        // Decorative circle
        Positioned(
          right: -30, top: -30,
          child: Container(
            width: 160, height: 160,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              color: Colors.white.withValues(alpha: 0.07),
            ),
          ),
        ),
        Positioned(
          right: 40, bottom: 10,
          child: Container(
            width: 80, height: 80,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              color: Colors.white.withValues(alpha: 0.05),
            ),
          ),
        ),
        // Content
        SafeArea(
          bottom: false,
          child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 24),
            child: Row(crossAxisAlignment: CrossAxisAlignment.center, children: [
              if (avatar != null) ...[avatar!, const SizedBox(width: 16)],
              Expanded(child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(title, style: const TextStyle(
                    color: Colors.white,
                    fontSize: 24,
                    fontWeight: FontWeight.w800,
                    letterSpacing: -0.5,
                  )),
                  if (subtitle != null) Text(subtitle!, style: TextStyle(
                    color: Colors.white.withValues(alpha: 0.8),
                    fontSize: 13,
                  )),
                ],
              )),
              if (actions != null) ...actions!,
            ]),
          ),
        ),
      ]),
    );
  }
}
