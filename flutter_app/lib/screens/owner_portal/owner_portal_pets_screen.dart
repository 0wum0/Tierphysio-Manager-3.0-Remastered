import 'package:flutter/material.dart';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:shimmer/shimmer.dart';
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
  String? _error;

  static const _green = Color(0xFF10B981);

  @override
  void initState() {
    super.initState();
    _loadPets();
  }

  Future<void> _loadPets() async {
    setState(() { _loading = true; _error = null; });
    try {
      final list = await _api.ownerPortalPetList();
      setState(() {
        _pets = list.map((e) => Map<String, dynamic>.from(e as Map)).toList();
        _loading = false;
      });
    } catch (e) {
      setState(() { _loading = false; _error = e.toString(); });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Meine Tiere'),
        backgroundColor: _green,
        foregroundColor: Colors.white,
      ),
      body: _loading
          ? _buildShimmer()
          : _error != null
              ? _buildError()
              : RefreshIndicator(
                  onRefresh: _loadPets,
                  color: _green,
                  child: _pets.isEmpty
                      ? _buildEmpty()
                      : ListView.builder(
                          padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
                          itemCount: _pets.length,
                          itemBuilder: (context, i) {
                            final pet = _pets[i];
                            return TweenAnimationBuilder<double>(
                              tween: Tween(begin: 0, end: 1),
                              duration: Duration(milliseconds: 300 + i * 50),
                              curve: Curves.easeOutCubic,
                              builder: (_, v, child) => Transform.translate(
                                offset: Offset(0, 18 * (1 - v)),
                                child: Opacity(opacity: v, child: child),
                              ),
                              child: _PetCard(pet: pet, onTap: () => _viewPet(pet['id'] as int? ?? 0)),
                            );
                          },
                        ),
                ),
    );
  }

  Widget _buildShimmer() {
    return Shimmer.fromColors(
      baseColor: Colors.grey.shade300,
      highlightColor: Colors.grey.shade100,
      child: ListView.builder(
        padding: const EdgeInsets.all(16),
        itemCount: 4,
        itemBuilder: (_, __) => Container(
          height: 110, margin: const EdgeInsets.only(bottom: 12),
          decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(18)),
        ),
      ),
    );
  }

  Widget _buildEmpty() {
    return Center(child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
      const Text('🐾', style: TextStyle(fontSize: 56)),
      const SizedBox(height: 16),
      const Text('Noch keine Tiere vorhanden',
          style: TextStyle(fontSize: 17, fontWeight: FontWeight.w700)),
      const SizedBox(height: 8),
      Text('Ihre Tiere werden hier angezeigt,\nwenn sie von der Praxis hinzugefügt wurden.',
          textAlign: TextAlign.center, style: TextStyle(fontSize: 13, color: Colors.grey.shade500)),
    ]));
  }

  Widget _buildError() {
    return Center(child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
      const Icon(Icons.cloud_off_rounded, size: 48, color: Colors.grey),
      const SizedBox(height: 12),
      Text(_error ?? '', textAlign: TextAlign.center, style: const TextStyle(fontSize: 13)),
      const SizedBox(height: 16),
      FilledButton.icon(onPressed: _loadPets,
        icon: const Icon(Icons.refresh_rounded), label: const Text('Erneut'),
        style: FilledButton.styleFrom(backgroundColor: _green)),
    ]));
  }

  Future<void> _viewPet(int id) async {
    if (id == 0) return;
    try {
      final pet = await _api.ownerPortalPetDetail(id);
      if (!mounted) return;
      _showPetDetail(pet);
    } catch (_) {}
  }

  void _showPetDetail(Map<String, dynamic> pet) {
    showModalBottomSheet(
      context: context, isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => _PetDetailSheet(pet: pet),
    );
  }
}

class _PetCard extends StatelessWidget {
  final Map<String, dynamic> pet;
  final VoidCallback onTap;
  const _PetCard({required this.pet, required this.onTap});

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).colorScheme.brightness == Brightness.dark;
    final name    = pet['name']    as String? ?? '?';
    final species = pet['species'] as String? ?? '';
    final breed   = pet['breed']   as String? ?? '';
    final dob     = pet['date_of_birth'] as String? ?? pet['geburtsdatum'] as String? ?? '';
    final photoUrl = pet['photo_url'] as String? ?? pet['foto_url'] as String? ?? '';

    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Material(
        color: isDark ? const Color(0xFF0F1F16) : Colors.white,
        borderRadius: BorderRadius.circular(18),
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: onTap,
          splashColor: const Color(0xFF10B981).withValues(alpha: 0.08),
          child: Container(
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(18),
              border: Border.all(
                color: isDark ? Colors.white.withValues(alpha: 0.07)
                              : Colors.black.withValues(alpha: 0.06)),
            ),
            child: Row(children: [
              Hero(
                tag: 'pet_${pet['id']}',
                child: Container(
                  width: 68, height: 68,
                  decoration: BoxDecoration(
                    borderRadius: BorderRadius.circular(16),
                    color: const Color(0xFF10B981).withValues(alpha: 0.15),
                  ),
                  clipBehavior: Clip.antiAlias,
                  child: photoUrl.isNotEmpty
                      ? CachedNetworkImage(
                          imageUrl: ApiService.mediaUrl(photoUrl),
                          fit: BoxFit.cover,
                          errorWidget: (_, __, ___) => _initials(name),
                        )
                      : _initials(name),
                ),
              ),
              const SizedBox(width: 14),
              Expanded(child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(name, style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w700)),
                  if (species.isNotEmpty || breed.isNotEmpty)
                    Text('$species${breed.isNotEmpty ? " · $breed" : ""}',
                        style: TextStyle(fontSize: 12, color: Colors.grey.shade500)),
                  if (dob.isNotEmpty)
                    Text('Geb. $dob', style: TextStyle(fontSize: 11, color: Colors.grey.shade400)),
                ],
              )),
              Icon(Icons.chevron_right_rounded, color: Colors.grey.shade400),
            ]),
          ),
        ),
      ),
    );
  }

  Widget _initials(String name) {
    return Center(child: Text(
      name.isNotEmpty ? name[0].toUpperCase() : '?',
      style: const TextStyle(fontSize: 28, fontWeight: FontWeight.w800, color: Color(0xFF10B981)),
    ));
  }
}

class _PetDetailSheet extends StatelessWidget {
  final Map<String, dynamic> pet;
  const _PetDetailSheet({required this.pet});

  @override
  Widget build(BuildContext context) {
    final name = pet['name'] as String? ?? '';
    final photoUrl = pet['photo_url'] as String? ?? pet['foto_url'] as String? ?? '';
    final fields = <String, String>{
      'Tierart':    pet['species']       as String? ?? '',
      'Rasse':      pet['breed']         as String? ?? '',
      'Geburt':     pet['date_of_birth'] as String? ?? pet['geburtsdatum'] as String? ?? '',
      'Geschlecht': pet['gender']        as String? ?? pet['geschlecht']   as String? ?? '',
      'Gewicht':    pet['weight']        != null ? '${pet["weight"]} kg' : '',
    }..removeWhere((_, v) => v.isEmpty);

    return DraggableScrollableSheet(
      initialChildSize: 0.6, maxChildSize: 0.92, minChildSize: 0.4,
      builder: (_, sc) => Container(
        decoration: const BoxDecoration(
          color: Color(0xFF0F1F16),
          borderRadius: BorderRadius.vertical(top: Radius.circular(26)),
        ),
        child: ListView(controller: sc, padding: const EdgeInsets.fromLTRB(24, 8, 24, 32), children: [
          Center(child: Container(width: 40, height: 4,
            margin: const EdgeInsets.only(bottom: 16),
            decoration: BoxDecoration(color: Colors.white24, borderRadius: BorderRadius.circular(2)))),
          if (photoUrl.isNotEmpty)
            Center(child: Hero(
              tag: 'pet_${pet['id']}',
              child: ClipRRect(
                borderRadius: BorderRadius.circular(20),
                child: CachedNetworkImage(imageUrl: ApiService.mediaUrl(photoUrl),
                  width: 120, height: 120, fit: BoxFit.cover,
                  errorWidget: (_, __, ___) => const SizedBox()),
              ),
            )),
          const SizedBox(height: 14),
          Text(name, textAlign: TextAlign.center,
              style: const TextStyle(fontSize: 24, fontWeight: FontWeight.w800, color: Colors.white)),
          const SizedBox(height: 20),
          ...fields.entries.map((e) => Padding(
            padding: const EdgeInsets.only(bottom: 10),
            child: Row(children: [
              Text(e.key, style: TextStyle(fontSize: 13, color: Colors.white.withValues(alpha: 0.5))),
              const Spacer(),
              Text(e.value, style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: Colors.white)),
            ]),
          )),
        ]),
      ),
    );
  }
}
