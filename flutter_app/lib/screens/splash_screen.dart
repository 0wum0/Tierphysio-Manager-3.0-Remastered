import 'dart:math' as math;
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import '../services/auth_service.dart';
import '../core/theme.dart';

class SplashScreen extends StatefulWidget {
  final AuthService authService;
  final VoidCallback onComplete;
  const SplashScreen({super.key, required this.authService, required this.onComplete});

  @override
  State<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends State<SplashScreen> with TickerProviderStateMixin {
  // ── Controllers ───────────────────────────────────────────────────────────
  late final AnimationController _bgCtrl;      // gradient shift
  late final AnimationController _particleCtrl;// floating particles loop
  late final AnimationController _logoCtrl;    // paw logo entrance
  late final AnimationController _pulseCtrl;   // heartbeat pulse loop
  late final AnimationController _glowCtrl;    // expanding glow ring
  late final AnimationController _lettersCtrl; // letter stagger
  late final AnimationController _sloganCtrl;  // slogan fade+slide
  late final AnimationController _dotsCtrl;    // loading dots loop
  late final AnimationController _exitCtrl;    // exit fade

  // ── Derived animations ────────────────────────────────────────────────────
  late final Animation<double> _bgAnim;
  late final Animation<double> _logoScale;
  late final Animation<double> _logoOpacity;
  late final Animation<double> _pulseScale;
  late final Animation<double> _glowScale;
  late final Animation<double> _glowOpacity;
  late final Animation<double> _sloganOffset;
  late final Animation<double> _sloganOpacity;
  late final Animation<double> _exitOpacity;

  static const _word = 'OmniPet';
  static const _particleCount = 22;
  late final List<_Particle> _particles;
  bool _initDone = false;

  @override
  void initState() {
    super.initState();
    SystemChrome.setEnabledSystemUIMode(SystemUiMode.immersiveSticky);

    // ── Background gradient ───────────────────────────────────────────────
    _bgCtrl = AnimationController(vsync: this, duration: const Duration(seconds: 4))
      ..repeat(reverse: true);
    _bgAnim = CurvedAnimation(parent: _bgCtrl, curve: Curves.easeInOut);

    // ── Particles ─────────────────────────────────────────────────────────
    _particleCtrl = AnimationController(vsync: this, duration: const Duration(seconds: 5))
      ..repeat();
    final rand = math.Random(42);
    _particles = List.generate(_particleCount, (i) => _Particle(
      x: rand.nextDouble(),
      baseY: rand.nextDouble(),
      size: 4 + rand.nextDouble() * 10,
      speed: 0.3 + rand.nextDouble() * 0.7,
      phase: rand.nextDouble(),
      isPaw: i % 4 == 0,
      opacity: 0.08 + rand.nextDouble() * 0.18,
    ));

    // ── Logo entrance (starts at 0ms) ──────────────────────────────────────
    _logoCtrl = AnimationController(vsync: this, duration: const Duration(milliseconds: 900));
    _logoScale   = Tween<double>(begin: 0.0, end: 1.0).animate(
        CurvedAnimation(parent: _logoCtrl, curve: Curves.elasticOut));
    _logoOpacity = Tween<double>(begin: 0.0, end: 1.0).animate(
        CurvedAnimation(parent: _logoCtrl, curve: const Interval(0.0, 0.4)));

    // ── Heartbeat pulse loop (starts after logo) ───────────────────────────
    _pulseCtrl = AnimationController(vsync: this, duration: const Duration(milliseconds: 900))
      ..repeat(reverse: true);
    _pulseScale = Tween<double>(begin: 1.0, end: 1.08).animate(
        CurvedAnimation(parent: _pulseCtrl, curve: Curves.easeInOut));

    // ── Glow ring (starts with logo) ──────────────────────────────────────
    _glowCtrl = AnimationController(vsync: this, duration: const Duration(milliseconds: 1800))
      ..repeat();
    _glowScale   = Tween<double>(begin: 0.8, end: 2.2).animate(
        CurvedAnimation(parent: _glowCtrl, curve: Curves.easeOut));
    _glowOpacity = Tween<double>(begin: 0.45, end: 0.0).animate(
        CurvedAnimation(parent: _glowCtrl, curve: Curves.easeOut));

    // ── Letters (starts at 600ms) ─────────────────────────────────────────
    _lettersCtrl = AnimationController(vsync: this,
        duration: Duration(milliseconds: 300 + _word.length * 80));

    // ── Slogan (starts after letters) ─────────────────────────────────────
    _sloganCtrl = AnimationController(vsync: this, duration: const Duration(milliseconds: 700));
    _sloganOffset  = Tween<double>(begin: 24.0, end: 0.0).animate(
        CurvedAnimation(parent: _sloganCtrl, curve: Curves.easeOutCubic));
    _sloganOpacity = Tween<double>(begin: 0.0, end: 1.0).animate(
        CurvedAnimation(parent: _sloganCtrl, curve: Curves.easeOut));

    // ── Loading dots loop ─────────────────────────────────────────────────
    _dotsCtrl = AnimationController(vsync: this, duration: const Duration(milliseconds: 1200))
      ..repeat();

    // ── Exit fade ─────────────────────────────────────────────────────────
    _exitCtrl = AnimationController(vsync: this, duration: const Duration(milliseconds: 500));
    _exitOpacity = Tween<double>(begin: 1.0, end: 0.0).animate(
        CurvedAnimation(parent: _exitCtrl, curve: Curves.easeIn));

    _runSequence();
  }

  Future<void> _runSequence() async {
    // Start init in parallel
    final initFuture = widget.authService.init();

    await Future.delayed(const Duration(milliseconds: 100));
    _logoCtrl.forward();

    await Future.delayed(const Duration(milliseconds: 600));
    _lettersCtrl.forward();

    await Future.delayed(Duration(milliseconds: 400 + _word.length * 80));
    _sloganCtrl.forward();

    // Wait for init to complete (min 2.8s total splash)
    await Future.wait([
      initFuture,
      Future.delayed(const Duration(milliseconds: 2800)),
    ]);

    if (!mounted) return;
    setState(() => _initDone = true);

    await Future.delayed(const Duration(milliseconds: 600));
    if (!mounted) return;

    await _exitCtrl.forward();
    if (!mounted) return;

    SystemChrome.setEnabledSystemUIMode(SystemUiMode.edgeToEdge);
    widget.onComplete();
  }

  @override
  void dispose() {
    _bgCtrl.dispose();
    _particleCtrl.dispose();
    _logoCtrl.dispose();
    _pulseCtrl.dispose();
    _glowCtrl.dispose();
    _lettersCtrl.dispose();
    _sloganCtrl.dispose();
    _dotsCtrl.dispose();
    _exitCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: Listenable.merge([_exitCtrl]),
      builder: (context, _) => Opacity(
        opacity: _exitOpacity.value,
        child: _buildContent(),
      ),
    );
  }

  Widget _buildContent() {
    return AnimatedBuilder(
      animation: _bgCtrl,
      builder: (context, _) {
        final t = _bgAnim.value;
        return Container(
          decoration: BoxDecoration(
            gradient: LinearGradient(
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
              colors: [
                Color.lerp(const Color(0xFF0B0E1A), const Color(0xFF1A0B2E), t)!,
                Color.lerp(const Color(0xFF0D1B3E), const Color(0xFF2D1B69), t)!,
                Color.lerp(const Color(0xFF1A0B2E), const Color(0xFF0B0E1A), t)!,
              ],
              stops: const [0.0, 0.5, 1.0],
            ),
          ),
          child: Stack(children: [
            // Floating particles
            AnimatedBuilder(
              animation: _particleCtrl,
              builder: (_, __) => CustomPaint(
                painter: _ParticlePainter(_particles, _particleCtrl.value),
                size: Size.infinite,
              ),
            ),
            // Main content
            SafeArea(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  const Spacer(flex: 3),
                  _buildLogo(),
                  const SizedBox(height: 36),
                  _buildTitle(),
                  const SizedBox(height: 14),
                  _buildSlogan(),
                  const Spacer(flex: 2),
                  _buildDots(),
                  const SizedBox(height: 48),
                ],
              ),
            ),
          ]),
        );
      },
    );
  }

  // ── Logo ──────────────────────────────────────────────────────────────────

  Widget _buildLogo() {
    return AnimatedBuilder(
      animation: Listenable.merge([_logoCtrl, _pulseCtrl, _glowCtrl]),
      builder: (_, __) => SizedBox(
        width: 160,
        height: 160,
        child: Stack(alignment: Alignment.center, children: [
          // Outer glow ring
          Opacity(
            opacity: _glowOpacity.value,
            child: Container(
              width: 110 * _glowScale.value,
              height: 110 * _glowScale.value,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                gradient: RadialGradient(colors: [
                  AppTheme.secondary.withValues(alpha: 0.5),
                  AppTheme.primary.withValues(alpha: 0.0),
                ]),
              ),
            ),
          ),
          // Ambient glow (static)
          Container(
            width: 130,
            height: 130,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              boxShadow: [
                BoxShadow(
                  color: AppTheme.secondary.withValues(alpha: 0.35 * _logoOpacity.value),
                  blurRadius: 60,
                  spreadRadius: 10,
                ),
                BoxShadow(
                  color: AppTheme.primary.withValues(alpha: 0.2 * _logoOpacity.value),
                  blurRadius: 80,
                  spreadRadius: 20,
                ),
              ],
            ),
          ),
          // Logo container
          Transform.scale(
            scale: _logoScale.value * _pulseScale.value,
            child: Opacity(
              opacity: _logoOpacity.value.clamp(0.0, 1.0),
              child: Container(
                width: 110,
                height: 110,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  gradient: LinearGradient(
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                    colors: [
                      AppTheme.primary,
                      AppTheme.secondary,
                      const Color(0xFF7C3AED),
                    ],
                  ),
                  boxShadow: [
                    BoxShadow(
                      color: AppTheme.secondary.withValues(alpha: 0.6),
                      blurRadius: 30,
                      offset: const Offset(0, 8),
                    ),
                  ],
                ),
                child: const Center(
                  child: _PawIcon(size: 58, color: Colors.white),
                ),
              ),
            ),
          ),
        ]),
      ),
    );
  }

  // ── Title letters ─────────────────────────────────────────────────────────

  Widget _buildTitle() {
    return AnimatedBuilder(
      animation: _lettersCtrl,
      builder: (_, __) {
        return Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: List.generate(_word.length, (i) {
            final start = i / _word.length * 0.6;
            final end   = start + 0.4;
            final t     = (((_lettersCtrl.value - start) / (end - start)).clamp(0.0, 1.0));
            final curve = Curves.easeOutBack.transform(t);

            final isOmni = i < 4;
            return Transform.translate(
              offset: Offset(0, (1 - curve) * -30),
              child: Opacity(
                opacity: curve,
                child: Text(
                  _word[i],
                  style: TextStyle(
                    fontSize: 52,
                    fontWeight: FontWeight.w900,
                    letterSpacing: 1.5,
                    height: 1.0,
                    foreground: Paint()
                      ..shader = LinearGradient(
                        colors: isOmni
                          ? [Colors.white, const Color(0xFFB8D0FF)]
                          : [AppTheme.secondary.withValues(alpha: 0.9), const Color(0xFFE0AAFF)],
                      ).createShader(const Rect.fromLTWH(0, 0, 60, 60)),
                  ),
                ),
              ),
            );
          }),
        );
      },
    );
  }

  // ── Slogan ────────────────────────────────────────────────────────────────

  Widget _buildSlogan() {
    return AnimatedBuilder(
      animation: _sloganCtrl,
      builder: (_, __) => Transform.translate(
        offset: Offset(0, _sloganOffset.value),
        child: Opacity(
          opacity: _sloganOpacity.value,
          child: Column(children: [
            Container(
              width: 48,
              height: 1.5,
              decoration: BoxDecoration(
                gradient: LinearGradient(colors: [
                  Colors.transparent,
                  Colors.white.withValues(alpha: 0.4),
                  Colors.transparent,
                ]),
              ),
            ),
            const SizedBox(height: 10),
            Text(
              'Mehr Zeit fürs Tier,',
              textAlign: TextAlign.center,
              style: TextStyle(
                fontSize: 14,
                fontWeight: FontWeight.w400,
                color: Colors.white.withValues(alpha: 0.7),
                letterSpacing: 0.5,
              ),
            ),
            const SizedBox(height: 2),
            Text(
              'weniger Zeit am Schreibtisch.',
              textAlign: TextAlign.center,
              style: TextStyle(
                fontSize: 14,
                fontWeight: FontWeight.w400,
                color: Colors.white.withValues(alpha: 0.7),
                letterSpacing: 0.5,
              ),
            ),
          ]),
        ),
      ),
    );
  }

  // ── Loading dots ──────────────────────────────────────────────────────────

  Widget _buildDots() {
    return AnimatedBuilder(
      animation: _dotsCtrl,
      builder: (_, __) {
        return Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: List.generate(3, (i) {
            final phase = ((_dotsCtrl.value - i * 0.25) % 1.0).clamp(0.0, 1.0);
            final scale = 0.5 + 0.5 * math.sin(phase * math.pi).clamp(0.0, 1.0);
            return Container(
              margin: const EdgeInsets.symmetric(horizontal: 4),
              width: 7 * scale,
              height: 7 * scale,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: _initDone
                    ? AppTheme.success.withValues(alpha: 0.8)
                    : Colors.white.withValues(alpha: 0.35 + 0.45 * scale),
              ),
            );
          }),
        );
      },
    );
  }
}

// ── Paw Icon (pure canvas — no asset needed) ──────────────────────────────────

class _PawIcon extends StatelessWidget {
  final double size;
  final Color color;
  const _PawIcon({required this.size, required this.color});

  @override
  Widget build(BuildContext context) => CustomPaint(
    size: Size(size, size),
    painter: _PawPainter(color),
  );
}

class _PawPainter extends CustomPainter {
  final Color color;
  _PawPainter(this.color);

  @override
  void paint(Canvas canvas, Size size) {
    final p = Paint()..color = color..style = PaintingStyle.fill;
    final w = size.width;
    final h = size.height;

    // Main pad (bottom-centre)
    final padPath = Path();
    padPath.addOval(Rect.fromCenter(
      center: Offset(w * 0.5, h * 0.68),
      width: w * 0.42,
      height: h * 0.32,
    ));
    canvas.drawPath(padPath, p);

    // 4 toe beans
    final toes = [
      Offset(w * 0.20, h * 0.38),
      Offset(w * 0.38, h * 0.28),
      Offset(w * 0.62, h * 0.28),
      Offset(w * 0.80, h * 0.38),
    ];
    for (var t in toes) {
      canvas.drawOval(Rect.fromCenter(center: t, width: w * 0.18, height: h * 0.22), p);
    }
  }

  @override
  bool shouldRepaint(covariant _PawPainter old) => old.color != color;
}

// ── Particle system ───────────────────────────────────────────────────────────

class _Particle {
  final double x;       // 0..1 horizontal position
  final double baseY;   // 0..1 starting vertical position
  final double size;
  final double speed;   // relative speed
  final double phase;   // animation phase offset
  final bool isPaw;
  final double opacity;
  _Particle({
    required this.x, required this.baseY, required this.size,
    required this.speed, required this.phase, required this.isPaw,
    required this.opacity,
  });
}

class _ParticlePainter extends CustomPainter {
  final List<_Particle> particles;
  final double t; // 0..1 loop progress

  _ParticlePainter(this.particles, this.t);

  @override
  void paint(Canvas canvas, Size size) {
    for (final p in particles) {
      final progress = ((t * p.speed + p.phase) % 1.0);
      final y = size.height * (1.0 - progress); // float upward
      final x = size.width * p.x + math.sin(progress * math.pi * 2 + p.phase * 6) * 15;
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
    final s = size;
    // Main pad
    canvas.drawOval(Rect.fromCenter(
      center: Offset(center.dx, center.dy + s * 0.15),
      width: s * 0.55, height: s * 0.42,
    ), paint);
    // Toes
    final offsets = [
      Offset(-s * 0.26, -s * 0.1), Offset(-s * 0.09, -s * 0.28),
      Offset(s * 0.09, -s * 0.28), Offset(s * 0.26, -s * 0.1),
    ];
    for (final o in offsets) {
      canvas.drawOval(Rect.fromCenter(
        center: Offset(center.dx + o.dx, center.dy + o.dy),
        width: s * 0.2, height: s * 0.26,
      ), paint);
    }
  }

  @override
  bool shouldRepaint(covariant _ParticlePainter old) => old.t != t;
}
