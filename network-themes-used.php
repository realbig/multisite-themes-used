<?php
/*
Plugin Name: List All Themes on Network
Description: Shows all Parent and Child Themes in Use on a Multisite Network
Author: Eric Defore
Version: 1.0
Author URI: http://realbigmarketing.com
*/

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

if ( ! class_exists( 'MultisiteThemesUsedTable') ) {

    class MultisiteThemesUsedTable extends WP_List_Table {
        /**
     * Prepare the items for the table to process
     *
     * @return Void
     */
        public function prepare_items( $data ) {
            $columns = $this->get_columns();
            $hidden = $this->get_hidden_columns();
            $sortable = $this->get_sortable_columns();
            usort( $data, array( &$this, 'sort_data' ) );
            $perPage = 30;
            $currentPage = $this->get_pagenum();
            $totalItems = count($data);
            $this->set_pagination_args( array(
                'total_items' => $totalItems,
                'per_page'    => $perPage
            ) );
            $data = array_slice($data,(($currentPage-1)*$perPage),$perPage);
            $this->_column_headers = array($columns, $hidden, $sortable);
            $this->items = $data;
        }
        /**
     * Override the parent columns method. Defines the columns to use in your listing table
     *
     * @return Array
     */
        public function get_columns() {
            $columns = array(
                'name'          => 'Name',
                'author'       => 'Author',
                'version' => 'Version',
                'directory' => 'Directory',
                'parent'        => 'Parent Theme',
                'children' => 'Child Themes',
                'sites'    => 'Sites Used On',
            );
            return $columns;
        }
        /**
     * Define which columns are hidden
     *
     * @return Array
     */
        public function get_hidden_columns() {
            return array();
        }
        /**
     * Define the sortable columns
     *
     * @return Array
     */
        public function get_sortable_columns() {
            return array(
                'name' => array('name', false),
                'directory' => array('directory', false)
            );
        }
        /**
     * Define what data to show on each column of the table
     *
     * @param  Array $item        Data
     * @param  String $column_name - Current column name
     *
     * @return Mixed
     */
        public function column_default( $item, $column_name )
        {
            switch( $column_name ) {
                case 'name':
                case 'author':
                case 'version':
                case 'parent':
                case 'directory':
                    return $item[ $column_name ];
                case 'children':
                    echo '<ul>';
                        foreach ( $item[ $column_name ] as $list_item ) {
                            echo '<li>' . $list_item . '</li>';
                        }
                    echo '</ul>';
                    break;
                case 'sites':
                    echo '<ul>';
                        foreach ( $item[ $column_name ] as $list_item ) {
                            echo '<li><a href="//' . $list_item . '" target="_blank">' . $list_item. '</a></li>';
                        }
                    echo '</ul>';
                    break;
                default:
                    return print_r( $item, true );
            }
        }
        /**
     * Allows you to sort the data by the variables set in the $_GET
     *
     * @return Mixed
     */
        private function sort_data( $a, $b ) {
            // Set defaults
            $orderby = 'directory';
            $order = 'asc';
            // If orderby is set, use this as the sort column
            if(!empty($_GET['orderby']))
            {
                $orderby = $_GET['orderby'];
            }
            // If order is set use this as the order
            if(!empty($_GET['order']))
            {
                $order = $_GET['order'];
            }
            $result = strcmp( $a[$orderby], $b[$orderby] );
            if($order === 'asc')
            {
                return $result;
            }
            return -$result;
        }
    }
    
    

}

if ( ! class_exists( 'MultisiteThemesUsedList' ) ) {

    class MultisiteThemesUsedList {

        private static $instance = null;
        private static $plugin_id = 'multisite-themes-used-list';

        public static function get_instance() {

            if ( ! self::$instance ) {
                self::$instance = new MultisiteThemesUsedList();
                self::$instance->hooks();
            }

            return self::$instance;

        }

        private function hooks() {

            add_action( 'network_admin_menu', array( $this, 'add_themes_used_page' ) );

        }

        public function add_themes_used_page() {

            add_submenu_page( 'themes.php', 'All Parent and Child Themes Used on the Network', 'Themes Used', 'manage_sites', 'network-themes-used', array( $this, 'themes_used_page' ) );

        }

        public function themes_used_page() {

            $themes = $this->get_all_themes_in_use();
            
            $table = new MultisiteThemesUsedTable();
            $table->prepare_items( $themes );
            $table->display();
        }

        private function get_all_themes_in_use() {

            $multisite = wp_get_sites( array( 
                'limit' => 0,
            ) );

            $themes = array();

            foreach ( $multisite as $site) {

                // Switch to Individual Site Scope
                switch_to_blog( $site['blog_id'] );

                if ( is_child_theme() ) {

                    // We store both parent and child for each site

                    $child = wp_get_theme();
                    $themes[$child->stylesheet] = array( 
                        'name' => $child->Name,
                        'author' => $child->Author,
                        'parent' => $child->template,
                        'version' => $child->Version,
                        'directory' => $child->stylesheet,
                    );

                    // Store sites used on
                    $themes[$child->stylesheet]['sites'][] = $site['domain'];

                    $parent = wp_get_theme( get_template() );
                    $themes[$parent->stylesheet] = array(
                        'name' => $parent->Name,
                        'author' => $parent->Author,
                        'version' => $parent->Version,
                        'directory' => $parent->stylesheet,
                    );

                    // Store Child themes and sites used on
                    $themes[$parent->stylesheet]['children'][] = $child->Name;
                    $themes[$parent->stylesheet]['sites'][] = $site['domain'];

                }
                else {

                    // Store just the Theme in use

                    $parent = wp_get_theme();
                    $themes[$parent->stylesheet] = array(
                        'name' => $parent->Name,
                        'author' => $parent->Author,
                        'version' => $parent->Version,
                        'directory' => $parent->stylesheet,
                    );

                    // Store the sites used on
                    $themes[$parent->stylesheet]['sites'][] = $site['domain'];

                }

                // Restore Multisite Scope
                restore_current_blog();

            }

            return $themes;

        }

    }

    $instance = MultisiteThemesUsedList::get_instance();

}