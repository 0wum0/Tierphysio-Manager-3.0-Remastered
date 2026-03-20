import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
import 'package:flutter_svg/flutter_svg.dart';
import '../services/auth_service.dart';
import '../services/api_service.dart';
import '../core/theme.dart';

class ShellScreen extends StatefulWidget {
  final Widget child;
  const ShellScreen({super.key, required this.child});

  @override
  State<ShellScreen> createState() => _ShellScreenState();
}

class _ShellScreenState extends State<ShellScreen> {
  final _api = ApiService();
  int _unreadMessages = 0;
  int _overdueCount  = 0;

  // Primary bottom-nav routes (phone)
  static const _primaryRoutes = [
    '/dashboard',
    '/patienten',
    '/rechnungen',
    '/kalender',
    '/nachrichten',
  ];

  // All rail routes (tablet/wide)
  static const _railRoutes = [
    '/dashboard',
    '/patienten',
    '/tierhalter',
    '/rechnungen',
    '/kalender',
    '/nachrichten',
    '/warteliste',
    '/mahnungen',
    '/behandlungsarten',
    '/portal-admin',
  ];

  @override
  void initState() {
    super.initState();
    _pollUnread();
    _loadOverdue();
  }

  Future<void> _pollUnread() async {
    try {
      final count = await _api.messageUnread();
      if (mounted) setState(() => _unreadMessages = count);
    } catch (_) {}
    Future.delayed(const Duration(seconds: 60), () {
      if (mounted) _pollUnread();
    });
  }

  Future<void> _loadOverdue() async {
    try {
      final list = await _api.overdueAlerts();
      if (mounted) setState(() => _overdueCount = list.length);
    } catch (_) {}
  }

  void _onPrimarySelected(int idx) {
    context.go(_primaryRoutes[idx]);
    if (idx == 4) {
      Future.delayed(const Duration(milliseconds: 800), () {
        if (mounted) _pollUnread();
      });
    }
  }

  void _onRailSelected(int idx) {
    context.go(_railRoutes[idx]);
  }

  Widget _msgBadge({bool selected = false, bool rail = false}) {
    final icon = Icon(
      selected ? Icons.chat_rounded : Icons.chat_outlined,
      size: rail ? 24 : 22,
    );
    if (_unreadMessages == 0) return icon;
    return Badge(
      label: Text('$_unreadMessages', style: const TextStyle(fontSize: 9, fontWeight: FontWeight.w700)),
      backgroundColor: AppTheme.danger,
      child: icon,
    );
  }

  Widget _overdueBadge({bool selected = false}) {
    final icon = Icon(selected ? Icons.warning_amber_rounded : Icons.warning_amber_outlined);
    if (_overdueCount == 0) return icon;
    return Badge(
      label: Text('$_overdueCount', style: const TextStyle(fontSize: 9, fontWeight: FontWeight.w700)),
      backgroundColor: AppTheme.warning,
      child: icon,
    );
  }

  void _openMoreDrawer() {
    showModalBottomSheet(
      context: context,
      backgroundColor: Theme.of(context).colorScheme.surface,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (ctx) => _MoreSheet(
        unread: _unreadMessages,
        overdue: _overdueCount,
        onTap: (route) {
          Navigator.pop(ctx);
          context.push(route);
        },
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final isWide = MediaQuery.of(context).size.width >= 600;

    if (isWide) {
      return _buildWideLayout(context);
    }
    return _buildNarrowLayout(context);
  }

  Widget _buildWideLayout(BuildContext context) {
    final extended = MediaQuery.of(context).size.width >= 960;
    // sync selectedIndex for rail
    final location = GoRouterState.of(context).matchedLocation;
    final railIdx = _railRoutes.indexWhere((r) => location.startsWith(r));
    final railSelected = railIdx >= 0 ? railIdx : 0;

    return Scaffold(
      body: Row(children: [
        NavigationRail(
          extended: extended,
          selectedIndex: railSelected,
          onDestinationSelected: _onRailSelected,
          leading: Column(children: [
            const SizedBox(height: 12),
            Container(
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: AppTheme.primary.withValues(alpha: 0.12),
                borderRadius: BorderRadius.circular(14),
              ),
              child: SvgPicture.asset('assets/icons/paw.svg', width: 26, height: 26,
                colorFilter: ColorFilter.mode(AppTheme.primary, BlendMode.srcIn)),
            ),
            if (extended) ...[const SizedBox(height: 4),
              Text('TierPhysio', style: TextStyle(
                fontSize: 12, fontWeight: FontWeight.w800,
                color: AppTheme.primary, letterSpacing: -0.3)),
            ],
            const SizedBox(height: 4),
            if (extended)
              SizedBox(width: 180, child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
                child: TextButton.icon(
                  icon: const Icon(Icons.search_rounded, size: 16),
                  label: const Text('Suche', style: TextStyle(fontSize: 12)),
                  onPressed: () => context.push('/suche'),
                  style: TextButton.styleFrom(alignment: Alignment.centerLeft),
                ),
              ))
            else
              IconButton(
                icon: const Icon(Icons.search_rounded, size: 20),
                tooltip: 'Suche',
                onPressed: () => context.push('/suche'),
              ),
            const SizedBox(height: 8),
          ]),
          trailing: Expanded(
            child: Align(
              alignment: Alignment.bottomCenter,
              child: Padding(
                padding: const EdgeInsets.only(bottom: 16),
                child: Column(mainAxisSize: MainAxisSize.min, children: [
                  extended
                    ? TextButton.icon(
                        icon: const Icon(Icons.person_rounded, size: 18),
                        label: const Text('Profil', style: TextStyle(fontSize: 13)),
                        onPressed: () => context.push('/profil'),
                      )
                    : IconButton(
                        icon: const Icon(Icons.person_rounded),
                        tooltip: 'Profil',
                        onPressed: () => context.push('/profil'),
                      ),
                  extended
                    ? TextButton.icon(
                        icon: const Icon(Icons.logout_rounded, size: 18),
                        label: const Text('Abmelden', style: TextStyle(fontSize: 13)),
                        onPressed: () => _confirmLogout(context),
                        style: TextButton.styleFrom(foregroundColor: AppTheme.danger),
                      )
                    : IconButton(
                        icon: const Icon(Icons.logout_rounded),
                        tooltip: 'Abmelden',
                        onPressed: () => _confirmLogout(context),
                      ),
                ]),
              ),
            ),
          ),
          destinations: [
            const NavigationRailDestination(icon: Icon(Icons.dashboard_outlined), selectedIcon: Icon(Icons.dashboard_rounded), label: Text('Dashboard')),
            const NavigationRailDestination(icon: Icon(Icons.pets_outlined),      selectedIcon: Icon(Icons.pets_rounded),      label: Text('Patienten')),
            const NavigationRailDestination(icon: Icon(Icons.person_outline_rounded), selectedIcon: Icon(Icons.person_rounded), label: Text('Tierhalter')),
            const NavigationRailDestination(icon: Icon(Icons.receipt_long_outlined), selectedIcon: Icon(Icons.receipt_long_rounded), label: Text('Rechnungen')),
            const NavigationRailDestination(icon: Icon(Icons.calendar_month_outlined), selectedIcon: Icon(Icons.calendar_month_rounded), label: Text('Kalender')),
            NavigationRailDestination(
              icon: _msgBadge(rail: true), selectedIcon: _msgBadge(selected: true, rail: true),
              label: const Text('Nachrichten'),
            ),
            const NavigationRailDestination(icon: Icon(Icons.people_alt_outlined), selectedIcon: Icon(Icons.people_alt_rounded), label: Text('Warteliste')),
            NavigationRailDestination(
              icon: _overdueBadge(), selectedIcon: _overdueBadge(selected: true),
              label: const Text('Mahnungen'),
            ),
            const NavigationRailDestination(icon: Icon(Icons.category_outlined), selectedIcon: Icon(Icons.category_rounded), label: Text('Behandlungsarten')),
            const NavigationRailDestination(icon: Icon(Icons.home_work_outlined), selectedIcon: Icon(Icons.home_work_rounded), label: Text('Portal Admin')),
          ],
        ),
        VerticalDivider(width: 1, color: Theme.of(context).dividerColor),
        Expanded(child: widget.child),
      ]),
    );
  }

  Widget _buildNarrowLayout(BuildContext context) {
    final location = GoRouterState.of(context).matchedLocation;
    final primaryIdx = _primaryRoutes.indexWhere((r) => location.startsWith(r));
    final navIdx = primaryIdx >= 0 ? primaryIdx : 0;

    return Scaffold(
      body: widget.child,
      bottomNavigationBar: NavigationBar(
        selectedIndex: navIdx,
        onDestinationSelected: (idx) {
          if (idx == 5) {
            _openMoreDrawer();
          } else {
            _onPrimarySelected(idx);
          }
        },
        destinations: [
          const NavigationDestination(
            icon: Icon(Icons.dashboard_outlined),
            selectedIcon: Icon(Icons.dashboard_rounded),
            label: 'Dashboard',
          ),
          NavigationDestination(
            icon: SvgPicture.asset('assets/icons/paw.svg', width: 22, height: 22,
              colorFilter: ColorFilter.mode(Theme.of(context).colorScheme.onSurfaceVariant, BlendMode.srcIn)),
            selectedIcon: SvgPicture.asset('assets/icons/paw.svg', width: 22, height: 22,
              colorFilter: ColorFilter.mode(AppTheme.primary, BlendMode.srcIn)),
            label: 'Patienten',
          ),
          const NavigationDestination(
            icon: Icon(Icons.receipt_long_outlined),
            selectedIcon: Icon(Icons.receipt_long_rounded),
            label: 'Rechnungen',
          ),
          const NavigationDestination(
            icon: Icon(Icons.calendar_month_outlined),
            selectedIcon: Icon(Icons.calendar_month_rounded),
            label: 'Kalender',
          ),
          NavigationDestination(
            icon: _msgBadge(),
            selectedIcon: _msgBadge(selected: true),
            label: 'Nachrichten',
          ),
          NavigationDestination(
            icon: Badge(
              isLabelVisible: _overdueCount > 0,
              label: Text('$_overdueCount', style: const TextStyle(fontSize: 9)),
              backgroundColor: AppTheme.danger,
              child: const Icon(Icons.grid_view_outlined),
            ),
            selectedIcon: const Icon(Icons.grid_view_rounded),
            label: 'Mehr',
          ),
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

// ── "Mehr" bottom sheet ────────────────────────────────────────────────────────

class _MoreSheet extends StatelessWidget {
  final int unread;
  final int overdue;
  final void Function(String route) onTap;

  const _MoreSheet({required this.unread, required this.overdue, required this.onTap});

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 8, 16, 32),
      child: Column(mainAxisSize: MainAxisSize.min, children: [
        Container(width: 40, height: 4, margin: const EdgeInsets.only(bottom: 16),
          decoration: BoxDecoration(color: Colors.grey.shade300, borderRadius: BorderRadius.circular(2))),
        Text('Menü', style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700)),
        const SizedBox(height: 16),
        _grid(context, [
          _SheetItem(Icons.person_rounded,       'Tierhalter',       AppTheme.secondary, '/tierhalter'),
          _SheetItem(Icons.people_alt_rounded,   'Warteliste',       AppTheme.warning,   '/warteliste'),
          _SheetItem(Icons.warning_amber_rounded,'Mahnungen',        AppTheme.danger,    '/mahnungen',  badge: overdue),
          _SheetItem(Icons.category_rounded,     'Behandlungs-\narten', AppTheme.tertiary, '/behandlungsarten'),
          _SheetItem(Icons.search_rounded,       'Suche',            AppTheme.primary,   '/suche'),
          _SheetItem(Icons.home_work_rounded,    'Portal\nAdmin',    AppTheme.tertiary,  '/portal-admin'),
          _SheetItem(Icons.person_pin_rounded,   'Mein Profil',      cs.primary,         '/profil'),
        ]),
      ]),
    );
  }

  Widget _grid(BuildContext context, List<_SheetItem> items) {
    return GridView.builder(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 3,
        mainAxisSpacing: 12,
        crossAxisSpacing: 12,
        childAspectRatio: 0.95,
      ),
      itemCount: items.length,
      itemBuilder: (ctx, i) {
        final item = items[i];
        return InkWell(
          borderRadius: BorderRadius.circular(14),
          onTap: () => onTap(item.route),
          child: Container(
            decoration: BoxDecoration(
              color: item.color.withValues(alpha: 0.08),
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: item.color.withValues(alpha: 0.18)),
            ),
            child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
              Badge(
                isLabelVisible: (item.badge ?? 0) > 0,
                label: Text('${item.badge}', style: const TextStyle(fontSize: 9)),
                backgroundColor: AppTheme.danger,
                child: Container(
                  width: 44, height: 44,
                  decoration: BoxDecoration(
                    color: item.color.withValues(alpha: 0.12),
                    shape: BoxShape.circle,
                  ),
                  child: Icon(item.icon, color: item.color, size: 22),
                ),
              ),
              const SizedBox(height: 8),
              Text(item.label, style: TextStyle(fontSize: 11, fontWeight: FontWeight.w600, color: item.color),
                textAlign: TextAlign.center, maxLines: 2),
            ]),
          ),
        );
      },
    );
  }
}

class _SheetItem {
  final IconData icon;
  final String label;
  final Color color;
  final String route;
  final int? badge;
  const _SheetItem(this.icon, this.label, this.color, this.route, {this.badge});
}
