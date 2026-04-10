import 'package:flutter/material.dart';
import '../../services/api_service.dart';

class OwnerPortalAppointmentsScreen extends StatefulWidget {
  const OwnerPortalAppointmentsScreen({super.key});

  @override
  State<OwnerPortalAppointmentsScreen> createState() => _OwnerPortalAppointmentsScreenState();
}

class _OwnerPortalAppointmentsScreenState extends State<OwnerPortalAppointmentsScreen> {
  final ApiService _api = ApiService();
  List<Map<String, dynamic>> _appointments = [];
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _loadAppointments();
  }

  Future<void> _loadAppointments() async {
    setState(() => _loading = true);
    try {
      final list = await _api.ownerPortalAppointments();
      setState(() {
        _appointments = list.map((e) => Map<String, dynamic>.from(e as Map)).toList();
        _loading = false;
      });
    } catch (_) {
      setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Termine'),
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: _loadAppointments,
              child: ListView.builder(
                padding: const EdgeInsets.all(16),
                itemCount: _appointments.length,
                itemBuilder: (context, index) {
                  final appointment = _appointments[index];
                  return Card(
                    margin: const EdgeInsets.only(bottom: 12),
                    child: ListTile(
                      title: Text(appointment['title'] as String? ?? ''),
                      subtitle: Text('${appointment['appointment_date'] as String? ?? ''} - ${appointment['time'] as String? ?? ''}'),
                      trailing: Chip(
                        label: Text(appointment['status'] as String? ?? ''),
                      ),
                    ),
                  );
                },
              ),
            ),
    );
  }
}
