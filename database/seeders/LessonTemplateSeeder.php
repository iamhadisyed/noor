<?php

namespace Database\Seeders;

use App\Models\Lesson;
use Illuminate\Database\Seeder;

/** Starter lessons parents can copy. They carry no pictures or sounds: the parent adds their own, and the publish rule says what is missing. */
class LessonTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            ['Urdu letters: Alif to Se', 'Trace the first six letters, one stroke at a time.', 'urdu', 'standard', [], [
                ['trace_letter', 'Alif', 'ا'], ['trace_letter', 'Be', 'ب'], ['trace_letter', 'Pe', 'پ'], ['trace_letter', 'Te', 'ت'], ['trace_letter', 'Tte', 'ٹ'], ['trace_letter', 'Se', 'ث'],
            ]],
            ['First English words', 'Three-letter words with a picture clue and a spoken word.', 'english', 'standard', [], [
                ['spell_word', 'Cat', 'cat'], ['spell_word', 'Dog', 'dog'], ['spell_word', 'Sun', 'sun'], ['spell_word', 'Hat', 'hat'], ['spell_word', 'Pen', 'pen'], ['spell_word', 'Bus', 'bus'],
            ]],
            ['Family faces', 'Match each photo to the person, with their own voice.', 'both', 'standard', [], [
                ['match_face', 'Mama', null], ['match_face', 'Papa', null], ['match_face', 'Dadi', null],
            ]],
            ['Gentle Alif to Se', 'Slow pace, large magnet, two choices, no timers.', 'urdu', 'special', ['magnet_px' => 36, 'pace' => 'slow', 'max_choices' => 2, 'no_timers' => true, 'first_then' => true, 'praise' => 'quiet'], [
                ['trace_letter', 'Alif', 'ا'], ['trace_letter', 'Be', 'ب'], ['trace_letter', 'Pe', 'پ'],
            ]],
            ['My day: First, Then', 'Picture routine cards: wash, eat, sleep. Add a photo and a calm voice for each.', 'both', 'special', ['no_timers' => true, 'first_then' => true, 'praise' => 'quiet'], [
                ['routine_step', 'Wash hands', null], ['routine_step', 'Eat', null], ['routine_step', 'Sleep', null],
            ]],
            ['Pictures and words', 'Choose the right picture from two. Add the picture and say the word.', 'english', 'special', ['max_choices' => 2, 'pace' => 'slow', 'no_timers' => true], [
                ['pick_picture', 'Which is the cat?', null], ['pick_picture', 'Which is the ball?', null],
            ]],
        ];

        foreach ($templates as [$title, $desc, $lang, $track, $settings, $tasks]) {
            $lesson = Lesson::updateOrCreate(
                ['is_template' => true, 'user_id' => null, 'title' => $title],
                ['description' => $desc, 'language' => $lang, 'track' => $track, 'settings' => $settings ?: null, 'status' => 'published']
            );
            if ($lesson->tasks()->count() === 0) {
                foreach ($tasks as $i => [$type, $name, $target]) {
                    $options = null;   // the parent adds the choices
                    $lesson->tasks()->create(['position' => $i + 1, 'type' => $type, 'title' => $name, 'target' => $target, 'options' => $options]);
                }
            }
        }
    }
}
