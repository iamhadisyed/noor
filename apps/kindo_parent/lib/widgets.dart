import 'package:flutter/material.dart';
import 'package:kindo_ui/kindo_ui.dart';
import 'package:provider/provider.dart';

/// Loads something from the server and shows it, with a loading spinner, an error with "Try again", and pull to refresh.
class Load<T> extends StatefulWidget {
  const Load({super.key, required this.future, required this.builder, this.keyValue, this.lockedBuilder});

  /// Shown instead of an error when the server says this needs a plan upgrade (402).
  final WidgetBuilder? lockedBuilder;
  final Future<T> Function() future;
  final Widget Function(BuildContext context, T data, Future<void> Function() reload) builder;

  /// Change this to load again (for example the child's id).
  final Object? keyValue;

  @override
  State<Load<T>> createState() => _LoadState<T>();
}

class _LoadState<T> extends State<Load<T>> {
  late Future<T> _f = widget.future();

  @override
  void didUpdateWidget(Load<T> old) {
    super.didUpdateWidget(old);
    if (old.keyValue != widget.keyValue) _f = widget.future();
  }

  Future<void> _reload() async {
    final f = widget.future();
    setState(() => _f = f);
    try {
      await f;
    } catch (_) {
      // the builder shows the error
    }
  }

  @override
  Widget build(BuildContext context) => FutureBuilder<T>(
        future: _f,
        builder: (context, snap) {
          final e = snap.error;
          if (e is ApiException && e.needsUpgrade && widget.lockedBuilder != null) return widget.lockedBuilder!(context);
          if (snap.hasError) return ErrorBox(snap.error!, onRetry: _reload);
          if (!snap.hasData) return const Loading();
          return widget.builder(context, snap.data as T, _reload);
        },
      );
}

/// The child shown on this screen, with a switcher when the family has more than one.
class ChildHeader extends StatelessWidget {
  const ChildHeader({super.key, this.trailing});
  final Widget? trailing;

  @override
  Widget build(BuildContext context) {
    final s = context.watch<KindoSession>();
    final kids = s.children;
    final c = s.currentChild;
    if (c == null) return const SizedBox.shrink();

    return Row(children: [
      CircleAvatar(radius: 22, backgroundColor: KindoColors.lilac, child: Text(c.name.isEmpty ? '?' : c.name[0].toUpperCase(), style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 20))),
      const SizedBox(width: 12),
      Expanded(
        child: kids.length < 2
            ? Text(c.name, style: Theme.of(context).textTheme.titleLarge)
            : DropdownButtonHideUnderline(
                child: DropdownButton<int>(
                  value: c.id,
                  isExpanded: true,
                  borderRadius: BorderRadius.circular(20),
                  style: Theme.of(context).textTheme.titleLarge,
                  items: [for (final k in kids) DropdownMenuItem(value: k.id, child: Text(k.name))],
                  onChanged: (v) => v == null ? null : s.selectChild(v),
                ),
              ),
      ),
      if (c.isSpecial) Pill(context.tr('Extra support', 'اضافی مدد'), color: KindoColors.butter),
      ?trailing,
    ]);
  }
}

/// Page padding used by every tab.
const pagePadding = EdgeInsets.fromLTRB(16, 12, 16, 24);

/// A row inside a [KindoCard] with an icon, a title, a subtitle and an arrow.
class NavRow extends StatelessWidget {
  const NavRow({super.key, required this.icon, required this.title, this.subtitle, this.onTap, this.trailing});
  final IconData icon;
  final String title;
  final String? subtitle;
  final VoidCallback? onTap;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) => ListTile(
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 2),
        leading: Container(width: 42, height: 42, decoration: const BoxDecoration(color: KindoColors.lavenderTint, shape: BoxShape.circle), child: Icon(icon, color: KindoColors.ink2)),
        title: Text(title, style: const TextStyle(fontWeight: FontWeight.w700)),
        subtitle: subtitle == null ? null : Text(subtitle!),
        trailing: trailing ?? (onTap == null ? null : const Icon(Icons.chevron_right_rounded)),
        onTap: onTap,
      );
}

Future<T?> push<T>(BuildContext context, Widget page) => Navigator.of(context).push<T>(MaterialPageRoute(builder: (_) => LangDirection(child: page)));

/// A labelled number stepper-free "stat" tile.
class Stat extends StatelessWidget {
  const Stat(this.value, this.label, {super.key, this.color});
  final String value;
  final String label;
  final Color? color;

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(color: color ?? Colors.white, borderRadius: BorderRadius.circular(24)),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(value, style: Theme.of(context).textTheme.headlineMedium),
          const SizedBox(height: 2),
          Text(label, style: Theme.of(context).textTheme.bodySmall),
        ]),
      );
}
