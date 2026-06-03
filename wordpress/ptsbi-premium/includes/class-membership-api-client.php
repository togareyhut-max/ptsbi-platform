<?php
/**
 * Klien Membership API (opsional). Aman jika API belum siap — tidak memblokir login lokal.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PTPRM_Membership_Api_Client {

    public static function enabled(): bool {
        $o = ptprm_options();
        if ( empty( $o['membership_api_enabled'] ) ) {
            return false;
        }
        return self::base_url() !== '';
    }

    public static function base_url(): string {
        $o   = ptprm_options();
        $url = trim( (string) ( $o['membership_api_base_url'] ?? '' ) );
        return untrailingslashit( $url );
    }

    private static function integration_key(): string {
        $o = ptprm_options();
        return trim( (string) ( $o['membership_api_integration_key'] ?? '' ) );
    }

    private static function timeout(): int {
        $o = ptprm_options();
        $n = (int) ( $o['membership_api_timeout_sec'] ?? 8 );
        return max( 3, min( 30, $n ) );
    }

    /**
     * Uji koneksi ke Membership API (Tarombo) + database, memakai nilai tersimpan.
     * Tidak bergantung pada flag "enabled" agar bisa dites sebelum diaktifkan.
     *
     * @return array{ok:bool,message:string,detail:string}
     */
    public static function test_connection(): array {
        $base = self::base_url();
        $key  = self::integration_key();
        if ( $base === '' ) {
            return [
                'ok'      => false,
                'message' => __( 'Belum tersambung: Base URL Membership API masih kosong.', 'ptsbi-premium' ),
                'detail'  => '',
            ];
        }
        if ( $key === '' ) {
            return [
                'ok'      => false,
                'message' => __( 'Belum tersambung: Integration Key masih kosong.', 'ptsbi-premium' ),
                'detail'  => '',
            ];
        }

        $url = $base . '/ping';
        $res = wp_remote_get(
            $url,
            [
                'timeout' => self::timeout(),
                'headers' => [
                    'Accept'            => 'application/json',
                    'X-Integration-Key' => $key,
                ],
            ]
        );

        if ( is_wp_error( $res ) ) {
            return [
                'ok'      => false,
                'message' => __( 'Belum tersambung: tidak dapat menghubungi server Tarombo.', 'ptsbi-premium' ),
                'detail'  => $res->get_error_message(),
            ];
        }

        $code = (int) wp_remote_retrieve_response_code( $res );
        $body = (string) wp_remote_retrieve_body( $res );
        $json = json_decode( $body, true );

        if ( $code === 200 && is_array( $json ) && ! empty( $json['ok'] ) ) {
            $db = isset( $json['db'] ) ? (string) $json['db'] : 'connected';
            return [
                'ok'      => true,
                'message' => __( 'Test berhasil — tersambung ke API dan database Tarombo.', 'ptsbi-premium' ),
                'detail'  => 'db: ' . $db,
            ];
        }

        if ( $code === 401 ) {
            return [
                'ok'      => false,
                'message' => __( 'Belum tersambung: Integration Key salah (ditolak server).', 'ptsbi-premium' ),
                'detail'  => 'HTTP 401',
            ];
        }
        if ( $code === 404 ) {
            return [
                'ok'      => false,
                'message' => __( 'Belum tersambung: endpoint /ping tidak ditemukan (perbarui aplikasi Tarombo).', 'ptsbi-premium' ),
                'detail'  => 'HTTP 404',
            ];
        }
        if ( $code === 503 && is_array( $json ) ) {
            return [
                'ok'      => false,
                'message' => __( 'Belum tersambung: database Tarombo tidak dapat diakses.', 'ptsbi-premium' ),
                'detail'  => isset( $json['error'] ) ? (string) $json['error'] : 'HTTP 503',
            ];
        }

        return [
            'ok'      => false,
            'message' => __( 'Belum tersambung: respons tidak terduga dari server.', 'ptsbi-premium' ),
            'detail'  => 'HTTP ' . $code,
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>|\WP_Error
     */
    public static function post( string $path, array $payload = [] ) {
        return self::request( 'POST', $path, $payload );
    }

    /**
     * @return array<string,mixed>|\WP_Error
     */
    public static function get( string $path ) {
        return self::request( 'GET', $path, null );
    }

    /**
     * @param array<string,mixed>|null $payload
     * @return array<string,mixed>|\WP_Error
     */
    private static function request( string $method, string $path, ?array $payload ) {
        if ( ! self::enabled() ) {
            return new WP_Error( 'ptprm_api_disabled', __( 'Membership API belum diaktifkan.', 'ptsbi-premium' ) );
        }

        $url  = self::base_url() . '/' . ltrim( $path, '/' );
        $args = [
            'method'  => strtoupper( $method ),
            'timeout' => self::timeout(),
            'headers' => [
                'Accept'            => 'application/json',
                'Content-Type'      => 'application/json',
                'X-Integration-Key' => self::integration_key(),
                'X-Idempotency-Key' => wp_generate_uuid4(),
            ],
        ];
        if ( is_array( $payload ) ) {
            $args['body'] = wp_json_encode( $payload );
        }

        $res = wp_remote_request( $url, $args );
        if ( is_wp_error( $res ) ) {
            return $res;
        }

        $code = (int) wp_remote_retrieve_response_code( $res );
        $body = (string) wp_remote_retrieve_body( $res );
        $json = json_decode( $body, true );
        if ( ! is_array( $json ) ) {
            $json = [ 'raw' => $body ];
        }

        if ( $code < 200 || $code >= 300 ) {
            return new WP_Error(
                'ptprm_api_http_' . $code,
                sprintf( __( 'Membership API mengembalikan status %d.', 'ptsbi-premium' ), $code ),
                $json
            );
        }
        return $json;
    }
}
