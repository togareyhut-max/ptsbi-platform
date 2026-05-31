<?php
/**
 * Halaman standar organisasi premium + sinkron menu WordPress.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PTPRM_Pages {

    const MENU_OPTION = 'ptprm_main_menu_id';

    /**
     * @return array<string, array{title:string, excerpt:string, content:string}>
     */
    public static function blueprints(): array {
        $org = PTPRM_Bootstrap::org_name();

        return [
            'tentang-kami' => [
                'title'   => __( 'Tentang Kami', 'ptsbi-premium' ),
                'excerpt' => sprintf(
                    /* translators: %s: organization name */
                    __( 'Profil, visi, dan sejarah %s.', 'ptsbi-premium' ),
                    $org
                ),
                'content' => self::block_content(
                    sprintf( __( 'Mengenal %s', 'ptsbi-premium' ), $org ),
                    sprintf(
                        __( '%s adalah wadah kebersamaan yang mempererat persaudaraan, melestarikan budaya, dan mendukung anggota di berbagai kegiatan.', 'ptsbi-premium' ),
                        esc_html( $org )
                    ) . "\n\n" .
                    __( 'Kami mengadakan pertemuan rutin, program sosial, dan dokumentasi sejarah organisasi. Setiap anggota dipersilakan berpartisipasi sesuai minat dan kemampuan.', 'ptsbi-premium' ) . "\n\n" .
                    __( 'Visi kami: organisasi yang solid, inklusif, dan relevan untuk generasi sekarang dan mendatang.', 'ptsbi-premium' )
                ),
            ],
            'program' => [
                'title'   => __( 'Program Kerja', 'ptsbi-premium' ),
                'excerpt' => __( 'Program dan bidang kerja organisasi.', 'ptsbi-premium' ),
                'content' => self::block_content(
                    __( 'Program & Bidang Kerja', 'ptsbi-premium' ),
                    __( 'Organisasi kami menjalankan program di bidang sosial, budaya, pendidikan, dan kepemudaan. Berikut ringkasan bidang utama:', 'ptsbi-premium' ) . "\n\n" .
                    "<ul>\n<li>" . __( 'Silaturahmi & kekeluargaan antar anggota', 'ptsbi-premium' ) . "</li>\n" .
                    "<li>" . __( 'Pelestarian adat dan kegiatan budaya', 'ptsbi-premium' ) . "</li>\n" .
                    "<li>" . __( 'Bantuan sosial dan tanggap darurat anggota', 'ptsbi-premium' ) . "</li>\n" .
                    "<li>" . __( 'Pemberdayaan generasi muda', 'ptsbi-premium' ) . "</li>\n</ul>\n\n" .
                    __( 'Detail agenda dan laporan kegiatan dapat dilihat di halaman Kegiatan.', 'ptsbi-premium' )
                ),
            ],
            'kegiatan' => [
                'title'   => __( 'Kegiatan', 'ptsbi-premium' ),
                'excerpt' => __( 'Berita dan dokumentasi kegiatan terbaru.', 'ptsbi-premium' ),
                'content' => self::block_content(
                    __( 'Kegiatan & Berita', 'ptsbi-premium' ),
                    __( 'Halaman ini menampilkan arsip kegiatan organisasi. Tambahkan postingan WordPress dengan kategori “Kegiatan” atau gunakan blok daftar postingan untuk menampilkan berita terbaru.', 'ptsbi-premium' ) . "\n\n" .
                    __( 'Pengumuman penting juga disampaikan melalui WhatsApp resmi dan media sosial organisasi.', 'ptsbi-premium' )
                ),
            ],
            'struktur-organisasi' => [
                'title'   => __( 'Struktur Organisasi', 'ptsbi-premium' ),
                'excerpt' => __( 'Susunan pengurus dan bidang kerja.', 'ptsbi-premium' ),
                'content' => self::block_content(
                    __( 'Struktur Kepengurusan', 'ptsbi-premium' ),
                    __( 'Struktur organisasi terdiri dari pengurus inti, koordinator bidang, dan perwakilan wilayah. Susunan lengkap juga ditampilkan di section Pengurus pada beranda.', 'ptsbi-premium' ) . "\n\n" .
                    __( 'Untuk pertanyaan terkait kepengurusan, silakan hubungi sekretariat melalui halaman Kontak.', 'ptsbi-premium' )
                ),
            ],
            'galeri' => [
                'title'   => __( 'Galeri', 'ptsbi-premium' ),
                'excerpt' => __( 'Dokumentasi foto kegiatan.', 'ptsbi-premium' ),
                'content' => self::block_content(
                    __( 'Galeri Foto', 'ptsbi-premium' ),
                    __( 'Kumpulan dokumentasi pertemuan, acara adat, dan program sosial. Aktifkan section Galeri di pengaturan plugin Beranda untuk menampilkan cuplikan di halaman depan.', 'ptsbi-premium' )
                ),
            ],
            'kontak' => [
                'title'   => __( 'Kontak', 'ptsbi-premium' ),
                'excerpt' => __( 'Hubungi sekretariat organisasi.', 'ptsbi-premium' ),
                'content' => self::block_content(
                    __( 'Hubungi Kami', 'ptsbi-premium' ),
                    __( 'Silakan hubungi sekretariat untuk pendaftaran anggota, kerja sama program, atau pertanyaan umum.', 'ptsbi-premium' ) . "\n\n" .
                    __( 'Gunakan tombol WhatsApp di beranda atau isi formulir jika tema Anda menyediakannya. Jam layanan mengikuti pengaturan di tab Umum plugin.', 'ptsbi-premium' )
                ),
            ],
        ];
    }

    /**
     * @return array{created:int, updated:int, menu_id:int, page_ids:array<string,int>}
     */
    public static function create_standard_pages( bool $update_existing = false ): array {
        $result = [
            'created'  => 0,
            'updated'  => 0,
            'menu_id'  => 0,
            'page_ids' => [],
        ];

        foreach ( self::blueprints() as $slug => $bp ) {
            $existing = get_page_by_path( $slug, OBJECT, 'page' );
            if ( $existing instanceof WP_Post ) {
                $result['page_ids'][ $slug ] = (int) $existing->ID;
                if ( $update_existing ) {
                    wp_update_post(
                        [
                            'ID'           => $existing->ID,
                            'post_title'   => $bp['title'],
                            'post_excerpt' => $bp['excerpt'],
                            'post_content' => $bp['content'],
                        ]
                    );
                    $result['updated']++;
                }
                continue;
            }

            $id = wp_insert_post(
                [
                    'post_title'   => $bp['title'],
                    'post_name'    => $slug,
                    'post_status'  => 'publish',
                    'post_type'    => 'page',
                    'post_excerpt' => $bp['excerpt'],
                    'post_content' => $bp['content'],
                ],
                true
            );

            if ( is_wp_error( $id ) ) {
                continue;
            }
            $result['page_ids'][ $slug ] = (int) $id;
            $result['created']++;
        }

        $menu_id = self::sync_main_menu( $result['page_ids'] );
        $result['menu_id'] = $menu_id;

        return $result;
    }

    /**
     * @param array<string, int> $page_ids slug => post_id.
     */
    public static function sync_main_menu( array $page_ids ): int {
        $menu_name = __( 'Menu Utama Premium', 'ptsbi-premium' );
        $menu_id   = (int) get_option( self::MENU_OPTION, 0 );

        if ( $menu_id && ! is_nav_menu( $menu_id ) ) {
            $menu_id = 0;
        }

        if ( ! $menu_id ) {
            $menu_id = wp_create_nav_menu( $menu_name );
            if ( is_wp_error( $menu_id ) ) {
                return 0;
            }
            update_option( self::MENU_OPTION, (int) $menu_id, false );
        }

        $items = [
            [
                'title' => __( 'Beranda', 'ptsbi-premium' ),
                'url'   => home_url( '/' ),
            ],
            [
                'title' => __( 'Tentang', 'ptsbi-premium' ),
                'slug'  => 'tentang-kami',
            ],
            [
                'title' => __( 'Program', 'ptsbi-premium' ),
                'slug'  => 'program',
            ],
            [
                'title' => __( 'Kegiatan', 'ptsbi-premium' ),
                'slug'  => 'kegiatan',
            ],
            [
                'title' => __( 'Struktur', 'ptsbi-premium' ),
                'slug'  => 'struktur-organisasi',
            ],
            [
                'title' => __( 'Galeri', 'ptsbi-premium' ),
                'slug'  => 'galeri',
            ],
            [
                'title' => __( 'Kontak', 'ptsbi-premium' ),
                'slug'  => 'kontak',
            ],
        ];

        $existing_items = wp_get_nav_menu_items( $menu_id );
        $by_url         = [];
        if ( is_array( $existing_items ) ) {
            foreach ( $existing_items as $item ) {
                if ( is_object( $item ) && isset( $item->url ) ) {
                    $by_url[ $item->url ] = (int) $item->ID;
                }
            }
        }

        $position = 1;
        foreach ( $items as $row ) {
            if ( ! empty( $row['slug'] ) && empty( $page_ids[ $row['slug'] ] ) ) {
                continue;
            }
            $object_id = ! empty( $row['slug'] ) ? (int) $page_ids[ $row['slug'] ] : 0;
            $url       = ! empty( $row['url'] ) ? $row['url'] : get_permalink( $object_id );

            if ( isset( $by_url[ $url ] ) ) {
                $position++;
                continue;
            }

            if ( $object_id ) {
                wp_update_nav_menu_item(
                    $menu_id,
                    0,
                    [
                        'menu-item-title'     => $row['title'],
                        'menu-item-object'    => 'page',
                        'menu-item-object-id' => $object_id,
                        'menu-item-type'      => 'post_type',
                        'menu-item-status'    => 'publish',
                        'menu-item-position'  => $position,
                    ]
                );
            } else {
                wp_update_nav_menu_item(
                    $menu_id,
                    0,
                    [
                        'menu-item-title'  => $row['title'],
                        'menu-item-url'    => $url,
                        'menu-item-type'   => 'custom',
                        'menu-item-status' => 'publish',
                        'menu-item-position' => $position,
                    ]
                );
            }
            $position++;
        }

        $locations              = get_theme_mod( 'nav_menu_locations', [] );
        $locations['ptprm-primary'] = (int) $menu_id;
        if ( isset( $locations['primary'] ) || current_theme_supports( 'menus' ) ) {
            $locations['primary'] = (int) $menu_id;
        }
        set_theme_mod( 'nav_menu_locations', $locations );

        return (int) $menu_id;
    }

    /**
     * @return list<array{label:string,url:string,target:string,highlight:int,children:array}>
     */
    public static function default_menu_items_for_options(): array {
        return [
            [
                'label'     => __( 'Beranda', 'ptsbi-premium' ),
                'url'       => '/',
                'target'    => '_self',
                'highlight' => 0,
                'children'  => [],
            ],
            [
                'label'     => __( 'Tentang', 'ptsbi-premium' ),
                'url'       => '/tentang-kami/',
                'target'    => '_self',
                'highlight' => 0,
                'children'  => [],
            ],
            [
                'label'     => __( 'Program', 'ptsbi-premium' ),
                'url'       => '/program/',
                'target'    => '_self',
                'highlight' => 0,
                'children'  => [],
            ],
            [
                'label'     => __( 'Kegiatan', 'ptsbi-premium' ),
                'url'       => '/kegiatan/',
                'target'    => '_self',
                'highlight' => 0,
                'children'  => [],
            ],
            [
                'label'     => __( 'Galeri', 'ptsbi-premium' ),
                'url'       => '/galeri/',
                'target'    => '_self',
                'highlight' => 0,
                'children'  => [],
            ],
            [
                'label'     => __( 'Kontak', 'ptsbi-premium' ),
                'url'       => '/kontak/',
                'target'    => '_self',
                'highlight' => 0,
                'children'  => [],
            ],
            [
                'label'     => __( 'Rumah Anggota', 'ptsbi-premium' ),
                'url'       => '/rumah-anggota/',
                'target'    => '_self',
                'highlight' => 1,
                'children'  => [],
            ],
        ];
    }

    private static function block_content( string $heading, string $paragraphs ): string {
        $p_blocks = '';
        foreach ( preg_split( "/\n\n+/", trim( $paragraphs ) ) as $para ) {
            $para = trim( $para );
            if ( $para === '' ) {
                continue;
            }
            if ( strpos( $para, '<ul>' ) === 0 ) {
                $p_blocks .= $para . "\n\n";
            } else {
                $p_blocks .= '<!-- wp:paragraph --><p>' . wp_kses_post( $para ) . "</p><!-- /wp:paragraph -->\n\n";
            }
        }

        return '<!-- wp:heading {"level":1} --><h1 class="wp-block-heading">' . esc_html( $heading ) . "</h1><!-- /wp:heading -->\n\n" . $p_blocks;
    }
}
