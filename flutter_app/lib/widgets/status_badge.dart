import 'package:flutter/material.dart';
import '../core/theme.dart';

class StatusBadge extends StatelessWidget {
  final String status;
  final String? label;
  const StatusBadge({super.key, required this.status, this.label});

  @override
  Widget build(BuildContext context) {
    final (lbl, color) = _resolve(status);
    final text = label ?? lbl;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: color.withValues(alpha: 0.25)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 6,
            height: 6,
            decoration: BoxDecoration(color: color, shape: BoxShape.circle),
          ),
          const SizedBox(width: 6),
          Text(
            text,
            style: TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.w600,
              color: color,
              letterSpacing: 0.2,
            ),
          ),
        ],
      ),
    );
  }

  static (String, Color) _resolve(String s) => switch (s.toLowerCase()) {
    'active'   || 'aktiv'      => ('Aktiv',      AppTheme.success),
    'paid'     || 'bezahlt'    => ('Bezahlt',     AppTheme.success),
    'open'     || 'offen'      => ('Offen',       AppTheme.primary),
    'overdue'  || 'überfällig' => ('Überfällig',  AppTheme.danger),
    'draft'    || 'entwurf'    => ('Entwurf',     AppTheme.warning),
    'inactive' || 'inaktiv'    => ('Inaktiv',     Colors.grey),
    'deceased' || 'verstorben' => ('Verstorben',  Colors.grey),
    'confirmed'                => ('Bestätigt',   AppTheme.success),
    'cancelled'                => ('Abgesagt',    AppTheme.danger),
    'pending'                  => ('Ausstehend',  AppTheme.warning),
    _                          => (s,             Colors.grey),
  };

  static Color colorFor(String s) => _resolve(s).$2;
}
