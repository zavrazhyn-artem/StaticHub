<x-app-layout :bare="true">
    <feedback-detail
        :payload='@json($payload)'
        :is-authenticated="{{ $isAuthenticated ? 'true' : 'false' }}"
        :can-manage="{{ $canManage ? 'true' : 'false' }}"
        :is-author="{{ $isAuthor ? 'true' : 'false' }}"
    ></feedback-detail>
</x-app-layout>
