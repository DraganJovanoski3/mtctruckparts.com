<div class="nav-item">
    <a class="nav-link" href="{{ route('category.show', $navCat->slug) }}">
        {{ $navCat->menuLabel() }}
        @if($navCat->children->isNotEmpty())
            <span class="nav-caret" aria-hidden="true">▾</span>
        @endif
    </a>
    @if($navCat->children->isNotEmpty())
        <div class="nav-dropdown" role="menu">
            @foreach($navCat->children as $child)
                <div class="nav-subitem {{ $child->children->isNotEmpty() ? 'has-children' : '' }}">
                    <a href="{{ route('category.show', $child->slug) }}">
                        {{ $child->menuLabel() }}
                        @if($child->children->isNotEmpty())
                            <span class="nav-arrow">›</span>
                        @endif
                    </a>
                    @if($child->children->isNotEmpty())
                        <div class="nav-subdropdown">
                            @foreach($child->children as $grand)
                                <a href="{{ route('category.show', $grand->slug) }}">{{ $grand->menuLabel() }}</a>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
