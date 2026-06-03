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
    }

    public static function can_purge(): bool {
        if ( ! is_user_logged_in() ) {
            return false;
        }
        if ( class_exists( 'PTPRM_Access' ) && PTPRM_Access::is_site_admin() ) {
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

        $purge_nonce_ok = false;
        if ( isset( $_POST[ self::NONCE_FIELD ] ) ) {
            $purge_nonce_ok = (bool) wp_verify_nonce(
                sanitize_text_field( wp_unslash( (string) $_POST[ self::NONCE_FIELD ] ) ),
                self::NONCE_ACTION
            );
        }
        if ( ! $purge_nonce_ok && function_exists( 'ptprm_verify_portal_form_nonce_fallback' ) ) {
            $purge_nonce_ok = ptprm_verify_portal_form_nonce_fallback( self::NONCE_ACTION );
        }
        if ( ! $purge_nonce_ok ) {
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
        echo esc_html__( 'Hapus semua cache (Purge All)', 'ptsbi-premium' );
        echo '</button>';
        echo '</form>';
    }
}
