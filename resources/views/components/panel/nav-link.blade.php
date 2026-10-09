@props(['href', 'active' => false])

<a
    href="{{ $href }}"
    @if($href !== '#') wire:navigate @endif
    {{ $attributes->merge([
        'class' => 'group flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition border-l-2 ' . ($active
            ? 'bg-indigo-50 text-indigo-700 border-indigo-600'
            : 'text-gray-600 border-transparent hover:bg-gray-50 hover:text-gray-900')
    ]) }}
>
    @isset($icon)
        <span class="w-5 h-5 shrink-0 {{ $active ? 'text-indigo-600' : 'text-gray-400 group-hover:text-gray-500' }}">
            {{ $icon }}
        </span>
    @endisset
    <span>{{ $slot }}</span>
</a>
