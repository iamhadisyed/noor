<?php

namespace App\Support;

use App\Models\AacCard;
use App\Models\CalmSession;
use App\Models\Child;
use App\Models\ChildNeedsLog;
use App\Models\FamilyMember;
use App\Models\GameSession;
use App\Models\Lesson;
use App\Models\LessonTask;
use App\Models\PracticeSession;
use App\Models\RewardLedger;
use App\Models\ScreenTimeLog;
use App\Models\SessionLetter;
use App\Models\User;
use App\Services\MediaStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;

/**
 * Fills a demo account with a believable family: children of different ages, published lessons with pictures and voices, picture cards,
 * family cards, a week of practice, games, calm time, requests for help and screen time. Used by DemoSeeder and `kindo:demo-reset`.
 * Pictures are the placeholder pictures in public/aac; voices are a short generated tone (no real recordings are used).
 */
class DemoContent
{
    public function __construct(private MediaStore $media) {}

    /** @param list<array{name: string, born: string, neurotype: string, track: string}> $kids */
    public function fill(User $user, array $kids): void
    {
        foreach ($kids as $i => $k) {
            $child = Child::create(['user_id' => $user->id, 'name' => $k['name'], 'date_of_birth' => $k['born'], 'neurotype' => $k['neurotype'], 'track' => $k['track'], 'sensory_mode' => $k['track'] === 'special',
                'screen_time' => $i === 0 ? ['enabled' => true, 'daily_minutes' => 120, 'quiet_start' => null, 'quiet_end' => null] : null]);   // no quiet hours here: a demo opened at night must not start in sleepy time
            $this->lessons($user, $child);
            $this->cards($child);
            $this->history($child, $i);
        }
    }

    private function upload(string $name): UploadedFile
    {
        $src = public_path("aac/{$name}.png");
        $tmp = tempnam(sys_get_temp_dir(), 'demo') . '.png';
        File::copy($src, $tmp);

        return new UploadedFile($tmp, "{$name}.png", 'image/png', null, true);
    }

    private function voice(float $hz = 330): UploadedFile
    {
        $rate = 8000;
        $n = (int) ($rate * 0.5);
        $pcm = '';
        for ($i = 0; $i < $n; $i++) {
            $env = min(1, min($i / 400, ($n - $i) / 400));
            $pcm .= pack('v', (int) (sin(2 * M_PI * $hz * $i / $rate) * 9000 * $env) & 0xFFFF);
        }
        $wav = 'RIFF' . pack('V', 36 + strlen($pcm)) . 'WAVEfmt ' . pack('VvvVVvv', 16, 1, 1, $rate, $rate * 2, 2, 16) . 'data' . pack('V', strlen($pcm)) . $pcm;
        $tmp = tempnam(sys_get_temp_dir(), 'demo') . '.wav';
        file_put_contents($tmp, $wav);

        return new UploadedFile($tmp, 'voice.wav', 'audio/wav', null, true);
    }

    private function task(Lesson $lesson, int $pos, array $data, ?string $image = null, bool $sound = false): LessonTask
    {
        $t = $lesson->tasks()->create($data + ['position' => $pos]);
        if ($image) {
            $this->media->storeImage($t, $this->upload($image));
        }
        if ($sound) {
            $this->media->storeAudio($t, $this->voice(330 + $pos * 20));
        }
        $t->save();

        return $t;
    }

    private function lesson(User $user, Child $child, string $title, string $language, int $week): Lesson
    {
        return Lesson::create(['user_id' => $user->id, 'child_id' => $child->id, 'title' => $title, 'language' => $language, 'track' => $child->track, 'week_number' => $week, 'status' => 'published', 'published_at' => now()]);
    }

    private function lessons(User $user, Child $child): void
    {
        $l1 = $this->lesson($user, $child, 'Week 1: Letters and numbers', 'both', 1);
        $this->task($l1, 1, ['type' => 'trace_letter', 'title' => 'Trace the letter A', 'target' => 'A', 'instruction' => 'Follow the dots']);
        $this->task($l1, 2, ['type' => 'trace_letter', 'title' => 'Trace the small letter b', 'target' => 'b']);
        $this->task($l1, 3, ['type' => 'trace_number', 'title' => 'Trace the number 7', 'target' => '7']);
        $this->task($l1, 4, ['type' => 'trace_letter', 'title' => 'Trace the Urdu letter alif', 'target' => 'ا']);

        $l2 = $this->lesson($user, $child, 'Week 2: Words and pictures', 'english', 2);
        $this->task($l2, 1, ['type' => 'pick_picture', 'title' => 'Which one is water?', 'options' => ['choices' => ['Water', 'Milk', 'Bed'], 'correct' => 0]], 'water', true);
        $this->task($l2, 2, ['type' => 'spell_word', 'title' => 'Spell bed', 'target' => 'bed'], 'bed', true);
        $this->task($l2, 3, ['type' => 'spell_word', 'title' => 'Spell milk', 'target' => 'milk'], 'milk', true);
        $this->task($l2, 4, ['type' => 'social_story', 'title' => 'A friend takes your toy', 'instruction' => 'How do you feel?', 'options' => ['choices' => ['Sad', 'Happy'], 'correct' => 0]]);

        $l3 = $this->lesson($user, $child, 'اردو: حروف اور الفاظ', 'urdu', 3);
        $this->task($l3, 1, ['type' => 'trace_letter', 'title' => 'ب لکھو', 'target' => 'ب']);
        $this->task($l3, 2, ['type' => 'spell_word', 'title' => 'پانی ہجے کرو', 'target' => 'پانی'], 'water', true);
        $this->task($l3, 3, ['type' => 'listen_repeat', 'title' => 'سنو اور دہراؤ', 'target' => 'السلام علیکم'], null, true);
    }

    private function cards(Child $child): void
    {
        $card = AacCard::create(['child_id' => $child->id, 'category' => 'activities', 'english_label' => 'Blue ball', 'urdu_label' => 'نیلی گیند', 'icon_path' => '']);
        $this->media->storeAacIcon($card, $this->upload('play'));
        $this->media->storeAacAudio($card, $this->voice(440));
        $card->save();

        foreach ([['Mama', 'Sana', 'hug', 392], ['Baba', 'Imran', 'happy', 262]] as [$rel, $name, $pic, $hz]) {
            $m = FamilyMember::create(['child_id' => $child->id, 'relation_title' => $rel, 'display_name' => $name, 'photo_path' => '', 'audio_prompt_path' => '', 'audio_mime' => 'audio/wav', 'consented_at' => now(), 'consented_by' => $child->user_id]);
            $this->media->storeFamilyPhoto($m, $this->upload($pic));
            $this->media->storeFamilyAudio($m, $this->voice($hz));
            $m->save();
        }
    }

    /** A week of life: what the parent's reports, the timeline and the screen-time chart show. */
    private function history(Child $child, int $seed): void
    {
        mt_srand(1000 + $seed);
        $letters = [['A', 'english'], ['b', 'english'], ['7', 'english'], ['ا', 'urdu'], ['ب', 'urdu']];
        for ($d = 6; $d >= 0; $d--) {
            $day = now()->subDays($d);
            if ($d % 3 !== 2) {
                $total = mt_rand(8, 14);
                $ok = (int) round($total * (0.55 + (6 - $d) * 0.05));
                $p = new PracticeSession(['child_id' => $child->id, 'language' => $d % 2 ? 'urdu' : 'english', 'total_attempts' => $total, 'successful_attempts' => min($total, $ok), 'duration_seconds' => mt_rand(240, 600)]);
                $p->created_at = $day->copy()->setTime(16, mt_rand(0, 40));
                $p->save();
                foreach (array_slice($letters, $d % 2 ? 3 : 0, $d % 2 ? 2 : 3) as [$l, $lang]) {
                    $a = mt_rand(2, 5);
                    $s = new SessionLetter(['session_id' => $p->id, 'child_id' => $child->id, 'language' => $lang, 'letter' => $l, 'attempts' => $a, 'successes' => min($a, $a - mt_rand(0, 2)), 'assisted' => mt_rand(0, $a), 'deviation' => mt_rand(30, 120) / 10]);
                    $s->created_at = $p->created_at;
                    $s->save();
                }
                RewardLedger::create(['child_id' => $child->id, 'minutes' => 5, 'reason' => 'good_score', 'ref_id' => $p->id])->forceFill(['created_at' => $p->created_at])->save();
            }
            if ($d <= 4) {
                $g = new GameSession(['child_id' => $child->id, 'game' => ['count', 'memory_match', 'first_letter', 'scramble', 'feelings'][$d % 5], 'level' => 1 + $d % 3, 'allowed_seconds' => 300, 'status' => 'finished', 'rounds_played' => 8, 'correct' => mt_rand(4, 8), 'seconds_played' => mt_rand(120, 280), 'minutes_spent' => mt_rand(2, 5), 'finished_at' => $day->copy()->setTime(17, 10)]);
                $g->created_at = $day->copy()->setTime(17, 0);
                $g->save();
            }
            if ($d % 2 === 0) {
                $c = new CalmSession(['child_id' => $child->id, 'activity' => ['breathing_bubble', 'balloon_belly', 'animal_stretch'][$d % 3], 'seconds' => mt_rand(60, 150)]);
                $c->created_at = $day->copy()->setTime(18, 30);
                $c->save();
            }
            foreach ([['water', 9], ['help', 15]] as $k => [$need, $hour]) {
                if (($d + $k) % 3 === 0) {
                    $n = new ChildNeedsLog(['child_id' => $child->id, 'need_type' => $need, 'acknowledged' => true, 'acknowledged_at' => $day->copy()->setTime($hour, 3)]);
                    $n->created_at = $day->copy()->setTime($hour, 1);
                    $n->save();
                }
            }
            ScreenTimeLog::create(['child_id' => $child->id, 'day' => $day->toDateString(), 'seconds' => mt_rand(25, 70) * 60, 'bonus_seconds' => $d === 3 ? 600 : 0]);
        }
        RewardLedger::create(['child_id' => $child->id, 'minutes' => 10, 'reason' => 'parent_bonus', 'note' => 'A good week']);
    }
}
