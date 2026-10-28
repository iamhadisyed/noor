import 'package:flutter/material.dart';
import 'package:kindo_core/kindo_core.dart';
import 'package:provider/provider.dart';

import '../session.dart';
import '../theme.dart';
import 'common.dart';

/// Sign in with an emailed code. If the server says the terms must be accepted (a new account), the terms are shown and the
/// same code is sent again with the acceptance.
class SignInScreen extends StatefulWidget {
  const SignInScreen({super.key, required this.appName, this.tagline, this.onSignedIn});
  final String appName;
  final String? tagline;
  final VoidCallback? onSignedIn;

  @override
  State<SignInScreen> createState() => _SignInScreenState();
}

class _SignInScreenState extends State<SignInScreen> {
  final _email = TextEditingController();
  final _code = TextEditingController();
  bool _sent = false, _busy = false;

  @override
  void dispose() {
    _email.dispose();
    _code.dispose();
    super.dispose();
  }

  Future<void> _send() async {
    final s = context.read<KindoSession>();
    setState(() => _busy = true);
    final ok = await guarded(context, () async {
      await s.requestCode(_email.text);
      return true;
    });
    if (!mounted) return;
    setState(() {
      _busy = false;
      if (ok == true) _sent = true;
    });
  }

  Future<void> _verify({bool accept = false}) async {
    final s = context.read<KindoSession>();
    setState(() => _busy = true);
    try {
      await s.verify(_email.text, _code.text, acceptTerms: accept);
      widget.onSignedIn?.call();
    } on ApiException catch (e) {
      if (e.termsRequired && !accept && mounted) {
        final agreed = await _terms();
        if (agreed && mounted) return _verify(accept: true);
      } else if (mounted) {
        showError(context, e);
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<bool> _terms() async {
    final s = context.read<KindoSession>();
    Json terms = {};
    try {
      terms = await s.api.terms();
    } on ApiException {
      // show the short version below
    }
    if (!mounted) return false;
    final sections = (terms['sections'] as List?) ?? const [];
    final r = await showDialog<bool>(
      context: context,
      builder: (c) => AlertDialog(
        title: Text('${terms['title'] ?? 'Terms and privacy'}'),
        content: SizedBox(
          width: 420,
          child: ListView(shrinkWrap: true, children: [
            for (final sec in sections) ...[
              Text('${asJson(sec)['heading'] ?? ''}', style: const TextStyle(fontWeight: FontWeight.w800)),
              const SizedBox(height: 4),
              Text('${asJson(sec)['body'] ?? ''}'),
              const SizedBox(height: 12),
            ],
            if (sections.isEmpty) const Text('Please read our terms and privacy notice on our website before you continue.'),
          ]),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(c, false), child: const Text('Not now')),
          FilledButton(onPressed: () => Navigator.pop(c, true), child: const Text('I agree')),
        ],
      ),
    );
    return r ?? false;
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 420),
              child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                Text(widget.appName, style: Theme.of(context).textTheme.headlineLarge, textAlign: TextAlign.center),
                if (widget.tagline != null) Padding(padding: const EdgeInsets.only(top: 8), child: Text(widget.tagline!, textAlign: TextAlign.center, style: Theme.of(context).textTheme.bodyMedium?.copyWith(color: KindoColors.gray))),
                const SizedBox(height: 28),
                KindoCard(
                  child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                    Text(_sent ? 'Enter the code' : 'Sign in', style: Theme.of(context).textTheme.titleLarge),
                    const SizedBox(height: 14),
                    TextField(controller: _email, enabled: !_sent, keyboardType: TextInputType.emailAddress, autofillHints: const [AutofillHints.email], decoration: const InputDecoration(labelText: 'Email')),
                    if (_sent) ...[
                      const SizedBox(height: 12),
                      TextField(controller: _code, keyboardType: TextInputType.number, decoration: const InputDecoration(labelText: 'Code from your email')),
                    ],
                    const SizedBox(height: 18),
                    FilledButton(onPressed: _busy ? null : (_sent ? _verify : _send), child: Text(_busy ? 'Please wait...' : (_sent ? 'Sign in' : 'Email me a code'))),
                    if (_sent) TextButton(onPressed: _busy ? null : () => setState(() => _sent = false), child: const Text('Use a different email')),
                  ]),
                ),
              ]),
            ),
          ),
        ),
      ),
    );
  }
}
