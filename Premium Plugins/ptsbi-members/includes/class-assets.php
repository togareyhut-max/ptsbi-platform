<?php
/**
 * Asset portal anggota — otomatis dipakai jika ptsbi-premium tidak memuat frontend.css.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PTSMB_Assets {

    public function __construct() {
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ], 998 );
    }

    public function enqueue(): void {
        if ( defined( 'PTPRM_VERSION' ) ) {
            return;
        }

        wp_enqueue_style(
            'ptsmb-portal',
            PTSMB_URL . 'assets/css/members-portal.css',
            [],
            PTSMB_VERSION
        );

        $this->enqueue_member_scripts();
    }

    private function enqueue_member_scripts(): void {
        if ( class_exists( 'PTPRM_Member_Portal' ) && PTPRM_Member_Portal::should_enqueue_portal_assets() && class_exists( 'PTPRM_Address_Regions' ) ) {
            PTPRM_Address_Regions::enqueue_scripts(
                'ptprm-member-address',
                PTSMB_URL . 'assets/js/member-portal-address.js'
            );
        } elseif ( class_exists( 'PTPRM_Members_Admin' ) && PTPRM_Members_Admin::needs_directory_assets() && class_exists( 'PTPRM_Address_Regions' ) ) {
            PTPRM_Address_Regions::enqueue_scripts(
                'ptprm-member-directory-filter',
                PTSMB_URL . 'assets/js/member-directory-filter.js'
            );
        }
    }
}
