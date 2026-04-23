<x-app-layout :bare="true">
    <feedback-list
        :initial-data='@json($payload)'
        :is-authenticated="{{ $isAuthenticated ? 'true' : 'false' }}"
        :can-manage="{{ $canManage ? 'true' : 'false' }}"
    ></feedback-list>
</x-app-layout>
