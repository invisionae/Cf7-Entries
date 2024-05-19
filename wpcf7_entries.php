<?php

/*
Plugin Name: Contact Form 7 Entries
Plugin URI: http://www.ajaxy.org/
Description: Records all Contact Form 7 entries in the database.
Author: Naji Amer - @n-for-all
Author URI: http://ajaxy.org/
Text Domain: cf7-entiries
Version: 1.0.0
*/

/* Not tested with older versions of wpcf7, lower the version number at your own risk */
define("WPCF7_ENTRIES_REQUIRED_VERSION", "5.0");


define("WPCF7_ENTRIES_TEXT_DOMAIN", "cf7-entries");
define("WPCF7_ENTRIES_PLUGIN_URL", plugins_url('', __FILE__));

class WPCF7_Entries
{
    private $license = null;
    private $posted_data = null;
    public function __construct()
    {
        if ( ! class_exists( 'WPCF7_Contact_Form_List_Table' ) ) {
			require_once 'includes/table/wpcf7_list_table.class.php';
		}
        add_action('init', array(&$this, 'init'));
        add_action('wpcf7_init', array(&$this, 'wpcf7_init'));
        add_action('admin_menu', array(&$this, '_menu'));
        add_action('restrict_manage_posts', array(&$this, 'restrict_manage_posts'), 10, 1);

        add_action('save_post', array(&$this, 'save_entry'), 10, 3 );
    }
    public function filters()
    {
        add_filter('wpcf7_mail_components', array($this, 'handle_submission'), 999, 3);
        add_filter('wpcf7_posted_data', array($this, 'save_posted'), 999, 3);
        add_filter('manage_posts_columns', array($this, 'manage_posts_columns'), 10, 2);
        add_filter('parse_query', array($this, 'form_posts_filter') );
    }
    public function actions()
    {
        add_action('load-post.php', array($this, 'init_meta_box'));
        add_action('load-post-new.php', array($this, 'init_meta_box'));
        add_action( 'manage_posts_custom_column' , array(&$this,'custom_columns'), 10, 2 );
        add_action('admin_enqueue_scripts', array(&$this, 'admin_scripts'));
    }

    public function version_notice__error()
    {
        $class = 'notice notice-error';
        $message = __('Contact form 7 Entries requires Contact Form 7 version 4.4 or higher.', 'sample-text-domain');

        printf('<div class="%1$s"><p>%2$s</p></div>', $class, $message);
    }
    public function _menu() {
        add_submenu_page('wpcf7', 'Contact Form 7 Entries', 'Entries', 'manage_options', 'edit.php?post_type=wpcf7-entry');
    }
    public function init()
    {
        register_post_type( 'wpcf7-entry',
            array(
                'labels' => array(
                'name' => _x( 'Entries', 'post type general name', WPCF7_ENTRIES_TEXT_DOMAIN ),
                'singular_name' => _x( 'Entry', 'post type singular name', WPCF7_ENTRIES_TEXT_DOMAIN ),
                'add_new' => __( 'New Entry', WPCF7_ENTRIES_TEXT_DOMAIN ),
                'add_new_item' => __( 'New Entry', WPCF7_ENTRIES_TEXT_DOMAIN ),
                'edit_item' => __( 'Edit Entry', WPCF7_ENTRIES_TEXT_DOMAIN ),
                'new_item' => __( 'New Entry', WPCF7_ENTRIES_TEXT_DOMAIN ),
                'view_item' => __( 'View Entry', WPCF7_ENTRIES_TEXT_DOMAIN ),
                'search_items' => __( 'Search Entries', WPCF7_ENTRIES_TEXT_DOMAIN ),
                'not_found' => __( 'Nothing Found', WPCF7_ENTRIES_TEXT_DOMAIN ),
                'not_found_in_trash' => __( 'Nothing found in Trash', WPCF7_ENTRIES_TEXT_DOMAIN ),
                'parent_item_colon' => ''
            ),
            'description' => "Entries",
            'public' => false, // All the relevant settings below inherit from this setting
            'exclude_from_search' => true, // When a search is conducted through search.php, should it be excluded?
            'publicly_queryable' => false, // When a parse_request() search is conducted, should it be included?
            'show_ui' => true, // Should the primary admin menu be displayed?
            'show_in_nav_menus' => false, // Should it show up in Appearance > Menus?
            'show_in_menu' => false, // This inherits from show_ui, and determines *where* it should be displayed in the admin
            'show_in_admin_bar' => false, // Should it show up in the toolbar when a user is logged in?
            'rewrite' => array( 'slug' => 'wpcf7-entry' ),
            'capability_type' => 'post',
            'capabilities' => array(
                'create_posts' => false,
                'edit_posts' => true
            ),
            'map_meta_cap' => true,
            'supports' => array('title')
        ));
        register_post_status( 'approved', array(
            'label'                     => _x( 'Approved', 'post' ),
            'public'                    => true,
            'exclude_from_search'       => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop( 'Approved <span class="count">(%s)</span>', 'Approved <span class="count">(%s)</span>' ),
        ) );
    }
    public function wpcf7_init()
    {
        if (version_compare(WPCF7_VERSION, WPCF7_ENTRIES_REQUIRED_VERSION) >= 0) {
            $this->filters();
            $this->actions();
        } else {
            add_action('admin_notices', array(&$this, 'version_notice__error'));
        }
    }

    public function restrict_manage_posts($post_type){
        $f = isset( $_GET['f'] ) ? (int) $_GET['f'] : 0;
        $forms = get_posts(array('post_type' => 'wpcf7_contact_form', 'posts_per_page'=> 50, 'suppress_filters' => false));
        ?>
        <label for="filter-by-form" class="screen-reader-text"><?php _e( 'Filter by Form' ); ?></label>
		<select name="f" id="filter-by-form">
			<option<?php selected( $f, 0 ); ?> value="0"><?php _e( 'All forms' ); ?></option>
<?php
		foreach ( $forms as $form ) {
			printf( "<option %s value='%s'>%s</option>\n",
				selected( $f, $form->ID ),
				esc_attr( $form->ID ),
				/* translators: 1: month name, 2: 4-digit year */
				$form->post_title
			);
		}
?>
		</select>
<?php
    }
    public function form_posts_filter( $query ){
        global $pagenow;
        $type = 'wpcf7-entry';
        // echo $query->query_vars['post_type'];
        if (is_admin() && isset($query->query_vars['post_type']) && $query->query_vars['post_type'] == $type) {
            if (is_admin() && $pagenow=='edit.php' && isset($_GET['f']) && $_GET['f'] != '') {
                $query->query_vars['meta_key'] = 'form_id';
                $query->query_vars['meta_value'] = $_GET['f'];
            }
        }
    }

    public function display_post_states($post_states, $post){
        return $post_states;
    }
    public function display_field($field, $name){
        if(is_array($field)){
            foreach($field as $sub){
                $this->display_field($sub, $name);
            }
        }else{
            echo '<span class="wcf7-column-field"><span class="wcf7-column-field-name">'.$name.'</span><span class="wcf7-column-field-value">'.$field.'</span></span>';
        }
    }
    public function custom_columns($column, $post_id)
    {
        $post = get_post($post_id);
        if($post->post_type == 'wpcf7-entry'){
            switch ( $column ) {
        		case 'status':
        			echo get_post_meta( $post_id, '_status', true);
        			break;

        		case 'form':
        			$form_id = get_post_meta( $post_id, 'form_id', true );
                    echo '<a href="admin.php?page=wpcf7&post='.$form_id.'&action=edit">Form ID: '.$form_id.' - View</a>';
        			break;
        		case 'fields':
                    $fields = get_post_meta($post_id, 'wpcf7-fields', true);

                    if($fields){
                      foreach($fields as $name){
                          $field = get_post_meta($post_id, 'wpcf7-field-' . $name, true);
                          $this->display_field($field, $name);
                      }
                    }
        			break;
        	}
        }
    }
    public function manage_posts_columns($posts_columns, $post_type){
        if($post_type == 'wpcf7-entry'){
            return array_merge( $posts_columns,
            array( 'form' => __( 'Form', WPCF7_ENTRIES_TEXT_DOMAIN ), 'fields' => __( 'Fields', WPCF7_ENTRIES_TEXT_DOMAIN ), 'status' => __( 'Status', WPCF7_ENTRIES_TEXT_DOMAIN ) ));
        }
        return $posts_columns;
    }

    public function init_meta_box()
    {
        add_action('add_meta_boxes_wpcf7-entry', array($this, 'meta_box'));
    }
    public function meta_box()
    {
        add_meta_box(
            'wpcf7-entry-meta-box-entry',
            __('Form fields'),
            array($this, 'render_meta_box_entry'),
            'wpcf7-entry',
            'advanced',
            'high'
        );
    }
    public function render_meta_box_entry($post)
    {
        include("views/entry.php");
        $view = new WPCF7_Entries_View();
        $view->metabox($post->ID);
    }

    public function properties($properties, $WPCF7_ContactForm)
    {
        if (!isset($properties['repeater'])) {
            $properties['repeater'] = array();
        }
        return $properties;
    }
    public function admin_scripts()
    {
        wp_enqueue_style(WPCF7_ENTRIES_TEXT_DOMAIN."-style", WPCF7_ENTRIES_PLUGIN_URL. '/admin/css/styles.css');
        do_action('wpcf7_entries_admin_scripts');
        wp_enqueue_script(WPCF7_ENTRIES_TEXT_DOMAIN, WPCF7_ENTRIES_PLUGIN_URL. '/admin/js/plugin.js', array('jquery-ui-sortable'), "1.0.0", true);
    }
    public function scripts()
    {
        $in_footer = true;
        if ('header' === wpcf7_load_js()) {
            $in_footer = false;
        }
    }

    function save_posted($posted_data){
        $this->posted_data = $posted_data;
        return $posted_data;
    }

    function handle_submission($components, $contact_form, $mail){
        $fields = array();
        if($this->posted_data && !empty($this->posted_data)) {
            foreach($this->posted_data as $name => $value){

            }
        }
        $tags = $contact_form->scan_form_tags();
        foreach($tags as $tag){
            if($tag->basetype == 'repeater'){
                if($contact_form->prop('repeater')){
                    $tag_values = array();
                    $repeaters = (array)$contact_form->prop('repeater');
                    foreach($repeaters as $index => $repeater){
                        $fields['repeater-'.$index] = array();
                        $repeater_tags = WPCF7_FormTagsManager::get_instance()->scan($repeater['text']);
                        $_value_index = 0;
                        foreach($repeater_tags as $_tag){
                            $name = $_tag->name;
                            $values = preg_grep('/^'.$name.'/', array_keys($this->posted_data));
                            foreach($values as $key => $_name){
                                if(!isset($fields['repeater-'.$index][$_value_index])){
                                    $fields['repeater-'.$index][$_value_index] = array();
                                }
                                $fields['repeater-'.$index][$_value_index][$name] = $this->posted_data[$_name];
                            }
                        }
                        $_value_index ++;
                    }
                }
            }elseif('_wpcf7' !== substr($tag->name, 0, 6) && trim($tag->name) != ''){
                $fields[$tag->name] = $value;
            }
        }
        $contact_form_id = $contact_form->id();

        $body = $components['body'];
        $sender = wpcf7_strip_newline( $components['sender'] );
        $recipient = wpcf7_strip_newline( $components['recipient'] );
        $subject = wpcf7_strip_newline( $components['subject'] );
        $headers = trim($components['additional_headers']);
        $attachments = $components['attachments'];

        $submission = array(
            'form_id'   => $contact_form_id,
            'body'      => $body,
            'sender'    => $sender,
            'subject'   => $subject,
            'recipient' => $recipient,
            'additional_headers' => $headers,
            'attachments' => $attachments,
            'fields'    => $fields
        );

        $this->save_submission($submission);
        return $components;
    }

    private function save_submission($submission = array()){
        $post = array(
            'post_title'    => 'Submitted Entry',
            'post_content'  => json_encode($submission['fields']),
            'post_status'   => 'publish',
            'post_type'     => 'wpcf7-entry',
        );
        $post_id = wp_insert_post($post);

        add_post_meta($post_id, 'form_id', $submission['form_id']);
        add_post_meta($post_id, 'wpcf7-fields', array_keys( $submission['fields'] ));
        add_post_meta($post_id, '_status', 'submitted' );

        if(!empty($submission['fields'])){
          foreach($submission['fields'] as $name => $value){
              add_post_meta($post_id, 'wpcf7-field-' . $name, $value);
          }
        }
        do_action('wpcf7_entries_save_submission', $post_id, $submission['fields']);
        return $post_id;
    }
    public function get_tags($form_id){
        $form = WPCF7_ContactForm::get_instance($form_id);
        if($form){
            return $form->collect_mail_tags();
        }
        return array();
    }
    public function save_entry($post_id, $post, $update){
        $post_type = get_post_type($post_id);

        // If this isn't a 'book' post, don't update it.
        if ( "wpcf7-entry" != $post_type ) return;

        // - Update the post's metadata.

        if ( isset( $_POST['fields'] ) ) {
            $fields = $_POST['fields'];
            foreach($fields as $name => $value){
                update_post_meta($post_id, 'wpcf7-field-' . $name, $value);
            }
        }
    }
}

global $WPCF7_Entries;
$WPCF7_Entries = new WPCF7_Entries();

?>
