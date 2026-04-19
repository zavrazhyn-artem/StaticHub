<?php

declare(strict_types=1);

namespace App\Helpers;

class WclQueryBuilder
{
    public static function buildFightsQuery(): string
    {
        return <<<'GQL'
        query ($reportId: String!) {
          reportData {
            report(code: $reportId) {
              title
              phases { encounterID separatesWipes phases { id name isIntermission } }
              fights {
                id name difficulty kill encounterID
                bossPercentage fightPercentage
                startTime endTime averageItemLevel
                lastPhase lastPhaseIsIntermission
                phaseTransitions { id startTime }
                friendlyPlayers friendlySpecs friendlyItemLevels
              }
              masterData { actors { id name type subType } }
            }
          }
        }
GQL;
    }

    public static function buildTablesQuery(): string
    {
        return <<<'GQL'
        query ($reportId: String!, $fightIds: [Int]!) {
          reportData {
            report(code: $reportId) {
              deaths:       table(dataType: Deaths,      fightIDs: $fightIds, killType: Encounters)
              damageTaken:  table(dataType: DamageTaken, fightIDs: $fightIds, killType: Encounters)
              casts:        table(dataType: Casts,       fightIDs: $fightIds, killType: Encounters, viewBy: Ability)
              damageDone:   table(dataType: DamageDone,  fightIDs: $fightIds, killType: Encounters)
              healing:      table(dataType: Healing,     fightIDs: $fightIds, killType: Encounters)
              dispels:      table(dataType: Dispels,     fightIDs: $fightIds, killType: Encounters)
              interrupts:   table(dataType: Interrupts,  fightIDs: $fightIds, killType: Encounters)
              buffs:        table(dataType: Buffs,       fightIDs: $fightIds, killType: Encounters)
              debuffs:      table(dataType: Debuffs,     fightIDs: $fightIds, killType: Encounters)
              resources:    table(dataType: Resources,   fightIDs: $fightIds, killType: Encounters)
              playerDetails(fightIDs: $fightIds, includeCombatantInfo: true)
            }
          }
        }
GQL;
    }

    public static function buildRankingsQuery(): string
    {
        return <<<'GQL'
        query ($reportId: String!, $fightIds: [Int]!) {
          reportData {
            report(code: $reportId) {
              rankings(fightIDs: $fightIds, compare: Parses)
            }
          }
        }
GQL;
    }

    /**
     * Fetch guild info by ID.
     */
    public static function buildGuildInfoByIdQuery(): string
    {
        return <<<'GQL'
        query ($guildId: Int!) {
          guildData {
            guild(id: $guildId) {
              id
              name
              server { name slug region { slug compactName } }
            }
          }
        }
GQL;
    }

    /**
     * Fetch guild info by name, server slug, and region.
     */
    public static function buildGuildInfoByNameQuery(): string
    {
        return <<<'GQL'
        query ($name: String!, $serverSlug: String!, $serverRegion: String!) {
          guildData {
            guild(name: $name, serverSlug: $serverSlug, serverRegion: $serverRegion) {
              id
              name
              server { name slug region { slug compactName } }
            }
          }
        }
GQL;
    }

    /**
     * Fetch recent reports for a guild within a time range.
     */
    public static function buildGuildReportsQuery(): string
    {
        return <<<'GQL'
        query ($guildId: Int!, $startTime: Float, $endTime: Float, $limit: Int) {
          reportData {
            reports(guildID: $guildId, startTime: $startTime, endTime: $endTime, limit: $limit) {
              data {
                code
                title
                startTime
                endTime
              }
            }
          }
        }
GQL;
    }

    /**
     * Fetch debuff events for a specific ability to analyze stack timing.
     * Returns applydebuff, applydebuffstack, refreshdebuff, removedebuff events.
     */
    public static function buildDebuffStackEventsQuery(): string
    {
        return <<<'GQL'
        query ($reportId: String!, $fightIds: [Int]!, $abilityId: Float!, $startTime: Float) {
          reportData {
            report(code: $reportId) {
              events(dataType: Debuffs, fightIDs: $fightIds, killType: Encounters, abilityID: $abilityId, startTime: $startTime, limit: 2000) {
                data
                nextPageTimestamp
              }
            }
          }
        }
GQL;
    }

    /**
     * Per-encounter player stats — casts/buffs/dispels/interrupts/consumables/debuffs
     * scoped to a specific set of fight IDs (one boss).
     */
    public static function buildPerEncounterStatsQuery(): string
    {
        return <<<'GQL'
        query ($reportId: String!, $fightIds: [Int]!) {
          reportData {
            report(code: $reportId) {
              casts:        table(dataType: Casts, fightIDs: $fightIds, killType: Encounters, viewBy: Ability)
              buffs:        table(dataType: Buffs, fightIDs: $fightIds, killType: Encounters)
              dispels:      table(dataType: Dispels, fightIDs: $fightIds, killType: Encounters)
              interrupts:   table(dataType: Interrupts, fightIDs: $fightIds, killType: Encounters)
              debuffs:      table(dataType: Debuffs, fightIDs: $fightIds, killType: Encounters)
              damageDone:   table(dataType: DamageDone, fightIDs: $fightIds, killType: Encounters)
              damageTaken:  table(dataType: DamageTaken, fightIDs: $fightIds, killType: Encounters)
              healing:      table(dataType: Healing, fightIDs: $fightIds, killType: Encounters)
              deaths:       table(dataType: Deaths, fightIDs: $fightIds, killType: Encounters)
            }
          }
        }
GQL;
    }

    /**
     * Cooldown timing — fetch cast events for specific spell IDs (player major CDs).
     * Uses filterExpression to scope to a list of ability IDs.
     * One query per encounter, returns timestamps + sourceID per cast.
     */
    public static function buildCooldownEventsQuery(): string
    {
        return <<<'GQL'
        query ($reportId: String!, $fightIds: [Int]!, $filterExpression: String!) {
          reportData {
            report(code: $reportId) {
              events(dataType: Casts, fightIDs: $fightIds, killType: Encounters, filterExpression: $filterExpression, limit: 5000) {
                data
              }
            }
          }
        }
GQL;
    }

    /**
     * Per-boss damage_done by target — for proper per-boss add identification.
     */
    public static function buildBossAddsQuery(): string
    {
        return <<<'GQL'
        query ($reportId: String!, $fightIds: [Int]!) {
          reportData {
            report(code: $reportId) {
              addsDamage: table(dataType: DamageDone, fightIDs: $fightIds, killType: Encounters)
            }
          }
        }
GQL;
    }

    /**
     * Targeted damage_taken lookup for a specific ability.
     * Returns per-player damage taken for exactly that ability (not top-N filtered).
     */
    public static function buildTargetedDamageTakenQuery(): string
    {
        return <<<'GQL'
        query ($reportId: String!, $fightIds: [Int]!, $abilityId: Float!) {
          reportData {
            report(code: $reportId) {
              damageTaken: table(dataType: DamageTaken, fightIDs: $fightIds, killType: Encounters, abilityID: $abilityId)
            }
          }
        }
GQL;
    }

    /**
     * Fetch NPC death events (enemy deaths — adds/orbs).
     */
    public static function buildEnemyDeathEventsQuery(): string
    {
        return <<<'GQL'
        query ($reportId: String!, $fightIds: [Int]!, $startTime: Float) {
          reportData {
            report(code: $reportId) {
              events(dataType: Deaths, fightIDs: $fightIds, killType: Encounters, hostilityType: Enemies, startTime: $startTime, limit: 2000) {
                data
                nextPageTimestamp
              }
            }
          }
        }
GQL;
    }

    /**
     * Fetch cast events for a specific ability (useful for boss cast coords or interrupt analysis).
     * For enemy casts (bosses), pass hostilityType: "Enemies".
     */
    public static function buildCastEventsQuery(): string
    {
        return <<<'GQL'
        query ($reportId: String!, $fightIds: [Int]!, $abilityId: Float!, $startTime: Float, $hostilityType: HostilityType) {
          reportData {
            report(code: $reportId) {
              events(dataType: Casts, fightIDs: $fightIds, killType: Encounters, abilityID: $abilityId, includeResources: true, startTime: $startTime, hostilityType: $hostilityType, limit: 2000) {
                data
                nextPageTimestamp
              }
            }
          }
        }
GQL;
    }

    /**
     * Fetch enemy buff events (e.g. Nexus Shield on clones).
     */
    public static function buildEnemyBuffEventsQuery(): string
    {
        return <<<'GQL'
        query ($reportId: String!, $fightIds: [Int]!, $abilityId: Float!, $startTime: Float) {
          reportData {
            report(code: $reportId) {
              events(dataType: Buffs, fightIDs: $fightIds, killType: Encounters, hostilityType: Enemies, abilityID: $abilityId, startTime: $startTime, limit: 2000) {
                data
                nextPageTimestamp
              }
            }
          }
        }
GQL;
    }

    /**
     * Fetch all summon events in the fights (boss-spawned adds).
     */
    public static function buildSummonEventsQuery(): string
    {
        return <<<'GQL'
        query ($reportId: String!, $fightIds: [Int]!, $startTime: Float) {
          reportData {
            report(code: $reportId) {
              events(dataType: Summons, fightIDs: $fightIds, killType: Encounters, startTime: $startTime, limit: 2000) {
                data
                nextPageTimestamp
              }
            }
          }
        }
GQL;
    }

    public static function buildCharacterParsesQuery(): string
    {
        return <<<'GQL'
            query ($name: String, $serverSlug: String, $serverRegion: String) {
                characterData {
                    character(name: $name, serverSlug: $serverSlug, serverRegion: $serverRegion) {
                        zoneRankings
                    }
                }
            }
GQL;
    }
}
