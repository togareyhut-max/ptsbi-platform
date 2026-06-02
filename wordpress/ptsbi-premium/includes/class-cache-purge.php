<?php
/**
 * Hapus cache situs dari panel frontend (setara Purge All LiteSpeed di wp-admin).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PTPRM_Cache_Purge {

    private const NONCE_ACTION = 'ptprm_purge_cache';
    private const NONCE_FIELD  = 'ptprm_purge_cache_nonce';

    public function __construct() {
        add_action( 'init', [ $this, 'handle_purge_request' ], 19 );
        add_action( 'wp_footer', [ __CLASS__, 'maybe_render_floating_purge' ], 99 );
    }

    /**
     * Semua pengguna panel frontend (bukan tamu) boleh purge cache tanpa wp-admin.
     */
    public static function can_purge(): bool {
        if ( ! is_user_logged_in() ) {
            return false;
        }
        if ( user_can( 'manage_options' ) ) {
            return true;
        }
        if ( ! class_exists( 'PTPRM_Access' ) ) {
            return false;
        }
        if ( PTPRM_Access::can_manage() || PTPRM_Access::can_manage_org_settings() ) {
            return true;
        }
        return current_user_can( PTPRM_Access::CAP_PURGE_CACHE );
    }

    /**
     * @return array{ok:bool, message:string}
     */
    public static function purge_all(): array {
        $litespeed = false;

        if ( ! defined( 'LITESPEED_PURGE_SILENT' ) ) {
            define( 'LITESPEED_PURGE_SILENT', true );
        }

        if ( class_exists( '\LiteSpeed\Purge' ) ) {
            \LiteSpeed\Purge::purge_all( 'PTPRM portal' );
            $litespeed = true;
        } elseif ( class_exists( 'LiteSpeed_Cache_API' ) && is_callable( [ 'LiteSpeed_Cache_API', 'purge_all' ] ) ) {
            LiteSpeed_Cache_API::purge_all();
            $litespeed = true;
        } elseif ( has_action( 'litespeed_purge_all' ) ) {
            do_action( 'litespeed_purge_all' );
            $litespeed = true;
        }

        wp_cache_flush();

        if ( function_exists( 'wp_cache_clear_cache' ) ) {
            wp_cache_clear_cache();
        }

        if ( function_exists( 'rocket_clean_domain' ) ) {
            rocket_clean_domain();
        }

        if ( function_exists( 'w3tc_flush_all' ) ) {
            w3tc_flush_all();
        }

        if ( $litespeed ) {
            return [
                'ok'      => true,
                'message' => __( 'Semua cache LiteSpeed berhasil dihapus (Purge All). Muat ulang halaman jika perlu.', 'ptsbi-premium' ),
            ];
        }

        return [
            'ok'      => true,
            'message' => __( 'Cache WordPress/object cache dibersihkan. Jika situs memakai LiteSpeed, pastikan plugin LiteSpeed Cache aktif.', 'ptsbi-premium' ),
        ];
    }

    public function handle_purge_request(): void {
        if ( empty( $_POST['ptprm_purge_cache'] ) || ! self::can_purge() ) {
            return;
        }

        if (
            ! isset( $_POST[ self::NONCE_FIELD ] )
            || ! wp_verify_nonce(
                sanitize_text_field( wp_unslash( (string) $_POST[ self::NONCE_FIELD ] ) ),
                self::NONCE_ACTION
            )
        ) {
            $redirect = $this->sanitize_return_url( wp_unslash( (string) ( $_POST['ptprm_purge_return'] ?? '' ) ) );
            wp_safe_redirect(
                add_query_arg(
                    'ptprm_cache_error',
                    rawurlencode(
                        function_exists( 'ptprm_portal_session_expired_message' )
                            ? ptprm_portal_session_expired_message()
                            : __( 'Sesi tidak valid. Muat ulang halaman lalu coba lagi.', 'ptsbi-premium' )
                    ),
                    $redirect
                )
            );
            exit;
        }

        $result   = self::purge_all();
        $redirect = $this->sanitize_return_url( wp_unslash( (string) ( $_POST['ptprm_purge_return'] ?? '' ) ) );

        if ( ! $result['ok'] ) {
            wp_safe_redirect(
                add_query_arg( 'ptprm_cache_error', rawurlencode( $result['message'] ), $redirect )
            );
            exit;
        }

        if ( function_exists( 'ptprm_portal_nocache_headers' ) ) {
            ptprm_portal_nocache_headers();
        }

        wp_safe_redirect(
            add_query_arg(
                [
                    'ptprm_cache_purged' => '1',
                    'ptprm_cache_msg'    => rawurlencode( $result['message'] ),
                ],
                $redirect
            )
        );
        exit;
    }

    /**
     * Tombol mengambang di halaman portal (selalu terlihat tanpa scroll ke footer).
     */
    public static function maybe_render_floating_purge(): void {
        if ( ! self::can_purge() || ! function_exists( 'ptprm_is_portal_page_request' ) || ! ptprm_is_portal_page_request() ) {
            return;
        }
        $return_url = esc_url( self::current_page_url() );
        echo '<div class="ptprm-cache-purge-float" aria-label="' . esc_attr__( 'Hapus cache', 'ptsbi-premium' ) . '">';
        self::render_purge_button( $return_url );
        echo '</div>';
    }

    public static function current_page_url(): string {
        if ( is_singular() ) {
            return get_permalink();
        }
        $uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( (string) $_SERVER['REQUEST_URI'] ) : '/'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        return home_url( $uri );
    }

    /**
     * Bilah di atas panel — untuk masalah "sesi kedaluwarsa" karena cache halaman.
     *
     * @param string $return_url URL setelah purge.
     */
    public static function render_purge_toolbar( string $return_url ): void {
        if ( ! self::can_purge() ) {
            return;
        }
        echo '<div class="ptprm-cache-purge-toolbar">';
        echo '<p class="ptprm-cache-purge-toolbar__text">';
        echo esc_html__( 'Jika simpan gagal (sesi kedaluwarsa), klik tombol di bawah untuk menghapus semua cache situs (sama seperti Purge All di LiteSpeed), lalu muat ulang halaman dan coba simpan lagi.', 'ptsbi-premium' );
        echo '</p>';
        self::render_purge_button( $return_url );
        echo '</div>';
    }

    /**
     * @param string $url
     */
    private function sanitize_return_url( $url ): string {
        $url = is_string( $url ) ? trim( $url ) : '';
        if ( $url === '' ) {
            return home_url( '/' );
        }
        $safe = wp_validate_redirect( $url, '' );
        return $safe !== '' ? $safe : home_url( '/' );
    }

    public static function render_notice(): void {
        if ( isset( $_GET['ptprm_cache_purged'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $msg = isset( $_GET['ptprm_cache_msg'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                ? sanitize_text_field( wp_unslash( (string) $_GET['ptprm_cache_msg'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                : __( 'Cache situs berhasil dihapus.', 'ptsbi-premium' );
            if ( $msg !== '' ) {
                echo '<p class="ptprm-member-alert ptprm-member-alert-ok">' . esc_html( $msg ) . '</p>';
            }
        }
        if ( isset( $_GET['ptprm_cache_error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $err = sanitize_text_field( wp_unslash( (string) $_GET['ptprm_cache_error'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            if ( $err !== '' ) {
                echo '<p class="ptprm-member-alert ptprm-member-alert-error">' . esc_html( $err ) . '</p>';
            }
        }
    }

    /**
     * Tombol Purge All di footer panel (pengurus / anggota).
     *
     * @param string $return_url URL halaman setelah purge.
     */
    public static function render_purge_button( string $return_url ): void {
        if ( ! self::can_purge() ) {
            return;
        }

        $return_url = esc_url( $return_url );
        echo '<form method="post" class="ptprm-cache-purge-form" action="' . $return_url . '">';
        wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );
        echo '<input type="hidden" name="ptprm_purge_cache" value="1">';
        echo '<input type="hidden" name="ptprm_purge_return" value="' . $return_url . '">';
        echo '<button type="submit" class="button button-secondary ptprm-cache-purge-btn">';
        echo esc_html__( 'Hapus semua cache situs (Purge All)', 'ptsbi-premium' );
        echo '</button>';
        echo '</form>';
    }
}
