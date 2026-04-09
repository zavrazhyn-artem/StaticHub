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
}
