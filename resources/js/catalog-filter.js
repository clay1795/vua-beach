const mobileCatalog = window.matchMedia('(max-width: 767.98px)');

function syncCatalogFilters() {
    document.querySelectorAll('.catalog-filter-disclosure').forEach((disclosure) => {
        if (!(disclosure instanceof HTMLDetailsElement)) return;

        if (!mobileCatalog.matches) {
            disclosure.open = true;
            return;
        }

        disclosure.open = disclosure.dataset.activeFilters === 'true';
    });
}

syncCatalogFilters();
mobileCatalog.addEventListener('change', syncCatalogFilters);
