@if ($paginator->hasPages())
    <nav class="pager" role="navigation" aria-label="Pagination">
        <ul class="pager-list">
            {{-- Previous --}}
            @if ($paginator->onFirstPage())
                <li class="pager-item is-disabled" aria-disabled="true">
                    <span class="pager-btn" aria-label="Previous">&lsaquo;</span>
                </li>
            @else
                <li class="pager-item">
                    <a class="pager-btn" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Previous">&lsaquo;</a>
                </li>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <li class="pager-item is-disabled" aria-disabled="true"><span class="pager-btn">{{ $element }}</span></li>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <li class="pager-item is-active" aria-current="page"><span class="pager-btn">{{ $page }}</span></li>
                        @else
                            <li class="pager-item"><a class="pager-btn" href="{{ $url }}">{{ $page }}</a></li>
                        @endif
                    @endforeach
                @endif
            @endforeach

            {{-- Next --}}
            @if ($paginator->hasMorePages())
                <li class="pager-item">
                    <a class="pager-btn" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Next">&rsaquo;</a>
                </li>
            @else
                <li class="pager-item is-disabled" aria-disabled="true">
                    <span class="pager-btn" aria-label="Next">&rsaquo;</span>
                </li>
            @endif
        </ul>
    </nav>
@endif
