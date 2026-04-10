import 'dart:math' as math;
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../services/auth_service.dart';
import '../core/theme.dart';

// ─────────────────────────────────────────────────────────────────────────────
// Login Screen
// ─────────────────────────────────────────────────────────────────────────────

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen>
    with TickerProviderStateMixin {
  final _formKey   = GlobalKey<FormState>();
  final _emailCtrl = TextEditingController();
  final _passCtrl  = TextEditingController();
  bool _loading  = false;
  bool _obscure  = true;
  String? _error;
  LoginError? _errorType;

  // ── Animation controllers ──────────────────────────────────────────────────
  late final AnimationController _bgCtrl;       // animated gradient background
  late final AnimationController _particleCtrl; // floating paw particles
  late final AnimationController _enterCtrl;    // card entrance
  late final AnimationController _logoCtrl;     // logo pulse
  late final AnimationController _glowCtrl;     // logo glow ring
  late final AnimationController _lettersCtrl;  // letter stagger
  late final AnimationController _shakeCtrl;    // error shake
  late final AnimationController _shimmerCtrl;  // button shimmer

  // ── Derived animations ────────────────────────────────────────────────────
  late final Animation<double> _bgAnim;
  late final Animation<double> _cardOpacity;
  late final Animation<Offset> _cardSlide;
  late final Animation<double> _logoScale;
  late final Animation<double> _glowScale;
  late final Animation<double> _glowOpacity;
  late final Animation<double> _shimmer;

  static const _letters = 'TheraPano';
  late final List<_LoginParticle> _particles;

  @override
  void initState() {
    super.initState();

    // Background
    _bgCtrl = AnimationController(vsync: this, duration: const Duration(seconds: 7))
      ..repeat(reverse: true);
    _bgAnim = CurvedAnimation(parent: _bgCtrl, curve: Curves.easeInOut);

    // Particles
    _particleCtrl = AnimationController(vsync: this, duration: const Duration(seconds: 6))
      ..repeat();
    final rand = math.Random(77);
    _particles = List.generate(20, (i) => _LoginParticle(
      x: rand.nextDouble(),
      baseY: rand.nextDouble(),
      size: 3.0 + rand.nextDouble() * 9.0,
      speed: 0.25 + rand.nextDouble() * 0.55,
      phase: rand.nextDouble(),
      isPaw: i % 4 == 0,
      opacity: 0.06 + rand.nextDouble() * 0.14,
    ));

    // Card entrance
    _enterCtrl = AnimationController(vsync: this, duration: const Duration(milliseconds: 900));
    _cardOpacity = CurvedAnimation(parent: _enterCtrl, curve: const Interval(0.0, 0.6, curve: Curves.easeOut));
    _cardSlide = Tween<Offset>(begin: const Offset(0, 0.15), end: Offset.zero)
        .animate(CurvedAnimation(parent: _enterCtrl, curve: Curves.easeOutCubic));

    // Logo pulse
    _logoCtrl = AnimationController(vsync: this, duration: const Duration(milliseconds: 1200))
      ..repeat(reverse: true);
    _logoScale = Tween<double>(begin: 1.0, end: 1.07)
        .animate(CurvedAnimation(parent: _logoCtrl, curve: Curves.easeInOut));

    // Glow ring
    _glowCtrl = AnimationController(vsync: this, duration: const Duration(milliseconds: 2000))
      ..repeat();
    _glowScale   = Tween<double>(begin: 0.85, end: 1.9).animate(CurvedAnimation(parent: _glowCtrl, curve: Curves.easeOut));
    _glowOpacity = Tween<double>(begin: 0.40, end: 0.0).animate(CurvedAnimation(parent: _glowCtrl, curve: Curves.easeOut));

    // Letter stagger
    _lettersCtrl = AnimationController(vsync: this, duration: Duration(milliseconds: 300 + _letters.length * 80));

    // Shake for errors
    _shakeCtrl = AnimationController(vsync: this, duration: const Duration(milliseconds: 500));

    // Shimmer on button
    _shimmerCtrl = AnimationController(vsync: this, duration: const Duration(milliseconds: 2200))
      ..repeat();
    _shimmer = Tween<double>(begin: -1.5, end: 2.5)
        .animate(CurvedAnimation(parent: _shimmerCtrl, curve: Curves.easeInOut));

    // Start entrance sequence
    Future.delayed(const Duration(milliseconds: 80), () {
      if (mounted) _lettersCtrl.forward();
    });
    Future.delayed(const Duration(milliseconds: 200), () {
      if (mounted) _enterCtrl.forward();
    });
  }

  @override
  void dispose() {
    _bgCtrl.dispose();
    _particleCtrl.dispose();
    _enterCtrl.dispose();
    _logoCtrl.dispose();
    _glowCtrl.dispose();
    _lettersCtrl.dispose();
    _shakeCtrl.dispose();
    _shimmerCtrl.dispose();
    _emailCtrl.dispose();
    _passCtrl.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() { _loading = true; _error = null; _errorType = null; });

    final result = await context.read<AuthService>().loginWithResult(
      _emailCtrl.text.trim(),
      _passCtrl.text,
    );

    if (mounted) {
      setState(() {
        _loading = false;
        if (!result.success) {
          _error = result.message ?? 'Unbekannter Fehler.';
          _errorType = result.error;
          _shakeCtrl.forward(from: 0);
        }
      });
    }
  }

  // ── Build ──────────────────────────────────────────────────────────────────

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Stack(children: [
        // Animated gradient background
        _AnimatedBg(bgAnim: _bgAnim),
        // Floating particles
        AnimatedBuilder(
          animation: _particleCtrl,
          builder: (_, __) => CustomPaint(
            painter: _LoginParticlePainter(_particles, _particleCtrl.value),
            size: Size.infinite,
          ),
        ),
        // Main content
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
                      // Logo
                      _buildLogo(),
                      const SizedBox(height: 22),
                      // Staggered title letters
                      _buildTitle(),
                      const SizedBox(height: 8),
                      _buildSubtitle(),
                      const SizedBox(height: 36),
                      // Glass form card
                      _buildCard(),
                      const SizedBox(height: 28),
                      // Footer
                      _buildFooter(context),
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

  // ── Logo ──────────────────────────────────────────────────────────────────

  Widget _buildLogo() {
    return AnimatedBuilder(
      animation: Listenable.merge([_logoCtrl, _glowCtrl]),
      builder: (_, __) {
        return SizedBox(
          width: 120,
          height: 120,
          child: Stack(alignment: Alignment.center, children: [
            // Expanding glow ring
            Opacity(
              opacity: _glowOpacity.value,
              child: Container(
                width: 90 * _glowScale.value,
                height: 90 * _glowScale.value,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  gradient: RadialGradient(colors: [
                    AppTheme.secondary.withValues(alpha: 0.45),
                    AppTheme.primary.withValues(alpha: 0.0),
                  ]),
                ),
              ),
            ),
            // Ambient glow
            Container(
              width: 100,
              height: 100,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                boxShadow: [
                  BoxShadow(
                    color: AppTheme.secondary.withValues(alpha: 0.3),
                    blurRadius: 55,
                    spreadRadius: 8,
                  ),
                  BoxShadow(
                    color: AppTheme.primary.withValues(alpha: 0.2),
                    blurRadius: 70,
                    spreadRadius: 14,
                  ),
                ],
              ),
            ),
            // Pulsing logo
            Transform.scale(
              scale: _logoScale.value,
              child: Container(
                width: 88,
                height: 88,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  gradient: const LinearGradient(
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                    colors: [AppTheme.primary, AppTheme.secondary, Color(0xFF7C3AED)],
                  ),
                  boxShadow: [
                    BoxShadow(
                      color: AppTheme.secondary.withValues(alpha: 0.55),
                      blurRadius: 28,
                      offset: const Offset(0, 8),
                    ),
                  ],
                ),
                child: const Center(
                  child: Text('🐾', style: TextStyle(fontSize: 38)),
                ),
              ),
            ),
          ]),
        );
      },
    );
  }

  // ── Staggered letter title ────────────────────────────────────────────────

  Widget _buildTitle() {
    return AnimatedBuilder(
      animation: _lettersCtrl,
      builder: (_, __) {
        return Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: List.generate(_letters.length, (i) {
            final start = (i / _letters.length) * 0.65;
            final end   = (start + 0.35).clamp(0.0, 1.0);
            final raw   = (_lettersCtrl.value - start) / (end - start);
            final t     = Curves.easeOutBack.transform(raw.clamp(0.0, 1.0));
            final isThera = i < 5;
            return Transform.translate(
              offset: Offset(0, (1 - t) * -28),
              child: Opacity(
                opacity: t,
                child: Text(
                  _letters[i],
                  style: TextStyle(
                    fontSize: 40,
                    fontWeight: FontWeight.w900,
                    letterSpacing: 1.5,
                    height: 1.0,
                    foreground: Paint()
                      ..shader = LinearGradient(
                        colors: isThera
                          ? [Colors.white, const Color(0xFFB8D4FF)]
                          : [AppTheme.secondary.withValues(alpha: 0.95), const Color(0xFFD8B4FE)],
                      ).createShader(const Rect.fromLTWH(0, 0, 55, 50)),
                  ),
                ),
              ),
            );
          }),
        );
      },
    );
  }

  Widget _buildSubtitle() {
    return Text(
      'Mehr Zeit fürs Tier.',
      style: TextStyle(
        fontSize: 13,
        letterSpacing: 0.3,
        color: Colors.white.withValues(alpha: 0.55),
      ),
    );
  }

  // ── Glass Form Card ────────────────────────────────────────────────────────

  Widget _buildCard() {
    return ClipRRect(
      borderRadius: BorderRadius.circular(28),
      child: Container(
        decoration: BoxDecoration(
          color: Colors.white.withValues(alpha: 0.07),
          borderRadius: BorderRadius.circular(28),
          border: Border.all(color: Colors.white.withValues(alpha: 0.14), width: 1.2),
          boxShadow: [
            BoxShadow(
              color: AppTheme.primary.withValues(alpha: 0.12),
              blurRadius: 48,
              offset: const Offset(0, 20),
            ),
          ],
        ),
        child: Stack(children: [
          // Inner shimmer highlight at top
          Positioned(
            top: 0,
            left: 0,
            right: 0,
            child: Container(
              height: 2,
              decoration: BoxDecoration(
                gradient: LinearGradient(colors: [
                  Colors.transparent,
                  Colors.white.withValues(alpha: 0.35),
                  Colors.transparent,
                ]),
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(28, 30, 28, 28),
            child: Form(
              key: _formKey,
              child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                // Section header
                Row(children: [
                  Container(
                    width: 4,
                    height: 22,
                    decoration: BoxDecoration(
                      gradient: const LinearGradient(
                        colors: [AppTheme.primary, AppTheme.secondary],
                        begin: Alignment.topCenter,
                        end: Alignment.bottomCenter,
                      ),
                      borderRadius: BorderRadius.circular(2),
                    ),
                  ),
                  const SizedBox(width: 10),
                  const Text('Anmelden',
                    style: TextStyle(
                      fontSize: 20,
                      fontWeight: FontWeight.w700,
                      color: Colors.white,
                    ),
                  ),
                ]),

                // Server info badge
                const SizedBox(height: 18),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                  decoration: BoxDecoration(
                    color: AppTheme.success.withValues(alpha: 0.12),
                    borderRadius: BorderRadius.circular(10),
                    border: Border.all(color: AppTheme.success.withValues(alpha: 0.25)),
                  ),
                  child: Row(children: [
                    const Icon(Icons.cloud_done_outlined, size: 14, color: AppTheme.success),
                    const SizedBox(width: 8),
                    Text(
                      'app.therapano.de',
                      style: TextStyle(
                        fontSize: 12, color: AppTheme.success.withValues(alpha: 0.9),
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                    const Spacer(),
                    Container(
                      width: 6, height: 6,
                      decoration: const BoxDecoration(color: AppTheme.success, shape: BoxShape.circle),
                    ),
                  ]),
                ),

                const SizedBox(height: 20),

                // Email
                _DarkTextField(
                  controller: _emailCtrl,
                  label: 'E-Mail',
                  icon: Icons.alternate_email_rounded,
                  keyboardType: TextInputType.emailAddress,
                  textInputAction: TextInputAction.next,
                  validator: (v) => (v == null || v.isEmpty) ? 'E-Mail eingeben' : null,
                ),
                const SizedBox(height: 14),

                // Password
                _DarkTextField(
                  controller: _passCtrl,
                  label: 'Passwort',
                  icon: Icons.lock_rounded,
                  obscureText: _obscure,
                  textInputAction: TextInputAction.done,
                  onFieldSubmitted: (_) => _submit(),
                  validator: (v) => (v == null || v.isEmpty) ? 'Passwort eingeben' : null,
                  suffixIcon: IconButton(
                    icon: Icon(
                      _obscure ? Icons.visibility_rounded : Icons.visibility_off_rounded,
                      size: 20,
                      color: Colors.white38,
                    ),
                    onPressed: () => setState(() => _obscure = !_obscure),
                  ),
                ),

                // Error message with shake
                if (_error != null) ...[
                  const SizedBox(height: 14),
                  AnimatedBuilder(
                    animation: _shakeCtrl,
                    builder: (_, child) => Transform.translate(
                      offset: Offset(
                        math.sin(_shakeCtrl.value * math.pi * 6) * 6 * (1 - _shakeCtrl.value),
                        0,
                      ),
                      child: child,
                    ),
                    child: _ErrorBanner(error: _error!, errorType: _errorType),
                  ),
                ],

                const SizedBox(height: 24),

                // Login button with shimmer
                _ShimmerButton(
                  shimmer: _shimmer,
                  loading: _loading,
                  onPressed: _submit,
                ),
              ]),
            ),
          ),
        ]),
      ),
    );
  }

  Widget _buildFooter(BuildContext context) {
    return Column(children: [
      Container(
        width: 44, height: 1.5,
        decoration: BoxDecoration(
          gradient: LinearGradient(colors: [
            Colors.transparent,
            Colors.white.withValues(alpha: 0.25),
            Colors.transparent,
          ]),
        ),
      ),
      const SizedBox(height: 12),
      Text(
        'v1.1.2 · TheraPano · Tierphysiotherapie',
        style: TextStyle(
          fontSize: 11,
          color: Colors.white.withValues(alpha: 0.25),
          letterSpacing: 0.3,
        ),
      ),
    ]);
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// Animated background
// ─────────────────────────────────────────────────────────────────────────────

class _AnimatedBg extends StatelessWidget {
  final Animation<double> bgAnim;
  const _AnimatedBg({required this.bgAnim});

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: bgAnim,
      builder: (_, __) {
        final t = bgAnim.value;
        return Container(
          decoration: BoxDecoration(
            gradient: LinearGradient(
              begin: Alignment(-1 + t * 0.5, -1),
              end: Alignment(1, 1 - t * 0.4),
              colors: [
                Color.lerp(const Color(0xFF06080F), const Color(0xFF0D0B1A), t)!,
                Color.lerp(const Color(0xFF0C1528), const Color(0xFF1A0D38), t)!,
                Color.lerp(const Color(0xFF10082A), const Color(0xFF05080F), t)!,
              ],
              stops: const [0.0, 0.5, 1.0],
            ),
          ),
        );
      },
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// Dark-themed text field for the dark glass card
// ─────────────────────────────────────────────────────────────────────────────

class _DarkTextField extends StatelessWidget {
  final TextEditingController controller;
  final String label;
  final IconData icon;
  final TextInputType? keyboardType;
  final TextInputAction? textInputAction;
  final bool obscureText;
  final String? Function(String?)? validator;
  final void Function(String)? onFieldSubmitted;
  final Widget? suffixIcon;

  const _DarkTextField({
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
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: BorderSide.none,
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: BorderSide(color: Colors.white.withValues(alpha: 0.10)),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: const BorderSide(color: AppTheme.primary, width: 1.8),
        ),
        errorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: const BorderSide(color: AppTheme.danger, width: 1.5),
        ),
        focusedErrorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: const BorderSide(color: AppTheme.danger, width: 1.8),
        ),
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
      ),
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// Error banner
// ─────────────────────────────────────────────────────────────────────────────

class _ErrorBanner extends StatelessWidget {
  final String error;
  final LoginError? errorType;
  const _ErrorBanner({required this.error, this.errorType});

  @override
  Widget build(BuildContext context) {
    IconData icon;
    String title;
    switch (errorType) {
      case LoginError.network:
        icon  = Icons.wifi_off_rounded;
        title = 'Netzwerkfehler';
      case LoginError.timeout:
        icon  = Icons.timer_off_rounded;
        title = 'Zeitüberschreitung';
      case LoginError.serverError:
        icon  = Icons.dns_rounded;
        title = 'Serverfehler';
      default:
        icon  = Icons.lock_person_rounded;
        title = 'Anmeldung fehlgeschlagen';
    }
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: AppTheme.danger.withValues(alpha: 0.10),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: AppTheme.danger.withValues(alpha: 0.30)),
      ),
      child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Icon(icon, size: 18, color: AppTheme.danger),
        const SizedBox(width: 10),
        Expanded(
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(title,
              style: const TextStyle(
                color: AppTheme.danger,
                fontWeight: FontWeight.w700,
                fontSize: 13,
              ),
            ),
            const SizedBox(height: 3),
            Text(error,
              style: TextStyle(color: AppTheme.danger.withValues(alpha: 0.80), fontSize: 12),
            ),
          ]),
        ),
      ]),
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// Shimmer login button
// ─────────────────────────────────────────────────────────────────────────────

class _ShimmerButton extends StatelessWidget {
  final Animation<double> shimmer;
  final bool loading;
  final VoidCallback onPressed;

  const _ShimmerButton({required this.shimmer, required this.loading, required this.onPressed});

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: shimmer,
      builder: (_, __) {
        return SizedBox(
          height: 54,
          child: Stack(children: [
            // Gradient base
            DecoratedBox(
              decoration: BoxDecoration(
                gradient: const LinearGradient(
                  colors: [AppTheme.primary, AppTheme.secondary, Color(0xFF7C3AED)],
                ),
                borderRadius: BorderRadius.circular(16),
                boxShadow: [
                  BoxShadow(
                    color: AppTheme.secondary.withValues(alpha: 0.40),
                    blurRadius: 24,
                    offset: const Offset(0, 8),
                  ),
                ],
              ),
              child: const SizedBox.expand(),
            ),
            // Shimmer highlight
            if (!loading)
              Positioned.fill(
                child: ClipRRect(
                  borderRadius: BorderRadius.circular(16),
                  child: CustomPaint(
                    painter: _ShimmerPainter(shimmer.value),
                  ),
                ),
              ),
            // Button
            Positioned.fill(
              child: ElevatedButton(
                onPressed: loading ? null : onPressed,
                style: ElevatedButton.styleFrom(
                  backgroundColor: Colors.transparent,
                  shadowColor: Colors.transparent,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                  disabledBackgroundColor: Colors.transparent,
                ),
                child: loading
                    ? const SizedBox(
                        height: 22, width: 22,
                        child: CircularProgressIndicator(strokeWidth: 2.5, color: Colors.white),
                      )
                    : const Row(mainAxisAlignment: MainAxisAlignment.center, children: [
                        Icon(Icons.login_rounded, color: Colors.white, size: 20),
                        SizedBox(width: 10),
                        Text('Anmelden',
                          style: TextStyle(
                            fontSize: 16,
                            fontWeight: FontWeight.w700,
                            color: Colors.white,
                            letterSpacing: 0.3,
                          ),
                        ),
                      ]),
              ),
            ),
          ]),
        );
      },
    );
  }
}

class _ShimmerPainter extends CustomPainter {
  final double progress; // -1.5 .. 2.5
  _ShimmerPainter(this.progress);

  @override
  void paint(Canvas canvas, Size size) {
    final x = size.width * (progress - 0.5) / 1.0;
    final paint = Paint()
      ..shader = LinearGradient(
        begin: Alignment.centerLeft,
        end: Alignment.centerRight,
        colors: [
          Colors.white.withValues(alpha: 0.0),
          Colors.white.withValues(alpha: 0.18),
          Colors.white.withValues(alpha: 0.0),
        ],
        stops: const [0.0, 0.5, 1.0],
      ).createShader(Rect.fromLTWH(x - 60, 0, 120, size.height));
    canvas.drawRect(Offset.zero & size, paint);
  }

  @override
  bool shouldRepaint(covariant _ShimmerPainter old) => old.progress != progress;
}

// ─────────────────────────────────────────────────────────────────────────────
// Particle system (login page variant)
// ─────────────────────────────────────────────────────────────────────────────

class _LoginParticle {
  final double x;
  final double baseY;
  final double size;
  final double speed;
  final double phase;
  final bool isPaw;
  final double opacity;

  const _LoginParticle({
    required this.x, required this.baseY, required this.size,
    required this.speed, required this.phase, required this.isPaw,
    required this.opacity,
  });
}

class _LoginParticlePainter extends CustomPainter {
  final List<_LoginParticle> particles;
  final double t;

  _LoginParticlePainter(this.particles, this.t);

  @override
  void paint(Canvas canvas, Size size) {
    for (final p in particles) {
      final progress = ((t * p.speed + p.phase) % 1.0);
      final y = size.height * (1.0 - progress);
      final x = size.width * p.x + math.sin(progress * math.pi * 2 + p.phase * 6) * 12;
      final opacity = p.opacity * math.sin(progress * math.pi).clamp(0.0, 1.0);
      if (opacity <= 0) continue;

      final paint = Paint()
        ..color = (p.isPaw ? AppTheme.secondary : Colors.white).withValues(alpha: opacity)
        ..style = PaintingStyle.fill;

      if (p.isPaw) {
        _drawMiniPaw(canvas, Offset(x, y), p.size, paint);
      } else {
        canvas.drawCircle(Offset(x, y), p.size / 2, paint);
      }
    }
  }

  void _drawMiniPaw(Canvas canvas, Offset center, double size, Paint paint) {
    canvas.drawOval(Rect.fromCenter(
      center: Offset(center.dx, center.dy + size * 0.15),
      width: size * 0.55, height: size * 0.42,
    ), paint);
    for (final o in [
      Offset(-size * 0.26, -size * 0.1), Offset(-size * 0.09, -size * 0.28),
      Offset(size * 0.09, -size * 0.28), Offset(size * 0.26, -size * 0.1),
    ]) {
      canvas.drawOval(Rect.fromCenter(
        center: Offset(center.dx + o.dx, center.dy + o.dy),
        width: size * 0.2, height: size * 0.26,
      ), paint);
    }
  }

  @override
  bool shouldRepaint(covariant _LoginParticlePainter old) => old.t != t;
}
