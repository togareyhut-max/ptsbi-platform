<?php
/**
 * Capabilities anggota — dipakai bersama PTPRM_Access di ptsbi-premium.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PTPRM_Access_Members {

    public static function ensure_capabilities(): void {
        if ( ! class_exists( 'PTPRM_Access' ) ) {
            return;
        }
        PTPRM_Access::ensure_capabilities();
    }
}
