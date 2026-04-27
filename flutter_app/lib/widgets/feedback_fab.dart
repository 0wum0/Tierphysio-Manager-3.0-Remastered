import 'dart:io';
import 'package:flutter/material.dart';
import 'package:package_info_plus/package_info_plus.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
import '../services/api_service.dart';
import '../services/auth_service.dart';

/// Floating Action Button that opens the feedback bottom sheet.
/// Usage: add FeedbackFab() anywhere in a Scaffold, or via the shell.
class FeedbackFab extends StatefulWidget {
  const FeedbackFab({super.key});

  @override
  State<FeedbackFab> createState() => _FeedbackFabState();
}

class _ShellLevelFeedback {
  static bool isOpen = false;
}

class _FeedbackFabState extends State<FeedbackFab> {
  @override
  Widget build(BuildContext context) {
    if (_ShellLevelFeedback.isOpen) return const SizedBox.shrink();

    // Hide FAB on messaging/chat screens to prevent overlapping with send buttons
    try {
      final location = GoRouterState.of(context).matchedLocation;
      if (location.contains('nachrichten') || location.contains('chat')) {
        return const SizedBox.shrink();
      }
    } catch (_) {
      // Fallback to ModalRoute if GoRouter is not available
      try {
        final route = ModalRoute.of(context)?.settings.name ?? '';
        if (route.contains('nachrichten') || route.contains('chat')) {
          return const SizedBox.shrink();
        }
      } catch (_) {}
    }

    return FloatingActionButton.small(
      heroTag: 'feedback_fab',
      tooltip: 'Feedback senden',
      backgroundColor: const Color(0xFF6EA8FE),
      onPressed: () => _displaySheet(context),
      child: const Icon(Icons.chat_bubble_outline_rounded,
          color: Colors.white, size: 20),
    );
  }

  Future<void> _displaySheet(BuildContext context) async {
    setState(() => _ShellLevelFeedback.isOpen = true);
    await showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => const _FeedbackSheet(),
    );
    if (mounted) {
      setState(() => _ShellLevelFeedback.isOpen = false);
    }
  }
}

class _FeedbackSheet extends StatefulWidget {
  const _FeedbackSheet();

  @override
  State<_FeedbackSheet> createState() => _FeedbackSheetState();
}

class _FeedbackSheetState extends State<_FeedbackSheet> {
  final _controller = TextEditingController();
  String _category = 'other';
  int? _rating;
  bool _sending = false;
  bool _sent = false;
  String? _error;

  static const _categories = [
    ('bug', '🐛', 'Bug melden'),
    ('feature', '💡', 'Idee/Feature'),
    ('praise', '⭐', 'Lob'),
    ('other', '💬', 'Sonstiges'),
  ];

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  Future<void> _send() async {
    final msg = _controller.text.trim();
    if (msg.isEmpty) {
      setState(() => _error = 'Bitte gib eine Nachricht ein.');
      return;
    }
    setState(() {
      _sending = true;
      _error = null;
    });

    final email = context.read<AuthService>().userEmail;
    PackageInfo? info;
    try {
      info = await PackageInfo.fromPlatform();
    } catch (_) {}

    final ok = await ApiService.submitFeedback(
      message: msg,
      category: _category,
      rating: _rating,
      platform: Platform.isWindows
          ? 'windows'
          : Platform.isAndroid
              ? 'android'
              : Platform.operatingSystem,
      appVersion: info == null ? null : '${info.version}+${info.buildNumber}',
      email: email,
    );

    if (!mounted) return;
    setState(() {
      _sending = false;
      _sent = ok;
      _error = ok
          ? null
          : 'Konnte nicht gesendet werden. Bitte prüfe deine Verbindung.';
    });
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;
    final bgColor = isDark ? const Color(0xFF1E293B) : Colors.white;
    final textColor = isDark ? Colors.white : Colors.black87;

    return Padding(
      padding:
          EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom),
      child: Container(
        decoration: BoxDecoration(
          color: bgColor,
          borderRadius: const BorderRadius.vertical(top: Radius.circular(20)),
        ),
        child: SafeArea(
          child: _sent
              ? _buildSuccess(textColor)
              : _buildForm(theme, textColor, isDark),
        ),
      ),
    );
  }

  Widget _buildSuccess(Color textColor) {
    return Padding(
      padding: const EdgeInsets.all(32),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Text('🎉', style: TextStyle(fontSize: 48)),
          const SizedBox(height: 12),
          Text('Danke für dein Feedback!',
              style: TextStyle(
                  fontSize: 18, fontWeight: FontWeight.bold, color: textColor)),
          const SizedBox(height: 8),
          Text('Wir schauen es uns schnellstmöglich an.',
              textAlign: TextAlign.center,
              style: TextStyle(color: textColor.withOpacity(.6), fontSize: 14)),
          const SizedBox(height: 24),
          SizedBox(
            width: double.infinity,
            child: ElevatedButton(
              style: ElevatedButton.styleFrom(
                backgroundColor: const Color(0xFF6EA8FE),
                foregroundColor: Colors.white,
                shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12)),
              ),
              onPressed: () => Navigator.of(context).pop(),
              child: const Text('Schließen'),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildForm(ThemeData theme, Color textColor, bool isDark) {
    final borderColor = isDark ? const Color(0xFF334155) : Colors.grey.shade300;
    final chipBg = isDark ? const Color(0xFF0F172A) : Colors.grey.shade100;

    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 8, 20, 20),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Handle
          Center(
            child: Container(
              width: 40,
              height: 4,
              margin: const EdgeInsets.only(bottom: 16),
              decoration: BoxDecoration(
                color: borderColor,
                borderRadius: BorderRadius.circular(2),
              ),
            ),
          ),

          // Title
          Row(children: [
            const Text('💬', style: TextStyle(fontSize: 22)),
            const SizedBox(width: 8),
            Text('Feedback senden',
                style: TextStyle(
                    fontSize: 17,
                    fontWeight: FontWeight.bold,
                    color: textColor)),
          ]),
          const SizedBox(height: 16),

          // Category chips
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: _categories.map((cat) {
              final (id, icon, label) = cat;
              final selected = _category == id;
              return ChoiceChip(
                label: Text('$icon $label',
                    style: TextStyle(
                        fontSize: 13,
                        color: selected ? Colors.white : textColor)),
                selected: selected,
                selectedColor: const Color(0xFF6EA8FE),
                backgroundColor: chipBg,
                side: BorderSide(
                    color: selected ? const Color(0xFF6EA8FE) : borderColor),
                onSelected: (_) => setState(() => _category = id),
              );
            }).toList(),
          ),
          const SizedBox(height: 14),

          // Rating (only for praise/other)
          if (_category == 'praise' || _category == 'other') ...[
            Text('Bewertung (optional)',
                style:
                    TextStyle(fontSize: 13, color: textColor.withOpacity(.6))),
            const SizedBox(height: 6),
            Row(
              children: List.generate(5, (i) {
                final star = i + 1;
                return GestureDetector(
                  onTap: () =>
                      setState(() => _rating = _rating == star ? null : star),
                  child: Padding(
                    padding: const EdgeInsets.only(right: 4),
                    child: Icon(
                      (_rating ?? 0) >= star
                          ? Icons.star_rounded
                          : Icons.star_outline_rounded,
                      color: (_rating ?? 0) >= star
                          ? const Color(0xFFFDE68A)
                          : borderColor,
                      size: 32,
                    ),
                  ),
                );
              }),
            ),
            const SizedBox(height: 14),
          ],

          // Message field
          TextField(
            controller: _controller,
            maxLines: 4,
            maxLength: 1000,
            style: TextStyle(color: textColor, fontSize: 14),
            decoration: InputDecoration(
              hintText: _category == 'bug'
                  ? 'Was ist passiert? Wie können wir den Fehler reproduzieren?'
                  : _category == 'feature'
                      ? 'Welche Funktion wünschst du dir?'
                      : _category == 'praise'
                          ? 'Was gefällt dir besonders gut?'
                          : 'Deine Nachricht…',
              hintStyle:
                  TextStyle(color: textColor.withOpacity(.35), fontSize: 13),
              filled: true,
              fillColor: isDark ? const Color(0xFF0F172A) : Colors.grey.shade50,
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(12),
                borderSide: BorderSide(color: borderColor),
              ),
              enabledBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(12),
                borderSide: BorderSide(color: borderColor),
              ),
              focusedBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(12),
                borderSide:
                    const BorderSide(color: Color(0xFF6EA8FE), width: 1.5),
              ),
              counterStyle:
                  TextStyle(color: textColor.withOpacity(.35), fontSize: 11),
            ),
          ),

          if (_error != null) ...[
            const SizedBox(height: 8),
            Text(_error!,
                style: const TextStyle(color: Color(0xFFFCA5A5), fontSize: 13)),
          ],

          const SizedBox(height: 16),

          // Send button
          SizedBox(
            width: double.infinity,
            child: ElevatedButton(
              style: ElevatedButton.styleFrom(
                backgroundColor: const Color(0xFF6EA8FE),
                foregroundColor: Colors.white,
                padding: const EdgeInsets.symmetric(vertical: 14),
                shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12)),
              ),
              onPressed: _sending ? null : _send,
              child: _sending
                  ? const SizedBox(
                      width: 20,
                      height: 20,
                      child: CircularProgressIndicator(
                          strokeWidth: 2, color: Colors.white))
                  : const Text('Feedback senden',
                      style:
                          TextStyle(fontWeight: FontWeight.w600, fontSize: 15)),
            ),
          ),
        ],
      ),
    );
  }
}
