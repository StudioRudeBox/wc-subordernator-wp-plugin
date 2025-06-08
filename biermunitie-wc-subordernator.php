<?php
/**
 * Plugin Name: Studio Rude Box WC Subordernator
 * Plugin URI: https://github.com/StudioRudeBox/wc-subordernator-wp-plugin
 * Description: Add the ability to link a WooCommerce order to another order, creating a parent–suborder relationship.
 * Version: 2.1.1
 * Author: Studio Rude Box
 * Author URI: https://studiorudebox.nl
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: srb-subordernator
 * Domain Path: /languages
 */


/**
 * Add a feature to WP-Admin by creating a custom number field in an order
 * This adds the functionality of linking to another main order ID and create internal sub orders
 * 
 * Only use this code in WP-Admin
 */

if(is_admin())
{
    /**
     * Define the plugin post meta parameter name
     */
    define('SRB_POST_META_PARAM_NAME', 'srb_subordernator_order_reference' );
    
    /**
     * Add CSS style to plugin
     * 
     * @return void             return nothing
     */
    
    function srb_subordernator_enqueue_plugin_css():void
    {
        wp_enqueue_style('srb-subordernator', plugin_dir_url(__FILE__) . 'style.css');        
    }
    add_action('admin_enqueue_scripts', 'srb_subordernator_enqueue_plugin_css');
  
    /**
     * Add the field in a meta box
     * 
     * @param object $order     WooCommerce WC_Order object
     * @return void             return nothing
     */

    function srb_subordernator_add_suborder_field($order): void
    {
        // get order info
        $current_order_id = $order->get_id();
        $selected_order_id = get_post_meta($current_order_id, SRB_POST_META_PARAM_NAME, true);

        // add a section to the right column
        echo '<p class="form-field form-field-wide">';
        echo '<label for="srb_subordernator_order_reference">' . __('Link to a parent order ID (optional):', 'srb-subordernator') . '</label>';

        // display input field
        printf('<input type="number" name="srb_subordernator_order_reference" value="%s" min="0" placeholder="order ID" />',
            $selected_order_id
        );
        
        echo '</p>';
    }
    add_action('woocommerce_admin_order_data_after_order_details', 'srb_subordernator_add_suborder_field');
   
    /**
     * Save the selected value to the current order (optional)
     * 
     * @param int $order_id     WooCommerce order id of WC_Order object
     * @return void             return nothing
     */

    function srb_subordernator_save_suborder_field_value($order_id): void
    {
        $post_data = $_POST[SRB_POST_META_PARAM_NAME];
        
        if (isset($post_data) && is_numeric($post_data))
        {
            update_post_meta($order_id, SRB_POST_META_PARAM_NAME, sanitize_text_field($_POST[SRB_POST_META_PARAM_NAME]));
        }
    }
    add_action('woocommerce_process_shop_order_meta', 'srb_subordernator_save_suborder_field_value');

    /**
     * Add custom columns to the order list for sub orders
     * 
     * @since 1.2 new function execution and added the ID column
     * 
     * @param array $columns    Wordpress current array with columns
     * @return array            return new array including the added column
     */
        
    function srb_subordernator_add_custom_columns_head($columns): array
    {
        $new_columns = [];     
        
        // add the ID column after the checkbox column
        foreach ($columns as $key => $column)
        {
            $new_columns[$key] = $column;
            if ($key === 'cb')
            {
                $new_columns['srb_subordernator_order_id'] = 'ID';
            }
        }
        
        // add main order column
        $new_columns['srb_subordernator_sub_order'] = __('Connected order', 'srb-subordernator');
       
        return $new_columns;
    }
    add_filter('manage_edit-shop_order_columns', 'srb_subordernator_add_custom_columns_head', 20);

    /**
     * Add data to the new columns for sub orders
     * 
     * @since 2.0              now with emoticons :)
     * 
     * @param string $column   Wordpress current column name
     * @param int $post_id     Wordpress post ID
     * 
     * @return void            return nothing
     */
    
    function srb_subordernator_add_custom_columns_content($column, $post_id): void
    {
        // get order ID of main order (only available for sub orders)
        $main_order_id = get_post_meta($post_id, SRB_POST_META_PARAM_NAME, true);
        $is_suborder = is_numeric($main_order_id);

        // fill columns for all orders
        if($column === "srb_subordernator_order_id")
        {
            echo $post_id;
        }

        // add icon to main and sub orders
        if($column === "order_number")
        {
            if($is_suborder)
            {
                echo "&emsp;➡️ ";
            }
            else
            {
                echo "📦 ";
            }
        }
        
        // add sub order info in custom column
        if($is_suborder && $column === "srb_subordernator_sub_order")
        {
            $main_order = wc_get_order($main_order_id);
            if ($main_order)
            {
                printf('<mark class="order-status"><a class="srb-subordernator-btn" href="%s" title="%s">%s</a></mark>',
                    get_edit_post_link($main_order_id),
                    __('Open the parent orders', 'srb-subordernator'),
                    "#" . $main_order->get_order_number()
                );
            }     
        }
    }
    add_action('manage_shop_order_posts_custom_column', 'srb_subordernator_add_custom_columns_content', 10, 2);

    /**
     * Add filter on top of order table to order on main or sub orders
     * 
     * @return void            return nothing
     */

    function srb_subordernator_add_suborders_filter(): void
    {
        global $typenow;
        if ($typenow == 'shop_order')
        {
            $selected = isset($_GET['main_sub_order_filter']) ? $_GET['main_sub_order_filter'] : '';
            $options = [
                'main' => __('Main orders', 'srb-subordernator'),
                'sub' => __('Sub orders', 'srb-subordernator')
            ];            

            echo '<select name="main_sub_order_filter">';
            echo '<option value="" ' . selected($selected, '', false) . '>' . __('All orders', 'srb-subordernator') . '</option>';
            
            foreach ($options as $key => $label)
            {
                echo '<option value="' . esc_attr($key) . '" ' . selected($selected, $key, false) . '>' . esc_html($label) . '</option>';
            }
            echo '</select>';
        }
    }
    add_action('restrict_manage_posts', 'srb_subordernator_add_suborders_filter');

    /**
     * Change query based on custom filter
     * 
     * @param object $query    Wordpress query object
     * @return void            return nothing
     */
    
    function srb_subordernator_filter_query($query): void
    {
        global $pagenow;

        if ($pagenow == 'edit.php' && isset($_GET['post_type']) && $_GET['post_type'] == 'shop_order' && isset($_GET['main_sub_order_filter']))
        {
            $main_sub_order_filter = sanitize_text_field($_GET['main_sub_order_filter']);
            $meta_query = $query->get('meta_query');

            // check if meta query is empty
            if (empty($meta_query))
            {
                $meta_query = [];
            }

            // check if custom sub / main order filter is used and add items to the meta query
            if ($main_sub_order_filter == 'sub')
            {
                $meta_query[] = [
                    'relation' => 'AND',
                    [
                        'key' => SRB_POST_META_PARAM_NAME,
                        'value' => '',
                        'compare' => '!=',
                    ],
                    [
                        'key' => SRB_POST_META_PARAM_NAME,
                        'compare' => 'EXISTS',
                    ],
                ];
            }
            elseif ($main_sub_order_filter == 'main')
            {
                $meta_query[] = [
                    'relation' => 'OR',
                    [
                        'key' => SRB_POST_META_PARAM_NAME,
                        'compare' => 'NOT EXISTS',
                    ],
                    [
                        'key' => SRB_POST_META_PARAM_NAME,
                        'value' => '',
                        'compare' => '=',
                    ],
                ];
            }

            // set new meta query
            $query->set('meta_query', $meta_query);
        }
    }
    add_action('pre_get_posts', 'srb_subordernator_filter_query');  
}