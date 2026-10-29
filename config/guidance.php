<?php

/**
 * Parent guides. General information only, never medical advice: every guide opens and closes with the disclaimer, and the apps and the
 * portal show it before anything else. Pro feature (see GuidanceController). Written in plain words in English and Urdu.
 *
 * Each section has `items`; each item has a `title` and a `text`, each with `en` and `ur`.
 */
return [
    'autism' => [
        'title' => ['en' => 'A guide for parents of a child with autism', 'ur' => 'آٹزم والے بچے کے والدین کے لیے رہنما'],
        'disclaimer' => [
            'en' => 'This is general information only. It is not medical advice and it does not diagnose or treat anything. Every child is special and different, so please talk to your own child\'s doctor before you do tests, change food, give supplements or start new exercise.',
            'ur' => 'یہ صرف عمومی معلومات ہیں۔ یہ طبی مشورہ نہیں ہے اور نہ کسی چیز کی تشخیص یا علاج ہے۔ ہر بچہ خاص اور مختلف ہوتا ہے، اس لیے ٹیسٹ کرانے، خوراک بدلنے، سپلیمنٹ دینے یا نئی ورزش شروع کرنے سے پہلے اپنے بچے کے ڈاکٹر سے ضرور بات کریں۔',
        ],
        'closing' => [
            'en' => 'Please remember: this guide is not a replacement for your child\'s doctor, dietitian or therapist. They know your child. Take this page with you and ask what is right for your child.',
            'ur' => 'یاد رکھیں: یہ رہنما آپ کے بچے کے ڈاکٹر، ماہرِ غذائیت یا تھراپسٹ کی جگہ نہیں لے سکتا۔ وہ آپ کے بچے کو جانتے ہیں۔ اس صفحے کو ساتھ لے جائیں اور پوچھیں کہ آپ کے بچے کے لیے کیا مناسب ہے۔',
        ],
        'sections' => [
            [
                'key' => 'tests',
                'icon' => 'tests',
                'title' => ['en' => 'Health checks doctors often ask about', 'ur' => 'صحت کے وہ معائنے جن کے بارے میں ڈاکٹر اکثر پوچھتے ہیں'],
                'intro' => [
                    'en' => 'There is no blood test that can diagnose autism. A diagnosis comes from a developmental assessment by a specialist. But children with autism, like all children, can have health problems that are easy to miss, so doctors often check the general health. Ask your doctor which of these are right for your child.',
                    'ur' => 'کوئی خون کا ٹیسٹ آٹزم کی تشخیص نہیں کر سکتا۔ تشخیص ماہر کے نشوونما کے جائزے سے ہوتی ہے۔ لیکن آٹزم والے بچوں میں بھی، دوسرے بچوں کی طرح، صحت کے ایسے مسائل ہو سکتے ہیں جو آسانی سے نظر نہیں آتے، اس لیے ڈاکٹر عموماً مجموعی صحت دیکھتے ہیں۔ اپنے ڈاکٹر سے پوچھیں کہ ان میں سے کون سے آپ کے بچے کے لیے مناسب ہیں۔',
                ],
                'items' => [
                    ['title' => ['en' => 'Full blood count (CBC)', 'ur' => 'مکمل بلڈ کاؤنٹ (سی بی سی)'], 'text' => ['en' => 'A simple blood test that shows anaemia (low blood) and signs of infection.', 'ur' => 'خون کا ایک سادہ ٹیسٹ جو خون کی کمی اور انفیکشن کی علامات دکھاتا ہے۔']],
                    ['title' => ['en' => 'Iron and ferritin', 'ur' => 'آئرن اور فیریٹن'], 'text' => ['en' => 'Low iron is common in children, especially fussy eaters. It can affect sleep, energy and attention.', 'ur' => 'بچوں میں آئرن کی کمی عام ہے، خاص طور پر کھانے میں نخرے کرنے والوں میں۔ اس سے نیند، توانائی اور توجہ متاثر ہو سکتی ہے۔']],
                    ['title' => ['en' => 'Vitamin D', 'ur' => 'وٹامن ڈی'], 'text' => ['en' => 'Needed for strong bones and general health. Many children, including those who stay indoors, have low levels.', 'ur' => 'مضبوط ہڈیوں اور مجموعی صحت کے لیے ضروری ہے۔ بہت سے بچوں میں، خاص طور پر جو زیادہ گھر کے اندر رہتے ہیں، اس کی کمی ہوتی ہے۔']],
                    ['title' => ['en' => 'Vitamin B12 and folate', 'ur' => 'وٹامن بی 12 اور فولیٹ'], 'text' => ['en' => 'Worth asking about when the child eats only a few foods or little meat, egg and dairy.', 'ur' => 'جب بچہ بہت کم چیزیں کھاتا ہو یا گوشت، انڈا اور دودھ کم لیتا ہو تو اس بارے میں پوچھنا مناسب ہے۔']],
                    ['title' => ['en' => 'Thyroid test (TSH)', 'ur' => 'تھائرائیڈ ٹیسٹ (ٹی ایس ایچ)'], 'text' => ['en' => 'Thyroid problems can affect growth, energy, mood and learning, and are treatable.', 'ur' => 'تھائرائیڈ کے مسائل نشوونما، توانائی، مزاج اور سیکھنے پر اثر ڈال سکتے ہیں، اور ان کا علاج ممکن ہے۔']],
                    ['title' => ['en' => 'Blood sugar, kidney and liver tests', 'ur' => 'بلڈ شوگر، گردے اور جگر کے ٹیسٹ'], 'text' => ['en' => 'A general health picture, and often needed before a doctor starts any medicine.', 'ur' => 'مجموعی صحت کی تصویر، اور اکثر کوئی دوا شروع کرنے سے پہلے ڈاکٹر کو ضرورت ہوتی ہے۔']],
                    ['title' => ['en' => 'Lead level (if your doctor advises)', 'ur' => 'سیسے کی مقدار (اگر ڈاکٹر مشورہ دے)'], 'text' => ['en' => 'Lead in old paint, pipes or some cosmetics can harm development. Children who put things in their mouth are checked more often.', 'ur' => 'پرانے رنگ، پائپوں یا کچھ کاسمیٹکس میں موجود سیسہ نشوونما کو نقصان پہنچا سکتا ہے۔ جو بچے چیزیں منہ میں ڈالتے ہیں ان کا زیادہ چیک کیا جاتا ہے۔']],
                    ['title' => ['en' => 'Coeliac and allergy tests (only if needed)', 'ur' => 'سیلیک اور الرجی ٹیسٹ (صرف ضرورت ہو تو)'], 'text' => ['en' => 'Considered when a child has tummy pain, loose stools, poor growth or reactions to foods. Do not cut foods out before testing, as it can hide the result.', 'ur' => 'جب بچے کو پیٹ درد، پتلے پاخانے، کمزور نشوونما یا کھانوں سے ردعمل ہو تو ان پر غور کیا جاتا ہے۔ ٹیسٹ سے پہلے کھانے بند نہ کریں، اس سے نتیجہ چھپ سکتا ہے۔']],
                    ['title' => ['en' => 'Hearing and eye checks', 'ur' => 'سماعت اور بینائی کے معائنے'], 'text' => ['en' => 'Not blood tests, but very important. Hearing and vision problems are easy to miss and can look like behaviour or speech difficulties.', 'ur' => 'یہ خون کے ٹیسٹ نہیں، مگر بہت اہم ہیں۔ سماعت اور بینائی کے مسائل آسانی سے نظر نہیں آتے اور رویے یا بولنے کی مشکل جیسے لگ سکتے ہیں۔']],
                    ['title' => ['en' => 'Other tests, only when a doctor advises', 'ur' => 'دیگر ٹیسٹ، صرف ڈاکٹر کے مشورے پر'], 'text' => ['en' => 'Genetic tests or a brain wave test (EEG) are for particular situations, for example seizures or loss of skills. They are not routine.', 'ur' => 'جینیاتی ٹیسٹ یا دماغی لہروں کا ٹیسٹ (ای ای جی) مخصوص حالات کے لیے ہوتے ہیں، مثلاً دورے یا صلاحیتوں کا ختم ہونا۔ یہ معمول کے ٹیسٹ نہیں ہیں۔']],
                ],
            ],
            [
                'key' => 'food',
                'icon' => 'food',
                'title' => ['en' => 'Food and eating', 'ur' => 'خوراک اور کھانا'],
                'intro' => [
                    'en' => 'No food or diet treats or cures autism. What helps is a balanced, safe diet that gives growing bodies what they need, and patience with fussy eating.',
                    'ur' => 'کوئی خوراک یا پرہیز آٹزم کا علاج یا شفا نہیں ہے۔ جو چیز مدد کرتی ہے وہ متوازن اور محفوظ خوراک ہے جو بڑھتے جسم کی ضرورت پوری کرے، اور کھانے کے نخروں پر صبر۔',
                ],
                'items' => [
                    ['title' => ['en' => 'A colourful plate', 'ur' => 'رنگ برنگی پلیٹ'], 'text' => ['en' => 'Aim for vegetables, fruit, daal and beans, eggs, chicken or fish, yoghurt (dahi) and whole-grain roti most days. Offer, do not force.', 'ur' => 'زیادہ تر دن سبزیاں، پھل، دال اور پھلیاں، انڈے، مرغی یا مچھلی، دہی اور سالم آٹے کی روٹی دینے کی کوشش کریں۔ پیش کریں، زبردستی نہ کریں۔']],
                    ['title' => ['en' => 'Iron-rich foods', 'ur' => 'آئرن والی غذائیں'], 'text' => ['en' => 'Daal, chickpeas, spinach, eggs and meat give iron. A squeeze of lemon or a piece of orange with the meal helps the body take it up.', 'ur' => 'دال، چنے، پالک، انڈے اور گوشت سے آئرن ملتا ہے۔ کھانے کے ساتھ لیموں یا مالٹے کا ٹکڑا جسم کو اسے جذب کرنے میں مدد دیتا ہے۔']],
                    ['title' => ['en' => 'Healthy fats', 'ur' => 'صحت مند چکنائی'], 'text' => ['en' => 'Fish, walnuts, almonds (ground or chopped for young children, and check for allergies) and a little ghee or olive oil are good choices.', 'ur' => 'مچھلی، اخروٹ، بادام (چھوٹے بچوں کے لیے پیس کر یا باریک کاٹ کر، اور الرجی دیکھ کر) اور تھوڑا گھی یا زیتون کا تیل اچھے انتخاب ہیں۔']],
                    ['title' => ['en' => 'Water and fibre for a happy tummy', 'ur' => 'خوش پیٹ کے لیے پانی اور ریشہ'], 'text' => ['en' => 'Constipation is common. Water, fruit, vegetables and whole grains help. If it keeps coming back or hurts, tell the doctor.', 'ur' => 'قبض عام ہے۔ پانی، پھل، سبزیاں اور سالم اناج مدد کرتے ہیں۔ اگر بار بار ہو یا تکلیف ہو تو ڈاکٹر کو بتائیں۔']],
                    ['title' => ['en' => 'Go easy on sugary and packaged foods', 'ur' => 'میٹھی اور پیکٹ والی چیزیں کم دیں'], 'text' => ['en' => 'Fizzy drinks, sweets, chips and biscuits fill small tummies and leave no room for real food. Keep them as treats.', 'ur' => 'سافٹ ڈرنک، مٹھائی، چپس اور بسکٹ چھوٹے پیٹ بھر دیتے ہیں اور اصل کھانے کی جگہ نہیں چھوڑتے۔ انہیں کبھی کبھار کی چیز رکھیں۔']],
                    ['title' => ['en' => 'Fussy eating: small steps', 'ur' => 'کھانے کے نخرے: چھوٹے قدم'], 'text' => ['en' => 'Put a tiny piece of a new food on the plate with a food they like. Let them touch, smell and lick it. It can take 10 to 15 tries. Cooking together helps. Try "food chaining": move from a food they like to one that is a little like it, for example chips to baked potato wedges.', 'ur' => 'پسندیدہ کھانے کے ساتھ نئی چیز کا ایک چھوٹا سا ٹکڑا پلیٹ میں رکھیں۔ انہیں چھونے، سونگھنے اور چکھنے دیں۔ اس میں 10 سے 15 کوششیں لگ سکتی ہیں۔ ساتھ مل کر پکانا مدد کرتا ہے۔ "فوڈ چیننگ" آزمائیں: پسندیدہ چیز سے اس سے ملتی جلتی چیز کی طرف جائیں، مثلاً چپس سے تندور کے آلو کی قاشوں کی طرف۔']],
                    ['title' => ['en' => 'Do not start special diets on your own', 'ur' => 'خود سے خاص پرہیز شروع نہ کریں'], 'text' => ['en' => 'Gluten-free, milk-free and other cut-out diets have not been shown to treat autism, and they can leave a child short of nutrients. Only change the diet with your doctor or a dietitian, for example if there is a proven allergy.', 'ur' => 'گلوٹن، دودھ اور دوسری چیزیں بند کرنے والے پرہیز آٹزم کے علاج کے طور پر ثابت نہیں ہوئے، اور ان سے بچے میں غذائی اجزا کی کمی ہو سکتی ہے۔ خوراک ڈاکٹر یا ماہرِ غذائیت کے ساتھ ہی بدلیں، مثلاً جب کوئی ثابت شدہ الرجی ہو۔']],
                    ['title' => ['en' => 'Vitamins and supplements', 'ur' => 'وٹامن اور سپلیمنٹ'], 'text' => ['en' => 'Give them only if the doctor says so, after a test shows a need. Too much of some vitamins can be harmful. Keep all supplements out of reach.', 'ur' => 'صرف ڈاکٹر کے کہنے پر دیں، جب ٹیسٹ سے ضرورت ثابت ہو۔ کچھ وٹامن زیادہ مقدار میں نقصان دہ ہو سکتے ہیں۔ تمام سپلیمنٹ بچے کی پہنچ سے دور رکھیں۔']],
                    ['title' => ['en' => 'Calm, regular meals', 'ur' => 'پرسکون، باقاعدہ کھانا'], 'text' => ['en' => 'Same times, same seat, little noise, no screens. Use the Kindo "meal" routine cards so the child knows what comes next. Watch for choking and cut food small.', 'ur' => 'وہی وقت، وہی جگہ، کم شور، اسکرین کے بغیر۔ کنڈو کے "کھانا" روٹین کارڈ استعمال کریں تاکہ بچے کو پتہ ہو آگے کیا ہوگا۔ گلے میں پھنسنے کا خیال رکھیں اور کھانا چھوٹا کاٹیں۔']],
                ],
            ],
            [
                'key' => 'exercise',
                'icon' => 'exercise',
                'title' => ['en' => 'Exercise and movement', 'ur' => 'ورزش اور حرکت'],
                'intro' => [
                    'en' => 'Active play helps sleep, mood, strength, balance and calm. Aim for about an hour of active play most days, in small pieces. Young children may need more. Let your child choose, and start small.',
                    'ur' => 'حرکت والا کھیل نیند، مزاج، طاقت، توازن اور سکون میں مدد دیتا ہے۔ زیادہ تر دن تقریباً ایک گھنٹہ، چھوٹے چھوٹے حصوں میں، حرکت والے کھیل کی کوشش کریں۔ چھوٹے بچوں کو زیادہ کی ضرورت ہو سکتی ہے۔ بچے کو چننے دیں، اور چھوٹے سے شروع کریں۔',
                ],
                'items' => [
                    ['title' => ['en' => 'Walking and outdoor play', 'ur' => 'چہل قدمی اور باہر کھیل'], 'text' => ['en' => 'A daily walk, the park, a garden. Sunlight also helps vitamin D. Pick quiet times and places if crowds and noise upset your child.', 'ur' => 'روزانہ چہل قدمی، پارک، باغ۔ دھوپ سے وٹامن ڈی میں بھی مدد ملتی ہے۔ اگر ہجوم اور شور بچے کو پریشان کرے تو پرسکون وقت اور جگہ چنیں۔']],
                    ['title' => ['en' => 'Swimming (with close supervision)', 'ur' => 'تیراکی (قریبی نگرانی کے ساتھ)'], 'text' => ['en' => 'Many children love water. Some wander towards it without fear, so an adult must always be within arm\'s reach, and swimming lessons help.', 'ur' => 'بہت سے بچے پانی کو پسند کرتے ہیں۔ کچھ بغیر ڈر کے اس کی طرف چلے جاتے ہیں، اس لیے بڑا ہمیشہ بازو کی پہنچ میں ہو، اور تیراکی سیکھنا مفید ہے۔']],
                    ['title' => ['en' => 'Jumping, bouncing and balancing', 'ur' => 'اچھلنا، کودنا اور توازن'], 'text' => ['en' => 'Hopping, a small trampoline (with an adult and a safe set-up), balance beams, stepping stones. Many children find this calming.', 'ur' => 'پھدکنا، چھوٹا ٹرامپولین (بڑے کے ساتھ اور محفوظ انتظام میں)، توازن کی لکڑی، پتھروں پر قدم۔ بہت سے بچے اسے پرسکون پاتے ہیں۔']],
                    ['title' => ['en' => 'Ball games and cycling', 'ur' => 'گیند کے کھیل اور سائیکل'], 'text' => ['en' => 'Rolling, kicking and throwing a ball builds coordination and turn-taking. Use a helmet for a bike or scooter.', 'ur' => 'گیند لڑھکانا، ٹھوکر مارنا اور پھینکنا ہم آہنگی اور باری لینا سکھاتا ہے۔ سائیکل یا اسکوٹر کے لیے ہیلمٹ پہنائیں۔']],
                    ['title' => ['en' => 'Dance, music and animal stretches', 'ur' => 'رقص، موسیقی اور جانوروں کی طرح کھنچاؤ'], 'text' => ['en' => 'Move to a favourite song. Try the "Animal stretches" and "Slow Simon says" activities in the Kindo child app.', 'ur' => 'پسندیدہ گانے پر حرکت کریں۔ کنڈو چائلڈ ایپ میں "جانوروں کی طرح کھنچاؤ" اور "آہستہ سائمن کہتا ہے" آزمائیں۔']],
                    ['title' => ['en' => 'Heavy work for calm', 'ur' => 'سکون کے لیے بھاری کام'], 'text' => ['en' => 'Pushing a laundry basket, carrying light shopping, wall push-ups, pulling a wagon. Pushing and pulling can help some children feel settled. An occupational therapist can tailor this.', 'ur' => 'کپڑوں کی ٹوکری دھکیلنا، ہلکا سامان اٹھانا، دیوار پر پش اپ، گاڑی کھینچنا۔ دھکیلنا اور کھینچنا کچھ بچوں کو سکون دیتا ہے۔ آکیوپیشنل تھراپسٹ اسے بچے کے مطابق بنا سکتا ہے۔']],
                    ['title' => ['en' => 'An obstacle course at home', 'ur' => 'گھر میں رکاوٹوں کا راستہ'], 'text' => ['en' => 'Crawl under a table, jump over a cushion, walk along a line of tape. Show the steps with pictures.', 'ur' => 'میز کے نیچے رینگیں، گدی کے اوپر سے کودیں، ٹیپ کی لکیر پر چلیں۔ مراحل تصویروں سے دکھائیں۔']],
                    ['title' => ['en' => 'Make it easy to say yes', 'ur' => 'ہاں کہنا آسان بنائیں'], 'text' => ['en' => 'Start with 5 minutes. Use a "first, then" picture. Same time each day. Praise effort. Stop if your child is upset and try again another day. Never force.', 'ur' => '5 منٹ سے شروع کریں۔ "پہلے، پھر" کی تصویر استعمال کریں۔ روزانہ وہی وقت۔ کوشش کی تعریف کریں۔ اگر بچہ پریشان ہو تو رک جائیں اور کسی اور دن دوبارہ کوشش کریں۔ کبھی زبردستی نہ کریں۔']],
                    ['title' => ['en' => 'Check first if there is a health concern', 'ur' => 'صحت کا مسئلہ ہو تو پہلے پوچھیں'], 'text' => ['en' => 'Ask the doctor before vigorous exercise if your child has seizures, a heart condition, joint problems or very low muscle tone.', 'ur' => 'اگر بچے کو دورے، دل کا مسئلہ، جوڑوں کی تکلیف یا بہت کمزور پٹھے ہوں تو سخت ورزش سے پہلے ڈاکٹر سے پوچھیں۔']],
                ],
            ],
            [
                'key' => 'daily',
                'icon' => 'daily',
                'title' => ['en' => 'Sleep, routines and comfort', 'ur' => 'نیند، معمولات اور آرام'],
                'intro' => [
                    'en' => 'Predictable days feel safe. Small things at home can make a big difference.',
                    'ur' => 'متوقع دن محفوظ محسوس ہوتے ہیں۔ گھر کی چھوٹی چیزیں بڑا فرق ڈال سکتی ہیں۔',
                ],
                'items' => [
                    ['title' => ['en' => 'A steady sleep routine', 'ur' => 'نیند کا مستقل معمول'], 'text' => ['en' => 'Same bedtime, dim lights, no screens for an hour before bed, a calm routine such as bath, pyjamas, story, sleep. Use the Kindo "bedtime" routine game. Tell the doctor if sleep stays very hard.', 'ur' => 'سونے کا وہی وقت، مدھم روشنی، سونے سے ایک گھنٹہ پہلے اسکرین بند، پرسکون معمول جیسے نہانا، سونے کے کپڑے، کہانی، نیند۔ کنڈو کا "سونے کی تیاری" روٹین گیم استعمال کریں۔ اگر نیند بہت مشکل رہے تو ڈاکٹر کو بتائیں۔']],
                    ['title' => ['en' => 'Pictures for what comes next', 'ur' => 'آگے کیا ہوگا، تصویروں سے'], 'text' => ['en' => 'Use picture cards and "first, then" so changes are not a surprise. Warn before a change.', 'ur' => 'تصویری کارڈ اور "پہلے، پھر" استعمال کریں تاکہ تبدیلی اچانک نہ لگے۔ تبدیلی سے پہلے بتا دیں۔']],
                    ['title' => ['en' => 'Respect sensory needs', 'ur' => 'حسی ضرورتوں کا احترام'], 'text' => ['en' => 'Loud sounds, bright lights, scratchy clothes or strong smells can be very hard. Quiet corners, ear defenders and soft clothes can help. Use the Kindo calm activities after a hard moment.', 'ur' => 'تیز آوازیں، چبھتی روشنی، کھردرے کپڑے یا تیز خوشبو بہت مشکل ہو سکتی ہیں۔ پرسکون کونا، کانوں کے محافظ اور نرم کپڑے مدد کر سکتے ہیں۔ مشکل لمحے کے بعد کنڈو کی پرسکون سرگرمیاں استعمال کریں۔']],
                    ['title' => ['en' => 'Therapy and school', 'ur' => 'تھراپی اور اسکول'], 'text' => ['en' => 'Speech, occupational and behaviour therapists and teachers know your child best together with you. Share the Kindo report and invite them with a code under "Therapists and teachers".', 'ur' => 'اسپیچ، آکیوپیشنل اور رویے کے تھراپسٹ اور اساتذہ آپ کے ساتھ مل کر بچے کو بہتر جانتے ہیں۔ کنڈو کی رپورٹ شیئر کریں اور "تھراپسٹ اور اساتذہ" میں کوڈ کے ذریعے انہیں مدعو کریں۔']],
                    ['title' => ['en' => 'Look after yourself too', 'ur' => 'اپنا بھی خیال رکھیں'], 'text' => ['en' => 'Parents need rest and support. Invite family to share the load (Kindo "Share with family") and ask for help when you are tired.', 'ur' => 'والدین کو آرام اور سہارے کی ضرورت ہوتی ہے۔ خاندان کو ذمہ داری بانٹنے کے لیے مدعو کریں (کنڈو "خاندان کے ساتھ شیئر کریں") اور تھکن ہو تو مدد مانگیں۔']],
                ],
            ],
            [
                'key' => 'doctor',
                'icon' => 'doctor',
                'title' => ['en' => 'See a doctor soon if...', 'ur' => 'جلد ڈاکٹر کو دکھائیں اگر...'],
                'intro' => [
                    'en' => 'Trust your feelings as a parent. These are reasons not to wait. In an emergency, go to the nearest hospital or call your local emergency number.',
                    'ur' => 'ایک والد یا والدہ کے طور پر اپنے احساس پر بھروسہ کریں۔ یہ انتظار نہ کرنے کی وجوہات ہیں۔ ہنگامی صورت میں قریبی ہسپتال جائیں یا اپنے علاقے کا ایمرجنسی نمبر ملائیں۔',
                ],
                'items' => [
                    ['title' => ['en' => 'Your child loses skills', 'ur' => 'بچہ پہلے کی سیکھی باتیں بھولنے لگے'], 'text' => ['en' => 'For example stops using words or gestures they used before.', 'ur' => 'مثلاً وہ الفاظ یا اشارے استعمال کرنا چھوڑ دے جو پہلے کرتا تھا۔']],
                    ['title' => ['en' => 'Seizures or staring spells', 'ur' => 'دورے یا گم صم ہو جانا'], 'text' => ['en' => 'Shaking, sudden falls, or times when your child does not respond and seems "switched off".', 'ur' => 'جھٹکے، اچانک گرنا، یا ایسے وقت جب بچہ جواب نہ دے اور "بند" سا لگے۔']],
                    ['title' => ['en' => 'Hurting themselves or others', 'ur' => 'اپنے آپ کو یا دوسروں کو نقصان پہنچانا'], 'text' => ['en' => 'Head banging that leaves marks, biting, or aggression that is getting worse. Pain is often the hidden cause.', 'ur' => 'سر پٹخنا جس سے نشان پڑیں، کاٹنا، یا بڑھتی ہوئی جارحیت۔ اکثر چھپی ہوئی وجہ درد ہوتا ہے۔']],
                    ['title' => ['en' => 'Pain or tummy trouble', 'ur' => 'درد یا پیٹ کی تکلیف'], 'text' => ['en' => 'Constant tummy pain, very hard stools, vomiting, weight loss, or not eating or drinking. A child who cannot say it hurts may show it by being upset.', 'ur' => 'مسلسل پیٹ درد، بہت سخت پاخانہ، الٹی، وزن کم ہونا، یا کھانا پینا چھوڑ دینا۔ جو بچہ بتا نہیں سکتا کہ درد ہے وہ پریشان ہو کر دکھا سکتا ہے۔']],
                    ['title' => ['en' => 'Very little sleep for many days', 'ur' => 'کئی دن بہت کم نیند'], 'text' => ['en' => 'Or sudden big changes in mood, eating or behaviour.', 'ur' => 'یا مزاج، کھانے یا رویے میں اچانک بڑی تبدیلی۔']],
                    ['title' => ['en' => 'Choking or swallowing objects', 'ur' => 'گلے میں پھنسنا یا چیزیں نگل لینا'], 'text' => ['en' => 'Needs urgent care. Batteries and magnets are especially dangerous.', 'ur' => 'فوری علاج کی ضرورت ہے۔ بیٹری اور مقناطیس خاص طور پر خطرناک ہیں۔']],
                ],
            ],
        ],
    ],
];
