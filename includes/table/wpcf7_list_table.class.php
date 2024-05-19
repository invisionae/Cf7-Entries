<?php

if ( ! class_exists( 'WP_List_Table' ) )
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );

class WPCF7_Contact_Form_List_Table extends WP_List_Table {

	public static function define_columns() {
		$columns = array(
			'cb' => '<input type="checkbox" />',
			'title' => __( 'Title', 'contact-form-7' ),
			'entries' => __( 'Entries', 'contact-form-7' ),
			// 'shortcode' => __( 'Shortcode', 'contact-form-7' ),
			// 'author' => __( 'Author', 'contact-form-7' ),
			'date' => __( 'Date', 'contact-form-7' ) );

		return $columns;
	}

	function __construct() {
		parent::__construct( array(
			'singular' => 'post',
			'plural' => 'posts',
			'ajax' => false ) );
	}

	function prepare_items() {
		$current_screen = get_current_screen();
		$per_page = $this->get_items_per_page( 'cfseven_contact_forms_per_page' );

		$this->_column_headers = $this->get_column_info();

		$args = array(
			'posts_per_page' => $per_page,
			'orderby' => 'title',
			'order' => 'ASC',
			'offset' => ( $this->get_pagenum() - 1 ) * $per_page );

		if ( ! empty( $_REQUEST['s'] ) )
			$args['s'] = $_REQUEST['s'];

		if ( ! empty( $_REQUEST['orderby'] ) ) {
			if ( 'title' == $_REQUEST['orderby'] )
				$args['orderby'] = 'title';
			elseif ( 'author' == $_REQUEST['orderby'] )
				$args['orderby'] = 'author';
			elseif ( 'date' == $_REQUEST['orderby'] )
				$args['orderby'] = 'date';
		}

		if ( ! empty( $_REQUEST['order'] ) ) {
			if ( 'asc' == strtolower( $_REQUEST['order'] ) )
				$args['order'] = 'ASC';
			elseif ( 'desc' == strtolower( $_REQUEST['order'] ) )
				$args['order'] = 'DESC';
		}

		$this->items = WPCF7_ContactForm::find( $args );

		$total_items = WPCF7_ContactForm::count();
		$total_pages = ceil( $total_items / $per_page );

		$this->set_pagination_args( array(
			'total_items' => $total_items,
			'total_pages' => $total_pages,
			'per_page' => $per_page ) );
	}

	function get_columns() {
		return get_column_headers( get_current_screen() );
	}

	function get_sortable_columns() {
		$columns = array(
			'title' => array( 'title', true ),
			'author' => array( 'author', false ),
			'date' => array( 'date', false ) );

		return $columns;
	}

	function get_bulk_actions() {
		$actions = array(
			'delete' => __( 'Delete', 'contact-form-7' ) );

		return $actions;
	}

	function column_default( $item, $column_name ) {
		return '';
	}

	function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" name="%1$s[]" value="%2$s" />',
			$this->_args['singular'],
			$item->id() );
	}

	function column_title( $item ) {
		$url = admin_url( 'admin.php?page=wpcf7&post=' . absint( $item->id() ) );
		$edit_link = add_query_arg( array( 'action' => 'edit' ), $url );

		$actions = array(
			'edit' => sprintf( '<a href="%1$s">%2$s</a>',
				esc_url( $edit_link ),
				esc_html( __( 'Edit', 'contact-form-7' ) ) ) );

		if ( current_user_can( 'wpcf7_edit_contact_form', $item->id() ) ) {
			$copy_link = wp_nonce_url(
				add_query_arg( array( 'action' => 'copy' ), $url ),
				'wpcf7-copy-contact-form_' . absint( $item->id() ) );

			$actions = array_merge( $actions, array(
				'copy' => sprintf( '<a href="%1$s">%2$s</a>',
					esc_url( $copy_link ),
					esc_html( __( 'Duplicate', 'contact-form-7' ) ) ) ) );
		}

        $actions = array_merge( $actions, array(
            'entries' => sprintf( '<a href="%1$s">%2$s</a>',
                esc_url( admin_url( 'edit.php?post_type=wpcf7-entry&f='. $item->id() ) ),
                esc_html( __( 'Entries', 'contact-form-7' ) ) ) ) );

		$a = sprintf( '<a class="row-title" href="%1$s" title="%2$s">%3$s</a>',
			esc_url( $edit_link ),
			esc_attr( sprintf( __( 'Edit &#8220;%s&#8221;', 'contact-form-7' ),
				$item->title() ) ),
			esc_html( $item->title() ) );

        $shortcodes = array( $item->shortcode() );

		$output = '';

		foreach ( $shortcodes as $shortcode ) {
			$output .= "\n" . '<span class="shortcode"><input style="font-weight:normal;font-size:11px;padding:3px 0" type="text"'
				. ' onfocus="this.select();" readonly="readonly"'
				. ' value="' . esc_attr( $shortcode ) . '"'
				. ' class="large-text code" /></span>';
		}

		// return trim( $output );

		return '<strong>' . $a .$output. '</strong> ' . $this->row_actions( $actions );
	}

	function column_entries( $item ) {
        $query = new WP_Query(
            array(
                'post_type' => 'wpcf7-entry',
                'posts_per_page' => '1',
                'meta_key' => 'form_id',
                'meta_value' => $item->id()
            )
        );

        $a = sprintf( '<a class="row-title" href="%1$s" title="%2$s">%3$s</a>',
			admin_url( 'edit.php?post_type=wpcf7-entry&f='. $item->id() ),
			esc_attr( sprintf( __( '%s Entries', 'contact-form-7' ), $item->title() ) ),
			$query->found_posts);

        $query = new WP_Query(
            array(
                'post_type' => 'wpcf7-entry',
                'posts_per_page' => '100',
                'meta_key' => 'form_id',
                'meta_value' => $item->id(),
                'date_query' => array(
            		array(
            			'year'  => date('Y'),
            			'month' => date('m'),
            			'day'   => 1,
                        'compare'   => '>='
            		),
            	),
            )
        );
        $a .= '<div><small>('.$query->found_posts.') this month</small></div>';
        return $a;
	}
	function column_author( $item ) {
		$post = get_post( $item->id() );

		if ( ! $post )
			return;

		$author = get_userdata( $post->post_author );

		return esc_html( $author->display_name );
	}

	function column_shortcode( $item ) {
		$shortcodes = array( $item->shortcode() );

		$output = '';

		foreach ( $shortcodes as $shortcode ) {
			$output .= "\n" . '<span class="shortcode"><input type="text"'
				. ' onfocus="this.select();" readonly="readonly"'
				. ' value="' . esc_attr( $shortcode ) . '"'
				. ' class="large-text code" /></span>';
		}

		return trim( $output );
	}

	function column_date( $item ) {
		$post = get_post( $item->id() );

		if ( ! $post )
			return;

        $t_time = get_the_time( __( 'Y/m/d g:i:s a' ), $post );
		$m_time = $post->post_date;
		$time = get_post_time( 'G', true, $post );
		$time_diff = time() - $time;
		if ( $time_diff > 0 && $time_diff < DAY_IN_SECONDS ) {
			$h_time = sprintf( __( '%s ago' ), human_time_diff( $time ) );
		} else {
			$h_time = mysql2date( __( 'Y/m/d' ), $m_time );
		}
		return '<abbr title="' . $t_time . '">' . $h_time . '</abbr>';
	}
}

?>
