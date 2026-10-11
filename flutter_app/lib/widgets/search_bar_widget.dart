import 'dart:async';

import 'package:flutter/material.dart';

class AppSearchBar extends StatefulWidget {
  final ValueChanged<String> onSearch;
  final String hint;
  const AppSearchBar({
    super.key,
    required this.onSearch,
    this.hint = 'Suchen…',
  });
  @override
  State<AppSearchBar> createState() => _AppSearchBarState();
}

class _AppSearchBarState extends State<AppSearchBar> {
  final _ctrl = TextEditingController();
  Timer? _debounce;
  @override
  void dispose() {
    _debounce?.cancel();
    _ctrl.dispose();
    super.dispose();
  }

  void _search(String value, {bool immediate = false}) {
    _debounce?.cancel();
    setState(() {});
    if (immediate || value.isEmpty) {
      widget.onSearch(value.trim());
    } else {
      _debounce = Timer(
        const Duration(milliseconds: 300),
        () => widget.onSearch(value.trim()),
      );
    }
  }

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.fromLTRB(16, 10, 16, 8),
    child: TextField(
      controller: _ctrl,
      decoration: InputDecoration(
        hintText: widget.hint,
        prefixIcon: const Icon(Icons.search_rounded, size: 22),
        suffixIcon: _ctrl.text.isEmpty
            ? null
            : IconButton(
                tooltip: 'Suche leeren',
                icon: const Icon(Icons.close_rounded, size: 20),
                onPressed: () {
                  _ctrl.clear();
                  _search('', immediate: true);
                },
              ),
      ),
      onChanged: _search,
      textInputAction: TextInputAction.search,
      onSubmitted: (value) => _search(value, immediate: true),
    ),
  );
}
