import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../core/theme.dart';
import '../services/portal_auth_service.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});
  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _formKey  = GlobalKey<FormState>();
  final _emailCtrl = TextEditingController();
  final _pwCtrl    = TextEditingController();
  bool _loading    = false;
  bool _obscure    = true;
  String? _error;

  @override
  void dispose() { _emailCtrl.dispose(); _pwCtrl.dispose(); super.dispose(); }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() { _loading = true; _error = null; });
    final auth   = context.read<PortalAuthService>();
    final result = await auth.login(_emailCtrl.text, _pwCtrl.text);
    if (!mounted) return;
    if (result.success) return; // router redirects
    setState(() { _loading = false; _error = result.message; });
  }

  @override
  Widget build(BuildContext context) {
    final cs = Theme.of(context).colorScheme;
    return Scaffold(
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            const SizedBox(height: 32),
            // Logo
            Center(child: Container(
              width: 80, height: 80,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                gradient: const LinearGradient(begin: Alignment.topLeft, end: Alignment.bottomRight,
                  colors: [AppTheme.primary, AppTheme.secondary, Color(0xFF7C3AED)]),
                boxShadow: [BoxShadow(color: AppTheme.secondary.withValues(alpha: 0.4), blurRadius: 24, offset: const Offset(0, 8))],
              ),
              child: const Center(child: _PawIcon(size: 42, color: Colors.white)),
            )),
            const SizedBox(height: 20),
            Center(child: Text('TheraPano', style: TextStyle(
              fontSize: 32, fontWeight: FontWeight.w900, letterSpacing: -0.5,
              foreground: Paint()..shader = const LinearGradient(
                colors: [AppTheme.primary, AppTheme.secondary],
              ).createShader(const Rect.fromLTWH(0, 0, 200, 40)),
            ))),
            const SizedBox(height: 4),
            Center(child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 3),
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(20),
                color: AppTheme.secondary.withValues(alpha: 0.12),
                border: Border.all(color: AppTheme.secondary.withValues(alpha: 0.3)),
              ),
              child: const Text('Besitzerportal', style: TextStyle(
                color: AppTheme.secondary, fontSize: 12, fontWeight: FontWeight.w600, letterSpacing: 1.0)),
            )),
            const SizedBox(height: 40),
            Text('Willkommen zurück', style: Theme.of(context).textTheme.headlineMedium),
            const SizedBox(height: 6),
            Text('Melden Sie sich mit Ihren Portal-Zugangsdaten an.',
              style: TextStyle(color: cs.onSurfaceVariant)),
            const SizedBox(height: 32),
            Form(key: _formKey, child: Column(children: [
              TextFormField(
                controller: _emailCtrl,
                keyboardType: TextInputType.emailAddress,
                textInputAction: TextInputAction.next,
                decoration: const InputDecoration(labelText: 'E-Mail', prefixIcon: Icon(Icons.mail_outline_rounded)),
                validator: (v) => v == null || !v.contains('@') ? 'Bitte gültige E-Mail eingeben' : null,
              ),
              const SizedBox(height: 16),
              TextFormField(
                controller: _pwCtrl,
                obscureText: _obscure,
                textInputAction: TextInputAction.done,
                onFieldSubmitted: (_) => _submit(),
                decoration: InputDecoration(
                  labelText: 'Passwort',
                  prefixIcon: const Icon(Icons.lock_outline_rounded),
                  suffixIcon: IconButton(
                    icon: Icon(_obscure ? Icons.visibility_off_outlined : Icons.visibility_outlined),
                    onPressed: () => setState(() => _obscure = !_obscure),
                  ),
                ),
                validator: (v) => v == null || v.isEmpty ? 'Bitte Passwort eingeben' : null,
              ),
              if (_error != null) ...[
                const SizedBox(height: 16),
                Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: AppTheme.danger.withValues(alpha: 0.1),
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: AppTheme.danger.withValues(alpha: 0.3)),
                  ),
                  child: Row(children: [
                    const Icon(Icons.error_outline_rounded, color: AppTheme.danger, size: 18),
                    const SizedBox(width: 8),
                    Expanded(child: Text(_error!, style: const TextStyle(color: AppTheme.danger))),
                  ]),
                ),
              ],
              const SizedBox(height: 24),
              FilledButton(
                onPressed: _loading ? null : _submit,
                child: _loading
                    ? const SizedBox(width: 22, height: 22, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2.5))
                    : const Text('Anmelden'),
              ),
            ])),
            const SizedBox(height: 40),
            Center(child: Text('Zugangsdaten erhalten Sie von Ihrer Tierarztpraxis.',
              textAlign: TextAlign.center,
              style: TextStyle(color: cs.onSurfaceVariant, fontSize: 13))),
          ]),
        ),
      ),
    );
  }
}

class _PawIcon extends StatelessWidget {
  final double size;
  final Color color;
  const _PawIcon({required this.size, required this.color});
  @override
  Widget build(BuildContext context) => CustomPaint(size: Size(size, size), painter: _PawPainter(color));
}

class _PawPainter extends CustomPainter {
  final Color color;
  _PawPainter(this.color);
  @override
  void paint(Canvas canvas, Size size) {
    final p = Paint()..color = color..style = PaintingStyle.fill;
    final w = size.width; final h = size.height;
    canvas.drawPath(Path()..addOval(Rect.fromCenter(center: Offset(w * 0.5, h * 0.68), width: w * 0.42, height: h * 0.32)), p);
    for (final t in [Offset(w * 0.20, h * 0.38), Offset(w * 0.38, h * 0.28), Offset(w * 0.62, h * 0.28), Offset(w * 0.80, h * 0.38)]) {
      canvas.drawOval(Rect.fromCenter(center: t, width: w * 0.18, height: h * 0.22), p);
    }
  }
  @override
  bool shouldRepaint(covariant _PawPainter old) => old.color != color;
}
