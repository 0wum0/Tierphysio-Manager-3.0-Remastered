import 'package:flutter/material.dart';
import '../../services/api_service.dart';

class OwnerPortalDashboardScreen extends StatefulWidget {
  const OwnerPortalDashboardScreen({super.key});

  @override
  State<OwnerPortalDashboardScreen> createState() => _OwnerPortalDashboardScreenState();
}

class _OwnerPortalDashboardScreenState extends State<OwnerPortalDashboardScreen> {
  final ApiService _api = ApiService();
  Map<String, dynamic> _dashboard = {};
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _loadData();
  }

  Future<void> _loadData() async {
    setState(() => _loading = true);
    try {
      final data = await _api.ownerPortalDashboard();
      setState(() {
        _dashboard = data;
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
        title: const Text('Besitzerportal'),
        actions: [
          IconButton(
            icon: const Icon(Icons.logout),
            onPressed: _logout,
          ),
        ],
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: _loadData,
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  Card(
                    child: Padding(
                      padding: const EdgeInsets.all(16),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text('Hallo, ${_dashboard['owner_name'] as String? ?? ''}!',
                              style: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold)),
                          const SizedBox(height: 8),
                          Text('Tiere: ${_dashboard['pet_count'] as int? ?? 0}'),
                          Text('Offene Rechnungen: ${_dashboard['open_invoices'] as int? ?? 0}'),
                        ],
                      ),
                    ),
                  ),
                  const SizedBox(height: 16),
                  Card(
                    child: ListTile(
                      leading: const Icon(Icons.pets),
                      title: const Text('Meine Tiere'),
                      trailing: const Icon(Icons.chevron_right),
                      onTap: () => Navigator.pushNamed(context, '/owner-portal/pets'),
                    ),
                  ),
                  Card(
                    child: ListTile(
                      leading: const Icon(Icons.receipt_long),
                      title: const Text('Rechnungen'),
                      trailing: const Icon(Icons.chevron_right),
                      onTap: () => Navigator.pushNamed(context, '/owner-portal/invoices'),
                    ),
                  ),
                  Card(
                    child: ListTile(
                      leading: const Icon(Icons.calendar_today),
                      title: const Text('Termine'),
                      trailing: const Icon(Icons.chevron_right),
                      onTap: () => Navigator.pushNamed(context, '/owner-portal/appointments'),
                    ),
                  ),
                  Card(
                    child: ListTile(
                      leading: const Icon(Icons.message),
                      title: const Text('Nachrichten'),
                      trailing: const Icon(Icons.chevron_right),
                      onTap: () => Navigator.pushNamed(context, '/owner-portal/messages'),
                    ),
                  ),
                  Card(
                    child: ListTile(
                      leading: const Icon(Icons.description),
                      title: const Text('Befundbögen'),
                      trailing: const Icon(Icons.chevron_right),
                      onTap: () => Navigator.pushNamed(context, '/owner-portal/befunde'),
                    ),
                  ),
                ],
              ),
            ),
    );
  }

  Future<void> _logout() async {
    await _api.portalLogout();
    Navigator.pushReplacementNamed(context, '/owner-portal/login');
  }
}
