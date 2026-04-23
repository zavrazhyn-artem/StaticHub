<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\FeedbackPost;
use App\Models\FeedbackSubtask;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeds the public feedback board with the initial roadmap:
 * 3 "Planned" items (what we're going to build) and 8 "Done" items
 * (what already shipped). Idempotent — matches by title, skips duplicates.
 *
 * Run:  php artisan db:seed --class=FeedbackRoadmapSeeder
 */
class FeedbackRoadmapSeeder extends Seeder
{
    public function run(): void
    {
        $author = $this->pickAuthor();
        if ($author === null) {
            $this->command->error('No users found — cannot seed feedback posts. Create at least one user first.');
            return;
        }

        foreach ($this->posts() as $data) {
            $existing = FeedbackPost::query()->where('title', $data['title'])->first();
            if ($existing) {
                $this->command->line("Skipped (already exists): {$data['title']}");
                continue;
            }

            /** @var FeedbackPost $post */
            $post = FeedbackPost::query()->create([
                'user_id' => $author->id,
                'title'   => $data['title'],
                'body'    => $data['body'],
                'status'  => $data['status'],
                'tag'     => $data['tag'],
            ]);

            foreach (($data['subtasks'] ?? []) as $i => $subtaskTitle) {
                FeedbackSubtask::query()->create([
                    'feedback_post_id' => $post->id,
                    'title'            => $subtaskTitle,
                    // Done posts → all subtasks shipped. Planned posts → todo.
                    'status'           => $data['status'] === 'done' ? 'done' : 'todo',
                    'sort_order'       => $i,
                ]);
            }

            $post->update(['subtasks_count' => count($data['subtasks'] ?? [])]);
            $this->command->info("Created: {$data['title']} ({$data['status']}, {$post->subtasks_count} subtasks)");
        }
    }

    /**
     * Prefer a user that owns a static (likely admin) so posts have a plausible author.
     * Falls back to the first user, then null.
     */
    private function pickAuthor(): ?User
    {
        return User::query()->whereHas('ownedStatics')->first()
            ?? User::query()->orderBy('id')->first();
    }

    /**
     * @return array<int, array{title: string, body: string, status: string, tag: string, subtasks?: array<int, string>}>
     */
    private function posts(): array
    {
        return [
            // ─────────────────────────────────────────────────────────────
            // PLANNED — next up
            // ─────────────────────────────────────────────────────────────
            [
                'title'  => 'Gear: wishlist, loot history та BiS-трекінг',
                'status' => 'planned',
                'tag'    => 'gear',
                'body'   => "Повний gear-менеджмент: персональні wishlist'и на релевантні рейд-айтеми, історія лута по рейду/босу/тижню, трекінг BiS з імпортом з Raidbots/simc. Ціль — одна сторінка де бачиш свій поточний гір, куди рости, що ти отримав за прогрес і кому можна віддати айтем по trade-window.",
                'subtasks' => [
                    "Wishlist per character (ручний ввід + import з Raidbots)",
                    "Loot history: хто / що / з якого боса / якого тижня",
                    "BiS-трекінг з actual expansion progression",
                    "UI порівняння: current vs wishlist vs BiS",
                    "Loot priority rules для статіка (attendance × roster_status)",
                    "Trade-ассистент: «цей айтем трейдабельний гравцям X, Y, Z»",
                ],
            ],
            [
                'title'  => 'WoW-аддон + Desktop Sync App (нульова ручна синхронізація)',
                'status' => 'planned',
                'tag'    => 'character',
                'body'   => "In-game аддон (Lua) + desktop-компаньйон, який читає WoW SavedVariables і пушить зміни в Blastr. Ціль — прибрати ручний ввід даних: гір, кулдауни, логи, RSVP — усе автоматом після кожного логіну/пулу/трейду. Secure pairing через одноразовий PIN.",
                'subtasks' => [
                    "Addon: експорт гіра, спеків, route/coords, cooldowns",
                    "Addon: slash-команди (/blastr rsvp, /blastr plan, /blastr loot)",
                    "Addon: overlay календаря рейдів в грі",
                    "Desktop app: file-watcher над SavedVariables",
                    "Desktop app: auto-upload WCL-логів після кожного пулу",
                    "Desktop app: live combat-log стрім (CD-tracking в реальному часі)",
                    "Auth: безпечне pairing addon ↔ Blastr-аккаунт (PIN / QR-код)",
                    "Auto-install / update flow для desktop-компаньйона",
                ],
            ],
            [
                'title'  => 'AI co-pilot у Boss Planner — генерація та ревью CD-планів',
                'status' => 'planned',
                'tag'    => 'boss_planner',
                'body'   => "AI-помічник що знає ростер + боса + тактику і автоматично пропонує оптимальне розкидання CD по механіках. Може «пояснити» чужий план, знайти проблемні місця, порівняти з community-best-practice. Ціль — зменшити час на planning з 2 годин до 15 хвилин.",
                'subtasks' => [
                    "Auto-generate CD-plan зі складу + boss timeline",
                    "Explain mode: «чому цей CD поставлено тут»",
                    "Plan review: AI-критика «що можна покращити»",
                    "Порівняння з community-планами (wago.io import)",
                    "Warning-система: short-CD ability використана 1 раз замість 2",
                    "Voice-brief: готовий текст для рейд-ліда в Discord",
                    "Drag-to-suggest: «пересунути цей кулдаун на секунду X»",
                ],
            ],

            // ─────────────────────────────────────────────────────────────
            // DONE — shipped
            // ─────────────────────────────────────────────────────────────
            [
                'title'  => 'Raid events, розклад і RSVP',
                'status' => 'done',
                'tag'    => 'raid_events',
                'body'   => "Повноцінна календар-система рейдів з RSVP через Discord + web. Auto-створення подій за розкладом статіка, timezone-aware, трекінг attendance з ручними оверайдами.",
                'subtasks' => [
                    "Створення рейд-подій вручну або auto з розкладу",
                    "RSVP через web + Discord slash-команди",
                    "Attendance-трекінг з можливістю оверайдів",
                    "Timezone-aware розклад (TZ статіка)",
                    "Push нотифікацій про рейд у Discord-канал",
                    "Календар-view з фільтрами (my / guild / все)",
                ],
            ],
            [
                'title'  => 'Unified Roster з gear audit',
                'status' => 'done',
                'tag'    => 'roster',
                'body'   => "Єдиний погляд на весь склад: усі гравці + їх персонажі з класом/спеком/гіром. Композиція рейду з попередженнями про role/class gaps. Ієрархія core/bench/backup.",
                'subtasks' => [
                    "Unified view усіх персонажів статіка",
                    "Gear audit — якість екіпу по слотах",
                    "Composition warnings (role/class gaps)",
                    "Статуси core / bench / backup з drag-and-drop",
                    "Per-spec view для multispec-гравців",
                    "Vault preview — що дадуть на наступному ресеті",
                ],
            ],
            [
                'title'  => 'Battle.net OAuth та синхронізація персонажів',
                'status' => 'done',
                'tag'    => 'character',
                'body'   => "Логін через Battle.net, автоматичний pull персонажів з Blizzard API, вибір main/alt per-static, кастомний аватар і hide_battletag для приватності.",
                'subtasks' => [
                    "Battle.net OAuth login через Socialite",
                    "Автосинхронізація персонажів з Bnet API",
                    "Вибір main / alt для кожного статіка окремо",
                    "Hide BattleTag + публічна назва = нік головного перса",
                    "Character refresh по запиту і шедулером",
                    "Raider.io інтеграція (M+ rating, прогрес)",
                ],
            ],
            [
                'title'  => 'AI-аналіз рейд-логів (Gemini + WCL)',
                'status' => 'done',
                'tag'    => 'ai_analysis',
                'body'   => "5-хвильовий pipeline AI-аналізу кожного WCL-логу: тактичні блоки по фазах боса, розбір помилок, персональні відгуки, переклад українською. Запускається автоматично після upload'у.",
                'subtasks' => [
                    "WCL log ingestion (GraphQL + caching)",
                    "Wave 1: tactical blocks по фазах боса",
                    "Wave 2: critical mistakes identification",
                    "Wave 3: personal feedback per player",
                    "Wave 4: cross-log trend analysis",
                    "Wave 5: переклад блоків на українську",
                    "UI перегляду: колапсовані блоки + highlight важливого",
                ],
            ],
            [
                'title'  => 'Boss Planner — мапа + timeline CD-плану',
                'status' => 'done',
                'tag'    => 'boss_planner',
                'body'   => "Інтерактивний редактор плану на боса: drag-and-drop мапа з позиціями гравців, abilities на timeline по фазах. Public-sharing через посилання. Базова версія готова — AI-помічник і cooldown-колізії в плані.",
                'subtasks' => [
                    "Drag-and-drop мапа з класово-колористими маркерами",
                    "Timeline-редактор abilities по фазах боса",
                    "Seeding-парсер боса з YAML-тактик",
                    "Public sharing через read-only link",
                    "Kick/interrupt координація",
                    "Icons для маркерів (skull/cross/circle/etc.)",
                ],
            ],
            [
                'title'  => 'Treasury: транзакції, баланс і consumables planning',
                'status' => 'done',
                'tag'    => 'treasury',
                'body'   => "Скарбниця статіка з повним трекінгом gold-транзакцій, weekly tax per player, плануванням витратних матеріалів (flask/food/augment), consumables settings per static.",
                'subtasks' => [
                    "Transactions з comments + категоріями",
                    "Guild tax per player (weekly)",
                    "Consumables planner (flask/food/augment)",
                    "Recipe-ingredient calculator",
                    "Snapshot цін з AH (auction integration)",
                    "Treasury balance reconciliation",
                ],
            ],
            [
                'title'  => 'Discord інтеграція: webhooks, automations, bot-команди',
                'status' => 'done',
                'tag'    => 'discord',
                'body'   => "Повноцінна Discord-інтеграція: webhook-и для рейд-оголошень, slash-команди бота, persistent RSVP-message у каналі, автоматизації (reminder за X хвилин до рейду, Discord-сповіщення про нові логи).",
                'subtasks' => [
                    "Webhook-и для публікації рейд-подій",
                    "Slash-команди бота (verify, RSVP, тощо)",
                    "Persistent RSVP-message у Discord-каналі",
                    "Auto-reminder за X хвилин до рейду",
                    "Сповіщення про новий AI-аналіз у канал",
                    "Discord account linking на профіль",
                ],
            ],
            [
                'title'  => 'Створення статіка, інвайти та ролі',
                'status' => 'done',
                'tag'    => 'admin',
                'body'   => "Повний lifecycle статіка: create wizard, unique-token-invite-посилання, ролі (leader/officer/member), onboarding нових гравців зі синком персонажів, transfer ownership.",
                'subtasks' => [
                    "Create static wizard (realm, guild, server, tz)",
                    "Auto-refresh invite token з expiry",
                    "Onboarding new-player flow з char-sync",
                    "Role-based access (leader / officer / member)",
                    "Transfer ownership на іншого юзера",
                    "Settings: розклад, Discord, consumables defaults",
                ],
            ],
        ];
    }
}
