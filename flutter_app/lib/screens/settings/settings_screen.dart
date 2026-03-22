import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:package_info_plus/package_info_plus.dart';
import '../../services/api_service.dart';
import '../../services/theme_service.dart';
import '../../core/theme.dart';

class SettingsScreen extends StatefulWidget {
  const SettingsScreen({super.key});
  @override
  State<SettingsScreen> createState() => _SettingsScreenState();
}

class _SettingsScreenState extends State<SettingsScreen>
    with SingleTickerProviderStateMixin {
  final _api = ApiService();
  late TabController _tabs;
  bool _loading = true;
  bool _saving  = false;
  String _appVersion = '…';

  // Praxis
  final _nameCtrl    = TextEditingController();
  final _addrCtrl    = TextEditingController();
  final _phoneCtrl   = TextEditingController();
  final _emailCtrl   = TextEditingController();
  final _urlCtrl     = TextEditingController();

  // Finanzen
  final _taxCtrl     = TextEditingController();
  final _prefixCtrl  = TextEditingController();
  bool _kleinunternehmer = false;

  // Portal
  final _portalUrlCtrl = TextEditingController();
  bool _portalHomework = true;

  @override
  void dispose() {
    _tabs.dispose();
    _nameCtrl.dispose(); _addrCtrl.dispose(); _phoneCtrl.dispose();
    _emailCtrl.dispose(); _urlCtrl.dispose();
    _taxCtrl.dispose(); _prefixCtrl.dispose();
    _portalUrlCtrl.dispose();
    super.dispose();
  }

  @override
  void initState() {
    super.initState();
    _tabs = TabController(length: 3, vsync: this);
    _load();
    PackageInfo.fromPlatform().then((info) {
      if (mounted) setState(() => _appVersion = '${info.version}+${info.buildNumber}');
    });
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final s = await _api.settings();
      setState(() {
        _nameCtrl.text    = s['company_name']    as String? ?? '';
        _addrCtrl.text    = s['company_address'] as String? ?? '';
        _phoneCtrl.text   = s['company_phone']   as String? ?? '';
        _emailCtrl.text   = s['company_email']   as String? ?? '';
        _urlCtrl.text     = s['app_url']         as String? ?? '';
        _taxCtrl.text     = s['tax_rate']        as String? ?? '19';
        _prefixCtrl.text  = s['invoice_prefix']  as String? ?? 'RE';
        _kleinunternehmer = (s['kleinunternehmer'] as String? ?? '0') == '1';
        _portalUrlCtrl.text = s['portal_base_url'] as String? ?? '';
        _portalHomework   = (s['portal_show_homework'] as String? ?? '1') == '1';
        _loading = false;
      });
    } catch (e) {
      setState(() => _loading = false);
      _snack(e.toString(), error: true);
    }
  }

  Future<void> _save() async {
    setState(() => _saving = true);
    try {
      await _api.settingsUpdate({
        'company_name':       _nameCtrl.text.trim(),
        'company_address':    _addrCtrl.text.trim(),
        'company_phone':      _phoneCtrl.text.trim(),
        'company_email':      _emailCtrl.text.trim(),
        'app_url':            _urlCtrl.text.trim(),
        'tax_rate':           _taxCtrl.text.trim(),
        'invoice_prefix':     _prefixCtrl.text.trim(),
        'kleinunternehmer':   _kleinunternehmer ? '1' : '0',
        'portal_base_url':    _portalUrlCtrl.text.trim(),
        'portal_show_homework': _portalHomework ? '1' : '0',
      });
      _snack('Einstellungen gespeichert ✓');
    } catch (e) {
      _snack(e.toString(), error: true);
    } finally {
      setState(() => _saving = false);
    }
  }

  void _snack(String msg, {bool error = false}) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(
      content: Text(msg),
      backgroundColor: error ? AppTheme.danger : AppTheme.success,
      behavior: SnackBarBehavior.floating,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
    ));
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Einstellungen'),
        actions: [
          if (!_loading)
            _saving
                ? const Padding(
                    padding: EdgeInsets.symmetric(horizontal: 16),
                    child: Center(child: SizedBox(
                      width: 20, height: 20,
                      child: CircularProgressIndicator(strokeWidth: 2))),
                  )
                : FilledButton.icon(
                    onPressed: _save,
                    icon: const Icon(Icons.save_rounded, size: 18),
                    label: const Text('Speichern'),
                    style: FilledButton.styleFrom(
                      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                      visualDensity: VisualDensity.compact,
                    ),
                  ),
          const SizedBox(width: 8),
        ],
        bottom: TabBar(
          controller: _tabs,
          tabs: const [
            Tab(icon: Icon(Icons.business_rounded, size: 16), text: 'Praxis'),
            Tab(icon: Icon(Icons.euro_rounded, size: 16),     text: 'Finanzen'),
            Tab(icon: Icon(Icons.phone_iphone_rounded, size: 16), text: 'App'),
          ],
        ),
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : TabBarView(controller: _tabs, children: [
              _buildPraxisTab(),
              _buildFinanzenTab(),
              _buildAppTab(),
            ]),
    );
  }

  // ── Tab 1: Praxis ─────────────────────────────────────────────────────────

  Widget _buildPraxisTab() {
    return SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      child: Column(children: [
        _card(
          icon: Icons.business_rounded,
          color: AppTheme.primary,
          title: 'Praxisdaten',
          child: Column(children: [
            _field(_nameCtrl,  'Praxisname *', Icons.business_rounded),
            _field(_addrCtrl,  'Adresse',      Icons.location_on_rounded, maxLines: 2),
            _field(_phoneCtrl, 'Telefon',      Icons.phone_rounded, type: TextInputType.phone),
            _field(_emailCtrl, 'E-Mail',       Icons.email_rounded,  type: TextInputType.emailAddress),
            _field(_urlCtrl,   'Website-URL',  Icons.language_rounded, type: TextInputType.url,
              hint: 'https://meine-praxis.de'),
          ]),
        ),
        const SizedBox(height: 16),
        _card(
          icon: Icons.public_rounded,
          color: AppTheme.secondary,
          title: 'Besitzer-Portal',
          child: Column(children: [
            _field(_portalUrlCtrl, 'Portal-URL', Icons.link_rounded,
              type: TextInputType.url, hint: 'https://portal.meine-praxis.de'),
            const SizedBox(height: 4),
            _switchTile(
              'Hausaufgaben-Tab anzeigen',
              'Zeigt den Hausaufgaben-Tab im Besitzer-Portal',
              Icons.assignment_rounded,
              AppTheme.secondary,
              _portalHomework,
              (v) => setState(() => _portalHomework = v),
            ),
          ]),
        ),
      ]),
    );
  }

  // ── Tab 2: Finanzen ───────────────────────────────────────────────────────

  Widget _buildFinanzenTab() {
    return SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      child: Column(children: [
        _card(
          icon: Icons.receipt_long_rounded,
          color: AppTheme.warning,
          title: 'Rechnungen',
          child: Column(children: [
            _field(_prefixCtrl, 'Rechnungsnummer-Präfix', Icons.tag_rounded,
              hint: 'z.B. RE oder INV'),
            _field(_taxCtrl, 'Mehrwertsteuersatz (%)', Icons.percent_rounded,
              type: TextInputType.number, hint: '19'),
            const SizedBox(height: 4),
            _switchTile(
              'Kleinunternehmerregelung',
              'Keine MwSt. auf Rechnungen (§19 UStG)',
              Icons.store_rounded,
              AppTheme.warning,
              _kleinunternehmer,
              (v) => setState(() => _kleinunternehmer = v),
            ),
          ]),
        ),
        const SizedBox(height: 16),
        _infoCard(
          icon: Icons.info_outline_rounded,
          text: 'SMTP-Einstellungen, Erinnerungsintervalle und E-Mail-Templates können im Web-Backend unter Einstellungen → E-Mail konfiguriert werden.',
        ),
      ]),
    );
  }

  // ── Tab 3: App ────────────────────────────────────────────────────────────

  Widget _buildAppTab() {
    final themeService = context.watch<ThemeService>();
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final cs = Theme.of(context).colorScheme;

    return SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      child: Column(children: [
        _card(
          icon: Icons.palette_rounded,
          color: AppTheme.tertiary,
          title: 'Erscheinungsbild',
          child: Column(children: [
            const SizedBox(height: 4),
            ...[
              (ThemeMode.system, 'Systemstandard', Icons.brightness_auto_rounded,
               'Folgt dem System-Theme'),
              (ThemeMode.light,  'Hell',           Icons.light_mode_rounded,
               'Immer helles Design'),
              (ThemeMode.dark,   'Dunkel',         Icons.dark_mode_rounded,
               'Immer dunkles Design'),
            ].map((t) {
              final (mode, label, icon, sub) = t;
              final selected = themeService.mode == mode;
              return Padding(
                padding: const EdgeInsets.only(bottom: 8),
                child: InkWell(
                  borderRadius: BorderRadius.circular(12),
                  onTap: () => themeService.setMode(mode),
                  child: Container(
                    padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                    decoration: BoxDecoration(
                      borderRadius: BorderRadius.circular(12),
                      color: selected
                          ? AppTheme.tertiary.withValues(alpha: isDark ? 0.18 : 0.08)
                          : cs.surfaceContainerHighest.withValues(alpha: 0.4),
                      border: Border.all(
                        color: selected
                            ? AppTheme.tertiary.withValues(alpha: 0.5)
                            : cs.outline.withValues(alpha: 0.15),
                        width: selected ? 1.5 : 1,
                      ),
                    ),
                    child: Row(children: [
                      Icon(icon,
                        color: selected ? AppTheme.tertiary : cs.onSurfaceVariant,
                        size: 22),
                      const SizedBox(width: 12),
                      Expanded(child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(label, style: TextStyle(
                            fontWeight: selected ? FontWeight.w700 : FontWeight.w500,
                            color: selected ? AppTheme.tertiary : null,
                          )),
                          Text(sub, style: TextStyle(
                            fontSize: 11,
                            color: cs.onSurfaceVariant,
                          )),
                        ],
                      )),
                      if (selected)
                        Icon(Icons.check_circle_rounded,
                          color: AppTheme.tertiary, size: 20),
                    ]),
                  ),
                ),
              );
            }),
          ]),
        ),
        const SizedBox(height: 16),
        _card(
          icon: Icons.info_rounded,
          color: AppTheme.primary,
          title: 'App-Info',
          child: Column(children: [
            _infoRow(Icons.apps_rounded,         'App-Name',   'TheraPano'),
            _infoRow(Icons.tag_rounded,          'Version',    _appVersion),
            _infoRow(Icons.business_rounded,     'Entwickler', 'Tierphysio Manager'),
            _infoRow(Icons.code_rounded,         'Framework',  'Flutter'),
            _infoRow(Icons.android_rounded,      'Plattform',  'Android'),
          ]),
        ),
      ]),
    );
  }

  // ── Helpers ───────────────────────────────────────────────────────────────

  Widget _card({
    required IconData icon,
    required Color color,
    required String title,
    required Widget child,
  }) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    return Container(
      width: double.infinity,
      decoration: BoxDecoration(
        color: Theme.of(context).cardTheme.color,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: Theme.of(context).dividerColor),
      ),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 14, 16, 8),
          child: Row(children: [
            Container(
              padding: const EdgeInsets.all(8),
              decoration: BoxDecoration(
                color: color.withValues(alpha: isDark ? 0.2 : 0.1),
                borderRadius: BorderRadius.circular(10),
              ),
              child: Icon(icon, color: color, size: 18),
            ),
            const SizedBox(width: 10),
            Text(title, style: TextStyle(
              fontWeight: FontWeight.w700,
              fontSize: 15,
              color: color,
            )),
          ]),
        ),
        const Divider(height: 1),
        Padding(padding: const EdgeInsets.all(16), child: child),
      ]),
    );
  }

  Widget _field(
    TextEditingController ctrl,
    String label,
    IconData icon, {
    TextInputType type = TextInputType.text,
    int maxLines = 1,
    String? hint,
  }) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: TextField(
        controller: ctrl,
        keyboardType: type,
        maxLines: maxLines,
        decoration: InputDecoration(
          labelText: label,
          hintText: hint,
          prefixIcon: Icon(icon, size: 18),
          isDense: true,
        ),
      ),
    );
  }

  Widget _switchTile(
    String title,
    String sub,
    IconData icon,
    Color color,
    bool value,
    ValueChanged<bool> onChanged,
  ) {
    return Padding(
      padding: const EdgeInsets.only(top: 4),
      child: Row(children: [
        Icon(icon, size: 18, color: color.withValues(alpha: 0.7)),
        const SizedBox(width: 10),
        Expanded(child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(title, style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 14)),
            Text(sub, style: TextStyle(fontSize: 11,
              color: Theme.of(context).colorScheme.onSurfaceVariant)),
          ],
        )),
        Switch(value: value, onChanged: onChanged, activeThumbColor: color),
      ]),
    );
  }

  Widget _infoRow(IconData icon, String label, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Row(children: [
        Icon(icon, size: 16,
          color: Theme.of(context).colorScheme.onSurfaceVariant),
        const SizedBox(width: 10),
        Text(label, style: TextStyle(
          fontSize: 13,
          color: Theme.of(context).colorScheme.onSurfaceVariant,
        )),
        const Spacer(),
        Text(value, style: const TextStyle(
          fontSize: 13, fontWeight: FontWeight.w600)),
      ]),
    );
  }

  Widget _infoCard({required IconData icon, required String text}) {
    final cs = Theme.of(context).colorScheme;
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: cs.surfaceContainerHighest.withValues(alpha: 0.5),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: cs.outline.withValues(alpha: 0.2)),
      ),
      child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Icon(icon, size: 18, color: cs.onSurfaceVariant),
        const SizedBox(width: 10),
        Expanded(child: Text(text, style: TextStyle(
          fontSize: 12, color: cs.onSurfaceVariant, height: 1.5))),
      ]),
    );
  }
}
