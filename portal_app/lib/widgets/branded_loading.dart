import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../core/theme.dart';
import '../services/portal_auth_service.dart';

/// Full-screen loading indicator with company name.
class BrandedLoading extends StatelessWidget {
  final String? label;
  const BrandedLoading({super.key, this.label});

  @override
  Widget build(BuildContext context) {
    final practiceName = context.watch<PortalAuthService>().practiceName;
    return Center(child: Column(mainAxisSize: MainAxisSize.min, children: [
      const SizedBox(
        width: 48, height: 48,
        child: CircularProgressIndicator(strokeWidth: 3, color: AppTheme.primary),
      ),
      const SizedBox(height: 20),
      Text(
        practiceName,
        style: const TextStyle(
          fontSize: 17,
          fontWeight: FontWeight.w700,
          color: AppTheme.primary,
          letterSpacing: 0.3,
        ),
      ),
      if (label != null) ...[
        const SizedBox(height: 6),
        Text(label!, style: TextStyle(
          fontSize: 13,
          color: Theme.of(context).colorScheme.onSurfaceVariant,
        )),
      ],
    ]));
  }
}
