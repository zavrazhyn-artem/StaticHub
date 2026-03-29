<x-app-layout>
    <div class="max-w-4xl mx-auto space-y-8">
        <div>
            <h1 class="text-4xl font-black text-white uppercase tracking-tighter font-headline">Static Settings</h1>
            <p class="text-on-surface-variant font-medium mt-1 uppercase tracking-widest text-xs">{{ $static->name }} • Schedule Configuration</p>
        </div>

        <div class="bg-surface-container-low border border-white/5 rounded-xl p-8 shadow-2xl backdrop-blur-sm">
            <form action="{{ route('statics.settings.schedule.update', $static) }}" method="POST" class="space-y-8">
                @csrf

                <!-- Raid Days -->
                <div class="space-y-4">
                    <label class="block font-headline text-xs font-bold text-primary uppercase tracking-[0.2em]">Raid Days</label>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        @php
                            $currentDays = is_array($static->raid_days) ? $static->raid_days : (json_decode($static->raid_days, true) ?? []);
                            $days = [
                                'mon' => 'Monday',
                                'tue' => 'Tuesday',
                                'wed' => 'Wednesday',
                                'thu' => 'Thursday',
                                'fri' => 'Friday',
                                'sat' => 'Saturday',
                                'sun' => 'Sunday'
                            ];
                        @endphp
                        @foreach($days as $key => $label)
                            <label class="flex items-center gap-3 p-4 rounded-lg bg-surface-container-highest border border-white/5 cursor-pointer hover:bg-white/5 transition-colors group">
                                <input type="checkbox" name="raid_days[]" value="{{ $key }}"
                                    class="w-4 h-4 rounded border-outline-variant bg-black/40 text-primary focus:ring-primary focus:ring-offset-0 focus:ring-offset-transparent"
                                    {{ in_array($key, $currentDays) ? 'checked' : '' }}>
                                <span class="font-headline text-[10px] font-bold uppercase tracking-widest text-on-surface-variant group-hover:text-white transition-colors">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <!-- Schedule Parameters -->
                <div class="space-y-4">
                    <label class="block font-headline text-xs font-bold text-primary uppercase tracking-[0.2em]">Schedule Parameters</label>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <!-- Start Time -->
                        <div class="space-y-2">
                            <label for="raid_start_time" class="block font-headline text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">Start Time</label>
                            <div class="relative group">
                                <span class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <span class="material-symbols-outlined text-on-surface-variant group-focus-within:text-primary transition-colors text-lg">schedule</span>
                                </span>
                                <input type="time" name="raid_start_time" id="raid_start_time"
                                    value="{{ $static->raid_start_time ? \Carbon\Carbon::parse($static->raid_start_time)->format('H:i') : '' }}"
                                    class="block w-full pl-12 pr-4 py-3 bg-surface-container-highest border border-white/5 rounded-lg font-headline text-sm font-bold text-white tracking-widest focus:ring-2 focus:ring-primary focus:border-transparent transition-all outline-none">
                            </div>
                        </div>

                        <!-- End Time -->
                        <div class="space-y-2">
                            <label for="raid_end_time" class="block font-headline text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">End Time</label>
                            <div class="relative group">
                                <span class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <span class="material-symbols-outlined text-on-surface-variant group-focus-within:text-primary transition-colors text-lg">timer_off</span>
                                </span>
                                <input type="time" name="raid_end_time" id="raid_end_time"
                                    value="{{ $static->raid_end_time ? \Carbon\Carbon::parse($static->raid_end_time)->format('H:i') : '' }}"
                                    class="block w-full pl-12 pr-4 py-3 bg-surface-container-highest border border-white/5 rounded-lg font-headline text-sm font-bold text-white tracking-widest focus:ring-2 focus:ring-primary focus:border-transparent transition-all outline-none">
                            </div>
                        </div>

                        <!-- Timezone -->
                        <div class="space-y-2">
                            <label for="timezone" class="block font-headline text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">Timezone</label>
                            <div class="relative group">
                                <span class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <span class="material-symbols-outlined text-on-surface-variant group-focus-within:text-primary transition-colors text-lg">public</span>
                                </span>
                                <select name="timezone" id="timezone"
                                    class="block w-full pl-12 pr-4 py-3 bg-surface-container-highest border border-white/5 rounded-lg font-headline text-sm font-bold text-white tracking-widest focus:ring-2 focus:ring-primary focus:border-transparent transition-all outline-none appearance-none">
                                    @foreach($timezones as $tz)
                                        <option value="{{ $tz }}" {{ $static->timezone == $tz || (empty($static->timezone) && $tz == 'Europe/Paris') ? 'selected' : '' }}>{{ $tz }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                    <p class="text-[10px] text-on-surface-variant font-medium uppercase tracking-wider">Times should be set in the selected timezone.</p>
                </div>

                <!-- Treasury Settings -->
                <div class="space-y-4 pt-4 border-t border-white/5">
                    <label class="block font-headline text-xs font-bold text-[#FFD700] uppercase tracking-[0.2em]">Treasury Parameters</label>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <label for="guild_tax_per_player" class="block font-headline text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">Weekly Tax Per Player</label>
                            <div class="relative group">
                                <span class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <span class="material-symbols-outlined text-on-surface-variant group-focus-within:text-[#FFD700] transition-colors text-lg">payments</span>
                                </span>
                                <input type="number" name="guild_tax_per_player" id="guild_tax_per_player"
                                    value="{{ $static->guild_tax_per_player }}"
                                    class="block w-full pl-12 pr-4 py-3 bg-surface-container-highest border border-white/5 rounded-lg font-headline text-sm font-bold text-white tracking-widest focus:ring-2 focus:ring-[#FFD700] focus:border-transparent transition-all outline-none">
                            </div>
                            <p class="text-[9px] text-on-surface-variant font-medium uppercase tracking-wider">Amount of gold each raider is expected to contribute weekly.</p>
                        </div>
                    </div>
                </div>

                <!-- Discord Integration -->
                <div class="space-y-4 pt-4 border-t border-white/5">
                    <label class="block font-headline text-xs font-bold text-[#5865F2] uppercase tracking-[0.2em]">Discord Integration</label>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Guild ID -->
                        <div class="space-y-2">
                            <label for="discord_guild_id" class="block font-headline text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">Discord Server (Guild)</label>
                            <div class="relative group">
                                <span class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <span class="material-symbols-outlined text-on-surface-variant group-focus-within:text-[#5865F2] transition-colors text-lg">dns</span>
                                </span>
                                @if(!empty($botGuilds))
                                    <select name="discord_guild_id" id="discord_guild_id"
                                        class="block w-full pl-12 pr-4 py-3 bg-surface-container-highest border border-white/5 rounded-lg font-headline text-sm font-bold text-white tracking-widest focus:ring-2 focus:ring-[#5865F2] focus:border-transparent transition-all outline-none appearance-none">
                                        <option value="">Select a server...</option>
                                        @foreach($botGuilds as $guild)
                                            <option value="{{ $guild['id'] }}" {{ $discordGuildId == $guild['id'] ? 'selected' : '' }}>
                                                {{ $guild['name'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                @else
                                    <input type="text" name="discord_guild_id" id="discord_guild_id"
                                        value="{{ $static->discord_guild_id }}"
                                        placeholder="e.g. 123456789012345678"
                                        class="block w-full pl-12 pr-4 py-3 bg-surface-container-highest border border-white/5 rounded-lg font-headline text-sm font-bold text-white tracking-widest focus:ring-2 focus:ring-[#5865F2] focus:border-transparent transition-all outline-none">
                                @endif
                            </div>
                            @if(empty($botGuilds))
                                <p class="text-[9px] text-error-neon font-medium uppercase tracking-wider mt-1">
                                    <span class="material-symbols-outlined text-[10px] align-middle">warning</span>
                                    Bot is not in any servers or token is missing.
                                </p>
                            @else
                                <p class="text-[9px] text-on-surface-variant font-medium uppercase tracking-wider">Select the server where your bot is present.</p>
                            @endif
                        </div>

                        <!-- Channel ID -->
                        <div class="space-y-2">
                            <label for="discord_channel_id" class="block font-headline text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">Announcement Channel</label>
                            <div class="relative group">
                                <span class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <span class="material-symbols-outlined text-on-surface-variant group-focus-within:text-[#5865F2] transition-colors text-lg">chat</span>
                                </span>
                                @if(!empty($discordChannels))
                                    <select name="discord_channel_id" id="discord_channel_id"
                                        class="block w-full pl-12 pr-4 py-3 bg-surface-container-highest border border-white/5 rounded-lg font-headline text-sm font-bold text-white tracking-widest focus:ring-2 focus:ring-[#5865F2] focus:border-transparent transition-all outline-none appearance-none">
                                        <option value="">Select a channel...</option>
                                        @foreach($discordChannels as $channel)
                                            <option value="{{ $channel['id'] }}" {{ $static->discord_channel_id == $channel['id'] ? 'selected' : '' }}># {{ $channel['name'] }}</option>
                                        @endforeach
                                    </select>
                                @elseif($discordGuildId)
                                    <div class="block w-full pl-12 pr-4 py-3 bg-surface-container-highest border border-error-neon/50 rounded-lg font-headline text-sm font-bold text-error-neon tracking-widest">
                                        Bot no longer has access or no text channels found.
                                    </div>
                                    <input type="hidden" name="discord_channel_id" value="{{ $static->discord_channel_id }}">
                                @else
                                    <div class="block w-full pl-12 pr-4 py-3 bg-surface-container-highest/50 border border-white/5 rounded-lg font-headline text-xs font-bold text-on-surface-variant/40 tracking-widest italic">
                                        Select Server first...
                                    </div>
                                @endif
                            </div>
                            <p class="text-[9px] text-on-surface-variant font-medium uppercase tracking-wider">The Discord channel where raid announcements will be posted.</p>
                        </div>
                    </div>
                </div>

                <!-- Automation Rules -->
                <div class="space-y-4 pt-4 border-t border-white/5">
                    <label class="block font-headline text-xs font-bold text-secondary-neon uppercase tracking-[0.2em]">Automation Rules</label>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <!-- Post Next Automatically -->
                        <div class="space-y-4">
                            <label class="flex items-center gap-3 p-4 rounded-lg bg-surface-container-highest border border-white/5 cursor-pointer hover:bg-white/5 transition-colors group">
                                <input type="checkbox" name="automation_settings[post_next_after_raid]" value="1"
                                    class="w-4 h-4 rounded border-outline-variant bg-black/40 text-secondary-neon focus:ring-secondary-neon focus:ring-offset-0 focus:ring-offset-transparent"
                                    {{ !empty($static->automation_settings['post_next_after_raid']) ? 'checked' : '' }}>
                                <div class="flex flex-col">
                                    <span class="font-headline text-[10px] font-bold uppercase tracking-widest text-on-surface-variant group-hover:text-white transition-colors">Auto-Post Next Raid</span>
                                    <span class="text-[9px] text-on-surface-variant/60 font-medium uppercase tracking-wider">Post the next scheduled raid immediately after current one ends</span>
                                </div>
                            </label>
                        </div>

                        <!-- Reminder Hours -->
                        <div class="space-y-2">
                            <label for="reminder_hours_before" class="block font-headline text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">Pre-Raid Announcement (Hours)</label>
                            <div class="relative group">
                                <span class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <span class="material-symbols-outlined text-on-surface-variant group-focus-within:text-secondary-neon transition-colors text-lg">notifications_active</span>
                                </span>
                                <input type="number" name="automation_settings[reminder_hours_before]" id="reminder_hours_before"
                                    value="{{ $static->automation_settings['reminder_hours_before'] ?? '' }}"
                                    placeholder="e.g. 24"
                                    class="block w-full pl-12 pr-4 py-3 bg-surface-container-highest border border-white/5 rounded-lg font-headline text-sm font-bold text-white tracking-widest focus:ring-2 focus:ring-secondary-neon focus:border-transparent transition-all outline-none">
                            </div>
                            <p class="text-[9px] text-on-surface-variant font-medium uppercase tracking-wider">Automatically post announcement X hours before start if missing.</p>
                        </div>

                        <!-- Role to Mention -->
                        <div class="space-y-2">
                            <label for="ping_role_id" class="block font-headline text-[10px] font-bold text-on-surface-variant uppercase tracking-widest">Role to Mention</label>
                            <div class="relative group">
                                <span class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                    <span class="material-symbols-outlined text-on-surface-variant group-focus-within:text-secondary-neon transition-colors text-lg">groups</span>
                                </span>
                                @if(!empty($discordRoles))
                                    <select name="automation_settings[ping_role_id]" id="ping_role_id"
                                        class="block w-full pl-12 pr-4 py-3 bg-surface-container-highest border border-white/5 rounded-lg font-headline text-sm font-bold text-white tracking-widest focus:ring-2 focus:ring-secondary-neon focus:border-transparent transition-all outline-none appearance-none">
                                        <option value="">No mention</option>
                                        @foreach($discordRoles as $role)
                                            <option value="{{ $role['id'] }}" {{ ($static->automation_settings['ping_role_id'] ?? '') == $role['id'] ? 'selected' : '' }}>@ {{ $role['name'] }}</option>
                                        @endforeach
                                    </select>
                                @elseif($discordGuildId)
                                    <div class="block w-full pl-12 pr-4 py-3 bg-surface-container-highest border border-error-neon/50 rounded-lg font-headline text-sm font-bold text-error-neon tracking-widest">
                                        Bot no longer has access or no roles found.
                                    </div>
                                    <input type="hidden" name="automation_settings[ping_role_id]" value="{{ $static->automation_settings['ping_role_id'] ?? '' }}">
                                @else
                                    <div class="block w-full pl-12 pr-4 py-3 bg-surface-container-highest/50 border border-white/5 rounded-lg font-headline text-xs font-bold text-on-surface-variant/40 tracking-widest italic">
                                        Select Server first...
                                    </div>
                                @endif
                            </div>
                            <p class="text-[9px] text-on-surface-variant font-medium uppercase tracking-wider">The Discord role to mention in the announcement (e.g. @Raiders).</p>
                        </div>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="pt-4 border-t border-white/5">
                    <button type="submit" class="bg-primary text-on-primary px-8 py-3 rounded-sm font-headline text-xs font-bold uppercase tracking-[0.2em] hover:brightness-110 active:scale-95 transition-all flex items-center gap-3">
                        <span class="material-symbols-outlined text-lg">save</span>
                        Save Configuration
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
