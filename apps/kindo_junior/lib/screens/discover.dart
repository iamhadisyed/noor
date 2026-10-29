import 'package:flutter/material.dart';
import 'package:kindo_ui/kindo_ui.dart';
import 'package:provider/provider.dart';

import 'games.dart';

Color _hex(Object? v, Color fallback) {
  final h = '$v'.replaceFirst('#', '');
  final n = int.tryParse(h, radix: 16);
  return h.length == 6 && n != null ? Color(0xFF000000 | n) : fallback;
}

String _t(Object? v, String lang) {
  final m = asJson(v);
  final s = '${m[lang] ?? ''}';
  return s.isEmpty ? '${m['en'] ?? ''}' : s;
}

/// Discover: animals and where they live, under the sea, space, matter, and AI. Short facts for the child's age, and a free quiz for each.
class DiscoverHub extends StatefulWidget {
  const DiscoverHub({super.key});

  @override
  State<DiscoverHub> createState() => _DiscoverHubState();
}

class _DiscoverHubState extends State<DiscoverHub> {
  late final Future<List<Json>> _f = context.read<KindoSession>().api.learnTopics();

  @override
  Widget build(BuildContext context) {
    final lang = context.watch<KindoSession>().lang;
    return Scaffold(
      appBar: AppBar(title: Text(context.tr('Discover', 'دریافت کریں'))),
      body: FutureBuilder<List<Json>>(
        future: _f,
        builder: (context, snap) {
          if (snap.hasError) return Center(child: Padding(padding: const EdgeInsets.all(24), child: Text(context.tr('You need the internet to discover new things.', 'نئی چیزیں جاننے کے لیے انٹرنیٹ چاہیے۔'), textAlign: TextAlign.center)));
          if (!snap.hasData) return const Loading();
          return ListView(padding: const EdgeInsets.all(20), children: [
            for (final t in snap.data!)
              Padding(
                padding: const EdgeInsets.only(bottom: 14),
                child: Material(
                  color: _hex(t['color'], KindoColors.lilac),
                  borderRadius: BorderRadius.circular(28),
                  child: InkWell(
                    borderRadius: BorderRadius.circular(28),
                    onTap: () => Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => LangDirection(child: TopicScreen(topic: '${t['topic']}', title: _t(t['title'], lang), emoji: '${t['emoji']}', color: _hex(t['color'], KindoColors.lilac), game: t['game'] as String?)))),
                    child: Padding(
                      padding: const EdgeInsets.all(20),
                      child: Row(children: [
                        Text('${t['emoji']}', style: const TextStyle(fontSize: 54)),
                        const SizedBox(width: 16),
                        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                          Text(_t(t['title'], lang), style: Theme.of(context).textTheme.titleLarge),
                          const SizedBox(height: 4),
                          Text(_t(t['about'], lang)),
                        ])),
                      ]),
                    ),
                  ),
                ),
              ),
          ]);
        },
      ),
    );
  }
}

class TopicScreen extends StatefulWidget {
  const TopicScreen({super.key, required this.topic, required this.title, required this.emoji, required this.color, this.game});
  final String topic;
  final String title;
  final String emoji;
  final Color color;
  final String? game;

  @override
  State<TopicScreen> createState() => _TopicScreenState();
}

class _TopicScreenState extends State<TopicScreen> {
  late final Future<Json> _f;

  @override
  void initState() {
    super.initState();
    final s = context.read<KindoSession>();
    _f = s.api.learnTopic(widget.topic, age: s.currentChild?.ageYears());
  }

  Future<void> _quiz() async {
    final s = context.read<KindoSession>();
    final child = s.currentChild!;
    try {
      final start = await s.api.startGame(child.id, widget.game!, language: s.lang);
      final info = (await s.api.games(child.id)).firstWhere((g) => g.game == widget.game);
      if (!mounted) return;
      await Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => LangDirection(child: GameScreen(start: start, info: info))));
    } on ApiException catch (e) {
      if (mounted) showMessage(context, e.isNetwork ? context.tr('You need the internet to start a quiz.', 'کوئز شروع کرنے کے لیے انٹرنیٹ چاہیے۔') : e.message);
    }
  }

  @override
  Widget build(BuildContext context) {
    final lang = context.watch<KindoSession>().lang;
    return Scaffold(
      appBar: AppBar(title: Text('${widget.emoji}  ${widget.title}')),
      body: FutureBuilder<Json>(
        future: _f,
        builder: (context, snap) {
          if (snap.hasError) return Center(child: Text(context.tr('You need the internet to read this.', 'اسے پڑھنے کے لیے انٹرنیٹ چاہیے۔')));
          if (!snap.hasData) return const Loading();
          final facts = asJsonList(snap.data!['facts']);
          return ListView(padding: const EdgeInsets.all(20), children: [
            if (widget.game != null)
              FilledButton.icon(
                style: FilledButton.styleFrom(minimumSize: const Size.fromHeight(60), backgroundColor: widget.color, foregroundColor: Colors.black87),
                onPressed: _quiz,
                icon: const Icon(Icons.quiz_rounded),
                label: Text(context.tr('Play the quiz', 'کوئز کھیلیں'), style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800)),
              ),
            const SizedBox(height: 14),
            for (final f in facts)
              Padding(
                padding: const EdgeInsets.only(bottom: 12),
                child: Container(
                  padding: const EdgeInsets.all(18),
                  decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(24), border: Border.all(color: KindoColors.line)),
                  child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Text('${f['emoji']}', style: const TextStyle(fontSize: 40)),
                    const SizedBox(width: 14),
                    Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Text(_t(f['title'], lang), style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800)),
                      const SizedBox(height: 4),
                      Text(_t(f['text'], lang)),
                    ])),
                  ]),
                ),
              ),
          ]);
        },
      ),
    );
  }
}
