<?php
/**
 * Custom WordPress function to sanitize taxonomy term slugs by removing accents.
 *
 * This function iterates through all registered taxonomies and their terms.
 * For each term, it sanitizes its name (title) by replacing accented characters
 * with their non-accented equivalents. It then generates a new slug based on
 * this sanitized name and updates the term if the slug has changed.
 */
function my_custom_sanitize_taxonomy_term_slugs() {
    // Ensure this function is only run in a WordPress environment.
    if ( ! defined( 'ABSPATH' ) ) {
        return false;
    }

    // Check for necessary WordPress functions.
    if ( ! function_exists( 'get_taxonomies' ) || ! function_exists( 'get_terms' ) || ! function_exists( 'wp_update_term' ) ) {
        error_log( 'my_custom_sanitize_taxonomy_term_slugs: Required WordPress functions are missing.' );
        return false;
    }

    // Get all registered taxonomies, including ACF taxonomies
    // ACF taxonomies might not always be marked as 'public' => true
    $taxonomies = get_taxonomies( array(), 'names' );
    
    // Filter out built-in WordPress taxonomies that we don't want to process
    $excluded_taxonomies = array( 'nav_menu', 'link_category', 'post_format' );
    $taxonomies = array_diff( $taxonomies, $excluded_taxonomies );

    if ( empty( $taxonomies ) ) {
        error_log( 'my_custom_sanitize_taxonomy_term_slugs: No public taxonomies found.' );
        return false;
    }

    $updated_terms_count = 0;

    foreach ( $taxonomies as $taxonomy ) {
        // Skip if taxonomy doesn't exist (safety check)
        if ( ! taxonomy_exists( $taxonomy ) ) {
            continue;
        }
        
        // Get all terms for the current taxonomy.
        $terms = get_terms( array(
            'taxonomy'   => $taxonomy,
            'hide_empty' => false,
            'fields'     => 'all',
        ) );

        if ( is_wp_error( $terms ) ) {
            error_log( 'my_custom_sanitize_taxonomy_term_slugs: Error getting terms for taxonomy ' . $taxonomy . ': ' . $terms->get_error_message() );
            continue;
        }

        if ( empty( $terms ) ) {
            continue;
        }

        foreach ( $terms as $term ) {
            // Get the original term name and slug.
            $original_name = $term->name;
            $original_slug = $term->slug;

            // Sanitize the name by replacing accented characters.
            $sanitized_name = remove_accents_from_string( $original_name );

            // Generate a new slug from the sanitized name.
            $new_slug = sanitize_title( $sanitized_name );

            // Make sure the slug is unique
            $new_slug = wp_unique_term_slug( $new_slug, (object) array(
                'term_id' => $term->term_id,
                'taxonomy' => $taxonomy,
                'parent' => $term->parent
            ) );

            // Only update if the new slug is different from the original.
            if ( $original_slug !== $new_slug ) {
                $updated_term = wp_update_term( $term->term_id, $taxonomy, array(
                    'slug' => $new_slug,
                ) );

                if ( is_wp_error( $updated_term ) ) {
                    error_log( 'my_custom_sanitize_taxonomy_term_slugs: Failed to update term ID ' . $term->term_id . ' (' . $original_name . ') in taxonomy ' . $taxonomy . '. Error: ' . $updated_term->get_error_message() );
                } else {
                    $updated_terms_count++;
                    error_log( 'Updated slug for term ID ' . $term->term_id . ' (' . $original_name . '): ' . $original_slug . ' -> ' . $new_slug );
                }
            }
        }
    }

    // Log the final count
    error_log( 'my_custom_sanitize_taxonomy_term_slugs: Finished. Total terms updated: ' . $updated_terms_count );
    return $updated_terms_count;
}

/**
 * Helper function to remove accents from a string.
 *
 * This function specifically targets common French accented characters
 * and replaces them with their non-accented ASCII equivalents.
 *
 * @param string $string The input string with accents.
 * @return string The string with accents removed.
 */
function remove_accents_from_string( $string ) {
    // First, use WordPress's built-in remove_accents function if available
    if ( function_exists( 'remove_accents' ) ) {
        $string = remove_accents( $string );
    }
    
    // Additional replacements for characters that might not be covered
    $replacements = array(
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a',
        'ç' => 'c',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
        'ñ' => 'n',
        'ý' => 'y', 'ÿ' => 'y',
        'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
        'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A',
        'Ç' => 'C',
        'Í' => 'I', 'Ì' => 'I', 'Î' => 'I', 'Ï' => 'I',
        'Ó' => 'O', 'Ò' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O',
        'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U',
        'Ñ' => 'N',
        'Ý' => 'Y', 'Ÿ' => 'Y',
    );

    return strtr( $string, $replacements );
}

// Admin page to run the function
add_action( 'admin_menu', 'my_custom_slug_sanitization_menu' );
function my_custom_slug_sanitization_menu() {
    add_management_page(
        'Sanitize Slugs',
        'Sanitize Slugs',
        'manage_options',
        'sanitize-slugs',
        'my_custom_slug_sanitization_page_content'
    );
}

function my_custom_slug_sanitization_page_content() {
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Sanitize Taxonomy Slugs' ); ?></h1>
        <p><?php esc_html_e( 'This tool will go through all your taxonomy terms (including ACF custom taxonomies) and sanitize their slugs by replacing accented characters with non-accented equivalents (e.g., é to e).' ); ?></p>
        <p><strong><?php esc_html_e( 'Important:' ); ?></strong> <?php esc_html_e( 'It is highly recommended to backup your database before running this tool.' ); ?></p>
        
        <?php
        // Handle form submission
        if ( isset( $_POST['run_slug_sanitization_button'] ) && check_admin_referer( 'run_slug_sanitization', 'slug_sanitization_nonce' ) ) {
            $updated_count = my_custom_sanitize_taxonomy_term_slugs();
            if ( $updated_count !== false ) {
                echo '<div class="notice notice-success is-dismissible"><p><strong>' . sprintf( esc_html__( '%d taxonomy terms have been sanitized and updated.' ), $updated_count ) . '</strong></p></div>';
            } else {
                echo '<div class="notice notice-error is-dismissible"><p><strong>' . esc_html__( 'An error occurred during slug sanitization. Check your error logs.' ) . '</strong></p></div>';
            }
        }
        ?>
        
        <form method="post" action="">
            <?php wp_nonce_field( 'run_slug_sanitization', 'slug_sanitization_nonce' ); ?>
            <p class="submit">
                <input type="submit" name="run_slug_sanitization_button" id="submit" class="button button-primary" value="<?php esc_attr_e( 'Run Slug Sanitization Now' ); ?>" onclick="return confirm('Are you sure you want to run this? Make sure you have a database backup!');">
            </p>
        </form>
        
        <h2>Preview What Will Be Changed</h2>
        <p>Click the button below to see what terms would be changed without actually updating them:</p>
        <p><em>Note: This will check all taxonomies including ACF custom taxonomies.</em></p>
        <form method="post" action="">
            <?php wp_nonce_field( 'preview_slug_sanitization', 'preview_slug_sanitization_nonce' ); ?>
            <p class="submit">
                <input type="submit" name="preview_slug_sanitization_button" id="preview" class="button button-secondary" value="<?php esc_attr_e( 'Preview Changes' ); ?>">
            </p>
        </form>
        
        <?php
        // Handle preview
        if ( isset( $_POST['preview_slug_sanitization_button'] ) && check_admin_referer( 'preview_slug_sanitization', 'preview_slug_sanitization_nonce' ) ) {
            preview_slug_changes();
        }
        ?>
    </div>
    <?php
}

/**
 * Preview function to show what would be changed
 */
function preview_slug_changes() {
    $taxonomies = get_taxonomies( array(), 'names' );
    $excluded_taxonomies = array( 'nav_menu', 'link_category', 'post_format' );
    $taxonomies = array_diff( $taxonomies, $excluded_taxonomies );
    $changes = array();
    
    foreach ( $taxonomies as $taxonomy ) {
        // Skip if taxonomy doesn't exist
        if ( ! taxonomy_exists( $taxonomy ) ) {
            continue;
        }
        
        $terms = get_terms( array(
            'taxonomy'   => $taxonomy,
            'hide_empty' => false,
            'fields'     => 'all',
        ) );

        if ( is_wp_error( $terms ) || empty( $terms ) ) {
            continue;
        }

        foreach ( $terms as $term ) {
            $original_name = $term->name;
            $original_slug = $term->slug;
            $sanitized_name = remove_accents_from_string( $original_name );
            $new_slug = sanitize_title( $sanitized_name );
            
            if ( $original_slug !== $new_slug ) {
                $changes[] = array(
                    'taxonomy' => $taxonomy,
                    'name' => $original_name,
                    'old_slug' => $original_slug,
                    'new_slug' => $new_slug
                );
            }
        }
    }
    
    if ( empty( $changes ) ) {
        echo '<div class="notice notice-info"><p><strong>No changes needed!</strong> All taxonomy term slugs are already properly sanitized.</p></div>';
    } else {
        echo '<div class="notice notice-warning"><p><strong>' . count( $changes ) . ' terms will be updated:</strong></p></div>';
        echo '<table class="widefat"><thead><tr><th>Taxonomy</th><th>Term Name</th><th>Current Slug</th><th>New Slug</th></tr></thead><tbody>';
        foreach ( $changes as $change ) {
            echo '<tr>';
            echo '<td>' . esc_html( $change['taxonomy'] ) . '</td>';
            echo '<td>' . esc_html( $change['name'] ) . '</td>';
            echo '<td>' . esc_html( $change['old_slug'] ) . '</td>';
            echo '<td>' . esc_html( $change['new_slug'] ) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }
}