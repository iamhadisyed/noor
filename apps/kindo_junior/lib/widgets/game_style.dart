import 'package:flutter/material.dart';

/// How a game looks on the games shelf: a big picture and its own colours, so the shelf looks like a toy box and not a list.
class GameStyle {
  const GameStyle(this.emoji, this.from, this.to);
  final String emoji;
  final Color from;
  final Color to;

  static const _fallback = GameStyle('🎲', Color(0xFFCABAEF), Color(0xFFE697F2));

  static const _byCategory = {
    'math': GameStyle('🔢', Color(0xFF8EC5FF), Color(0xFF5FA8E8)),
    'language': GameStyle('🔤', Color(0xFFFFC98B), Color(0xFFF0A35E)),
    'brain': GameStyle('🧩', Color(0xFFB9A2F5), Color(0xFF8A6FE0)),
    'concepts': GameStyle('🎨', Color(0xFF9BE3B5), Color(0xFF5CBF86)),
    'listening': GameStyle('🎧', Color(0xFFF9A8D4), Color(0xFFE879B9)),
    'social': GameStyle('😊', Color(0xFFFFE08A), Color(0xFFF2C94C)),
    'science': GameStyle('🔭', Color(0xFFB7D8FF), Color(0xFF6C8EEA)),
    'daily_living': GameStyle('🪥', Color(0xFFA5E6E0), Color(0xFF5CC8BE)),
  };

  /// A picture for each game, so every shelf tile is its own thing.
  static const _emoji = {
    'count': '🍎', 'add_sub': '➕', 'number_order': '🔟', 'animal_sound': '🐮', 'sound_guess': '🔊', 'memory_match': '🃏', 'odd_one_out': '🕵️',
    'pattern': '🔷', 'first_letter': '🅰️', 'spell_picture': '✏️', 'alphabet_gap': '🔡', 'opposites': '↕️', 'calendar': '📅', 'colors': '🌈',
    'which_group': '🧺', 'match_same': '👯', 'more_less': '⚖️', 'feelings': '😊', 'routine_order': '🪥', 'number_tap': '👆', 'size_game': '🐘',
    'word_to_picture': '📖', 'last_letter': '🔚', 'bigger_number': '🔢', 'number_bonds': '🧮', 'word_blank': '🕳️', 'read_clock': '🕒',
    'scramble': '🧩', 'hangman': '🕵️', 'times_table': '✖️',
    'science_animals': '🦁', 'science_ocean': '🐠', 'science_space': '🚀', 'science_chemistry': '🧪', 'science_ai': '🤖',
    'table_row': '📊', 'division_facts': '➗', 'case_match': '🔠', 'number_word': '🔡',
  };

  static GameStyle of(String game, String category) {
    final base = _byCategory[category] ?? _fallback;
    return GameStyle(_emoji[game] ?? base.emoji, base.from, base.to);
  }
}
