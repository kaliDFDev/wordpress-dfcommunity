<?php defined( 'ABSPATH' ) OR exit; ?>

<div data-stly-layout="labs">
    <h3 class="title"><?php _e( 'Labs', 'statically' ); ?></h3>
    <p class="description">
        <?php _e( 'Everyone likes to try things! This is a list of Beta features, these options are intended to try out new features from Statically for WordPress based websites. We will add related feature as main menu when we receive good feedback for it. Use wisely!', 'statically' ); ?>
    </p>

    <table class="form-table">
        <tr valign="top">
            <th scope="row">
                <?php _e( 'Page Booster', 'statically' ); ?>
            </th>
            <td>
                <fieldset>
                    <label for="statically_pagebooster">
                        <input type="checkbox" name="statically[pagebooster]" id="statically_pagebooster" value="1" <?php checked(1, $options['pagebooster']) ?> />
                        <?php _e( 'Automatically speeds up your pages using latest progressive enhancements. Default: <code>OFF</code>', 'statically' ); ?>
                    </label>
                </fieldset>

                <fieldset>
                    <label for="statically_pagebooster-content">
                        <h4><?php _e( 'Selector', 'statically' ); ?></h4>
                        <input type="text" name="statically[pagebooster_content]" id="statically_pagebooster-content" value="<?php echo $options['pagebooster_content']; ?>" style="max-width: 10em" />
                        <?php _e( ' &#8212; Can be class, ID, or element selector.', 'statically' ); ?>
                    </label>
                </fieldset>

                <fieldset>
                    <label for="statically_pagebooster-turbo">
                        <h4><?php _e( 'Turbo', 'statically' ); ?></h4>
                        <input type="checkbox" name="statically[pagebooster_turbo]" id="statically_pagebooster-turbo" value="1" <?php checked(1, $options['pagebooster_turbo']) ?> />
                        <?php _e( 'Enable cache and URL pre-fetching on mouse hover.', 'statically' ); ?>
                    </label>
                </fieldset>

                <fieldset>
                    <label for="statically_pagebooster-scripts-to-refresh">
                        <h4><?php _e( 'Scripts to refresh', 'statically' ); ?></h4>
                        <input type="text" name="statically[pagebooster_scripts_to_refresh]" id="statically_pagebooster-scripts-to-refresh" value="<?php echo $options['pagebooster_scripts_to_refresh']; ?>" style="min-width: 30em" />
                    </label>
                    
                    <p class="description">
                        <?php _e( 'Enter scripts to refresh separated by', 'statically' ); ?> <code>,</code>
                    </p>
                </fieldset>

                <?php if ( $options['dev'] ) : ?>
                <fieldset>
                    <label for="statically_pagebooster-custom-js">
                        <h4><?php _e( 'F3H Settings (custom JS)', 'statically' ); ?></h4>
                        <textarea type="text" name="statically[pagebooster_custom_js]" id="statically_pagebooster-custom-js"><?php echo $options['pagebooster_custom_js']; ?></textarea><br>

                        <input type="checkbox" name="statically[pagebooster_custom_js_enabled]" id="statically_pagebooster-custom-js-enabled" value="1" <?php checked(1, $options['pagebooster_custom_js_enabled']) ?> />
                        <?php _e( 'Enable this custom JS will overwrite all settings above.', 'statically' ); ?>

                        <p class="description"><?php _e( 'This feature uses F3H.js as the main function, matching this setting with your site to achieve the best possible progressive WordPress. Learn more how to configure F3H <a href="https://statically.io/go/f3h" target="_blank">here</a>.', 'statically' ); ?></p>
                    </label>
                </fieldset>
                <?php endif; ?>
            </td>
        </tr>
    </table>

    <?php submit_button(); ?>
</div>
