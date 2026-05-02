<!-- ========================================
             Search Bar
             ======================================== -->
        <div class="search-container">
            <input type="text" class="search-input" id="gameSearch" placeholder="Search games..." autocomplete="off">
            <i class="fa-solid fa-magnifying-glass search-icon"></i>
            <button class="search-clear" id="searchClear" aria-label="Clear search"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <!-- ========================================
             Category Tabs
             ======================================== -->
        <div class="category-tabs" id="categoryTabs">
            <button class="category-tab active" data-category="all">All Games <span class="tab-count" id="count-all"></span></button>
            <button class="category-tab" data-category="action">Action <span class="tab-count" id="count-action"></span></button>
            <button class="category-tab" data-category="arcade">Arcade <span class="tab-count" id="count-arcade"></span></button>
            <button class="category-tab" data-category="strategy">Strategy <span class="tab-count" id="count-strategy"></span></button>
            <button class="category-tab" data-category="puzzle">Puzzle <span class="tab-count" id="count-puzzle"></span></button>
            <button class="category-tab" data-category="horror">Horror <span class="tab-count" id="count-horror"></span></button>
            <button class="category-tab" data-category="fnaf">FNAF 1-4 <span class="tab-count" id="count-fnaf"></span></button>
            <button class="category-tab" data-category="fix">Fixed Games <span class="tab-count" id="count-fix"></span></button>
            <button class="category-tab" data-category="NEW!">Newly Added <span class="tab-count" id="count-NEW!"></span></button>
            <button class="category-tab" data-category="broke">Broken <span class="tab-count" id="count-broke"></span></button>
        </div>

        <!-- No Results Message -->
        <div class="search-no-results" id="noResults">
            <i class="fa-solid fa-ghost"></i>
            No games found matching your search.
        </div>