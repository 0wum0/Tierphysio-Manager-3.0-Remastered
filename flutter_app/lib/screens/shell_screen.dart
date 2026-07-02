import 'dart:async';
import 'dart:io';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:intl/intl.dart';
import '../services/auth_service.dart';
import '../widgets/feedback_fab.dart';
import '../services/api_service.dart';
import '../services/notification_service.dart';
import '../services/theme_service.dart';
import '../core/theme.dart';
import '../core/terminology.dart';
import '../core/navigation.dart';

const double _kSidebarCollapsed = 76.0;
const double _kSidebarExpanded = 248.0;

class ShellScreen extends StatefulWidget {
  final Widget child;
  const ShellScreen({super.key, required this.child});

  @override
  State<ShellScreen> createState() => _ShellScreenState();
}

class _ShellScreenState extends State<ShellScreen>
    with TickerProviderStateMixin {
  final _api = ApiService();
  int _unreadMessages = 0;
  int _overdueCount = 0;
  int _newIntakes = 0;
  int _birthdayCount = 0;
  List<Map<String, dynamic>> _pendingIntakes = [];
  late Timer _clockTimer;
  late Timer _connectivityTimer;
  DateTime _now = DateTime.now();
  bool _isOffline = false;

  // Bell shake animation
  late AnimationController _bellCtrl;
  late Animation<double> _bellAnim;
  int _lastBellCount = 0;

  // Sidebar expand/collapse
  bool _sidebarExpanded = true;
  late AnimationController _sidebarCtrl;
  late Animation<double> _sidebarAnim;

  @override
  void initState() {
    super.initState();
    _pollUnread();
    _loadOverdue();
    _pollNotifications();
    _clockTimer = Timer.periodic(const Duration(seconds: 1), (_) {
      if (mounted) setState(() => _now = DateTime.now());
    });
    NotificationService.requestPermission();
    NotificationService.onTap = (route) {
      if (mounted) context.go(route);
    };
    _checkConnectivity();
    _connectivityTimer = Timer.periodic(const Duration(seconds: 10), (_) {
      if (mounted) _checkConnectivity();
    });
    // Auto 2-Wege-Sync beim App-Start (still im Hintergrund)
    Future.delayed(const Duration(seconds: 3), () {
      if (mounted) _autoGoogleSync();
    });

    _sidebarCtrl = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 280),
      value: 1.0,
    );
    _sidebarAnim = CurvedAnimation(
      parent: _sidebarCtrl,
      curve: Curves.easeInOut,
    );

    _bellCtrl = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 600),
    );
    _bellAnim = TweenSequence<double>([
      TweenSequenceItem(tween: Tween(begin: 0.0, end: 0.18), weight: 1),
      TweenSequenceItem(tween: Tween(begin: 0.18, end: -0.18), weight: 2),
      TweenSequenceItem(tween: Tween(begin: -0.18, end: 0.12), weight: 2),
      TweenSequenceItem(tween: Tween(begin: 0.12, end: -0.08), weight: 2),
      TweenSequenceItem(tween: Tween(begin: -0.08, end: 0.0), weight: 1),
    ]).animate(CurvedAnimation(parent: _bellCtrl, curve: Curves.easeInOut));
  }

  @override
  void dispose() {
    _clockTimer.cancel();
    _connectivityTimer.cancel();
    _sidebarCtrl.dispose();
    _bellCtrl.dispose();
    super.dispose();
  }

  // ── Background polling / connectivity ──────────────────────────────────────

  Future<void> _checkConnectivity() async {
    try {
      final result = await InternetAddress.lookup('google.com')
          .timeout(const Duration(seconds: 4));
      if (mounted) {
        setState(() =>
            _isOffline = result.isEmpty || result.first.rawAddress.isEmpty);
      }
    } catch (_) {
      if (mounted) setState(() => _isOffline = true);
    }
  }

  Future<void> _autoGoogleSync() async {
    if (_isOffline) {
      Future.delayed(const Duration(minutes: 30), () {
        if (mounted) _autoGoogleSync();
      });
      return;
    }
    try {
      final status = await _api.googleSyncStatus();
      final connected = status['connected'] as bool? ?? false;
      final enabled = status['sync_enabled'] as bool? ?? false;
      if (!connected || !enabled) return;
      await Future.wait([
        _api.googleSyncPush().catchError((_) => <String, dynamic>{}),
        _api.googleSyncPull().catchError((_) => <String, dynamic>{}),
      ]);
    } catch (_) {}
    Future.delayed(const Duration(minutes: 30), () {
      if (mounted) _autoGoogleSync();
    });
  }

  Future<void> _pollNotifications() async {
    try {
      final results = await Future.wait([
        _api.dashboard(),
        _api.intakeInbox().catchError((_) => <String, dynamic>{}),
      ]);
      final d = results[0];
      final intakeData = results[1];
      final allIntakes = (intakeData['items'] as List? ?? []);
      final pending = allIntakes
          .where((e) {
            final s = (e as Map)['status'] as String? ?? 'neu';
            return s == 'neu' || s == 'in_bearbeitung' || s == 'pending';
          })
          .map((e) => Map<String, dynamic>.from(e as Map))
          .toList();

      if (mounted) {
        final newCount =
            pending.length + ((d['birthdays_today'] as List?)?.length ?? 0);
        final hadMore = newCount > _lastBellCount && _lastBellCount >= 0;
        setState(() {
          _newIntakes = pending.length;
          _pendingIntakes = pending;
          _birthdayCount = ((d['birthdays_today'] as List?)?.length) ?? 0;
        });
        if (hadMore && _lastBellCount >= 0) {
          _bellCtrl.forward(from: 0);
        }
        _lastBellCount = newCount;
      }
      NotificationService.checkNow(d, _api).ignore();
    } catch (_) {}
    Future.delayed(const Duration(minutes: 5), () {
      if (mounted) _pollNotifications();
    });
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

  // ── Navigation model ───────────────────────────────────────────────────────

  Terminology _term() =>
      Terminology(isTrainer: context.read<AuthService>().isTrainer);

  List<NavSection> _sections() => buildNavSections(
        term: _term(),
        isTrainer: context.read<AuthService>().isTrainer,
        badges: NavBadges(
          unreadMessages: _unreadMessages,
          overdueInvoices: _overdueCount,
          newIntakes: _newIntakes,
        ),
      );

  static bool _isActive(String route, String loc) =>
      loc == route || loc.startsWith('$route/');

  void _openItem(NavItem item) {
    if (item.comingSoon) {
      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(SnackBar(
          content: Text('„${item.label}" kommt in Kürze (Hundeschul-Modul).'),
        ));
      return;
    }
    context.go(item.route);
  }

  void _toggleSidebar() {
    setState(() => _sidebarExpanded = !_sidebarExpanded);
    if (_sidebarExpanded) {
      _sidebarCtrl.forward();
    } else {
      _sidebarCtrl.reverse();
    }
  }

  Widget _animatedContent(String location) {
    return AnimatedSwitcher(
      duration: const Duration(milliseconds: 260),
      switchInCurve: Curves.easeOutCubic,
      switchOutCurve: Curves.easeInCubic,
      transitionBuilder: (child, animation) {
        final slide = Tween<Offset>(
          begin: const Offset(0.02, 0),
          end: Offset.zero,
        ).animate(animation);
        return FadeTransition(
          opacity: animation,
          child: SlideTransition(position: slide, child: child),
        );
      },
      child: KeyedSubtree(
        key: ValueKey<String>(location),
        child: widget.child,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    context.watch<AuthService>();
    final width = MediaQuery.of(context).size.width;
    // Auto-collapse the sidebar on medium widths for more content space.
    if (width < 1000 && _sidebarExpanded) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted && _sidebarExpanded && MediaQuery.of(context).size.width < 1000) {
          setState(() => _sidebarExpanded = false);
          _sidebarCtrl.reverse();
        }
      });
    }
    if (width >= 600) return _buildWideLayout(context);
    return _buildNarrowLayout(context);
  }

  // ── Wide layout (tablet / desktop) ─────────────────────────────────────────

  Widget _buildWideLayout(BuildContext context) {
    final location = GoRouterState.of(context).matchedLocation;
    final cs = Theme.of(context).colorScheme;
    final sections = _sections();

    return Scaffold(
      floatingActionButton: const FeedbackFab(),
      body: Row(children: [
        AnimatedBuilder(
          animation: _sidebarAnim,
          builder: (context, _) {
            final w = _kSidebarCollapsed +
                (_kSidebarExpanded - _kSidebarCollapsed) * _sidebarAnim.value;
            final showLabels = _sidebarAnim.value > 0.5;
            return Container(
              width: w,
              color: cs.surface,
              child: Column(
                children: [
                  _sidebarHeader(context, showLabels),
                  Divider(height: 1, color: cs.outlineVariant),
                  Expanded(
                    child: ListView(
                      padding: const EdgeInsets.symmetric(
                          horizontal: 8, vertical: 8),
                      children: [
                        for (final section in sections) ...[
                          _sectionHeader(context, section.title, showLabels),
                          for (final item in section.items)
                            _SidebarNavTile(
                              item: item,
                              isSelected: _isActive(item.route, location),
                              showLabel: showLabels,
                              onTap: () => _openItem(item),
                            ),
                          const SizedBox(height: 6),
                        ],
                      ],
                    ),
                  ),
                  Divider(height: 1, color: cs.outlineVariant),
                  _SidebarNavTile(
                    item: const NavItem(
                      route: '__logout__',
                      icon: Icons.logout_rounded,
                      selectedIcon: Icons.logout_rounded,
                      label: 'Abmelden',
                      color: AppTheme.danger,
                    ),
                    isSelected: false,
                    showLabel: showLabels,
                    onTap: () => _confirmLogout(context),
                  ),
                  const SizedBox(height: 8),
                ],
              ),
            );
          },
        ),
        VerticalDivider(width: 1, color: Theme.of(context).dividerColor),
        Expanded(
          child: Column(
            children: [
              _buildTopBar(context),
              if (_isOffline) _offlineBanner(),
              Expanded(child: _animatedContent(location)),
            ],
          ),
        ),
      ]),
    );
  }

  Widget _sidebarHeader(BuildContext context, bool showLabels) {
    return SizedBox(
      height: 64,
      child: Row(
        children: [
          const SizedBox(width: 16),
          Container(
            width: 38,
            height: 38,
            decoration: BoxDecoration(
              color: AppTheme.primary.withValues(alpha: 0.12),
              borderRadius: BorderRadius.circular(11),
            ),
            child: Center(
              child: SvgPicture.asset(
                'assets/icons/paw.svg',
                width: 21,
                height: 21,
                colorFilter:
                    const ColorFilter.mode(AppTheme.primary, BlendMode.srcIn),
              ),
            ),
          ),
          if (showLabels) ...[
            const SizedBox(width: 10),
            Expanded(child: _wordmark(context, 16)),
          ],
          SizedBox(
            width: 40,
            child: IconButton(
              padding: EdgeInsets.zero,
              icon: AnimatedRotation(
                turns: _sidebarExpanded ? 0.5 : 0,
                duration: const Duration(milliseconds: 280),
                child: const Icon(Icons.chevron_right_rounded, size: 20),
              ),
              tooltip: _sidebarExpanded ? 'Einklappen' : 'Ausklappen',
              onPressed: _toggleSidebar,
            ),
          ),
        ],
      ),
    );
  }

  Widget _sectionHeader(BuildContext context, String title, bool showLabels) {
    final cs = Theme.of(context).colorScheme;
    if (!showLabels) {
      return const Padding(
        padding: EdgeInsets.symmetric(vertical: 4),
        child: Divider(height: 8, indent: 14, endIndent: 14),
      );
    }
    return Padding(
      padding: const EdgeInsets.fromLTRB(12, 8, 12, 6),
      child: Text(
        title.toUpperCase(),
        style: TextStyle(
          fontSize: 11,
          fontWeight: FontWeight.w700,
          letterSpacing: 0.6,
          color: cs.onSurfaceVariant.withValues(alpha: 0.75),
        ),
      ),
    );
  }

  Widget _wordmark(BuildContext context, double size) {
    final cs = Theme.of(context).colorScheme;
    return RichText(
      text: TextSpan(
        style: TextStyle(
          fontSize: size,
          fontWeight: FontWeight.w800,
          letterSpacing: -0.4,
          decoration: TextDecoration.none,
        ),
        children: [
          TextSpan(
            text: 'Thera',
            style: TextStyle(
                color: cs.onSurface, decoration: TextDecoration.none),
          ),
          TextSpan(
            text: 'Pano',
            style: TextStyle(
              decoration: TextDecoration.none,
              foreground: Paint()
                ..shader = const LinearGradient(
                  colors: [AppTheme.primary, AppTheme.secondary],
                ).createShader(Rect.fromLTWH(0, 0, size * 3, size)),
            ),
          ),
        ],
      ),
    );
  }

  Widget _offlineBanner() {
    return Material(
      color: Colors.orange.shade700,
      child: const SafeArea(
        top: false,
        bottom: false,
        child: Padding(
          padding: EdgeInsets.symmetric(horizontal: 16, vertical: 6),
          child: Row(children: [
            Icon(Icons.wifi_off_rounded, color: Colors.white, size: 16),
            SizedBox(width: 8),
            Text('Keine Internetverbindung',
                style: TextStyle(
                    color: Colors.white,
                    fontSize: 12,
                    fontWeight: FontWeight.w600)),
          ]),
        ),
      ),
    );
  }

  Widget _buildTopBar(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    final timeStr = DateFormat('HH:mm', 'de_DE').format(_now);
    return Container(
      height: 56,
      decoration: BoxDecoration(
        color: cs.surface,
        border: Border(bottom: BorderSide(color: cs.outlineVariant)),
      ),
      padding: const EdgeInsets.symmetric(horizontal: 12),
      child: Row(children: [
        InkWell(
          borderRadius: BorderRadius.circular(10),
          onTap: () => context.push('/suche'),
          child: Container(
            height: 38,
            width: 240,
            decoration: BoxDecoration(
              color: cs.surfaceContainerHighest.withValues(alpha: 0.5),
              borderRadius: BorderRadius.circular(10),
            ),
            padding: const EdgeInsets.symmetric(horizontal: 12),
            child: Row(children: [
              Icon(Icons.search_rounded, size: 18, color: cs.onSurfaceVariant),
              const SizedBox(width: 8),
              Text(_term().searchHint(),
                  style:
                      TextStyle(fontSize: 13, color: cs.onSurfaceVariant)),
            ]),
          ),
        ),
        const Spacer(),
        Text(timeStr,
            style: TextStyle(
              fontSize: 15,
              fontWeight: FontWeight.w600,
              color: cs.onSurfaceVariant,
              fontFeatures: const [FontFeature.tabularFigures()],
            )),
        const SizedBox(width: 4),
        _themeToggle(),
        _bell(context),
      ]),
    );
  }

  // ── Narrow layout (phone) ──────────────────────────────────────────────────

  Widget _buildNarrowLayout(BuildContext context) {
    final location = GoRouterState.of(context).matchedLocation;
    final sections = _sections();
    final primary = primaryNavItems(sections);
    final primaryIdx =
        primary.indexWhere((i) => _isActive(i.route, location));
    final navIdx = primaryIdx >= 0 ? primaryIdx : primary.length;

    return Scaffold(
      appBar: _buildAppBar(context),
      floatingActionButton: const FeedbackFab(),
      body: Column(children: [
        if (_isOffline) _offlineBanner(),
        Expanded(child: _animatedContent(location)),
      ]),
      bottomNavigationBar: NavigationBar(
        selectedIndex: navIdx,
        onDestinationSelected: (idx) {
          if (idx == primary.length) {
            _openMoreSheet(sections);
          } else {
            _openItem(primary[idx]);
            if (primary[idx].route == '/nachrichten') {
              Future.delayed(const Duration(milliseconds: 800), () {
                if (mounted) _pollUnread();
              });
            }
          }
        },
        destinations: [
          for (final item in primary)
            NavigationDestination(
              icon: _navIcon(item, selected: false),
              selectedIcon: _navIcon(item, selected: true),
              label: item.label,
            ),
          NavigationDestination(
            icon: Badge(
              isLabelVisible: _overdueCount > 0,
              label:
                  Text('$_overdueCount', style: const TextStyle(fontSize: 9)),
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

  Widget _navIcon(NavItem item, {required bool selected}) {
    final base = Icon(selected ? item.selectedIcon : item.icon);
    if (item.badge <= 0) return base;
    return Badge(
      label: Text('${item.badge}',
          style: const TextStyle(fontSize: 9, fontWeight: FontWeight.w700)),
      backgroundColor: AppTheme.danger,
      child: base,
    );
  }

  PreferredSizeWidget _buildAppBar(BuildContext context) {
    final timeStr = DateFormat('HH:mm', 'de_DE').format(_now);
    return AppBar(
      automaticallyImplyLeading: false,
      titleSpacing: 16,
      title: Row(
        children: [
          SvgPicture.asset('assets/icons/paw.svg',
              width: 22,
              height: 22,
              colorFilter: const ColorFilter.mode(AppTheme.primary, BlendMode.srcIn)),
          const SizedBox(width: 8),
          _wordmark(context, 18),
          Expanded(
            child: Center(
              child: Text(timeStr,
                  style: TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.w600,
                    color: Theme.of(context).colorScheme.onSurfaceVariant,
                    fontFeatures: const [FontFeature.tabularFigures()],
                  )),
            ),
          ),
        ],
      ),
      actions: [
        IconButton(
          icon: const Icon(Icons.search_rounded),
          tooltip: 'Suche',
          onPressed: () => context.push('/suche'),
        ),
        _themeToggle(),
        _bell(context),
        const SizedBox(width: 4),
      ],
    );
  }

  Widget _themeToggle() {
    return Consumer<ThemeService>(
      builder: (_, ts, __) => IconButton(
        icon: Icon(switch (ts.mode) {
          ThemeMode.light => Icons.light_mode_rounded,
          ThemeMode.dark => Icons.dark_mode_rounded,
          ThemeMode.system => Icons.brightness_auto_rounded,
        }),
        tooltip: 'Theme wechseln',
        onPressed: () {
          final next = switch (ts.mode) {
            ThemeMode.system => ThemeMode.light,
            ThemeMode.light => ThemeMode.dark,
            ThemeMode.dark => ThemeMode.system,
          };
          ts.setMode(next);
        },
      ),
    );
  }

  Widget _bell(BuildContext context) {
    final totalBadge = _newIntakes + _birthdayCount;
    return AnimatedBuilder(
      animation: _bellAnim,
      builder: (context, child) => Transform.rotate(
        angle: _bellAnim.value,
        child: child,
      ),
      child: Stack(
        alignment: Alignment.center,
        children: [
          IconButton(
            icon: Icon(totalBadge > 0
                ? Icons.notifications_rounded
                : Icons.notifications_outlined),
            tooltip: 'Benachrichtigungen',
            onPressed: () => _showNotificationPanel(context),
          ),
          if (totalBadge > 0)
            Positioned(
              top: 6,
              right: 6,
              child: Container(
                padding:
                    const EdgeInsets.symmetric(horizontal: 4, vertical: 2),
                decoration: BoxDecoration(
                  color: AppTheme.danger,
                  borderRadius: BorderRadius.circular(10),
                  border: Border.all(
                      color: Theme.of(context).colorScheme.surface,
                      width: 1.5),
                ),
                constraints:
                    const BoxConstraints(minWidth: 16, minHeight: 16),
                child: Text('$totalBadge',
                    style: const TextStyle(
                        color: Colors.white,
                        fontSize: 9,
                        fontWeight: FontWeight.w800),
                    textAlign: TextAlign.center),
              ),
            ),
        ],
      ),
    );
  }

  void _showNotificationPanel(BuildContext context) {
    showModalBottomSheet(
      context: context,
      backgroundColor: Theme.of(context).colorScheme.surface,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      isScrollControlled: true,
      builder: (ctx) => _NotificationSheet(
        newIntakes: _newIntakes,
        pendingIntakes: _pendingIntakes,
        birthdayCount: _birthdayCount,
        onTap: (route) {
          Navigator.pop(ctx);
          context.push(route);
        },
      ),
    );
  }

  // ── Phone "Mehr" sheet — grouped, not a cramped grid ───────────────────────

  void _openMoreSheet(List<NavSection> sections) {
    final cs = Theme.of(context).colorScheme;
    // Only sections/items that aren't already reachable via the bottom bar.
    final groups = [
      for (final s in sections)
        NavSection(s.title, [for (final i in s.items) if (!i.primary) i]),
    ].where((s) => s.items.isNotEmpty).toList();

    showModalBottomSheet(
      context: context,
      backgroundColor: cs.surface,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (ctx) {
        return SafeArea(
          top: false,
          child: ConstrainedBox(
            constraints: BoxConstraints(
              maxHeight: MediaQuery.of(ctx).size.height * 0.85,
            ),
            child: SingleChildScrollView(
              padding: const EdgeInsets.fromLTRB(12, 4, 12, 16),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  for (final section in groups) ...[
                    Padding(
                      padding: const EdgeInsets.fromLTRB(8, 12, 8, 8),
                      child: Text(
                        section.title.toUpperCase(),
                        style: TextStyle(
                          fontSize: 11,
                          fontWeight: FontWeight.w700,
                          letterSpacing: 0.6,
                          color: cs.onSurfaceVariant.withValues(alpha: 0.75),
                        ),
                      ),
                    ),
                    GridView.count(
                      shrinkWrap: true,
                      crossAxisCount: 4,
                      mainAxisSpacing: 8,
                      crossAxisSpacing: 8,
                      childAspectRatio: 0.82,
                      physics: const NeverScrollableScrollPhysics(),
                      children: [
                        for (final item in section.items)
                          _MoreGridTile(
                            item: item,
                            onTap: () {
                              Navigator.pop(ctx);
                              _openItem(item);
                            },
                          ),
                      ],
                    ),
                  ],
                ],
              ),
            ),
          ),
        );
      },
    );
  }

  Future<void> _confirmLogout(BuildContext context) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (_) => AlertDialog(
        title: const Text('Abmelden'),
        content: const Text('Möchten Sie sich wirklich abmelden?'),
        actions: [
          TextButton(
              onPressed: () => Navigator.pop(context, false),
              child: const Text('Abbrechen')),
          FilledButton(
              onPressed: () => Navigator.pop(context, true),
              child: const Text('Abmelden')),
        ],
      ),
    );
    if (ok == true && context.mounted) {
      await context.read<AuthService>().logout();
    }
  }
}

// ── Sidebar tile ─────────────────────────────────────────────────────────────

class _SidebarNavTile extends StatelessWidget {
  final NavItem item;
  final bool isSelected;
  final bool showLabel;
  final VoidCallback onTap;

  const _SidebarNavTile({
    required this.item,
    required this.isSelected,
    required this.showLabel,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    final accent = item.color;
    final disabled = item.comingSoon;
    final fg = disabled
        ? cs.onSurfaceVariant.withValues(alpha: 0.5)
        : (isSelected ? accent : cs.onSurfaceVariant);

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 2),
      child: Tooltip(
        message: showLabel ? '' : item.label,
        preferBelow: false,
        child: InkWell(
          borderRadius: BorderRadius.circular(12),
          onTap: onTap,
          child: AnimatedContainer(
            duration: const Duration(milliseconds: 200),
            decoration: BoxDecoration(
              color: isSelected
                  ? accent.withValues(alpha: 0.12)
                  : Colors.transparent,
              borderRadius: BorderRadius.circular(12),
            ),
            padding: EdgeInsets.symmetric(
              horizontal: showLabel ? 12 : 0,
              vertical: 11,
            ),
            child: Row(
              mainAxisAlignment: showLabel
                  ? MainAxisAlignment.start
                  : MainAxisAlignment.center,
              children: [
                Badge(
                  isLabelVisible: item.badge > 0,
                  label: Text('${item.badge}',
                      style: const TextStyle(
                          fontSize: 9, fontWeight: FontWeight.w700)),
                  backgroundColor: AppTheme.danger,
                  child: Icon(
                    isSelected ? item.selectedIcon : item.icon,
                    color: fg,
                    size: 22,
                  ),
                ),
                if (showLabel) ...[
                  const SizedBox(width: 12),
                  Expanded(
                    child: Text(
                      item.label,
                      style: TextStyle(
                        fontSize: 13.5,
                        fontWeight:
                            isSelected ? FontWeight.w700 : FontWeight.w500,
                        color: disabled
                            ? cs.onSurfaceVariant.withValues(alpha: 0.5)
                            : (isSelected ? accent : cs.onSurface),
                      ),
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                  if (disabled)
                    _soonChip(cs),
                ],
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _soonChip(ColorScheme cs) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
        decoration: BoxDecoration(
          color: cs.surfaceContainerHighest,
          borderRadius: BorderRadius.circular(8),
        ),
        child: Text('bald',
            style: TextStyle(
                fontSize: 9,
                fontWeight: FontWeight.w700,
                color: cs.onSurfaceVariant)),
      );
}

// ── Phone "Mehr" grid tile ───────────────────────────────────────────────────

class _MoreGridTile extends StatelessWidget {
  final NavItem item;
  final VoidCallback onTap;
  const _MoreGridTile({required this.item, required this.onTap});

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final disabled = item.comingSoon;
    return GestureDetector(
      onTap: onTap,
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Stack(
            clipBehavior: Clip.none,
            children: [
              Opacity(
                opacity: disabled ? 0.5 : 1,
                child: Container(
                  width: 56,
                  height: 56,
                  decoration: BoxDecoration(
                    gradient: LinearGradient(
                      colors: [item.color, item.color.withValues(alpha: 0.72)],
                      begin: Alignment.topLeft,
                      end: Alignment.bottomRight,
                    ),
                    borderRadius: BorderRadius.circular(16),
                    boxShadow: [
                      BoxShadow(
                        color:
                            item.color.withValues(alpha: isDark ? 0.25 : 0.32),
                        blurRadius: 10,
                        offset: const Offset(0, 4),
                      ),
                    ],
                  ),
                  child: Icon(item.selectedIcon, color: Colors.white, size: 26),
                ),
              ),
              if (item.badge > 0)
                Positioned(
                  top: -5,
                  right: -5,
                  child: Container(
                    padding:
                        const EdgeInsets.symmetric(horizontal: 5, vertical: 2),
                    decoration: BoxDecoration(
                      color: AppTheme.danger,
                      borderRadius: BorderRadius.circular(10),
                      border: Border.all(
                          color:
                              isDark ? const Color(0xFF1A1D27) : Colors.white,
                          width: 1.5),
                    ),
                    child: Text('${item.badge}',
                        style: const TextStyle(
                            color: Colors.white,
                            fontSize: 9,
                            fontWeight: FontWeight.w800)),
                  ),
                ),
            ],
          ),
          const SizedBox(height: 7),
          Text(
            item.label,
            textAlign: TextAlign.center,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: TextStyle(
              fontSize: 10.5,
              fontWeight: FontWeight.w600,
              color: disabled
                  ? cs.onSurfaceVariant.withValues(alpha: 0.6)
                  : cs.onSurface,
              height: 1.2,
            ),
          ),
        ],
      ),
    );
  }
}

// ── Notification bottom sheet ──────────────────────────────────────────────────

class _NotificationSheet extends StatefulWidget {
  final int newIntakes;
  final List<Map<String, dynamic>> pendingIntakes;
  final int birthdayCount;
  final void Function(String route) onTap;

  const _NotificationSheet({
    required this.newIntakes,
    required this.pendingIntakes,
    required this.birthdayCount,
    required this.onTap,
  });

  @override
  State<_NotificationSheet> createState() => _NotificationSheetState();
}

class _NotificationSheetState extends State<_NotificationSheet>
    with SingleTickerProviderStateMixin {
  late AnimationController _entryCtrl;
  late Animation<double> _fadeAnim;
  late Animation<Offset> _slideAnim;

  @override
  void initState() {
    super.initState();
    _entryCtrl = AnimationController(
        vsync: this, duration: const Duration(milliseconds: 350));
    _fadeAnim = CurvedAnimation(parent: _entryCtrl, curve: Curves.easeOut);
    _slideAnim = Tween<Offset>(begin: const Offset(0, 0.15), end: Offset.zero)
        .animate(
            CurvedAnimation(parent: _entryCtrl, curve: Curves.easeOutCubic));
    _entryCtrl.forward();
  }

  @override
  void dispose() {
    _entryCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final hasAny = widget.newIntakes > 0 || widget.birthdayCount > 0;
    return FadeTransition(
      opacity: _fadeAnim,
      child: SlideTransition(
        position: _slideAnim,
        child: SafeArea(
          top: false,
          child: ConstrainedBox(
            constraints: BoxConstraints(
              maxHeight: MediaQuery.of(context).size.height * 0.75,
            ),
            child: SingleChildScrollView(
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 16),
              child: Column(mainAxisSize: MainAxisSize.min, children: [
                Container(
                    width: 40,
                    height: 4,
                    margin: const EdgeInsets.only(bottom: 16),
                    decoration: BoxDecoration(
                        color: Colors.grey.shade300,
                        borderRadius: BorderRadius.circular(2))),
                Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text('Benachrichtigungen',
                          style: Theme.of(context)
                              .textTheme
                              .titleMedium
                              ?.copyWith(fontWeight: FontWeight.w700)),
                      if (hasAny)
                        Container(
                          padding: const EdgeInsets.symmetric(
                              horizontal: 8, vertical: 3),
                          decoration: BoxDecoration(
                            color: AppTheme.danger.withValues(alpha: 0.12),
                            borderRadius: BorderRadius.circular(12),
                          ),
                          child: Text(
                              '${widget.newIntakes + widget.birthdayCount} neu',
                              style: const TextStyle(
                                  color: AppTheme.danger,
                                  fontSize: 11,
                                  fontWeight: FontWeight.w700)),
                        ),
                    ]),
                const SizedBox(height: 16),
                if (!hasAny)
                  Padding(
                    padding: const EdgeInsets.symmetric(vertical: 24),
                    child: Column(children: [
                      Icon(Icons.notifications_none_rounded,
                          size: 48,
                          color:
                              Theme.of(context).colorScheme.onSurfaceVariant),
                      const SizedBox(height: 8),
                      Text('Keine neuen Benachrichtigungen',
                          style: Theme.of(context)
                              .textTheme
                              .bodyMedium
                              ?.copyWith(
                                  color: Theme.of(context)
                                      .colorScheme
                                      .onSurfaceVariant)),
                    ]),
                  ),
                ...widget.pendingIntakes.map((intake) {
                  final ownerFirst =
                      intake['owner_first_name'] as String? ?? '';
                  final ownerLast = intake['owner_last_name'] as String? ?? '';
                  final ownerName = '$ownerFirst $ownerLast'.trim();
                  final petName = intake['patient_name'] as String? ?? '';
                  final species = intake['patient_species'] as String? ?? '';
                  final subtitle = [
                    if (petName.isNotEmpty) petName,
                    if (species.isNotEmpty) species,
                  ].join(' · ');
                  return _NotifTile(
                    icon: Icons.assignment_ind_rounded,
                    color: AppTheme.primary,
                    title: ownerName.isNotEmpty ? ownerName : 'Neue Anmeldung',
                    subtitle: subtitle.isNotEmpty
                        ? subtitle
                        : 'Zur Bestätigung antippen',
                    onTap: () => widget.onTap('/anmeldungen/${intake['id']}'),
                  );
                }),
                if (widget.birthdayCount > 0)
                  _NotifTile(
                    icon: Icons.cake_rounded,
                    color: AppTheme.secondary,
                    title:
                        '${widget.birthdayCount} Geburtstag${widget.birthdayCount == 1 ? '' : 'e'} heute!',
                    subtitle:
                        'Tier${widget.birthdayCount == 1 ? '' : 'e'} haben heute Geburtstag',
                    onTap: () => widget.onTap('/patienten'),
                  ),
              ]),
            ),
          ),
        ),
      ),
    );
  }
}

class _NotifTile extends StatelessWidget {
  final IconData icon;
  final Color color;
  final String title, subtitle;
  final VoidCallback onTap;
  const _NotifTile(
      {required this.icon,
      required this.color,
      required this.title,
      required this.subtitle,
      required this.onTap});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Material(
        color: color.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(14),
        child: InkWell(
          borderRadius: BorderRadius.circular(14),
          onTap: onTap,
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
            child: Row(children: [
              Container(
                width: 42,
                height: 42,
                decoration: BoxDecoration(
                    color: color.withValues(alpha: 0.15),
                    shape: BoxShape.circle),
                child: Icon(icon, color: color, size: 22),
              ),
              const SizedBox(width: 12),
              Expanded(
                  child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                    Text(title,
                        style: TextStyle(
                            fontWeight: FontWeight.w700,
                            fontSize: 14,
                            color: color)),
                    Text(subtitle,
                        style: Theme.of(context).textTheme.bodySmall),
                  ])),
              Icon(Icons.chevron_right_rounded, color: color, size: 18),
            ]),
          ),
        ),
      ),
    );
  }
}
