<?php
/**
 * HTML output for Elementor widgets (shared markup with legacy JS inject).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PTSBI_PE_Render {

    public static function default_values_items() {
        $rows = [
            [ 'icon' => 'users', 'title' => 'Kebersamaan', 'desc' => 'Kami percaya kekuatan terletak pada ikatan yang erat antar anggota dan keluarga besar.' ],
            [ 'icon' => 'heart', 'title' => 'Kasih', 'desc' => 'Kasih menjadi dasar setiap tindakan, keputusan, dan hubungan dalam komunitas kami.' ],
            [ 'icon' => 'feather', 'title' => 'Pelestarian Budaya', 'desc' => 'Kami menjaga dan meneruskan nilai serta warisan budaya kepada generasi berikutnya.' ],
            [ 'icon' => 'hands', 'title' => 'Saling Mendukung', 'desc' => 'Dalam suka maupun duka, kami hadir untuk saling menopang dan menguatkan.' ],
        ];
        return self::repeater_defaults( $rows );
    }

    /**
     * Elementor repeater requires _id on each default row.
     *
     * @param array $rows
     * @return array
     */
    public static function repeater_defaults( $rows ) {
        $out = [];
        $i   = 1;
        foreach ( $rows as $row ) {
            $row['_id'] = 'item' . $i;
            $out[]      = $row;
            $i++;
        }
        return $out;
    }

    /**
     * @param array $settings Widget settings from Elementor.
     */
    public static function values( $settings ) {
        $eyebrow  = $settings['eyebrow'] ?? 'NILAI-NILAI KAMI';
        $title    = $settings['title'] ?? 'Fondasi yang Kami Pegang Bersama';
        $subtitle = $settings['subtitle'] ?? 'Empat nilai inti yang menuntun setiap langkah organisasi dalam menjaga kebersamaan keluarga besar.';
        $items    = ! empty( $settings['items'] ) ? $settings['items'] : self::default_values_items();
        $teaser   = $settings['teaser'] ?? __( 'Arahkan kursor untuk penjelasan', 'ptsbi-premium-enhancer' );

        echo '<section class="studio-values studio-elementor" data-studio-widget="values" data-studio-section="values">';
        echo '<span class="studio-eyebrow">' . esc_html( $eyebrow ) . '</span>';
        echo '<h2 class="studio-values-title">' . esc_html( $title ) . '</h2>';
        echo '<p class="studio-values-subtitle">' . esc_html( $subtitle ) . '</p>';
        echo '<div class="studio-values-grid">';

        foreach ( $items as $item ) {
            $icon  = isset( $item['icon'] ) ? $item['icon'] : 'users';
            $tit   = isset( $item['title'] ) ? $item['title'] : '';
            $desc  = isset( $item['desc'] ) ? $item['desc'] : '';
            if ( ! $tit ) {
                continue;
            }
            echo '<article class="studio-value-card" tabindex="0">';
            echo '<div class="studio-value-icon">' . PTSBI_PE_Icons::get( $icon ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo '</div>';
            echo '<h3>' . esc_html( $tit ) . '</h3>';
            echo '<p class="studio-value-teaser">' . esc_html( $teaser ) . '</p>';
            echo '<div class="studio-value-tooltip" role="tooltip"><p>' . esc_html( $desc ) . '</p></div>';
            echo '</article>';
        }

        echo '</div></section>';
    }

    public static function visit( $settings ) {
        $opts = ptsbi_pe_get_options();

        $eyebrow = $settings['eyebrow'] ?? 'KUNJUNGI KAMI';
        $title   = $settings['title'] ?? 'Mari Terhubung & Bertemu Bersama Keluarga Besar';
        $intro   = $settings['intro'] ?? 'Kami terbuka untuk silaturahmi, koordinasi kegiatan, dan masukan dari seluruh anggota.';
        $addr    = $settings['address'] ?? ( $opts['address'] ?? '' );
        $hours   = $settings['hours'] ?? 'Senin–Jumat, 09.00–17.00 WIB';
        $show_wa = ! empty( $settings['show_whatsapp'] );

        $wa_url = '';
        if ( $show_wa ) {
            $wa_url = ! empty( $settings['whatsapp_url'] ) ? $settings['whatsapp_url'] : '';
            if ( ! $wa_url && ! empty( $opts['cta2_url'] ) ) {
                $wa_url = $opts['cta2_url'];
            }
            if ( ! $wa_url && ! empty( $opts['whatsapp_number'] ) ) {
                $wa_url = ptsbi_pe_wa_link( $opts['whatsapp_number'] );
            }
        }

        echo '<section class="studio-visit studio-elementor" data-studio-widget="visit" data-studio-section="contact-info">';
        echo '<div class="studio-visit-inner">';
        echo '<div class="studio-visit-text">';
        echo '<span class="studio-eyebrow">' . esc_html( $eyebrow ) . '</span>';
        echo '<h2>' . esc_html( $title ) . '</h2>';
        echo '<p>' . esc_html( $intro ) . '</p>';
        echo '</div><div class="studio-visit-card">';

        if ( $addr ) {
            echo '<div class="studio-visit-line">' . PTSBI_PE_Icons::get( 'pin' ); // phpcs:ignore
            echo '<span><strong>' . esc_html__( 'Alamat:', 'ptsbi-premium-enhancer' ) . '</strong><br>' . esc_html( $addr ) . '</span></div>';
        }
        if ( $hours ) {
            echo '<div class="studio-visit-line">' . PTSBI_PE_Icons::get( 'clock' ); // phpcs:ignore
            echo '<span><strong>' . esc_html__( 'Jam Operasional:', 'ptsbi-premium-enhancer' ) . '</strong><br>' . esc_html( $hours ) . '</span></div>';
        }
        if ( $wa_url ) {
            echo '<div class="studio-visit-line">' . PTSBI_PE_Icons::get( 'whatsapp' ); // phpcs:ignore
            echo '<span><strong>WhatsApp:</strong><br><a href="' . esc_url( $wa_url ) . '" target="_blank" rel="noopener">' . esc_html__( 'Hubungi kami', 'ptsbi-premium-enhancer' ) . '</a></span></div>';
        }

        echo '</div></div></section>';
    }

    public static function footer( $settings ) {
        $opts   = ptsbi_pe_get_options();
        $home   = home_url( '/' );
        $year   = (int) gmdate( 'Y' );

        $brand    = $settings['brand_name'] ?? 'PTSBI';
        $tagline  = $settings['tagline'] ?? 'Wadah kebersamaan keluarga besar untuk mempererat persaudaraan, melestarikan budaya, dan saling mendukung.';
        $addr     = $settings['address'] ?? ( $opts['address'] ?? '' );
        $hours    = $settings['hours'] ?? 'Senin–Jumat, 09.00–17.00 WIB';
        $copy     = $settings['copyright'] ?? sprintf( '© %d %s. Seluruh hak cipta dilindungi.', $year, $brand );

        $use_global_social = ! isset( $settings['use_global_social'] ) || ! empty( $settings['use_global_social'] );
        $social = [
            'facebook'  => $use_global_social ? ( $opts['fb_url'] ?? '' ) : ( $settings['fb_url'] ?? '' ),
            'instagram' => $use_global_social ? ( $opts['ig_url'] ?? '' ) : ( $settings['ig_url'] ?? '' ),
            'youtube'   => $use_global_social ? ( $opts['yt_url'] ?? '' ) : ( $settings['yt_url'] ?? '' ),
            'tiktok'    => $use_global_social ? ( $opts['tt_url'] ?? '' ) : ( $settings['tt_url'] ?? '' ),
        ];

        $wa_url = '';
        if ( ! empty( $opts['cta2_url'] ) ) {
            $wa_url = $opts['cta2_url'];
        } elseif ( ! empty( $opts['whatsapp_number'] ) ) {
            $wa_url = ptsbi_pe_wa_link( $opts['whatsapp_number'] );
        }

        echo '<footer class="studio-footer studio-elementor" data-studio-widget="footer" role="contentinfo">';
        echo '<div class="studio-footer-grid">';

        echo '<div class="studio-footer-brand"><h4>' . esc_html( $brand ) . '</h4>';
        echo '<p>' . esc_html( $tagline ) . '</p>';
        echo '<div class="studio-footer-social">';
        foreach ( $social as $key => $url ) {
            if ( ! $url ) {
                continue;
            }
            $label = ucfirst( $key );
            echo '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener" aria-label="' . esc_attr( $label ) . '">';
            echo PTSBI_PE_Icons::get( $key ); // phpcs:ignore
            echo '</a>';
        }
        if ( $wa_url ) {
            echo '<a href="' . esc_url( $wa_url ) . '" target="_blank" rel="noopener" aria-label="WhatsApp">';
            echo PTSBI_PE_Icons::get( 'whatsapp' ); // phpcs:ignore
            echo '</a>';
        }
        echo '</div></div>';

        $links_a = $settings['links_quick'] ?? [];
        $links_b = $settings['links_org'] ?? [];

        echo '<div><h4>' . esc_html__( 'Tautan Cepat', 'ptsbi-premium-enhancer' ) . '</h4><ul>';
        if ( $links_a ) {
            foreach ( $links_a as $link ) {
                self::footer_link( $link );
            }
        } else {
            self::footer_link_preset( __( 'Beranda', 'ptsbi-premium-enhancer' ), $home );
            self::footer_link_preset( __( 'Tentang Kami', 'ptsbi-premium-enhancer' ), $home . 'tentang-kami/' );
            self::footer_link_preset( __( 'Program Kerja', 'ptsbi-premium-enhancer' ), $home . 'program-kerja/' );
            self::footer_link_preset( __( 'Informasi Kegiatan', 'ptsbi-premium-enhancer' ), $home . 'informasi-kegiatan/' );
        }
        echo '</ul></div>';

        echo '<div><h4>' . esc_html__( 'Organisasi', 'ptsbi-premium-enhancer' ) . '</h4><ul>';
        if ( $links_b ) {
            foreach ( $links_b as $link ) {
                self::footer_link( $link );
            }
        } else {
            self::footer_link_preset( 'Tarombo', $home . 'tarombo/' );
            self::footer_link_preset( __( 'Struktur Organisasi', 'ptsbi-premium-enhancer' ), $home . 'struktur-organisasi/' );
        }
        echo '</ul></div>';

        echo '<div><h4>' . esc_html__( 'Kontak', 'ptsbi-premium-enhancer' ) . '</h4>';
        if ( $addr ) {
            echo '<p>' . esc_html( $addr ) . '</p>';
        }
        if ( $hours ) {
            echo '<p>' . esc_html( $hours ) . '</p>';
        }
        echo '</div></div>';
        echo '<div class="studio-footer-bottom">' . esc_html( $copy ) . '</div>';
        echo '</footer>';
    }

    private static function footer_link( $link ) {
        $url = '';
        if ( isset( $link['url'] ) && is_array( $link['url'] ) && ! empty( $link['url']['url'] ) ) {
            $url = $link['url']['url'];
        } elseif ( isset( $link['url'] ) && is_string( $link['url'] ) ) {
            $url = $link['url'];
        }
        if ( ! $url || empty( $link['text'] ) ) {
            return;
        }
        $target = ( is_array( $link['url'] ) && ! empty( $link['url']['is_external'] ) ) ? ' target="_blank" rel="noopener"' : '';
        echo '<li><a href="' . esc_url( $url ) . '"' . $target . '>' . esc_html( $link['text'] ) . '</a></li>';
    }

    private static function footer_link_preset( $text, $url ) {
        echo '<li><a href="' . esc_url( $url ) . '">' . esc_html( $text ) . '</a></li>';
    }
}
