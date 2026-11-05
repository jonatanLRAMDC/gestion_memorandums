@props([
    'title',
    'description',
])

<div class="flex w-full flex-col gap-1">
    <h1 class="text-lg font-semibold text-zinc-800">{{ $title }}</h1>
    <p class="text-sm text-zinc-500">{{ $description }}</p>
</div>
