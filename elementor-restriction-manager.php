<?php
/**
 * Plugin Name:       SFLWA Elementor Restriction Updater
 * Plugin URI:        https://github.com/sflwa/elementor-restriction-manager/
 * Description:       Automated utility to scan, back up, migrate, and clean up legacy "Restrict for Elementor" settings into native Elementor Display Conditions via WP-CLI or WP-Admin.
 * Version:           1.0.0
 * Author:            South Florida Web Advisors
 * Author URI:        https://southfloridawebadvisors.com/
 * Text Domain:       sflwa-elementor-restriction-updater
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 5.8
 * Requires PHP:      7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class SFLWA_Elementor_Restriction_Updater {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'sflwa_eru_add_menu' ] );
        
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            WP_CLI::add_command( 'find-restrictions', [ $this, 'sflwa_eru_cli_scan' ] );
            WP_CLI::add_command( 'migrate-elementor-restrictions', [ $this, 'sflwa_eru_cli_migrate' ] );
            WP_CLI::add_command( 'rollback-elementor-restrictions', [ $this, 'sflwa_eru_cli_rollback' ] );
            WP_CLI::add_command( 'cleanup-restriction-backups', [ $this, 'sflwa_eru_cli_cleanup_backups' ] );
        }
    }

    /**
     * Create Dashboard Menu under Tools -> Elementor Migration
     */
    public function sflwa_eru_add_menu() {
        add_management_page(
            __( 'SFLWA Elementor Restriction Updater', 'sflwa-elementor-restriction-updater' ),
            __( 'Elementor Migration', 'sflwa-elementor-restriction-updater' ),
            'manage_options',
            'sflwa-elementor-restriction-updater',
            [ $this, 'sflwa_eru_render_admin_page' ]
        );
    }

    /**
     * Render WP-Admin Tools Dashboard UI
     */
    public function sflwa_eru_render_admin_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'sflwa-elementor-restriction-updater' ) );
        }

        // Handle Admin UI Action Submissions
        if ( isset( $_POST['sflwa_eru_action'] ) ) {
            check_admin_referer( 'sflwa_eru_migration_action', 'sflwa_eru_nonce' );

            $action = sanitize_text_field( wp_unslash( $_POST['sflwa_eru_action'] ) );

            switch ( $action ) {
                case 'migrate':
                    $result = $this->sflwa_eru_run_migration();
                    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sprintf( __( 'Migration finished. Updated %d post(s).', 'sflwa-elementor-restriction-updater' ), $result ) ) . '</p></div>';
                    break;

                case 'rollback':
                    $result = $this->sflwa_eru_run_rollback();
                    echo '<div class="notice notice-info is-dismissible"><p>' . esc_html( sprintf( __( 'Rollback complete. Restored %d post(s).', 'sflwa-elementor-restriction-updater' ), $result ) ) . '</p></div>';
                    break;

                case 'cleanup':
                    $result = $this->sflwa_eru_run_cleanup();
                    echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html( sprintf( __( 'Deleted %d temporary backup record(s).', 'sflwa-elementor-restriction-updater' ), $result ) ) . '</p></div>';
                    break;
            }
        }

        $scan_data    = $this->sflwa_eru_get_scan_results();
        $backup_count = $this->sflwa_eru_get_backup_count();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <p><?php esc_html_e( 'Scan and migrate legacy Restrict for Elementor settings to native Elementor Display Conditions.', 'sflwa-elementor-restriction-updater' ); ?></p>

            <hr />

            <h2><?php esc_html_e( '1. Scan Results', 'sflwa-elementor-restriction-updater' ); ?></h2>
            <?php if ( empty( $scan_data ) ) : ?>
                <div class="notice notice-success inline"><p><?php esc_html_e( 'No active legacy restrictions found in element settings.', 'sflwa-elementor-restriction-updater' ); ?></p></div>
            <?php else : ?>
                <table class="widefat fixed striped" style="max-width: 1000px;">
                    <thead>
                        <tr>
                            <th style="width: 10%;"><?php esc_html_e( 'Post ID', 'sflwa-elementor-restriction-updater' ); ?></th>
                            <th style="width: 35%;"><?php esc_html_e( 'Title', 'sflwa-elementor-restriction-updater' ); ?></th>
                            <th style="width: 15%;"><?php esc_html_e( 'Element ID', 'sflwa-elementor-restriction-updater' ); ?></th>
                            <th style="width: 15%;"><?php esc_html_e( 'Type', 'sflwa-elementor-restriction-updater' ); ?></th>
                            <th style="width: 25%;"><?php esc_html_e( 'Restriction Mode', 'sflwa-elementor-restriction-updater' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $scan_data as $item ) : ?>
                            <tr>
                                <td><?php echo esc_html( $item['Post ID'] ); ?></td>
                                <td><strong><a href="<?php echo esc_url( get_edit_post_link( $item['Post ID'] ) ); ?>"><?php echo esc_html( $item['Title'] ); ?></a></strong></td>
                                <td><code><?php echo esc_html( $item['Element ID'] ); ?></code></td>
                                <td><?php echo esc_html( $item['Type'] ); ?></td>
                                <td><?php echo esc_html( $item['Restriction'] ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <br /><hr />

            <h2><?php esc_html_e( '2. Migration Actions', 'sflwa-elementor-restriction-updater' ); ?></h2>
            <form method="post" style="display: inline-block; margin-right: 10px;">
                <?php wp_nonce_field( 'sflwa_eru_migration_action', 'sflwa_eru_nonce' ); ?>
                <input type="hidden" name="sflwa_eru_action" value="migrate">
                <?php submit_button( __( 'Run Live Migration', 'sflwa-elementor-restriction-updater' ), 'primary', 'submit', false ); ?>
            </form>

            <?php if ( $backup_count > 0 ) : ?>
                <form method="post" style="display: inline-block; margin-right: 10px;">
                    <?php wp_nonce_field( 'sflwa_eru_migration_action', 'sflwa_eru_nonce' ); ?>
                    <input type="hidden" name="sflwa_eru_action" value="rollback">
                    <?php submit_button( sprintf( __( 'Rollback Changes (%d Backup Found)', 'sflwa-elementor-restriction-updater' ), $backup_count ), 'secondary', 'submit', false ); ?>
                </form>

                <form method="post" style="display: inline-block;">
                    <?php wp_nonce_field( 'sflwa_eru_migration_action', 'sflwa_eru_nonce' ); ?>
                    <input type="hidden" name="sflwa_eru_action" value="cleanup">
                    <?php submit_button( __( 'Delete Backup Meta', 'sflwa-elementor-restriction-updater' ), 'delete', 'submit', false, [ 'onclick' => 'return confirm("' . esc_js( __( 'Are you sure you want to permanently delete backup keys?', 'sflwa-elementor-restriction-updater' ) ) . '");' ] ); ?>
                </form>
            <?php endif; ?>
        </div>
        <?php
    }

    /* ==========================================================================
       CORE ENGINE LOGIC (Shared between CLI and Admin UI)
       ========================================================================== */

    private function sflwa_eru_get_scan_results() {
        global $wpdb;

        $results = $wpdb->get_results( $wpdb->prepare( "
            SELECT post_id, meta_value 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = %s 
            AND meta_value LIKE %s
        ", '_elementor_data', '%restrict_for_elementor%' ) );

        if ( empty( $results ) ) return [];

        $found_elements = [];

        foreach ( $results as $row ) {
            $data = json_decode( $row->meta_value, true );
            if ( ! is_array( $data ) ) continue;

            $scan_elements = function( $elements ) use ( &$scan_elements, &$found_elements, $row ) {
                foreach ( $elements as $element ) {
                    $settings = $element['settings'] ?? [];

                    $has_active = ! empty( $settings['restrict_for_elementor_show_to'] ) || 
                                 ( isset( $settings['user_role_selection'] ) && ! empty( $settings['user_role_selection'] ) );

                    $has_orphan = false;
                    foreach ( $settings as $key => $val ) {
                        if ( stristr( $key, 'restrict_for_elementor' ) !== false ) {
                            $has_orphan = true;
                            break;
                        }
                    }

                    if ( $has_active || $has_orphan ) {
                        $found_elements[] = [
                            'Post ID'     => $row->post_id,
                            'Title'       => get_the_title( $row->post_id ),
                            'Element ID'  => $element['id'] ?? 'N/A',
                            'Type'        => ($element['elType'] === 'widget') ? ($element['widgetType'] ?? 'widget') : $element['elType'],
                            'Restriction' => $settings['restrict_for_elementor_show_to'] ?? ($has_orphan ? 'Orphaned Meta' : 'Enabled'),
                        ];
                    }

                    if ( ! empty( $element['elements'] ) ) {
                        $scan_elements( $element['elements'] );
                    }
                }
            };

            $scan_elements( $data );
        }

        return $found_elements;
    }

    private function sflwa_eru_run_migration( $dry_run = false, $skip_backup = false ) {
        global $wpdb;

        $results = $wpdb->get_results( $wpdb->prepare( "
            SELECT post_id, meta_value 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = %s 
            AND meta_value LIKE %s
        ", '_elementor_data', '%restrict_for_elementor%' ) );

        if ( empty( $results ) ) return 0;

        $updated_posts = 0;

        foreach ( $results as $row ) {
            $data = json_decode( $row->meta_value, true );
            if ( ! is_array( $data ) ) continue;

            $modified = false;

            $migrate_elements = function( &$elements ) use ( &$migrate_elements, &$modified ) {
                foreach ( $elements as &$element ) {
                    if ( isset( $element['settings'] ) && is_array( $element['settings'] ) ) {
                        $settings = &$element['settings'];

                        if ( ! empty( $settings['restrict_for_elementor_show_to'] ) ) {
                            $show_to    = $settings['restrict_for_elementor_show_to'];
                            $rule_group = [];

                            if ( $show_to === 'user_role' && ! empty( $settings['user_role_selection'] ) ) {
                                $rule_group[] = [
                                    'condition'  => 'user_role',
                                    'comparator' => 'is_one_of',
                                    'roles'      => array_values( (array) $settings['user_role_selection'] ),
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
                                $settings['e_display_conditions'] = [ json_encode( [ $rule_group ] ) ];
                            }
                        }

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

            if ( $modified && ! $dry_run ) {
                if ( ! $skip_backup ) {
                    update_post_meta( $row->post_id, '_elementor_data_backup_legacy', $row->meta_value );
                }

                $updated_json = wp_slash( json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
                update_metadata( 'post', $row->post_id, '_elementor_data', $updated_json );
                delete_post_meta( $row->post_id, '_elementor_css' );
                $updated_posts++;
            }
        }

        if ( ! $dry_run && $updated_posts > 0 && class_exists( '\Elementor\Plugin' ) ) {
            \Elementor\Plugin::$instance->files_manager->clear_cache();
        }

        return $updated_posts;
    }

    private function sflwa_eru_run_rollback() {
        global $wpdb;

        $backups = $wpdb->get_results( $wpdb->prepare( "
            SELECT post_id, meta_value 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = %s
        ", '_elementor_data_backup_legacy' ) );

        if ( empty( $backups ) ) return 0;

        $count = 0;
        foreach ( $backups as $backup ) {
            update_metadata( 'post', $backup->post_id, '_elementor_data', wp_slash( $backup->meta_value ) );
            delete_post_meta( $backup->post_id, '_elementor_css' );
            $count++;
        }

        if ( class_exists( '\Elementor\Plugin' ) && $count > 0 ) {
            \Elementor\Plugin::$instance->files_manager->clear_cache();
        }

        return $count;
    }

    private function sflwa_eru_run_cleanup() {
        global $wpdb;

        return $wpdb->query( $wpdb->prepare( "
            DELETE FROM {$wpdb->postmeta} 
            WHERE meta_key = %s
        ", '_elementor_data_backup_legacy' ) );
    }

    private function sflwa_eru_get_backup_count() {
        global $wpdb;

        return (int) $wpdb->get_var( $wpdb->prepare( "
            SELECT COUNT(*) 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = %s
        ", '_elementor_data_backup_legacy' ) );
    }

    /* ==========================================================================
       WP-CLI HANDLERS
       ========================================================================== */

    public function sflwa_eru_cli_scan( $args, $assoc_args ) {
        $found_elements = $this->sflwa_eru_get_scan_results();
        if ( ! empty( $found_elements ) ) {
            WP_CLI\Utils\format_items( 'table', $found_elements, [ 'Post ID', 'Title', 'Element ID', 'Type', 'Restriction' ] );
        } else {
            WP_CLI::success( 'No active restrictions found inside element settings.' );
        }
    }

    public function sflwa_eru_cli_migrate( $args, $assoc_args ) {
        $dry_run     = isset( $assoc_args['dry-run'] );
        $skip_backup = isset( $assoc_args['skip-backup'] );

        $updated = $this->sflwa_eru_run_migration( $dry_run, $skip_backup );
        if ( $dry_run ) {
            WP_CLI::log( '[Dry Run] Migration complete check performed.' );
        } else {
            WP_CLI::success( "Migration complete. Updated {$updated} post(s)." );
        }
    }

    public function sflwa_eru_cli_rollback( $args, $assoc_args ) {
        $count = $this->sflwa_eru_run_rollback();
        if ( $count > 0 ) {
            WP_CLI::success( "Rollback complete. Restored {$count} post(s)." );
        } else {
            WP_CLI::error( 'No backup records found to restore.' );
        }
    }

    public function sflwa_eru_cli_cleanup_backups( $args, $assoc_args ) {
        $count = $this->sflwa_eru_run_cleanup();
        WP_CLI::success( "Deleted {$count} backup record(s)." );
    }
}

new SFLWA_Elementor_Restriction_Updater();
