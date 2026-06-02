<?php
/**
 * Sinkron WordPress ↔ Tarombo (PostgreSQL master via REST v1).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PTPRM_Membership_Sync {

    /**
     * @return array<string,mixed>|\WP_Error
     */
    public static function auth_login( string $email, string $password ) {
        return PTPRM_Membership_Api_Client::post(
            '/auth/login',
            [
                'email'    => $email,
                'password' => $password,
            ]
        );
    }

    /**
     * Pastikan akun WP ada & selaras dengan master Tarombo.
     *
     * @param array<string,mixed> $remote
     */
    public static function ensure_wp_user( array $remote, string $password ): WP_User {
        $email  = sanitize_email( (string) ( $remote['email'] ?? '' ) );
        $name   = PTPRM_Members::sanitize_person_name( sanitize_text_field( (string) ( $remote['full_name'] ?? '' ) ) );
        $role   = self::map_tarombo_role_to_wp( (string) ( $remote['role'] ?? 'member' ) );
        $status = (string) ( $remote['member_status'] ?? 'pending' );

        $user = get_user_by( 'email', $email );
        if ( ! $user instanceof WP_User ) {
            $login = sanitize_user( current( explode( '@', $email ) ), true );
            if ( username_exists( $login ) ) {
                $login = sanitize_user( $login . wp_rand( 100, 999 ), true );
            }
            $user_id = wp_create_user( $login, $password, $email );
            if ( is_wp_error( $user_id ) ) {
                return $user_id;
            }
            $user = get_user_by( 'id', (int) $user_id );
        } else {
            wp_set_password( $password, (int) $user->ID );
        }

        if ( ! $user instanceof WP_User ) {
            return new WP_Error( 'ptprm_sync_user', __( 'Gagal menyiapkan akun WordPress.', 'ptsbi-premium' ) );
        }

        wp_update_user(
            [
                'ID'           => (int) $user->ID,
                'display_name' => $name !== '' ? $name : $user->display_name,
                'role'         => $status === 'active' ? $role : 'subscriber',
            ]
        );

        // NOTE: Status pending/active disimpan di Tarombo (master).
        if ( $status === 'active' ) {
            $user->set_role( $role );
        }

        update_user_meta( (int) $user->ID, 'ptprm_tarombo_user_id', (int) ( $remote['id'] ?? 0 ) );
        if ( class_exists( 'PTPRM_Default_Accounts' ) && $role !== 'administrator' ) {
            $type = $role === PTPRM_Access::ROLE_ORG_ADMIN ? 'admin' : ( $role === 'pengurus' ? 'pengurus' : 'anggota' );
            update_user_meta( (int) $user->ID, PTPRM_Default_Accounts::META_TYPE, $type );
        }

        return $user;
    }

    public static function map_tarombo_role_to_wp( string $tarombo_role ): string {
        $tarombo_role = strtolower( $tarombo_role );
        if ( $tarombo_role === 'admin' ) {
            return PTPRM_Access::ROLE_ORG_ADMIN;
        }
        if ( $tarombo_role === 'developer' ) {
            return 'administrator';
        }
        if ( $tarombo_role === 'pengurus' ) {
            return 'pengurus';
        }
        return PTPRM_Members::ROLE_MEMBER;
    }

    /**
     * @return array<string,mixed>|null
     */
    public static function pull_profile( int $wp_user_id ): ?array {
        if ( ! PTPRM_Membership_Api_Client::enabled() || $wp_user_id <= 0 ) {
            return null;
        }
        $res = PTPRM_Membership_Api_Client::get( '/members/by-wp-user/' . $wp_user_id );
        if ( is_wp_error( $res ) || empty( $res['user']['profile'] ) ) {
            return null;
        }
        $profile = (array) $res['user']['profile'];
        if ( empty( $profile['kepala_keluarga'] ) && ! empty( $res['user']['full_name'] ) ) {
            $fn = PTPRM_Members::sanitize_person_name( (string) $res['user']['full_name'] );
            if ( $fn !== '' ) {
                $profile['kepala_keluarga'] = $fn;
            }
        }
        if ( empty( $profile['phone'] ) && ! empty( $res['user']['phone'] ) ) {
            $profile['phone'] = (string) $res['user']['phone'];
        }
        return $profile;
    }

    /**
     * @param array<string,mixed> $data
     */
    public static function push_profile( int $wp_user_id, array $data ): bool {
        if ( ! PTPRM_Membership_Api_Client::enabled() || $wp_user_id <= 0 ) {
            return false;
        }
        $user    = get_user_by( 'id', $wp_user_id );
        $payload = $data;
        if ( $user instanceof WP_User ) {
            $payload['email'] = (string) $user->user_email;
        }
        $res = PTPRM_Membership_Api_Client::request_json(
            'PUT',
            '/members/by-wp-user/' . $wp_user_id . '/profile',
            $payload
        );
        return ! is_wp_error( $res );
    }

    public static function approve( int $wp_user_id ): bool {
        if ( ! PTPRM_Membership_Api_Client::enabled() || $wp_user_id <= 0 ) {
            return false;
        }
        $res = PTPRM_Membership_Api_Client::post( '/members/by-wp-user/' . $wp_user_id . '/approve', [] );
        return ! is_wp_error( $res );
    }

    public static function profile_complete( int $wp_user_id ): bool {
        if ( ! PTPRM_Membership_Api_Client::enabled() ) {
            return true;
        }
        $res = PTPRM_Membership_Api_Client::get( '/members/by-wp-user/' . $wp_user_id );
        if ( is_wp_error( $res ) ) {
            return false;
        }
        if ( ! empty( $res['user']['profile_complete'] ) ) {
            return true;
        }
        $profile = (array) ( $res['user']['profile'] ?? [] );
        return ! empty( $profile['profile_complete'] );
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function export_rows(): array {
        $res = PTPRM_Membership_Api_Client::get( '/members/export' );
        if ( is_wp_error( $res ) || empty( $res['rows'] ) || ! is_array( $res['rows'] ) ) {
            return [];
        }
        return $res['rows'];
    }
}
