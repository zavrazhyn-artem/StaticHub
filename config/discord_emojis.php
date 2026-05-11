<?php

/**
 * Discord custom emoji IDs.
 *
 * Defaults are the production bot's emoji IDs — used by the deployed k8s app
 * without per-pod env overrides. Local development overrides via .env when
 * pointing at a dev/test bot with different emoji IDs.
 */
return [
    'classes' => [
        'death_knight' => env('DISCORD_EMOJI_CLASS_DEATH_KNIGHT', '1495381369944150026'),
        'demon_hunter' => env('DISCORD_EMOJI_CLASS_DEMON_HUNTER', '1495381372074721351'),
        'druid'        => env('DISCORD_EMOJI_CLASS_DRUID',        '1495381374117351454'),
        'evoker'       => env('DISCORD_EMOJI_CLASS_EVOKER',       '1495381376579539045'),
        'hunter'       => env('DISCORD_EMOJI_CLASS_HUNTER',       '1495381377971916851'),
        'mage'         => env('DISCORD_EMOJI_CLASS_MAGE',         '1495386698480222289'),
        'monk'         => env('DISCORD_EMOJI_CLASS_MONK',         '1495381380937285692'),
        'paladin'      => env('DISCORD_EMOJI_CLASS_PALADIN',      '1495381382149701682'),
        'priest'       => env('DISCORD_EMOJI_CLASS_PRIEST',       '1495381383059865621'),
        'rogue'        => env('DISCORD_EMOJI_CLASS_ROGUE',        '1495381384812826726'),
        'shaman'       => env('DISCORD_EMOJI_CLASS_SHAMAN',       '1495381386499067964'),
        'warlock'      => env('DISCORD_EMOJI_CLASS_WARLOCK',      '1495381387694440458'),
        'warrior'      => env('DISCORD_EMOJI_CLASS_WARRIOR',      '1495381388814454814'),
    ],

    'roles' => [
        'tank'  => env('DISCORD_EMOJI_ROLE_TANK',  '1495381288339898419'),
        'heal'  => env('DISCORD_EMOJI_ROLE_HEAL',  '1495381276033552445'),
        'melee' => env('DISCORD_EMOJI_ROLE_MELEE', '1495381285101899848'),
        'range' => env('DISCORD_EMOJI_ROLE_RANGE', '1495381286938869810'),
    ],

    'rsvp' => [
        'present'   => env('DISCORD_EMOJI_RSVP_PRESENT',   '1495381331570327602'),
        'late'      => env('DISCORD_EMOJI_RSVP_LATE',      '1495381328470999203'),
        'tentative' => env('DISCORD_EMOJI_RSVP_TENTATIVE', '1495381334259138600'),
        'absent'    => env('DISCORD_EMOJI_RSVP_ABSENT',    '1495381324641341581'),
        'pending'   => env('DISCORD_EMOJI_RSVP_PENDING',   '1495381330098258050'),
        'bench'     => env('DISCORD_EMOJI_RSVP_BENCH',     '1495381326373851278'),
    ],
];
