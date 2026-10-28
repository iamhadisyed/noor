import 'package:flutter/cupertino.dart' show CupertinoPageTransitionsBuilder;
import 'package:flutter/material.dart';

/// The Kindo colours (same tokens as the web portal) and the themes built from them.
class KindoColors {
  const KindoColors._();
  static const lavender = Color(0xFFCABAEF);
  static const lavenderSoft = Color(0xFFD7C6F5);
  static const lavenderTint = Color(0xFFF3F0FA);
  static const lilac = Color(0xFFEEADFE);
  static const orchid = Color(0xFFE697F2);
  static const butter = Color(0xFFFFF0AD);
  static const cream = Color(0xFFFCE9B4);
  static const ink = Color(0xFF120C15);
  static const ink2 = Color(0xFF2A242E);
  static const mist = Color(0xFFEEF2F5);
  static const gray = Color(0xFF6F6A78);
  static const line = Color(0xFFE7E3EF);
  static const sage = Color(0xFF81B29A);
  static const sageDeep = Color(0xFF3D6B55);
  static const warn = Color(0xFFB3423F);
  static const warnTint = Color(0xFFFBE3E1);
}

/// Font that carries Urdu. Add it as a fallback so Urdu text looks right next to Latin text.
const urduFont = 'NotoNaskhArabic';
const urduFontPackage = 'kindo_ui';

class KindoTheme {
  const KindoTheme._();

  /// [sensory] is for children who find bright colours and motion hard: softer colours, no bouncy page changes.
  static ThemeData build({bool sensory = false}) {
    final accent = sensory ? KindoColors.lavender : KindoColors.orchid;
    final scheme = ColorScheme.fromSeed(seedColor: KindoColors.orchid, brightness: Brightness.light).copyWith(
      primary: KindoColors.ink,
      onPrimary: Colors.white,
      secondary: accent,
      onSecondary: KindoColors.ink,
      tertiary: KindoColors.butter,
      surface: Colors.white,
      onSurface: KindoColors.ink,
      error: KindoColors.warn,
      surfaceContainerHighest: KindoColors.lavenderTint,
      outline: KindoColors.line,
    );
    final base = ThemeData(useMaterial3: true, colorScheme: scheme, scaffoldBackgroundColor: KindoColors.mist);
    const fallback = ['packages/$urduFontPackage/$urduFont'];
    TextStyle? s(TextStyle? t) => t?.copyWith(color: KindoColors.ink, fontFamilyFallback: fallback);
    final tt = base.textTheme;

    return base.copyWith(
      textTheme: tt.copyWith(
        displayLarge: s(tt.displayLarge), displayMedium: s(tt.displayMedium), displaySmall: s(tt.displaySmall),
        headlineLarge: s(tt.headlineLarge?.copyWith(fontWeight: FontWeight.w800)), headlineMedium: s(tt.headlineMedium?.copyWith(fontWeight: FontWeight.w800)),
        headlineSmall: s(tt.headlineSmall?.copyWith(fontWeight: FontWeight.w700)), titleLarge: s(tt.titleLarge?.copyWith(fontWeight: FontWeight.w700)),
        titleMedium: s(tt.titleMedium?.copyWith(fontWeight: FontWeight.w600)), titleSmall: s(tt.titleSmall), bodyLarge: s(tt.bodyLarge),
        bodyMedium: s(tt.bodyMedium), bodySmall: s(tt.bodySmall?.copyWith(color: KindoColors.gray)), labelLarge: s(tt.labelLarge?.copyWith(fontWeight: FontWeight.w700)),
        labelMedium: s(tt.labelMedium), labelSmall: s(tt.labelSmall),
      ),
      appBarTheme: const AppBarTheme(backgroundColor: KindoColors.mist, foregroundColor: KindoColors.ink, elevation: 0, scrolledUnderElevation: 0, centerTitle: false),
      cardTheme: CardThemeData(color: Colors.white, elevation: 0, margin: EdgeInsets.zero, shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(28))),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: Colors.white,
        contentPadding: const EdgeInsets.symmetric(horizontal: 20, vertical: 16),
        border: OutlineInputBorder(borderRadius: BorderRadius.circular(999), borderSide: const BorderSide(color: KindoColors.line, width: 2)),
        enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(999), borderSide: const BorderSide(color: KindoColors.line, width: 2)),
        focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(999), borderSide: const BorderSide(color: KindoColors.ink, width: 2)),
        errorBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(999), borderSide: const BorderSide(color: KindoColors.warn, width: 2)),
      ),
      filledButtonTheme: FilledButtonThemeData(style: FilledButton.styleFrom(backgroundColor: KindoColors.ink, foregroundColor: Colors.white, minimumSize: const Size(64, 52), shape: const StadiumBorder(), textStyle: const TextStyle(fontWeight: FontWeight.w700, fontSize: 16))),
      outlinedButtonTheme: OutlinedButtonThemeData(style: OutlinedButton.styleFrom(foregroundColor: KindoColors.ink, minimumSize: const Size(64, 52), shape: const StadiumBorder(), side: const BorderSide(color: KindoColors.line, width: 2), textStyle: const TextStyle(fontWeight: FontWeight.w700, fontSize: 16))),
      textButtonTheme: TextButtonThemeData(style: TextButton.styleFrom(foregroundColor: KindoColors.ink, minimumSize: const Size(48, 48), textStyle: const TextStyle(fontWeight: FontWeight.w700))),
      chipTheme: ChipThemeData(backgroundColor: KindoColors.lavenderTint, side: BorderSide.none, shape: const StadiumBorder(), labelStyle: const TextStyle(fontWeight: FontWeight.w600, color: KindoColors.ink)),
      navigationBarTheme: NavigationBarThemeData(
        backgroundColor: KindoColors.ink,
        indicatorColor: accent,
        height: 68,
        labelTextStyle: WidgetStateProperty.resolveWith((st) => TextStyle(fontSize: 11, fontWeight: FontWeight.w600, color: st.contains(WidgetState.selected) ? KindoColors.ink : Colors.white)),
        iconTheme: WidgetStateProperty.resolveWith((st) => IconThemeData(color: st.contains(WidgetState.selected) ? KindoColors.ink : Colors.white)),
      ),
      snackBarTheme: SnackBarThemeData(behavior: SnackBarBehavior.floating, backgroundColor: KindoColors.ink, contentTextStyle: const TextStyle(color: Colors.white, fontWeight: FontWeight.w600), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20))),
      dialogTheme: DialogThemeData(backgroundColor: Colors.white, shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(32))),
      pageTransitionsTheme: sensory
          ? const PageTransitionsTheme(builders: {TargetPlatform.android: FadeForwardsPageTransitionsBuilder(), TargetPlatform.iOS: CupertinoPageTransitionsBuilder()})
          : null,
      dividerTheme: const DividerThemeData(color: KindoColors.line, space: 1),
    );
  }
}
