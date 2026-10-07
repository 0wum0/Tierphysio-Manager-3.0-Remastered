import 'package:flutter/material.dart';

import '../core/navigation.dart';

/// Shares all navigation entries with the tablet sidebar.
class ModuleSheet extends StatefulWidget {
  final List<NavSection> sections;
  final String currentRoute;
  final ValueChanged<NavItem> onSelect;
  final VoidCallback onLogout;
  const ModuleSheet({
    super.key,
    required this.sections,
    required this.currentRoute,
    required this.onSelect,
    required this.onLogout,
  });
  @override
  State<ModuleSheet> createState() => _ModuleSheetState();
}

class _ModuleSheetState extends State<ModuleSheet> {
  String _query = '';
  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final cs = theme.colorScheme;
    final groups = [
      for (final section in widget.sections)
        NavSection(
          section.title,
          section.items
              .where(
                (item) => '${section.title} ${item.label}'
                    .toLowerCase()
                    .contains(_query),
              )
              .toList(),
        ),
    ].where((section) => section.items.isNotEmpty).toList();
    return SafeArea(
      top: false,
      child: Padding(
        padding: EdgeInsets.only(
          bottom: MediaQuery.viewInsetsOf(context).bottom,
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(24, 0, 12, 12),
              child: Row(
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Deine Praxis',
                          style: theme.textTheme.headlineSmall,
                        ),
                        Text(
                          'Alle Module an einem Ort',
                          style: TextStyle(color: cs.onSurfaceVariant),
                        ),
                      ],
                    ),
                  ),
                  IconButton(
                    tooltip: 'Schließen',
                    onPressed: () => Navigator.pop(context),
                    icon: const Icon(Icons.close_rounded),
                  ),
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(24, 0, 24, 16),
              child: TextField(
                decoration: const InputDecoration(
                  hintText: 'Modul suchen',
                  prefixIcon: Icon(Icons.search_rounded),
                ),
                onChanged: (value) =>
                    setState(() => _query = value.trim().toLowerCase()),
              ),
            ),
            Expanded(
              child: ListView(
                keyboardDismissBehavior:
                    ScrollViewKeyboardDismissBehavior.onDrag,
                padding: const EdgeInsets.fromLTRB(16, 0, 16, 20),
                children: [
                  if (groups.isEmpty)
                    Padding(
                      padding: const EdgeInsets.all(24),
                      child: Text(
                        'Kein passendes Modul gefunden.',
                        style: TextStyle(color: cs.onSurfaceVariant),
                      ),
                    ),
                  for (final section in groups) ...[
                    Padding(
                      padding: const EdgeInsets.fromLTRB(12, 18, 12, 8),
                      child: Text(
                        section.title.toUpperCase(),
                        style: TextStyle(
                          color: cs.onSurfaceVariant,
                          fontSize: 11,
                          fontWeight: FontWeight.w700,
                          letterSpacing: 1.2,
                        ),
                      ),
                    ),
                    for (final item in section.items)
                      ListTile(
                        enabled: !item.comingSoon,
                        selected:
                            widget.currentRoute == item.route ||
                            widget.currentRoute.startsWith('${item.route}/'),
                        selectedTileColor: cs.primary.withValues(alpha: 0.08),
                        leading: Container(
                          padding: const EdgeInsets.all(10),
                          decoration: BoxDecoration(
                            color: item.color.withValues(alpha: 0.10),
                            borderRadius: BorderRadius.circular(12),
                          ),
                          child: Icon(
                            item.icon,
                            color: theme.brightness == Brightness.dark
                                ? cs.primary
                                : item.color,
                            size: 22,
                          ),
                        ),
                        title: Text(
                          item.label,
                          style: const TextStyle(fontWeight: FontWeight.w600),
                        ),
                        trailing: item.badge > 0
                            ? Badge(label: Text('${item.badge}'))
                            : Icon(
                                Icons.chevron_right_rounded,
                                color: cs.onSurfaceVariant,
                                size: 20,
                              ),
                        onTap: () => widget.onSelect(item),
                      ),
                  ],
                  if (_query.isEmpty) ...[
                    const SizedBox(height: 20),
                    const Divider(),
                    ListTile(
                      leading: const Icon(Icons.logout_rounded),
                      title: const Text('Abmelden'),
                      onTap: widget.onLogout,
                    ),
                  ],
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
