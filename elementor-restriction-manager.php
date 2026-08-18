<?php
/*
Plugin Name: Elementor Restriction Manager
Description: Combined WP-CLI tools to scan, backup, and migrate legacy Restrict for Elementor settings to native Elementor Display Conditions.
*/

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
    return;
}

class Elementor_Restriction_Manager {

    /**
     * 1. SCAN COMMAND: wp find-restrictions
     */
    public function scan( $args, $assoc_args ) {
        global $wpdb;

        $results = $wpdb->get_results( "
            SELECT post_id, meta_value 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = '_elementor_data' 
            AND meta_value LIKE '%restrict_for_elementor%'
        " );

        if ( empty( $results ) ) {
            WP_CLI::success( "No pages found using Restrict for Elementor." );
            return;
        }

        $found_elements = [];

        foreach ( $results as $row ) {
            $data = json_decode( $row->meta_value, true );
            if ( ! is_array( $data ) ) continue;

            $scan_elements = function( $elements ) use ( &$scan_elements, &$found_elements, $row ) {
                foreach ( $elements as $element ) {
                    $settings = $element['settings'] ?? [];

                    // Only count as active restriction if main logic/roles are present
                    $has_active_restriction = ! empty( $settings['restrict_for_elementor_show_to'] ) || 
                                             ( isset( $settings['user_role_selection'] ) && ! empty( $settings['user_role_selection'] ) );

                    // Check for orphaned keys
                    $has_orphan_keys = false;
                    foreach ( $settings as $key => $val ) {
                        if ( stristr( $key, 'restrict_for_elementor' ) !== false ) {
                            $has_orphan_keys = true;
                            break;
                        }
                    }

                    if ( $has_active_restriction || $has_orphan_keys ) {
                        $found_elements[] = [
                            'Post ID'      => $row->post_id,
                            'Title'        => get_the_title( $row->post_id ),
                            'Element ID'   => $element['id'] ?? 'N/A',
                            'Type'         => ($element['elType'] === 'widget') ? ($element['widgetType'] ?? 'widget') : $element['elType'],
                            'Restriction'  => $settings['restrict_for_elementor_show_to'] ?? ($has_orphan_keys ? 'Orphaned Meta (Needs Cleanup)' : 'Enabled'),
                        ];
                    }

                    if ( ! empty( $element['elements'] ) ) {
                        $scan_elements( $element['elements'] );
                    }
                }
            };

            $scan_elements( $data );
        }

        if ( ! empty( $found_elements ) ) {
            WP_CLI\Utils\format_items( 'table', $found_elements, [ 'Post ID', 'Title', 'Element ID', 'Type', 'Restriction' ] );
        } else {
            WP_CLI::success( "No active restrictions found inside element settings." );
        }
    }

    /**
     * 2. MIGRATE COMMAND: wp migrate-elementor-restrictions [--dry-run] [--skip-backup]
     */
    public function migrate( $args, $assoc_args ) {
        global $wpdb;

        $dry_run     = isset( $assoc_args['dry-run'] );
        $skip_backup = isset( $assoc_args['skip-backup'] );

        $results = $wpdb->get_results( "
            SELECT post_id, meta_value 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = '_elementor_data' 
            AND meta_value LIKE '%restrict_for_elementor%'
        " );

        if ( empty( $results ) ) {
            WP_CLI::success( "No pages found with legacy restrictions to migrate." );
            return;
        }

        $updated_posts = 0;

        foreach ( $results as $row ) {
            $data = json_decode( $row->meta_value, true );
            if ( ! is_array( $data ) ) continue;

            $modified = false;

            $migrate_elements = function( &$elements ) use ( &$migrate_elements, &$modified ) {
                foreach ( $elements as &$element ) {
                    if ( isset( $element['settings'] ) && is_array( $element['settings'] ) ) {
                        $settings = &$element['settings'];

                        // Check if active restriction logic exists
                        if ( ! empty( $settings['restrict_for_elementor_show_to'] ) ) {
                            $show_to    = $settings['restrict_for_elementor_show_to'];
                            $rule_group = [];

                            if ( $show_to === 'user_role' && ! empty( $settings['user_role_selection'] ) ) {
                                $roles = (array) $settings['user_role_selection'];
                                $rule_group[] = [
                                    'condition'  => 'user_role',
                                    'comparator' => 'is_one_of',
                                    'roles'      => array_values( $roles ),
                                ];
                            } elseif ( $show_to === 'logged_in_users' ) {
                                $rule_group[] = [
                                    'condition'  => 'user_status',
                                    'comparator' => 'is',
                                    'status'     => 'logged_in',
                                ];
                            } elseif ( $show_to === 'logged_out_users' ) {
                                $rule_group[] = [
                                    'condition'  => 'user_status',
                                    'comparator' => 'is',
                                    'status'     => 'logged_out',
                                ];
                            }

                            if ( ! empty( $rule_group ) ) {
                                $nested_conditions = [ $rule_group ];
                                $settings['e_display_conditions'] = [ json_encode( $nested_conditions ) ];
                            }
                        }

                        // Forcefully remove ALL old plugin keys (including orphans like widget 510aa04)
                        foreach ( array_keys( $settings ) as $key ) {
                            if ( stristr( $key, 'restrict_for_elementor' ) !== false || $key === 'user_role_selection' ) {
                                unset( $settings[$key] );
                                $modified = true;
                            }
                        }
                    }

                    if ( ! empty( $element['elements'] ) ) {
                        $migrate_elements( $element['elements'] );
                    }
                }
            };

            $migrate_elements( $data );

            if ( $modified ) {
                if ( $dry_run ) {
                    WP_CLI::log( "[Dry Run] Would clean up/migrate Post ID: {$row->post_id}" );
                } else {
                    if ( ! $skip_backup ) {
                        update_post_meta( $row->post_id, '_elementor_data_backup_legacy', $row->meta_value );
                    }

                    $updated_json = wp_slash( json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
                    update_metadata( 'post', $row->post_id, '_elementor_data', $updated_json );
                    
                    delete_post_meta( $row->post_id, '_elementor_css' );
                    WP_CLI::success( "Successfully cleaned/migrated Post ID: {$row->post_id}" );
                    $updated_posts++;
                }
            }
        }

        if ( ! $dry_run && $updated_posts > 0 ) {
            if ( class_exists( '\Elementor\Plugin' ) ) {
                \Elementor\Plugin::$instance->files_manager->clear_cache();
            }
            WP_CLI::success( "Migration and cleanup complete. Updated {$updated_posts} posts." );
        }
    }

    /**
     * 3. ROLLBACK COMMAND: wp rollback-elementor-restrictions
     */
    public function rollback( $args, $assoc_args ) {
        global $wpdb;

        $backups = $wpdb->get_results( "
            SELECT post_id, meta_value 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = '_elementor_data_backup_legacy'
        " );

        if ( empty( $backups ) ) {
            WP_CLI::error( "No backup meta (_elementor_data_backup_legacy) found to restore." );
            return;
        }

        foreach ( $backups as $backup ) {
            update_metadata( 'post', $backup->post_id, '_elementor_data', wp_slash( $backup->meta_value ) );
            delete_post_meta( $backup->post_id, '_elementor_css' );
            WP_CLI::success( "Restored original data for Post ID: {$backup->post_id}" );
        }

        if ( class_exists( '\Elementor\Plugin' ) ) {
            \Elementor\Plugin::$instance->files_manager->clear_cache();
        }
        WP_CLI::success( "Rollback complete." );
    }
}

$manager = new Elementor_Restriction_Manager();
WP_CLI::add_command( 'find-restrictions', [ $manager, 'scan' ] );
WP_CLI::add_command( 'migrate-elementor-restrictions', [ $manager, 'migrate' ] );
WP_CLI::add_command( 'rollback-elementor-restrictions', [ $manager, 'rollback' ] );
