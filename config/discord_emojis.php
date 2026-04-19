<?php

/**
 * Discord custom emoji IDs.
 *
 * Defaults below are the dev/test bot's emoji IDs. On other environments (e.g. prod),
 * override via .env because the bot there has different emojis with different IDs.
 */
return [
    'classes' => [
        'death_knight' => env('DISCORD_EMOJI_CLASS_DEATH_KNIGHT', '1495360361606545438'),
        'demon_hunter' => env('DISCORD_EMOJI_CLASS_DEMON_HUNTER', '1495360362713972796'),
        'druid'        => env('DISCORD_EMOJI_CLASS_DRUID',        '1495360364018139237'),
        'evoker'       => env('DISCORD_EMOJI_CLASS_EVOKER',       '1495360365083758754'),
        'hunter'       => env('DISCORD_EMOJI_CLASS_HUNTER',       '1495360367046692995'),
        'mage'         => env('DISCORD_EMOJI_CLASS_MAGE',         '1495360368673947768'),
        'monk'         => env('DISCORD_EMOJI_CLASS_MONK',         '1495360370100142151'),
        'paladin'      => env('DISCORD_EMOJI_CLASS_PALADIN',      '1495360371345588416'),
        'priest'       => env('DISCORD_EMOJI_CLASS_PRIEST',       '1495360372905869472'),
        'rogue'        => env('DISCORD_EMOJI_CLASS_ROGUE',        '1495360374239662192'),
        'shaman'       => env('DISCORD_EMOJI_CLASS_SHAMAN',       '1495360376215306280'),
        'warlock'      => env('DISCORD_EMOJI_CLASS_WARLOCK',      '1495360377410687137'),
        'warrior'      => env('DISCORD_EMOJI_CLASS_WARRIOR',      '1495360378920767488'),
    ],

    'roles' => [
        'tank'  => env('DISCORD_EMOJI_ROLE_TANK',  '1495366423848157337'),
        'heal'  => env('DISCORD_EMOJI_ROLE_HEAL',  '1495366420098449428'),
        'melee' => env('DISCORD_EMOJI_ROLE_MELEE', '1495366421318865078'),
        'range' => env('DISCORD_EMOJI_ROLE_RANGE', '1495366422480556133'),
    ],

    'rsvp' => [
        'present'   => env('DISCORD_EMOJI_RSVP_PRESENT',   '1495360289418514512'),
        'late'      => env('DISCORD_EMOJI_RSVP_LATE',      '1495360292266442875'),
        'tentative' => env('DISCORD_EMOJI_RSVP_TENTATIVE', '1495360287950246030'),
        'absent'    => env('DISCORD_EMOJI_RSVP_ABSENT',    '1495360293780328538'),
        'pending'   => env('DISCORD_EMOJI_RSVP_PENDING',   '1495360291343433758'),
        'bench'     => env('DISCORD_EMOJI_RSVP_BENCH',     '1495365373472669839'),
    ],
];
