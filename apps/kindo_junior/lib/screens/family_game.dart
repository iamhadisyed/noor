import 'dart:math';

import 'package:flutter/material.dart';
import 'package:kindo_ui/kindo_ui.dart';
import 'package:provider/provider.dart';

import '../widgets/celebrate.dart';

/// Hear someone's voice and tap their photo. Made from the family cards the parent added.
class FamilyGame extends StatefulWidget {
  const FamilyGame({super.key});

  @override
  State<FamilyGame> createState() => _FamilyGameState();
}

class _FamilyGameState extends State<FamilyGame> {
  final _r = Random();
  late List<FamilyMember> _order;
  int _i = 0;
  late List<FamilyMember> _options;
  final Set<int> _wrong = {};
  bool _right = false;

  KindoSession get _s => context.read<KindoSession>();

  @override
  void initState() {
    super.initState();
    final all = _s.sync.familyFor(_s.currentChild!.id).where((m) => m.photoUrl != null && m.audioUrl != null).toList()..shuffle(_r);
    _order = all;
    _setup();
    WidgetsBinding.instance.addPostFrameCallback((_) => _hear());
  }

  FamilyMember get _target => _order[_i];

  void _setup() {
    final all = _s.sync.familyFor(_s.currentChild!.id).where((m) => m.photoUrl != null).toList();
    final max = _s.currentChild!.support.maxChoices;
    final others = all.where((m) => m.id != _target.id).toList()..shuffle(_r);
    _options = [_target, ...others.take(max - 1)]..shuffle(_r);
    _wrong.clear();
    _right = false;
  }

  void _hear() {
    if (mounted && _i < _order.length) AudioHub.instance.play(_s, _target.audioUrl, version: _target.audioV);
  }

  @override
  Widget build(BuildContext context) {
    final support = _s.currentChild!.support;
    if (_order.length < 2) return Scaffold(appBar: AppBar(), body: EmptyState(title: context.tr('Ask a grown-up to add family cards', 'کسی بڑے سے خاندانی کارڈ شامل کروائیں')));
    if (_i >= _order.length) {
      return Scaffold(appBar: AppBar(), body: Center(child: Column(mainAxisSize: MainAxisSize.min, children: [Celebrate(support: support), const SizedBox(height: 24), FilledButton(onPressed: () => Navigator.pop(context), child: Text(context.tr('Done', 'ہو گیا')))])));
    }

    return Scaffold(
      appBar: AppBar(title: Text(context.tr('My family', 'میرا خاندان'))),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(20),
          child: Column(children: [
            Text(context.tr('Who is talking?', 'کون بول رہا ہے؟'), style: Theme.of(context).textTheme.headlineMedium),
            const SizedBox(height: 10),
            IconButton.filledTonal(iconSize: 44, onPressed: _hear, icon: const Icon(Icons.volume_up_rounded), tooltip: context.tr('Hear it', 'سنیں')),
            const SizedBox(height: 16),
            Expanded(
              child: GridView.count(
                crossAxisCount: 2,
                mainAxisSpacing: 14,
                crossAxisSpacing: 14,
                children: [
                  for (final m in _options)
                    Opacity(
                      opacity: _wrong.contains(m.id) ? .3 : 1,
                      child: InkWell(
                        borderRadius: BorderRadius.circular(28),
                        onTap: _right || _wrong.contains(m.id)
                            ? null
                            : () {
                                if (m.id == _target.id) {
                                  setState(() => _right = true);
                                  Future<void>.delayed(Duration(milliseconds: support.slow ? 1400 : 900), () {
                                    if (!mounted) return;
                                    setState(() => _i++);
                                    if (_i < _order.length) {
                                      _setup();
                                      _hear();
                                    }
                                  });
                                } else {
                                  setState(() => _wrong.add(m.id));
                                }
                              },
                        child: Container(
                          padding: const EdgeInsets.all(8),
                          decoration: BoxDecoration(color: _right && m.id == _target.id ? KindoColors.sage : Colors.white, borderRadius: BorderRadius.circular(28)),
                          child: Column(children: [
                            Expanded(child: NetImage(m.photoUrl, version: m.photoV, radius: 22)),
                            Padding(padding: const EdgeInsets.only(top: 6), child: Text(_right && m.id == _target.id ? m.relationTitle : '', style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w800))),
                          ]),
                        ),
                      ),
                    ),
                ],
              ),
            ),
          ]),
        ),
      ),
    );
  }
}
