<?php

return [
    // Play minutes are earned by practising and spent by playing. Parents can change every number per child.
    'rewards' => [
        'enabled' => true,
        'threshold_pct' => 70,          // accuracy that counts as a good session
        'min_attempts' => 5,            // a session needs at least this many tries to earn anything
        'minutes_per_good_session' => 5,
        'reward_for_effort' => false,   // a few minutes just for finishing a session
        'effort_minutes' => 2,
        'improvement_points' => 10,     // beat your own recent average by this many points
        'improvement_minutes' => 3,
        'streak_days' => 3,             // practising this many days in a row
        'streak_minutes' => 3,
        'daily_cap_minutes' => 30,      // most a child can earn in a day
        'bank_cap_minutes' => 60,       // most a child can save up
        'max_session_minutes' => 15,    // longest single play
        'enabled_games' => null,        // null = all
    ],
    // A special-support child starts gentler: lower bar, small reward for effort.
    'rewards_special' => ['threshold_pct' => 60, 'reward_for_effort' => true, 'min_attempts' => 3],

    'games' => [
        'count' => ['category' => 'math', 'title' => ['en' => 'How many?', 'ur' => 'کتنے ہیں؟'], 'about' => 'Count the pictures and pick the number.', 'needs_items' => false],
        'add_sub' => ['category' => 'math', 'title' => ['en' => 'Add and take away', 'ur' => 'جمع اور تفریق'], 'about' => 'Small sums with pictures.', 'needs_items' => false],
        'number_order' => ['category' => 'math', 'title' => ['en' => 'What number is missing?', 'ur' => 'کون سا نمبر غائب ہے؟'], 'about' => 'Fill the gap in a number line.', 'needs_items' => false],
        'animal_sound' => ['category' => 'listening', 'title' => ['en' => 'Who makes this sound?', 'ur' => 'یہ آواز کون نکالتا ہے؟'], 'about' => 'Listen, then pick the animal.', 'needs_items' => true],
        'sound_guess' => ['category' => 'listening', 'title' => ['en' => 'Which sound is it?', 'ur' => 'کون سی آواز ہے؟'], 'about' => 'Look at the animal, then pick its sound.', 'needs_items' => true],
        'memory_match' => ['category' => 'brain', 'title' => ['en' => 'Memory match', 'ur' => 'جوڑے ملاؤ'], 'about' => 'Flip cards and find the pairs.', 'needs_items' => false],
        'odd_one_out' => ['category' => 'brain', 'title' => ['en' => 'Odd one out', 'ur' => 'الگ کون ہے؟'], 'about' => 'Find the one that does not belong.', 'needs_items' => false],
        'pattern' => ['category' => 'brain', 'title' => ['en' => 'What comes next?', 'ur' => 'اگلا کون سا؟'], 'about' => 'Finish the pattern.', 'needs_items' => false],
    ],
    'min_library_items' => 4,           // sound games need at least this many animals with a picture and a sound
    'max_level' => 5,
    'rounds_default' => 10,
    'rounds_max' => 20,

    // Icons the app draws itself (original artwork to be supplied by design). The server only sends keys and names.
    'icons' => [
        'fruit' => ['apple' => ['سیب', 'Apple'], 'banana' => ['کیلا', 'Banana'], 'orange' => ['مالٹا', 'Orange'], 'grapes' => ['انگور', 'Grapes'], 'mango' => ['آم', 'Mango']],
        'animal' => ['cat' => ['بلی', 'Cat'], 'dog' => ['کتا', 'Dog'], 'cow' => ['گائے', 'Cow'], 'fish' => ['مچھلی', 'Fish'], 'bird' => ['پرندہ', 'Bird']],
        'vehicle' => ['car' => ['گاڑی', 'Car'], 'bus' => ['بس', 'Bus'], 'bike' => ['سائیکل', 'Bike'], 'plane' => ['ہوائی جہاز', 'Plane'], 'boat' => ['کشتی', 'Boat']],
        'shape' => ['circle' => ['دائرہ', 'Circle'], 'square' => ['چوکور', 'Square'], 'triangle' => ['تکون', 'Triangle'], 'star' => ['ستارہ', 'Star'], 'heart' => ['دل', 'Heart']],
        'toy' => ['ball' => ['گیند', 'Ball'], 'kite' => ['پتنگ', 'Kite'], 'doll' => ['گڑیا', 'Doll'], 'drum' => ['ڈھول', 'Drum'], 'blocks' => ['بلاکس', 'Blocks']],
    ],

    // Names of the animals the sound games use. Pictures and sounds are uploaded by the administrator.
    'animals' => [
        'lion' => ['Lion', 'شیر'], 'cow' => ['Cow', 'گائے'], 'dog' => ['Dog', 'کتا'], 'cat' => ['Cat', 'بلی'], 'duck' => ['Duck', 'بطخ'], 'sheep' => ['Sheep', 'بھیڑ'],
        'horse' => ['Horse', 'گھوڑا'], 'elephant' => ['Elephant', 'ہاتھی'], 'rooster' => ['Rooster', 'مرغا'], 'frog' => ['Frog', 'مینڈک'], 'donkey' => ['Donkey', 'گدھا'], 'goat' => ['Goat', 'بکری'],
    ],
];
