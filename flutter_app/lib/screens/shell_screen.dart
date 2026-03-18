import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
import 'package:flutter_svg/flutter_svg.dart';
import '../services/auth_service.dart';
import '../core/theme.dart';

class ShellScreen extends StatefulWidget {
  final Widget child;
  const ShellScreen({super.key, required this.child});

  @override
  State<ShellScreen> createState() => _ShellScreenState();
}

class _ShellScreenState extends State<ShellScreen> {
  int _selectedIndex = 0;

  static const _routes = [
    '/dashboard',
    '/patienten',
    '/tierhalter',
    '/rechnungen',
    '/kalender',
  ];

  void _onDestinationSelected(int idx) {
    setState(() => _selectedIndex = idx);
    context.go(_routes[idx]);
  }

  @override
  Widget build(BuildContext context) {
    final isWide = MediaQuery.of(context).size.width >= 600;

    if (isWide) {
      final extended = MediaQuery.of(context).size.width >= 900;
      return Scaffold(
        body: Row(children: [
          NavigationRail(
            extended: extended,
            selectedIndex: _selectedIndex,
            onDestinationSelected: _onDestinationSelected,
            leading: Column(children: [
              const SizedBox(height: 8),
              Container(
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: AppTheme.primary.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(14),
                ),
                child: SvgPicture.asset(
                  'assets/icons/paw.svg',
                  width: 28, height: 28,
                  colorFilter: ColorFilter.mode(AppTheme.primary, BlendMode.srcIn),
                ),
              ),
              if (extended) ...[
                const SizedBox(height: 6),
                Text('TierPhysio', style: TextStyle(
                  fontSize: 13, fontWeight: FontWeight.w800,
                  color: AppTheme.primary, letterSpacing: -0.3,
                )),
              ],
              const SizedBox(height: 16),
            ]),
            trailing: Expanded(
              child: Align(
                alignment: Alignment.bottomCenter,
                child: Padding(
                  padding: const EdgeInsets.only(bottom: 20),
                  child: IconButton(
                    icon: const Icon(Icons.logout_rounded),
                    tooltip: 'Abmelden',
                    onPressed: () => _confirmLogout(context),
                  ),
                ),
              ),
            ),
            destinations: const [
              NavigationRailDestination(icon: Icon(Icons.dashboard_outlined), selectedIcon: Icon(Icons.dashboard_rounded), label: Text('Dashboard')),
              NavigationRailDestination(icon: Icon(Icons.pets_outlined), selectedIcon: Icon(Icons.pets_rounded), label: Text('Patienten')),
              NavigationRailDestination(icon: Icon(Icons.person_outline_rounded), selectedIcon: Icon(Icons.person_rounded), label: Text('Tierhalter')),
              NavigationRailDestination(icon: Icon(Icons.receipt_long_outlined), selectedIcon: Icon(Icons.receipt_long_rounded), label: Text('Rechnungen')),
              NavigationRailDestination(icon: Icon(Icons.calendar_month_outlined), selectedIcon: Icon(Icons.calendar_month_rounded), label: Text('Kalender')),
            ],
          ),
          VerticalDivider(width: 1, color: Theme.of(context).dividerColor),
          Expanded(child: widget.child),
        ]),
      );
    }

    return Scaffold(
      body: widget.child,
      bottomNavigationBar: NavigationBar(
        selectedIndex: _selectedIndex,
        onDestinationSelected: _onDestinationSelected,
        destinations: [
          const NavigationDestination(icon: Icon(Icons.dashboard_outlined), selectedIcon: Icon(Icons.dashboard_rounded), label: 'Dashboard'),
          NavigationDestination(
            icon: SvgPicture.asset('assets/icons/paw.svg', width: 22, height: 22,
                colorFilter: ColorFilter.mode(Theme.of(context).colorScheme.onSurfaceVariant, BlendMode.srcIn)),
            selectedIcon: SvgPicture.asset('assets/icons/paw.svg', width: 22, height: 22,
                colorFilter: ColorFilter.mode(AppTheme.primary, BlendMode.srcIn)),
            label: 'Patienten',
          ),
          const NavigationDestination(icon: Icon(Icons.person_outline_rounded), selectedIcon: Icon(Icons.person_rounded), label: 'Tierhalter'),
          const NavigationDestination(icon: Icon(Icons.receipt_long_outlined), selectedIcon: Icon(Icons.receipt_long_rounded), label: 'Rechnungen'),
          const NavigationDestination(icon: Icon(Icons.calendar_month_outlined), selectedIcon: Icon(Icons.calendar_month_rounded), label: 'Kalender'),
        ],
      ),
    );
  }

  Future<void> _confirmLogout(BuildContext context) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (_) => AlertDialog(
        title: const Text('Abmelden'),
        content: const Text('Möchten Sie sich wirklich abmelden?'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Abbrechen')),
          FilledButton(onPressed: () => Navigator.pop(context, true), child: const Text('Abmelden')),
        ],
      ),
    );
    if (ok == true && context.mounted) {
      await context.read<AuthService>().logout();
    }
  }
}
