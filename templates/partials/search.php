<?php if (!defined('ABSPATH')) exit; ?>
<section class="ytcp-search-section">
    <div class="ytcp-search-container">
        <div class="ytcp-search-input-wrap">
            <svg class="ytcp-search-icon" viewBox="0 0 24 24" width="20" height="20">
                <circle cx="11" cy="11" r="8" fill="none" stroke="currentColor" stroke-width="2"/>
                <line x1="21" y1="21" x2="16.65" y2="16.65" stroke="currentColor" stroke-width="2"/>
            </svg>
            <input type="text"
                   id="ytcp-search-input"
                   class="ytcp-search-input"
                   placeholder="<?php echo esc_attr__('Search episodes...', 'ytchannel-pro'); ?>"
                   autocomplete="off">
            <button class="ytcp-search-clear" id="ytcp-search-clear" style="display:none">&times;</button>
        </div>
        <div class="ytcp-search-results" id="ytcp-search-results" style="display:none">
            <div class="ytcp-search-results-inner" id="ytcp-search-results-inner"></div>
        </div>
    </div>
</section>
