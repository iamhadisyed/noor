import 'package:flutter/widgets.dart';
import 'package:kindo_ui/kindo_ui.dart';

/// The web build has no AdMob: nothing is shown.
class AdBanner extends StatelessWidget {
  const AdBanner({super.key, required this.ads});
  final Json ads;

  @override
  Widget build(BuildContext context) => const SizedBox.shrink();
}
