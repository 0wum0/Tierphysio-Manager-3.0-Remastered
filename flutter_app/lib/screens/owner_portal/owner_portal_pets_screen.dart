import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../services/api_service.dart';

class OwnerPortalPetsScreen extends StatefulWidget {
  const OwnerPortalPetsScreen({super.key});

  @override
  State<OwnerPortalPetsScreen> createState() => _OwnerPortalPetsScreenState();
}

class _OwnerPortalPetsScreenState extends State<OwnerPortalPetsScreen> {
  final ApiService _api = ApiService();
  List<Map<String, dynamic>> _pets = [];
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _loadPets();
  }

  Future<void> _loadPets() async {
    setState(() => _loading = true);
    try {
      final list = await _api.ownerPortalPetList();
      setState(() {
        _pets = list.map((e) => Map<String, dynamic>.from(e as Map)).toList();
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
        title: const Text('Meine Tiere'),
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: _loadPets,
              child: ListView.builder(
                padding: const EdgeInsets.all(16),
                itemCount: _pets.length,
                itemBuilder: (context, index) {
                  final pet = _pets[index];
                  return Card(
                    margin: const EdgeInsets.only(bottom: 12),
                    child: ListTile(
                      leading: CircleAvatar(
                        backgroundImage: pet['photo_url'] != null
                            ? NetworkImage(pet['photo_url'] as String)
                            : null,
                        child: pet['photo_url'] == null ? Text(pet['name'] as String? ?? '') : null,
                      ),
                      title: Text(pet['name'] as String? ?? ''),
                      subtitle: Text('${pet['species'] as String? ?? ''} - ${pet['breed'] as String? ?? ''}'),
                      trailing: const Icon(Icons.chevron_right),
                      onTap: () => _viewPet(pet['id'] as int),
                    ),
                  );
                },
              ),
            ),
    );
  }

  Future<void> _viewPet(int id) async {
    final pet = await _api.ownerPortalPetDetail(id);
    
    if (!mounted) return;
    
    Navigator.pushNamed(context, '/owner-portal/pets/$id', arguments: pet);
  }
}
