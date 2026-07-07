import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../core/theme.dart';
import '../services/portal_auth_service.dart';
import 'invoices_screen.dart';
import 'homework_screen.dart';
import 'befunde_screen.dart';
import 'profile_screen.dart';

class MoreScreen extends StatelessWidget {
  const MoreScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<PortalAuthService>();
    final cs   = Theme.of(context).colorScheme;

    return Scaffold(
      appBar: AppBar(title: const Text('Mehr')),
      body: ListView(padding: const EdgeInsets.only(bottom: 32), children: [
        // Practice banner
        if (auth.practiceName.isNotEmpty)
          Container(
            width: double.infinity,
            margin: const EdgeInsets.fromLTRB(16, 16, 16, 0),
            padding: const EdgeInsets.symmetric(vertical: 10, horizontal: 16),
            decoration: BoxDecoration(
              color: AppTheme.primary.withValues(alpha: 0.08),
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: AppTheme.primary.withValues(alpha: 0.2)),
            ),
            child: Row(children: [
              const Icon(Icons.business_rounded, color: AppTheme.primary, size: 18),
              const SizedBox(width: 8),
              Text(auth.practiceName,
                style: const TextStyle(fontWeight: FontWeight.w600, color: AppTheme.primary, fontSize: 14)),
            ]),
          ),

        // Profile header
        Padding(padding: const EdgeInsets.all(20), child: Row(children: [
          CircleAvatar(radius: 32, backgroundColor: AppTheme.primary.withValues(alpha: 0.12),
            child: const Icon(Icons.person_rounded, color: AppTheme.primary, size: 32)),
          const SizedBox(width: 16),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(auth.name, style: Theme.of(context).textTheme.titleLarge),
            Text(auth.email, style: TextStyle(color: cs.onSurfaceVariant)),
          ])),
        ])),

        const Divider(),

        _MenuTile(
          icon: Icons.receipt_long_rounded,
          color: AppTheme.warning,
          title: 'Rechnungen',
          subtitle: 'Ihre Rechnungen und Zahlungen',
          onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const InvoicesScreen())),
        ),
        _MenuTile(
          icon: Icons.assignment_rounded,
          color: AppTheme.success,
          title: 'Hausaufgaben',
          subtitle: 'Übungen für Ihr Tier',
          onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const HomeworkScreen())),
        ),
        // Befundbögen only for therapeut-type practices
        if (!auth.isTrainer)
          _MenuTile(
            icon: Icons.description_rounded,
            color: AppTheme.tertiary,
            title: 'Befundbögen',
            subtitle: 'Befundberichte einsehen',
            onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const BefundeScreen())),
          ),

        const Divider(),

        _MenuTile(
          icon: Icons.manage_accounts_rounded,
          color: AppTheme.primary,
          title: 'Profil & Passwort',
          subtitle: 'Konto-Einstellungen',
          onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const ProfileScreen())),
        ),
        _MenuTile(
          icon: Icons.logout_rounded,
          color: AppTheme.danger,
          title: 'Abmelden',
          subtitle: 'Von diesem Gerät abmelden',
          onTap: () async {
            final confirmed = await showDialog<bool>(
              context: context,
              builder: (_) => AlertDialog(
                title: const Text('Abmelden'),
                content: const Text('Wirklich abmelden?'),
                actions: [
                  TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Abbrechen')),
                  FilledButton(
                    onPressed: () => Navigator.pop(context, true),
                    style: FilledButton.styleFrom(backgroundColor: AppTheme.danger),
                    child: const Text('Abmelden'),
                  ),
                ],
              ),
            );
            if (confirmed == true && context.mounted) {
              context.read<PortalAuthService>().logout();
            }
          },
        ),
      ]),
    );
  }
}

class _MenuTile extends StatelessWidget {
  final IconData icon;
  final Color color;
  final String title;
  final String subtitle;
  final VoidCallback onTap;
  const _MenuTile({required this.icon, required this.color, required this.title, required this.subtitle, required this.onTap});

  @override
  Widget build(BuildContext context) => ListTile(
    leading: Container(width: 44, height: 44,
      decoration: BoxDecoration(color: color.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(12)),
      child: Icon(icon, color: color, size: 22)),
    title: Text(title, style: const TextStyle(fontWeight: FontWeight.w600)),
    subtitle: Text(subtitle, style: TextStyle(color: Theme.of(context).colorScheme.onSurfaceVariant, fontSize: 13)),
    trailing: const Icon(Icons.chevron_right_rounded),
    onTap: onTap,
  );
}
