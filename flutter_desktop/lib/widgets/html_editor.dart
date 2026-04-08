import 'package:flutter/material.dart';

/// A full-screen HTML rich-text editor pushed via Navigator.
/// Returns the HTML string when saved.
class HtmlEditorScreen extends StatefulWidget {
  final String initialHtml;
  final String title;

  const HtmlEditorScreen({
    super.key,
    required this.title,
    this.initialHtml = '',
  });

  @override
  State<HtmlEditorScreen> createState() => _HtmlEditorScreenState();
}

class _HtmlEditorScreenState extends State<HtmlEditorScreen> {
  late final TextEditingController _ctrl;
  final FocusNode _focus = FocusNode();

  @override
  void initState() {
    super.initState();
    _ctrl = TextEditingController(text: widget.initialHtml);
  }

  @override
  void dispose() {
    _ctrl.dispose();
    _focus.dispose();
    super.dispose();
  }

  // ── Toolbar actions ────────────────────────────────────────

  void _wrap(String open, String close) {
    final sel   = _ctrl.selection;
    final text  = _ctrl.text;
    if (!sel.isValid) {
      _insert(open + close);
      return;
    }
    final before   = text.substring(0, sel.start);
    final selected = text.substring(sel.start, sel.end);
    final after    = text.substring(sel.end);
    final newText  = '$before$open$selected$close$after';
    final newCursor = sel.end + open.length + close.length;
    _ctrl.value = TextEditingValue(
      text: newText,
      selection: TextSelection.collapsed(offset: newCursor),
    );
  }

  void _insert(String snippet) {
    final sel    = _ctrl.selection;
    final text   = _ctrl.text;
    final pos    = sel.isValid ? sel.end : text.length;
    final before = text.substring(0, pos);
    final after  = text.substring(pos);
    _ctrl.value = TextEditingValue(
      text: '$before$snippet$after',
      selection: TextSelection.collapsed(offset: pos + snippet.length),
    );
  }

  void _wrapBlock(String tag) {
    final sel  = _ctrl.selection;
    final text = _ctrl.text;
    if (!sel.isValid) {
      _insert('<$tag></$tag>');
      return;
    }
    final before   = text.substring(0, sel.start);
    final selected = text.substring(sel.start, sel.end);
    final after    = text.substring(sel.end);
    final lines    = selected.split('\n');
    final wrapped  = lines.map((l) => '<li>$l</li>').join('\n');
    final newText  = '$before<$tag>\n$wrapped\n</$tag>$after';
    _ctrl.value = TextEditingValue(
      text: newText,
      selection: TextSelection.collapsed(offset: newText.length - after.length),
    );
  }

  Future<void> _insertLink() async {
    String url  = '';
    String text = _ctrl.selection.isValid
        ? _ctrl.text.substring(_ctrl.selection.start, _ctrl.selection.end)
        : '';
    final result = await showDialog<Map<String, String>>(
      context: context,
      builder: (ctx) {
        final urlCtrl  = TextEditingController(text: url);
        final textCtrl = TextEditingController(text: text);
        return AlertDialog(
          title: const Text('Link einfügen'),
          content: Column(mainAxisSize: MainAxisSize.min, children: [
            TextField(
              controller: textCtrl,
              decoration: const InputDecoration(labelText: 'Anzeigetext'),
            ),
            const SizedBox(height: 8),
            TextField(
              controller: urlCtrl,
              decoration: const InputDecoration(labelText: 'URL (https://...)'),
              keyboardType: TextInputType.url,
            ),
          ]),
          actions: [
            TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Abbrechen')),
            FilledButton(
              onPressed: () => Navigator.pop(ctx, {'url': urlCtrl.text, 'text': textCtrl.text}),
              child: const Text('Einfügen'),
            ),
          ],
        );
      },
    );
    if (result != null && result['url']!.isNotEmpty) {
      final linkText = result['text']!.isNotEmpty ? result['text']! : result['url']!;
      final tag = '<a href="${result['url']!}">$linkText</a>';
      // replace selection or insert at cursor
      final sel  = _ctrl.selection;
      final t    = _ctrl.text;
      final pos  = sel.isValid ? sel.start : t.length;
      final end  = sel.isValid ? sel.end : t.length;
      final newText = t.substring(0, pos) + tag + t.substring(end);
      _ctrl.value = TextEditingValue(
        text: newText,
        selection: TextSelection.collapsed(offset: pos + tag.length),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return Scaffold(
      appBar: AppBar(
        title: Text(widget.title),
        actions: [
          TextButton.icon(
            icon: const Icon(Icons.check_rounded),
            label: const Text('Speichern'),
            onPressed: () => Navigator.pop(context, _ctrl.text),
          ),
        ],
      ),
      body: Column(children: [
        // ── Toolbar ──
        Container(
          color: cs.surfaceContainerHighest,
          child: SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 4),
            child: Row(children: [
              _ToolBtn(icon: Icons.format_bold,          tooltip: 'Fett',           onTap: () => _wrap('<b>', '</b>')),
              _ToolBtn(icon: Icons.format_italic,        tooltip: 'Kursiv',         onTap: () => _wrap('<i>', '</i>')),
              _ToolBtn(icon: Icons.format_underlined,    tooltip: 'Unterstrichen',  onTap: () => _wrap('<u>', '</u>')),
              _ToolBtn(icon: Icons.format_strikethrough, tooltip: 'Durchgestrichen',onTap: () => _wrap('<s>', '</s>')),
              const _Divider(),
              _ToolBtn(icon: Icons.format_list_bulleted, tooltip: 'Aufzählung',     onTap: () => _wrapBlock('ul')),
              _ToolBtn(icon: Icons.format_list_numbered, tooltip: 'Nummerierung',   onTap: () => _wrapBlock('ol')),
              const _Divider(),
              _ToolBtn(icon: Icons.link_rounded,         tooltip: 'Link',           onTap: _insertLink),
              const _Divider(),
              _ToolBtn(icon: Icons.title_rounded,        tooltip: 'Überschrift H3', onTap: () => _wrap('<h3>', '</h3>')),
              _ToolBtn(icon: Icons.horizontal_rule_rounded, tooltip: 'Trennlinie',  onTap: () => _insert('<hr/>')),
            ]),
          ),
        ),
        // ── Editor ──
        Expanded(
          child: Padding(
            padding: const EdgeInsets.all(12),
            child: TextField(
              controller: _ctrl,
              focusNode: _focus,
              maxLines: null,
              expands: true,
              textAlignVertical: TextAlignVertical.top,
              style: const TextStyle(fontFamily: 'monospace', fontSize: 13),
              decoration: InputDecoration(
                hintText: 'HTML-Inhalt eingeben…\nBeispiel: <b>Fett</b>, <i>Kursiv</i>',
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                filled: true,
                fillColor: cs.surface,
              ),
            ),
          ),
        ),
      ]),
    );
  }
}

// ── Internal toolbar button ────────────────────────────────────────────────────

class _ToolBtn extends StatelessWidget {
  final IconData icon;
  final String tooltip;
  final VoidCallback onTap;
  const _ToolBtn({required this.icon, required this.tooltip, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return Tooltip(
      message: tooltip,
      child: InkWell(
        borderRadius: BorderRadius.circular(6),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(8),
          child: Icon(icon, size: 20),
        ),
      ),
    );
  }
}

class _Divider extends StatelessWidget {
  const _Divider();
  @override
  Widget build(BuildContext context) =>
      Container(width: 1, height: 24, margin: const EdgeInsets.symmetric(horizontal: 4),
          color: Theme.of(context).dividerColor);
}
