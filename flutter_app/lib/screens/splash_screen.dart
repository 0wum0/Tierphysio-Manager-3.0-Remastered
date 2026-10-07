import 'package:flutter/material.dart';

import '../services/auth_service.dart';

/// Restore the session without an artificial animation delay.
class SplashScreen extends StatefulWidget {
  final AuthService authService;
  final VoidCallback onComplete;
  const SplashScreen({
    super.key,
    required this.authService,
    required this.onComplete,
  });
  @override
  State<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends State<SplashScreen> {
  bool _failed = false;
  @override
  void initState() {
    super.initState();
    _restore();
  }

  Future<void> _restore() async {
    try {
      await widget.authService.init();
      if (mounted) widget.onComplete();
    } catch (_) {
      if (mounted) setState(() => _failed = true);
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return ColoredBox(
      color: theme.scaffoldBackgroundColor,
      child: SafeArea(
        child: Center(
          child: Padding(
            padding: const EdgeInsets.all(32),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Container(
                  padding: const EdgeInsets.all(24),
                  decoration: BoxDecoration(
                    color: theme.colorScheme.primary,
                    borderRadius: BorderRadius.circular(28),
                  ),
                  child: Icon(
                    Icons.pets_rounded,
                    size: 42,
                    color: theme.colorScheme.onPrimary,
                  ),
                ),
                const SizedBox(height: 24),
                Text('TheraPano', style: theme.textTheme.headlineMedium),
                const SizedBox(height: 12),
                Text(
                  _failed
                      ? 'Die App konnte nicht gestartet werden.'
                      : 'Deine Praxis wird vorbereitet.',
                  textAlign: TextAlign.center,
                  style: TextStyle(color: theme.colorScheme.onSurfaceVariant),
                ),
                const SizedBox(height: 32),
                if (_failed)
                  FilledButton.icon(
                    onPressed: () {
                      setState(() => _failed = false);
                      _restore();
                    },
                    icon: const Icon(Icons.refresh_rounded),
                    label: const Text('Erneut versuchen'),
                  )
                else
                  const SizedBox(
                    width: 24,
                    height: 24,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
