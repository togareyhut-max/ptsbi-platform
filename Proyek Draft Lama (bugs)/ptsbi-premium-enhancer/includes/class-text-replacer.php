<?php
/**
 * Lightweight text replacement helpers, applied on the client via JS only.
 * The server side just provides the rules array.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PTSBI_PE_Text_Replacer {

    public function rules() {
        $o = ptsbi_pe_get_options();
        $rules = [];

        if ( (int) $o['fix_address_label'] === 1 ) {
            $rules[] = [ 'find' => 'Address:', 'replace' => 'Alamat:' ];
            $rules[] = [ 'find' => 'ADDRESS:', 'replace' => 'ALAMAT:' ];
        }
        if ( (int) $o['fix_hours_label'] === 1 ) {
            $rules[] = [ 'find' => 'Mon-Fri 9:00AM - 5:00PM', 'replace' => 'Senin–Jumat, 09.00–17.00 WIB' ];
            $rules[] = [ 'find' => 'Mon-Fri 9:00AM-5:00PM',   'replace' => 'Senin–Jumat, 09.00–17.00 WIB' ];
            $rules[] = [ 'find' => 'Mon-Fri',                 'replace' => 'Senin–Jumat' ];
        }

        return $rules;
    }
}
