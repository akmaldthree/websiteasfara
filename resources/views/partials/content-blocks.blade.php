@foreach($contentBlocks ?? [] as $block)
    @switch($block['type'] ?? '')
        @case('heading')
            <h2 class="mt-5 mb-3">{{ $block['text'] }}</h2>
            @break
        @case('paragraph')
            <p class="preserve-lines">{{ $block['text'] }}</p>
            @break
        @case('quote')
            <blockquote class="panel my-4">{{ $block['text'] }}</blockquote>
            @break
        @case('button')
            <p><a class="btn btn-dark" href="{{ $block['url'] }}">{{ $block['label'] }} ↗</a></p>
            @break
        @case('image')
            <figure class="my-4"><img class="w-100" src="{{ $block['url'] }}" alt="{{ $block['label'] }}"><figcaption class="small text-muted mt-2">{{ $block['label'] }}</figcaption></figure>
            @break
    @endswitch
@endforeach
