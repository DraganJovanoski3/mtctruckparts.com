@if(!empty($crumbs) && count($crumbs))
<nav class="breadcrumbs" aria-label="Breadcrumb">
    <a href="{{ route('home') }}">Home</a>
    @foreach($crumbs as $crumb)
        /
        @if(!empty($crumb['url']))
            <a href="{{ $crumb['url'] }}">{{ $crumb['label'] }}</a>
        @else
            <span>{{ $crumb['label'] }}</span>
        @endif
    @endforeach
</nav>
@endif
