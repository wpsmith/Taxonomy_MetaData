<?php

if ( ! class_exists( 'Taxonomy_MetaData' ) ) {
/**
 * Adds pseudo term meta functionality
 * @version 0.1.1
 * @author  Justin Sternberg
 */
class Taxonomy_MetaData {

	/**
	 * Stores every instance created with this class.
	 * @since  0.1.0
	 * @var array
	 */
	protected static $taxonomy_objects = array();

	/**
	 * Meta fields array passed in when instantiating the calss
	 * @since  0.1.0
	 * @var array
	 */
	public $fields = array();

	/**
	 * Taxonomy Slug
	 * @since  0.1.0
	 * @var string
	 */
	public $taxonomy = '';

	/**
	 * Taxonomy Object
	 * @since  0.1.1
	 * @var object
	 */
	public $tax_object = '';

	/**
	 * Meta fields heading (optional)
	 * @since  0.1.0
	 * @var string
	 */
	public $section_title = '';

	/**
	 * Unique ID string for each taxonomy
	 * @since  0.1.0
	 * @var string
	 */
	protected $id_base = '';

	/**
	 * Unique ID string for each taxonomy term
	 * @since  0.1.0
	 * @var string
	 */
	protected $id = '';

	/**
	 * Cached option/meta data for this taxonomy
	 * @since  0.1.0
	 * @var array
	 */
	protected $meta = array();

	/**
	 * Get Started
	 * @since  0.1.0
	 */
	public function __construct( $taxonomy, $fields_or_callback, $title = '' ) {
		if ( isset( self::$taxonomy_objects[ $taxonomy ] ) )
			return;

		$this->taxonomy      = $taxonomy;
		$this->id_base       = strtolower( __CLASS__ ) . '_' . $this->taxonomy;
		$this->fields        = $fields_or_callback;
		$this->section_title = $title;

		self::$taxonomy_objects[ $taxonomy ] = $this;
		add_action( 'admin_init', array( $this, 'hooks' ) );

	}

	/**
	 * Get fields config array from callback if requested
	 * @since  0.1.0
	 * @return array  Fields config array
	 */
	public function fields() {
		return $this->fields;
	}

	/**
	 * Loop field array and send through callback function
	 * @since  0.1.1
	 * @param  array  $alldata All option data
	 * @param  mixed  $cb      Callback method/function
	 */
	public function loop_fields( $alldata, $cb ) {
		// Loop through fields and do cb
		foreach ( $this->fields() as $key => $field ) {
			$field['id'] = $key;
			$field = (object) $field;

			call_user_func( $cb, $field, isset( $alldata[ $key ] ) ? $alldata[ $key ] : '' );
		}
	}

	/**
	 * Hook into our term edit & new term forms
	 * @since  0.1.0
	 */
	public function hooks() {

		// Display our form data
		add_action( $this->taxonomy .'_edit_form', array( $this, 'metabox_edit' ), 8, 2 );
		add_action( $this->taxonomy .'_add_form_fields', array( $this, 'metabox_edit' ), 8, 2 );

		// Save our form data
		add_action( 'created_'. $this->taxonomy, array( $this, 'save_data' ) );
		add_action( 'edited_'. $this->taxonomy, array( $this, 'save_data' ) );

		// Delete it if necessary
		add_action( 'delete_'. $this->taxonomy, array( $this, 'delete_data' ) );

	}

	/**
	 * Displays Taxonomy Term form fields for meta
	 * @since  0.1.0
	 * @param  int|object $term     Term object, or Taxonomy name
	 * @param  string     $taxonomy If term object is passed in, this is the taxonomy
	 */
	public function metabox_edit( $term, $taxonomy = '' ) {

		$editpage = isset( $_GET['tag_ID'] ) ? true : false;
		$taxonomy = $taxonomy ? $taxonomy : $term;
		$term_id  = $editpage ? $term->term_id : 0;

		if ( ! current_user_can( $this->taxonomy_object()->cap->edit_terms ) ) {
			return;
		}

		// Initiate ID
		$this->id( $term_id );
		// Get term meta
		$data = get_option( $this->id() );

		// Add a title for these fields, if requested
		if ( $this->section_title ) : ?>
		<h3 class="cmb_metabox_title"><?php echo $this->section_title; ?></h3>
		<?php endif; ?>
		<input type="hidden" name="term_opt_name" value="<?php echo $this->id( $term_id ); ?>">
		<?php wp_nonce_field( 'term_meta_box_nonce', 'term_meta_box_nonce', false, true ); ?>
		<table class="form-table term-meta-box">
			<?php
			// Loop through fields and do field view
			$this->loop_fields( $data, array( $this, 'render_field_view' ) );
			?>
		</table>
		<?php
	}

	/**
	 * Field view
	 * @since  0.1.1
	 * @param  array  $field Field config
	 * @param  mixed  $data  Field value
	 */
	public function render_field_view( $field, $data ) {
		$cb = isset( $field->render_cb ) && is_callable( $field->render_cb )
			? $field->render_cb
			: array( $this, 'text_input_view' );
		$id = '_id_'. sanitize_html_class( $field->id );
		?>
		<tr id="<?php echo $id; ?>">
			<th>
				<label for="<?php echo $field->id; ?>"><?php echo $field->name; ?></label>
			</th>
			<td>
				<?php call_user_func( $cb, $field, $data, $field->id ); ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Default field input view
	 * @since  0.1.1
	 * @param  array  $field Field config
	 * @param  mixed  $data  Field value
	 */
	public function text_input_view( $field, $data ) {
		echo '<input type="text" class="regular-text" name="', $field->id, '" id="', $field->id, '" value="', esc_attr( $data ), '" />';
		if ( isset( $field->desc ) && $field->desc )
			echo "\n<p class=\"description\">{$field->desc}</p>\n";
	}

	/**
	 * Save the data from the taxonomy forms to the taxonomy site option
	 * @since  0.1.0
	 * @param  int $term_id Term's ID
	 */
	public function save_data( $term_id ) {

		if (
			// check nonce
			! isset( $_POST['term_opt_name'], $_POST['term_meta_box_nonce'], $_POST['action'] )
			|| ! wp_verify_nonce( $_POST['term_meta_box_nonce'], 'term_meta_box_nonce' )
		)
			return;

		// Can the user edit this term?
		if ( ! current_user_can( $this->taxonomy_object()->cap->edit_terms ) )
			return;

		$this->id = ( false !== strpos( $_POST['term_opt_name'], 'setme' ) )
			? $this->id = $this->id_base .'_'. $term_id
			: $this->id( $term_id );

		$this->sanitized = array();

		// Loop and sanitize data
		$this->loop_fields( $_POST, array( $this, 'sanitize_field' ) );

		// Save the field data
		update_option( $this->id(), $this->sanitized );
	}

	/**
	 * Sanitizes an input field
	 * @since  0.1.1
	 * @param  array  $field Field config
	 * @param  mixed  $data  Field value
	 */
	public function sanitize_field( $field, $data ) {
		$cb = isset( $field->sanitize_cb ) && is_callable( $field->sanitize_cb ) ? $field->sanitize_cb : 'sanitize_text_field';
		$this->sanitized[ $field->id ] = call_user_func( $cb, $data );
	}

	/**
	 * Generate option key id
	 * @since  0.1.0
	 * @param  integer $term_id Optional, Term ID
	 * @return string           Option key
	 */
	public function id( $term_id = 0 ) {

		if ( ! $this->id ) {
			$this->id = $term_id ? $this->id_base .'_'. $term_id : $this->id_base . '_setme';
		}

		return $this->id;
	}

	/**
	 * Retrieves the full db object for this instance' taxonomy
	 * @since  0.1.1
	 * @return object  Taxonomy object
	 */
	public function taxonomy_object() {
		if ( $this->tax_object )
			return $this->tax_object;

		$this->tax_object = get_taxonomy( $this->taxonomy );
		return $this->tax_object;
	}

	/**
	 * Remove associated term meta when deleting a term
	 * @since  0.1.0
	 * @param int $term_id      Term's ID
	 */
	public function delete_data( $term_id ) {
		return delete_option( $this->id( $term_id ) );
	}

	/**
	 * Returns the $this->taxonomy site option for this term ID
	 * @since  0.1.0
	 * @param  string  $term_id  The term id for the options we're getting
	 * @return mixed             Option value
	 */
	public function _get_meta( $term_id ) {
		if ( isset( $this->meta[ $term_id ] ) )
			return $this->meta[ $term_id ];

		$this->meta[ $term_id ] = get_option( $this->id( $term_id ) );
		return $this->meta[ $term_id ];
	}

	/**
	 * Returns term meta with options to return a subset
	 * @since  0.1.0
	 * @param  string  $term_id  The term id for the options we're getting
	 * @param  string  $key      Term meta key to check
	 * @return mixed             Requested value | false
	 */
	public function get_meta( $term_id, $key = '' ) {
		$data = $this->_get_meta( $term_id );
		if ( $key )
			return isset( $data[ $key ] ) ? $data[ $key ] : false;
		return $data;
	}

	/**
	 * Public method for getting term meta
	 * @since  0.1.0
	 * @param  string $taxonomy Taxonomy slug
	 * @param  string $term_id  The ID of the term whose option we're getting
	 * @param  string $key      Term meta key to check
	 * @return mixed            Requested value | false
	 */
	public static function get( $taxonomy, $term_id = '', $key = '' ) {
		// Get taxonomy instance
		$instance = self::get_instance( $taxonomy );
		// Return the meta, or false if the taxonomy object doesn't exist
		return $instance ? $instance->get_meta( $term_id, $key ) : false;
	}

	/**
	 * Public method for getting an instanciated instance of this class by taxonomy
	 * @since  0.1.0
	 * @param  string $taxonomy  Taxonomy slug
	 * @return object            Taxonomy_MetaData instance or false
	 */
	public static function get_instance( $taxonomy ) {
		// If the object instance doesn't exist, bail
		if ( ! isset( self::$taxonomy_objects[ $taxonomy ] ) )
			return false;
		// Ok, send it back.
		return self::$taxonomy_objects[ $taxonomy ];
	}

	/**
	 * Public method for getting all instanciated instances of this class
	 * @since  0.1.0
	 * @return array Array of Taxonomy_MetaData instances
	 */
	public static function get_all_instances() {
		return self::$taxonomy_objects;
	}

}

} // end class_exists check
