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
        'count' => ['category' => 'math', 'age' => [3, 6], 'title' => ['en' => 'How many?', 'ur' => 'کتنے ہیں؟'], 'about' => 'Count the pictures and pick the number.', 'needs_items' => false, 'for' => ['standard', 'special']],
        'add_sub' => ['category' => 'math', 'age' => [5, 8], 'title' => ['en' => 'Add and take away', 'ur' => 'جمع اور تفریق'], 'about' => 'Small sums with pictures.', 'needs_items' => false, 'for' => ['standard']],
        'number_order' => ['category' => 'math', 'age' => [4, 7], 'title' => ['en' => 'What number is missing?', 'ur' => 'کون سا نمبر غائب ہے؟'], 'about' => 'Fill the gap in a number line.', 'needs_items' => false, 'for' => ['standard', 'special']],
        'animal_sound' => ['category' => 'listening', 'age' => [3, 6], 'title' => ['en' => 'Who makes this sound?', 'ur' => 'یہ آواز کون نکالتا ہے؟'], 'about' => 'Listen, then pick the animal.', 'needs_items' => true, 'for' => ['standard', 'special']],
        'sound_guess' => ['category' => 'listening', 'age' => [3, 6], 'title' => ['en' => 'Which sound is it?', 'ur' => 'کون سی آواز ہے؟'], 'about' => 'Look at the animal, then pick its sound.', 'needs_items' => true, 'for' => ['standard', 'special']],
        'memory_match' => ['category' => 'brain', 'age' => [3, 10], 'title' => ['en' => 'Memory match', 'ur' => 'جوڑے ملاؤ'], 'about' => 'Flip cards and find the pairs.', 'needs_items' => false, 'for' => ['standard', 'special']],
        'odd_one_out' => ['category' => 'brain', 'age' => [4, 8], 'title' => ['en' => 'Odd one out', 'ur' => 'الگ کون ہے؟'], 'about' => 'Find the one that does not belong.', 'needs_items' => false, 'for' => ['standard']],
        'pattern' => ['category' => 'brain', 'age' => [4, 9], 'title' => ['en' => 'What comes next?', 'ur' => 'اگلا کون سا؟'], 'about' => 'Finish the pattern.', 'needs_items' => false, 'for' => ['standard']],

        // Added for a wider range of children. `for` says which track a game suits best (the apps put those first); every game works for every child.
        'first_letter' => ['category' => 'language', 'age' => [4, 7], 'title' => ['en' => 'First letter', 'ur' => 'پہلا حرف'], 'about' => 'See a picture, pick the letter its name starts with.', 'needs_items' => false, 'for' => ['standard']],
        'spell_picture' => ['category' => 'language', 'age' => [5, 9], 'title' => ['en' => 'Spell it', 'ur' => 'ہجے کرو'], 'about' => 'See a picture, spell its name on the alphabet keyboard.', 'needs_items' => false, 'for' => ['standard']],
        'alphabet_gap' => ['category' => 'language', 'age' => [4, 7], 'title' => ['en' => 'Missing letter', 'ur' => 'غائب حرف'], 'about' => 'Fill the gap in the alphabet.', 'needs_items' => false, 'for' => ['standard', 'special']],
        'opposites' => ['category' => 'language', 'age' => [5, 9], 'title' => ['en' => 'Opposites', 'ur' => 'الٹ'], 'about' => 'Pick the opposite word.', 'needs_items' => false, 'for' => ['standard']],
        'calendar' => ['category' => 'language', 'age' => [6, 10], 'title' => ['en' => 'Days and months', 'ur' => 'دن اور مہینے'], 'about' => 'What day or month comes next?', 'needs_items' => false, 'for' => ['standard']],
        'colors' => ['category' => 'concepts', 'age' => [3, 6], 'title' => ['en' => 'Colours', 'ur' => 'رنگ'], 'about' => 'Find the colour, or name it.', 'needs_items' => false, 'for' => ['standard', 'special']],
        'which_group' => ['category' => 'concepts', 'age' => [3, 6], 'title' => ['en' => 'Which one is a ...?', 'ur' => 'کون سا ... ہے؟'], 'about' => 'Pick the fruit, the animal, the vehicle.', 'needs_items' => false, 'for' => ['standard', 'special']],
        'match_same' => ['category' => 'concepts', 'age' => [3, 6], 'title' => ['en' => 'Find the same', 'ur' => 'ایک جیسا ڈھونڈو'], 'about' => 'Find the picture that matches.', 'needs_items' => false, 'for' => ['special']],
        'more_less' => ['category' => 'math', 'age' => [4, 7], 'title' => ['en' => 'More or fewer', 'ur' => 'زیادہ یا کم'], 'about' => 'Which group has the most, or the fewest?', 'needs_items' => false, 'for' => ['standard', 'special']],
        'feelings' => ['category' => 'social', 'age' => [3, 8], 'title' => ['en' => 'Feelings', 'ur' => 'احساسات'], 'about' => 'Name the feeling, or find the face.', 'needs_items' => false, 'for' => ['special']],
        'routine_order' => ['category' => 'daily_living', 'age' => [3, 8], 'title' => ['en' => 'First, then ...', 'ur' => 'پہلے، پھر ...'], 'about' => 'Put the steps of a daily routine in order.', 'needs_items' => false, 'for' => ['special']],

        // More games for ages 3 to 10. `age` is [youngest, oldest] it suits best; the apps show the right ones first, every game still works for every child.
        'number_tap' => ['category' => 'math', 'age' => [3, 5], 'title' => ['en' => 'Show me the number', 'ur' => 'عدد دکھاؤ'], 'about' => 'Hear a number, tap the group with that many.', 'needs_items' => false, 'for' => ['standard', 'special']],
        'size_game' => ['category' => 'concepts', 'age' => [3, 5], 'title' => ['en' => 'Big and small', 'ur' => 'بڑا اور چھوٹا'], 'about' => 'Find the biggest or the smallest.', 'needs_items' => false, 'for' => ['standard', 'special']],
        'word_to_picture' => ['category' => 'language', 'age' => [5, 7], 'title' => ['en' => 'Read and pick', 'ur' => 'پڑھو اور چنو'], 'about' => 'Read a word, pick its picture.', 'needs_items' => false, 'for' => ['standard']],
        'last_letter' => ['category' => 'language', 'age' => [5, 8], 'title' => ['en' => 'Last letter', 'ur' => 'آخری حرف'], 'about' => 'See a picture, pick the letter its name ends with.', 'needs_items' => false, 'for' => ['standard']],
        'bigger_number' => ['category' => 'math', 'age' => [5, 8], 'title' => ['en' => 'Bigger or smaller', 'ur' => 'بڑا یا چھوٹا عدد'], 'about' => 'Pick the bigger or the smaller number.', 'needs_items' => false, 'for' => ['standard']],
        'number_bonds' => ['category' => 'math', 'age' => [6, 9], 'title' => ['en' => 'Make the number', 'ur' => 'عدد بناؤ'], 'about' => 'Find the missing part: 3 + ? = 7.', 'needs_items' => false, 'for' => ['standard']],
        'word_blank' => ['category' => 'language', 'age' => [6, 9], 'title' => ['en' => 'Guess the missing letter', 'ur' => 'غائب حرف کا اندازہ'], 'about' => 'A word with a hole in it. Which letter fits?', 'needs_items' => false, 'for' => ['standard', 'special']],
        'read_clock' => ['category' => 'math', 'age' => [7, 10], 'title' => ['en' => 'What time is it?', 'ur' => 'کیا وقت ہوا ہے؟'], 'about' => 'Read the clock face.', 'needs_items' => false, 'for' => ['standard']],
        'scramble' => ['category' => 'language', 'age' => [7, 10], 'title' => ['en' => 'Word puzzle', 'ur' => 'لفظوں کی پہیلی'], 'about' => 'The letters are jumbled. Put them in order.', 'needs_items' => false, 'for' => ['standard']],
        'hangman' => ['category' => 'language', 'age' => [7, 10], 'title' => ['en' => 'Guess the word', 'ur' => 'لفظ بوجھو'], 'about' => 'Guess the secret word one letter at a time.', 'needs_items' => false, 'for' => ['standard']],
        // Discover quizzes: free to play (no play minutes needed). The facts come from config/learn.php, chosen by the child's age.
        'science_animals' => ['category' => 'science', 'free' => true, 'topic' => 'animals', 'age' => [3, 10], 'title' => ['en' => 'Animal quiz', 'ur' => 'جانوروں کا کوئز'], 'about' => 'Animals and where they live.', 'needs_items' => false, 'for' => ['standard', 'special']],
        'science_ocean' => ['category' => 'science', 'free' => true, 'topic' => 'ocean', 'age' => [3, 10], 'title' => ['en' => 'Under the sea quiz', 'ur' => 'سمندر کا کوئز'], 'about' => 'Sea animals and the deep ocean.', 'needs_items' => false, 'for' => ['standard', 'special']],
        'science_space' => ['category' => 'science', 'free' => true, 'topic' => 'space', 'age' => [3, 10], 'title' => ['en' => 'Space quiz', 'ur' => 'خلا کا کوئز'], 'about' => 'The Sun, Moon, planets and galaxies.', 'needs_items' => false, 'for' => ['standard', 'special']],
        'science_chemistry' => ['category' => 'science', 'free' => true, 'topic' => 'chemistry', 'age' => [3, 10], 'title' => ['en' => 'Matter quiz', 'ur' => 'مادے کا کوئز'], 'about' => 'Solids, liquids, gases and what things are made of.', 'needs_items' => false, 'for' => ['standard', 'special']],
        'science_ai' => ['category' => 'science', 'free' => true, 'topic' => 'ai', 'age' => [3, 10], 'title' => ['en' => 'AI quiz', 'ur' => 'اے آئی کوئز'], 'about' => 'What AI is and how to use it safely.', 'needs_items' => false, 'for' => ['standard', 'special']],
        // Tables and matching
        'table_row' => ['category' => 'math', 'age' => [6, 10], 'title' => ['en' => 'Complete the table', 'ur' => 'پہاڑا مکمل کرو'], 'about' => 'Fill the gap in a times-table row: 3, 6, ?, 12.', 'needs_items' => false, 'for' => ['standard']],
        'division_facts' => ['category' => 'math', 'age' => [8, 10], 'title' => ['en' => 'Sharing sums', 'ur' => 'تقسیم'], 'about' => 'Share equally: 12 ÷ 3 = ?', 'needs_items' => false, 'for' => ['standard']],
        'case_match' => ['category' => 'language', 'age' => [4, 7], 'title' => ['en' => 'Big and small letters', 'ur' => 'بڑے اور چھوٹے حروف'], 'about' => 'Match the capital letter with its small letter.', 'needs_items' => false, 'for' => ['standard', 'special']],
        'number_word' => ['category' => 'math', 'age' => [5, 8], 'title' => ['en' => 'Number words', 'ur' => 'عدد کے نام'], 'about' => 'Match the number with its name: 7 and seven.', 'needs_items' => false, 'for' => ['standard']],
        'times_table' => ['category' => 'math', 'age' => [8, 10], 'title' => ['en' => 'Times tables', 'ur' => 'پہاڑے'], 'about' => 'Multiply with pictures of groups.', 'needs_items' => false, 'for' => ['standard']],
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

    // Feelings and routines are kept apart from `icons` so the matching and odd-one-out games never mix them in.
    'feelings' => [
        'happy' => ['خوش', 'Happy'], 'sad' => ['اداس', 'Sad'], 'angry' => ['غصہ', 'Angry'], 'scared' => ['ڈرا ہوا', 'Scared'], 'tired' => ['تھکا ہوا', 'Tired'], 'surprised' => ['حیران', 'Surprised'],
    ],
    'routines' => [
        'morning' => ['title' => ['en' => 'Getting ready in the morning', 'ur' => 'صبح تیار ہونا'], 'steps' => [
            'wake_up' => ['جاگو', 'Wake up'], 'wash_face' => ['منہ دھوؤ', 'Wash your face'], 'brush_teeth' => ['دانت صاف کرو', 'Brush your teeth'], 'eat_breakfast' => ['ناشتہ کرو', 'Eat breakfast'], 'get_dressed' => ['کپڑے پہنو', 'Get dressed'], 'pack_bag' => ['بستہ رکھو', 'Pack your bag'],
        ]],
        'bedtime' => ['title' => ['en' => 'Going to bed', 'ur' => 'سونے کی تیاری'], 'steps' => [
            'bath' => ['نہاؤ', 'Have a bath'], 'pajamas' => ['سونے کے کپڑے پہنو', 'Put on pyjamas'], 'brush_teeth' => ['دانت صاف کرو', 'Brush your teeth'], 'story' => ['کہانی سنو', 'Listen to a story'], 'sleep' => ['سو جاؤ', 'Go to sleep'],
        ]],
        'hand_washing' => ['title' => ['en' => 'Washing your hands', 'ur' => 'ہاتھ دھونا'], 'steps' => [
            'wet' => ['ہاتھ گیلے کرو', 'Wet your hands'], 'soap' => ['صابن لگاؤ', 'Use soap'], 'scrub' => ['ہاتھ ملو', 'Rub your hands'], 'rinse' => ['پانی سے دھوؤ', 'Rinse'], 'dry' => ['ہاتھ خشک کرو', 'Dry your hands'],
        ]],
        'meal' => ['title' => ['en' => 'Having a meal', 'ur' => 'کھانا کھانا'], 'steps' => [
            'wash_hands' => ['ہاتھ دھوؤ', 'Wash your hands'], 'sit_down' => ['بیٹھ جاؤ', 'Sit at the table'], 'eat' => ['کھانا کھاؤ', 'Eat your food'], 'drink' => ['پانی پیو', 'Have a drink'], 'clean_up' => ['صفائی کرو', 'Tidy up'],
        ]],
    ],
    // Extra words for the word games (puzzle, guess the word, missing letter). Each has a picture the app draws, and the names in both languages
    // (Urdu is null where no short, common word fits the keyboard). Words are lower case; the picture is a hint, never the answer.
    'words' => [
        'moon' => ['چاند', 'moon'], 'sun' => ['سورج', 'sun'], 'tree' => ['درخت', 'tree'], 'book' => ['کتاب', 'book'], 'rain' => ['بارش', 'rain'],
        'milk' => ['دودھ', 'milk'], 'frog' => ['مینڈک', 'frog'], 'bear' => ['ریچھ', 'bear'], 'lion' => ['شیر', 'lion'], 'cake' => ['کیک', 'cake'],
        'door' => ['دروازہ', 'door'], 'ship' => ['جہاز', 'ship'], 'house' => ['گھر', 'house'], 'water' => ['پانی', 'water'], 'horse' => ['گھوڑا', 'horse'],
        'plant' => ['پودا', 'plant'], 'cloud' => ['بادل', 'cloud'], 'pizza' => [null, 'pizza'], 'tiger' => [null, 'tiger'], 'bread' => ['روٹی', 'bread'],
        'chair' => ['کرسی', 'chair'], 'clock' => ['گھڑی', 'clock'], 'snake' => ['سانپ', 'snake'], 'mouse' => ['چوہا', 'mouse'], 'train' => ['ریل', 'train'],
        'river' => ['دریا', 'river'], 'rabbit' => ['خرگوش', 'rabbit'], 'flower' => ['پھول', 'flower'], 'school' => ['اسکول', 'school'], 'garden' => ['باغ', 'garden'],
        'monkey' => ['بندر', 'monkey'], 'pencil' => ['پنسل', 'pencil'], 'bottle' => ['بوتل', 'bottle'], 'rainbow' => [null, 'rainbow'], 'giraffe' => ['زرافہ', 'giraffe'],
        'penguin' => [null, 'penguin'], 'dolphin' => [null, 'dolphin'], 'elephant' => ['ہاتھی', 'elephant'], 'egg' => ['انڈا', 'egg'], 'hat' => ['ٹوپی', 'hat'],
        'bag' => ['بستہ', 'bag'], 'shoe' => ['جوتا', 'shoe'], 'key' => ['چابی', 'key'], 'bell' => ['گھنٹی', 'bell'], 'pen' => ['قلم', 'pen'],
        'cup' => ['پیالہ', 'cup'],
        'ice' => [null, 'ice'], 'juice' => [null, 'juice'], 'nose' => [null, 'nose'], 'queen' => [null, 'queen'], 'umbrella' => [null, 'umbrella'], 'van' => [null, 'van'],
        'xylophone' => [null, 'xylophone'], 'yoyo' => [null, 'yoyo'], 'zebra' => [null, 'zebra'], 'owl' => ['الو', 'owl'], 'duck' => ['بطخ', 'duck'], 'butterfly' => ['تتلی', 'butterfly'],
        'soap' => ['صابن', 'soap'], 'parrot' => ['طوطا', 'parrot'], 'glasses' => ['عینک', 'glasses'], 'balloon' => ['غبارہ', 'balloon'], 'lemon' => ['لیموں', 'lemon'], 'whale' => ['وہیل', 'whale'],
        'pear' => ['ناشپاتی', 'pear'], 'ear' => ['کان', 'ear'], 'hand' => ['ہاتھ', 'hand'], 'ant' => ['چیونٹی', 'ant'], 'sock' => ['جراب', 'sock'], 'jar' => [null, 'jar'],
    ],

    'colors' => [
        'red' => ['#e8483f', 'Red', 'سرخ'], 'blue' => ['#3f7fe8', 'Blue', 'نیلا'], 'green' => ['#3fb36b', 'Green', 'ہرا'], 'yellow' => ['#f4cf3a', 'Yellow', 'پیلا'], 'orange' => ['#f58a2e', 'Orange', 'نارنجی'],
        'purple' => ['#8a56d6', 'Purple', 'جامنی'], 'pink' => ['#f27fb0', 'Pink', 'گلابی'], 'brown' => ['#8a5a3a', 'Brown', 'بھورا'], 'black' => ['#222222', 'Black', 'کالا'], 'white' => ['#f7f7f7', 'White', 'سفید'],
    ],
    'opposites' => [
        ['big', 'بڑا', 'small', 'چھوٹا'], ['hot', 'گرم', 'cold', 'ٹھنڈا'], ['up', 'اوپر', 'down', 'نیچے'], ['day', 'دن', 'night', 'رات'], ['open', 'کھولو', 'close', 'بند کرو'],
        ['happy', 'خوش', 'sad', 'اداس'], ['fast', 'تیز', 'slow', 'آہستہ'], ['full', 'بھرا', 'empty', 'خالی'], ['in', 'اندر', 'out', 'باہر'], ['clean', 'صاف', 'dirty', 'گندا'],
        ['long', 'لمبا', 'short', 'چھوٹا قد'], ['old', 'بوڑھا', 'young', 'جوان'], ['hard', 'سخت', 'soft', 'نرم'], ['heavy', 'بھاری', 'light', 'ہلکا'],
    ],
    'days' => [['Monday', 'پیر'], ['Tuesday', 'منگل'], ['Wednesday', 'بدھ'], ['Thursday', 'جمعرات'], ['Friday', 'جمعہ'], ['Saturday', 'ہفتہ'], ['Sunday', 'اتوار']],
    'months' => [['January', 'جنوری'], ['February', 'فروری'], ['March', 'مارچ'], ['April', 'اپریل'], ['May', 'مئی'], ['June', 'جون'], ['July', 'جولائی'], ['August', 'اگست'], ['September', 'ستمبر'], ['October', 'اکتوبر'], ['November', 'نومبر'], ['December', 'دسمبر']],

    // Names of the animals the sound games use. Pictures and sounds are uploaded by the administrator.
    'animals' => [
        'lion' => ['Lion', 'شیر'], 'cow' => ['Cow', 'گائے'], 'dog' => ['Dog', 'کتا'], 'cat' => ['Cat', 'بلی'], 'duck' => ['Duck', 'بطخ'], 'sheep' => ['Sheep', 'بھیڑ'],
        'horse' => ['Horse', 'گھوڑا'], 'elephant' => ['Elephant', 'ہاتھی'], 'rooster' => ['Rooster', 'مرغا'], 'frog' => ['Frog', 'مینڈک'], 'donkey' => ['Donkey', 'گدھا'], 'goat' => ['Goat', 'بکری'],
    ],
];
