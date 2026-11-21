{{-- English / Urdu switch. The choice is saved on the user (or the session on the login page). --}}
<div {{ $attributes->merge(['class' => 'inline-flex overflow-hidden rounded-md border border-slate-300 text-xs font-medium']) }}>
    @foreach (['en' => 'English', 'ur' => 'اردو'] as $code => $name)
        <form method="POST" action="{{ route('locale.update', $code) }}">
            @csrf
            <button @class([
                'px-2.5 py-1',
                'bg-indigo-600 text-white' => app()->getLocale() === $code,
                'bg-white text-slate-700 hover:bg-slate-50' => app()->getLocale() !== $code,
            ]) @if ($code === 'ur') lang="ur" dir="rtl" @endif>{{ $name }}</button>
        </form>
    @endforeach
</div>
