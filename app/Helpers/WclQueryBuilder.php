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
              fights { id name difficulty kill bossPercentage startTime endTime }
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
              deaths: table(dataType: Deaths, fightIDs: $fightIds, killType: Encounters)
              interrupts: table(dataType: Interrupts, fightIDs: $fightIds, killType: Encounters)
              damageTaken: table(dataType: DamageTaken, fightIDs: $fightIds, killType: Encounters)
              casts: table(dataType: Casts, fightIDs: $fightIds, killType: Encounters, viewBy: Ability)
              damageDone: table(dataType: DamageDone, fightIDs: $fightIds, killType: Encounters)
              healing: table(dataType: Healing, fightIDs: $fightIds, killType: Encounters)
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
