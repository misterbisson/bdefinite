<?php
/*
This class includes the admin UI components and metaboxes, and the supporting methods they require.
*/

class bDefinite_Admin extends bDefinite
{
	public function __construct()
	{
		add_action( 'admin_init', array( $this , 'admin_init' ) );
	}

	public function admin_init()
	{
		global $pagenow;

		// only continue if we're on a page related to our post type
		// is there a better way to do this?
		if ( 
			! ( // matches the new post page
				'post-new.php' == $pagenow &&
				isset ( $_GET['post_type'] ) && 
				$this->post_type_name == $_GET['post_type'] 
			) &&
			! ( // matches the editor for our post type
				'post.php' == $pagenow &&
				isset ( $_GET['post'] ) && 
				$this->get_post( $_GET['post'] ) 
			) 
		)
		{
			return;
		}

		// add any JS or CSS for the needed for the dashboard
		add_action( 'admin_enqueue_scripts', array( $this , 'admin_enqueue_scripts' ) );

		$this->upgrade();
	}

	public function upgrade()
	{
		$options = get_option( $this->post_type_name );

		// initial activation and default options
		if( ! isset( $options['version'] ) )
		{
			$this->init_partsofspeech();

			// init the var
			$options = array();

			// set the options
			$options['active'] = TRUE;
			$options['version'] = $this->version;
		}

		// replace the old options with the new ones
		update_option( $this->post_type_name, $options );
	}

	public function init_partsofspeech()
	{
		foreach( $this->default_parts_of_speech as $k => $v )
		{
			wp_insert_term( $v['name'], $this->post_type_name . '_partsofspeech', array(
				'slug' => $k,
				'description' => $v['description'],
			) );
		}
	}

	// register and enqueue any scripts needed for the dashboard
	public function admin_enqueue_scripts()
	{
		wp_register_style( $this->id_base . '-admin' , $this->plugin_url . '/css/' . $this->id_base . '-admin.css' , array() , $this->version );
		wp_enqueue_style( $this->id_base . '-admin' );
		
		wp_register_script( $this->id_base . '-admin', $this->plugin_url . '/js/' . $this->id_base . '-admin.js', array( 'jquery' ), $this->version, true );
		wp_enqueue_script( $this->id_base . '-admin');
	}//end admin_enqueue_scripts

	public function nonce_field()
	{
		wp_nonce_field( plugin_basename( __FILE__ ) , $this->id_base .'-nonce' );
	}

	public function verify_nonce()
	{
		return wp_verify_nonce( $_POST[ $this->id_base .'-nonce' ] , plugin_basename( __FILE__ ));
	}

	public function get_field_name( $field_name )
	{
		return $this->id_base . '[' . $field_name . ']';
	}

	public function get_field_id( $field_name )
	{
		return $this->id_base . '-' . $field_name;
	}

	// the Details metabox
	public function metabox_details( $post )
	{
		// must have this on the page in one of the metaboxes
		// the nonce is then checked in $this->save_post()
		$this->nonce_field();

		// add the form elements you want to use here. 
		// these are regular html form elements, but use $this->get_field_name( 'name' ) and $this->get_field_id( 'name' ) to identify them

		include_once __DIR__ . '/templates/metabox-details.php';

		// be sure to use proper validation on user input displayed here
		// http://codex.wordpress.org/Data_Validation

		// use checked() or selected() for checkboxes and select lists
		// http://codex.wordpress.org/Function_Reference/selected
		// http://codex.wordpress.org/Function_Reference/checked
		// there are other convenience methods in WP, as well

		// when saved, the form elements will be passed to 
		// $this->save_post(), which simply checks permissions and 
		// captures the $_POST var, and then to go_analyst()->update_meta(),
		// where the data is sanitized and validated before saving
	}

	public function control_partsofspeech( $field_name, $meta )
	{
		$parts = $this->get_partsofspeech();

		if( ! is_array( $parts ) )
		{
			return FALSE;
		}

		echo '<select id="' . $this->get_field_id( $field_name ) . '" name="' . $this->get_field_name( $field_name ) . '">';
		foreach( $parts as $k => $v )
		{
			echo '<option value="' . $k . '" '. selected( $meta[ $field_name ], $k, FALSE ) .'>' . $v . '</option>';
		}
		echo '</select>';
	}

	public function get_partsofspeech()
	{
		$terms = get_terms( $this->post_type_name . '_partsofspeech', array(
			'orderby' => 'name',
			'number' => 15,
			'hide_empty' => 0,
		) );

		if( ! is_array( $terms ) )
		{
			return FALSE;
		}

		$parts = array();
		foreach( $terms as $term )
		{
			$parts[ $term->slug ] = $term->name;
		}

		return $parts;

	}

	// register our metaboxes
	public function metaboxes()
	{
		add_meta_box( $this->get_field_id( 'details' ), 'Details', array( $this, 'metabox_details' ), $this->post_type_name , 'normal', 'default' );
	}

	// process
	public function save_post( $post_id )
	{
		// check that this isn't an autosave
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
		{
			return;
		}

		// don't run on post revisions (almost always happens just before the real post is saved)
		if( wp_is_post_revision( $post_id ))
		{
			return;
		}

		// get and check the post
		$post = get_post( $post_id );

		// only work on authority posts
		if( ! isset( $post->post_type ) || $this->post_type_name != $post->post_type )
		{
			return;
		}

		// check the nonce
		if( ! $this->verify_nonce() )
		{
			return;
		}

		// check the permissions
		if( ! current_user_can( 'edit_post' , $post_id ) )
		{
			return;
		}

		// save it
		$this->update_meta( $post_id , stripslashes_deep( $_POST[ $this->id_base ] ) );

	}//end save_post

}//end GO_Analyst_Admin class