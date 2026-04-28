<?php if (!defined('ABSPATH')) exit; ?>
<section class="ytflix-search-section">
    <div class="ytflix-search-container">
        <div class="ytflix-search-input-wrap">
            <svg class="ytflix-search-icon" viewBox="0 0 24 24" width="20" height="20">
                <circle cx="11" cy="11" r="8" fill="none" stroke="currentColor" stroke-width="2"/>
                <line x1="21" y1="21" x2="16.65" y2="16.65" stroke="currentColor" stroke-width="2"/>
            </svg>
            <input type="text"
                   id="ytflix-search-input"
                   class="ytflix-search-input"
                   placeholder="Search characters..."
                   autocomplete="off">
            <button class="ytflix-search-clear" id="ytflix-search-clear" style="display:none">&times;</button>
        </div>
        <div class="ytflix-search-results" id="ytflix-search-results" style="display:none">
            <div class="ytflix-search-results-inner" id="ytflix-search-results-inner"></div>
        </div>
    </div>
</section>
