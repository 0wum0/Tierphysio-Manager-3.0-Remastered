import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';

import '../services/auth_service.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});
  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _formKey = GlobalKey<FormState>();
  final _email = TextEditingController();
  final _password = TextEditingController();
  bool _loading = false;
  bool _obscure = true;
  String? _error;

  @override
  void dispose() {
    _email.dispose();
    _password.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (_loading || !_formKey.currentState!.validate()) return;
    FocusScope.of(context).unfocus();
    setState(() {
      _loading = true;
      _error = null;
    });
    final result = await context.read<AuthService>().loginWithResult(
      _email.text.trim(),
      _password.text,
    );
    if (!mounted) return;
    if (result.success) TextInput.finishAutofillContext();
    setState(() {
      _loading = false;
      _error = result.success
          ? null
          : result.message ??
                'Anmeldung fehlgeschlagen. Bitte erneut versuchen.';
    });
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    body: SafeArea(
      child: LayoutBuilder(
        builder: (context, constraints) {
          final wide = constraints.maxWidth >= 840;
          return SingleChildScrollView(
            keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
            padding: EdgeInsets.all(wide ? 40 : 24),
            child: ConstrainedBox(
              constraints: BoxConstraints(
                minHeight: (constraints.maxHeight - (wide ? 80 : 48)).clamp(
                  0,
                  double.infinity,
                ),
              ),
              child: Center(
                child: ConstrainedBox(
                  constraints: const BoxConstraints(maxWidth: 1100),
                  child: wide
                      ? Row(
                          children: [
                            const Expanded(child: _WelcomePanel()),
                            const SizedBox(width: 64),
                            Expanded(child: _form(context)),
                          ],
                        )
                      : ConstrainedBox(
                          constraints: const BoxConstraints(maxWidth: 440),
                          child: _form(context),
                        ),
                ),
              ),
            ),
          );
        },
      ),
    ),
  );

  Widget _form(BuildContext context) {
    final theme = Theme.of(context);
    final cs = theme.colorScheme;
    return AutofillGroup(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          Row(
            children: [
              Container(
                width: 48,
                height: 48,
                decoration: BoxDecoration(
                  color: cs.primary,
                  borderRadius: BorderRadius.circular(16),
                ),
                child: Icon(Icons.pets_rounded, color: cs.onPrimary, size: 25),
              ),
              const SizedBox(width: 12),
              Text('TheraPano', style: theme.textTheme.titleLarge),
            ],
          ),
          const SizedBox(height: 38),
          Text(
            'Mehr Zeit\nfürs Tier.',
            style: theme.textTheme.headlineLarge?.copyWith(height: 1.15),
          ),
          const SizedBox(height: 14),
          Text(
            'Deine Praxis. Dein Überblick.\nAlles an einem Ort.',
            style: theme.textTheme.bodyLarge?.copyWith(
              color: cs.onSurfaceVariant,
              height: 1.5,
            ),
          ),
          const SizedBox(height: 32),
          Container(
            padding: const EdgeInsets.all(24),
            decoration: BoxDecoration(
              color: theme.cardTheme.color,
              borderRadius: BorderRadius.circular(24),
              border: Border.all(color: cs.outlineVariant),
            ),
            child: Form(
              key: _formKey,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Text('Willkommen zurück', style: theme.textTheme.titleLarge),
                  const SizedBox(height: 6),
                  Text(
                    'Melde dich mit deinem Praxiskonto an.',
                    style: TextStyle(color: cs.onSurfaceVariant, height: 1.45),
                  ),
                  const SizedBox(height: 24),
                  TextFormField(
                    controller: _email,
                    enabled: !_loading,
                    keyboardType: TextInputType.emailAddress,
                    textInputAction: TextInputAction.next,
                    autofillHints: const [
                      AutofillHints.username,
                      AutofillHints.email,
                    ],
                    autocorrect: false,
                    decoration: const InputDecoration(
                      labelText: 'E-Mail-Adresse',
                      prefixIcon: Icon(Icons.mail_outline_rounded),
                    ),
                    validator: (value) => (value ?? '').trim().isEmpty
                        ? 'Bitte E-Mail-Adresse eingeben.'
                        : null,
                  ),
                  const SizedBox(height: 18),
                  TextFormField(
                    controller: _password,
                    enabled: !_loading,
                    obscureText: _obscure,
                    autofillHints: const [AutofillHints.password],
                    autocorrect: false,
                    enableSuggestions: false,
                    textInputAction: TextInputAction.done,
                    onFieldSubmitted: (_) => _submit(),
                    decoration: InputDecoration(
                      labelText: 'Passwort',
                      prefixIcon: const Icon(Icons.lock_outline_rounded),
                      suffixIcon: IconButton(
                        tooltip: _obscure
                            ? 'Passwort anzeigen'
                            : 'Passwort verbergen',
                        icon: Icon(
                          _obscure
                              ? Icons.visibility_outlined
                              : Icons.visibility_off_outlined,
                        ),
                        onPressed: () => setState(() => _obscure = !_obscure),
                      ),
                    ),
                    validator: (value) => (value ?? '').isEmpty
                        ? 'Bitte Passwort eingeben.'
                        : null,
                  ),
                  if (_error != null) ...[
                    const SizedBox(height: 18),
                    Semantics(
                      liveRegion: true,
                      child: Container(
                        padding: const EdgeInsets.all(14),
                        decoration: BoxDecoration(
                          color: cs.errorContainer,
                          borderRadius: BorderRadius.circular(12),
                        ),
                        child: Text(
                          _error!,
                          style: TextStyle(
                            color: cs.onErrorContainer,
                            height: 1.45,
                          ),
                        ),
                      ),
                    ),
                  ],
                  const SizedBox(height: 24),
                  FilledButton(
                    onPressed: _loading ? null : _submit,
                    child: _loading
                        ? const SizedBox(
                            width: 22,
                            height: 22,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : const Text('Anmelden'),
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 24),
          Center(
            child: Text(
              'app.therapano.de',
              style: theme.textTheme.bodySmall?.copyWith(
                color: cs.onSurfaceVariant,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _WelcomePanel extends StatelessWidget {
  const _WelcomePanel();
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(36),
    decoration: BoxDecoration(
      borderRadius: BorderRadius.circular(36),
      gradient: const LinearGradient(
        begin: Alignment.topLeft,
        end: Alignment.bottomRight,
        colors: [Color(0xFF263353), Color(0xFF4354B4)],
      ),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Icon(Icons.pets_rounded, color: Color(0xFFB5EAD9), size: 56),
        const SizedBox(height: 80),
        Text(
          'Gut organisiert.\nMit ganzem Herzen.',
          style: Theme.of(context).textTheme.headlineMedium
              ?.copyWith(color: Colors.white),
        ),
        const SizedBox(height: 20),
        const Text(
          'Von der ersten Anmeldung bis zum Behandlungsplan: Deine Praxis begleitet dich auch unterwegs.',
          style: TextStyle(color: Color(0xFFDDE3FF), fontSize: 16, height: 1.6),
        ),
        const SizedBox(height: 36),
        for (final entry in [
          (Icons.calendar_today_outlined, 'Termine im Blick'),
          (Icons.favorite_border_rounded, 'Patienten und Behandlung'),
          (Icons.forum_outlined, 'Direkt mit Tierhaltern verbunden'),
        ])
          Padding(
            padding: const EdgeInsets.only(bottom: 20),
            child: Row(
              children: [
                Icon(entry.$1, color: const Color(0xFFB5EAD9), size: 21),
                const SizedBox(width: 14),
                Expanded(
                  child: Text(
                    entry.$2,
                    style: const TextStyle(color: Colors.white, fontSize: 14),
                  ),
                ),
              ],
            ),
          ),
      ],
    ),
  );
}
