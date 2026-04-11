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
              masterData { actors(type: "Player") { id name subType } }
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
              deaths:      table(dataType: Deaths,      fightIDs: $fightIds, killType: Encounters)
              damageTaken: table(dataType: DamageTaken, fightIDs: $fightIds, killType: Encounters)
              casts:       table(dataType: Casts,       fightIDs: $fightIds, killType: Encounters, viewBy: Ability)
              damageDone:  table(dataType: DamageDone,  fightIDs: $fightIds, killType: Encounters)
              healing:     table(dataType: Healing,     fightIDs: $fightIds, killType: Encounters)
              dispels:     table(dataType: Dispels,     fightIDs: $fightIds, killType: Encounters)
              buffs:       table(dataType: Buffs,       fightIDs: $fightIds, killType: Encounters)
              debuffs:     table(dataType: Debuffs,     fightIDs: $fightIds, killType: Encounters)
              resources:   table(dataType: Resources,   fightIDs: $fightIds, killType: Encounters)
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

    /**
     * Fetch fights + master data (actors + abilities) for a single report.
     * Used to locate the boss actor ID for a given encounter before querying cast events.
     */
    public static function buildReportFightsAndActorsQuery(): string
    {
        return <<<'GQL'
        query ($reportId: String!) {
          reportData {
            report(code: $reportId) {
              title
              phases {
                encounterID
                phases { id name isIntermission }
              }
              fights {
                id name difficulty kill encounterID
                startTime endTime
                phaseTransitions { id startTime }
              }
              masterData {
                actors { id name type subType gameID }
                abilities { gameID name icon type }
              }
            }
          }
        }
GQL;
    }

    /**
     * Fetch enemy cast events for a specific fight, filtered to boss sources.
     * WCL events are paginated — caller should loop using nextPageTimestamp.
     */
    public static function buildBossCastEventsQuery(): string
    {
        return self::buildBossEventStreamQuery('Casts');
    }

    /**
     * Fetch enemy buff events (apply/refresh/remove) — used to compute boss
     * ability duration by matching apply ↔ remove pairs.
     */
    public static function buildBossBuffEventsQuery(): string
    {
        return self::buildBossEventStreamQuery('Buffs');
    }

    public static function buildBossDebuffEventsQuery(): string
    {
        return self::buildBossEventStreamQuery('Debuffs');
    }

    private static function buildBossEventStreamQuery(string $dataType): string
    {
        return <<<GQL
        query (\$reportId: String!, \$fightId: Int!, \$startTime: Float!, \$endTime: Float!) {
          reportData {
            report(code: \$reportId) {
              events(
                dataType: {$dataType}
                hostilityType: Enemies
                fightIDs: [\$fightId]
                startTime: \$startTime
                endTime: \$endTime
                limit: 10000
              ) {
                data
                nextPageTimestamp
              }
            }
          }
        }
GQL;
    }
}
