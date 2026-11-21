<x-layouts.app :title="__('Message log')">
    <x-slot:actions>
        @can('settings.manage')
            <a href="{{ route('messaging.settings') }}" class="btn btn-secondary">{{ __('SMS / WhatsApp settings') }}</a>
        @endcan
    </x-slot:actions>

    @if (! $provider || $provider->value === 'log')
        <div class="mb-4 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            {{ __('No SMS or WhatsApp provider is set up yet, so messages are recorded here but not sent.') }}
            @can('settings.manage')<a href="{{ route('messaging.settings') }}" class="font-medium underline">{{ __('Set up a provider') }}</a>.@endcan
        </div>
    @endif

    <form method="GET" class="card card-body mb-4 grid gap-3 sm:grid-cols-4">
        <x-field name="q" :label="__('Phone or customer')" :value="request('q')" />
        <x-select name="event" :label="__('Message')" :options="collect($events)->mapWithKeys(fn ($e) => [$e->value => $e->label()])" :selected="request('event')" :placeholder="__('All')" />
        <x-select name="status" :label="__('Status')" :options="['queued' => __('Queued'), 'sent' => __('Sent'), 'failed' => __('Failed'), 'logged' => __('Not sent (no provider)')]" :selected="request('status')" :placeholder="__('All')" />
        <div class="flex items-end"><button class="btn btn-secondary w-full">{{ __('Filter') }}</button></div>
    </form>

    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>{{ __('When') }}</th><th>{{ __('To') }}</th><th>{{ __('Message') }}</th><th>{{ __('Via') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
            <tbody>
            @forelse ($logs as $log)
                <tr>
                    <td class="whitespace-nowrap text-xs">{{ $log->created_at->translatedFormat('d M Y H:i') }}<div class="text-slate-500">{{ $log->creator?->name ?? __('Automatic') }}</div></td>
                    <td class="text-xs">
                        @if ($log->customer)<a href="{{ route('customers.show', $log->customer) }}" class="font-medium text-indigo-600 hover:underline">{{ $log->customer->name }}</a><br>@endif
                        <span class="font-mono">+{{ $log->phone }}</span>
                    </td>
                    <td class="max-w-md text-xs">
                        <span class="badge badge-gray">{{ $log->event->label() }}</span>
                        <div class="mt-1 whitespace-pre-line" dir="auto">{{ $log->message }}</div>
                        @if ($log->error)<div class="mt-1 text-rose-600">{{ $log->error }}</div>@endif
                    </td>
                    <td class="text-xs">{{ ucfirst($log->channel) }}<div class="text-slate-500">{{ $log->provider }}</div></td>
                    <td><span class="badge {{ $log->statusBadge() }}">{{ $log->status === 'logged' ? __('Not sent') : ucfirst($log->status) }}</span>@if ($log->attempts > 1)<div class="text-xs text-slate-500">{{ $log->attempts }} {{ __('tries') }}</div>@endif</td>
                    <td class="text-end">
                        @if (in_array($log->status, ['failed', 'logged'], true))
                            <form method="POST" action="{{ route('notifications.resend', $log) }}">
                                @csrf
                                <button class="btn btn-secondary btn-sm">{{ __('Send again') }}</button>
                            </form>
                        @endif
                        <a href="https://wa.me/{{ $log->phone }}?text={{ rawurlencode($log->message) }}" target="_blank" rel="noopener" class="mt-1 inline-block text-xs text-indigo-600 hover:underline">{{ __('Open in WhatsApp') }}</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="py-6 text-center text-slate-500">{{ __('No messages yet.') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $logs->links() }}</div>
</x-layouts.app>
