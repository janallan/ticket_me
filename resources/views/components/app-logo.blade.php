@props([
    'sidebar' => false,
])

{{-- The app's logo link in the sidebar and the header, pointing wherever "href" says. --}}
<a {{ $attributes->class(['flex items-center', 'px-2 py-1' => $sidebar, 'me-4' => ! $sidebar]) }}>
    <x-brand-logo :class="$sidebar ? 'h-8' : 'h-7'" />
</a>
