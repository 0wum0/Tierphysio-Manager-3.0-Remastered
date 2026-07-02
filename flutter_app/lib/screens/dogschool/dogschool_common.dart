import 'package:flutter/material.dart';
import '../../core/theme.dart';

/// Shared helpers for the Hundeschule (dogschool) module screens.

String euroFromCents(dynamic cents) {
  final v = (cents is num) ? cents.toDouble() : double.tryParse('${cents ?? 0}') ?? 0;
  return '${(v / 100).toStringAsFixed(2).replaceAll('.', ',')} €';
}

int asInt(dynamic v) {
  if (v is int) return v;
  if (v is num) return v.toInt();
  return int.tryParse('${v ?? ''}') ?? 0;
}

String asStr(dynamic v) => (v ?? '').toString();

/// German label for a course type key.
String courseTypeLabel(String type) {
  const map = {
    'group': 'Gruppe',
    'welpen': 'Welpen',
    'junghunde': 'Junghunde',
    'alltag': 'Alltag',
    'rueckruf': 'Rückruf',
    'leinenfuehrigkeit': 'Leinenführigkeit',
    'begegnung': 'Begegnung',
    'social_walk': 'Social Walk',
    'problem': 'Problemverhalten',
    'agility': 'Agility',
    'beschaeftigung': 'Beschäftigung',
    'workshop': 'Workshop',
    'seminar': 'Seminar',
    'event': 'Event',
  };
  return map[type] ?? type;
}

const courseTypeKeys = [
  'group', 'welpen', 'junghunde', 'alltag', 'rueckruf', 'leinenfuehrigkeit',
  'begegnung', 'social_walk', 'problem', 'agility', 'beschaeftigung',
  'workshop', 'seminar', 'event',
];

String courseStatusLabel(String status) {
  const map = {
    'draft': 'Entwurf',
    'active': 'Aktiv',
    'full': 'Ausgebucht',
    'paused': 'Pausiert',
    'completed': 'Abgeschlossen',
    'cancelled': 'Storniert',
  };
  return map[status] ?? status;
}

Color statusColor(String status) {
  switch (status) {
    case 'active':
    case 'converted':
    case 'signed':
    case 'present':
      return AppTheme.success;
    case 'draft':
    case 'new':
    case 'waiting':
    case 'planned':
      return AppTheme.primary;
    case 'full':
    case 'paused':
    case 'contacted':
    case 'trial_scheduled':
    case 'late':
      return AppTheme.warning;
    case 'cancelled':
    case 'lost':
    case 'absent':
    case 'no_show':
    case 'expired':
      return AppTheme.danger;
    default:
      return AppTheme.tertiary;
  }
}

String leadStatusLabel(String status) {
  const map = {
    'new': 'Neu',
    'contacted': 'Kontaktiert',
    'trial_scheduled': 'Probe geplant',
    'trial_done': 'Probe erledigt',
    'converted': 'Konvertiert',
    'lost': 'Verloren',
    'archived': 'Archiviert',
  };
  return map[status] ?? status;
}

String attendanceLabel(String status) {
  const map = {
    'present': 'Anwesend',
    'absent': 'Abwesend',
    'excused': 'Entschuldigt',
    'late': 'Verspätet',
    'left_early': 'Früher weg',
    'no_show': 'Nicht erschienen',
  };
  return map[status] ?? status;
}

/// Small coloured status pill.
class StatusChip extends StatelessWidget {
  final String label;
  final Color color;
  const StatusChip({super.key, required this.label, required this.color});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: color.withValues(alpha: 0.25)),
      ),
      child: Text(
        label,
        style: TextStyle(color: color, fontSize: 11, fontWeight: FontWeight.w700),
      ),
    );
  }
}

/// A rounded card used for list rows across the module.
class DsCard extends StatelessWidget {
  final Widget child;
  final VoidCallback? onTap;
  final EdgeInsets padding;
  const DsCard({
    super.key,
    required this.child,
    this.onTap,
    this.padding = const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
  });

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    return Material(
      color: isDark ? const Color(0xFF1A1D27) : Colors.white,
      borderRadius: BorderRadius.circular(16),
      child: InkWell(
        borderRadius: BorderRadius.circular(16),
        onTap: onTap,
        child: Container(
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(16),
            border: Border.all(
              color: isDark
                  ? Colors.white.withValues(alpha: 0.07)
                  : Colors.black.withValues(alpha: 0.06),
            ),
          ),
          padding: padding,
          child: child,
        ),
      ),
    );
  }
}

/// Generic empty / error / loading body used by every dogschool screen.
class AsyncBody extends StatelessWidget {
  final bool loading;
  final String? error;
  final bool empty;
  final String emptyTitle;
  final String emptyHint;
  final IconData emptyIcon;
  final Future<void> Function() onRetry;
  final Widget child;

  const AsyncBody({
    super.key,
    required this.loading,
    required this.error,
    required this.empty,
    required this.emptyTitle,
    required this.emptyHint,
    required this.emptyIcon,
    required this.onRetry,
    required this.child,
  });

  @override
  Widget build(BuildContext context) {
    if (loading) {
      return const Center(child: CircularProgressIndicator());
    }
    if (error != null) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(Icons.error_outline_rounded, size: 56, color: AppTheme.danger),
            const SizedBox(height: 12),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 24),
              child: Text(error!, textAlign: TextAlign.center),
            ),
            const SizedBox(height: 16),
            FilledButton.icon(
              onPressed: onRetry,
              icon: const Icon(Icons.refresh_rounded),
              label: const Text('Erneut'),
            ),
          ],
        ),
      );
    }
    if (empty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(emptyIcon, size: 72, color: Colors.grey.shade300),
            const SizedBox(height: 16),
            Text(emptyTitle,
                style: TextStyle(color: Colors.grey.shade500, fontSize: 16)),
            const SizedBox(height: 8),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 32),
              child: Text(
                emptyHint,
                style: TextStyle(color: Colors.grey.shade400, fontSize: 13),
                textAlign: TextAlign.center,
              ),
            ),
          ],
        ),
      );
    }
    return child;
  }
}

void showDsSnack(BuildContext context, String msg, {bool error = false}) {
  ScaffoldMessenger.of(context).showSnackBar(SnackBar(
    content: Text(msg),
    backgroundColor: error ? AppTheme.danger : AppTheme.success,
    behavior: SnackBarBehavior.floating,
    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
  ));
}
