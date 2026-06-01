<?php
/**
 * Sinkron data portal WordPress ke database Tarombo (Membership API).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PTPRM_Membership_Sync {

    /**
     * @param array<string,mixed> $profile
     */
    public static function push_member_profile( int $user_id, array $profile ): void {
        if ( ! class_exists( 'PTPRM_Membership_Api_Client' ) || ! PTPRM_Membership_Api_Client::enabled() ) {
            return;
        }
        $user = get_userdata( $user_id );
        if ( ! $user instanceof WP_User ) {
            return;
        }
        PTPRM_Membership_Api_Client::post(
            '/members/profile',
            [
                'wp_user_id' => $user_id,
                'email'      => $user->user_email,
                'profile'    => $profile,
            ]
        );
    }

    /**
     * @param array<string,mixed> $patch Keys to merge into ptprm_options mirror in Tarombo.
     */
    public static function push_options_patch( array $patch ): void {
        if ( ! class_exists( 'PTPRM_Membership_Api_Client' ) || ! PTPRM_Membership_Api_Client::enabled() ) {
            return;
        }
        if ( $patch === [] ) {
            return;
        }
        $payload = [];
        foreach ( $patch as $key => $value ) {
            $payload[ 'ptprm_' . ltrim( (string) $key, 'ptprm_' ) ] = $value;
        }
        PTPRM_Membership_Api_Client::post( '/settings/options', [ 'options' => $payload ] );
    }

    /**
     * @param list<array<string,mixed>> $items
     */
    public static function push_pdf_items( array $items ): void {
        if ( ! class_exists( 'PTPRM_Membership_Api_Client' ) || ! PTPRM_Membership_Api_Client::enabled() ) {
            return;
        }
        PTPRM_Membership_Api_Client::post( '/settings/pdf-items', [ 'pdf_items' => $items ] );
    }

    public static function push_full_options(): void {
        if ( ! class_exists( 'PTPRM_Membership_Api_Client' ) || ! PTPRM_Membership_Api_Client::enabled() ) {
            return;
        }
        $o = ptprm_options();
        self::push_options_patch( $o );
        if ( ! empty( $o['pdf_items'] ) ) {
            $decoded = json_decode( (string) $o['pdf_items'], true );
            if ( is_array( $decoded ) ) {
                self::push_pdf_items( ptprm_sanitize_pdf_items( $decoded ) );
            }
        }
    }
}
