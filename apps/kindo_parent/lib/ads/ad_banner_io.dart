import 'dart:io' show Platform;

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:google_mobile_ads/google_mobile_ads.dart';
import 'package:kindo_ui/kindo_ui.dart';

/// Google's own test banners. Used only in debug builds when the server has not been given real unit ids yet.
const _testBanner = {'android': 'ca-app-pub-3940256099942544/6300978111', 'ios': 'ca-app-pub-3940256099942544/2934735716'};

Future<void>? _started;

/// Start the AdMob SDK once, with the strictest settings for children: ads are marked child-directed and for users under the age of
/// consent, and only G-rated ads are allowed. (The server only sends `ads` to free parents; see config/ads.php.)
Future<void> startAds() => _started ??= MobileAds.instance
    .updateRequestConfiguration(RequestConfiguration(
      tagForChildDirectedTreatment: TagForChildDirectedTreatment.yes,
      tagForUnderAgeOfConsent: TagForUnderAgeOfConsent.yes,
      maxAdContentRating: MaxAdContentRating.g,
    ))
    .then((_) => MobileAds.instance.initialize())
    .then((_) {});

/// One banner for the parent app (never the child's app). Takes the `ads` block of `GET /me`.
class AdBanner extends StatefulWidget {
  const AdBanner({super.key, required this.ads});
  final Json ads;

  @override
  State<AdBanner> createState() => _AdBannerState();
}

class _AdBannerState extends State<AdBanner> {
  BannerAd? _ad;
  bool _loaded = false;

  /// The unit id for this phone, or null when none may be used.
  String? get _unit {
    final platform = Platform.isIOS ? 'ios' : 'android';
    final configured = '${asJson(widget.ads['units'])[Platform.isIOS ? 'banner_ios' : 'banner_android'] ?? ''}'.trim();
    if (configured.isNotEmpty) return configured;
    return kDebugMode ? _testBanner[platform] : null;   // a release build never falls back to test ads
  }

  @override
  void initState() {
    super.initState();
    if (!(Platform.isAndroid || Platform.isIOS) || widget.ads['child_directed'] != true) return;   // the server must say the ad is child-directed
    final unit = _unit;
    if (unit == null) return;
    startAds().then((_) {
      if (!mounted) return;
      final ad = BannerAd(
        adUnitId: unit,
        size: AdSize.banner,
        request: const AdRequest(nonPersonalizedAds: true, extras: {'npa': '1'}),
        listener: BannerAdListener(
          onAdLoaded: (_) => mounted ? setState(() => _loaded = true) : null,
          onAdFailedToLoad: (ad, _) {
            ad.dispose();
            if (mounted) setState(() => _ad = null);
          },
        ),
      )..load();
      setState(() => _ad = ad);
    });
  }

  @override
  void dispose() {
    _ad?.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final ad = _ad;
    if (ad == null || !_loaded) return const SizedBox.shrink();
    return Semantics(
      label: context.tr('Advertisement', 'اشتہار'),
      child: Column(mainAxisSize: MainAxisSize.min, children: [
        Text(context.tr('Ad (removed with Pro)', 'اشتہار (پرو میں نہیں ہوتا)'), style: Theme.of(context).textTheme.bodySmall),
        const SizedBox(height: 4),
        SizedBox(width: ad.size.width.toDouble(), height: ad.size.height.toDouble(), child: AdWidget(ad: ad)),
      ]),
    );
  }
}
