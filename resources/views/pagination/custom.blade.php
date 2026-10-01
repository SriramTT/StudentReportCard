@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination Navigation" class="pagination-wrapper">
        <div class="pagination-summary">
            Showing
            <span class="pagination-highlight">{{ $paginator->firstItem() }}</span>
            to
            <span class="pagination-highlight">{{ $paginator->lastItem() }}</span>
            of
            <span class="pagination-highlight">{{ $paginator->total() }}</span>
            results
        </div>

        <ul class="pagination-nav">
            {{-- Previous Page Link --}}
            @if ($paginator->onFirstPage())
                <li class="pagination-item">
                    <span class="pagination-btn is-disabled" aria-disabled="true" aria-label="@lang('pagination.previous')">
                        &lsaquo; Prev
                    </span>
                </li>
            @else
                <li class="pagination-item">
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="pagination-btn" aria-label="@lang('pagination.previous')">
                        &lsaquo; Prev
                    </a>
                </li>
            @endif

            {{-- Pagination Elements --}}
            @foreach ($elements as $element)
                {{-- "Three Dots" Separator --}}
                @if (is_string($element))
                    <li class="pagination-item">
                        <span class="pagination-btn is-disabled" aria-disabled="true">{{ $element }}</span>
                    </li>
                @endif

                {{-- Array Of Links --}}
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        <li class="pagination-item">
                            @if ($page == $paginator->currentPage())
                                <span class="pagination-btn is-active" aria-current="page">{{ $page }}</span>
                            @else
                                <a href="{{ $url }}" class="pagination-btn">{{ $page }}</a>
                            @endif
                        </li>
                    @endforeach
                @endif
            @endforeach

            {{-- Next Page Link --}}
            @if ($paginator->hasMorePages())
                <li class="pagination-item">
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="pagination-btn" aria-label="@lang('pagination.next')">
                        Next &rsaquo;
                    </a>
                </li>
            @else
                <li class="pagination-item">
                    <span class="pagination-btn is-disabled" aria-disabled="true" aria-label="@lang('pagination.next')">
                        Next &rsaquo;
                    </span>
                </li>
            @endif
        </ul>
    </nav>
@endif
