<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Player Major Cooldowns (full coverage — all 40 specs)
|--------------------------------------------------------------------------
|
| Keyed by spec slug: "{class}.{spec}" lowercase, no spaces.
|
| type:
|   personal — self-only defensive
|   external — cast on a single ally
|   raid     — affects multiple allies / raid-wide
|   utility  — non-defensive raid utility (interrupts excluded; immunities,
|              speed buffs, lust, etc.)
|
| requires_talent: true marks CDs that need a talent selection. Future
| character_cooldown_overrides override this per-character so officers can
| toggle off CDs the player isn't actually running.
|
| icon: Blizzard icon filename (no .jpg). Resolved via the zamimg CDN at render.
*/

return [

    /* ───────────── Death Knight ───────────── */

    'deathknight.blood' => [
        ['spell_id' => 55233,  'name' => 'Vampiric Blood',          'icon' => 'spell_shadow_lifedrain',          'cooldown' => 90,  'type' => 'personal'],
        ['spell_id' => 48707,  'name' => 'Anti-Magic Shell',        'icon' => 'spell_shadow_antimagicshell',     'cooldown' => 60,  'type' => 'personal'],
        ['spell_id' => 51052,  'name' => 'Anti-Magic Zone',         'icon' => 'spell_deathknight_antimagiczone', 'cooldown' => 120, 'type' => 'raid', 'requires_talent' => true],
        ['spell_id' => 49028,  'name' => 'Dancing Rune Weapon',     'icon' => 'inv_sword_07',                    'cooldown' => 120, 'type' => 'personal'],
        ['spell_id' => 194679, 'name' => 'Rune Tap',                'icon' => 'spell_deathknight_runetap',       'cooldown' => 25,  'type' => 'personal'],
        ['spell_id' => 219809, 'name' => 'Tombstone',               'icon' => 'achievement_boss_kelthuzad_01',   'cooldown' => 60,  'type' => 'personal', 'requires_talent' => true],
        ['spell_id' => 49039,  'name' => 'Lichborne',               'icon' => 'spell_shadow_raisedead',          'cooldown' => 120, 'type' => 'personal', 'requires_talent' => true],
    ],

    'deathknight.frost' => [
        ['spell_id' => 48707,  'name' => 'Anti-Magic Shell',        'icon' => 'spell_shadow_antimagicshell',     'cooldown' => 60,  'type' => 'personal'],
        ['spell_id' => 48792,  'name' => 'Icebound Fortitude',      'icon' => 'spell_deathknight_iceboundfortitude','cooldown' => 180, 'type' => 'personal'],
        ['spell_id' => 51052,  'name' => 'Anti-Magic Zone',         'icon' => 'spell_deathknight_antimagiczone', 'cooldown' => 120, 'type' => 'raid', 'requires_talent' => true],
        ['spell_id' => 49039,  'name' => 'Lichborne',               'icon' => 'spell_shadow_raisedead',          'cooldown' => 120, 'type' => 'personal', 'requires_talent' => true],
        ['spell_id' => 327574, 'name' => 'Sacrificial Pact',        'icon' => 'spell_shadow_corpseexplode',      'cooldown' => 120, 'type' => 'personal'],
    ],

    'deathknight.unholy' => [
        ['spell_id' => 48707,  'name' => 'Anti-Magic Shell',        'icon' => 'spell_shadow_antimagicshell',     'cooldown' => 60,  'type' => 'personal'],
        ['spell_id' => 48792,  'name' => 'Icebound Fortitude',      'icon' => 'spell_deathknight_iceboundfortitude','cooldown' => 180, 'type' => 'personal'],
        ['spell_id' => 51052,  'name' => 'Anti-Magic Zone',         'icon' => 'spell_deathknight_antimagiczone', 'cooldown' => 120, 'type' => 'raid', 'requires_talent' => true],
        ['spell_id' => 49039,  'name' => 'Lichborne',               'icon' => 'spell_shadow_raisedead',          'cooldown' => 120, 'type' => 'personal', 'requires_talent' => true],
        ['spell_id' => 327574, 'name' => 'Sacrificial Pact',        'icon' => 'spell_shadow_corpseexplode',      'cooldown' => 120, 'type' => 'personal'],
    ],

    /* ───────────── Demon Hunter ───────────── */

    'demonhunter.vengeance' => [
        ['spell_id' => 187827, 'name' => 'Metamorphosis',           'icon' => 'ability_demonhunter_metamorphasistank','cooldown' => 240, 'type' => 'personal'],
        ['spell_id' => 263648, 'name' => 'Soul Barrier',            'icon' => 'ability_demonhunter_soulcleave2', 'cooldown' => 30,  'type' => 'personal', 'requires_talent' => true],
        ['spell_id' => 196718, 'name' => 'Darkness',                'icon' => 'ability_demonhunter_darkness',    'cooldown' => 180, 'type' => 'raid'],
        ['spell_id' => 204021, 'name' => 'Fiery Brand',             'icon' => 'ability_demonhunter_fierybrand',  'cooldown' => 60,  'type' => 'personal'],
        ['spell_id' => 198589, 'name' => 'Blur',                    'icon' => 'ability_demonhunter_blur',        'cooldown' => 60,  'type' => 'personal'],
    ],

    'demonhunter.havoc' => [
        ['spell_id' => 198589, 'name' => 'Blur',                    'icon' => 'ability_demonhunter_blur',        'cooldown' => 60,  'type' => 'personal'],
        ['spell_id' => 196555, 'name' => 'Netherwalk',              'icon' => 'spell_warlock_demonsoul',         'cooldown' => 180, 'type' => 'personal', 'requires_talent' => true],
        ['spell_id' => 196718, 'name' => 'Darkness',                'icon' => 'ability_demonhunter_darkness',    'cooldown' => 180, 'type' => 'raid', 'requires_talent' => true],
    ],

    // New Midnight 12.0 third Demon Hunter spec (ranged DPS) — placeholder until
    // the full ability list is confirmed. Inherits common DH defensives for now.
    'demonhunter.devourer' => [
        ['spell_id' => 198589, 'name' => 'Blur',                    'icon' => 'ability_demonhunter_blur',        'cooldown' => 60,  'type' => 'personal'],
        ['spell_id' => 196718, 'name' => 'Darkness',                'icon' => 'ability_demonhunter_darkness',    'cooldown' => 180, 'type' => 'raid', 'requires_talent' => true],
    ],

    /* ───────────── Druid ───────────── */

    'druid.guardian' => [
        ['spell_id' => 61336,  'name' => 'Survival Instincts',      'icon' => 'ability_druid_tigersroar',        'cooldown' => 180, 'type' => 'personal'],
        ['spell_id' => 22812,  'name' => 'Barkskin',                'icon' => 'spell_nature_stoneclawtotem',     'cooldown' => 60,  'type' => 'personal'],
        ['spell_id' => 102558, 'name' => 'Incarnation: Guardian of Ursoc','icon' => 'spell_druid_incarnation',   'cooldown' => 180, 'type' => 'personal', 'requires_talent' => true],
        ['spell_id' => 200851, 'name' => "Rage of the Sleeper",     'icon' => 'inv_inscription_80_warscroll_battleshout','cooldown' => 120, 'type' => 'personal', 'requires_talent' => true],
        ['spell_id' => 102342, 'name' => 'Ironbark',                'icon' => 'spell_druid_ironbark',            'cooldown' => 60,  'type' => 'external'],
        ['spell_id' => 106898, 'name' => 'Stampeding Roar',         'icon' => 'spell_druid_stampedingroar_cat',  'cooldown' => 120, 'type' => 'utility'],
    ],

    'druid.restoration' => [
        ['spell_id' => 740,    'name' => 'Tranquility',             'icon' => 'spell_nature_tranquility',        'cooldown' => 180, 'type' => 'raid'],
        ['spell_id' => 33891,  'name' => 'Incarnation: Tree of Life','icon' => 'ability_druid_improvedtreeform', 'cooldown' => 180, 'type' => 'personal', 'requires_talent' => true],
        ['spell_id' => 102342, 'name' => 'Ironbark',                'icon' => 'spell_druid_ironbark',            'cooldown' => 60,  'type' => 'external'],
        ['spell_id' => 22812,  'name' => 'Barkskin',                'icon' => 'spell_nature_stoneclawtotem',     'cooldown' => 60,  'type' => 'personal'],
        ['spell_id' => 61336,  'name' => 'Survival Instincts',      'icon' => 'ability_druid_tigersroar',        'cooldown' => 180, 'type' => 'personal'],
        ['spell_id' => 29166,  'name' => 'Innervate',               'icon' => 'spell_nature_lightning',          'cooldown' => 180, 'type' => 'utility'],
        ['spell_id' => 106898, 'name' => 'Stampeding Roar',         'icon' => 'spell_druid_stampedingroar_cat',  'cooldown' => 120, 'type' => 'utility', 'requires_talent' => true],
    ],

    'druid.balance' => [
        ['spell_id' => 22812,  'name' => 'Barkskin',                'icon' => 'spell_nature_stoneclawtotem',     'cooldown' => 60,  'type' => 'personal'],
        ['spell_id' => 108238, 'name' => 'Renewal',                 'icon' => 'spell_nature_natureblessing',     'cooldown' => 90,  'type' => 'personal', 'requires_talent' => true],
        ['spell_id' => 740,    'name' => 'Tranquility',             'icon' => 'spell_nature_tranquility',        'cooldown' => 180, 'type' => 'raid', 'requires_talent' => true],
        ['spell_id' => 29166,  'name' => 'Innervate',               'icon' => 'spell_nature_lightning',          'cooldown' => 180, 'type' => 'utility', 'requires_talent' => true],
        ['spell_id' => 106898, 'name' => 'Stampeding Roar',         'icon' => 'spell_druid_stampedingroar_cat',  'cooldown' => 120, 'type' => 'utility', 'requires_talent' => true],
    ],

    'druid.feral' => [
        ['spell_id' => 22812,  'name' => 'Barkskin',                'icon' => 'spell_nature_stoneclawtotem',     'cooldown' => 60,  'type' => 'personal'],
        ['spell_id' => 61336,  'name' => 'Survival Instincts',      'icon' => 'ability_druid_tigersroar',        'cooldown' => 180, 'type' => 'personal'],
        ['spell_id' => 108238, 'name' => 'Renewal',                 'icon' => 'spell_nature_natureblessing',     'cooldown' => 90,  'type' => 'personal', 'requires_talent' => true],
        ['spell_id' => 106898, 'name' => 'Stampeding Roar',         'icon' => 'spell_druid_stampedingroar_cat',  'cooldown' => 120, 'type' => 'utility'],
    ],

    /* ───────────── Evoker ───────────── */

    'evoker.preservation' => [
        ['spell_id' => 363534, 'name' => 'Rewind',                  'icon' => 'ability_evoker_rewind',           'cooldown' => 240, 'type' => 'raid'],
        ['spell_id' => 374227, 'name' => 'Zephyr',                  'icon' => 'ability_evoker_zephyr',           'cooldown' => 120, 'type' => 'raid'],
        ['spell_id' => 370960, 'name' => 'Emerald Communion',       'icon' => 'ability_evoker_emeraldcommunion', 'cooldown' => 180, 'type' => 'personal'],
        ['spell_id' => 357170, 'name' => 'Time Dilation',           'icon' => 'ability_evoker_timedilation',     'cooldown' => 60,  'type' => 'external'],
        ['spell_id' => 363916, 'name' => 'Obsidian Scales',         'icon' => 'ability_evoker_obsidianscales',   'cooldown' => 150, 'type' => 'personal'],
    ],

    'evoker.devastation' => [
        ['spell_id' => 363916, 'name' => 'Obsidian Scales',         'icon' => 'ability_evoker_obsidianscales',   'cooldown' => 150, 'type' => 'personal'],
        ['spell_id' => 374348, 'name' => 'Renewing Blaze',          'icon' => 'ability_evoker_renewingblaze',    'cooldown' => 90,  'type' => 'personal'],
        ['spell_id' => 363534, 'name' => 'Rewind',                  'icon' => 'ability_evoker_rewind',           'cooldown' => 240, 'type' => 'personal', 'requires_talent' => true],
        ['spell_id' => 360995, 'name' => 'Verdant Embrace',         'icon' => 'ability_evoker_rescue',           'cooldown' => 24,  'type' => 'personal', 'requires_talent' => true],
        ['spell_id' => 370960, 'name' => 'Emerald Communion',       'icon' => 'ability_evoker_emeraldcommunion', 'cooldown' => 180, 'type' => 'personal', 'requires_talent' => true],
        ['spell_id' => 374227, 'name' => 'Zephyr',                  'icon' => 'ability_evoker_zephyr',           'cooldown' => 120, 'type' => 'raid', 'requires_talent' => true],
        ['spell_id' => 357170, 'name' => 'Time Dilation',           'icon' => 'ability_evoker_timedilation',     'cooldown' => 60,  'type' => 'external', 'requires_talent' => true],
        ['spell_id' => 357210, 'name' => 'Deep Breath',             'icon' => 'ability_evoker_deepbreath',       'cooldown' => 120, 'type' => 'utility'],
    ],

    'evoker.augmentation' => [
        ['spell_id' => 363916, 'name' => 'Obsidian Scales',         'icon' => 'ability_evoker_obsidianscales',   'cooldown' => 150, 'type' => 'personal'],
        ['spell_id' => 374348, 'name' => 'Renewing Blaze',          'icon' => 'ability_evoker_renewingblaze',    'cooldown' => 90,  'type' => 'personal'],
        ['spell_id' => 360995, 'name' => 'Verdant Embrace',         'icon' => 'ability_evoker_rescue',           'cooldown' => 24,  'type' => 'personal', 'requires_talent' => true],
        ['spell_id' => 370960, 'name' => 'Emerald Communion',       'icon' => 'ability_evoker_emeraldcommunion', 'cooldown' => 180, 'type' => 'personal', 'requires_talent' => true],
        ['spell_id' => 374227, 'name' => 'Zephyr',                  'icon' => 'ability_evoker_zephyr',           'cooldown' => 120, 'type' => 'raid', 'requires_talent' => true],
        ['spell_id' => 357170, 'name' => 'Time Dilation',           'icon' => 'ability_evoker_timedilation',     'cooldown' => 60,  'type' => 'external', 'requires_talent' => true],
        ['spell_id' => 408092, 'name' => 'Time Stop',               'icon' => 'ability_evoker_timestop',         'cooldown' => 90,  'type' => 'external', 'requires_talent' => true],
    ],

    /* ───────────── Hunter ───────────── */

    'hunter.beastmastery' => [
        ['spell_id' => 186265, 'name' => 'Aspect of the Turtle',    'icon' => 'ability_hunter_pet_turtle',       'cooldown' => 180, 'type' => 'personal'],
        ['spell_id' => 109304, 'name' => 'Exhilaration',            'icon' => 'ability_hunter_onewithnature',    'cooldown' => 120, 'type' => 'personal'],
        ['spell_id' => 264735, 'name' => 'Survival of the Fittest', 'icon' => 'ability_hunter_pet_assist',       'cooldown' => 180, 'type' => 'personal'],
        ['spell_id' => 53480,  'name' => 'Roar of Sacrifice',       'icon' => 'ability_hunter_pet_assist',       'cooldown' => 60,  'type' => 'external', 'requires_talent' => true],
    ],

    'hunter.marksmanship' => [
        ['spell_id' => 186265, 'name' => 'Aspect of the Turtle',    'icon' => 'ability_hunter_pet_turtle',       'cooldown' => 180, 'type' => 'personal'],
        ['spell_id' => 109304, 'name' => 'Exhilaration',            'icon' => 'ability_hunter_onewithnature',    'cooldown' => 120, 'type' => 'personal'],
        ['spell_id' => 264735, 'name' => 'Survival of the Fittest', 'icon' => 'ability_hunter_pet_assist',       'cooldown' => 180, 'type' => 'personal'],
    ],

    'hunter.survival' => [
        ['spell_id' => 186265, 'name' => 'Aspect of the Turtle',    'icon' => 'ability_hunter_pet_turtle',       'cooldown' => 180, 'type' => 'personal'],
        ['spell_id' => 109304, 'name' => 'Exhilaration',            'icon' => 'ability_hunter_onewithnature',    'cooldown' => 120, 'type' => 'personal'],
        ['spell_id' => 264735, 'name' => 'Survival of the Fittest', 'icon' => 'ability_hunter_pet_assist',       'cooldown' => 180, 'type' => 'personal'],
        ['spell_id' => 53480,  'name' => 'Roar of Sacrifice',       'icon' => 'ability_hunter_pet_assist',       'cooldown' => 60,  'type' => 'external', 'requires_talent' => true],
    ],

    /* ───────────── Mage ───────────── */

    'mage.arcane' => [
        ['spell_id' => 45438,  'name' => 'Ice Block',               'icon' => 'spell_frost_frost',               'cooldown' => 240, 'type' => 'personal'],
        ['spell_id' => 110959, 'name' => 'Greater Invisibility',    'icon' => 'ability_mage_greaterinvisibility','cooldown' => 120, 'type' => 'personal'],
        ['spell_id' => 55342,  'name' => 'Mirror Image',            'icon' => 'spell_magic_lesserinvisibilty',   'cooldown' => 120, 'type' => 'personal'],
        ['spell_id' => 235313, 'name' => 'Blazing Barrier',         'icon' => 'ability_mage_moltenshields',      'cooldown' => 25,  'type' => 'personal'],
        ['spell_id' => 414660, 'name' => 'Mass Barrier',            'icon' => 'spell_magearmor',                 'cooldown' => 120, 'type' => 'raid', 'requires_talent' => true],
    ],

    'mage.fire' => [
        ['spell_id' => 45438,  'name' => 'Ice Block',               'icon' => 'spell_frost_frost',               'cooldown' => 240, 'type' => 'personal'],
        ['spell_id' => 110959, 'name' => 'Greater Invisibility',    'icon' => 'ability_mage_greaterinvisibility','cooldown' => 120, 'type' => 'personal'],
        ['spell_id' => 55342,  'name' => 'Mirror Image',            'icon' => 'spell_magic_lesserinvisibilty',   'cooldown' => 120, 'type' => 'personal'],
        ['spell_id' => 235313, 'name' => 'Blazing Barrier',         'icon' => 'ability_mage_moltenshields',      'cooldown' => 25,  'type' => 'personal'],
        ['spell_id' => 414660, 'name' => 'Mass Barrier',            'icon' => 'spell_magearmor',                 'cooldown' => 120, 'type' => 'raid', 'requires_talent' => true],
    ],

    'mage.frost' => [
        ['spell_id' => 45438,  'name' => 'Ice Block',               'icon' => 'spell_frost_frost',               'cooldown' => 240, 'type' => 'personal'],
        ['spell_id' => 110959, 'name' => 'Greater Invisibility',    'icon' => 'ability_mage_greaterinvisibility','cooldown' => 120, 'type' => 'personal'],
        ['spell_id' => 55342,  'name' => 'Mirror Image',            'icon' => 'spell_magic_lesserinvisibilty',   'cooldown' => 120, 'type' => 'personal'],
        ['spell_id' => 11426,  'name' => 'Ice Barrier',             'icon' => 'spell_ice_lament',                'cooldown' => 25,  'type' => 'personal'],
        ['spell_id' => 414660, 'name' => 'Mass Barrier',            'icon' => 'spell_magearmor',                 'cooldown' => 120, 'type' => 'raid', 'requires_talent' => true],
    ],

    /* ───────────── Monk ───────────── */

    'monk.brewmaster' => [
        ['spell_id' => 115203, 'name' => 'Fortifying Brew',         'icon' => 'ability_monk_fortifyingale_new',  'cooldown' => 180, 'type' => 'personal'],
        ['spell_id' => 322507, 'name' => 'Celestial Brew',          'icon' => 'ability_monk_ironskinbrew',       'cooldown' => 60,  'type' => 'personal'],
        ['spell_id' => 122470, 'name' => 'Touch of Karma',          'icon' => 'ability_monk_touchofkarma',       'cooldown' => 90,  'type' => 'personal'],
        ['spell_id' => 122278, 'name' => 'Dampen Harm',             'icon' => 'ability_monk_dampenharm',         'cooldown' => 120, 'type' => 'personal'],
        ['spell_id' => 115176, 'name' => 'Zen Meditation',          'icon' => 'ability_monk_zenmeditation',      'cooldown' => 300, 'type' => 'personal'],
    ],

    'monk.mistweaver' => [
        ['spell_id' => 115310, 'name' => 'Revival',                 'icon' => 'spell_monk_revival',              'cooldown' => 180, 'type' => 'raid'],
        ['spell_id' => 116849, 'name' => 'Life Cocoon',             'icon' => 'ability_monk_chicocoon',          'cooldown' => 120, 'type' => 'external'],
        ['spell_id' => 322118, 'name' => "Invoke Yu'lon",            'icon' => 'ability_monk_dragonkick',         'cooldown' => 180, 'type' => 'raid', 'requires_talent' => true],
        ['spell_id' => 122470, 'name' => 'Touch of Karma',          'icon' => 'ability_monk_touchofkarma',       'cooldown' => 90,  'type' => 'personal'],
        ['spell_id' => 122278, 'name' => 'Dampen Harm',             'icon' => 'ability_monk_dampenharm',         'cooldown' => 120, 'type' => 'personal', 'requires_talent' => true],
        ['spell_id' => 115203, 'name' => 'Fortifying Brew',         'icon' => 'ability_monk_fortifyingale_new',  'cooldown' => 180, 'type' => 'personal'],
    ],

    'monk.windwalker' => [
        ['spell_id' => 122470, 'name' => 'Touch of Karma',          'icon' => 'ability_monk_touchofkarma',       'cooldown' => 90,  'type' => 'personal'],
        ['spell_id' => 122278, 'name' => 'Dampen Harm',             'icon' => 'ability_monk_dampenharm',         'cooldown' => 120, 'type' => 'personal', 'requires_talent' => true],
        ['spell_id' => 115203, 'name' => 'Fortifying Brew',         'icon' => 'ability_monk_fortifyingale_new',  'cooldown' => 180, 'type' => 'personal'],
        ['spell_id' => 122783, 'name' => 'Diffuse Magic',           'icon' => 'spell_monk_diffusemagic',         'cooldown' => 90,  'type' => 'personal', 'requires_talent' => true],
    ],

    /* ───────────── Paladin ───────────── */

    'paladin.protection' => [
        ['spell_id' => 31850,  'name' => 'Ardent Defender',         'icon' => 'spell_holy_ardentdefender',       'cooldown' => 120, 'type' => 'personal'],
        ['spell_id' => 86659,  'name' => 'Guardian of Ancient Kings','icon' => 'spell_holy_heroism',             'cooldown' => 300, 'type' => 'personal'],
        ['spell_id' => 633,    'name' => 'Lay on Hands',            'icon' => 'spell_holy_layonhands',           'cooldown' => 600, 'type' => 'external'],
        ['spell_id' => 6940,   'name' => 'Blessing of Sacrifice',   'icon' => 'spell_holy_sealofsacrifice',      'cooldown' => 120, 'type' => 'external'],
        ['spell_id' => 1022,   'name' => 'Blessing of Protection',  'icon' => 'spell_holy_sealofprotection',     'cooldown' => 300, 'type' => 'external'],
        ['spell_id' => 498,    'name' => 'Divine Protection',       'icon' => 'spell_holy_divineprotection',     'cooldown' => 60,  'type' => 'personal'],
    ],

    'paladin.holy' => [
        ['spell_id' => 633,    'name' => 'Lay on Hands',            'icon' => 'spell_holy_layonhands',           'cooldown' => 600, 'type' => 'external'],
        ['spell_id' => 1022,   'name' => 'Blessing of Protection',  'icon' => 'spell_holy_sealofprotection',     'cooldown' => 300, 'type' => 'external'],
        ['spell_id' => 6940,   'name' => 'Blessing of Sacrifice',   'icon' => 'spell_holy_sealofsacrifice',      'cooldown' => 120, 'type' => 'external'],
        ['spell_id' => 31821,  'name' => 'Aura Mastery',            'icon' => 'spell_holy_auramastery',          'cooldown' => 180, 'type' => 'raid'],
        ['spell_id' => 31884,  'name' => 'Avenging Wrath',          'icon' => 'spell_holy_avenginewrath',        'cooldown' => 120, 'type' => 'personal'],
        ['spell_id' => 498,    'name' => 'Divine Protection',       'icon' => 'spell_holy_divineprotection',     'cooldown' => 60,  'type' => 'personal'],
    ],

    'paladin.retribution' => [
        ['spell_id' => 642,    'name' => 'Divine Shield',           'icon' => 'spell_holy_divineshield',         'cooldown' => 300, 'type' => 'personal'],
        ['spell_id' => 633,    'name' => 'Lay on Hands',            'icon' => 'spell_holy_layonhands',           'cooldown' => 600, 'type' => 'external'],
        ['spell_id' => 6940,   'name' => 'Blessing of Sacrifice',   'icon' => 'spell_holy_sealofsacrifice',      'cooldown' => 120, 'type' => 'external'],
        ['spell_id' => 1022,   'name' => 'Blessing of Protection',  'icon' => 'spell_holy_sealofprotection',     'cooldown' => 300, 'type' => 'external'],
        ['spell_id' => 498,    'name' => 'Divine Protection',       'icon' => 'spell_holy_divineprotection',     'cooldown' => 60,  'type' => 'personal'],
    ],

    /* ───────────── Priest ───────────── */

    'priest.discipline' => [
        ['spell_id' => 33206,  'name' => 'Pain Suppression',        'icon' => 'spell_holy_painsupression',       'cooldown' => 180, 'type' => 'external'],
        ['spell_id' => 62618,  'name' => 'Power Word: Barrier',     'icon' => 'spell_holy_powerwordbarrier',     'cooldown' => 180, 'type' => 'raid'],
        ['spell_id' => 47536,  'name' => 'Rapture',                 'icon' => 'spell_holy_rapture',              'cooldown' => 90,  'type' => 'raid'],
        ['spell_id' => 19236,  'name' => 'Desperate Prayer',        'icon' => 'spell_holy_testoffaith',          'cooldown' => 90,  'type' => 'personal'],
        ['spell_id' => 271466, 'name' => 'Luminous Barrier',        'icon' => 'ability_priest_powerwordbarrier', 'cooldown' => 180, 'type' => 'raid', 'requires_talent' => true],
        ['spell_id' => 586,    'name' => 'Fade',                    'icon' => 'spell_magic_lesserinvisibilty',   'cooldown' => 30,  'type' => 'personal'],
    ],

    'priest.holy' => [
        ['spell_id' => 47788,  'name' => 'Guardian Spirit',         'icon' => 'spell_holy_guardianspirit',       'cooldown' => 180, 'type' => 'external'],
        ['spell_id' => 64843,  'name' => 'Divine Hymn',             'icon' => 'spell_holy_divinehymn',           'cooldown' => 180, 'type' => 'raid'],
        ['spell_id' => 265202, 'name' => "Holy Word: Salvation",    'icon' => 'ability_priest_archangel',        'cooldown' => 720, 'type' => 'raid', 'requires_talent' => true],
        ['spell_id' => 19236,  'name' => 'Desperate Prayer',        'icon' => 'spell_holy_testoffaith',          'cooldown' => 90,  'type' => 'personal'],
        ['spell_id' => 62618,  'name' => 'Power Word: Barrier',     'icon' => 'spell_holy_powerwordbarrier',     'cooldown' => 180, 'type' => 'raid', 'requires_talent' => true],
        ['spell_id' => 586,    'name' => 'Fade',                    'icon' => 'spell_magic_lesserinvisibilty',   'cooldown' => 30,  'type' => 'personal'],
    ],

    'priest.shadow' => [
        ['spell_id' => 47585,  'name' => 'Dispersion',              'icon' => 'spell_shadow_dispersion',         'cooldown' => 120, 'type' => 'personal'],
        ['spell_id' => 19236,  'name' => 'Desperate Prayer',        'icon' => 'spell_holy_testoffaith',          'cooldown' => 90,  'type' => 'personal'],
        ['spell_id' => 586,    'name' => 'Fade',                    'icon' => 'spell_magic_lesserinvisibilty',   'cooldown' => 30,  'type' => 'personal'],
        ['spell_id' => 10060,  'name' => 'Power Infusion',          'icon' => 'spell_holy_powerinfusion',        'cooldown' => 120, 'type' => 'utility'],
        ['spell_id' => 15286,  'name' => 'Vampiric Embrace',        'icon' => 'spell_shadow_unsummonbuilding',   'cooldown' => 120, 'type' => 'raid'],
    ],

    /* ───────────── Rogue ───────────── */

    'rogue.assassination' => [
        ['spell_id' => 31224,  'name' => 'Cloak of Shadows',        'icon' => 'spell_shadow_nethercloak',        'cooldown' => 120, 'type' => 'personal'],
        ['spell_id' => 5277,   'name' => 'Evasion',                 'icon' => 'spell_shadow_shadowward',         'cooldown' => 120, 'type' => 'personal'],
        ['spell_id' => 1966,   'name' => 'Feint',                   'icon' => 'ability_rogue_feint',             'cooldown' => 15,  'type' => 'personal'],
        ['spell_id' => 1856,   'name' => 'Vanish',                  'icon' => 'ability_vanish',                  'cooldown' => 120, 'type' => 'personal'],
    ],

    'rogue.outlaw' => [
        ['spell_id' => 31224,  'name' => 'Cloak of Shadows',        'icon' => 'spell_shadow_nethercloak',        'cooldown' => 120, 'type' => 'personal'],
        ['spell_id' => 5277,   'name' => 'Evasion',                 'icon' => 'spell_shadow_shadowward',         'cooldown' => 120, 'type' => 'personal'],
        ['spell_id' => 1966,   'name' => 'Feint',                   'icon' => 'ability_rogue_feint',             'cooldown' => 15,  'type' => 'personal'],
        ['spell_id' => 1856,   'name' => 'Vanish',                  'icon' => 'ability_vanish',                  'cooldown' => 120, 'type' => 'personal'],
    ],

    'rogue.subtlety' => [
        ['spell_id' => 31224,  'name' => 'Cloak of Shadows',        'icon' => 'spell_shadow_nethercloak',        'cooldown' => 120, 'type' => 'personal'],
        ['spell_id' => 5277,   'name' => 'Evasion',                 'icon' => 'spell_shadow_shadowward',         'cooldown' => 120, 'type' => 'personal'],
        ['spell_id' => 1966,   'name' => 'Feint',                   'icon' => 'ability_rogue_feint',             'cooldown' => 15,  'type' => 'personal'],
        ['spell_id' => 1856,   'name' => 'Vanish',                  'icon' => 'ability_vanish',                  'cooldown' => 120, 'type' => 'personal'],
    ],

    /* ───────────── Shaman ───────────── */

    'shaman.restoration' => [
        ['spell_id' => 108280, 'name' => 'Healing Tide Totem',      'icon' => 'ability_shaman_healingtide',      'cooldown' => 180, 'type' => 'raid'],
        ['spell_id' => 98008,  'name' => 'Spirit Link Totem',       'icon' => 'spell_shaman_spiritlink',         'cooldown' => 180, 'type' => 'raid'],
        ['spell_id' => 207399, 'name' => 'Ancestral Protection Totem','icon' => 'spell_nature_reincarnation',    'cooldown' => 300, 'type' => 'raid', 'requires_talent' => true],
        ['spell_id' => 108281, 'name' => 'Ancestral Guidance',      'icon' => 'ability_shaman_ancestralguidance','cooldown' => 120, 'type' => 'raid', 'requires_talent' => true],
        ['spell_id' => 108271, 'name' => 'Astral Shift',            'icon' => 'ability_shaman_astralshift',      'cooldown' => 90,  'type' => 'personal'],
        ['spell_id' => 32182,  'name' => 'Heroism',                 'icon' => 'ability_shaman_heroism',          'cooldown' => 300, 'type' => 'utility'],
    ],

    'shaman.elemental' => [
        ['spell_id' => 108271, 'name' => 'Astral Shift',            'icon' => 'ability_shaman_astralshift',      'cooldown' => 90,  'type' => 'personal'],
        ['spell_id' => 32182,  'name' => 'Heroism',                 'icon' => 'ability_shaman_heroism',          'cooldown' => 300, 'type' => 'utility'],
        ['spell_id' => 108281, 'name' => 'Ancestral Guidance',      'icon' => 'ability_shaman_ancestralguidance','cooldown' => 120, 'type' => 'raid', 'requires_talent' => true],
        ['spell_id' => 108280, 'name' => 'Healing Tide Totem',      'icon' => 'ability_shaman_healingtide',      'cooldown' => 180, 'type' => 'raid', 'requires_talent' => true],
    ],

    'shaman.enhancement' => [
        ['spell_id' => 108271, 'name' => 'Astral Shift',            'icon' => 'ability_shaman_astralshift',      'cooldown' => 90,  'type' => 'personal'],
        ['spell_id' => 32182,  'name' => 'Heroism',                 'icon' => 'ability_shaman_heroism',          'cooldown' => 300, 'type' => 'utility'],
        ['spell_id' => 108281, 'name' => 'Ancestral Guidance',      'icon' => 'ability_shaman_ancestralguidance','cooldown' => 120, 'type' => 'raid', 'requires_talent' => true],
        ['spell_id' => 108280, 'name' => 'Healing Tide Totem',      'icon' => 'ability_shaman_healingtide',      'cooldown' => 180, 'type' => 'raid', 'requires_talent' => true],
    ],

    /* ───────────── Warlock ───────────── */

    'warlock.affliction' => [
        ['spell_id' => 104773, 'name' => 'Unending Resolve',        'icon' => 'spell_shadow_demonictactics',     'cooldown' => 180, 'type' => 'personal'],
        ['spell_id' => 108416, 'name' => 'Dark Pact',               'icon' => 'spell_shadow_deathpact',          'cooldown' => 60,  'type' => 'personal', 'requires_talent' => true],
        ['spell_id' => 6201,   'name' => 'Healthstone (create)',    'icon' => 'inv_stone_04',                    'cooldown' => 60,  'type' => 'utility'],
        ['spell_id' => 20707,  'name' => 'Soulstone',               'icon' => 'spell_shadow_soulgem',            'cooldown' => 600, 'type' => 'utility'],
    ],

    'warlock.demonology' => [
        ['spell_id' => 104773, 'name' => 'Unending Resolve',        'icon' => 'spell_shadow_demonictactics',     'cooldown' => 180, 'type' => 'personal'],
        ['spell_id' => 108416, 'name' => 'Dark Pact',               'icon' => 'spell_shadow_deathpact',          'cooldown' => 60,  'type' => 'personal', 'requires_talent' => true],
        ['spell_id' => 6201,   'name' => 'Healthstone (create)',    'icon' => 'inv_stone_04',                    'cooldown' => 60,  'type' => 'utility'],
        ['spell_id' => 20707,  'name' => 'Soulstone',               'icon' => 'spell_shadow_soulgem',            'cooldown' => 600, 'type' => 'utility'],
    ],

    'warlock.destruction' => [
        ['spell_id' => 104773, 'name' => 'Unending Resolve',        'icon' => 'spell_shadow_demonictactics',     'cooldown' => 180, 'type' => 'personal'],
        ['spell_id' => 108416, 'name' => 'Dark Pact',               'icon' => 'spell_shadow_deathpact',          'cooldown' => 60,  'type' => 'personal', 'requires_talent' => true],
        ['spell_id' => 6201,   'name' => 'Healthstone (create)',    'icon' => 'inv_stone_04',                    'cooldown' => 60,  'type' => 'utility'],
        ['spell_id' => 20707,  'name' => 'Soulstone',               'icon' => 'spell_shadow_soulgem',            'cooldown' => 600, 'type' => 'utility'],
    ],

    /* ───────────── Warrior ───────────── */

    'warrior.protection' => [
        ['spell_id' => 871,    'name' => 'Shield Wall',             'icon' => 'ability_warrior_shieldwall',      'cooldown' => 180, 'type' => 'personal'],
        ['spell_id' => 12975,  'name' => 'Last Stand',              'icon' => 'spell_holy_ashestoashes',         'cooldown' => 180, 'type' => 'personal'],
        ['spell_id' => 23920,  'name' => 'Spell Reflection',        'icon' => 'ability_warrior_shieldreflection','cooldown' => 25,  'type' => 'personal'],
        ['spell_id' => 97462,  'name' => 'Rallying Cry',            'icon' => 'ability_warrior_rallyingcry',     'cooldown' => 180, 'type' => 'raid'],
        ['spell_id' => 118038, 'name' => 'Die by the Sword',        'icon' => 'ability_warrior_challange',       'cooldown' => 120, 'type' => 'personal'],
    ],

    'warrior.arms' => [
        ['spell_id' => 118038, 'name' => 'Die by the Sword',        'icon' => 'ability_warrior_challange',       'cooldown' => 120, 'type' => 'personal'],
        ['spell_id' => 23920,  'name' => 'Spell Reflection',        'icon' => 'ability_warrior_shieldreflection','cooldown' => 25,  'type' => 'personal'],
        ['spell_id' => 97462,  'name' => 'Rallying Cry',            'icon' => 'ability_warrior_rallyingcry',     'cooldown' => 180, 'type' => 'raid'],
        ['spell_id' => 18499,  'name' => 'Berserker Rage',          'icon' => 'spell_nature_ancestralguardian',  'cooldown' => 60,  'type' => 'personal'],
    ],

    'warrior.fury' => [
        ['spell_id' => 184364, 'name' => 'Enraged Regeneration',    'icon' => 'ability_warrior_focusedrage',     'cooldown' => 120, 'type' => 'personal'],
        ['spell_id' => 23920,  'name' => 'Spell Reflection',        'icon' => 'ability_warrior_shieldreflection','cooldown' => 25,  'type' => 'personal'],
        ['spell_id' => 97462,  'name' => 'Rallying Cry',            'icon' => 'ability_warrior_rallyingcry',     'cooldown' => 180, 'type' => 'raid'],
        ['spell_id' => 18499,  'name' => 'Berserker Rage',          'icon' => 'spell_nature_ancestralguardian',  'cooldown' => 60,  'type' => 'personal'],
    ],

];
