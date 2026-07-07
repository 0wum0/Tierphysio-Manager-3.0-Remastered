import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../core/theme.dart';
import '../services/portal_auth_service.dart';
import '../services/portal_api_service.dart';
import '../widgets/auth_image.dart';
import '../widgets/branded_loading.dart';
import 'pet_detail_screen.dart';

class PetsScreen extends StatefulWidget {
  const PetsScreen({super.key});
  @override
  State<PetsScreen> createState() => _PetsScreenState();
}

class _PetsScreenState extends State<PetsScreen> {
  List<dynamic> _pets  = [];
  bool _loading = true;
  String? _error;

  @override
  void initState() { super.initState(); _load(); }

  Future<void> _load() async {
    setState(() { _loading = true; _error = null; });
    try {
      final auth = context.read<PortalAuthService>();
      final data = await PortalApiService(token: auth.token).get('/api/portal/mobile/tiere');
      final raw  = data is List ? data : (data['pets'] ?? []);
      if (mounted) setState(() { _pets = List<dynamic>.from(raw); _loading = false; });
    } catch (e) {
      if (mounted) setState(() { _error = e.toString(); _loading = false; });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Meine Tiere')),
      body: _loading
          ? const BrandedLoading()
          : _error != null
              ? _buildError()
              : RefreshIndicator(onRefresh: _load, child: _pets.isEmpty
                  ? _buildEmpty()
                  : ListView.builder(
                      padding: const EdgeInsets.only(bottom: 24),
                      itemCount: _pets.length,
                      itemBuilder: (_, i) => _PetTile(pet: _pets[i]),
                    )),
    );
  }

  Widget _buildEmpty() => Center(child: Column(mainAxisSize: MainAxisSize.min, children: [
    Icon(Icons.pets_rounded, size: 64, color: AppTheme.primary.withValues(alpha: 0.3)),
    const SizedBox(height: 16),
    const Text('Keine Tiere gefunden', style: TextStyle(fontWeight: FontWeight.w600)),
    const SizedBox(height: 8),
    Text('Ihre Tiere werden hier angezeigt.', style: TextStyle(color: Theme.of(context).colorScheme.onSurfaceVariant)),
  ]));

  Widget _buildError() => Center(child: Padding(padding: const EdgeInsets.all(24), child: Column(mainAxisSize: MainAxisSize.min, children: [
    const Icon(Icons.wifi_off_rounded, size: 48, color: AppTheme.danger),
    const SizedBox(height: 16),
    Text(_error!, textAlign: TextAlign.center),
    const SizedBox(height: 24),
    FilledButton.icon(onPressed: _load, icon: const Icon(Icons.refresh_rounded), label: const Text('Erneut versuchen')),
  ])));
}

class _PetTile extends StatelessWidget {
  final Map<String, dynamic> pet;
  const _PetTile({required this.pet});

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return Card(
      child: ListTile(
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
        leading: AuthAvatar(
          url: pet['photo_url'] as String?,
          radius: 26,
          bgColor: AppTheme.primary.withValues(alpha: 0.12),
          iconColor: AppTheme.primary,
        ),
        title: Text(pet['name'] as String? ?? '–', style: const TextStyle(fontWeight: FontWeight.w700)),
        subtitle: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          const SizedBox(height: 2),
          Text('${pet['species'] ?? ''}${pet['breed'] != null ? ' · ${pet['breed']}' : ''}',
              style: TextStyle(color: cs.onSurfaceVariant)),
          if (pet['birth_date'] != null || pet['gender'] != null)
            Text('${pet['gender'] ?? ''}${pet['birth_date'] != null ? ' · geb. ${pet['birth_date']}' : ''}',
                style: TextStyle(color: cs.onSurfaceVariant, fontSize: 12)),
        ]),
        trailing: const Icon(Icons.chevron_right_rounded),
        onTap: () => Navigator.push(context, MaterialPageRoute(
          builder: (_) => PetDetailScreen(petId: pet['id'] as int))),
      ),
    );
  }
}
