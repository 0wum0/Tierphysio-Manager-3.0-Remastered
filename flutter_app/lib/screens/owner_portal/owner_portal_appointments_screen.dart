import 'package:flutter/material.dart';
import 'package:shimmer/shimmer.dart';
import '../../services/api_service.dart';
import '../../core/theme.dart';

class OwnerPortalAppointmentsScreen extends StatefulWidget {
  const OwnerPortalAppointmentsScreen({super.key});

  @override
  State<OwnerPortalAppointmentsScreen> createState() => _OwnerPortalAppointmentsScreenState();
}

class _OwnerPortalAppointmentsScreenState extends State<OwnerPortalAppointmentsScreen> {
  final ApiService _api = ApiService();
  List<Map<String, dynamic>> _appointments = [];
  bool _loading = true;
  String? _error;

  static const _blue = Color(0xFF3B82F6);

  @override
  void initState() { super.initState(); _loadAppointments(); }

  Future<void> _loadAppointments() async {
    setState(() { _loading = true; _error = null; });
    try {
      final list = await _api.ownerPortalAppointments();
      setState(() { _appointments = list.map((e) => Map<String, dynamic>.from(e as Map)).toList(); _loading = false; });
    } catch (e) { setState(() { _loading = false; _error = e.toString(); }); }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Termine'),
        backgroundColor: _blue, foregroundColor: Colors.white),
      body: _loading
        ? Shimmer.fromColors(baseColor: Colors.grey.shade300, highlightColor: Colors.grey.shade100,
            child: ListView.builder(padding: const EdgeInsets.all(16), itemCount: 4,
              itemBuilder: (_, __) => Container(height: 90, margin: const EdgeInsets.only(bottom: 12),
                decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(16)))))
        : _error != null
            ? Center(child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                const Icon(Icons.cloud_off_rounded, size: 48, color: Colors.grey),
                const SizedBox(height: 12), Text(_error!, textAlign: TextAlign.center),
                const SizedBox(height: 16),
                FilledButton.icon(onPressed: _loadAppointments,
                  icon: const Icon(Icons.refresh_rounded), label: const Text('Erneut'),
                  style: FilledButton.styleFrom(backgroundColor: _blue))]))
            : RefreshIndicator(onRefresh: _loadAppointments, color: _blue,
                child: _appointments.isEmpty
                  ? Center(child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                      const Text('📅', style: TextStyle(fontSize: 52)),
                      const SizedBox(height: 16),
                      const Text('Keine Termine vorhanden',
                          style: TextStyle(fontSize: 17, fontWeight: FontWeight.w700)),
                      const SizedBox(height: 8),
                      Text('Ihre kommenden Termine erscheinen hier.',
                          style: TextStyle(fontSize: 13, color: Colors.grey.shade500))]))
                  : ListView.builder(
                      padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
                      itemCount: _appointments.length,
                      itemBuilder: (context, i) {
                        final a = _appointments[i];
                        final title  = a['title'] as String? ?? a['type'] as String? ?? 'Termin';
                        final date   = a['appointment_date'] as String? ?? a['datum'] as String? ?? '';
                        final time   = a['time'] as String? ?? a['uhrzeit'] as String? ?? '';
                        final status = a['status'] as String? ?? '';
                        final pet    = a['patient_name'] as String? ?? a['pet_name'] as String? ?? '';
                        final statusColor = status == 'confirmed' || status == 'bestätigt'
                            ? AppTheme.success : status == 'cancelled' || status == 'storniert'
                            ? AppTheme.danger : AppTheme.warning;
                        final statusLabel = status == 'confirmed' || status == 'bestätigt' ? 'Bestätigt'
                            : status == 'cancelled' || status == 'storniert' ? 'Storniert' : 'Ausstehend';

                        return TweenAnimationBuilder<double>(
                          tween: Tween(begin: 0, end: 1),
                          duration: Duration(milliseconds: 280 + i * 50),
                          curve: Curves.easeOutCubic,
                          builder: (_, v, child) => Opacity(opacity: v,
                            child: Transform.translate(offset: Offset(0, 16 * (1-v)), child: child)),
                          child: Padding(
                            padding: const EdgeInsets.only(bottom: 12),
                            child: Container(
                              decoration: BoxDecoration(
                                color: Theme.of(context).colorScheme.brightness == Brightness.dark
                                    ? const Color(0xFF111B2E) : Colors.white,
                                borderRadius: BorderRadius.circular(18),
                                border: Border.all(color: _blue.withValues(alpha: 0.15)),
                              ),
                              child: Padding(
                                padding: const EdgeInsets.all(16),
                                child: Row(children: [
                                  Container(
                                    width: 48, height: 48,
                                    decoration: BoxDecoration(
                                      color: _blue.withValues(alpha: 0.1),
                                      borderRadius: BorderRadius.circular(12),
                                    ),
                                    child: const Icon(Icons.calendar_month_rounded, color: _blue, size: 22),
                                  ),
                                  const SizedBox(width: 14),
                                  Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                                    Text(title, style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w700)),
                                    if (date.isNotEmpty) Text('$date${time.isNotEmpty ? " · $time" : ""}',
                                      style: TextStyle(fontSize: 12, color: Colors.grey.shade500)),
                                    if (pet.isNotEmpty) Text(pet,
                                      style: TextStyle(fontSize: 11, color: Colors.grey.shade400)),
                                  ])),
                                  Container(
                                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                                    decoration: BoxDecoration(
                                      color: statusColor.withValues(alpha: 0.12),
                                      borderRadius: BorderRadius.circular(8),
                                      border: Border.all(color: statusColor.withValues(alpha: 0.3)),
                                    ),
                                    child: Text(statusLabel,
                                      style: TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: statusColor)),
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
}
