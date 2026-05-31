<?php
/**
 * Opsi anggota — tidak mendefinisikan ptprm_options() agar tidak bentrok dengan ptsbi-premium.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'ptsmb_options' ) ) {
    /**
     * @return array<string, mixed>
     */
    function ptsmb_options(): array {
        if ( function_exists( 'ptprm_options' ) ) {
            return ptprm_options();
        }

        static $cache = null;
        if ( is_array( $cache ) ) {
            return $cache;
        }

        $defaults = [
            'org_name'                       => get_bloginfo( 'name' ),
            'whatsapp'                       => '',
            'members_register_show'          => 1,
            'members_directory_show'         => 1,
            'members_per_page'               => 25,
            'portal_login_slug'              => 'rumah-anggota',
            'members_login_slug'             => 'rumah-anggota',
            'members_portal_slug'            => 'area-anggota',
            'admin_login_slug'               => 'masuk-pengurus',
            'admin_portal_slug'              => 'panel-pengurus',
            'members_register_auto_approve'  => 0,
            'members_register_notify_email'  => 1,
            'members_register_notify_wa'     => 1,
            'membership_api_enabled'         => 0,
            'membership_api_base_url'        => '',
            'membership_api_integration_key' => '',
            'membership_api_timeout_sec'     => 15,
        ];

        $option = defined( 'PTPRM_OPTION' ) ? PTPRM_OPTION : 'ptprm_options';
        $saved  = get_option( $option, [] );
        if ( ! is_array( $saved ) ) {
            $saved = [];
        }

        $cache = array_merge( $defaults, $saved );
        return $cache;
    }
}

if ( ! function_exists( 'ptsmb_wa_digits' ) ) {
    /**
     * @param mixed $number
     */
    function ptsmb_wa_digits( $number ): string {
        if ( function_exists( 'ptprm_wa_digits' ) ) {
            return ptprm_wa_digits( $number );
        }
        $d = preg_replace( '/\D/', '', (string) $number );
        if ( ! $d ) {
            return '';
        }
        if ( strpos( $d, '62' ) !== 0 && strpos( $d, '0' ) === 0 ) {
            $d = '62' . substr( $d, 1 );
        }
        return $d;
    }
}
