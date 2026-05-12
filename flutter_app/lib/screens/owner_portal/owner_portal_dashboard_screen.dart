import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
import 'package:shimmer/shimmer.dart';
import '../../services/api_service.dart';
import '../../services/owner_portal_auth_service.dart';
import '../../core/theme.dart';

class OwnerPortalDashboardScreen extends StatefulWidget {
  const OwnerPortalDashboardScreen({super.key});

  @override
  State<OwnerPortalDashboardScreen> createState() => _OwnerPortalDashboardScreenState();
}

class _OwnerPortalDashboardScreenState extends State<OwnerPortalDashboardScreen>
    with SingleTickerProviderStateMixin {
  final ApiService _api = ApiService();
  Map<String, dynamic> _data = {};
  bool _loading = true;
  String? _error;

  late final AnimationController _headerCtrl;
  late final Animation<double> _headerOpacity;
  late final Animation<Offset> _headerSlide;

  static const _green  = Color(0xFF10B981);
  static const _green2 = Color(0xFF059669);

  @override
  void initState() {
    super.initState();
    _headerCtrl = AnimationController(vsync: this, duration: const Duration(milliseconds: 700));
    _headerOpacity = CurvedAnimation(parent: _headerCtrl, curve: Curves.easeOut);
    _headerSlide = Tween<Offset>(begin: const Offset(0, -0.08), end: Offset.zero)
        .animate(CurvedAnimation(parent: _headerCtrl, curve: Curves.easeOutCubic));
    _loadData();
  }

  @override
  void dispose() {
    _headerCtrl.dispose();
    super.dispose();
  }

  Future<void> _loadData() async {
    setState(() { _loading = true; _error = null; });
    try {
      final d = await _api.ownerPortalDashboard();
      setState(() { _data = d; _loading = false; });
      _headerCtrl.forward(from: 0);
    } catch (e) {
      setState(() { _loading = false; _error = e.toString(); });
    }
  }

  String get _label => context.read<OwnerPortalAuthService>().isTrainer ? 'Hunde' : 'Tiere';

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    final isDark = cs.brightness == Brightness.dark;
    return Scaffold(
      backgroundColor: isDark ? const Color(0xFF070E0A) : const Color(0xFFF0FDF7),
      body: RefreshIndicator(
        onRefresh: _loadData,
        color: _green,
        child: CustomScrollView(
          slivers: [
            _buildAppBar(context, isDark),
            if (_loading)
              SliverToBoxAdapter(child: _buildShimmer())
            else if (_error != null)
              SliverFillRemaining(child: _buildError())
            else ...[
              SliverToBoxAdapter(child: _buildStatsRow(isDark)),
              SliverToBoxAdapter(child: _buildSectionLabel('Mein Portal')),
              SliverList(
                delegate: SliverChildListDelegate(
                  _menuItems(context).asMap().entries.map((e) =>
                    TweenAnimationBuilder<double>(
                      tween: Tween(begin: 0, end: 1),
                      duration: Duration(milliseconds: 350 + e.key * 60),
                      curve: Curves.easeOutCubic,
                      builder: (_, v, child) => Transform.translate(
                        offset: Offset(0, 20 * (1 - v)),
                        child: Opacity(opacity: v, child: child),
                      ),
                      child: e.value,
                    ),
                  ).toList(),
                ),
              ),
              const SliverToBoxAdapter(child: SizedBox(height: 32)),
            ],
          ],
        ),
      ),
    );
  }

  Widget _buildAppBar(BuildContext context, bool isDark) {
    final auth = context.read<OwnerPortalAuthService>();
    final name = _data['owner_name'] as String? ?? auth.ownerName;
    final practiceName = _data['practice_name'] as String? ?? 'TheraPano';
    return SliverAppBar(
      expandedHeight: 160,
      pinned: true,
      backgroundColor: _green2,
      elevation: 0,
      actions: [
        IconButton(
          icon: const Icon(Icons.logout_rounded, color: Colors.white),
          tooltip: 'Abmelden',
          onPressed: _logout,
        ),
        const SizedBox(width: 8),
      ],
      flexibleSpace: FlexibleSpaceBar(
        background: Container(
          decoration: const BoxDecoration(
            gradient: LinearGradient(
              colors: [Color(0xFF065F46), _green2, _green],
              begin: Alignment.topLeft, end: Alignment.bottomRight,
            ),
          ),
          child: SafeArea(
            child: FadeTransition(
              opacity: _loading ? const AlwaysStoppedAnimation(0) : _headerOpacity,
              child: SlideTransition(
                position: _loading ? const AlwaysStoppedAnimation(Offset.zero) : _headerSlide,
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(20, 16, 20, 12),
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.end,
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(children: [
                        Container(
                          width: 46, height: 46,
                          decoration: BoxDecoration(
                            shape: BoxShape.circle,
                            color: Colors.white.withValues(alpha: 0.18),
                          ),
                          child: const Center(child: Text('🐾', style: TextStyle(fontSize: 22))),
                        ),
                        const SizedBox(width: 12),
                        Expanded(child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(name.isEmpty ? 'Willkommen' : 'Hallo, $name!',
                              style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w800,
                                  color: Colors.white, letterSpacing: -0.3),
                              maxLines: 1, overflow: TextOverflow.ellipsis),
                            Text(practiceName,
                              style: TextStyle(fontSize: 13, color: Colors.white.withValues(alpha: 0.7))),
                          ],
                        )),
                      ]),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildStatsRow(bool isDark) {
    final pets    = _data['pet_count']     as int? ?? 0;
    final appts   = _data['upcoming_appointments'] as int? ?? (_data['appointments'] as int? ?? 0);
    final invoices = _data['open_invoices'] as int? ?? 0;
    final unread  = _data['unread_messages'] as int? ?? 0;
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 20, 16, 4),
      child: Row(children: [
        _StatChip(value: '$pets',     label: _label, icon: Icons.pets_rounded,           color: _green),
        const SizedBox(width: 10),
        _StatChip(value: '$appts',    label: 'Termine',   icon: Icons.calendar_today_rounded, color: AppTheme.primary),
        const SizedBox(width: 10),
        _StatChip(value: '$invoices', label: 'Rechnungen', icon: Icons.receipt_long_rounded,  color: AppTheme.warning),
        const SizedBox(width: 10),
        _StatChip(value: '$unread',   label: 'Ungelesen',  icon: Icons.chat_bubble_rounded,   color: AppTheme.secondary),
      ]),
    );
  }

  Widget _buildSectionLabel(String label) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 20, 20, 6),
      child: Text(label, style: TextStyle(
        fontSize: 11, fontWeight: FontWeight.w700,
        letterSpacing: 1.2, color: Colors.grey.shade500,
      )),
    );
  }

  List<Widget> _menuItems(BuildContext context) {
    final isTrainer = context.read<OwnerPortalAuthService>().isTrainer;
    return [
      _PortalMenuTile(icon: Icons.pets_rounded,            color: _green,             title: isTrainer ? 'Meine Hunde'   : 'Meine Tiere',
          subtitle: 'Profile, Fotos & Details',   onTap: () => context.go('/owner-portal/pets')),
      _PortalMenuTile(icon: Icons.calendar_month_rounded,  color: AppTheme.primary,   title: 'Termine',
          subtitle: 'Alle Termine auf einen Blick', onTap: () => context.go('/owner-portal/appointments')),
      _PortalMenuTile(icon: Icons.assignment_rounded,      color: AppTheme.secondary, title: isTrainer ? 'Trainingsaufgaben' : 'Hausaufgaben',
          subtitle: 'Übungen & Pläne',             onTap: () => context.go('/owner-portal/homework')),
      _PortalMenuTile(icon: Icons.chat_bubble_rounded,     color: const Color(0xFF0EA5E9), title: 'Nachrichten',
          subtitle: 'Chat mit ${isTrainer ? "Trainer" : "Praxis"}', onTap: () => context.go('/owner-portal/messages')),
      _PortalMenuTile(icon: Icons.receipt_long_rounded,    color: AppTheme.warning,   title: 'Rechnungen',
          subtitle: 'Rechnungen & Zahlungen',      onTap: () => context.go('/owner-portal/invoices')),
      _PortalMenuTile(icon: Icons.description_rounded,     color: AppTheme.danger,    title: 'Befundbögen',
          subtitle: 'Dokumentation & PDFs',        onTap: () => context.go('/owner-portal/befunde')),
    ];
  }

  Widget _buildShimmer() {
    return Shimmer.fromColors(
      baseColor: Colors.grey.shade800.withValues(alpha: 0.3),
      highlightColor: Colors.grey.shade600.withValues(alpha: 0.2),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(children: [
          const SizedBox(height: 16),
          Row(children: List.generate(4, (_) => Expanded(child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 5),
            child: Container(height: 72, decoration: BoxDecoration(
              color: Colors.white, borderRadius: BorderRadius.circular(14))),
          )))),
          const SizedBox(height: 20),
          ...List.generate(5, (_) => Padding(
            padding: const EdgeInsets.only(bottom: 10),
            child: Container(height: 72, decoration: BoxDecoration(
              color: Colors.white, borderRadius: BorderRadius.circular(18))),
          )),
        ]),
      ),
    );
  }

  Widget _buildError() {
    return Center(child: Padding(
      padding: const EdgeInsets.all(32),
      child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
        Container(width: 72, height: 72,
          decoration: BoxDecoration(
            color: AppTheme.danger.withValues(alpha: 0.1),
            shape: BoxShape.circle,
          ),
          child: const Icon(Icons.cloud_off_rounded, color: AppTheme.danger, size: 32),
        ),
        const SizedBox(height: 16),
        const Text('Verbindungsfehler',
          style: TextStyle(fontSize: 18, fontWeight: FontWeight.w700)),
        const SizedBox(height: 8),
        Text(_error ?? '', textAlign: TextAlign.center,
          style: const TextStyle(fontSize: 13, color: Colors.grey)),
        const SizedBox(height: 24),
        FilledButton.icon(
          onPressed: _loadData,
          icon: const Icon(Icons.refresh_rounded),
          label: const Text('Erneut versuchen'),
          style: FilledButton.styleFrom(backgroundColor: _green),
        ),
      ]),
    ));
  }

  Future<void> _logout() async {
    await context.read<OwnerPortalAuthService>().logout();
    if (mounted) context.go('/owner-portal/login');
  }
}

class _StatChip extends StatelessWidget {
  final String value, label;
  final IconData icon;
  final Color color;
  const _StatChip({required this.value, required this.label, required this.icon, required this.color});

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).colorScheme.brightness == Brightness.dark;
    return Expanded(child: Container(
      padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 8),
      decoration: BoxDecoration(
        color: isDark ? color.withValues(alpha: 0.12) : color.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: color.withValues(alpha: 0.2)),
      ),
      child: Column(children: [
        Icon(icon, color: color, size: 18),
        const SizedBox(height: 4),
        Text(value, style: TextStyle(fontSize: 17, fontWeight: FontWeight.w800, color: color)),
        Text(label, style: TextStyle(fontSize: 9, color: color.withValues(alpha: 0.7),
            fontWeight: FontWeight.w600, letterSpacing: 0.3),
            textAlign: TextAlign.center, maxLines: 1, overflow: TextOverflow.ellipsis),
      ]),
    ));
  }
}

class _PortalMenuTile extends StatelessWidget {
  final IconData icon;
  final Color color;
  final String title, subtitle;
  final VoidCallback onTap;
  const _PortalMenuTile({required this.icon, required this.color, required this.title,
      required this.subtitle, required this.onTap});

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).colorScheme.brightness == Brightness.dark;
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 5),
      child: Material(
        color: isDark ? const Color(0xFF0F1F16) : Colors.white,
        borderRadius: BorderRadius.circular(18),
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: onTap,
          splashColor: color.withValues(alpha: 0.08),
          highlightColor: color.withValues(alpha: 0.04),
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 14),
            decoration: BoxDecoration(
              border: Border.all(
                color: isDark ? Colors.white.withValues(alpha: 0.06) : color.withValues(alpha: 0.12),
              ),
              borderRadius: BorderRadius.circular(18),
            ),
            child: Row(children: [
              Container(
                width: 44, height: 44,
                decoration: BoxDecoration(
                  color: color.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Icon(icon, color: color, size: 22),
              ),
              const SizedBox(width: 14),
              Expanded(child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(title, style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w700)),
                  Text(subtitle, style: TextStyle(fontSize: 12, color: Colors.grey.shade500)),
                ],
              )),
              Icon(Icons.chevron_right_rounded, color: Colors.grey.shade400, size: 20),
            ]),
          ),
        ),
      ),
    );
  }
}
