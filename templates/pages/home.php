<?php
if (!defined('ABSPATH')) exit;
get_header();
?>
<div class="ytcp-app" id="ytcp-app">
    <?php
    include YTCP_PLUGIN_DIR . 'templates/partials/hero.php';
    include YTCP_PLUGIN_DIR . 'templates/partials/search.php';

    if (is_user_logged_in()) {
        $progress_svc = new YTCP_User_Progress();
        $continue_watching = $progress_svc->get_continue_watching(get_current_user_id(), 20);
        if (!empty($continue_watching)) {
            include YTCP_PLUGIN_DIR . 'templates/partials/continue-watching.php';
        }
    }

    include YTCP_PLUGIN_DIR . 'templates/partials/playlist-rows.php';
    include YTCP_PLUGIN_DIR . 'templates/partials/modal.php';
    ?>
</div>
<?php get_footer(); ?>
