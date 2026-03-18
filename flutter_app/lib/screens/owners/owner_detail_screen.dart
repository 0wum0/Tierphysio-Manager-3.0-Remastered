import 'package:fl_chart/fl_chart.dart';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../services/api_service.dart';
import '../../core/theme.dart';
import '../../widgets/paw_avatar.dart';

class OwnerDetailScreen extends StatefulWidget {
  final int id;
  const OwnerDetailScreen({super.key, required this.id});

  @override
  State<OwnerDetailScreen> createState() => _OwnerDetailScreenState();
}

class _OwnerDetailScreenState extends State<OwnerDetailScreen>
    with SingleTickerProviderStateMixin {
  final _api = ApiService();
  Map<String, dynamic>? _owner;
  List<dynamic> _invoices = [];
  bool _loading = true;
  String? _error;
  late TabController _tabCtrl;

  @override
  void initState() {
    super.initState();
    _tabCtrl = TabController(length: 3, vsync: this);
    _load();
  }

  @override
  void dispose() { _tabCtrl.dispose(); super.dispose(); }

  Future<void> _load() async {
    setState(() { _loading = true; _error = null; });
    try {
      final ownerData = await _api.ownerShow(widget.id);
      final invData   = await _api.invoices(perPage: 200, search: '');
      final allInv = List<dynamic>.from((invData['items'] as List?) ?? []);
      setState(() {
        _owner    = ownerData;
        _invoices = allInv.where((i) =>
            i['owner_id']?.toString() == widget.id.toString()).toList();
        _loading  = false;
      });
    } catch (e) {
      setState(() { _error = e.toString(); _loading = false; });
    }
  }

  static double _toDouble(dynamic v) =>
      v is num ? v.toDouble() : double.tryParse(v?.toString() ?? '') ?? 0.0;
  String _eur(dynamic v) =>
      NumberFormat.currency(locale: 'de_DE', symbol: '€').format(_toDouble(v));

  // ── Computed stats ──
  double get _totalPaid => _invoices.where((i) => i['status'] == 'paid')
      .fold(0.0, (s, i) => s + _toDouble(i['total_gross']));
  double get _totalOpen => _invoices.where((i) => i['status'] == 'open')
      .fold(0.0, (s, i) => s + _toDouble(i['total_gross']));
  int get _countInvoices => _invoices.length;

  Color _avatarColor(String name) {
    final colors = [AppTheme.primary, AppTheme.secondary, AppTheme.tertiary,
      AppTheme.success, AppTheme.warning, const Color(0xFFEC4899)];
    if (name.isEmpty) return colors[0];
    return colors[name.codeUnitAt(0) % colors.length];
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                  Text(_error!), const SizedBox(height: 12),
                  FilledButton(onPressed: _load, child: const Text('Erneut')),
                ]))
              : _buildContent(),
    );
  }

  Widget _buildContent() {
    final o = _owner!;
    final raw = o['patients'];
    final patients = raw is List ? List<dynamic>.from(raw) : <dynamic>[];
    final lastName = o['last_name'] as String? ?? '';
    final initial  = lastName.isNotEmpty ? lastName[0].toUpperCase() : '?';
    final color    = _avatarColor(lastName);

    return RefreshIndicator(
      onRefresh: _load,
      child: CustomScrollView(
        physics: const AlwaysScrollableScrollPhysics(),
        slivers: [
          // ── Gradient SliverAppBar ──
          SliverAppBar(
            expandedHeight: 200,
            pinned: true,
            backgroundColor: color,
            foregroundColor: Colors.white,
            actions: [
              IconButton(
                icon: const Icon(Icons.receipt_long_rounded),
                tooltip: 'Neue Rechnung',
                onPressed: () => context.push('/rechnungen/neu', extra: {
                  'ownerId': widget.id,
                  'ownerName': '${o['last_name']}, ${o['first_name']}',
                }),
              ),
              IconButton(
                icon: const Icon(Icons.edit_rounded),
                tooltip: 'Bearbeiten',
                onPressed: () => context.push('/tierhalter/${widget.id}/edit').then((_) => _load()),
              ),
            ],
            flexibleSpace: FlexibleSpaceBar(
              collapseMode: CollapseMode.pin,
              background: Stack(children: [
                Container(decoration: BoxDecoration(
                  gradient: LinearGradient(
                    begin: Alignment.topLeft, end: Alignment.bottomRight,
                    colors: [color, Color.lerp(color, AppTheme.secondary, 0.4)!],
                  ),
                )),
                Positioned(right: -30, top: -30, child: Container(width: 160, height: 160,
                  decoration: BoxDecoration(shape: BoxShape.circle,
                    color: Colors.white.withValues(alpha: 0.07)))),
                Positioned(left: 40, bottom: 60, child: Container(width: 80, height: 80,
                  decoration: BoxDecoration(shape: BoxShape.circle,
                    color: Colors.white.withValues(alpha: 0.05)))),
                Positioned.fill(
                  bottom: 48,
                  child: SafeArea(
                    bottom: false,
                    child: Padding(
                      padding: const EdgeInsets.fromLTRB(16, 52, 100, 0),
                      child: Row(crossAxisAlignment: CrossAxisAlignment.center, children: [
                        // Gradient avatar
                        Container(
                          width: 60, height: 60,
                          decoration: BoxDecoration(
                            shape: BoxShape.circle,
                            gradient: LinearGradient(
                              colors: [Colors.white.withValues(alpha: 0.3), Colors.white.withValues(alpha: 0.15)]),
                            border: Border.all(color: Colors.white.withValues(alpha: 0.5), width: 2),
                          ),
                          child: Center(child: Text(initial,
                            style: const TextStyle(color: Colors.white, fontSize: 26, fontWeight: FontWeight.w800))),
                        ),
                        const SizedBox(width: 14),
                        Expanded(child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Text('${o['first_name'] ?? ''} ${o['last_name'] ?? ''}'.trim(),
                              style: const TextStyle(color: Colors.white, fontSize: 20,
                                  fontWeight: FontWeight.w800, letterSpacing: -0.3),
                              overflow: TextOverflow.ellipsis),
                            const SizedBox(height: 3),
                            if ((o['email'] as String? ?? '').isNotEmpty)
                              Row(children: [
                                Icon(Icons.email_rounded, size: 12, color: Colors.white.withValues(alpha: 0.8)),
                                const SizedBox(width: 4),
                                Flexible(child: Text(o['email'] as String,
                                  style: TextStyle(color: Colors.white.withValues(alpha: 0.85), fontSize: 12),
                                  overflow: TextOverflow.ellipsis)),
                              ]),
                            if ((o['phone'] as String? ?? '').isNotEmpty)
                              Row(children: [
                                Icon(Icons.phone_rounded, size: 12, color: Colors.white.withValues(alpha: 0.8)),
                                const SizedBox(width: 4),
                                Text(o['phone'] as String,
                                  style: TextStyle(color: Colors.white.withValues(alpha: 0.85), fontSize: 12)),
                              ]),
                            const SizedBox(height: 4),
                            Row(children: [
                              _StatPill('${patients.length} Tiere', Icons.pets_rounded),
                              const SizedBox(width: 6),
                              _StatPill('$_countInvoices Rechnungen', Icons.receipt_rounded),
                            ]),
                          ],
                        )),
                      ]),
                    ),
                  ),
                ),
              ]),
            ),
            bottom: TabBar(
              controller: _tabCtrl,
              labelColor: Colors.white,
              unselectedLabelColor: Colors.white.withValues(alpha: 0.6),
              indicatorColor: Colors.white,
              indicatorWeight: 3,
              dividerColor: Colors.transparent,
              tabs: const [
                Tab(icon: Icon(Icons.pets_rounded, size: 16), text: 'Tiere'),
                Tab(icon: Icon(Icons.receipt_long_rounded, size: 16), text: 'Rechnungen'),
                Tab(icon: Icon(Icons.bar_chart_rounded, size: 16), text: 'Statistik'),
              ],
            ),
          ),

          // ── Contact quick-actions ──
          SliverToBoxAdapter(child: _buildContactActions(o)),

          // ── Tab content ──
          SliverFillRemaining(
            child: TabBarView(
              controller: _tabCtrl,
              children: [
                _buildPatientsTab(patients),
                _buildInvoicesTab(),
                _buildStatsTab(),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildContactActions(Map<String, dynamic> o) {
    final email = o['email'] as String? ?? '';
    final phone = o['phone'] as String? ?? '';
    if (email.isEmpty && phone.isEmpty) return const SizedBox.shrink();

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
      child: Row(children: [
        if (email.isNotEmpty) Expanded(child: OutlinedButton.icon(
          icon: const Icon(Icons.email_rounded, size: 16),
          label: const Text('E-Mail'),
          onPressed: () => launchUrl(Uri.parse('mailto:$email')),
        )),
        if (email.isNotEmpty && phone.isNotEmpty) const SizedBox(width: 10),
        if (phone.isNotEmpty) Expanded(child: FilledButton.icon(
          icon: const Icon(Icons.phone_rounded, size: 16),
          label: const Text('Anrufen'),
          onPressed: () => launchUrl(Uri.parse('tel:$phone')),
        )),
      ]),
    );
  }

  // ═══════════════════ PATIENTS TAB ════════════════════════

  Widget _buildPatientsTab(List<dynamic> patients) {
    return CustomScrollView(slivers: [
      SliverPadding(
        padding: const EdgeInsets.fromLTRB(12, 8, 12, 0),
        sliver: SliverToBoxAdapter(child: Row(
          mainAxisAlignment: MainAxisAlignment.end,
          children: [
            FilledButton.tonalIcon(
              icon: const Icon(Icons.add_rounded, size: 16),
              label: const Text('Neues Tier'),
              onPressed: () => context.push('/patienten/neu').then((_) => _load()),
            ),
          ],
        )),
      ),
      if (patients.isEmpty)
        const SliverFillRemaining(
          child: Center(child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
            Icon(Icons.pets_rounded, size: 48, color: Colors.grey),
            SizedBox(height: 8),
            Text('Noch keine Tiere.', style: TextStyle(color: Colors.grey)),
          ])),
        )
      else
        SliverPadding(
          padding: const EdgeInsets.all(12),
          sliver: SliverGrid(
            gridDelegate: const SliverGridDelegateWithMaxCrossAxisExtent(
              maxCrossAxisExtent: 220, childAspectRatio: 0.85,
              crossAxisSpacing: 10, mainAxisSpacing: 10),
            delegate: SliverChildBuilderDelegate(
              (ctx, i) {
                final p = patients[i] as Map<String, dynamic>;
                final status = p['status'] as String? ?? 'active';
                final statusColor = status == 'active' ? AppTheme.success
                    : status == 'deceased' ? AppTheme.danger : Colors.grey;
                return GestureDetector(
                  onTap: () => context.push('/patienten/${p['id']}'),
                  child: Card(
                    clipBehavior: Clip.antiAlias,
                    child: Column(children: [
                      Container(
                        height: 80,
                        decoration: BoxDecoration(
                          gradient: LinearGradient(
                            begin: Alignment.topLeft, end: Alignment.bottomRight,
                            colors: [statusColor.withValues(alpha: 0.8), statusColor],
                          ),
                        ),
                        child: Center(child: PawAvatar(
                          photoPath: p['photo_url'] as String?,
                          species: p['species'] as String?,
                          name: p['name'] as String?,
                          radius: 28,
                        )),
                      ),
                      Expanded(child: Padding(
                        padding: const EdgeInsets.all(8),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Text(p['name'] as String? ?? '',
                              style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13),
                              overflow: TextOverflow.ellipsis, maxLines: 1),
                            Text('${p['species'] ?? ''}${(p['breed'] as String? ?? '').isNotEmpty ? ' · ${p['breed']}' : ''}',
                              style: const TextStyle(fontSize: 11, color: Colors.grey),
                              overflow: TextOverflow.ellipsis, maxLines: 1),
                            const SizedBox(height: 4),
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                              decoration: BoxDecoration(
                                color: statusColor.withValues(alpha: 0.12),
                                borderRadius: BorderRadius.circular(4)),
                              child: Text(
                                status == 'active' ? 'Aktiv' : status == 'deceased' ? 'Verstorben' : 'Inaktiv',
                                style: TextStyle(fontSize: 10, color: statusColor, fontWeight: FontWeight.w700)),
                            ),
                          ],
                        ),
                      )),
                    ]),
                  ),
                );
              },
              childCount: patients.length,
            ),
          ),
        ),
    ]);
  }

  // ═══════════════════ INVOICES TAB ════════════════════════

  Widget _buildInvoicesTab() {
    if (_invoices.isEmpty) return const Center(child: Column(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        Icon(Icons.receipt_long_rounded, size: 48, color: Colors.grey),
        SizedBox(height: 8),
        Text('Keine Rechnungen vorhanden.', style: TextStyle(color: Colors.grey)),
      ],
    ));

    return ListView.builder(
      padding: const EdgeInsets.symmetric(vertical: 8),
      itemCount: _invoices.length,
      itemBuilder: (ctx, i) {
        final inv = _invoices[i] as Map<String, dynamic>;
        final status = inv['status'] as String? ?? '';
        final (statusLabel, statusColor) = switch (status) {
          'paid'    => ('Bezahlt',    AppTheme.success),
          'open'    => ('Offen',      AppTheme.primary),
          'overdue' => ('Überfällig', AppTheme.danger),
          _         => ('Entwurf',    Colors.grey),
        };
        return Card(
          margin: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
          child: ListTile(
            leading: CircleAvatar(
              backgroundColor: statusColor.withValues(alpha: 0.12),
              child: Icon(
                status == 'paid' ? Icons.check_circle_rounded
                    : status == 'overdue' ? Icons.warning_rounded : Icons.pending_rounded,
                color: statusColor, size: 20),
            ),
            title: Text(inv['invoice_number'] as String? ?? '',
                style: const TextStyle(fontWeight: FontWeight.w700)),
            subtitle: Text(
              inv['patient_name'] != null ? '${inv['patient_name']}' : _dateStr(inv['issue_date']),
              style: const TextStyle(fontSize: 12)),
            trailing: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              crossAxisAlignment: CrossAxisAlignment.end,
              children: [
                Text(_eur(inv['total_gross']),
                    style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                  decoration: BoxDecoration(
                    color: statusColor.withValues(alpha: 0.12),
                    borderRadius: BorderRadius.circular(4)),
                  child: Text(statusLabel,
                      style: TextStyle(fontSize: 10, color: statusColor, fontWeight: FontWeight.w600)),
                ),
              ],
            ),
            onTap: () => context.push('/rechnungen/${inv['id']}'),
          ),
        );
      },
    );
  }

  // ═══════════════════ STATS TAB ════════════════════════

  Widget _buildStatsTab() {
    if (_invoices.isEmpty) return const Center(
      child: Text('Keine Daten für Statistik', style: TextStyle(color: Colors.grey)));

    return SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        // Summary cards
        Row(children: [
          Expanded(child: _MiniStatCard(
            label: 'Gesamtumsatz',
            value: _eur(_totalPaid + _totalOpen),
            icon: Icons.euro_rounded,
            color: AppTheme.primary,
          )),
          const SizedBox(width: 10),
          Expanded(child: _MiniStatCard(
            label: 'Bezahlt',
            value: _eur(_totalPaid),
            icon: Icons.check_circle_rounded,
            color: AppTheme.success,
          )),
          const SizedBox(width: 10),
          Expanded(child: _MiniStatCard(
            label: 'Ausstehend',
            value: _eur(_totalOpen),
            icon: Icons.pending_rounded,
            color: AppTheme.warning,
          )),
        ]),
        const SizedBox(height: 16),

        // Donut: status distribution
        _OwnerChartCard(
          title: 'Status-Verteilung',
          subtitle: 'Anteil nach Betrag',
          height: 200,
          child: _buildDonut(),
        ),
        const SizedBox(height: 16),

        // Monthly bar chart
        _OwnerChartCard(
          title: 'Monatsverlauf',
          subtitle: 'Umsatz nach Monat',
          height: 180,
          child: _buildMonthlyBar(),
        ),

        const SizedBox(height: 16),
        // Per-patient breakdown
        _OwnerChartCard(
          title: 'Umsatz je Tier',
          subtitle: 'Nur bezahlte Rechnungen',
          height: 160,
          child: _buildPerPatientBar(),
        ),
      ]),
    );
  }

  Widget _buildDonut() {
    final paid    = _invoices.where((i) => i['status'] == 'paid').fold(0.0, (s, i) => s + _toDouble(i['total_gross']));
    final open    = _invoices.where((i) => i['status'] == 'open').fold(0.0, (s, i) => s + _toDouble(i['total_gross']));
    final overdue = _invoices.where((i) => i['status'] == 'overdue').fold(0.0, (s, i) => s + _toDouble(i['total_gross']));

    final sections = [
      if (paid > 0)    PieChartSectionData(value: paid,    color: AppTheme.success, title: '', radius: 44),
      if (open > 0)    PieChartSectionData(value: open,    color: AppTheme.primary, title: '', radius: 44),
      if (overdue > 0) PieChartSectionData(value: overdue, color: AppTheme.danger,  title: '', radius: 44),
    ];
    if (sections.isEmpty) return const Center(child: Text('Keine Daten'));

    return Row(children: [
      Expanded(child: PieChart(PieChartData(
        sections: sections, centerSpaceRadius: 40, sectionsSpace: 2))),
      const SizedBox(width: 12),
      Column(mainAxisAlignment: MainAxisAlignment.center, crossAxisAlignment: CrossAxisAlignment.start, children: [
        if (paid > 0) ...[
          _OLegend(color: AppTheme.success, label: 'Bezahlt',    value: _eur(paid)),
          const SizedBox(height: 6),
        ],
        if (open > 0) ...[
          _OLegend(color: AppTheme.primary, label: 'Offen',      value: _eur(open)),
          const SizedBox(height: 6),
        ],
        if (overdue > 0)
          _OLegend(color: AppTheme.danger,  label: 'Überfällig', value: _eur(overdue)),
      ]),
    ]);
  }

  Widget _buildMonthlyBar() {
    final Map<String, double> monthly = {};
    for (final inv in _invoices) {
      final date = inv['issue_date'] as String? ?? '';
      if (date.length < 7) continue;
      monthly[date.substring(0, 7)] = (monthly[date.substring(0, 7)] ?? 0) + _toDouble(inv['total_gross']);
    }
    if (monthly.isEmpty) return const Center(child: Text('Keine Daten'));
    final sorted = monthly.entries.toList()..sort((a, b) => a.key.compareTo(b.key));
    final last6  = sorted.length > 6 ? sorted.sublist(sorted.length - 6) : sorted;
    final maxY   = last6.map((e) => e.value).reduce((a, b) => a > b ? a : b);

    return BarChart(BarChartData(
      maxY: maxY * 1.25,
      gridData: const FlGridData(show: true, drawVerticalLine: false),
      borderData: FlBorderData(show: false),
      titlesData: FlTitlesData(
        bottomTitles: AxisTitles(sideTitles: SideTitles(showTitles: true, reservedSize: 22,
          getTitlesWidget: (v, _) {
            final idx = v.toInt();
            if (idx < 0 || idx >= last6.length) return const SizedBox();
            final p = last6[idx].key.split('-');
            return Text(p.length >= 2 ? '${p[1]}/${p[0].substring(2)}' : '',
                style: const TextStyle(fontSize: 9));
          },
        )),
        leftTitles: AxisTitles(sideTitles: SideTitles(showTitles: true, reservedSize: 44,
          getTitlesWidget: (v, _) => Text(
            NumberFormat.compactCurrency(locale: 'de_DE', symbol: '€').format(v),
            style: const TextStyle(fontSize: 9)),
        )),
        rightTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
        topTitles:   const AxisTitles(sideTitles: SideTitles(showTitles: false)),
      ),
      barGroups: last6.asMap().entries.map((e) => BarChartGroupData(x: e.key, barRods: [
        BarChartRodData(
          toY: e.value.value,
          gradient: LinearGradient(
            begin: Alignment.bottomCenter, end: Alignment.topCenter,
            colors: [AppTheme.secondary.withValues(alpha: 0.7), AppTheme.secondary]),
          width: 16, borderRadius: const BorderRadius.vertical(top: Radius.circular(6)),
        ),
      ])).toList(),
    ));
  }

  Widget _buildPerPatientBar() {
    final Map<String, double> byPatient = {};
    for (final inv in _invoices) {
      if (inv['status'] != 'paid') continue;
      final name = inv['patient_name'] as String? ?? 'Ohne Patient';
      byPatient[name] = (byPatient[name] ?? 0) + _toDouble(inv['total_gross']);
    }
    if (byPatient.isEmpty) return const Center(child: Text('Keine bezahlten Rechnungen'));
    final sorted = byPatient.entries.toList()..sort((a, b) => b.value.compareTo(a.value));
    final maxVal = sorted.first.value;
    final colors = [AppTheme.primary, AppTheme.secondary, AppTheme.tertiary, AppTheme.success, AppTheme.warning];

    return Column(mainAxisSize: MainAxisSize.min, children: sorted.take(5).toList().asMap().entries.map((e) {
      final c = colors[e.key % colors.length];
      return Padding(
        padding: const EdgeInsets.symmetric(vertical: 3),
        child: Row(children: [
          SizedBox(width: 90, child: Text(e.value.key,
            overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 11))),
          const SizedBox(width: 6),
          Expanded(child: ClipRRect(
            borderRadius: BorderRadius.circular(4),
            child: LinearProgressIndicator(
              value: maxVal > 0 ? e.value.value / maxVal : 0,
              minHeight: 14,
              backgroundColor: c.withValues(alpha: 0.1),
              valueColor: AlwaysStoppedAnimation(c),
            ),
          )),
          const SizedBox(width: 6),
          Text(_eur(e.value.value), style: TextStyle(fontSize: 10, fontWeight: FontWeight.w700, color: c)),
        ]),
      );
    }).toList());
  }

  String _dateStr(dynamic d) {
    if (d == null) return '';
    try { return DateFormat('dd.MM.yyyy').format(DateTime.parse(d.toString())); } catch (_) { return d.toString(); }
  }
}

// ── Helper widgets ────────────────────────────────────────────────────────────

class _StatPill extends StatelessWidget {
  final String label;
  final IconData icon;
  const _StatPill(this.label, this.icon);

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.2),
        borderRadius: BorderRadius.circular(20)),
      child: Row(mainAxisSize: MainAxisSize.min, children: [
        Icon(icon, size: 11, color: Colors.white),
        const SizedBox(width: 3),
        Text(label, style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w600)),
      ]),
    );
  }
}

class _MiniStatCard extends StatelessWidget {
  final String label, value;
  final IconData icon;
  final Color color;
  const _MiniStatCard({required this.label, required this.value, required this.icon, required this.color});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(10),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.07),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: color.withValues(alpha: 0.2)),
      ),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Icon(icon, color: color, size: 18),
        const SizedBox(height: 6),
        Text(value, style: TextStyle(color: color, fontWeight: FontWeight.w800, fontSize: 13, letterSpacing: -0.3),
            overflow: TextOverflow.ellipsis),
        Text(label, style: Theme.of(context).textTheme.bodySmall?.copyWith(fontSize: 10)),
      ]),
    );
  }
}

class _OwnerChartCard extends StatelessWidget {
  final String title, subtitle;
  final Widget child;
  final double height;
  const _OwnerChartCard({required this.title, required this.subtitle, required this.child, required this.height});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Theme.of(context).cardTheme.color ?? Theme.of(context).colorScheme.surface,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: Theme.of(context).dividerColor),
      ),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text(title, style: Theme.of(context).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w700)),
        Text(subtitle, style: Theme.of(context).textTheme.bodySmall?.copyWith(
          color: Theme.of(context).colorScheme.onSurfaceVariant, fontSize: 11)),
        const SizedBox(height: 12),
        SizedBox(height: height, child: child),
      ]),
    );
  }
}

class _OLegend extends StatelessWidget {
  final Color color;
  final String label, value;
  const _OLegend({required this.color, required this.label, required this.value});

  @override
  Widget build(BuildContext context) {
    return Row(mainAxisSize: MainAxisSize.min, children: [
      Container(width: 9, height: 9, decoration: BoxDecoration(color: color, shape: BoxShape.circle)),
      const SizedBox(width: 5),
      Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text(label, style: const TextStyle(fontSize: 10, color: Colors.grey)),
        Text(value, style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: color)),
      ]),
    ]);
  }
}
