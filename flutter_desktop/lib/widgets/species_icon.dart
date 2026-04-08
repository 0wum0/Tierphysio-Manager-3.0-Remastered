import 'package:flutter/material.dart';

class SpeciesIcon extends StatelessWidget {
  final String species;
  final double size;

  const SpeciesIcon({super.key, required this.species, this.size = 20});

  @override
  Widget build(BuildContext context) {
    final lower = species.toLowerCase();
    final icon = lower.contains('hund')    ? Icons.pets
               : lower.contains('katze')   ? Icons.catching_pokemon
               : lower.contains('pferd')   ? Icons.directions_run
               : lower.contains('vogel')   ? Icons.flutter_dash
               : lower.contains('kaninchen') ? Icons.cruelty_free
               : lower.contains('hamster') ? Icons.cruelty_free
               : Icons.pets;

    return Icon(icon, size: size, color: Theme.of(context).colorScheme.onPrimaryContainer);
  }
}
