import 'dart:math' as math;
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../services/owner_portal_auth_service.dart';
import '../../core/theme.dart';

class OwnerPortalLoginScreen extends StatefulWidget {
  const OwnerPortalLoginScreen({super.key});

  @override
  State<OwnerPortalLoginScreen> createState() => _OwnerPortalLoginScreenState();
}

class _OwnerPortalLoginScreenState extends State<OwnerPortalLoginScreen>
    with TickerProviderStateMixin {
  final _formKey    = GlobalKey<FormState>();
  final _emailCtrl  = TextEditingController();
  final _passCtrl   = TextEditingController();
  bool _loading     = false;
  bool _obscure     = true;
  String? _error;

  late final AnimationController _bgCtrl;
  late final AnimationController _enterCtrl;
  late final AnimationController _shakeCtrl;
  late final AnimationController _logoCtrl;
  late final Animation<double> _bgAnim;
  late final Animation<double> _cardOpacity;
  late final Animation<Offset> _cardSlide;
  late final Animation<double> _logoScale;

  static const _portalColor  = Color(0xFF10B981);
  static const _portalColor2 = Color(0xFF059669);
  static const _bgDark1      = Color(0xFF061A12);
  static const _bgDark2      = Color(0xFF0D2818);

  @override
  void initState() {
    super.initState();
    _bgCtrl = AnimationController(vsync: this, duration: const Duration(seconds: 8))
      ..repeat(reverse: true);
    _bgAnim = CurvedAnimation(parent: _bgCtrl, curve: Curves.easeInOut);

    _enterCtrl = AnimationController(vsync: this, duration: const Duration(milliseconds: 800));
    _cardOpacity = CurvedAnimation(parent: _enterCtrl, curve: const Interval(0, 0.6, curve: Curves.easeOut));
    _cardSlide   = Tween<Offset>(begin: const Offset(0, 0.12), end: Offset.zero)
        .animate(CurvedAnimation(parent: _enterCtrl, curve: Curves.easeOutCubic));

    _shakeCtrl = AnimationController(vsync: this, duration: const Duration(milliseconds: 480));
    _logoCtrl  = AnimationController(vsync: this, duration: const Duration(milliseconds: 1400))
      ..repeat(reverse: true);
    _logoScale = Tween<double>(begin: 1.0, end: 1.06)
        .animate(CurvedAnimation(parent: _logoCtrl, curve: Curves.easeInOut));

    Future.delayed(const Duration(milliseconds: 120), () {
      if (mounted) _enterCtrl.forward();
    });
  }

  @override
  void dispose() {
    _bgCtrl.dispose(); _enterCtrl.dispose(); _shakeCtrl.dispose(); _logoCtrl.dispose();
    _emailCtrl.dispose(); _passCtrl.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() { _loading = true; _error = null; });

    final auth   = context.read<OwnerPortalAuthService>();
    final result = await auth.loginWithResult(_emailCtrl.text.trim(), _passCtrl.text);

    if (!mounted) return;
    if (result.success) {
      context.go('/owner-portal/dashboard');
    } else {
      setState(() { _loading = false; _error = result.message; });
      _shakeCtrl.forward(from: 0);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Stack(children: [
        _AnimatedBg(anim: _bgAnim, c1: _bgDark1, c2: _bgDark2),
        SafeArea(
          child: Center(
            child: SingleChildScrollView(
              padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 32),
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 440),
                child: FadeTransition(
                  opacity: _cardOpacity,
                  child: SlideTransition(
                    position: _cardSlide,
                    child: Column(children: [
                      _buildLogo(),
                      const SizedBox(height: 20),
                      _buildTitle(),
                      const SizedBox(height: 6),
                      Text('portal.therapano.de',
                        style: TextStyle(fontSize: 12, color: Colors.white.withValues(alpha: 0.4))),
                      const SizedBox(height: 32),
                      _buildCard(),
                      const SizedBox(height: 24),
                      Text('v1.2.0 · TheraPano Portal',
                        style: TextStyle(fontSize: 11, color: Colors.white.withValues(alpha: 0.22))),
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

  Widget _buildLogo() {
    return AnimatedBuilder(
      animation: _logoCtrl,
      builder: (_, __) => Transform.scale(
        scale: _logoScale.value,
        child: Container(
          width: 88, height: 88,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            gradient: const LinearGradient(
              colors: [_portalColor, _portalColor2, Color(0xFF047857)],
              begin: Alignment.topLeft, end: Alignment.bottomRight,
            ),
            boxShadow: [BoxShadow(
              color: _portalColor.withValues(alpha: 0.5),
              blurRadius: 30, offset: const Offset(0, 8),
            )],
          ),
          child: const Center(child: Text('🐾', style: TextStyle(fontSize: 36))),
        ),
      ),
    );
  }

  Widget _buildTitle() {
    return Column(children: [
      ShaderMask(
        shaderCallback: (r) => const LinearGradient(
          colors: [Colors.white, Color(0xFFBBF7D0)],
        ).createShader(r),
        child: const Text('Besitzerportal',
          style: TextStyle(fontSize: 34, fontWeight: FontWeight.w800,
              color: Colors.white, letterSpacing: -0.5)),
      ),
      const SizedBox(height: 4),
      Text('Für Tierhalter & Hundebesitzer',
        style: TextStyle(fontSize: 13, color: Colors.white.withValues(alpha: 0.5))),
    ]);
  }

  Widget _buildCard() {
    return ClipRRect(
      borderRadius: BorderRadius.circular(26),
      child: Container(
        decoration: BoxDecoration(
          color: Colors.white.withValues(alpha: 0.07),
          borderRadius: BorderRadius.circular(26),
          border: Border.all(color: Colors.white.withValues(alpha: 0.12), width: 1.2),
        ),
        child: Padding(
          padding: const EdgeInsets.fromLTRB(26, 28, 26, 26),
          child: Form(
            key: _formKey,
            child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
              Row(children: [
                Container(width: 3, height: 20,
                  decoration: BoxDecoration(
                    gradient: const LinearGradient(
                      colors: [_portalColor, _portalColor2],
                      begin: Alignment.topCenter, end: Alignment.bottomCenter,
                    ),
                    borderRadius: BorderRadius.circular(2),
                  ),
                ),
                const SizedBox(width: 10),
                const Text('Anmelden',
                  style: TextStyle(fontSize: 18, fontWeight: FontWeight.w700, color: Colors.white)),
              ]),
              const SizedBox(height: 20),
              _PortalTextField(
                controller: _emailCtrl,
                label: 'E-Mail',
                icon: Icons.alternate_email_rounded,
                keyboardType: TextInputType.emailAddress,
                textInputAction: TextInputAction.next,
                validator: (v) => (v == null || v.isEmpty) ? 'E-Mail eingeben' : null,
              ),
              const SizedBox(height: 14),
              _PortalTextField(
                controller: _passCtrl,
                label: 'Passwort',
                icon: Icons.lock_rounded,
                obscureText: _obscure,
                textInputAction: TextInputAction.done,
                onFieldSubmitted: (_) => _submit(),
                validator: (v) => (v == null || v.isEmpty) ? 'Passwort eingeben' : null,
                suffixIcon: IconButton(
                  icon: Icon(_obscure ? Icons.visibility_rounded : Icons.visibility_off_rounded,
                    size: 20, color: Colors.white38),
                  onPressed: () => setState(() => _obscure = !_obscure),
                ),
              ),
              if (_error != null) ...[
                const SizedBox(height: 12),
                AnimatedBuilder(
                  animation: _shakeCtrl,
                  builder: (_, child) => Transform.translate(
                    offset: Offset(math.sin(_shakeCtrl.value * math.pi * 6) * 6 * (1 - _shakeCtrl.value), 0),
                    child: child,
                  ),
                  child: Container(
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: AppTheme.danger.withValues(alpha: 0.12),
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: AppTheme.danger.withValues(alpha: 0.35)),
                    ),
                    child: Row(children: [
                      const Icon(Icons.error_outline_rounded, color: AppTheme.danger, size: 16),
                      const SizedBox(width: 8),
                      Expanded(child: Text(_error!,
                        style: TextStyle(color: AppTheme.danger.withValues(alpha: 0.9), fontSize: 13))),
                    ]),
                  ),
                ),
              ],
              const SizedBox(height: 22),
              SizedBox(
                height: 52,
                child: DecoratedBox(
                  decoration: BoxDecoration(
                    gradient: const LinearGradient(colors: [_portalColor, _portalColor2]),
                    borderRadius: BorderRadius.circular(14),
                    boxShadow: [BoxShadow(
                      color: _portalColor.withValues(alpha: 0.35),
                      blurRadius: 18, offset: const Offset(0, 6),
                    )],
                  ),
                  child: ElevatedButton(
                    onPressed: _loading ? null : _submit,
                    style: ElevatedButton.styleFrom(
                      backgroundColor: Colors.transparent, shadowColor: Colors.transparent,
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                    ),
                    child: _loading
                        ? const SizedBox(width: 22, height: 22,
                            child: CircularProgressIndicator(strokeWidth: 2.5, color: Colors.white))
                        : const Row(mainAxisAlignment: MainAxisAlignment.center, children: [
                            Icon(Icons.login_rounded, color: Colors.white, size: 18),
                            SizedBox(width: 8),
                            Text('Anmelden',
                              style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700, color: Colors.white)),
                          ]),
                  ),
                ),
              ),
            ]),
          ),
        ),
      ),
    );
  }
}

class _AnimatedBg extends StatelessWidget {
  final Animation<double> anim;
  final Color c1, c2;
  const _AnimatedBg({required this.anim, required this.c1, required this.c2});

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: anim,
      builder: (_, __) => Container(
        decoration: BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment(-1 + anim.value * 0.4, -1),
            end: Alignment(1, 1 - anim.value * 0.3),
            colors: [
              Color.lerp(c1, const Color(0xFF040F09), anim.value)!,
              Color.lerp(c2, const Color(0xFF0A1F14), anim.value)!,
              Color.lerp(const Color(0xFF061812), c1, anim.value)!,
            ],
            stops: const [0.0, 0.5, 1.0],
          ),
        ),
      ),
    );
  }
}

class _PortalTextField extends StatelessWidget {
  final TextEditingController controller;
  final String label;
  final IconData icon;
  final TextInputType? keyboardType;
  final TextInputAction? textInputAction;
  final bool obscureText;
  final String? Function(String?)? validator;
  final void Function(String)? onFieldSubmitted;
  final Widget? suffixIcon;

  const _PortalTextField({
    required this.controller,
    required this.label,
    required this.icon,
    this.keyboardType,
    this.textInputAction,
    this.obscureText = false,
    this.validator,
    this.onFieldSubmitted,
    this.suffixIcon,
  });

  @override
  Widget build(BuildContext context) {
    return TextFormField(
      controller: controller,
      keyboardType: keyboardType,
      textInputAction: textInputAction,
      obscureText: obscureText,
      onFieldSubmitted: onFieldSubmitted,
      validator: validator,
      style: const TextStyle(color: Colors.white, fontSize: 15),
      decoration: InputDecoration(
        labelText: label,
        labelStyle: TextStyle(color: Colors.white.withValues(alpha: 0.5), fontSize: 14),
        prefixIcon: Icon(icon, color: Colors.white.withValues(alpha: 0.4), size: 20),
        suffixIcon: suffixIcon,
        filled: true,
        fillColor: Colors.white.withValues(alpha: 0.06),
        border: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: BorderSide.none),
        enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(14),
            borderSide: BorderSide(color: Colors.white.withValues(alpha: 0.10))),
        focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(14),
            borderSide: const BorderSide(color: Color(0xFF10B981), width: 1.8)),
        errorBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(14),
            borderSide: const BorderSide(color: AppTheme.danger, width: 1.5)),
        focusedErrorBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(14),
            borderSide: const BorderSide(color: AppTheme.danger, width: 1.8)),
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
      ),
    );
  }
}
