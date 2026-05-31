<?php
defined( 'ABSPATH' ) || exit;

class KontenKit_Post_Meta {
	public static function init(): void {
		add_action( 'init', [ __CLASS__, 'register_meta' ] );
		add_action( 'add_meta_boxes', [ __CLASS__, 'metabox' ] );
		add_action( 'save_post_post', [ __CLASS__, 'save_post' ] );
		add_action( 'save_post_kk_testimonial', [ __CLASS__, 'save_testimonial' ] );
		add_action( 'add_meta_boxes', [ __CLASS__, 'testimonial_box' ] );
	}

	public static function register_meta(): void {
		foreach ( [ '_kk_subtitle', '_kk_video_url', '_kk_label' ] as $key ) {
			register_post_meta( 'post', $key, [
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => static fn () => current_user_can( 'edit_posts' ),
			] );
		}
	}

	public static function metabox(): void {
		add_meta_box( 'kontenkit-post', __( 'KontenKit Premium', 'kontenkit-core' ), [ __CLASS__, 'render_post' ], 'post', 'normal', 'high' );
	}

	public static function testimonial_box(): void {
		add_meta_box( 'kontenkit-testimonial', __( 'Detail', 'kontenkit-core' ), [ __CLASS__, 'render_testimonial' ], 'kk_testimonial', 'side' );
	}

	public static function render_post( WP_Post $post ): void {
		wp_nonce_field( 'kontenkit_save', 'kontenkit_nonce' );
		$sub   = get_post_meta( $post->ID, '_kk_subtitle', true );
		$video = get_post_meta( $post->ID, '_kk_video_url', true );
		$label = get_post_meta( $post->ID, '_kk_label', true );
		?>
		<p><label><strong><?php esc_html_e( 'Subjudul', 'kontenkit-core' ); ?></strong></label>
		<input type="text" class="widefat" name="kk_subtitle" value="<?php echo esc_attr( (string) $sub ); ?>" /></label></p>
		<p><label><strong><?php esc_html_e( 'Label badge', 'kontenkit-core' ); ?></strong> <span class="description"><?php esc_html_e( 'mis. BREAKING, EKSKLUSIF', 'kontenkit-core' ); ?></span></label>
		<input type="text" class="widefat" name="kk_label" value="<?php echo esc_attr( (string) $label ); ?>" /></label></p>
		<p><label><strong><?php esc_html_e( 'URL video (YouTube)', 'kontenkit-core' ); ?></strong></label>
		<input type="url" class="widefat" name="kk_video_url" value="<?php echo esc_attr( (string) $video ); ?>" placeholder="https://youtube.com/watch?v=..." /></label></p>
		<?php
	}

	public static function render_testimonial( WP_Post $post ): void {
		wp_nonce_field( 'kontenkit_save', 'kontenkit_nonce' );
		$role = get_post_meta( $post->ID, '_kk_role', true );
		?>
		<p><label><?php esc_html_e( 'Jabatan / peran', 'kontenkit-core' ); ?>
		<input type="text" class="widefat" name="kk_role" value="<?php echo esc_attr( (string) $role ); ?>" /></label></p>
		<?php
	}

	public static function save_post( int $post_id ): void {
		if ( ! self::verify( $post_id ) ) {
			return;
		}
		update_post_meta( $post_id, '_kk_subtitle', sanitize_text_field( wp_unslash( $_POST['kk_subtitle'] ?? '' ) ) );
		update_post_meta( $post_id, '_kk_label', sanitize_text_field( wp_unslash( $_POST['kk_label'] ?? '' ) ) );
		update_post_meta( $post_id, '_kk_video_url', esc_url_raw( wp_unslash( $_POST['kk_video_url'] ?? '' ) ) );
	}

	public static function save_testimonial( int $post_id ): void {
		if ( ! self::verify( $post_id ) ) {
			return;
		}
		update_post_meta( $post_id, '_kk_role', sanitize_text_field( wp_unslash( $_POST['kk_role'] ?? '' ) ) );
	}

	private static function verify( int $post_id ): bool {
		if ( ! isset( $_POST['kontenkit_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['kontenkit_nonce'] ) ), 'kontenkit_save' ) ) {
			return false;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return false;
		}
		return current_user_can( 'edit_post', $post_id );
	}
}
