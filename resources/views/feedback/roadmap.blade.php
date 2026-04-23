<x-app-layout :bare="true">
    <roadmap-kanban
        :payload='@json($payload)'
        :is-authenticated="{{ $isAuthenticated ? 'true' : 'false' }}"
        :can-manage="{{ $canManage ? 'true' : 'false' }}"
    ></roadmap-kanban>
</x-app-layout>
