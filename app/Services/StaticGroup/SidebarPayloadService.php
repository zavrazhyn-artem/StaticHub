<?php

declare(strict_types=1);

namespace App\Services\StaticGroup;

use App\Enums\StaticGroup\Role;
use App\Models\Event;
use App\Models\StaticGroup;
use App\Models\User;
use App\Policies\Concerns\ResolvesStaticRole;
use Illuminate\Support\Facades\Gate;

class SidebarPayloadService
{
    use ResolvesStaticRole;

    public function __construct(
        private readonly StaticProgressionService           $progressionService,
        private readonly \App\Services\Raid\EventPayloadService $eventPayloadService,
    ) {}

    private const CLASS_HEX = [
        'Warrior' => '#C69B6D', 'Paladin' => '#F48CBA', 'Hunter' => '#ABD473',
        'Rogue' => '#FFF468', 'Priest' => '#FFFFFF', 'Death Knight' => '#C41F3B',
        'Shaman' => '#0070DD', 'Mage' => '#3FC7EB', 'Warlock' => '#8788EE',
        'Monk' => '#00FF98', 'Druid' => '#FF7C0A', 'Demon Hunter' => '#A330C9',
        'Evoker' => '#33937F',
    ];

    /**
     * Build the data payload for the LayoutShell Vue component.
     * Called from layouts/app.blade.php.
     */
    public function build(?User $user, ?StaticGroup $static, bool $isOnboarding, bool $isBare): array
    {
        $payload = [
            'csrf'  => csrf_token(),
            'lang'  => $this->buildLang(),
            'auth'  => $this->buildAuth($user, $static, $isBare),
            'ghost' => $this->buildGhost(),
            'sidebar' => null,
            'initialNotifications' => [],
        ];

        if (! $user || ! $static || $isOnboarding || $isBare) {
            return $payload;
        }

        $role = $this->getUserRoleInStatic($user, $static) ?? Role::Member;
        $mainCharacter = $user->getMainCharacterForStatic($static->id);

        $payload['sidebar'] = [
            'tagline' => 'RAID HUB',
            'dashboardUrl' => route('dashboard'),
            'staticInfo' => $this->buildStaticInfo($static, $role, $user),
            'primaryNav' => $this->buildPrimaryNav($static),
            'accountNav' => $this->buildAccountNav($user),
            'user'       => $this->buildUser($user, $static, $role, $mainCharacter),
            'footer'     => $this->buildFooter(),
        ];

        $payload['initialNotifications'] = $this->buildInitialNotifications($user, $static, $isBare);

        return $payload;
    }

    private function buildAuth(?User $user, ?StaticGroup $static, bool $isBare): ?array
    {
        if (! $user) {
            $signInHref = route('battlenet.redirect');
            $currentPath = request()->getRequestUri();
            if ($isBare && $currentPath) {
                $signInHref .= '?redirect_to=' . urlencode($currentPath);
            }
            return ['signInUrl' => $signInHref];
        }

        // On bare pages (feedback/roadmap) fully-onboarded users get a "Back to
        // Blastr" escape hatch — anyone less than that would just be bounced
        // back to onboarding.
        $canReturnToBlastr = $isBare
            && $static
            && User::query()->hasMainCharacter($user->id);

        if ($canReturnToBlastr) {
            return ['backToBlastrUrl' => route('dashboard')];
        }

        return null;
    }

    private function buildLang(): array
    {
        $localeMap = [
            'en' => ['country' => 'GB', 'label' => 'English'],
            'uk' => ['country' => 'UA', 'label' => 'Українська'],
        ];

        $locales = collect(glob(base_path('lang/*.json')))
            ->map(fn($f) => pathinfo($f, PATHINFO_FILENAME))
            ->filter(fn($l) => isset($localeMap[$l]))
            ->map(fn($l) => [
                'code'    => $l,
                'country' => $localeMap[$l]['country'],
                'label'   => $localeMap[$l]['label'],
            ])
            ->values()
            ->all();

        return [
            'current'   => app()->getLocale(),
            'locales'   => $locales,
            'switchUrl' => route('language.switch'),
        ];
    }

    private function buildGhost(): ?array
    {
        $ghost = app(\App\Services\Ghost\GhostModeService::class);
        if (! $ghost->isActive()) {
            return null;
        }
        $static = StaticGroup::withoutGlobalScopes()->find($ghost->currentStaticId());

        return [
            'active'     => true,
            'staticName' => $static?->name ?? '—',
            'exitUrl'    => route('admin.ghost.exit'),
        ];
    }

    private function buildStaticInfo(StaticGroup $static, Role $role, User $user): array
    {
        $nextRaid = Event::query()->nextRaid($static->id);

        return [
            'name'             => $static->name,
            'progressionLabel' => $this->buildProgressionLabel($static),
            'canInvite'        => in_array($role, [Role::Leader, Role::Officer], true),
            'inviteUrl'        => route('statics.invite.generate'),
            'nextRaid'         => $nextRaid ? [
                'timestamp'   => $nextRaid->start_time->timestamp,
                'href'        => route('schedule.event.show', $nextRaid->id),
                'rsvpContext' => $this->eventPayloadService->buildRsvpModalPayload($nextRaid, $user),
            ] : null,
        ];
    }

    /**
     * Pick the highest difficulty in which the static has at least one kill,
     * then count bosses cleared at that difficulty. Falls back to "Normal 0/N"
     * when the progression record is empty.
     *
     * Public so the DashboardService can render the same label in its topbar.
     */
    public function buildProgressionLabel(StaticGroup $static): string
    {
        $progression = $this->progressionService->getProgression($static->id);
        $rank  = ['LFR' => 1, 'N' => 2, 'H' => 3, 'M' => 4];
        $names = ['LFR' => 'LFR', 'N' => 'Normal', 'H' => 'Heroic', 'M' => 'Mythic'];

        $total = 0;
        $bossRanks = [];

        foreach ($progression as $instance) {
            foreach ($instance['bosses'] as $boss) {
                $total++;
                $diff = $boss['difficulty'] ?? null;
                if ($diff && isset($rank[$diff])) {
                    $bossRanks[] = $rank[$diff];
                }
            }
        }

        if ($bossRanks === []) {
            return sprintf('%s 0/%d', __('Normal'), $total);
        }

        $highestRank = max($bossRanks);
        $highestKey  = array_search($highestRank, $rank, true);
        $count       = count(array_filter($bossRanks, fn ($r) => $r === $highestRank));

        return sprintf('%s %d/%d', __($names[$highestKey]), $count, $total);
    }

    /**
     * @return array<int, array{label:string,icon:string,href:string,active:bool,badge?:array}>
     */
    private function buildPrimaryNav(StaticGroup $static): array
    {
        return [
            $this->navItem(__('Dashboard'),    'space_dashboard',     route('dashboard'),              'dashboard'),
            $this->navItem(__('Roster'),       'groups',              route('statics.roster'),         'statics.roster'),
            $this->navItem(__('Schedule'),     'event',               route('schedule.index'),         'schedule.*'),
            $this->navItem(__('Boss Planner'), 'sports_esports',      route('statics.boss-planner'),   'statics.boss-planner*'),
            $this->navItem(__('Treasury'),     'savings',             route('statics.treasury'),       'statics.treasury'),
            $this->navItem(__('Intelligence'), 'analytics',           route('statics.logs.index'),     'statics.logs.*'),
            $this->navItem(__('Gear'),         'shield',              route('statics.gear'),           'statics.gear'),
        ];
    }

    /**
     * @return array<int, array{label:string,icon:string,href:string,active:bool,badge?:array}>
     */
    private function buildAccountNav(User $user): array
    {
        $settingsItem = $this->navItem(__('Settings'), 'settings', route('statics.settings.profile'), 'statics.settings.*');
        if (! $user->discord_id) {
            $settingsItem['badge'] = ['type' => 'alert', 'color' => '#ff6e84'];
        }

        return [
            $this->navItem(__('My Characters'), 'shield_person', route('characters.index'), 'characters.*'),
            $settingsItem,
        ];
    }

    private function navItem(string $label, string $icon, string $href, string $activePattern): array
    {
        return [
            'label'  => $label,
            'icon'   => $icon,
            'href'   => $href,
            'active' => request()->routeIs($activePattern),
        ];
    }

    private function buildUser(User $user, StaticGroup $static, Role $role, $mainCharacter): array
    {
        $playableClass = $mainCharacter?->playable_class ?? 'Druid';

        return [
            'name'       => $mainCharacter?->name ?? $user->name,
            'subtitle'   => $mainCharacter ? trim(($playableClass) . ($mainCharacter->item_level ? ' · ' . $mainCharacter->item_level . ' ilvl' : '')) : null,
            'avatarUrl'  => $user->getEffectiveAvatarUrl($static->id),
            'classColor' => self::CLASS_HEX[$playableClass] ?? '#FF7C0A',
            'role'       => $role->value,
            'logoutUrl'  => route('logout'),
        ];
    }

    private function buildFooter(): array
    {
        return [
            'feedbackUrl' => route('feedback.index'),
            'discordUrl'  => 'https://discord.gg/rHcj6M5SEv',
            'patreonUrl'  => 'https://www.patreon.com/',
            'helpUrl'     => '#',
        ];
    }

    /**
     * @return array<int, array{type:string,icon?:string,title:string,body?:string,action?:array,persistKey?:string}>
     */
    private function buildInitialNotifications(User $user, StaticGroup $static, bool $isBare): array
    {
        $notifications = [];

        if (! $user->discord_id) {
            $notifications[] = [
                'type'       => 'warning',
                'icon'       => 'link_off',
                'title'      => __('Link your Discord'),
                'body'       => __('Required for bot commands, RSVP & notifications'),
                'action'     => [
                    'label' => __('Link Discord'),
                    'href'  => route('profile.discord.link'),
                    'icon'  => 'link',
                ],
                'persistKey' => 'discord-link-reminder',
            ];
        }

        return $notifications;
    }
}
