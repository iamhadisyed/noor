<?php

/**
 * Ads (Google AdMob). OFF until the owner has an AdMob account and fills these in the .env.
 *
 * The rules this app follows, because it is made for children:
 *  - ads are shown only to parents on the FREE plan, and only in the parent app (never in the child's app, never inside a lesson or a game);
 *  - no personalised ads, and every request is marked "child-directed" (COPPA / Google Families policy);
 *  - Pro, the demo accounts and the owner never see ads.
 * The apps read this through `GET /me` (`ads`), which is null whenever no ad may be shown.
 */
return [
    'enabled' => (bool) env('ADS_ENABLED', false),
    'units' => [
        'android_app_id' => env('ADMOB_ANDROID_APP_ID'),
        'ios_app_id' => env('ADMOB_IOS_APP_ID'),
        'banner_android' => env('ADMOB_BANNER_ANDROID'),
        'banner_ios' => env('ADMOB_BANNER_IOS'),
    ],
];
