import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../services/auth_service.dart';
import '../core/theme.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen>
    with SingleTickerProviderStateMixin {
  final _formKey    = GlobalKey<FormState>();
  final _emailCtrl  = TextEditingController();
  final _passCtrl   = TextEditingController();
  final _serverCtrl = TextEditingController(text: 'https://ew.makeit.uno');
  bool _loading     = false;
  bool _obscure     = true;
  bool _showServer  = false;
  String? _error;

  late AnimationController _anim;
  late Animation<double> _fadeIn;
  late Animation<Offset> _slideUp;

  @override
  void initState() {
    super.initState();
    _anim = AnimationController(vsync: this, duration: const Duration(milliseconds: 900));
    _fadeIn  = CurvedAnimation(parent: _anim, curve: const Interval(0.0, 0.7, curve: Curves.easeOut));
    _slideUp = Tween<Offset>(begin: const Offset(0, 0.12), end: Offset.zero)
        .animate(CurvedAnimation(parent: _anim, curve: Curves.easeOutCubic));
    _anim.forward();
  }

  @override
  void dispose() {
    _emailCtrl.dispose();
    _passCtrl.dispose();
    _serverCtrl.dispose();
    _anim.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() { _loading = true; _error = null; });
    final ok = await context.read<AuthService>().login(
      _emailCtrl.text.trim(), _passCtrl.text, _serverCtrl.text.trim(),
    );
    if (mounted) {
      setState(() => _loading = false);
      if (!ok) setState(() => _error = 'Ungültige Anmeldedaten oder Server nicht erreichbar.');
    }
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    return Scaffold(
      body: Stack(children: [
        /* ── Animated gradient background ── */
        _GradientBg(isDark: isDark),

        SafeArea(
          child: Center(
            child: SingleChildScrollView(
              padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 32),
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 420),
                child: FadeTransition(
                  opacity: _fadeIn,
                  child: SlideTransition(
                    position: _slideUp,
                    child: Column(children: [
                      /* ── Logo + Branding ── */
                      _LogoBadge(),
                      const SizedBox(height: 20),
                      Text('TheraPano',
                        style: TextStyle(
                          fontSize: 32, fontWeight: FontWeight.w800,
                          letterSpacing: -1,
                          foreground: Paint()..shader = LinearGradient(
                            colors: [AppTheme.primary, AppTheme.secondary],
                          ).createShader(const Rect.fromLTWH(0, 0, 200, 40)),
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        'Mehr Zeit fürs Tier.',
                        style: TextStyle(
                          fontSize: 14, color: isDark
                              ? Colors.white54 : Colors.black45,
                          letterSpacing: 0.2,
                        ),
                      ),
                      const SizedBox(height: 36),

                      /* ── Glass card ── */
                      Container(
                        decoration: BoxDecoration(
                          color: isDark
                              ? Colors.white.withValues(alpha: 0.06)
                              : Colors.white.withValues(alpha: 0.85),
                          borderRadius: BorderRadius.circular(24),
                          border: Border.all(
                            color: isDark
                                ? Colors.white.withValues(alpha: 0.10)
                                : Colors.white.withValues(alpha: 0.9),
                          ),
                          boxShadow: [
                            BoxShadow(
                              color: AppTheme.primary.withValues(alpha: 0.10),
                              blurRadius: 40,
                              offset: const Offset(0, 16),
                            ),
                          ],
                        ),
                        padding: const EdgeInsets.all(28),
                        child: Form(
                          key: _formKey,
                          child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                            Text('Anmelden',
                              style: TextStyle(
                                fontSize: 20, fontWeight: FontWeight.w700,
                                color: isDark ? Colors.white : Colors.black87,
                              ),
                            ),
                            const SizedBox(height: 20),

                            /* Server URL (collapsible) */
                            InkWell(
                              onTap: () => setState(() => _showServer = !_showServer),
                              borderRadius: BorderRadius.circular(8),
                              child: Padding(
                                padding: const EdgeInsets.symmetric(vertical: 4),
                                child: Row(children: [
                                  Icon(Icons.dns_outlined, size: 15,
                                    color: isDark ? Colors.white38 : Colors.black38),
                                  const SizedBox(width: 6),
                                  Expanded(child: Text(
                                    _serverCtrl.text.isEmpty ? 'Server konfigurieren' : _serverCtrl.text,
                                    style: TextStyle(fontSize: 12,
                                      color: isDark ? Colors.white38 : Colors.black38),
                                    overflow: TextOverflow.ellipsis,
                                  )),
                                  Icon(_showServer ? Icons.keyboard_arrow_up : Icons.keyboard_arrow_down,
                                    size: 16,
                                    color: isDark ? Colors.white38 : Colors.black38),
                                ]),
                              ),
                            ),
                            AnimatedSize(
                              duration: const Duration(milliseconds: 200),
                              child: _showServer
                                  ? Padding(
                                      padding: const EdgeInsets.only(top: 10, bottom: 4),
                                      child: TextFormField(
                                        controller: _serverCtrl,
                                        decoration: const InputDecoration(
                                          labelText: 'Server-URL',
                                          prefixIcon: Icon(Icons.dns_outlined),
                                          hintText: 'https://ihre-domain.de',
                                        ),
                                        keyboardType: TextInputType.url,
                                        textInputAction: TextInputAction.next,
                                        validator: (v) => (v == null || v.isEmpty) ? 'Server-URL eingeben' : null,
                                      ),
                                    )
                                  : const SizedBox.shrink(),
                            ),
                            const SizedBox(height: 14),

                            TextFormField(
                              controller: _emailCtrl,
                              decoration: const InputDecoration(
                                labelText: 'E-Mail',
                                prefixIcon: Icon(Icons.email_outlined),
                              ),
                              keyboardType: TextInputType.emailAddress,
                              textInputAction: TextInputAction.next,
                              validator: (v) => (v == null || v.isEmpty) ? 'E-Mail eingeben' : null,
                            ),
                            const SizedBox(height: 14),

                            TextFormField(
                              controller: _passCtrl,
                              obscureText: _obscure,
                              decoration: InputDecoration(
                                labelText: 'Passwort',
                                prefixIcon: const Icon(Icons.lock_outlined),
                                suffixIcon: IconButton(
                                  icon: Icon(_obscure
                                      ? Icons.visibility_outlined
                                      : Icons.visibility_off_outlined),
                                  onPressed: () => setState(() => _obscure = !_obscure),
                                ),
                              ),
                              textInputAction: TextInputAction.done,
                              onFieldSubmitted: (_) => _submit(),
                              validator: (v) => (v == null || v.isEmpty) ? 'Passwort eingeben' : null,
                            ),

                            if (_error != null) ...[
                              const SizedBox(height: 14),
                              Container(
                                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                                decoration: BoxDecoration(
                                  color: AppTheme.danger.withValues(alpha: 0.10),
                                  borderRadius: BorderRadius.circular(10),
                                  border: Border.all(color: AppTheme.danger.withValues(alpha: 0.25)),
                                ),
                                child: Row(children: [
                                  Icon(Icons.error_outline, size: 16, color: AppTheme.danger),
                                  const SizedBox(width: 8),
                                  Expanded(child: Text(_error!,
                                    style: TextStyle(color: AppTheme.danger, fontSize: 13))),
                                ]),
                              ),
                            ],

                            const SizedBox(height: 22),

                            /* Login Button */
                            SizedBox(
                              height: 52,
                              child: DecoratedBox(
                                decoration: BoxDecoration(
                                  gradient: const LinearGradient(
                                    colors: [AppTheme.primary, AppTheme.secondary],
                                  ),
                                  borderRadius: BorderRadius.circular(14),
                                  boxShadow: [
                                    BoxShadow(
                                      color: AppTheme.primary.withValues(alpha: 0.35),
                                      blurRadius: 16, offset: const Offset(0, 6),
                                    ),
                                  ],
                                ),
                                child: ElevatedButton(
                                  onPressed: _loading ? null : _submit,
                                  style: ElevatedButton.styleFrom(
                                    backgroundColor: Colors.transparent,
                                    shadowColor: Colors.transparent,
                                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                                  ),
                                  child: _loading
                                      ? const SizedBox(height: 22, width: 22,
                                          child: CircularProgressIndicator(strokeWidth: 2.5, color: Colors.white))
                                      : const Text('Anmelden',
                                          style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700,
                                            color: Colors.white)),
                                ),
                              ),
                            ),
                          ]),
                        ),
                      ),
                      const SizedBox(height: 28),
                      Text('v1.0.5 · TheraPano',
                        style: TextStyle(fontSize: 11,
                          color: isDark ? Colors.white24 : Colors.black26)),
                    ]),
                  ),
                ),
              ),
            ),
          ),
        ),
      ]),
    );
  }
}

/* ── Animated gradient background ────────────────────────────────────────── */
class _GradientBg extends StatefulWidget {
  final bool isDark;
  const _GradientBg({required this.isDark});
  @override
  State<_GradientBg> createState() => _GradientBgState();
}

class _GradientBgState extends State<_GradientBg> with SingleTickerProviderStateMixin {
  late AnimationController _ctrl;

  @override
  void initState() {
    super.initState();
    _ctrl = AnimationController(vsync: this, duration: const Duration(seconds: 8))
      ..repeat(reverse: true);
  }

  @override
  void dispose() { _ctrl.dispose(); super.dispose(); }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: _ctrl,
      builder: (_, __) => Container(
        decoration: BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment(-1 + _ctrl.value * 0.4, -1),
            end: Alignment(1, 1 - _ctrl.value * 0.3),
            colors: widget.isDark
                ? [
                    const Color(0xFF0A0C14),
                    Color.lerp(const Color(0xFF111827), const Color(0xFF0D1321), _ctrl.value)!,
                    const Color(0xFF0A0C14),
                  ]
                : [
                    Color.lerp(const Color(0xFFEEF2FF), const Color(0xFFF0F4FF), _ctrl.value)!,
                    const Color(0xFFF8FAFF),
                    Color.lerp(const Color(0xFFEDE9FE), const Color(0xFFE0E7FF), _ctrl.value)!,
                  ],
          ),
        ),
      ),
    );
  }
}

/* ── Animated Paw/Logo Badge ─────────────────────────────────────────────── */
class _LogoBadge extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Container(
      width: 80, height: 80,
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [AppTheme.primary, AppTheme.secondary],
          begin: Alignment.topLeft, end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(24),
        boxShadow: [
          BoxShadow(
            color: AppTheme.primary.withValues(alpha: 0.4),
            blurRadius: 24, offset: const Offset(0, 8),
          ),
        ],
      ),
      child: const Center(
        child: Text('🐾', style: TextStyle(fontSize: 36)),
      ),
    );
  }
}
