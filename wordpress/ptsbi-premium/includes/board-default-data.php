<?php
/**
 * Data awal pengurus PTSBI (pusat & wilayah) — diisi sekali ke opsi plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * @return array<string, list<array{name:string,role:string,group:string,image:string,featured:int}>>
 */
function ptprm_board_default_catalog(): array {
    $row = static function ( string $role, string $name, string $group = '', int $featured = 0 ): array {
        return [
            'name'     => $name,
            'role'     => $role,
            'group'    => $group,
            'image'    => '',
            'featured' => $featured,
        ];
    };

    $pusat = [
        $row( 'Ketua Umum', 'Ir. Raya Pontus Samosir', '', 1 ),
        $row( 'Ketua Harian', 'Rolaw Samosir' ),
        $row( 'Sekretaris Umum', 'Toman Samosir', '', 1 ),
        $row( 'Sekretaris I', 'Jeny Samosir' ),
        $row( 'Sekretaris II', 'Atur Hamonangan Samosir' ),
        $row( 'Bendahara Umum', 'Parluhutan Sinambela', '', 1 ),
        $row( 'Bendahara I', 'Henri Erikson Samosir' ),
        $row( 'Bendahara II', 'Jepri Sinaturi' ),
        $row( 'Ketua Bidang Adat dan Budaya', 'Alexander Samosir' ),
        $row( 'Ketua Bidang Usaha dan Dana', 'Adi Samosir' ),
        $row( 'Ketua Bidang Sosial, Pendidikan dan Kepemudaan', 'Henrico Samosir' ),
        $row( 'Ketua Bidang Hukum', 'Kardin Samosir' ),
        $row( 'Penasehat', 'Johny Samosir', 'Dewan Penasehat' ),
        $row( 'Penasehat', 'Laurentius Samosir', 'Dewan Penasehat' ),
        $row( 'Penasehat', 'Amansyah Samosri', 'Dewan Penasehat' ),
        $row( 'Penasehat', 'Jisman Samosir', 'Dewan Penasehat' ),
        $row( 'Penasehat', 'Omri Samosir', 'Dewan Penasehat' ),
        $row( 'Penasehat', 'Op. Kenso Samosir', 'Dewan Penasehat' ),
        $row( 'Pakar', 'Agunan Samosir', 'Dewan Pakar' ),
        $row( 'Pakar', 'Dr. Osbin Samosir', 'Dewan Pakar' ),
        $row( 'Pakar', 'Risto Samosir', 'Dewan Pakar' ),
        $row( 'Pakar', 'Dr. Hollan Samosir', 'Dewan Pakar' ),
    ];

    $samosir = [
        $row( 'Ketua', 'Daslon Samosir' ),
        $row( 'Ketua I', 'Jamron Samosir' ),
        $row( 'Sekretaris', 'Freddy Samosir' ),
        $row( 'Sekretaris I', 'Andri Sembi Samosir' ),
        $row( 'Sekretaris II', 'Riwanto Samosir' ),
        $row( 'Bendahara', 'Juni F Harianja' ),
        $row( 'Bendahara I', 'Manatar Samosir' ),
        $row( 'Bendahara II', 'Jhon Wenry Samosir' ),
        $row( 'Ketua', 'Patido Samosir', 'Bidang Adat dan Budaya' ),
        $row( 'Ketua', 'Tondor Samosir', 'Bidang Sosial' ),
        $row( 'Ketua', 'Alfonsus Samosir', 'Bidang Organisasi' ),
        $row( 'Ketua', 'Humisar Samosir', 'Bidang Kepemudaan' ),
        $row( 'Ketua', 'Robert Samosir', 'Koordinator Harian' ),
    ];

    $medan = [
        $row( 'Ketua Umum', 'Ir. Saidi Samosir' ),
        $row( 'Sekretaris Umum', 'Tommy Samosir' ),
        $row( 'Sekretaris II', 'Tunggul Samosir' ),
        $row( 'Bendahara Umum', 'Parkin Sihaloho' ),
        $row( 'Ketua Harian', 'Aron Samosir' ),
        $row( 'Ketua I', 'A.H. Samosir' ),
        $row( 'Ketua II', 'Lintong Samosir' ),
        $row( 'Ketua III', 'J. Samosir' ),
        $row( 'Ketua IV', 'AKBP. Dr. Parulian Samosir, S.H., M.H.' ),
        $row( 'Koordinator', 'Jennefer Samosir', 'Bidang Adat dan Budaya' ),
        $row( 'Ketua', 'St. Drs. D Samosir', 'Bidang Usaha Dana' ),
        $row( 'Ketua', 'Alfonsus Samosir', 'Bidang Organisasi' ),
        $row( 'Koordinator', 'Ardin Dolok Saribu', 'Bidang Kepemudaan, Sosial dan Pendidikan' ),
        $row( 'Koordinator', 'Timbul Samosir', 'Bidang Hukum' ),
    ];

    $pekanbaru = [
        $row( 'Ketua Umum', 'Mangaratua Samosir' ),
        $row( 'Ketua', 'Jhonson Binsar Samosir' ),
        $row( 'Wakil Ketua', 'Parluhutan Samosir' ),
        $row( 'Wakil Ketua', 'Rexson Mangatur Samosir' ),
        $row( 'Wakil Ketua', 'Roncon Samosir' ),
        $row( 'Wakil Ketua', 'Parlindungan Samosir' ),
        $row( 'Sekretaris', 'Manuntun Samosir' ),
        $row( 'Bendahara', 'Nurdin Manullang' ),
    ];

    return [
        'pusat'      => $pusat,
        'samosir'    => $samosir,
        'medan'      => $medan,
        'pekanbaru'  => $pekanbaru,
    ];
}
