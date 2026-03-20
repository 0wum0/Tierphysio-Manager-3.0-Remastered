import 'package:flutter/material.dart';
import 'package:fl_chart/fl_chart.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import '../services/api_service.dart';
import '../services/update_service.dart';
import '../core/theme.dart';
import '../widgets/shimmer_list.dart';
import '../widgets/animated_stat_card.dart';

class DashboardScreen extends StatefulWidget {
  const DashboardScreen({super.key});

  @override
  State<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen> {
  final _api = ApiService();
  Map<String, dynamic>? _data;
  Map<String, dynamic>? _notifications;
  List<Map<String, dynamic>> _waitlist = [];
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
    // Check for updates after a short delay so the UI settles first
    Future.delayed(const Duration(seconds: 3), () {
      if (mounted) UpdateService.checkForUpdate(context);
    });
  }

  Future<void> _load() async {
    setState(() { _loading = true; _error = null; });
    try {
      final results = await Future.wait([
        _api.dashboard(),
        _api.notificationSummary().catchError((_) => <String, dynamic>{}),
        _api.waitlistList().catchError((_) => <dynamic>[]),
      ]);
      setState(() {
        _data          = results[0] as Map<String, dynamic>;
        _notifications = results[1] as Map<String, dynamic>;
        _waitlist      = (results[2] as List).map((e) => Map<String, dynamic>.from(e as Map)).toList();
        _loading = false;
      });
    } catch (e) {
      setState(() { _error = e.toString(); _loading = false; });
    }
  }

  static double _toDouble(dynamic v) =>
      v is num ? v.toDouble() : double.tryParse(v?.toString() ?? '') ?? 0.0;

  String _eur(dynamic v) =>
      NumberFormat.currency(locale: 'de_DE', symbol: '€').format(_toDouble(v));

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: _loading
          ? _buildShimmer()
          : _error != null
              ? _ErrorView(error: _error!, onRetry: _load)
              : RefreshIndicator(
                  onRefresh: _load,
                  child: _buildContent(),
                ),
    );
  }

  Widget _buildShimmer() {
    return SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      child: Column(children: [
        Row(children: [
          Expanded(child: ShimmerBox(width: double.infinity, height: 100, radius: 16)),
          const SizedBox(width: 12),
          Expanded(child: ShimmerBox(width: double.infinity, height: 100, radius: 16)),
        ]),
        const SizedBox(height: 12),
        Row(children: [
          Expanded(child: ShimmerBox(width: double.infinity, height: 100, radius: 16)),
          const SizedBox(width: 12),
          Expanded(child: ShimmerBox(width: double.infinity, height: 100, radius: 16)),
        ]),
        const SizedBox(height: 20),
        ShimmerBox(width: double.infinity, height: 220, radius: 16),
        const SizedBox(height: 12),
        ShimmerBox(width: double.infinity, height: 180, radius: 16),
      ]),
    );
  }

  Widget _buildContent() {
    final w = MediaQuery.of(context).size.width;
    final isTablet = w >= 600;
    final d = _data!;

    return SingleChildScrollView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: EdgeInsets.symmetric(horizontal: isTablet ? 24 : 16, vertical: 16),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        // Greeting
        _greeting(d),
        const SizedBox(height: 20),

        // Stats row
        _buildStatsRow(isTablet, d),
        const SizedBox(height: 16),

        // Notification banners
        ..._buildAlertBanners(d),

        // Birthday banners
        ..._buildBirthdayBanners(d),

        // Quick actions
        _buildQuickActions(),
        const SizedBox(height: 16),

        // Today's appointments
        _buildTodayAppointments(d),
        const SizedBox(height: 16),

        // Finance + Appointments row (tablet: side by side)
        if (isTablet)
          Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Expanded(child: _revenueChart(d)),
            const SizedBox(width: 16),
            Expanded(child: _appointmentDonut(d)),
          ])
        else ...[
          _revenueChart(d),
          const SizedBox(height: 16),
          _appointmentDonut(d),
        ],

        const SizedBox(height: 16),
        _invoiceStats(d),
        if (_waitlist.isNotEmpty) ...[const SizedBox(height: 16), _waitlistPreview()],
        const SizedBox(height: 80),
      ]),
    );
  }

  List<Widget> _buildAlertBanners(Map<String, dynamic> d) {
    final overdue  = (d['overdue_invoices'] as num?)?.toInt() ?? 0;
    final unread   = (_notifications?['unread_messages'] as num?)?.toInt() ?? 0;
    final waitCnt  = _waitlist.length;
    final banners  = <Widget>[];

    if (overdue > 0) {
      banners.add(_AlertBanner(
        icon: Icons.warning_amber_rounded,
        color: AppTheme.danger,
        message: '$overdue überfällige Rechnung${overdue == 1 ? '' : 'en'}',
        action: 'Anzeigen',
        onTap: () => context.push('/mahnungen'),
      ));
    }
    if (unread > 0) {
      banners.add(_AlertBanner(
        icon: Icons.chat_rounded,
        color: AppTheme.primary,
        message: '$unread ungelesene Nachricht${unread == 1 ? '' : 'en'}',
        action: 'Öffnen',
        onTap: () => context.go('/nachrichten'),
      ));
    }
    if (waitCnt > 0) {
      banners.add(_AlertBanner(
        icon: Icons.people_alt_rounded,
        color: AppTheme.warning,
        message: '$waitCnt Patient${waitCnt == 1 ? '' : 'en'} auf der Warteliste',
        action: 'Anzeigen',
        onTap: () => context.push('/warteliste'),
      ));
    }
    if (banners.isNotEmpty) banners.add(const SizedBox(height: 16));
    return banners;
  }

  List<Widget> _buildBirthdayBanners(Map<String, dynamic> d) {
    final birthdays = List<Map<String, dynamic>>.from(
      (d['birthdays_today'] as List? ?? []).map((e) => Map<String, dynamic>.from(e as Map)));
    if (birthdays.isEmpty) return [];
    return [
      ...birthdays.map((b) => _AlertBanner(
        icon: Icons.cake_rounded,
        color: AppTheme.secondary,
        message: '🎂 ${b['name']} hat heute Geburtstag! (${b['age']} Jahre)',
        action: 'Profil',
        onTap: () => context.push('/patienten/${b['id']}'),
      )),
      const SizedBox(height: 4),
    ];
  }

  Widget _buildTodayAppointments(Map<String, dynamic> d) {
    final apts = List<Map<String, dynamic>>.from(
      (d['today_appointments'] as List? ?? []).map((e) => Map<String, dynamic>.from(e as Map)));

    return Container(
      decoration: BoxDecoration(
        color: Theme.of(context).cardTheme.color,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: Theme.of(context).dividerColor),
      ),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Padding(padding: const EdgeInsets.fromLTRB(16, 14, 12, 6), child: Row(children: [
          Icon(Icons.today_rounded, size: 18, color: AppTheme.tertiary),
          const SizedBox(width: 8),
          Expanded(child: Text(
            'Heute — ${DateFormat('d. MMMM', 'de_DE').format(DateTime.now())}',
            style: TextStyle(fontWeight: FontWeight.w700, color: AppTheme.tertiary, fontSize: 14),
          )),
          TextButton(
            onPressed: () => context.go('/kalender'),
            child: const Text('Kalender'),
          ),
        ])),
        const Divider(height: 1),
        if (apts.isEmpty)
          Padding(
            padding: const EdgeInsets.symmetric(vertical: 20, horizontal: 16),
            child: Row(children: [
              Icon(Icons.event_available_rounded, size: 20,
                color: Theme.of(context).colorScheme.onSurfaceVariant),
              const SizedBox(width: 10),
              Text('Keine Termine heute', style: TextStyle(
                color: Theme.of(context).colorScheme.onSurfaceVariant, fontSize: 13)),
            ]),
          )
        else
          ...apts.map((apt) => _AppointmentCard(apt: apt)),
      ]),
    );
  }

  Widget _buildQuickActions() {
    return Row(children: [
      Expanded(child: _QuickAction(icon: Icons.search_rounded, label: 'Suche', color: AppTheme.primary,
        onTap: () => context.push('/suche'))),
      const SizedBox(width: 10),
      Expanded(child: _QuickAction(icon: Icons.people_alt_rounded, label: 'Warteliste', color: AppTheme.warning,
        onTap: () => context.push('/warteliste'))),
      const SizedBox(width: 10),
      Expanded(child: _QuickAction(icon: Icons.warning_amber_rounded, label: 'Mahnungen', color: AppTheme.danger,
        onTap: () => context.push('/mahnungen'))),
      const SizedBox(width: 10),
      Expanded(child: _QuickAction(icon: Icons.person_rounded, label: 'Profil', color: AppTheme.secondary,
        onTap: () => context.push('/profil'))),
    ]);
  }

  Widget _waitlistPreview() {
    return Container(
      decoration: BoxDecoration(
        color: Theme.of(context).cardTheme.color,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AppTheme.warning.withValues(alpha: 0.3)),
      ),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Padding(padding: const EdgeInsets.fromLTRB(16, 14, 12, 6), child: Row(children: [
          Icon(Icons.people_alt_rounded, size: 18, color: AppTheme.warning),
          const SizedBox(width: 8),
          Expanded(child: Text('Warteliste (${_waitlist.length})',
            style: TextStyle(fontWeight: FontWeight.w700, color: AppTheme.warning, fontSize: 14))),
          TextButton(
            onPressed: () => context.push('/warteliste'),
            child: const Text('Alle'),
          ),
        ])),
        const Divider(height: 1),
        ...(_waitlist.take(3).toList().asMap().entries.map((e) {
          final item = e.value;
          return ListTile(
            dense: true,
            leading: CircleAvatar(
              radius: 14,
              backgroundColor: AppTheme.warning.withValues(alpha: 0.12),
              child: Text('${e.key + 1}', style: TextStyle(color: AppTheme.warning, fontSize: 11, fontWeight: FontWeight.w700)),
            ),
            title: Text(item['patient_name'] as String? ?? '—',
              style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13)),
            subtitle: (item['owner_name'] as String? ?? '').isNotEmpty
              ? Text(item['owner_name'] as String, style: const TextStyle(fontSize: 11))
              : null,
          );
        })),
      ]),
    );
  }


  Widget _greeting(Map<String, dynamic> d) {
    final hour = DateTime.now().hour;
    final greeting = hour < 12 ? 'Guten Morgen' : hour < 18 ? 'Guten Tag' : 'Guten Abend';
    return Row(children: [
      Expanded(
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(greeting, style: Theme.of(context).textTheme.bodyMedium?.copyWith(
            color: Theme.of(context).colorScheme.onSurfaceVariant,
          )),
          const SizedBox(height: 2),
          Text(
            d['user_name'] as String? ?? 'Willkommen',
            style: Theme.of(context).textTheme.headlineSmall?.copyWith(
              fontWeight: FontWeight.w800, letterSpacing: -0.5,
            ),
          ),
        ]),
      ),
      Container(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
        decoration: BoxDecoration(
          color: AppTheme.primary.withValues(alpha: 0.1),
          borderRadius: BorderRadius.circular(20),
        ),
        child: Row(mainAxisSize: MainAxisSize.min, children: [
          Icon(Icons.calendar_today_rounded, size: 14, color: AppTheme.primary),
          const SizedBox(width: 6),
          Text(
            DateFormat('d. MMM', 'de_DE').format(DateTime.now()),
            style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: AppTheme.primary),
          ),
        ]),
      ),
    ]);
  }

  Widget _buildStatsRow(bool isTablet, Map<String, dynamic> d) {
    final cards = [
      AnimatedStatCard(label: 'Patienten', value: '${d['patients_total'] ?? 0}',
          icon: Icons.pets_rounded, color: AppTheme.primary,
          sub: '+${d['patients_new'] ?? 0} neu'),
      AnimatedStatCard(label: 'Tierhalter', value: '${d['owners_total'] ?? 0}',
          icon: Icons.person_rounded, color: AppTheme.secondary),
      AnimatedStatCard(label: 'Heute', value: '${d['today_apts'] ?? 0}',
          icon: Icons.today_rounded, color: AppTheme.tertiary,
          sub: 'Termine'),
      AnimatedStatCard(label: 'Ausstehend', value: '${d['upcoming_apts'] ?? 0}',
          icon: Icons.event_rounded, color: AppTheme.warning,
          sub: 'geplant'),
    ];
    return GridView.count(
      crossAxisCount: isTablet ? 4 : 2,
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      crossAxisSpacing: 12,
      mainAxisSpacing: 12,
      childAspectRatio: isTablet ? 1.4 : 1.5,
      padding: EdgeInsets.zero,
      children: cards,
    );
  }

  Widget _revenueChart(Map<String, dynamic> d) {
    final months = List<Map<String, dynamic>>.from(d['monthly_revenue'] as List? ?? []);
    final bars = months.isEmpty
        ? List.generate(6, (i) => BarChartGroupData(x: i, barRods: [
            BarChartRodData(toY: 0, width: 14, borderRadius: BorderRadius.circular(6)),
          ]))
        : months.asMap().entries.map((e) {
            final rev = _toDouble(e.value['revenue']);
            return BarChartGroupData(x: e.key, barRods: [
              BarChartRodData(
                toY: rev,
                width: 14,
                borderRadius: BorderRadius.circular(6),
                gradient: LinearGradient(
                  colors: [AppTheme.primary, AppTheme.secondary],
                  begin: Alignment.bottomCenter,
                  end: Alignment.topCenter,
                ),
              ),
            ]);
          }).toList();

    final maxY = months.isEmpty ? 1000.0
        : (months.map((m) => _toDouble(m['revenue'])).reduce((a, b) => a > b ? a : b) * 1.2).clamp(100.0, double.infinity);

    return _ChartCard(
      title: 'Umsatz (6 Monate)',
      subtitle: _eur(d['revenue_month']) + ' diesen Monat',
      child: BarChart(
        BarChartData(
          maxY: maxY,
          gridData: FlGridData(
            show: true,
            drawVerticalLine: false,
            getDrawingHorizontalLine: (_) => FlLine(
              color: Theme.of(context).dividerColor,
              strokeWidth: 0.5,
            ),
          ),
          borderData: FlBorderData(show: false),
          titlesData: FlTitlesData(
            leftTitles: AxisTitles(sideTitles: SideTitles(
              showTitles: true,
              reservedSize: 46,
              getTitlesWidget: (v, _) => Text(
                NumberFormat.compactCurrency(locale: 'de', symbol: '€').format(v),
                style: const TextStyle(fontSize: 10),
              ),
            )),
            bottomTitles: AxisTitles(sideTitles: SideTitles(
              showTitles: true,
              getTitlesWidget: (v, _) {
                if (months.isEmpty) return const Text('');
                final idx = v.toInt();
                if (idx < 0 || idx >= months.length) return const Text('');
                final label = months[idx]['month'] as String? ?? '';
                return Padding(
                  padding: const EdgeInsets.only(top: 4),
                  child: Text(label.length > 3 ? label.substring(0, 3) : label,
                      style: const TextStyle(fontSize: 10)),
                );
              },
            )),
            topTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
            rightTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
          ),
          barGroups: bars,
          barTouchData: BarTouchData(
            touchTooltipData: BarTouchTooltipData(
              getTooltipItem: (group, _, rod, __) => BarTooltipItem(
                _eur(rod.toY),
                const TextStyle(color: Colors.white, fontWeight: FontWeight.w600, fontSize: 12),
              ),
            ),
          ),
        ),
      ),
    );
  }

  Widget _appointmentDonut(Map<String, dynamic> d) {
    final today    = _toDouble(d['today_apts']);
    final upcoming = _toDouble(d['upcoming_apts']);
    final total  = today + upcoming;

    final sections = total == 0
        ? [PieChartSectionData(value: 1, color: Colors.grey.shade200, radius: 40, title: '')]
        : [
            PieChartSectionData(value: today, color: AppTheme.primary, radius: 44,
                title: today > 0 ? today.toInt().toString() : '',
                titleStyle: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 13)),
            PieChartSectionData(value: upcoming, color: AppTheme.tertiary, radius: 40,
                title: upcoming > 0 ? upcoming.toInt().toString() : '',
                titleStyle: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 13)),
          ];

    return _ChartCard(
      title: 'Termine',
      subtitle: '${total.toInt()} gesamt',
      child: Row(children: [
        SizedBox(
          height: 140,
          width: 140,
          child: PieChart(PieChartData(
            sections: sections,
            centerSpaceRadius: 32,
            sectionsSpace: 3,
          )),
        ),
        const SizedBox(width: 20),
        Column(mainAxisAlignment: MainAxisAlignment.center, crossAxisAlignment: CrossAxisAlignment.start, children: [
          _Legend(color: AppTheme.primary, label: 'Heute', value: today.toInt().toString()),
          const SizedBox(height: 10),
          _Legend(color: AppTheme.tertiary, label: 'Geplant', value: upcoming.toInt().toString()),
        ]),
      ]),
    );
  }

  Widget _invoiceStats(Map<String, dynamic> d) {
    return _ChartCard(
      title: 'Rechnungen',
      subtitle: _eur(d['revenue_year']) + ' Jahresumsatz',
      child: Row(children: [
        Expanded(child: _InvoiceStat(
          label: 'Offen', count: '${d['open_invoices'] ?? 0}',
          amount: _eur(d['open_amount']), color: AppTheme.primary,
        )),
        Container(width: 1, height: 60, color: Theme.of(context).dividerColor),
        Expanded(child: _InvoiceStat(
          label: 'Überfällig', count: '${d['overdue_invoices'] ?? 0}',
          amount: _eur(d['overdue_amount']), color: AppTheme.danger,
        )),
        Container(width: 1, height: 60, color: Theme.of(context).dividerColor),
        Expanded(child: _InvoiceStat(
          label: 'Jahresumsatz', count: '', amount: _eur(d['revenue_year']), color: AppTheme.success,
        )),
      ]),
    );
  }
}

// ── Sub-widgets ─────────────────────────────────────────────────────────────

class _AppointmentCard extends StatelessWidget {
  final Map<String, dynamic> apt;
  const _AppointmentCard({required this.apt});

  Color _parseColor(String? hex, Color fallback) {
    if (hex == null || hex.isEmpty) return fallback;
    try {
      final h = hex.replaceAll('#', '');
      return Color(int.parse('FF$h', radix: 16));
    } catch (_) { return fallback; }
  }

  String _statusLabel(String? s) => switch (s) {
    'confirmed'  => 'Bestätigt',
    'completed'  => 'Abgeschlossen',
    'cancelled'  => 'Abgesagt',
    'noshow'     => 'Nicht erschienen',
    _            => 'Geplant',
  };

  Color _statusColor(String? s) => switch (s) {
    'confirmed'  => AppTheme.success,
    'completed'  => AppTheme.tertiary,
    'cancelled'  => AppTheme.danger,
    'noshow'     => AppTheme.warning,
    _            => AppTheme.primary,
  };

  @override
  Widget build(BuildContext context) {
    final aptColor  = _parseColor(apt['treatment_color'] as String? ?? apt['color'] as String?, AppTheme.primary);
    final startStr  = apt['start_at'] as String? ?? '';
    final endStr    = apt['end_at']   as String? ?? '';
    DateTime? start, end;
    try { start = DateTime.parse(startStr); } catch (_) {}
    try { end   = DateTime.parse(endStr);   } catch (_) {}
    final timeStr = start != null
        ? '${DateFormat('HH:mm').format(start)}${end != null ? ' – ${DateFormat('HH:mm').format(end)}' : ''}'
        : '';
    final patient   = apt['patient_name']       as String? ?? '';
    final owner     = apt['owner_name']          as String? ?? '';
    final treatment = apt['treatment_type_name'] as String? ?? '';
    final status    = apt['status']              as String? ?? 'scheduled';

    return Container(
      margin: const EdgeInsets.fromLTRB(12, 0, 12, 8),
      decoration: BoxDecoration(
        color: aptColor.withValues(alpha: 0.06),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: aptColor.withValues(alpha: 0.25)),
      ),
      child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
        // Color strip
        Container(
          width: 4,
          height: 72,
          margin: const EdgeInsets.only(right: 12),
          decoration: BoxDecoration(
            color: aptColor,
            borderRadius: const BorderRadius.horizontal(left: Radius.circular(12)),
          ),
        ),
        Expanded(
          child: Padding(padding: const EdgeInsets.symmetric(vertical: 10, horizontal: 0), child:
            Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Row(children: [
                Icon(Icons.schedule_rounded, size: 13, color: aptColor),
                const SizedBox(width: 4),
                Text(timeStr, style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: aptColor)),
                const Spacer(),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                  decoration: BoxDecoration(
                    color: _statusColor(status).withValues(alpha: 0.12),
                    borderRadius: BorderRadius.circular(20),
                  ),
                  child: Text(_statusLabel(status),
                    style: TextStyle(fontSize: 10, fontWeight: FontWeight.w700,
                      color: _statusColor(status))),
                ),
                const SizedBox(width: 8),
              ]),
              const SizedBox(height: 3),
              Text(
                apt['title'] as String? ?? '',
                style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13),
                maxLines: 1, overflow: TextOverflow.ellipsis,
              ),
              const SizedBox(height: 2),
              Row(children: [
                if (patient.isNotEmpty) ...[Icon(Icons.pets_rounded, size: 12,
                    color: Theme.of(context).colorScheme.onSurfaceVariant),
                  const SizedBox(width: 4),
                  Text(patient, style: const TextStyle(fontSize: 11)),
                  const SizedBox(width: 8),
                ],
                if (owner.isNotEmpty) ...[Icon(Icons.person_outline_rounded, size: 12,
                    color: Theme.of(context).colorScheme.onSurfaceVariant),
                  const SizedBox(width: 4),
                  Expanded(child: Text(owner,
                    style: const TextStyle(fontSize: 11), maxLines: 1, overflow: TextOverflow.ellipsis)),
                ],
                if (treatment.isNotEmpty && owner.isEmpty) ...[Icon(Icons.medical_services_outlined, size: 12,
                    color: Theme.of(context).colorScheme.onSurfaceVariant),
                  const SizedBox(width: 4),
                  Expanded(child: Text(treatment,
                    style: const TextStyle(fontSize: 11), maxLines: 1, overflow: TextOverflow.ellipsis)),
                ],
              ]),
            ]),
          ),
        ),
      ]),
    );
  }
}

class _ChartCard extends StatelessWidget {
  final String title, subtitle;
  final Widget child;
  const _ChartCard({required this.title, required this.subtitle, required this.child});

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: Theme.of(context).cardTheme.color,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: Theme.of(context).dividerColor),
      ),
      padding: const EdgeInsets.all(16),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text(title, style: Theme.of(context).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w700)),
        Text(subtitle, style: Theme.of(context).textTheme.bodySmall?.copyWith(
          color: Theme.of(context).colorScheme.onSurfaceVariant,
        )),
        const SizedBox(height: 16),
        SizedBox(height: 160, child: child),
      ]),
    );
  }
}

class _Legend extends StatelessWidget {
  final Color color;
  final String label, value;
  const _Legend({required this.color, required this.label, required this.value});

  @override
  Widget build(BuildContext context) {
    return Row(mainAxisSize: MainAxisSize.min, children: [
      Container(width: 10, height: 10, decoration: BoxDecoration(color: color, shape: BoxShape.circle)),
      const SizedBox(width: 8),
      Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text(label, style: Theme.of(context).textTheme.bodySmall),
        Text(value, style: TextStyle(fontWeight: FontWeight.w700, color: color, fontSize: 16)),
      ]),
    ]);
  }
}

class _InvoiceStat extends StatelessWidget {
  final String label, count, amount;
  final Color color;
  const _InvoiceStat({required this.label, required this.count, required this.amount, required this.color});

  @override
  Widget build(BuildContext context) {
    return Column(children: [
      if (count.isNotEmpty) Text(count, style: TextStyle(fontSize: 22, fontWeight: FontWeight.w800, color: color)),
      Text(amount, style: TextStyle(fontSize: count.isEmpty ? 14 : 11, fontWeight: FontWeight.w600, color: color)),
      const SizedBox(height: 2),
      Text(label, style: Theme.of(context).textTheme.bodySmall),
    ]);
  }
}

class _AlertBanner extends StatelessWidget {
  final IconData icon;
  final Color color;
  final String message;
  final String action;
  final VoidCallback onTap;
  const _AlertBanner({required this.icon, required this.color, required this.message, required this.action, required this.onTap});

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
          child: Padding(padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10), child: Row(children: [
            Icon(icon, color: color, size: 18),
            const SizedBox(width: 10),
            Expanded(child: Text(message, style: TextStyle(color: color, fontWeight: FontWeight.w600, fontSize: 13))),
            Text(action, style: TextStyle(color: color, fontWeight: FontWeight.w700, fontSize: 12,
              decoration: TextDecoration.underline, decorationColor: color)),
            const SizedBox(width: 4),
            Icon(Icons.chevron_right_rounded, color: color, size: 16),
          ])),
        ),
      ),
    );
  }
}

class _QuickAction extends StatelessWidget {
  final IconData icon;
  final String label;
  final Color color;
  final VoidCallback onTap;
  const _QuickAction({required this.icon, required this.label, required this.color, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return Material(
      color: color.withValues(alpha: 0.08),
      borderRadius: BorderRadius.circular(12),
      child: InkWell(
        borderRadius: BorderRadius.circular(12),
        onTap: onTap,
        child: Padding(padding: const EdgeInsets.symmetric(vertical: 12), child: Column(children: [
          Icon(icon, color: color, size: 22),
          const SizedBox(height: 4),
          Text(label, style: TextStyle(color: color, fontWeight: FontWeight.w600, fontSize: 10),
            textAlign: TextAlign.center, maxLines: 1, overflow: TextOverflow.ellipsis),
        ])),
      ),
    );
  }
}

class _ErrorView extends StatelessWidget {
  final String error;
  final VoidCallback onRetry;
  const _ErrorView({required this.error, required this.onRetry});

  @override
  Widget build(BuildContext context) {
    return Center(child: Padding(
      padding: const EdgeInsets.all(32),
      child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
        Container(
          padding: const EdgeInsets.all(20),
          decoration: BoxDecoration(
            color: AppTheme.danger.withValues(alpha: 0.1),
            shape: BoxShape.circle,
          ),
          child: Icon(Icons.error_outline_rounded, size: 40, color: AppTheme.danger),
        ),
        const SizedBox(height: 16),
        Text('Fehler', style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold)),
        const SizedBox(height: 8),
        Text(error, textAlign: TextAlign.center, style: Theme.of(context).textTheme.bodyMedium),
        const SizedBox(height: 24),
        FilledButton.icon(onPressed: onRetry, icon: const Icon(Icons.refresh_rounded), label: const Text('Erneut versuchen')),
      ]),
    ));
  }
}
