import 'package:flutter/material.dart';
import 'package:shimmer/shimmer.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../services/api_service.dart';
import '../../core/theme.dart';

class OwnerPortalBefundeScreen extends StatefulWidget {
  const OwnerPortalBefundeScreen({super.key});

  @override
  State<OwnerPortalBefundeScreen> createState() => _OwnerPortalBefundeScreenState();
}

class _OwnerPortalBefundeScreenState extends State<OwnerPortalBefundeScreen> {
  final ApiService _api = ApiService();
  List<Map<String, dynamic>> _befunde = [];
  bool _loading = true;
  String? _error;

  static const _red = AppTheme.danger;

  @override
  void initState() { super.initState(); _loadBefunde(); }

  Future<void> _loadBefunde() async {
    setState(() { _loading = true; _error = null; });
    try {
      final list = await _api.ownerPortalBefunde();
      setState(() {
        _befunde = list.map((e) => Map<String, dynamic>.from(e as Map)).toList();
        _loading = false;
      });
    } catch (e) { setState(() { _loading = false; _error = e.toString(); }); }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Befundbögen'),
        backgroundColor: _red, foregroundColor: Colors.white),
      body: _loading
          ? Shimmer.fromColors(baseColor: Colors.grey.shade300, highlightColor: Colors.grey.shade100,
              child: ListView.builder(padding: const EdgeInsets.all(16), itemCount: 4,
                itemBuilder: (_, __) => Container(height: 80, margin: const EdgeInsets.only(bottom: 12),
                  decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(16)))))
          : _error != null
              ? Center(child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                  const Icon(Icons.cloud_off_rounded, size: 48, color: Colors.grey),
                  const SizedBox(height: 12), Text(_error!, textAlign: TextAlign.center),
                  const SizedBox(height: 16),
                  FilledButton.icon(onPressed: _loadBefunde,
                    icon: const Icon(Icons.refresh_rounded), label: const Text('Erneut'),
                    style: FilledButton.styleFrom(backgroundColor: _red))]))
              : RefreshIndicator(onRefresh: _loadBefunde, color: _red,
                  child: _befunde.isEmpty
                    ? Center(child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                        const Text('📋', style: TextStyle(fontSize: 52)),
                        const SizedBox(height: 16),
                        const Text('Keine Befundbögen vorhanden',
                            style: TextStyle(fontSize: 17, fontWeight: FontWeight.w700)),
                        const SizedBox(height: 8),
                        Text('Befundbögen werden von der Praxis erstellt.',
                            style: TextStyle(fontSize: 13, color: Colors.grey.shade500))]))
                    : ListView.builder(
                        padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
                        itemCount: _befunde.length,
                        itemBuilder: (context, i) {
                          final b    = _befunde[i];
                          final title  = b['title'] as String? ?? b['bezeichnung'] as String? ?? 'Befundbogen';
                          final pet    = b['patient_name'] as String? ?? b['tier'] as String? ?? '';
                          final date   = b['created_at'] as String? ?? b['datum'] as String? ?? '';
                          final id     = b['id'] as int? ?? 0;
                          final isDark = Theme.of(context).colorScheme.brightness == Brightness.dark;

                          return TweenAnimationBuilder<double>(
                            tween: Tween(begin: 0, end: 1),
                            duration: Duration(milliseconds: 280 + i * 50),
                            curve: Curves.easeOutCubic,
                            builder: (_, v, child) => Opacity(opacity: v,
                              child: Transform.translate(offset: Offset(0, 16*(1-v)), child: child)),
                            child: Padding(
                              padding: const EdgeInsets.only(bottom: 12),
                              child: Container(
                                decoration: BoxDecoration(
                                  color: isDark ? const Color(0xFF1A0A0A) : Colors.white,
                                  borderRadius: BorderRadius.circular(18),
                                  border: Border.all(color: _red.withValues(alpha: 0.15)),
                                ),
                                child: Padding(
                                  padding: const EdgeInsets.all(14),
                                  child: Row(children: [
                                    Container(width: 46, height: 46,
                                      decoration: BoxDecoration(color: _red.withValues(alpha: 0.1),
                                        borderRadius: BorderRadius.circular(12)),
                                      child: const Icon(Icons.description_rounded, color: _red, size: 22)),
                                    const SizedBox(width: 12),
                                    Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                                      Text(title, style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w700)),
                                      if (pet.isNotEmpty) Text(pet,
                                          style: TextStyle(fontSize: 12, color: Colors.grey.shade500)),
                                      if (date.isNotEmpty) Text(date.length > 10 ? date.substring(0, 10) : date,
                                          style: TextStyle(fontSize: 11, color: Colors.grey.shade400)),
                                    ])),
                                    GestureDetector(
                                      onTap: () => _openPdf(id),
                                      child: Container(
                                        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
                                        decoration: BoxDecoration(
                                          color: _red.withValues(alpha: 0.1),
                                          borderRadius: BorderRadius.circular(10),
                                          border: Border.all(color: _red.withValues(alpha: 0.3)),
                                        ),
                                        child: const Row(mainAxisSize: MainAxisSize.min, children: [
                                          Icon(Icons.picture_as_pdf_rounded, color: _red, size: 16),
                                          SizedBox(width: 5),
                                          Text('PDF', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: _red)),
                                        ]),
                                      ),
                                    ),
                                  ]),
                                ),
                              ),
                            ),
                          );
                        }),
              ),
    );
  }

  Future<void> _openPdf(int id) async {
    if (id == 0) return;
    final url = await _api.ownerPortalBefundPdfUrl(id);
    if (url.isNotEmpty) {
      final uri = Uri.parse(url);
      if (await canLaunchUrl(uri)) await launchUrl(uri);
    }
  }
}
