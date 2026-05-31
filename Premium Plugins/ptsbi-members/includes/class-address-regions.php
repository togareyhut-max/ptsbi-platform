<?php
/**
 * Master wilayah: kategori Jabodetabek + hierarki Kepmendagri (provinsi → kab/kota → kecamatan → kelurahan).
 * Sumber: assets/data/indonesia-regions/ (wilayah.id / Kepmendagri 2025).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( class_exists( 'PTPRM_Address_Regions', false ) ) {
    return;
}

class PTPRM_Address_Regions {

    private const DATA_DIR     = 'assets/data/indonesia-regions/';
    private const INDEX_FILE   = 'assets/data/indonesia-regions/index.json';
    private const REGION_JABO  = 'jabodetabek';

    /** @var array<string,mixed>|null */
    private static $index = null;

    /** @var array<string,array<string,mixed>> */
    private static $province_cache = [];

    private static function plugin_dir(): string {
        if ( defined( 'PTSMB_DIR' ) ) {
            return PTSMB_DIR;
        }
        return defined( 'PTPRM_DIR' ) ? PTPRM_DIR : '';
    }

    private static function plugin_url(): string {
        if ( defined( 'PTSMB_URL' ) ) {
            return PTSMB_URL;
        }
        return defined( 'PTPRM_URL' ) ? PTPRM_URL : '';
    }

    public static function init(): void {
        add_action( 'wp_ajax_ptprm_address_regions', [ __CLASS__, 'ajax_tree' ] );
    }

    /**
     * Indeks ringan: wilayah Jabodetabek + daftar provinsi (tanpa kecamatan/kelurahan).
     *
     * @return array{version?:int,source?:string,hierarchy?:array<int,string>,regions:array<int,array<string,mixed>>,provinces:array<int,array{code:string,name:string}>}
     */
    public static function get_index(): array {
        if ( is_array( self::$index ) ) {
            return self::$index;
        }

        $index = [
            'regions'   => [
                [
                    'id'          => self::REGION_JABO,
                    'name'        => 'Jabodetabek',
                    'description' => __( 'Jakarta dan penyangga (Bogor, Depok, Bekasi, Tangerang)', 'ptsbi-premium' ),
                ],
            ],
            'provinces' => [],
        ];

        $path = self::plugin_dir() . self::INDEX_FILE;
        if ( is_readable( $path ) ) {
            $raw = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
            if ( is_string( $raw ) && $raw !== '' ) {
                $json = json_decode( $raw, true );
                if ( is_array( $json ) ) {
                    $index = array_merge( $index, $json );
                }
            }
        }

        self::$index = $index;
        return $index;
    }

    /**
     * @return array{version?:int,source?:string,hierarchy?:array<int,string>,regions:array<int,array<string,mixed>>,provinces:array<int,array{code:string,name:string}>}
     */
    public static function get_tree(): array {
        return self::get_index();
    }

    public static function province_code_by_name( string $name ): string {
        $name = trim( $name );
        if ( $name === '' ) {
            return '';
        }
        foreach ( self::get_index()['provinces'] ?? [] as $prov ) {
            if ( self::norm_key( (string) ( $prov['name'] ?? '' ) ) === self::norm_key( $name ) ) {
                return (string) ( $prov['code'] ?? '' );
            }
        }
        return '';
    }

    /**
     * Muat satu provinsi lengkap (kab/kota → kecamatan → kelurahan + kode pos).
     *
     * @return array{code?:string,name:string,cities:array<int,array<string,mixed>>}
     */
    public static function get_province_tree( string $province_code ): array {
        $province_code = preg_replace( '/\D/', '', $province_code );
        if ( $province_code === '' ) {
            return [ 'name' => '', 'cities' => [] ];
        }

        if ( isset( self::$province_cache[ $province_code ] ) ) {
            return self::$province_cache[ $province_code ];
        }

        $path = self::plugin_dir() . self::DATA_DIR . 'provinces/' . $province_code . '.json';
        $node = [ 'code' => $province_code, 'name' => '', 'cities' => [] ];
        if ( is_readable( $path ) ) {
            $raw = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
            if ( is_string( $raw ) && $raw !== '' ) {
                $json = json_decode( $raw, true );
                if ( is_array( $json ) ) {
                    $node = $json;
                }
            }
        }

        $wrapped = self::sanitize_tree( [ 'provinces' => [ $node ] ] );
        $node    = $wrapped['provinces'][0] ?? $node;
        self::merge_db_into_province( $node, $province_code );
        $wrapped = self::sort_tree( [ 'provinces' => [ $node ] ] );
        $node    = $wrapped['provinces'][0] ?? $node;

        self::$province_cache[ $province_code ] = $node;
        return $node;
    }

    /**
     * @param array<string,mixed> $prov_node
     */
    private static function merge_db_into_province( array &$prov_node, string $province_code ): void {
        global $wpdb;
        $table = $wpdb->prefix . 'ptprm_address_master';
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            return;
        }

        $prov_name = (string) ( $prov_node['name'] ?? '' );
        $rows      = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "SELECT province, city, district, subdistrict, postal_code FROM {$table} WHERE country_code IN ('ID','') AND (province = %s OR province = '' OR province IS NULL)",
                $prov_name
            ),
            ARRAY_A
        );
        if ( ! is_array( $rows ) ) {
            return;
        }

        $tree = [ 'provinces' => [ $prov_node ] ];
        foreach ( $rows as $row ) {
            $city = trim( (string) ( $row['city'] ?? '' ) );
            $kec  = trim( (string) ( $row['district'] ?? '' ) );
            $kel  = trim( (string) ( $row['subdistrict'] ?? '' ) );
            $pos  = trim( (string) ( $row['postal_code'] ?? '' ) );
            if ( $city === '' || $kec === '' ) {
                continue;
            }
            $resolved = self::resolve_path_on_province( $prov_node, $city, $kec, $kel, $prov_name );
            if ( null === $resolved ) {
                continue;
            }
            self::insert_path( $tree, $prov_name, $resolved['city'], $resolved['district'], $resolved['subdistrict'], $pos );
        }
        $prov_node = $tree['provinces'][0] ?? $prov_node;
    }

    public static function ajax_tree(): void {
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => __( 'Login diperlukan.', 'ptsbi-premium' ) ], 403 );
        }

        $code = isset( $_GET['province'] ) ? preg_replace( '/\D/', '', (string) wp_unslash( $_GET['province'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( $code !== '' ) {
            wp_send_json_success( self::get_province_tree( $code ) );
        }

        wp_send_json_success( self::get_index() );
    }

    public static function localize_script_data(): array {
        return [
            'index'   => self::get_index(),
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'action'  => 'ptprm_address_regions',
        ];
    }

    public static function enqueue_scripts( string $handle, string $src, array $deps = [] ): void {
        wp_enqueue_script(
            'ptprm-address-regions',
            self::plugin_url() . 'assets/js/address-regions.js',
            [],
            defined( 'PTSMB_VERSION' ) ? PTSMB_VERSION : PTPRM_VERSION,
            true
        );
        wp_localize_script( 'ptprm-address-regions', 'PTPRM_ADDRESS', self::localize_script_data() );

        $all_deps = array_merge( [ 'ptprm-address-regions' ], $deps );
        wp_enqueue_script( $handle, $src, $all_deps, defined( 'PTSMB_VERSION' ) ? PTSMB_VERSION : PTPRM_VERSION, true );
    }

    /**
     * @param array{provinces:array<int,array<string,mixed>>} $tree
     * @return array{provinces:array<int,array<string,mixed>>}
     */
    private static function sanitize_tree( array $tree ): array {
        $clean = [ 'provinces' => [] ];
        foreach ( $tree['provinces'] ?? [] as $prov ) {
            $pname = trim( (string) ( $prov['name'] ?? '' ) );
            if ( $pname === '' || self::is_junk_name( $pname ) ) {
                continue;
            }
            $cities = [];
            foreach ( $prov['cities'] ?? [] as $city ) {
                $cname = trim( (string) ( $city['name'] ?? '' ) );
                if ( $cname === '' || self::is_junk_name( $cname ) ) {
                    continue;
                }
                $districts = [];
                foreach ( $city['districts'] ?? [] as $dist ) {
                    $dname = trim( (string) ( $dist['name'] ?? '' ) );
                    if ( $dname === '' || self::is_junk_name( $dname ) ) {
                        continue;
                    }
                    $subs = [];
                    foreach ( $dist['subdistricts'] ?? [] as $sub ) {
                        $sname = trim( (string) ( $sub['name'] ?? '' ) );
                        if ( $sname === '' || self::is_junk_name( $sname ) ) {
                            continue;
                        }
                        $leaf = [ 'name' => $sname ];
                        $pos  = trim( (string) ( $sub['postal_code'] ?? '' ) );
                        if ( $pos !== '' ) {
                            $leaf['postal_code'] = $pos;
                        }
                        $subs[] = $leaf;
                    }
                    $districts[] = [
                        'name'          => $dname,
                        'subdistricts'  => $subs,
                    ];
                }
                $cities[] = [
                    'name'      => $cname,
                    'districts' => $districts,
                ];
            }
            $clean['provinces'][] = [
                'name'   => $pname,
                'cities' => $cities,
            ];
        }
        return $clean;
    }

    private static function is_junk_name( string $name ): bool {
        $n = self::norm_key( $name );
        if ( $n === '' || $n === '-' || $n === '0' || $n === '1' ) {
            return true;
        }
        return (bool) preg_match( '/^-+$/', $n );
    }

    /**
     * Cocokkan input ke nama resmi di pohon Kepmendagri (tanpa menambah kota duplikat).
     *
     * @param array{provinces:array<int,array<string,mixed>>} $tree
     * @return array{province:string,city:string,district:string,subdistrict:string}|null
     */
    public static function resolve_path( array $tree, string $province, string $city, string $district, string $subdistrict ): ?array {
        $province = trim( $province );
        if ( $province === '' ) {
            $province = self::guess_province( $city );
        }

        $prov_node = self::find_province_node( $tree, $province );
        if ( null === $prov_node ) {
            $code = self::province_code_by_name( $province );
            if ( $code === '' && $city !== '' ) {
                $code = self::province_code_by_name( self::guess_province( $city ) );
            }
            if ( $code !== '' ) {
                $prov_node = self::get_province_tree( $code );
            }
        }
        if ( null === $prov_node ) {
            return null;
        }

        return self::resolve_path_on_province( $prov_node, $city, $district, $subdistrict, (string) ( $prov_node['name'] ?? $province ) );
    }

    /**
     * @param array<string,mixed> $prov_node
     * @return array{province:string,city:string,district:string,subdistrict:string}|null
     */
    private static function resolve_path_on_province( array $prov_node, string $city, string $district, string $subdistrict, string $province_name = '' ): ?array {
        $city        = trim( $city );
        $district    = trim( $district );
        $subdistrict = trim( $subdistrict );
        $province    = $province_name !== '' ? $province_name : (string) ( $prov_node['name'] ?? '' );

        $city_node = self::find_city_node( $prov_node, $city );
        if ( null === $city_node ) {
            return null;
        }

        $canonical_city = (string) ( $city_node['name'] ?? '' );

        if ( $district === '' || self::is_junk_name( $district ) ) {
            return [
                'province'    => $province,
                'city'        => $canonical_city,
                'district'    => '',
                'subdistrict' => $subdistrict,
            ];
        }

        $dist_node = self::find_district_node( $city_node, $district );
        if ( null === $dist_node ) {
            return null;
        }

        $canonical_dist = (string) ( $dist_node['name'] ?? '' );
        $canonical_sub  = $subdistrict;
        if ( $subdistrict !== '' && ! self::is_junk_name( $subdistrict ) ) {
            $sub_node = self::find_subdistrict_node( $dist_node, $subdistrict );
            if ( null !== $sub_node ) {
                $canonical_sub = (string) ( $sub_node['name'] ?? $subdistrict );
            }
        }

        return [
            'province'    => $province,
            'city'        => $canonical_city,
            'district'    => $canonical_dist,
            'subdistrict' => $canonical_sub,
        ];
    }

    /**
     * @param array{provinces:array<int,array<string,mixed>>} $tree
     * @return array<string,mixed>|null
     */
    private static function find_province_node( array $tree, string $province ): ?array {
        foreach ( $tree['provinces'] ?? [] as $prov ) {
            if ( self::norm_key( (string) ( $prov['name'] ?? '' ) ) === self::norm_key( $province ) ) {
                return $prov;
            }
        }
        return null;
    }

    /**
     * @param array<string,mixed> $prov_node
     * @return array<string,mixed>|null
     */
    private static function find_city_node( array $prov_node, string $city ): ?array {
        if ( $city === '' || self::is_junk_name( $city ) ) {
            return null;
        }
        $cities = $prov_node['cities'] ?? [];
        $ci     = self::index_of( $cities, $city );
        if ( $ci >= 0 ) {
            return $cities[ $ci ];
        }

        $alias = self::city_alias( (string) ( $prov_node['name'] ?? '' ), $city );
        if ( $alias !== '' ) {
            $ci = self::index_of( $cities, $alias );
            if ( $ci >= 0 ) {
                return $cities[ $ci ];
            }
        }

        $needle = self::norm_key( $city );
        foreach ( $cities as $item ) {
            $cname = self::norm_key( (string) ( $item['name'] ?? '' ) );
            if ( $cname === $needle || ( $cname !== '' && strpos( $needle, $cname ) !== false ) || ( $needle !== '' && strpos( $cname, $needle ) !== false ) ) {
                return $item;
            }
        }
        return null;
    }

    /**
     * @param array<string,mixed> $city_node
     * @return array<string,mixed>|null
     */
    private static function find_district_node( array $city_node, string $district ): ?array {
        $districts = $city_node['districts'] ?? [];
        $di        = self::index_of( $districts, $district );
        if ( $di >= 0 ) {
            return $districts[ $di ];
        }
        $needle = self::norm_key( $district );
        foreach ( $districts as $item ) {
            $dname = self::norm_key( (string) ( $item['name'] ?? '' ) );
            if ( $dname === $needle || ( $dname !== '' && strpos( $needle, $dname ) !== false ) || ( $needle !== '' && strpos( $dname, $needle ) !== false ) ) {
                return $item;
            }
        }
        return null;
    }

    /**
     * @param array<string,mixed> $dist_node
     * @return array<string,mixed>|null
     */
    private static function find_subdistrict_node( array $dist_node, string $subdistrict ): ?array {
        $subs = $dist_node['subdistricts'] ?? [];
        $si   = self::index_of( $subs, $subdistrict );
        if ( $si >= 0 ) {
            return $subs[ $si ];
        }
        $needle = self::norm_key( $subdistrict );
        foreach ( $subs as $item ) {
            $sname = self::norm_key( (string) ( $item['name'] ?? '' ) );
            if ( $sname === $needle || ( $sname !== '' && strpos( $needle, $sname ) !== false ) ) {
                return $item;
            }
        }
        return null;
    }

    /**
     * Alias kota dari data lama (DAMI / sektor) ke nama resmi Kepmendagri.
     */
    public static function city_alias( string $province, string $city ): string {
        $c = self::norm_key( $city );
        if ( $c === '' ) {
            return '';
        }

        $jakarta = [
            'jakartapusat'     => 'Jakarta Pusat',
            'jakartautara'     => 'Jakarta Utara',
            'jakartabarat'     => 'Jakarta Barat',
            'jakartaselatan'   => 'Jakarta Selatan',
            'jakartatimur'     => 'Jakarta Timur',
            'jakartatimur1'    => 'Jakarta Timur',
            'jakartatimur2'    => 'Jakarta Timur',
            'jkttimur1'        => 'Jakarta Timur',
            'jkttimur2'        => 'Jakarta Timur',
            'cijantung'        => 'Jakarta Timur',
            'ciracas'          => 'Jakarta Timur',
            'cipayung'         => 'Jakarta Timur',
            'cakung'           => 'Jakarta Timur',
            'matraman'         => 'Jakarta Timur',
            'pulogadung'       => 'Jakarta Timur',
            'durensawit'       => 'Jakarta Timur',
            'pasarrebo'        => 'Jakarta Timur',
            'cililitan'        => 'Jakarta Timur',
        ];
        foreach ( $jakarta as $needle => $label ) {
            if ( strpos( $c, $needle ) !== false ) {
                return $label;
            }
        }

        $jabar = [
            'kotabekasi'       => 'Kota Bekasi',
            'bekasikota'       => 'Kota Bekasi',
            'bekasi2'          => 'Kota Bekasi',
            'bekasi1'          => 'Kota Bekasi',
            'bekasikotabks1'   => 'Kota Bekasi',
            'jatiasih'         => 'Kota Bekasi',
            'pondokgede'       => 'Kota Bekasi',
            'jatisampurna'     => 'Kota Bekasi',
            'rawalumbu'        => 'Kota Bekasi',
            'kabupatenbekasi'  => 'Kabupaten Bekasi',
            'cibitung'         => 'Kabupaten Bekasi',
            'cikarang'         => 'Kabupaten Bekasi',
            'bogorkota'        => 'Kota Bogor',
            'kabupatenbogor'   => 'Kabupaten Bogor',
            'cibinong'         => 'Kabupaten Bogor',
            'depokkota'        => 'Kota Depok',
            'depok1'           => 'Kota Depok',
            'cimanggis'        => 'Kota Depok',
            'kotatangerang'    => 'Kota Tangerang',
            'tangerangkota'    => 'Kota Tangerang',
            'tangerangselatan' => 'Kota Tangerang Selatan',
            'tangsel'          => 'Kota Tangerang Selatan',
            'cipondoh'         => 'Kota Tangerang',
            'ciledug'          => 'Kota Tangerang',
            'karawaci'         => 'Kota Tangerang',
            'kabupatentangerang' => 'Kabupaten Tangerang',
        ];
        foreach ( $jabar as $needle => $label ) {
            if ( strpos( $c, $needle ) !== false ) {
                return $label;
            }
        }

        return '';
    }

    /**
     * @param array{provinces:array<int,array<string,mixed>>} $tree
     */
    private static function insert_path( array &$tree, string $prov, string $city, string $kec, string $kel, string $pos ): void {
        $pi = self::index_of( $tree['provinces'], $prov );
        if ( $pi < 0 ) {
            $tree['provinces'][] = [ 'name' => $prov, 'cities' => [] ];
            $pi                  = count( $tree['provinces'] ) - 1;
        }

        $ci = self::index_of( $tree['provinces'][ $pi ]['cities'], $city );
        if ( $ci < 0 ) {
            $tree['provinces'][ $pi ]['cities'][] = [ 'name' => $city, 'districts' => [] ];
            $ci                                    = count( $tree['provinces'][ $pi ]['cities'] ) - 1;
        }

        $di = self::index_of( $tree['provinces'][ $pi ]['cities'][ $ci ]['districts'], $kec );
        if ( $di < 0 ) {
            $tree['provinces'][ $pi ]['cities'][ $ci ]['districts'][] = [ 'name' => $kec, 'subdistricts' => [] ];
            $di                                                        = count( $tree['provinces'][ $pi ]['cities'][ $ci ]['districts'] ) - 1;
        }

        if ( $kel === '' ) {
            return;
        }

        $subs = &$tree['provinces'][ $pi ]['cities'][ $ci ]['districts'][ $di ]['subdistricts'];
        $si   = self::index_of( $subs, $kel );
        if ( $si < 0 ) {
            $leaf = [ 'name' => $kel ];
            if ( $pos !== '' ) {
                $leaf['postal_code'] = $pos;
            }
            $subs[] = $leaf;
            return;
        }
        if ( $pos !== '' ) {
            $subs[ $si ]['postal_code'] = $pos;
        }
    }

    /**
     * @param array<int,array{name:string}> $list
     */
    private static function index_of( array $list, string $name ): int {
        $n = self::norm_key( $name );
        foreach ( $list as $i => $item ) {
            if ( self::norm_key( (string) ( $item['name'] ?? '' ) ) === $n ) {
                return (int) $i;
            }
        }
        return -1;
    }

    /**
     * @param array{provinces:array<int,array<string,mixed>>} $tree
     * @return array{provinces:array<int,array<string,mixed>>}
     */
    private static function sort_tree( array $tree ): array {
        if ( empty( $tree['provinces'] ) ) {
            return $tree;
        }
        usort(
            $tree['provinces'],
            static fn( $a, $b ) => strcasecmp( (string) ( $a['name'] ?? '' ), (string) ( $b['name'] ?? '' ) )
        );
        foreach ( $tree['provinces'] as &$prov ) {
            if ( empty( $prov['cities'] ) ) {
                continue;
            }
            usort( $prov['cities'], static fn( $a, $b ) => strcasecmp( (string) ( $a['name'] ?? '' ), (string) ( $b['name'] ?? '' ) ) );
            foreach ( $prov['cities'] as &$city ) {
                if ( empty( $city['districts'] ) ) {
                    continue;
                }
                usort( $city['districts'], static fn( $a, $b ) => strcasecmp( (string) ( $a['name'] ?? '' ), (string) ( $b['name'] ?? '' ) ) );
                foreach ( $city['districts'] as &$dist ) {
                    if ( empty( $dist['subdistricts'] ) ) {
                        continue;
                    }
                    usort( $dist['subdistricts'], static fn( $a, $b ) => strcasecmp( (string) ( $a['name'] ?? '' ), (string) ( $b['name'] ?? '' ) ) );
                }
            }
        }
        return $tree;
    }

    public static function guess_province( string $city ): string {
        $k = self::norm_key( $city );
        if ( $k === '' ) {
            return '';
        }
        if ( strpos( $k, 'jakarta' ) !== false || strpos( $k, 'jkttimur' ) !== false ) {
            return 'DKI Jakarta';
        }
        $banten = [ 'tangerang', 'tangsel', 'serpong', 'cipondoh', 'ciledug', 'karawaci', 'cilegon', 'serang', 'balaraja', 'tigaraksa', 'pasarkemis', 'curug' ];
        foreach ( $banten as $needle ) {
            if ( strpos( $k, $needle ) !== false ) {
                return 'Banten';
            }
        }
        $jabar = [ 'bekasi', 'depok', 'bogor', 'cikarang', 'cibitung', 'babelan', 'cimanggis', 'cikampek', 'karawang', 'bandung', 'sukabumi', 'cianjur', 'garut', 'tasik', 'cirebon', 'sumedang', 'purwakarta', 'subang', 'indramayu', 'majalengka', 'kuningan', 'ciamis', 'pangandaran' ];
        foreach ( $jabar as $needle ) {
            if ( strpos( $k, $needle ) !== false ) {
                return 'Jawa Barat';
            }
        }
        return '';
    }

    public static function norm( string $s ): string {
        $s = trim( strtolower( $s ) );
        $s = preg_replace( '/\s+/', ' ', $s );
        return is_string( $s ) ? $s : '';
    }

    public static function norm_key( string $s ): string {
        $s = self::norm( $s );
        $s = preg_replace( '/\([^)]*\)/', '', $s );
        $s = preg_replace( '/[^a-z0-9\s]/', '', $s );
        $s = preg_replace( '/\s+/', '', $s );
        return is_string( $s ) ? $s : '';
    }

    /**
     * Normalisasi provinsi/kota/kecamatan untuk penyimpanan (impor & profil).
     *
     * @return array{province:string,city:string,district:string,subdistrict:string}
     */
    public static function normalize_address_fields( string $province, string $city, string $district, string $subdistrict ): array {
        $prov = trim( $province );
        if ( $prov === '' && $city !== '' ) {
            $prov = self::guess_province( $city );
        }

        $code = self::province_code_by_name( $prov );
        if ( $code === '' && $prov === '' && $city !== '' ) {
            $code = self::province_code_by_name( self::guess_province( $city ) );
        }

        $tree = [ 'provinces' => [] ];
        if ( $code !== '' ) {
            $tree['provinces'][] = self::get_province_tree( $code );
        }

        $resolved = $tree['provinces'] ? self::resolve_path( $tree, $prov, $city, $district, $subdistrict ) : null;
        if ( is_array( $resolved ) ) {
            return $resolved;
        }

        $alias_city = self::city_alias( $prov, $city );
        if ( $alias_city !== '' && $tree['provinces'] ) {
            $resolved = self::resolve_path( $tree, $prov, $alias_city, $district, $subdistrict );
            if ( is_array( $resolved ) ) {
                return $resolved;
            }
            $city = $alias_city;
        }

        return [
            'province'    => $prov,
            'city'        => trim( $city ),
            'district'    => trim( $district ),
            'subdistrict' => trim( $subdistrict ),
        ];
    }

    public static function render_wilayah_select( string $current = 'jabodetabek' ): void {
        echo '<label class="ptprm-address-field" data-ptprm-field="wilayah">';
        echo '<span>' . esc_html__( 'Wilayah', 'ptsbi-members' ) . '</span>';
        echo '<select name="address_wilayah" data-ptprm-wilayah data-current="' . esc_attr( $current ) . '">';
        echo '<option value="jabodetabek"' . selected( $current, 'jabodetabek', false ) . '>' . esc_html__( 'Jabodetabek (Jakarta, Bogor, Tangerang, Bekasi)', 'ptsbi-members' ) . '</option>';
        echo '<option value="all"' . selected( $current, 'all', false ) . '>' . esc_html__( 'Seluruh Indonesia', 'ptsbi-members' ) . '</option>';
        echo '<option value="overseas"' . selected( $current, 'overseas', false ) . '>' . esc_html__( 'Luar Negeri', 'ptsbi-members' ) . '</option>';
        echo '</select></label>';
        echo '<p class="ptprm-portal-help ptprm-member-full">' . esc_html__( 'Jabodetabek: Jakarta & penyangga. Seluruh Indonesia: semua provinsi. Luar negeri: isi alamat manual.', 'ptsbi-members' ) . '</p>';
    }

    /**
     * @param array<string,string> $attrs
     */
    public static function render_select( string $name, string $label, string $current, array $attrs = [] ): void {
        $attr_str = '';
        foreach ( $attrs as $k => $v ) {
            $attr_str .= ' ' . esc_attr( $k ) . '="' . esc_attr( (string) $v ) . '"';
        }
        echo '<label class="ptprm-address-field" data-ptprm-field="' . esc_attr( $name ) . '">';
        echo '<span>' . esc_html( $label ) . '</span>';
        echo '<select name="' . esc_attr( $name ) . '"' . $attr_str . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '<option value="">' . esc_html__( '— Pilih —', 'ptsbi-premium' ) . '</option>';
        if ( $current !== '' ) {
            echo '<option value="' . esc_attr( $current ) . '" selected>' . esc_html( $current ) . '</option>';
        }
        echo '</select></label>';
    }
}
