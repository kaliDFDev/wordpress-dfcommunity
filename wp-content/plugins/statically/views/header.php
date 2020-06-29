<?php

defined( 'ABSPATH' ) OR exit;

$statically_logo_url = plugin_dir_url( STATICALLY_FILE ) . 'static/statically.svg';

?>

<header class="stly-header-container">
    <div class="stly-header">
        <div class="logo">
            <a href="https://statically.io/" target="_blank" title="<?php _e( 'Optimization for your website static assets.', 'statically' ); ?>">
                <img src="<?php echo $statically_logo_url; ?>" />
            </a>
        </div>

        <nav>
            <ul>
                <li><a href="https://wordpress.org/support/plugin/statically/" target="_blank"><?php _e( 'Help', 'statically' ); ?></a></li>
                <li><a href="https://twitter.com/intent/follow?screen_name=staticallyio" target="_blank" title="<?php _e( 'Follow @staticallyio on Twitter', 'statically' ); ?>"><i class="dashicons dashicons-twitter"></i></a></li>
            </ul>
        </nav>
    </div>
</header>

<nav class="stly-tab">
    <ul class="stly">

    <?php if ( Statically::admin_pagenow( 'statically' ) ) : ?>
        <li><a data-stly-tab="general" href="#general"><?php _e( 'General', 'statically' ); ?></a></li>
        <li><a data-stly-tab="speed" href="#speed"><?php _e ( 'Speed', 'statically'); ?></a></li>
        <li><a data-stly-tab="extra" href="#extra"><?php _e( 'Extra', 'statically' ); ?></a></li>
        <li>
            <a data-stly-tab="labs" href="#labs">
                <?php _e( 'Labs', 'statically' ); ?>
                <span class="new"><?php _e( 'New', 'statically' ); ?></span>
            </a>
        </li>
        <li>
            <a data-stly-tab="tools" href="#tools">
                <?php _e( 'Tools ', 'statically' ); ?>
                <span class="new"><?php _e( 'New', 'statically' ); ?></span>
            </a>
        </li>
    <?php endif; ?>

    <?php if ( Statically::admin_pagenow( 'statically-debugger' ) ) : ?>
        <li>
            <a href="<?php echo admin_url( 'admin.php?page=statically' ); ?>">
                <i class="dashicons dashicons-arrow-left"></i>
                <?php _e( 'Back to Settings', 'statically' ); ?>
            </a>
        </li>
    <?php endif; ?>

    </ul>
</nav>