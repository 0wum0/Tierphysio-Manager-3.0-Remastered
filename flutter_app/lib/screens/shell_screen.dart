import 'dart:async';
import 'dart:io';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:intl/intl.dart';
import '../services/auth_service.dart';
import '../services/api_service.dart';
import '../services/notification_service.dart';
import '../services/theme_service.dart';
import '../core/theme.dart';

const double _kSidebarCollapsed = 72.0;
const double _kSidebarExpanded  = 240.0;

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
    '/anmeldungen',
    '/einladungen',
    '/behandlungsarten',
    '/portal-admin',
  ];

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
      TweenSequenceItem(tween: Tween(begin: 0.0, end:  0.18), weight: 1),
      TweenSequenceItem(tween: Tween(begin: 0.18, end: -0.18), weight: 2),
      TweenSequenceItem(tween: Tween(begin: -0.18, end: 0.12), weight: 2),
      TweenSequenceItem(tween: Tween(begin: 0.12, end: -0.08), weight: 2),
      TweenSequenceItem(tween: Tween(begin: -0.08, end: 0.0),  weight: 1),
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

  Future<void> _checkConnectivity() async {
    try {
      final result = await InternetAddress.lookup('google.com')
          .timeout(const Duration(seconds: 4));
      if (mounted) setState(() => _isOffline = result.isEmpty || result.first.rawAddress.isEmpty);
    } catch (_) {
      if (mounted) setState(() => _isOffline = true);
    }
  }

  void _toggleSidebar() {
    setState(() => _sidebarExpanded = !_sidebarExpanded);
    if (_sidebarExpanded) {
      _sidebarCtrl.forward();
    } else {
      _sidebarCtrl.reverse();
    }
  }

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
        final newCount = pending.length + ((d['birthdays_today'] as List?)?.length ?? 0);
        final hadMore  = newCount > _lastBellCount && _lastBellCount >= 0;
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

  final _narrowScaffoldKey = GlobalKey<ScaffoldState>();

  void _openMoreDrawer() {
    final cs = Theme.of(context).colorScheme;
    showModalBottomSheet(
      context: context,
      backgroundColor: cs.surface,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (ctx) {
        final items = [
          _GridItem(Icons.person_rounded,          'Tierhalter',       AppTheme.secondary, '/tierhalter'),
          _GridItem(Icons.people_alt_rounded,      'Warteliste',       AppTheme.warning,   '/warteliste'),
          _GridItem(Icons.warning_amber_rounded,   'Mahnungen',        AppTheme.danger,    '/mahnungen',  badge: _overdueCount),
          _GridItem(Icons.assignment_ind_rounded,  'Anmeldungen',      AppTheme.primary,   '/anmeldungen', badge: _newIntakes),
          _GridItem(Icons.send_rounded,            'Einladungen',      AppTheme.secondary, '/einladungen'),
          _GridItem(Icons.category_rounded,        'Behandlungs\narten', AppTheme.tertiary,   '/behandlungsarten'),
          _GridItem(Icons.assignment_rounded,      'Hausaufgaben',     AppTheme.primary,    '/hausaufgaben'),
          _GridItem(Icons.home_work_rounded,       'Portal Admin',     AppTheme.tertiary,   '/portal-admin'),
          _GridItem(Icons.search_rounded,          'Suche',            AppTheme.primary,    '/suche'),
          _GridItem(Icons.person_outline_rounded,  'Mein Profil',      AppTheme.primary,    '/profil'),
          _GridItem(Icons.settings_rounded,        'Einstellungen',    AppTheme.tertiary,   '/einstellungen'),
        ];
        return SafeArea(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 16),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Container(
                  width: 40, height: 4,
                  margin: const EdgeInsets.only(bottom: 16),
                  decoration: BoxDecoration(
                    color: cs.outlineVariant,
                    borderRadius: BorderRadius.circular(2),
                  ),
                ),
                GridView.count(
                  shrinkWrap: true,
                  crossAxisCount: 4,
                  mainAxisSpacing: 8,
                  crossAxisSpacing: 8,
                  childAspectRatio: 0.82,
                  physics: const NeverScrollableScrollPhysics(),
                  children: items.map((item) {
                    final isDark = Theme.of(ctx).brightness == Brightness.dark;
                    return GestureDetector(
                      onTap: () {
                        Navigator.pop(ctx);
                        context.go(item.route);
                      },
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Stack(
                            clipBehavior: Clip.none,
                            children: [
                              Container(
                                width: 56, height: 56,
                                decoration: BoxDecoration(
                                  gradient: LinearGradient(
                                    colors: [item.color, item.color.withValues(alpha: 0.72)],
                                    begin: Alignment.topLeft,
                                    end: Alignment.bottomRight,
                                  ),
                                  borderRadius: BorderRadius.circular(16),
                                  boxShadow: [
                                    BoxShadow(
                                      color: item.color.withValues(alpha: isDark ? 0.25 : 0.32),
                                      blurRadius: 10, offset: const Offset(0, 4),
                                    ),
                                  ],
                                ),
                                child: Icon(item.icon, color: Colors.white, size: 26),
                              ),
                              if ((item.badge ?? 0) > 0)
                                Positioned(
                                  top: -5, right: -5,
                                  child: Container(
                                    padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 2),
                                    decoration: BoxDecoration(
                                      color: AppTheme.danger,
                                      borderRadius: BorderRadius.circular(10),
                                      border: Border.all(
                                        color: isDark ? const Color(0xFF1A1D27) : Colors.white,
                                        width: 1.5),
                                    ),
                                    child: Text('${item.badge}',
                                      style: const TextStyle(
                                        color: Colors.white, fontSize: 9,
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
                            style: TextStyle(
                              fontSize: 10.5,
                              fontWeight: FontWeight.w600,
                              color: cs.onSurface,
                              height: 1.2,
                            ),
                          ),
                        ],
                      ),
                    );
                  }).toList(),
                ),
              ],
            ),
          ),
        );
      },
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
    final location = GoRouterState.of(context).matchedLocation;
    final railIdx  = _railRoutes.indexWhere((r) => location.startsWith(r));
    final selected = railIdx >= 0 ? railIdx : 0;
    final cs = Theme.of(context).colorScheme;

    final destinations = [
      _SidebarDest(Icons.dashboard_outlined,      Icons.dashboard_rounded,         'Dashboard'),
      _SidebarDest(Icons.pets_outlined,           Icons.pets_rounded,              'Patienten'),
      _SidebarDest(Icons.person_outline_rounded,  Icons.person_rounded,            'Tierhalter'),
      _SidebarDest(Icons.receipt_long_outlined,   Icons.receipt_long_rounded,      'Rechnungen'),
      _SidebarDest(Icons.calendar_month_outlined, Icons.calendar_month_rounded,    'Kalender'),
      _SidebarDest(Icons.chat_outlined,           Icons.chat_rounded,              'Nachrichten',  badge: _unreadMessages),
      _SidebarDest(Icons.people_alt_outlined,           Icons.people_alt_rounded,          'Warteliste'),
      _SidebarDest(Icons.warning_amber_outlined,        Icons.warning_amber_rounded,       'Mahnungen',    badge: _overdueCount),
      _SidebarDest(Icons.assignment_ind_outlined,       Icons.assignment_ind_rounded,      'Anmeldungen',  badge: _newIntakes),
      _SidebarDest(Icons.send_outlined,                 Icons.send_rounded,                'Einladungen'),
      _SidebarDest(Icons.category_outlined,             Icons.category_rounded,            'Behandlungsarten'),
      _SidebarDest(Icons.home_work_outlined,            Icons.home_work_rounded,           'Portal Admin'),
    ];

    return Scaffold(
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
                  // ── Header ──
                  SizedBox(
                    height: 64,
                    child: Row(
                      children: [
                        const SizedBox(width: 14),
                        Container(
                          width: 36, height: 36,
                          decoration: BoxDecoration(
                            color: AppTheme.primary.withValues(alpha: 0.12),
                            borderRadius: BorderRadius.circular(10),
                          ),
                          child: Center(
                            child: SvgPicture.asset(
                              'assets/icons/paw.svg', width: 20, height: 20,
                              colorFilter: ColorFilter.mode(AppTheme.primary, BlendMode.srcIn),
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
                                  TextSpan(text: 'Thera',
                                    style: TextStyle(color: cs.onSurface, decoration: TextDecoration.none)),
                                  TextSpan(text: 'Pano', style: TextStyle(
                                    decoration: TextDecoration.none,
                                    foreground: Paint()..shader = LinearGradient(
                                      colors: [AppTheme.primary, AppTheme.secondary],
                                    ).createShader(const Rect.fromLTWH(0, 0, 50, 18)),
                                  )),
                                ],
                              ),
                            ),
                          ),
                        ],
                        // Toggle button
                        SizedBox(
                          width: 36,
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
                  ),
                  Divider(height: 1, color: cs.outlineVariant),
                  // ── Search ──
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 6),
                    child: showLabels
                        ? InkWell(
                            borderRadius: BorderRadius.circular(10),
                            onTap: () => context.push('/suche'),
                            child: Container(
                              height: 36,
                              decoration: BoxDecoration(
                                color: cs.surfaceContainerHighest.withValues(alpha: 0.5),
                                borderRadius: BorderRadius.circular(10),
                              ),
                              padding: const EdgeInsets.symmetric(horizontal: 10),
                              child: Row(children: [
                                Icon(Icons.search_rounded, size: 16,
                                  color: cs.onSurfaceVariant),
                                const SizedBox(width: 8),
                                Text('Suche', style: TextStyle(
                                  fontSize: 13, color: cs.onSurfaceVariant)),
                              ]),
                            ),
                          )
                        : IconButton(
                            icon: const Icon(Icons.search_rounded, size: 20),
                            tooltip: 'Suche',
                            onPressed: () => context.push('/suche'),
                          ),
                  ),
                  // ── Nav items ──
                  Expanded(
                    child: ListView.builder(
                      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                      itemCount: destinations.length,
                      itemBuilder: (ctx, i) {
                        final dest = destinations[i];
                        final isSelected = i == selected;
                        return _SidebarTile(
                          dest: dest,
                          isSelected: isSelected,
                          showLabel: showLabels,
                          onTap: () => _onRailSelected(i),
                        );
                      },
                    ),
                  ),
                  Divider(height: 1, color: cs.outlineVariant),
                  // ── Footer ──
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 8),
                    child: Column(mainAxisSize: MainAxisSize.min, children: [
                      _SidebarTile(
                        dest: _SidebarDest(Icons.person_outline_rounded,
                            Icons.person_rounded, 'Profil'),
                        isSelected: false,
                        showLabel: showLabels,
                        onTap: () => context.push('/profil'),
                      ),
                      _SidebarTile(
                        dest: _SidebarDest(Icons.settings_outlined,
                            Icons.settings_rounded, 'Einstellungen',
                            color: AppTheme.tertiary),
                        isSelected: location.startsWith('/einstellungen'),
                        showLabel: showLabels,
                        onTap: () => context.push('/einstellungen'),
                      ),
                      _SidebarTile(
                        dest: _SidebarDest(Icons.logout_rounded,
                            Icons.logout_rounded, 'Abmelden',
                            color: AppTheme.danger),
                        isSelected: false,
                        showLabel: showLabels,
                        onTap: () => _confirmLogout(context),
                      ),
                    ]),
                  ),
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
              if (_isOffline)
                Material(
                  color: Colors.orange.shade700,
                  child: const Padding(
                    padding: EdgeInsets.symmetric(horizontal: 16, vertical: 6),
                    child: Row(children: [
                      Icon(Icons.wifi_off_rounded, color: Colors.white, size: 16),
                      SizedBox(width: 8),
                      Text('Keine Internetverbindung',
                        style: TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.w600)),
                    ]),
                  ),
                ),
              Expanded(child: widget.child),
            ],
          ),
        ),
      ]),
    );
  }

  Widget _buildTopBar(BuildContext context) {
    final cs       = Theme.of(context).colorScheme;
    final timeStr  = DateFormat('HH:mm', 'de_DE').format(_now);
    final totalBadge = _newIntakes + _birthdayCount;
    return Container(
      height: 56,
      decoration: BoxDecoration(
        color: cs.surface,
        border: Border(bottom: BorderSide(color: cs.outlineVariant, width: 1)),
      ),
      padding: const EdgeInsets.symmetric(horizontal: 16),
      child: Row(children: [
        Text(timeStr, style: TextStyle(
          fontSize: 15, fontWeight: FontWeight.w600,
          color: cs.onSurfaceVariant,
          fontFeatures: const [FontFeature.tabularFigures()],
        )),
        const Spacer(),
        Stack(
          alignment: Alignment.center,
          children: [
            IconButton(
              icon: const Icon(Icons.notifications_outlined),
              tooltip: 'Benachrichtigungen',
              onPressed: () => _showNotificationPanel(context),
            ),
            if (totalBadge > 0)
              Positioned(
                top: 8, right: 8,
                child: Container(
                  padding: const EdgeInsets.all(3),
                  decoration: const BoxDecoration(color: Colors.red, shape: BoxShape.circle),
                  constraints: const BoxConstraints(minWidth: 16, minHeight: 16),
                  child: Text('$totalBadge',
                    style: const TextStyle(color: Colors.white, fontSize: 9, fontWeight: FontWeight.w700),
                    textAlign: TextAlign.center),
                ),
              ),
          ],
        ),
      ]),
    );
  }

  PreferredSizeWidget _buildAppBar(BuildContext context) {
    final timeStr = DateFormat('HH:mm', 'de_DE').format(_now);
    final totalBadge = _newIntakes + _birthdayCount;
    return AppBar(
      automaticallyImplyLeading: false,
      titleSpacing: 16,
      title: Row(
        children: [
          // Logo + TeraPano
          SvgPicture.asset('assets/icons/paw.svg', width: 22, height: 22,
            colorFilter: ColorFilter.mode(AppTheme.primary, BlendMode.srcIn)),
          const SizedBox(width: 8),
          RichText(text: TextSpan(
            style: const TextStyle(
              fontSize: 18, fontWeight: FontWeight.w800, letterSpacing: -0.5,
              decoration: TextDecoration.none,
            ),
            children: [
              TextSpan(text: 'Thera', style: TextStyle(
                color: Theme.of(context).colorScheme.onSurface,
                decoration: TextDecoration.none,
              )),
              TextSpan(text: 'Pano', style: TextStyle(
                decoration: TextDecoration.none,
                foreground: Paint()..shader = LinearGradient(
                  colors: [AppTheme.primary, AppTheme.secondary],
                ).createShader(const Rect.fromLTWH(0, 0, 56, 20)),
              )),
            ],
          )),
          // Live clock — centered
          Expanded(
            child: Center(
              child: Text(timeStr, style: TextStyle(
                fontSize: 15, fontWeight: FontWeight.w600,
                color: Theme.of(context).colorScheme.onSurfaceVariant,
                fontFeatures: const [FontFeature.tabularFigures()],
              )),
            ),
          ),
        ],
      ),
      actions: [
        // Theme toggle
        Consumer<ThemeService>(
          builder: (_, ts, __) => IconButton(
            icon: Icon(switch (ts.mode) {
              ThemeMode.light  => Icons.light_mode_rounded,
              ThemeMode.dark   => Icons.dark_mode_rounded,
              ThemeMode.system => Icons.brightness_auto_rounded,
            }),
            tooltip: 'Theme wechseln',
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
        // Notification bell with shake animation
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
                ),
                tooltip: 'Benachrichtigungen',
                onPressed: () => _showNotificationPanel(context),
              ),
              if (totalBadge > 0)
                Positioned(
                  top: 6, right: 6,
                  child: AnimatedContainer(
                    duration: const Duration(milliseconds: 300),
                    curve: Curves.elasticOut,
                    padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 2),
                    decoration: BoxDecoration(
                      color: AppTheme.danger,
                      borderRadius: BorderRadius.circular(10),
                      border: Border.all(
                        color: Theme.of(context).colorScheme.surface,
                        width: 1.5),
                    ),
                    constraints: const BoxConstraints(minWidth: 16, minHeight: 16),
                    child: Text('$totalBadge',
                      style: const TextStyle(color: Colors.white, fontSize: 9, fontWeight: FontWeight.w800),
                      textAlign: TextAlign.center),
                  ),
                ),
            ],
          ),
        ),
        const SizedBox(width: 4),
      ],
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
        newIntakes:     _newIntakes,
        pendingIntakes: _pendingIntakes,
        birthdayCount:  _birthdayCount,
        onTap: (route) { Navigator.pop(ctx); context.push(route); },
      ),
    );
  }

  Widget _buildNarrowLayout(BuildContext context) {
    final location = GoRouterState.of(context).matchedLocation;
    final primaryIdx = _primaryRoutes.indexWhere((r) => location.startsWith(r));
    final navIdx = primaryIdx >= 0 ? primaryIdx : 0;

    return Scaffold(
      key: _narrowScaffoldKey,
      appBar: _buildAppBar(context),
      body: Column(children: [
        if (_isOffline)
          Material(
            color: Colors.orange.shade700,
            child: const SafeArea(
              top: false, bottom: false,
              child: Padding(
                padding: EdgeInsets.symmetric(horizontal: 16, vertical: 6),
                child: Row(children: [
                  Icon(Icons.wifi_off_rounded, color: Colors.white, size: 16),
                  SizedBox(width: 8),
                  Text('Keine Internetverbindung',
                    style: TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.w600)),
                ]),
              ),
            ),
          ),
        Expanded(child: widget.child),
      ]),
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

// ── Sidebar helpers ────────────────────────────────────────────────────────────

class _SidebarDest {
  final IconData icon;
  final IconData selectedIcon;
  final String label;
  final int badge;
  final Color? color;
  const _SidebarDest(this.icon, this.selectedIcon, this.label,
      {this.badge = 0, this.color});
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
    final cs = Theme.of(context).colorScheme;
    final accent = dest.color ?? AppTheme.primary;
    final fg = isSelected ? accent : cs.onSurfaceVariant;

    return Padding(
      padding: const EdgeInsets.only(bottom: 2),
      child: Tooltip(
        message: showLabel ? '' : dest.label,
        preferBelow: false,
        child: InkWell(
          borderRadius: BorderRadius.circular(12),
          onTap: onTap,
          child: AnimatedContainer(
            duration: const Duration(milliseconds: 200),
            decoration: BoxDecoration(
              color: isSelected ? accent.withValues(alpha: 0.12) : Colors.transparent,
              borderRadius: BorderRadius.circular(12),
            ),
            padding: EdgeInsets.symmetric(
              horizontal: showLabel ? 10 : 0,
              vertical: 10,
            ),
            child: Row(
              mainAxisAlignment: showLabel
                  ? MainAxisAlignment.start
                  : MainAxisAlignment.center,
              children: [
                Badge(
                  isLabelVisible: dest.badge > 0,
                  label: Text('${dest.badge}',
                    style: const TextStyle(fontSize: 9, fontWeight: FontWeight.w700)),
                  backgroundColor: AppTheme.danger,
                  child: Icon(
                    isSelected ? dest.selectedIcon : dest.icon,
                    color: fg,
                    size: 22,
                  ),
                ),
                if (showLabel) ...[
                  const SizedBox(width: 12),
                  Expanded(
                    child: Text(
                      dest.label,
                      style: TextStyle(
                        fontSize: 13,
                        fontWeight: isSelected ? FontWeight.w700 : FontWeight.w500,
                        color: isSelected ? accent : cs.onSurface,
                      ),
                      overflow: TextOverflow.ellipsis,
                    ),
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

// ── Drawer item model ──────────────────────────────────────────────────────────

class _GridItem {
  final IconData icon;
  final String label;
  final Color color;
  final String route;
  final int? badge;
  const _GridItem(this.icon, this.label, this.color, this.route, {this.badge});
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
    _fadeAnim  = CurvedAnimation(parent: _entryCtrl, curve: Curves.easeOut);
    _slideAnim = Tween<Offset>(begin: const Offset(0, 0.15), end: Offset.zero)
        .animate(CurvedAnimation(parent: _entryCtrl, curve: Curves.easeOutCubic));
    _entryCtrl.forward();
  }

  @override
  void dispose() { _entryCtrl.dispose(); super.dispose(); }

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
                Container(width: 40, height: 4, margin: const EdgeInsets.only(bottom: 16),
                  decoration: BoxDecoration(color: Colors.grey.shade300, borderRadius: BorderRadius.circular(2))),
                Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                  Text('Benachrichtigungen',
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700)),
                  if (hasAny)
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                      decoration: BoxDecoration(
                        color: AppTheme.danger.withValues(alpha: 0.12),
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: Text('${widget.newIntakes + widget.birthdayCount} neu',
                        style: TextStyle(color: AppTheme.danger, fontSize: 11, fontWeight: FontWeight.w700)),
                    ),
                ]),
                const SizedBox(height: 16),
                if (!hasAny)
                  Padding(
                    padding: const EdgeInsets.symmetric(vertical: 24),
                    child: Column(children: [
                      Icon(Icons.notifications_none_rounded, size: 48,
                        color: Theme.of(context).colorScheme.onSurfaceVariant),
                      const SizedBox(height: 8),
                      Text('Keine neuen Benachrichtigungen',
                        style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                          color: Theme.of(context).colorScheme.onSurfaceVariant)),
                    ]),
                  ),
                // Individual pending intakes
                ...widget.pendingIntakes.map((intake) {
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
                    subtitle: subtitle.isNotEmpty ? subtitle : 'Zur Bestätigung antippen',
                    onTap: () => widget.onTap('/anmeldungen/${intake['id']}'),
                  );
                }),
                if (widget.birthdayCount > 0)
                  _NotifTile(
                    icon: Icons.cake_rounded,
                    color: AppTheme.secondary,
                    title: '${widget.birthdayCount} Geburtstag${widget.birthdayCount == 1 ? '' : 'e'} heute!',
                    subtitle: 'Tier${widget.birthdayCount == 1 ? '' : 'e'} haben heute Geburtstag',
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
  const _NotifTile({required this.icon, required this.color,
    required this.title, required this.subtitle, required this.onTap});

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
                width: 42, height: 42,
                decoration: BoxDecoration(color: color.withValues(alpha: 0.15), shape: BoxShape.circle),
                child: Icon(icon, color: color, size: 22),
              ),
              const SizedBox(width: 12),
              Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(title, style: TextStyle(fontWeight: FontWeight.w700, fontSize: 14, color: color)),
                Text(subtitle, style: Theme.of(context).textTheme.bodySmall),
              ])),
              Icon(Icons.chevron_right_rounded, color: color, size: 18),
            ]),
          ),
        ),
      ),
    );
  }
}
