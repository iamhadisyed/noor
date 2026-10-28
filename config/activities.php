<?php

// Calm and movement activities. They are not games: no score, no play minutes spent, never locked, and they work for every child.
// Each step has a `cue` the app turns into a picture or motion (in = grow, hold = stay, out = shrink, rest = pause, do = an action).
// A child on a "no timers" profile taps to move to the next step instead of watching a clock; `seconds` is then only a gentle guide.
return [
    'breathing_bubble' => [
        'kind' => 'breathing', 'for' => ['special', 'standard'],
        'title' => ['en' => 'Breathe with the bubble', 'ur' => 'بلبلے کے ساتھ سانس لو'],
        'about' => ['en' => 'A bubble grows as you breathe in and shrinks as you breathe out.', 'ur' => 'سانس اندر لینے پر بلبلہ بڑا ہوتا ہے اور باہر نکالنے پر چھوٹا۔'],
        'cycles' => 5,
        'steps' => [
            ['cue' => 'in', 'seconds' => 4, 'text' => ['en' => 'Breathe in slowly', 'ur' => 'آہستہ سے سانس اندر لو']],
            ['cue' => 'hold', 'seconds' => 2, 'text' => ['en' => 'Hold', 'ur' => 'روکو']],
            ['cue' => 'out', 'seconds' => 6, 'text' => ['en' => 'Breathe out slowly', 'ur' => 'آہستہ سے سانس باہر نکالو']],
        ],
    ],
    'five_senses' => [
        'kind' => 'grounding', 'for' => ['special', 'standard'],
        'title' => ['en' => 'Look, touch, listen', 'ur' => 'دیکھو، چھوؤ، سنو'],
        'about' => ['en' => 'Notice things around you, one sense at a time.', 'ur' => 'ایک ایک حس سے اپنے آس پاس کی چیزوں پر دھیان دو۔'],
        'cycles' => 1,
        'steps' => [
            ['cue' => 'do', 'seconds' => 10, 'text' => ['en' => 'Find 5 things you can see', 'ur' => 'پانچ چیزیں ڈھونڈو جو تم دیکھ سکتے ہو']],
            ['cue' => 'do', 'seconds' => 10, 'text' => ['en' => 'Touch 4 things', 'ur' => 'چار چیزوں کو چھوؤ']],
            ['cue' => 'do', 'seconds' => 10, 'text' => ['en' => 'Listen for 3 sounds', 'ur' => 'تین آوازیں سنو']],
            ['cue' => 'do', 'seconds' => 8, 'text' => ['en' => 'Smell 2 things', 'ur' => 'دو چیزوں کو سونگھو']],
            ['cue' => 'rest', 'seconds' => 6, 'text' => ['en' => 'Take one slow breath. Well done!', 'ur' => 'ایک لمبی سانس لو۔ شاباش!']],
        ],
    ],
    'squeeze_release' => [
        'kind' => 'relax', 'for' => ['special', 'standard'],
        'title' => ['en' => 'Squeeze and let go', 'ur' => 'دباؤ اور چھوڑ دو'],
        'about' => ['en' => 'Squeeze each part of your body tight, then let it go soft.', 'ur' => 'جسم کے ہر حصے کو زور سے دباؤ، پھر ڈھیلا چھوڑ دو۔'],
        'cycles' => 1,
        'steps' => [
            ['cue' => 'do', 'seconds' => 5, 'text' => ['en' => 'Squeeze your hands tight', 'ur' => 'ہاتھوں کو زور سے بند کرو']],
            ['cue' => 'rest', 'seconds' => 5, 'text' => ['en' => 'Let go. Soft hands', 'ur' => 'چھوڑ دو۔ نرم ہاتھ']],
            ['cue' => 'do', 'seconds' => 5, 'text' => ['en' => 'Lift your shoulders up to your ears', 'ur' => 'کندھے کانوں تک اٹھاؤ']],
            ['cue' => 'rest', 'seconds' => 5, 'text' => ['en' => 'Let them drop', 'ur' => 'نیچے گرا دو']],
            ['cue' => 'do', 'seconds' => 5, 'text' => ['en' => 'Scrunch your face like a lemon', 'ur' => 'چہرہ سکیڑو جیسے لیموں چکھا ہو']],
            ['cue' => 'rest', 'seconds' => 5, 'text' => ['en' => 'Let your face go soft', 'ur' => 'چہرہ ڈھیلا چھوڑ دو']],
        ],
    ],
    'slow_count' => [
        'kind' => 'counting', 'for' => ['special', 'standard'],
        'title' => ['en' => 'Count slowly to ten', 'ur' => 'آہستہ آہستہ دس تک گنو'],
        'about' => ['en' => 'One number at a time, nice and slow.', 'ur' => 'ایک ایک عدد، آرام آرام سے۔'],
        'cycles' => 1,
        'steps' => array_map(fn ($n) => ['cue' => 'do', 'seconds' => 2, 'text' => ['en' => (string) $n, 'ur' => (string) $n]], range(1, 10)),
    ],
    'movement_break' => [
        'kind' => 'movement', 'for' => ['special', 'standard'],
        'title' => ['en' => 'Move your body', 'ur' => 'جسم ہلاؤ'],
        'about' => ['en' => 'A short break to stretch and wiggle.', 'ur' => 'کھینچنے اور ہلنے کا ایک مختصر وقفہ۔'],
        'cycles' => 1,
        'steps' => [
            ['cue' => 'do', 'seconds' => 6, 'text' => ['en' => 'Reach up high like a giraffe', 'ur' => 'زرافے کی طرح اوپر پہنچو']],
            ['cue' => 'do', 'seconds' => 6, 'text' => ['en' => 'Touch your toes', 'ur' => 'پاؤں کی انگلیوں کو چھوؤ']],
            ['cue' => 'do', 'seconds' => 8, 'text' => ['en' => 'Stomp your feet like an elephant', 'ur' => 'ہاتھی کی طرح پاؤں پٹخو']],
            ['cue' => 'do', 'seconds' => 8, 'text' => ['en' => 'Shake your hands like a wet puppy', 'ur' => 'گیلے پلے کی طرح ہاتھ جھٹکو']],
            ['cue' => 'rest', 'seconds' => 6, 'text' => ['en' => 'Stand still and breathe', 'ur' => 'خاموش کھڑے ہو کر سانس لو']],
        ],
    ],
];
