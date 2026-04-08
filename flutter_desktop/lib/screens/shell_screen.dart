import 'dart:async';
import 'dart:io';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:intl/intl.dart';
import '../services/auth_service.dart';
import '../services/api_service.dart';
import '../services/notification_service.dart';
import '../services/theme_service.dart';
import '../core/theme.dart';

const double _kSidebarCollapsed = 64.0;
const double _kSidebarExpanded  = 240.0;

// ── Desktop ShellScreen ────────────────────────────────────────────────────────
// Immer die Sidebar (kein Bottom-Nav). Keyboard-Shortcuts für Navigation.
// ──────────────────────────────────────────────────────────────────────────────

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
  int _overdueCount   = 0;
  int _newIntakes     = 0;
  int _birthdayCount  = 0;
  List<Map<String, dynamic>> _pendingIntakes = [];
  late Timer _clockTimer;
  late Timer _connectivityTimer;
  DateTime _now = DateTime.now();
  bool _isOffline = false;

  // Bell shake animation
  late AnimationController _bellCtrl;
  late Animation<double> _bellAnim;
  int _lastBellCount = 0;

  // Sidebar animation
  bool _sidebarExpanded = true;
  late AnimationController _sidebarCtrl;
  late Animation<double> _sidebarAnim;

  // All sidebar routes (desktop shows all in the sidebar)
  static const _navRoutes = [
    '/dashboard',
    '/patienten',
    '/tierhalter',
    '/rechnungen',
    '/kalender',
    '/nachrichten',
    '/warteliste',
    '/mahnungen',
    '/anmeldungen',
    '/einladungen',
    '/befunde',
    '/hausaufgaben',
    '/behandlungsarten',
    '/portal-admin',
  ];

  @override
  void initState() {
    super.initState();
    _pollUnread();
    _loadOverdue();
    _pollNotifications();
    _clockTimer = Timer.periodic(
      const Duration(seconds: 1), (_) {
        if (mounted) setState(() => _now = DateTime.now());
      },
    );

    NotificationService.onTap = (route) {
      if (mounted) context.go(route);
    };
    _checkConnectivity();
    _connectivityTimer = Timer.periodic(const Duration(seconds: 10), (_) {
      if (mounted) _checkConnectivity();
    });

    // Auto 2-Wege-Sync beim App-Start
    Future.delayed(const Duration(seconds: 3), () {
      if (mounted) _autoGoogleSync();
    });

    _sidebarCtrl = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 260),
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
      TweenSequenceItem(tween: Tween(begin: 0.0,   end:  0.18), weight: 1),
      TweenSequenceItem(tween: Tween(begin: 0.18,  end: -0.18), weight: 2),
      TweenSequenceItem(tween: Tween(begin: -0.18, end:  0.12), weight: 2),
      TweenSequenceItem(tween: Tween(begin: 0.12,  end: -0.08), weight: 2),
      TweenSequenceItem(tween: Tween(begin: -0.08, end:  0.0),  weight: 1),
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

  // ── Connectivity ─────────────────────────────────────────────────────────────

  Future<void> _checkConnectivity() async {
    try {
      final result = await InternetAddress.lookup('google.com')
          .timeout(const Duration(seconds: 4));
      if (mounted) {
        setState(() => _isOffline =
            result.isEmpty || result.first.rawAddress.isEmpty);
      }
    } catch (_) {
      if (mounted) setState(() => _isOffline = true);
    }
  }

  // ── Google Sync ───────────────────────────────────────────────────────────────

  Future<void> _autoGoogleSync() async {
    if (_isOffline) {
      Future.delayed(const Duration(minutes: 30), () {
        if (mounted) _autoGoogleSync();
      });
      return;
    }
    try {
      final status  = await _api.googleSyncStatus();
      final connected = status['connected'] as bool? ?? false;
      final enabled   = status['sync_enabled'] as bool? ?? false;
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

  // ── Polling ───────────────────────────────────────────────────────────────────

  Future<void> _pollNotifications() async {
    try {
      final results = await Future.wait([
        _api.dashboard(),
        _api.intakeInbox().catchError((_) => <String, dynamic>{}),
      ]);
      final d = results[0] as Map<String, dynamic>;
      final intakeData = results[1] as Map<String, dynamic>;
      final allIntakes = (intakeData['items'] as List? ?? []);
      final pending = allIntakes.where((e) {
        final s = (e as Map)['status'] as String? ?? 'neu';
        return s == 'neu' || s == 'in_bearbeitung' || s == 'pending';
      }).map((e) => Map<String, dynamic>.from(e as Map)).toList();

      if (mounted) {
        final newCount = pending.length +
            ((d['birthdays_today'] as List?)?.length ?? 0);
        final hadMore = newCount > _lastBellCount && _lastBellCount >= 0;
        setState(() {
          _newIntakes     = pending.length;
          _pendingIntakes = pending;
          _birthdayCount  = ((d['birthdays_today'] as List?)?.length) ?? 0;
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

  // ── Sidebar toggle ────────────────────────────────────────────────────────────

  void _toggleSidebar() {
    setState(() => _sidebarExpanded = !_sidebarExpanded);
    if (_sidebarExpanded) {
      _sidebarCtrl.forward();
    } else {
      _sidebarCtrl.reverse();
    }
  }

  void _navigate(int idx) {
    if (idx < _navRoutes.length) {
      context.go(_navRoutes[idx]);
      if (_navRoutes[idx] == '/nachrichten') {
        Future.delayed(const Duration(milliseconds: 800), () {
          if (mounted) _pollUnread();
        });
      }
    }
  }

  // ── Logout ────────────────────────────────────────────────────────────────────

  Future<void> _confirmLogout(BuildContext context) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (_) => AlertDialog(
        title: const Text('Abmelden'),
        content: const Text('Möchten Sie sich wirklich abmelden?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Abbrechen'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Abmelden'),
          ),
        ],
      ),
    );
    if (ok == true && context.mounted) {
      await context.read<AuthService>().logout();
    }
  }

  // ── Notification dialog (desktop: kein BottomSheet) ───────────────────────────

  void _showNotificationPanel(BuildContext context) {
    showDialog(
      context: context,
      builder: (ctx) => Dialog(
        insetPadding: const EdgeInsets.all(0),
        alignment: Alignment.topRight,
        child: Container(
          width: 380,
          constraints: const BoxConstraints(maxHeight: 520),
          child: _NotificationPanel(
            newIntakes:     _newIntakes,
            pendingIntakes: _pendingIntakes,
            birthdayCount:  _birthdayCount,
            onTap: (route) {
              Navigator.pop(ctx);
              context.push(route);
            },
          ),
        ),
      ),
    );
  }

  // ── Build ─────────────────────────────────────────────────────────────────────

  @override
  Widget build(BuildContext context) {
    // Keyboard shortcuts: Ctrl+1..9 → Navigation, Ctrl+F → Suche
    return CallbackShortcuts(
      bindings: {
        const SingleActivator(LogicalKeyboardKey.digit1, control: true):
            () => _navigate(0),
        const SingleActivator(LogicalKeyboardKey.digit2, control: true):
            () => _navigate(1),
        const SingleActivator(LogicalKeyboardKey.digit3, control: true):
            () => _navigate(2),
        const SingleActivator(LogicalKeyboardKey.digit4, control: true):
            () => _navigate(3),
        const SingleActivator(LogicalKeyboardKey.digit5, control: true):
            () => _navigate(4),
        const SingleActivator(LogicalKeyboardKey.digit6, control: true):
            () => _navigate(5),
        const SingleActivator(LogicalKeyboardKey.digit7, control: true):
            () => _navigate(6),
        const SingleActivator(LogicalKeyboardKey.digit8, control: true):
            () => _navigate(7),
        const SingleActivator(LogicalKeyboardKey.digit9, control: true):
            () => _navigate(8),
        const SingleActivator(LogicalKeyboardKey.keyF, control: true):
            () => context.push('/suche'),
        const SingleActivator(LogicalKeyboardKey.keyK, control: true):
            () => context.push('/suche'),
        const SingleActivator(LogicalKeyboardKey.comma, control: true):
            () => context.push('/einstellungen'),
        const SingleActivator(LogicalKeyboardKey.backslash, control: true):
            _toggleSidebar,
      },
      child: Focus(
        autofocus: true,
        child: _buildLayout(context),
      ),
    );
  }

  Widget _buildLayout(BuildContext context) {
    final location = GoRouterState.of(context).matchedLocation;
    final navIdx   = _navRoutes.indexWhere((r) => location.startsWith(r));
    final selected = navIdx >= 0 ? navIdx : 0;
    final cs = Theme.of(context).colorScheme;

    final destinations = [
      _SidebarDest(Icons.dashboard_outlined,       Icons.dashboard_rounded,      'Dashboard',        shortcut: '⌃1'),
      _SidebarDest(Icons.pets_outlined,            Icons.pets_rounded,           'Patienten',        shortcut: '⌃2'),
      _SidebarDest(Icons.person_outline_rounded,   Icons.person_rounded,         'Tierhalter',       shortcut: '⌃3'),
      _SidebarDest(Icons.receipt_long_outlined,    Icons.receipt_long_rounded,   'Rechnungen',       shortcut: '⌃4'),
      _SidebarDest(Icons.calendar_month_outlined,  Icons.calendar_month_rounded, 'Kalender',         shortcut: '⌃5'),
      _SidebarDest(Icons.chat_outlined,            Icons.chat_rounded,           'Nachrichten',      badge: _unreadMessages, shortcut: '⌃6'),
      _SidebarDest(Icons.people_alt_outlined,      Icons.people_alt_rounded,     'Warteliste',       shortcut: '⌃7'),
      _SidebarDest(Icons.warning_amber_outlined,   Icons.warning_amber_rounded,  'Mahnungen',        badge: _overdueCount,  shortcut: '⌃8'),
      _SidebarDest(Icons.assignment_ind_outlined,  Icons.assignment_ind_rounded, 'Anmeldungen',      badge: _newIntakes,    shortcut: '⌃9'),
      _SidebarDest(Icons.send_outlined,            Icons.send_rounded,           'Einladungen'),
      _SidebarDest(Icons.description_outlined,     Icons.description_rounded,    'Befundbögen'),
      _SidebarDest(Icons.assignment_outlined,      Icons.assignment_rounded,     'Hausaufgaben'),
      _SidebarDest(Icons.category_outlined,        Icons.category_rounded,       'Behandlungsarten'),
      _SidebarDest(Icons.home_work_outlined,       Icons.home_work_rounded,      'Portal Admin'),
    ];

    return Scaffold(
      body: Row(children: [
        // ── Sidebar ──────────────────────────────────────────────────────────
        AnimatedBuilder(
          animation: _sidebarAnim,
          builder: (context, _) {
            final w = _kSidebarCollapsed +
                (_kSidebarExpanded - _kSidebarCollapsed) * _sidebarAnim.value;
            final showLabels = _sidebarAnim.value > 0.45;
            return Container(
              width: w,
              decoration: BoxDecoration(
                color: cs.brightness == Brightness.dark
                    ? const Color(0xFF13151E)
                    : const Color(0xFFF0F4FF),
                border: Border(
                  right: BorderSide(color: cs.outlineVariant, width: 1),
                ),
              ),
              child: Column(children: [
                // ── Logo header ──
                SizedBox(
                  height: 56,
                  child: Row(children: [
                    const SizedBox(width: 12),
                    Container(
                      width: 36, height: 36,
                      decoration: BoxDecoration(
                        color: AppTheme.primary.withValues(alpha: 0.14),
                        borderRadius: BorderRadius.circular(10),
                      ),
                      child: Center(
                        child: SvgPicture.asset(
                          'assets/icons/paw.svg',
                          width: 20, height: 20,
                          colorFilter: ColorFilter.mode(
                              AppTheme.primary, BlendMode.srcIn),
                        ),
                      ),
                    ),
                    if (showLabels) ...[
                      const SizedBox(width: 10),
                      Expanded(
                        child: RichText(
                          overflow: TextOverflow.clip,
                          text: TextSpan(
                            style: const TextStyle(
                              fontSize: 16, fontWeight: FontWeight.w800,
                              letterSpacing: -0.4,
                              decoration: TextDecoration.none,
                            ),
                            children: [
                              TextSpan(
                                text: 'Thera',
                                style: TextStyle(
                                  color: cs.onSurface,
                                  decoration: TextDecoration.none,
                                ),
                              ),
                              TextSpan(
                                text: 'Pano',
                                style: TextStyle(
                                  decoration: TextDecoration.none,
                                  foreground: Paint()
                                    ..shader = const LinearGradient(
                                      colors: [AppTheme.primary, AppTheme.secondary],
                                    ).createShader(const Rect.fromLTWH(0, 0, 50, 18)),
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                    ],
                    SizedBox(
                      width: 40,
                      child: IconButton(
                        padding: EdgeInsets.zero,
                        icon: AnimatedRotation(
                          turns: _sidebarExpanded ? 0.5 : 0,
                          duration: const Duration(milliseconds: 260),
                          child: const Icon(Icons.chevron_right_rounded, size: 18),
                        ),
                        tooltip: _sidebarExpanded
                            ? 'Einklappen  ⌃\\'
                            : 'Ausklappen  ⌃\\',
                        onPressed: _toggleSidebar,
                      ),
                    ),
                  ]),
                ),
                Divider(height: 1, color: cs.outlineVariant),

                // ── Suche ──
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 6),
                  child: showLabels
                      ? Tooltip(
                          message: 'Suche  ⌃F',
                          child: InkWell(
                            borderRadius: BorderRadius.circular(10),
                            onTap: () => context.push('/suche'),
                            child: Container(
                              height: 34,
                              decoration: BoxDecoration(
                                color: cs.surfaceContainerHighest
                                    .withValues(alpha: 0.5),
                                borderRadius: BorderRadius.circular(10),
                              ),
                              padding:
                                  const EdgeInsets.symmetric(horizontal: 10),
                              child: Row(children: [
                                Icon(Icons.search_rounded,
                                    size: 16,
                                    color: cs.onSurfaceVariant),
                                const SizedBox(width: 8),
                                Text('Suche',
                                    style: TextStyle(
                                        fontSize: 13,
                                        color: cs.onSurfaceVariant)),
                                const Spacer(),
                                Text('⌃F',
                                    style: TextStyle(
                                        fontSize: 10,
                                        color: cs.onSurfaceVariant
                                            .withValues(alpha: 0.6))),
                              ]),
                            ),
                          ),
                        )
                      : Tooltip(
                          message: 'Suche  ⌃F',
                          child: IconButton(
                            icon: const Icon(Icons.search_rounded, size: 18),
                            onPressed: () => context.push('/suche'),
                          ),
                        ),
                ),

                // ── Nav items ──
                Expanded(
                  child: ListView.builder(
                    padding:
                        const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                    itemCount: destinations.length,
                    itemBuilder: (ctx, i) {
                      return _SidebarTile(
                        dest: destinations[i],
                        isSelected: i == selected,
                        showLabel: showLabels,
                        onTap: () => _navigate(i),
                      );
                    },
                  ),
                ),

                Divider(height: 1, color: cs.outlineVariant),

                // ── Footer ──
                Padding(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 6, vertical: 8),
                  child: Column(mainAxisSize: MainAxisSize.min, children: [
                    _SidebarTile(
                      dest: _SidebarDest(
                          Icons.person_outline_rounded,
                          Icons.person_rounded,
                          'Profil'),
                      isSelected: location.startsWith('/profil'),
                      showLabel: showLabels,
                      onTap: () => context.push('/profil'),
                    ),
                    _SidebarTile(
                      dest: _SidebarDest(
                        Icons.settings_outlined,
                        Icons.settings_rounded,
                        'Einstellungen',
                        color: AppTheme.tertiary,
                        shortcut: '⌃,',
                      ),
                      isSelected: location.startsWith('/einstellungen'),
                      showLabel: showLabels,
                      onTap: () => context.push('/einstellungen'),
                    ),
                    _SidebarTile(
                      dest: _SidebarDest(
                          Icons.logout_rounded,
                          Icons.logout_rounded,
                          'Abmelden',
                          color: AppTheme.danger),
                      isSelected: false,
                      showLabel: showLabels,
                      onTap: () => _confirmLogout(context),
                    ),
                  ]),
                ),
              ]),
            );
          },
        ),

        // ── Main content ──────────────────────────────────────────────────────
        Expanded(
          child: Column(children: [
            _buildTopBar(context),
            if (_isOffline)
              Material(
                color: Colors.orange.shade700,
                child: const Padding(
                  padding: EdgeInsets.symmetric(horizontal: 16, vertical: 6),
                  child: Row(children: [
                    Icon(Icons.wifi_off_rounded, color: Colors.white, size: 16),
                    SizedBox(width: 8),
                    Text(
                      'Keine Internetverbindung',
                      style: TextStyle(
                          color: Colors.white,
                          fontSize: 12,
                          fontWeight: FontWeight.w600),
                    ),
                  ]),
                ),
              ),
            Expanded(child: widget.child),
          ]),
        ),
      ]),
    );
  }

  // ── Top bar ───────────────────────────────────────────────────────────────────

  Widget _buildTopBar(BuildContext context) {
    final cs        = Theme.of(context).colorScheme;
    final timeStr   = DateFormat('EEEE, d. MMMM · HH:mm', 'de_DE').format(_now);
    final totalBadge = _newIntakes + _birthdayCount;

    return Container(
      height: 52,
      decoration: BoxDecoration(
        color: cs.surface,
        border: Border(bottom: BorderSide(color: cs.outlineVariant, width: 1)),
      ),
      padding: const EdgeInsets.symmetric(horizontal: 20),
      child: Row(children: [
        Text(
          timeStr,
          style: TextStyle(
            fontSize: 13,
            fontWeight: FontWeight.w500,
            color: cs.onSurfaceVariant,
            fontFeatures: const [FontFeature.tabularFigures()],
          ),
        ),
        const Spacer(),

        // Theme toggle
        Consumer<ThemeService>(
          builder: (_, ts, __) => IconButton(
            icon: Icon(switch (ts.mode) {
              ThemeMode.light  => Icons.light_mode_rounded,
              ThemeMode.dark   => Icons.dark_mode_rounded,
              ThemeMode.system => Icons.brightness_auto_rounded,
            }),
            tooltip: 'Theme wechseln',
            iconSize: 20,
            onPressed: () {
              final next = switch (ts.mode) {
                ThemeMode.system => ThemeMode.light,
                ThemeMode.light  => ThemeMode.dark,
                ThemeMode.dark   => ThemeMode.system,
              };
              ts.setMode(next);
            },
          ),
        ),

        // Offline-Indicator
        if (_isOffline)
          Padding(
            padding: const EdgeInsets.only(right: 4),
            child: Tooltip(
              message: 'Keine Internetverbindung',
              child: Icon(Icons.wifi_off_rounded,
                  size: 18, color: Colors.orange.shade700),
            ),
          ),

        // Bell with shake animation
        AnimatedBuilder(
          animation: _bellAnim,
          builder: (context, child) => Transform.rotate(
            angle: _bellAnim.value,
            child: child,
          ),
          child: Stack(
            alignment: Alignment.center,
            children: [
              IconButton(
                icon: Icon(
                  totalBadge > 0
                      ? Icons.notifications_rounded
                      : Icons.notifications_outlined,
                  size: 20,
                ),
                tooltip: 'Benachrichtigungen',
                onPressed: () => _showNotificationPanel(context),
              ),
              if (totalBadge > 0)
                Positioned(
                  top: 6, right: 6,
                  child: Container(
                    padding: const EdgeInsets.symmetric(
                        horizontal: 4, vertical: 2),
                    decoration: BoxDecoration(
                      color: AppTheme.danger,
                      borderRadius: BorderRadius.circular(10),
                      border: Border.all(
                          color: cs.surface, width: 1.5),
                    ),
                    constraints: const BoxConstraints(
                        minWidth: 16, minHeight: 16),
                    child: Text(
                      '$totalBadge',
                      style: const TextStyle(
                          color: Colors.white,
                          fontSize: 9,
                          fontWeight: FontWeight.w800),
                      textAlign: TextAlign.center,
                    ),
                  ),
                ),
            ],
          ),
        ),

        const SizedBox(width: 4),

        // User chip
        Consumer<AuthService>(
          builder: (_, auth, __) => Padding(
            padding: const EdgeInsets.only(left: 4),
            child: InkWell(
              borderRadius: BorderRadius.circular(20),
              onTap: () => context.push('/profil'),
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                child: Row(mainAxisSize: MainAxisSize.min, children: [
                  CircleAvatar(
                    radius: 14,
                    backgroundColor: AppTheme.primary.withValues(alpha: 0.15),
                    child: Text(
                      (auth.userName.isNotEmpty
                              ? auth.userName[0]
                              : '?')
                          .toUpperCase(),
                      style: const TextStyle(
                          fontSize: 12,
                          fontWeight: FontWeight.w700,
                          color: AppTheme.primary),
                    ),
                  ),
                  const SizedBox(width: 8),
                  Text(
                    auth.userName,
                    style: TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w600,
                        color: cs.onSurface),
                  ),
                ]),
              ),
            ),
          ),
        ),
      ]),
    );
  }
}

// ── Sidebar helpers ────────────────────────────────────────────────────────────

class _SidebarDest {
  final IconData icon;
  final IconData selectedIcon;
  final String label;
  final int badge;
  final Color? color;
  final String? shortcut;
  const _SidebarDest(this.icon, this.selectedIcon, this.label,
      {this.badge = 0, this.color, this.shortcut});
}

class _SidebarTile extends StatelessWidget {
  final _SidebarDest dest;
  final bool isSelected;
  final bool showLabel;
  final VoidCallback onTap;

  const _SidebarTile({
    required this.dest,
    required this.isSelected,
    required this.showLabel,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final cs    = Theme.of(context).colorScheme;
    final accent = dest.color ?? AppTheme.primary;
    final fg    = isSelected ? accent : cs.onSurfaceVariant;
    final tip   = showLabel
        ? ''
        : (dest.shortcut != null
            ? '${dest.label}  ${dest.shortcut}'
            : dest.label);

    return Padding(
      padding: const EdgeInsets.only(bottom: 1),
      child: Tooltip(
        message: tip,
        preferBelow: false,
        child: InkWell(
          borderRadius: BorderRadius.circular(10),
          onTap: onTap,
          child: AnimatedContainer(
            duration: const Duration(milliseconds: 180),
            decoration: BoxDecoration(
              color: isSelected
                  ? accent.withValues(alpha: 0.12)
                  : Colors.transparent,
              borderRadius: BorderRadius.circular(10),
            ),
            padding: EdgeInsets.symmetric(
              horizontal: showLabel ? 10 : 0,
              vertical: 9,
            ),
            child: Row(
              mainAxisAlignment: showLabel
                  ? MainAxisAlignment.start
                  : MainAxisAlignment.center,
              children: [
                Badge(
                  isLabelVisible: dest.badge > 0,
                  label: Text('${dest.badge}',
                      style: const TextStyle(
                          fontSize: 9, fontWeight: FontWeight.w700)),
                  backgroundColor: AppTheme.danger,
                  child: Icon(
                    isSelected ? dest.selectedIcon : dest.icon,
                    color: fg,
                    size: 20,
                  ),
                ),
                if (showLabel) ...[
                  const SizedBox(width: 12),
                  Expanded(
                    child: Text(
                      dest.label,
                      style: TextStyle(
                        fontSize: 13,
                        fontWeight: isSelected
                            ? FontWeight.w700
                            : FontWeight.w500,
                        color: isSelected ? accent : cs.onSurface,
                      ),
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                  if (dest.shortcut != null)
                    Text(
                      dest.shortcut!,
                      style: TextStyle(
                          fontSize: 10,
                          color: cs.onSurfaceVariant
                              .withValues(alpha: 0.5)),
                    ),
                ],
              ],
            ),
          ),
        ),
      ),
    );
  }
}

// ── Notification panel (Dialog statt BottomSheet auf Desktop) ─────────────────

class _NotificationPanel extends StatelessWidget {
  final int newIntakes;
  final List<Map<String, dynamic>> pendingIntakes;
  final int birthdayCount;
  final void Function(String route) onTap;

  const _NotificationPanel({
    required this.newIntakes,
    required this.pendingIntakes,
    required this.birthdayCount,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final cs     = Theme.of(context).colorScheme;
    final hasAny = newIntakes > 0 || birthdayCount > 0;

    return Column(mainAxisSize: MainAxisSize.min, children: [
      // Header
      Padding(
        padding: const EdgeInsets.fromLTRB(20, 16, 12, 12),
        child: Row(children: [
          Text('Benachrichtigungen',
              style: Theme.of(context)
                  .textTheme
                  .titleMedium
                  ?.copyWith(fontWeight: FontWeight.w700)),
          const Spacer(),
          if (hasAny)
            Container(
              padding:
                  const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
              decoration: BoxDecoration(
                color: AppTheme.danger.withValues(alpha: 0.12),
                borderRadius: BorderRadius.circular(12),
              ),
              child: Text(
                '${newIntakes + birthdayCount} neu',
                style: TextStyle(
                    color: AppTheme.danger,
                    fontSize: 11,
                    fontWeight: FontWeight.w700),
              ),
            ),
          IconButton(
            icon: const Icon(Icons.close_rounded, size: 18),
            onPressed: () => Navigator.pop(context),
          ),
        ]),
      ),
      Divider(height: 1, color: cs.outlineVariant),

      // Content
      Flexible(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(16),
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            if (!hasAny)
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 32),
                child: Column(children: [
                  Icon(Icons.notifications_none_rounded,
                      size: 48,
                      color: cs.onSurfaceVariant),
                  const SizedBox(height: 8),
                  Text('Keine neuen Benachrichtigungen',
                      style: Theme.of(context)
                          .textTheme
                          .bodyMedium
                          ?.copyWith(color: cs.onSurfaceVariant)),
                ]),
              ),
            ...pendingIntakes.map((intake) {
              final ownerFirst = intake['owner_first_name'] as String? ?? '';
              final ownerLast  = intake['owner_last_name']  as String? ?? '';
              final ownerName  = '$ownerFirst $ownerLast'.trim();
              final petName    = intake['patient_name'] as String? ?? '';
              final species    = intake['patient_species'] as String? ?? '';
              final subtitle   = [
                if (petName.isNotEmpty) petName,
                if (species.isNotEmpty) species,
              ].join(' · ');
              return _NotifTile(
                icon: Icons.assignment_ind_rounded,
                color: AppTheme.primary,
                title: ownerName.isNotEmpty ? ownerName : 'Neue Anmeldung',
                subtitle: subtitle.isNotEmpty
                    ? subtitle
                    : 'Zur Bearbeitung öffnen',
                onTap: () => onTap('/anmeldungen/${intake['id']}'),
              );
            }),
            if (birthdayCount > 0)
              _NotifTile(
                icon: Icons.cake_rounded,
                color: AppTheme.secondary,
                title:
                    '$birthdayCount Geburtstag${birthdayCount == 1 ? '' : 'e'} heute!',
                subtitle:
                    'Tier${birthdayCount == 1 ? '' : 'e'} haben heute Geburtstag',
                onTap: () => onTap('/patienten'),
              ),
          ]),
        ),
      ),
    ]);
  }
}

class _NotifTile extends StatelessWidget {
  final IconData icon;
  final Color color;
  final String title, subtitle;
  final VoidCallback onTap;
  const _NotifTile({
    required this.icon,
    required this.color,
    required this.title,
    required this.subtitle,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Material(
        color: color.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(12),
        child: InkWell(
          borderRadius: BorderRadius.circular(12),
          onTap: onTap,
          child: Padding(
            padding:
                const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
            child: Row(children: [
              Container(
                width: 38, height: 38,
                decoration: BoxDecoration(
                    color: color.withValues(alpha: 0.15),
                    shape: BoxShape.circle),
                child: Icon(icon, color: color, size: 18),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(title,
                          style: const TextStyle(
                              fontWeight: FontWeight.w600,
                              fontSize: 13)),
                      Text(subtitle,
                          style: TextStyle(
                              fontSize: 11,
                              color: Theme.of(context)
                                  .colorScheme
                                  .onSurfaceVariant)),
                    ]),
              ),
              Icon(Icons.chevron_right_rounded,
                  size: 16,
                  color:
                      Theme.of(context).colorScheme.onSurfaceVariant),
            ]),
          ),
        ),
      ),
    );
  }
}
