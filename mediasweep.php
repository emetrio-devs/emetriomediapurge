<?php
/**
 * Plugin Name: MediaSweep
 * Plugin URI: https://github.com/emetrio-devs/mediasweep
 * Description: An efficient utility to clean up and sweep unneeded media files.
 * Version: 1.0.0
 * Author: Emetrio
 * Author URI: https://emetrio.com/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: mediasweep
 * Domain Path: /languages
 *
 * @package MediaSweep
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Include the WordPress List Table class if it hasn't been loaded
if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

/**
 * MediaSweep List Table Class
 */
class MediaSweep_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct([
            'singular' => 'unused_media',
            'plural'   => 'unused_medias',
            'ajax'     => false
        ]);
    }

    public function get_columns() {
        return [
            'cb'     => '<input type="checkbox" />',
            'file'   => __('File', 'mediasweep'),
            'type'   => __('Type', 'mediasweep'),
            'author' => __('Author', 'mediasweep'),
            'size'   => __('File Size', 'mediasweep'),
            'date'   => __('Uploaded Date', 'mediasweep'),
        ];
    }

    protected function get_sortable_columns() {
        return [
            'name' => ['name', false],
            'date' => ['date', true],
        ];
    }

    protected function column_cb($item) {
        return sprintf('<input type="checkbox" name="bulk-delete[]" value="%s" />', $item['ID']);
    }

    protected function column_file($item) {
        $delete_nonce = wp_create_nonce('mediasweep_delete_image');
        
        // $delete_url = sprintf(
        //     '?page=%s&action=%s&image_id=%s&_wpnonce=%s',
        //     esc_attr($_REQUEST['page']),
        //     'delete',
        //     absint($item['ID']),
        //     $delete_nonce
        // );
        $delete_url = admin_url('upload.php?page=mediasweep&action=delete&image_id=' . absint($item['ID']) . '&_wpnonce=' . $delete_nonce);

        $actions = [
            'delete' => sprintf('<a href="%s" class="submitdelete" onclick="return confirm(\'Are you sure?\')">%s</a>', esc_url($delete_url), esc_html__('Delete Permanently', 'mediasweep')),
        ];

        $mime = get_post_mime_type($item['ID']);
        $preview_html = '';
        if (strpos($mime, 'image/') === 0) {
            $thumb = wp_get_attachment_image($item['ID'], [60, 60]);
            if ($thumb) {
                $preview_html = $thumb;
            }
        }
        if (empty($preview_html)) {
            $icon_url = wp_mime_type_icon($item['ID']);
            $preview_html = sprintf('<img src="%s" class="attachment-60x60 size-60x60" alt="" style="max-width:60px; max-height:60px;" />', esc_url($icon_url));
        }

        return sprintf(
            '<div style="display:flex; align-items:center;">
                <div style="margin-right:15px; width:60px; height:60px; display:flex; align-items:center; justify-content:center; flex-shrink:0;">%1$s</div>
                <div>
                    <strong><p class="row-title" style="margin:0 0 2px 0; color:#183ad6;">%2$s</p></strong>
                    %3$s
                    %4$s
                </div>
            </div>',
            $preview_html,
            esc_html($item['name']),
            !empty($item['filename']) ? sprintf('<p style="margin:6px 0 2.6px; font-size:13px; color:#666; font-weight:normal;">%s</p>', esc_html($item['filename'])) : '',
            $this->row_actions($actions)
        );
    }

    protected function column_type($item) {
        return esc_html($item['type_label']);
    }

    protected function column_author($item) {
        return esc_html($item['author']);
    }

    protected function column_size($item) {
        return esc_html($item['size']);
    }

    protected function column_date($item) {
        return esc_html($item['date']);
    }

    protected function get_bulk_actions() {
        return ['bulk-delete' => esc_html__('Delete Permanently', 'mediasweep')];
    }

    protected function extra_tablenav($which) {
        if ($which === 'top') {
            // If a nonce exists on the page (bulk action form), verify it; read-only filters will continue to work.
            if ( isset( $_REQUEST['mediasweep_bulk_nonce'] ) ) {
                $bulk_nonce = sanitize_text_field( wp_unslash( $_REQUEST['mediasweep_bulk_nonce'] ) );
                wp_verify_nonce( $bulk_nonce, 'mediasweep_bulk_delete' );
            }
            
            $selected = isset($_REQUEST['media_type']) ? sanitize_text_field(wp_unslash($_REQUEST['media_type'])) : '';
            $filter_nonce = wp_create_nonce('mediasweep_filter');
            ?>
            <div class="alignleft actions">
                <select name="media_type">
                    <option value=""><?php esc_html_e('All Media Types', 'mediasweep'); ?></option>
                    <option value="image" <?php selected($selected, 'image'); ?>><?php esc_html_e('Images', 'mediasweep'); ?></option>
                    <option value="video" <?php selected($selected, 'video'); ?>><?php esc_html_e('Videos', 'mediasweep'); ?></option>
                    <option value="audio" <?php selected($selected, 'audio'); ?>><?php esc_html_e('Audio', 'mediasweep'); ?></option>
                    <option value="document" <?php selected($selected, 'document'); ?>><?php esc_html_e('Documents', 'mediasweep'); ?></option>
                </select>
                <input type="hidden" name="mediasweep_filter_nonce" value="<?php echo esc_attr( $filter_nonce ); ?>" />
                <?php submit_button(esc_html__('Filter', 'mediasweep'), 'button', 'filter_action', false); ?>
            </div>
            <?php
        }
    }

    public function prepare_items() {
        $this->_column_headers = [$this->get_columns(), [], $this->get_sortable_columns()];

        $unused_ids = MediaSweep_Plugin::get_unused_media_ids();
        $data = [];

        foreach ($unused_ids as $id) {
            $post = get_post($id);
            if (!$post) continue;

            $file_path = get_attached_file($id);
            $size_string = ($file_path && file_exists($file_path)) ? size_format(filesize($file_path)) : 'Unknown';

            $mime = get_post_mime_type($id);
            $type = 'document';
            $type_label = esc_html__('Document', 'mediasweep');
            
            if (strpos($mime, 'image/') === 0) {
                $type = 'image';
                $type_label = esc_html__('Image', 'mediasweep');
            } elseif (strpos($mime, 'video/') === 0) {
                $type = 'video';
                $type_label = esc_html__('Video', 'mediasweep');
            } elseif (strpos($mime, 'audio/') === 0) {
                $type = 'audio';
                $type_label = esc_html__('Audio', 'mediasweep');
            }

            $author_id = $post->post_author;
            $author_name = get_the_author_meta('display_name', $author_id);

            $data[] = [
                'ID'         => $id,
                'name'       => $post->post_title ? esc_html($post->post_title) : ($file_path ? esc_html(basename($file_path)) : esc_html__('Unknown File', 'mediasweep')),
                'filename'   => $file_path ? esc_html(basename($file_path)) : '',
                'size'       => $size_string,
                'date'       => get_the_date('Y-m-d H:i', $id),
                'type'       => $type,
                'type_label' => $type_label,
                'author'     => $author_name ? esc_html($author_name) : esc_html__('Unknown', 'mediasweep'),
            ];
        }

        // Handle Filter (verify filter nonce when filter button used)
        $media_type = '';
        $filter_nonce = isset( $_REQUEST['mediasweep_filter_nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['mediasweep_filter_nonce'] ) ) : '';
        if ( $filter_nonce && wp_verify_nonce( $filter_nonce, 'mediasweep_filter' ) ) {
            $media_type = isset( $_REQUEST['media_type'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['media_type'] ) ) : '';
        } else {
            $media_type = '';
        }
        if (!empty($media_type)) {
            $data = array_filter($data, function($item) use ($media_type) {
                return ($item['type'] === $media_type);
            });
        }

        // Handle Search (already sanitized with wp_unslash(), keep)
        $search = isset( $_REQUEST['s'] ) ? sanitize_text_field( trim( wp_unslash( $_REQUEST['s'] ) ) ) : '';
        if (!empty($search)) {
            $data = array_filter($data, function($item) use ($search) {
                return (stripos($item['name'], $search) !== false);
            });
        }

        // Handle Sorting (already sanitized)
        $orderby = isset($_REQUEST['orderby']) ? sanitize_text_field(wp_unslash($_REQUEST['orderby'])) : 'name';
        $order   = isset($_REQUEST['order']) ? strtolower(sanitize_text_field(wp_unslash($_REQUEST['order']))) : 'asc';

        $allowed_orderby = ['name', 'date'];
        if (!in_array($orderby, $allowed_orderby, true)) {
            $orderby = 'name';
        }
        $order = ($order === 'desc') ? 'desc' : 'asc';

        uasort($data, function($a, $b) use ($orderby, $order) {
            $aval = isset($a[$orderby]) ? $a[$orderby] : '';
            $bval = isset($b[$orderby]) ? $b[$orderby] : '';
            $result = strcmp($aval, $bval);
            return ($order === 'asc') ? $result : -$result;
        });

        // Pagination setup
        $per_page = 20;
        $current_page = $this->get_pagenum();
        $total_items = count($data);

        $this->items = array_slice($data, (($current_page - 1) * $per_page), $per_page);
        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page),
        ]);
    }
}

/**
 * Main Controller Setup
 */
class MediaSweep_Plugin {

    public function __construct() {
        add_action('admin_menu', [$this, 'add_plugin_menu']);
        add_action('admin_init', [$this, 'handle_table_actions']);
        add_action('delete_attachment', [$this, 'clear_unused_cache']);

        // Hooks for native Media Library integrations
        add_filter('wp_prepare_attachment_for_js', [$this, 'add_unused_status_to_js'], 10, 3);
        add_action('admin_print_footer_scripts', [$this, 'print_media_library_assets'], 99);
        add_filter('attachment_fields_to_edit', [$this, 'add_unused_msg_to_details_sidebar'], 10, 2);
        add_filter('media_row_actions', [$this, 'add_unused_label_to_list_view'], 10, 2);
    }

    public function add_plugin_menu() {
        add_media_page(
            esc_html__('MediaSweep | Emetrio', 'mediasweep'),
            esc_html__('Media Sweep', 'mediasweep'),
            'delete_posts',
            'mediasweep',
            [$this, 'render_admin_page']
        );
    }

    public function handle_table_actions() {
        if (!isset($_GET['page']) || $_GET['page'] !== 'mediasweep') return;

        // Ensure user has permission to delete posts
        if (!current_user_can('delete_posts')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'mediasweep'));
        }

        // Single delete (use wp_safe_redirect)
        if ( isset( $_REQUEST['action'] ) && $_REQUEST['action'] === 'delete' && isset( $_REQUEST['image_id'] ) ) {
            check_admin_referer( 'mediasweep_delete_image' );

            wp_delete_attachment( absint( wp_unslash( $_REQUEST['image_id'] ) ), true );
            $this->clear_unused_cache();

            wp_safe_redirect( admin_url( 'upload.php?page=mediasweep&message=deleted' ) );
            exit;
        }

        // Bulk delete (normalize and sanitize array)
        if ( ( isset( $_REQUEST['action'] ) && $_REQUEST['action'] === 'bulk-delete' ) || ( isset( $_REQUEST['action2'] ) && $_REQUEST['action2'] === 'bulk-delete' ) ) {
            check_admin_referer( 'mediasweep_bulk_delete', 'mediasweep_bulk_nonce' );

            $raw_bulk = isset( $_REQUEST['bulk-delete'] ) ? wp_unslash( $_REQUEST['bulk-delete'] ) : [];
            $bulk = is_array( $raw_bulk ) ? $raw_bulk : ( $raw_bulk ? [ $raw_bulk ] : [] );
            $ids = array_filter( array_map( 'absint', $bulk ) );
            foreach ( $ids as $id ) {
                wp_delete_attachment( $id, true );
            }
            $this->clear_unused_cache();

            wp_safe_redirect( admin_url( 'upload.php?page=mediasweep&message=bulk_deleted' ) );
            exit;
        }
    }

    /**
     * Clear the unused images cache transient
     */
    public function clear_unused_cache() {
        delete_transient('mediasweep_unused_ids');
    }

    /**
     * Core scan method shared across all views
     */
    public static function get_unused_media_ids() {
        global $wpdb;

        $unused_ids = get_transient('mediasweep_unused_ids');
        if (false !== $unused_ids) {
            return $unused_ids;
        }

        // 1. Get all attachment IDs (excluding trashed ones)
        $all_media = get_posts([
            'post_type'   => 'attachment',
            'post_status' => ['inherit','private','publish','draft','pending','future','auto-draft'],
            'fields'      => 'ids',
            'numberposts' => -1,
        ]);
        $all_media = array_map('intval', (array) $all_media);

        if (empty($all_media)) {
            set_transient('mediasweep_unused_ids', [], 12 * HOUR_IN_SECONDS);
            return [];
        }

        // 2. Filter out basic usage types (Featured Images & WooCommerce Galleries)
        $used_ids = [];
        
        $featured_posts = get_posts( [
            'post_type'   => 'any',
            'meta_query'  => [
                [
                    'key'     => '_thumbnail_id',
                    'compare' => 'EXISTS',
                ],
            ],
            'fields'      => 'ids',
            'numberposts' => -1,
        ]);
        foreach ( $featured_posts as $post_id ) {
            $thumb_id = get_post_meta( $post_id, '_thumbnail_id', true );
            if ( $thumb_id ) {
                $used_ids[] = intval( $thumb_id );
            }
        }

        // WooCommerce galleries — read the meta for posts that have meta key
        $product_posts = get_posts( [
            'post_type'   => 'any',
            'meta_key'    => '_product_image_gallery',
            'fields'      => 'ids',
            'numberposts' => -1,
        ]);
        foreach ( $product_posts as $pid ) {
            $gallery = get_post_meta( $pid, '_product_image_gallery', true );
            if ( ! empty( $gallery ) ) {
                $ids = array_map( 'intval', array_filter( array_map( 'trim', explode( ',', $gallery ) ) ) );
                $used_ids = array_merge( $used_ids, $ids );
            }
        }

        $used_ids = array_unique(array_map('intval', $used_ids));
        $potential_unused_ids = array_diff($all_media, $used_ids);

        if (empty($potential_unused_ids)) {
            set_transient('mediasweep_unused_ids', [], 12 * HOUR_IN_SECONDS);
            return [];
        }

        // 3. Deep content scan for inline post/product/page placements
        $final_unused_ids = [];
        foreach ($potential_unused_ids as $id) {
            $file_path = get_attached_file($id);
            if (!$file_path) continue;
            
            $filename = basename($file_path);

            $found = get_posts( [
                's'           => $filename,
                'post_status' => [ 'publish', 'private', 'draft', 'pending', 'future' ],
                'post_type'   => 'any',
                'fields'      => 'ids',
                'numberposts' => 1,
            ] );
            if ( empty( $found ) ) {
                $final_unused_ids[] = $id;
            }
        }

        // Cast to integers to support strict type comparison in in_array checks
        $final_unused_ids = array_map('intval', $final_unused_ids);
        set_transient('mediasweep_unused_ids', $final_unused_ids, 12 * HOUR_IN_SECONDS);

        return $final_unused_ids;
    }

    /**
     * Check if a single attachment is unused
     */
    public static function is_media_unused($id) {
        $unused_ids = self::get_unused_media_ids();
        return is_array($unused_ids) && in_array((int)$id, $unused_ids, true);
    }

    /**
     * Add unused flag to JavaScript attachment models (Backbone)
     */
    public function add_unused_status_to_js($response, $attachment, $meta) {
        $response['is_unused'] = self::is_media_unused($attachment->ID);
        return $response;
    }

    /**
     * Print CSS and Backbone JavaScript adjustments directly in the admin footer.
     * This is 100% foolproof and avoids script dependency issues across different pages.
     */
    public function print_media_library_assets() {
        ?>
        <style>
            /* Grid View: Gold Dot Indicator & Border */
            /* .attachments .attachment-preview.mediasweep-unused-item {
                box-shadow: inset 0 0 20px 3px rgba(255, 215, 0, 0.25) !important;
            } */
            .attachments .attachment-preview.mediasweep-unused-item .filename,
            .attachments li.attachment:has(div.mediasweep-unused-item)::after {
                background-color: #ffd7004d !important;
                color: #000 !important;
            }
            .attachments .attachment-preview.mediasweep-unused-item::after {
                content: '' !important;
                position: absolute !important;
                top: 10px !important;
                right: 10px !important;
                left: auto !important; /* Force reset left to override core styles */
                width: 10px !important; /* Force exact width */
                height: 10px !important; /* Force exact height */
                background-color: #ffd700 !important;
                border: none !important;
                border-radius: 50% !important;
                z-index: 9999 !important;
                box-shadow: 0 0 4px rgba(0, 0, 0, 0.5) !important;
                pointer-events: none !important;
                display: block !important;
            }
            /* Style override for attachment details sidebar field */
            .compat-field-mediasweep_status td {
                vertical-align: middle;
            }
        </style>
        <script>
            (function($) {
                // Method 1: Modify the Backbone script template before initialization
                function patchAttachmentTemplate() {
                    var $template = $('#tmpl-attachment');
                    if ($template.length) {
                        var html = $template.html();
                        if (html && html.indexOf('mediasweep-unused-item') === -1) {
                            html = html.replace(
                                'class="attachment-preview',
                                'class="attachment-preview <# if ( data.is_unused ) { #>mediasweep-unused-item<# } #>'
                            );
                            $template.html(html);
                        }
                    }
                }

                // Method 2: Fallback direct prototype override (targeting .attachment-preview)
                function initMediaSweepGrid() {
                    if (typeof wp !== 'undefined' && wp.media && wp.media.view && wp.media.view.Attachment) {
                        var originalRender = wp.media.view.Attachment.prototype.render;
                        wp.media.view.Attachment.prototype.render = function() {
                            originalRender.apply(this, arguments);
                            if (this.model.get('is_unused')) {
                                this.$el.find('.attachment-preview').addClass('mediasweep-unused-item');
                                this.$el.attr('title', '<?php echo esc_js(esc_html__('Unused media', 'mediasweep')); ?>');
                            }
                        };

                        if (wp.media.view.Attachment.Library) {
                            var originalLibRender = wp.media.view.Attachment.Library.prototype.render;
                            wp.media.view.Attachment.Library.prototype.render = function() {
                                originalLibRender.apply(this, arguments);
                                if (this.model.get('is_unused')) {
                                    this.$el.find('.attachment-preview').addClass('mediasweep-unused-item');
                                    this.$el.attr('title', '<?php echo esc_js(esc_html__('Unused media', 'mediasweep')); ?>');
                                }
                            };
                        }
                        return true;
                    }
                    return false;
                }

                // Execute immediately and on document ready
                patchAttachmentTemplate();
                initMediaSweepGrid();

                $(document).ready(function() {
                    patchAttachmentTemplate();
                    initMediaSweepGrid();
                });

                // Polling fallback to capture late-loading scripts
                var attempts = 0;
                var interval = setInterval(function() {
                    attempts++;
                    patchAttachmentTemplate();
                    if (initMediaSweepGrid() || attempts > 30) {
                        clearInterval(interval);
                    }
                }, 100);
            })(jQuery);
        </script>
        <?php
    }

    /**
     * List View: Show colored warning below media name in actions row
     */
    public function add_unused_label_to_list_view($actions, $post) {
        if (self::is_media_unused($post->ID)) {
            $actions['mediasweep_status'] = sprintf(
                '<span style="color: #ffd700; font-weight: 500; font-size: 13px; pointer-events: none; display: inline-block; margin-top: 4px;">%s</span>',
                esc_html__( 'Unused Media', 'mediasweep' )
            );
        }
        return $actions;
    }

    /**
     * Details Modal: Show status notice inside Sidebar details
     */
    public function add_unused_msg_to_details_sidebar($form_fields, $post) {
        if (self::is_media_unused($post->ID)) {
            $form_fields['mediasweep_status'] = [
                'label' => esc_html__( 'Media Sweep:', 'mediasweep' ),
                'input' => 'html',
                'html'  => sprintf(
                    '<span style="border-left: 4px solid #ffd700; background: #ffd7001a; padding: 6px 10px; display: inline-block;">%s</span>',
                    esc_html__( 'Media is not in use, can be swept.', 'mediasweep' )
                ),
            ];
        }
        return $form_fields;
    }

    public function render_admin_page() {
        $this->clear_unused_cache();
        $table = new MediaSweep_List_Table();
        $table->prepare_items();
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e('Media Sweep | Emetrio', 'mediasweep'); ?></h1>
            <p class="notice notice-warning inline"><strong><?php esc_html_e('Media Sweep Notice:', 'mediasweep'); ?></strong> <?php esc_html_e('For safety, please make a full backup of your website and media library before executing any deletions. Deleted media items are permanently removed and cannot be recovered.', 'mediasweep'); ?></p>

            <?php
            $message = isset( $_GET['message'] ) ? sanitize_text_field( wp_unslash( $_GET['message'] ) ) : '';
            if ( $message ) : ?>
                <div id="message" class="updated notice is-dismissible"><p>
                    <?php
                        if ( 'deleted' === $message ) {
                            esc_html_e( 'Item permanently removed.', 'mediasweep' );
                        } elseif ( 'bulk_deleted' === $message ) {
                            esc_html_e( 'Selected items permanently removed.', 'mediasweep' );
                        }
                    ?>
                </p></div>
            <?php endif; ?>

            <form method="get" action="">
                <input type="hidden" name="page" value="mediasweep" />
                <?php wp_nonce_field('mediasweep_bulk_delete', 'mediasweep_bulk_nonce'); ?>
                <?php $table->search_box(esc_html__('Search Media', 'mediasweep'), 'search_id'); ?>
                <?php $table->display(); ?>
            </form>
        </div>
        <?php
    }
}

new MediaSweep_Plugin();
