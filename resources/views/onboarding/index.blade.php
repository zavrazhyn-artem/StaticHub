<x-app-layout :onboarding="true">
    <onboarding-stepper
        pending-join-token="{{ $pendingJoinToken ?? '' }}"
        :pending-join-static="{{ json_encode($pendingJoinStatic) }}"
        :auto-join-result="{{ json_encode($autoJoinResult) }}"
        :is-guild-master="{{ json_encode($isGuildMaster) }}"
        guild-name="{{ $guildName ?? '' }}"
        csrf-token="{{ $csrfToken }}"
    ></onboarding-stepper>
</x-app-layout>
