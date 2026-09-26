@if ($paginator->hasPages())
    <nav class="store-pagination" role="navigation" aria-label="Phân trang sản phẩm">
        <p class="store-pagination-status mb-0">
            Trang <strong>{{ $paginator->currentPage() }}</strong> / {{ $paginator->lastPage() }}
        </p>

        <div class="store-pagination-controls">
            @if ($paginator->onFirstPage())
                <span class="store-page-action is-disabled" aria-disabled="true">
                    <i class="bi bi-arrow-left" aria-hidden="true"></i>
                    <span>Trang trước</span>
                </span>
            @else
                <a class="store-page-action" href="{{ $paginator->previousPageUrl() }}" rel="prev">
                    <i class="bi bi-arrow-left" aria-hidden="true"></i>
                    <span>Trang trước</span>
                </a>
            @endif

            <div class="store-page-numbers" aria-label="Danh sách trang">
                @foreach ($elements as $element)
                    @if (is_string($element))
                        <span class="store-page-ellipsis" aria-hidden="true">{{ $element }}</span>
                    @endif

                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <span class="store-page-number is-current" aria-current="page">{{ $page }}</span>
                            @else
                                <a class="store-page-number" href="{{ $url }}" aria-label="Đi đến trang {{ $page }}">{{ $page }}</a>
                            @endif
                        @endforeach
                    @endif
                @endforeach
            </div>

            @if ($paginator->hasMorePages())
                <a class="store-page-action" href="{{ $paginator->nextPageUrl() }}" rel="next">
                    <span>Trang sau</span>
                    <i class="bi bi-arrow-right" aria-hidden="true"></i>
                </a>
            @else
                <span class="store-page-action is-disabled" aria-disabled="true">
                    <span>Trang sau</span>
                    <i class="bi bi-arrow-right" aria-hidden="true"></i>
                </span>
            @endif
        </div>
    </nav>
@endif
