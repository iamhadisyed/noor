import 'package:flutter/material.dart';
import 'package:kindo_core/kindo_core.dart';
import 'package:provider/provider.dart';

import '../session.dart';
import '../theme.dart';

/// `context.tr('Hello', 'سلام')`: the text for the app's current language.
extension KindoText on BuildContext {
  ///
  /// Safe to call inside a button handler as well as in `build`: the app's locale follows the session language, so asking for the
  /// locale is what makes the widget rebuild when the language changes (`watch` would throw outside `build`).
  String tr(String en, String ur) {
    Localizations.maybeLocaleOf(this);
    return read<KindoSession>().lang == 'ur' && ur.isNotEmpty ? ur : en;
  }

  /// The language code (`en` or `ur`) without listening for changes.
  String get lang => read<KindoSession>().lang;
}

/// Wrap a screen so Urdu reads right to left.
class LangDirection extends StatelessWidget {
  const LangDirection({super.key, required this.child});
  final Widget child;

  @override
  Widget build(BuildContext context) => Directionality(textDirection: context.watch<KindoSession>().lang == 'ur' ? TextDirection.rtl : TextDirection.ltr, child: child);
}

/// Text that may be Urdu: right-to-left when it contains Arabic script.
TextDirection directionOf(String text) => RegExp('[\\u0600-\\u06FF]').hasMatch(text) ? TextDirection.rtl : TextDirection.ltr;

/// A white rounded card, the basic container of both apps.
class KindoCard extends StatelessWidget {
  const KindoCard({super.key, required this.child, this.color, this.padding = const EdgeInsets.all(20), this.onTap, this.radius = 28});
  final Widget child;
  final Color? color;
  final EdgeInsetsGeometry padding;
  final VoidCallback? onTap;
  final double radius;

  @override
  Widget build(BuildContext context) {
    final box = Material(
      color: color ?? Colors.white,
      borderRadius: BorderRadius.circular(radius),
      child: InkWell(borderRadius: BorderRadius.circular(radius), onTap: onTap, child: Padding(padding: padding, child: child)),
    );
    return box;
  }
}

class SectionTitle extends StatelessWidget {
  const SectionTitle(this.text, {super.key, this.trailing});
  final String text;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.fromLTRB(4, 20, 4, 10),
        child: Row(children: [Expanded(child: Text(text, style: Theme.of(context).textTheme.titleLarge)), ?trailing]),
      );
}

/// A small coloured label ("Draft", "Pro", "Extra support").
class Pill extends StatelessWidget {
  const Pill(this.text, {super.key, this.color, this.textColor});
  final String text;
  final Color? color;
  final Color? textColor;

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 5),
        decoration: BoxDecoration(color: color ?? KindoColors.lavenderTint, borderRadius: BorderRadius.circular(999)),
        child: Text(text, style: TextStyle(fontSize: 12.5, fontWeight: FontWeight.w700, color: textColor ?? KindoColors.ink)),
      );
}

class EmptyState extends StatelessWidget {
  const EmptyState({super.key, required this.title, this.message, this.icon = Icons.sentiment_satisfied_alt_rounded, this.action});
  final String title;
  final String? message;
  final IconData icon;
  final Widget? action;

  @override
  Widget build(BuildContext context) => Center(
        child: Padding(
          padding: const EdgeInsets.all(32),
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            Container(width: 84, height: 84, decoration: const BoxDecoration(color: KindoColors.lavenderTint, shape: BoxShape.circle), child: Icon(icon, size: 40, color: KindoColors.ink2)),
            const SizedBox(height: 18),
            Text(title, style: Theme.of(context).textTheme.titleLarge, textAlign: TextAlign.center),
            if (message != null) ...[const SizedBox(height: 8), Text(message!, textAlign: TextAlign.center, style: Theme.of(context).textTheme.bodyMedium?.copyWith(color: KindoColors.gray))],
            if (action != null) ...[const SizedBox(height: 20), action!],
          ]),
        ),
      );
}

class Loading extends StatelessWidget {
  const Loading({super.key});

  @override
  Widget build(BuildContext context) => const Center(child: Padding(padding: EdgeInsets.all(32), child: CircularProgressIndicator(color: KindoColors.ink)));
}

/// What went wrong, in words a parent can read, with a way to try again.
class ErrorBox extends StatelessWidget {
  const ErrorBox(this.error, {super.key, this.onRetry});
  final Object error;
  final VoidCallback? onRetry;

  @override
  Widget build(BuildContext context) => EmptyState(
        title: error is ApiException && (error as ApiException).isNetwork ? 'No connection' : 'Something went wrong',
        message: messageOf(error),
        icon: error is ApiException && (error as ApiException).isNetwork ? Icons.wifi_off_rounded : Icons.error_outline_rounded,
        action: onRetry == null ? null : FilledButton(onPressed: onRetry, child: const Text('Try again')),
      );
}

String messageOf(Object e) => e is ApiException ? e.message : 'Something went wrong. Please try again.';

/// Show an error as a snack bar. A plan limit offers the plan screen instead of a bare message.
void showError(BuildContext context, Object e, {VoidCallback? onUpgrade}) {
  final m = ScaffoldMessenger.of(context);
  m.hideCurrentSnackBar();
  m.showSnackBar(SnackBar(
    content: Text(messageOf(e)),
    action: e is ApiException && e.needsUpgrade && onUpgrade != null ? SnackBarAction(label: 'See plans', textColor: KindoColors.butter, onPressed: onUpgrade) : null,
  ));
}

void showMessage(BuildContext context, String text) {
  final m = ScaffoldMessenger.of(context);
  m.hideCurrentSnackBar();
  m.showSnackBar(SnackBar(content: Text(text)));
}

Future<bool> confirm(BuildContext context, {required String title, String? body, String yes = 'Yes', String no = 'Cancel', bool danger = false}) async {
  final r = await showDialog<bool>(
    context: context,
    builder: (c) => AlertDialog(
      title: Text(title),
      content: body == null ? null : Text(body),
      actions: [
        TextButton(onPressed: () => Navigator.pop(c, false), child: Text(no)),
        FilledButton(style: danger ? FilledButton.styleFrom(backgroundColor: KindoColors.warn) : null, onPressed: () => Navigator.pop(c, true), child: Text(yes)),
      ],
    ),
  );
  return r ?? false;
}

/// Run [op] while showing a busy state on the caller; shows the error if it fails. Returns null on failure.
Future<T?> guarded<T>(BuildContext context, Future<T> Function() op, {VoidCallback? onUpgrade}) async {
  try {
    return await op();
  } on ApiException catch (e) {
    if (context.mounted) showError(context, e, onUpgrade: onUpgrade);
  }
  return null;
}
