<?php
/**
 * Shortcode daftar pengurus wilayah per halaman.
 *
 * Format isi (meta box atau shortcode content):
 *   Ketua | Daslon Samosir
 *   Sekretaris | Freddy Samosir
 *   ## Bidang Adat dan Budaya
 *   Ketua | Patido Samosir
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PTPRM_Wilayah_Board {

    public const META_CONTENT = 'ptprm_wilayah_board_lines';

    public function __construct() {
        add_shortcode( 'ptprm_wilayah_board', [ $this, 'shortcode' ] );
        add_action( 'add_meta_boxes', [ $this, 'register_meta_box' ] );
        add_action( 'save_post_page', [ $this, 'save_meta_box' ], 10, 2 );
    }

    public function register_meta_box(): void {
        add_meta_box(
            'ptprm-wilayah-board',
            __( 'Daftar Pengurus Wilayah', 'ptsbi-premium' ),
            [ $this, 'render_meta_box' ],
            'page',
            'normal',
            'default'
        );
    }

    /**
     * @param WP_Post $post
     */
    public function render_meta_box( $post ): void {
        wp_nonce_field( 'ptprm_wilayah_board_save', 'ptprm_wilayah_board_nonce' );
        $lines = (string) get_post_meta( (int) $post->ID, self::META_CONTENT, true );
        ?>
        <p class="description">
            <?php esc_html_e( 'Satu baris per jabatan: Jabatan | Nama. Gunakan baris ## Judul untuk bagian/bidang. Shortcode [ptprm_wilayah_board] di konten halaman akan menampilkan daftar ini.', 'ptsbi-premium' ); ?>
        </p>
        <textarea name="ptprm_wilayah_board_lines" rows="16" class="large-text code"><?php echo esc_textarea( $lines ); ?></textarea>
        <p class="description">
            <?php esc_html_e( 'Contoh:', 'ptsbi-premium' ); ?><br>
            <code>Ketua | Daslon Samosir</code><br>
            <code>## Bidang Sosial</code><br>
            <code>Ketua | Tondor Samosir</code>
        </p>
        <?php
    }

    /**
     * @param int     $post_id
     * @param WP_Post $post
     */
    public function save_meta_box( int $post_id, $post ): void {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( ! isset( $_POST['ptprm_wilayah_board_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['ptprm_wilayah_board_nonce'] ) ), 'ptprm_wilayah_board_save' ) ) {
            return;
        }
        if ( ! current_user_can( 'edit_page', $post_id ) ) {
            return;
        }
        $raw = isset( $_POST['ptprm_wilayah_board_lines'] )
            ? (string) wp_unslash( $_POST['ptprm_wilayah_board_lines'] )
            : '';
        update_post_meta( $post_id, self::META_CONTENT, sanitize_textarea_field( $raw ) );
    }

    /**
     * @param array<string,string>|string $atts
     */
    public function shortcode( $atts = [] ): string {
        $atts = shortcode_atts(
            [
                'title' => '',
            ],
            is_array( $atts ) ? $atts : [],
            'ptprm_wilayah_board'
        );

        $post_id = get_the_ID();
        $lines   = $post_id ? (string) get_post_meta( (int) $post_id, self::META_CONTENT, true ) : '';
        if ( $lines === '' && is_singular() ) {
            $lines = (string) get_post_field( 'post_content', $post_id );
        }

        $sections = self::parse_lines( $lines );
        if ( ! $sections ) {
            return '';
        }

        $title = trim( (string) $atts['title'] );
        if ( $title === '' && $post_id ) {
            $title = (string) get_the_title( $post_id );
        }

        ob_start();
        echo '<div class="ptprm-wilayah-board">';
        if ( $title !== '' ) {
            echo '<h2 class="ptprm-wilayah-board-title">' . esc_html( $title ) . '</h2>';
        }
        foreach ( $sections as $section ) {
            if ( ! empty( $section['heading'] ) ) {
                echo '<h3 class="ptprm-wilayah-board-section">' . esc_html( (string) $section['heading'] ) . '</h3>';
            }
            if ( empty( $section['rows'] ) ) {
                continue;
            }
            echo '<dl class="ptprm-wilayah-board-list">';
            foreach ( $section['rows'] as $row ) {
                echo '<div class="ptprm-wilayah-board-row">';
                echo '<dt>' . esc_html( (string) $row['role'] ) . '</dt>';
                echo '<dd>' . esc_html( (string) $row['name'] ) . '</dd>';
                echo '</div>';
            }
            echo '</dl>';
        }
        echo '</div>';
        return (string) ob_get_clean();
    }

    /**
     * @return list<array{heading?:string,rows:list<array{role:string,name:string}>}>
     */
    public static function parse_lines( string $raw ): array {
        $sections = [
            [
                'heading' => '',
                'rows'    => [],
            ],
        ];
        $lines = preg_split( '/\r\n|\r|\n/', $raw ) ?: [];
        foreach ( $lines as $line ) {
            $line = trim( (string) $line );
            if ( $line === '' || strpos( $line, '[' ) === 0 ) {
                continue;
            }
            if ( strpos( $line, '##' ) === 0 ) {
                $sections[] = [
                    'heading' => trim( substr( $line, 2 ) ),
                    'rows'    => [],
                ];
                continue;
            }
            if ( strpos( $line, '#' ) === 0 ) {
                $sections[] = [
                    'heading' => trim( ltrim( $line, '#' ) ),
                    'rows'    => [],
                ];
                continue;
            }
            $role = $line;
            $name = '';
            if ( strpos( $line, '|' ) !== false ) {
                $parts = array_map( 'trim', explode( '|', $line, 2 ) );
                $role  = $parts[0] ?? '';
                $name  = $parts[1] ?? '';
            } elseif ( strpos( $line, ':' ) !== false ) {
                $parts = array_map( 'trim', explode( ':', $line, 2 ) );
                $role  = $parts[0] ?? '';
                $name  = $parts[1] ?? '';
            }
            if ( $role === '' ) {
                continue;
            }
            $idx = count( $sections ) - 1;
            $sections[ $idx ]['rows'][] = [
                'role' => $role,
                'name' => $name,
            ];
        }

        return array_values(
            array_filter(
                $sections,
                static function ( array $section ): bool {
                    return ( $section['heading'] ?? '' ) !== '' || ! empty( $section['rows'] );
                }
            )
        );
    }
}
