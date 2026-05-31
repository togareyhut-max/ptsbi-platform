<?php
/**
 * Render all home sections + sub-page hero.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PTPRM_Renderer {

    /* ===================================================================== */
    /* HOMEPAGE                                                              */
    /* ===================================================================== */

    public static function home() {
        $o = ptprm_options();

        echo '<div class="ptprm ptprm-home">';
        if ( ! empty( $o['hero_show'] ) ) {
            self::hero( $o );
        }

        $order = ptprm_get_home_sections_order( $o );
        foreach ( $order as $slug ) {
            switch ( $slug ) {
                case 'about':
                    if ( ! empty( $o['about_show'] ) ) self::about( $o );
                    break;
                case 'values':
                    if ( ! empty( $o['values_show'] ) ) self::values( $o );
                    break;
                case 'team':
                    if ( ! empty( $o['team_show'] ) ) {
                        self::team( $o );
                    }
                    break;
                case 'activities':
                    if ( ! empty( $o['activities_show'] ) ) self::activities( $o );
                    break;
                case 'gallery':
                    if ( ! empty( $o['gallery_show'] ) ) self::gallery( $o );
                    break;
                case 'stats':
                    if ( ! empty( $o['stats_show'] ) ) self::stats( $o );
                    break;
                case 'tarombo_digital':
                    if ( ! empty( $o['tarombo_digital_show'] ) ) {
                        self::tarombo_digital( $o );
                    }
                    break;
                case 'visit':
                    if ( class_exists( 'PTPRM_Members' ) && PTPRM_Members::registration_enabled() ) {
                        PTPRM_Members::render_home_registration_section();
                    }
                    if ( ! empty( $o['visit_show'] ) ) {
                        self::visit( $o );
                    }
                    break;
                default:
                    break;
            }
        }
        echo '</div>';
    }

    /* -------- HERO -------- */

    public static function hero( $o ) {
        $slides      = ptprm_get_hero_slides( $o );
        $slide_count = count( $slides );
        $img_d       = ptprm_image_url( $o['hero_img_desktop'] );
        $img_t       = ptprm_image_url( $o['hero_img_tablet'] );
        $img_m       = ptprm_image_url( $o['hero_img_mobile'] );
        $slideshow   = $slide_count > 1 && ! empty( $o['hero_slideshow'] );
        $img_pos     = ptprm_sanitize_hero_img_position( $o['hero_img_position'] ?? '' );
        $height_mode = ptprm_sanitize_hero_height_mode( $o['hero_height_mode'] ?? 'standard' );

        $align_h = in_array( $o['hero_text_align_h'], [ 'left', 'center', 'right' ], true ) ? $o['hero_text_align_h'] : 'left';
        $align_v = in_array( $o['hero_text_align_v'], [ 'top', 'middle', 'bottom' ], true ) ? $o['hero_text_align_v'] : 'middle';

        $classes = [
            'ptprm-hero',
            'ptprm-hero-align-h-' . $align_h,
            'ptprm-hero-align-v-' . $align_v,
            'ptprm-hero-height-' . $height_mode,
        ];
        if ( ! empty( $o['hero_animation'] ) && $o['hero_animation'] !== 'none' ) {
            $classes[] = 'ptprm-anim-' . $o['hero_animation'];
        }
        if ( $slide_count === 0 && ! $img_d && ! $img_t && ! $img_m ) {
            $classes[] = 'ptprm-hero-noimg';
        }
        if ( $slideshow ) {
            $classes[] = 'ptprm-hero-slideshow';
        }

        $interval = max( 2500, min( 15000, (int) ( $o['hero_slideshow_interval'] ?? 5000 ) ) );
        $pause    = ! empty( $o['hero_slideshow_pause'] ) ? '1' : '0';
        $dots     = ! empty( $o['hero_slideshow_dots'] ) ? '1' : '0';

        echo '<section class="' . esc_attr( implode( ' ', $classes ) ) . '" data-ptprm-hero style="--ptprm-hero-img-pos: ' . esc_attr( $img_pos ) . ';"';
        if ( $slideshow ) {
            echo ' data-ptprm-hero-slideshow data-interval="' . esc_attr( (string) $interval ) . '" data-pause="' . esc_attr( $pause ) . '" data-dots="' . esc_attr( $dots ) . '"';
        }
        echo '>';

        echo '<div class="ptprm-hero-media" aria-hidden="true">';
        if ( $slideshow ) {
            echo '<div class="ptprm-hero-slides">';
            foreach ( $slides as $idx => $url ) {
                $active = $idx === 0 ? ' is-active' : '';
                echo '<div class="ptprm-hero-slide' . esc_attr( $active ) . '">';
                echo '<img src="' . esc_url( $url ) . '" alt="" loading="' . ( $idx === 0 ? 'eager' : 'lazy' ) . '"';
                echo $idx === 0 ? ' fetchpriority="high"' : '';
                echo ' decoding="async" style="object-position:' . $img_pos . '">';
                echo '</div>';
            }
            echo '</div>';
        } elseif ( $slide_count === 1 ) {
            echo '<img class="ptprm-hero-single-img" src="' . esc_url( $slides[0] ) . '" alt="" loading="eager" fetchpriority="high" decoding="async" style="object-position:' . $img_pos . '">';
        } elseif ( $img_d || $img_t || $img_m ) {
            echo '<picture>';
            if ( $img_m ) {
                echo '<source media="(max-width: 600px)" srcset="' . esc_url( $img_m ) . '">';
            }
            if ( $img_t ) {
                echo '<source media="(max-width: 1023px)" srcset="' . esc_url( $img_t ) . '">';
            }
            $main = $img_d ?: ( $img_t ?: $img_m );
            echo '<img src="' . esc_url( $main ) . '" alt="" loading="eager" fetchpriority="high" decoding="async" style="object-position:' . esc_attr( $img_pos ) . '">';
            echo '</picture>';
        }
        echo '<div class="ptprm-hero-overlay"></div>';
        if ( $slideshow && $dots === '1' ) {
            echo '<div class="ptprm-hero-dots" role="tablist" aria-label="' . esc_attr__( 'Banner hero', 'ptsbi-premium' ) . '">';
            foreach ( $slides as $idx => $slide_url ) {
                $dot_active = $idx === 0 ? ' is-active' : '';
                echo '<button type="button" class="ptprm-hero-dot' . esc_attr( $dot_active ) . '" role="tab" aria-selected="' . ( $idx === 0 ? 'true' : 'false' ) . '" aria-label="' . esc_attr( sprintf( __( 'Banner %d', 'ptsbi-premium' ), $idx + 1 ) ) . '" data-ptprm-hero-dot="' . (int) $idx . '"></button>';
            }
            echo '</div>';
        }
        echo '</div>';

        $cta1_url = trim( (string) ( $o['cta1_url'] ?? '' ) );
        if ( $cta1_url === '' || ( class_exists( 'PTPRM_Members' ) && PTPRM_Members::registration_enabled() ) ) {
            $cta1_url = '#daftar-anggota';
        }
        $cta2_url = $o['cta2_url'];
        if ( ! $cta2_url && $o['whatsapp'] ) {
            $cta2_url = ptprm_wa_link( $o['whatsapp'] );
        }

        echo '<div class="ptprm-hero-inner"><div class="ptprm-hero-text">';

        if ( ! empty( $o['hero_eyebrow_show'] ) && $o['hero_eyebrow_text'] !== '' ) {
            echo '<span class="ptprm-hero-eyebrow">' . esc_html( $o['hero_eyebrow_text'] ) . '</span>';
        }
        if ( ! empty( $o['hero_title_show'] ) && $o['hero_title_text'] !== '' ) {
            echo '<h1 class="ptprm-hero-title">' . wp_kses_post( $o['hero_title_text'] ) . '</h1>';
        }
        if ( ! empty( $o['hero_sub_show'] ) && $o['hero_sub_text'] !== '' ) {
            echo '<p class="ptprm-hero-sub">' . wp_kses_post( nl2br( $o['hero_sub_text'] ) ) . '</p>';
        }

        if ( ! empty( $o['cta1_show'] ) || ! empty( $o['cta2_show'] ) ) {
            echo '<div class="ptprm-cta-row">';
            if ( ! empty( $o['cta1_show'] ) && $o['cta1_label'] !== '' ) {
                self::render_cta( 1, $o['cta1_label'], $cta1_url, $o['cta1_target'], $o['cta1_icon'], $o['cta1_style'], $o['cta1_size'] );
            }
            if ( ! empty( $o['cta2_show'] ) && $o['cta2_label'] !== '' ) {
                if ( ( $o['cta2_mode'] ?? 'link' ) === 'inquiry' ) {
                    self::render_inquiry_cta( 2, $o );
                } else {
                    self::render_cta( 2, $o['cta2_label'], $cta2_url, $o['cta2_target'], $o['cta2_icon'], $o['cta2_style'], $o['cta2_size'] );
                }
            }
            echo '</div>';
        }

        echo '</div></div></section>';
    }

    private static function render_cta( $i, $label, $url, $target, $icon, $style, $size ) {
        $url = $url ? ptprm_resolve_url( $url ) : '#';
        $rel = ( $target === '_blank' ) ? ' rel="noopener noreferrer"' : '';
        $cls = 'ptprm-cta ptprm-cta-' . $i . ' ptprm-cta-' . $style . ' ptprm-cta-size-' . $size;

        echo '<a class="' . esc_attr( $cls ) . '" href="' . esc_url( $url ) . '" target="' . esc_attr( $target ) . '"' . $rel . '>';
        $svg = ptprm_icon_svg( $icon );
        if ( $svg ) {
            echo '<span class="ptprm-cta-icon">' . $svg . '</span>'; // phpcs:ignore
        }
        echo '<span class="ptprm-cta-label">' . esc_html( $label ) . '</span>';
        echo '</a>';
    }

    private static function render_inquiry_cta( $i, $o ) {
        $cls = 'ptprm-cta ptprm-cta-' . $i . ' ptprm-cta-' . $o[ 'cta' . $i . '_style' ] . ' ptprm-cta-size-' . $o[ 'cta' . $i . '_size' ];
        echo '<button type="button" class="' . esc_attr( $cls ) . '" data-ptprm-inquiry-cta aria-haspopup="dialog">';
        $svg = ptprm_icon_svg( $o[ 'cta' . $i . '_icon' ] );
        if ( $svg ) {
            echo '<span class="ptprm-cta-icon">' . $svg . '</span>'; // phpcs:ignore
        }
        echo '<span class="ptprm-cta-label">' . esc_html( $o[ 'cta' . $i . '_label' ] ) . '</span>';
        echo '</button>';
    }

    /* -------- ABOUT -------- */

    public static function about( $o ) {
        $img = ptprm_image_url( $o['about_image'] );
        $layout = $o['about_layout'] === 'image-right' ? 'image-right' : ( $o['about_layout'] === 'text-only' ? 'text-only' : 'image-left' );

        echo '<section class="ptprm-section ptprm-about ptprm-about-' . esc_attr( $layout ) . '">';
        echo '<div class="ptprm-container ptprm-about-inner">';

        if ( $layout !== 'text-only' ) {
            echo '<div class="ptprm-about-media">';
            if ( $img ) {
                echo '<img src="' . esc_url( $img ) . '" alt="" loading="lazy" decoding="async">';
            } else {
                echo '<div class="ptprm-about-placeholder ptprm-media-placeholder" aria-hidden="true">' . ptprm_icon_svg( 'users' ) . '</div>'; // phpcs:ignore
            }
            echo '</div>';
        }

        echo '<div class="ptprm-about-text">';
        if ( $o['about_eyebrow'] !== '' ) echo '<span class="ptprm-eyebrow">' . esc_html( $o['about_eyebrow'] ) . '</span>';
        if ( $o['about_title'] !== '' )   echo '<h2 class="ptprm-h2">' . esc_html( $o['about_title'] ) . '</h2>';
        if ( $o['about_body'] !== '' ) {
            echo '<div class="ptprm-prose">' . wpautop( wp_kses_post( $o['about_body'] ) ) . '</div>';
        }
        if ( $o['about_cta_label'] !== '' && $o['about_cta_url'] !== '' ) {
            echo '<a class="ptprm-link-arrow" href="' . esc_url( ptprm_resolve_url( $o['about_cta_url'] ) ) . '">';
            echo esc_html( $o['about_cta_label'] ) . ' ' . ptprm_icon_svg( 'arrow' ); // phpcs:ignore
            echo '</a>';
        }
        echo '</div>';

        echo '</div></section>';
    }

    /* -------- VALUES -------- */

    public static function values( $o ) {
        $items = ptprm_get_values_items( $o );
        if ( ! $items ) {
            return;
        }
        $count = count( $items );
        echo '<section class="ptprm-section ptprm-values">';
        echo '<div class="ptprm-container ptprm-text-center">';
        if ( $o['values_eyebrow'] !== '' ) {
            echo '<span class="ptprm-eyebrow">' . esc_html( $o['values_eyebrow'] ) . '</span>';
        }
        if ( $o['values_title'] !== '' ) {
            echo '<h2 class="ptprm-h2">' . esc_html( $o['values_title'] ) . '</h2>';
        }
        if ( $o['values_subtitle'] !== '' ) {
            echo '<p class="ptprm-lead">' . esc_html( $o['values_subtitle'] ) . '</p>';
        }

        echo '<div class="ptprm-values-grid" style="--ptprm-values-count:' . (int) $count . ';">';
        $hint = trim( (string) ( $o['values_hover_hint'] ?? '' ) );
        if ( $hint === '' ) {
            $hint = __( 'Arahkan kursor untuk penjelasan', 'ptsbi-premium' );
        }
        foreach ( $items as $item ) {
            $icon  = $item['icon'] ?? 'users';
            $title = $item['title'] ?? '';
            $desc  = trim( (string) ( $item['desc'] ?? '' ) );
            $card_cls = 'ptprm-value-card' . ( $desc !== '' ? ' ptprm-value-card--has-desc' : '' );
            echo '<article class="' . esc_attr( $card_cls ) . '" tabindex="0">';
            echo '<div class="ptprm-value-front" aria-hidden="' . ( $desc !== '' ? 'false' : 'false' ) . '">';
            echo '<div class="ptprm-value-icon">' . ptprm_icon_svg( $icon ) . '</div>'; // phpcs:ignore
            echo '<h3>' . esc_html( $title ) . '</h3>';
            echo '<p class="ptprm-value-teaser">' . esc_html( $hint ) . '</p>';
            echo '</div>';
            if ( $desc !== '' ) {
                echo '<div class="ptprm-value-back ptprm-value-tooltip" role="note" aria-hidden="true">';
                echo '<div class="ptprm-value-tooltip__inner">';
                echo '<p class="ptprm-value-desc">' . nl2br( esc_html( $desc ) ) . '</p>';
                echo '</div>';
                echo '</div>';
            }
            echo '</article>';
        }
        echo '</div></div></section>';
    }

    /* -------- ACTIVITIES (posts) -------- */

    public static function activities( $o ) {
        $count    = max( 1, (int) $o['activities_count'] );
        $cat      = (int) $o['activities_category'];

        $args = [
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => $count,
            'ignore_sticky_posts' => true,
        ];
        if ( $cat > 0 ) $args['cat'] = $cat;

        $q = new WP_Query( $args );

        echo '<section class="ptprm-section ptprm-activities">';
        echo '<div class="ptprm-container ptprm-text-center">';
        if ( $o['activities_eyebrow'] !== '' )  echo '<span class="ptprm-eyebrow">' . esc_html( $o['activities_eyebrow'] ) . '</span>';
        if ( $o['activities_title'] !== '' )    echo '<h2 class="ptprm-h2">' . esc_html( $o['activities_title'] ) . '</h2>';
        if ( $o['activities_subtitle'] !== '' ) echo '<p class="ptprm-lead">' . esc_html( $o['activities_subtitle'] ) . '</p>';

        if ( $q->have_posts() ) {
            echo '<div class="ptprm-activities-grid">';
            while ( $q->have_posts() ) {
                $q->the_post();
                $thumb = get_the_post_thumbnail_url( null, 'large' );
                echo '<article class="ptprm-activity-card">';
                echo '<a href="' . esc_url( get_permalink() ) . '" class="ptprm-activity-thumb">';
                if ( $thumb ) {
                    echo '<img src="' . esc_url( $thumb ) . '" alt="" loading="lazy" decoding="async">';
                } else {
                    echo '<div class="ptprm-activity-placeholder ptprm-media-placeholder">' . ptprm_icon_svg( 'feather' ) . '</div>'; // phpcs:ignore
                }
                echo '</a>';
                echo '<div class="ptprm-activity-body">';
                echo '<time class="ptprm-activity-date">' . esc_html( get_the_date() ) . '</time>';
                echo '<h3 class="ptprm-activity-title"><a href="' . esc_url( get_permalink() ) . '">' . esc_html( get_the_title() ) . '</a></h3>';
                echo '<p class="ptprm-activity-excerpt">' . esc_html( wp_trim_words( wp_strip_all_tags( get_the_excerpt() ), 22 ) ) . '</p>';
                echo '<a class="ptprm-link-arrow" href="' . esc_url( get_permalink() ) . '">Selengkapnya ' . ptprm_icon_svg( 'arrow' ) . '</a>'; // phpcs:ignore
                echo '</div></article>';
            }
            echo '</div>';
            wp_reset_postdata();
        } else {
            echo '<p class="ptprm-empty">Belum ada kegiatan yang dipublikasikan.</p>';
        }

        if ( $o['activities_more_label'] !== '' && $o['activities_more_url'] !== '' ) {
            echo '<div class="ptprm-section-cta">';
            echo '<a class="ptprm-cta ptprm-cta-outline ptprm-cta-size-medium" href="' . esc_url( ptprm_resolve_url( $o['activities_more_url'] ) ) . '">';
            echo '<span class="ptprm-cta-label">' . esc_html( $o['activities_more_label'] ) . '</span>';
            echo ptprm_icon_svg( 'arrow' ); // phpcs:ignore
            echo '</a></div>';
        }

        echo '</div></section>';
    }

    /* -------- STATS -------- */

    public static function stats( $o ) {
        echo '<section class="ptprm-section ptprm-stats">';
        echo '<div class="ptprm-container">';
        echo '<div class="ptprm-text-center">';
        if ( $o['stats_eyebrow'] !== '' ) echo '<span class="ptprm-eyebrow ptprm-eyebrow-on-dark">' . esc_html( $o['stats_eyebrow'] ) . '</span>';
        if ( $o['stats_title'] !== '' )   echo '<h2 class="ptprm-h2 ptprm-on-dark">' . esc_html( $o['stats_title'] ) . '</h2>';
        echo '</div>';
        echo '<div class="ptprm-stats-grid"' . ( ! empty( $o['stats_animate'] ) ? ' data-ptprm-animate' : '' ) . '>';
        foreach ( [ 'a', 'b', 'c', 'd' ] as $k ) {
            $num   = $o[ "stats_{$k}_num" ] ?? '';
            $label = $o[ "stats_{$k}_label" ] ?? '';
            if ( $num === '' && $label === '' ) continue;
            echo '<div class="ptprm-stat">';
            echo '<div class="ptprm-stat-num" data-ptprm-counter>' . esc_html( $num ) . '</div>';
            echo '<div class="ptprm-stat-label">' . esc_html( $label ) . '</div>';
            echo '</div>';
        }
        echo '</div></div></section>';
    }

    /* -------- VISIT -------- */

    public static function visit( $o ) {
        $wa = '';
        if ( ! empty( $o['visit_show_wa'] ) ) {
            $wa = ptprm_wa_link( $o['whatsapp'] );
        }

        echo '<section class="ptprm-section ptprm-visit">';
        echo '<div class="ptprm-container">';

        echo '<div class="ptprm-visit-head">';
        if ( $o['visit_eyebrow'] !== '' ) echo '<span class="ptprm-eyebrow">' . esc_html( $o['visit_eyebrow'] ) . '</span>';
        if ( $o['visit_title'] !== '' )   echo '<h2 class="ptprm-h2">' . esc_html( $o['visit_title'] ) . '</h2>';
        if ( $o['visit_intro'] !== '' )   echo '<p class="ptprm-lead">' . esc_html( $o['visit_intro'] ) . '</p>';
        echo '</div>';

        echo '<div class="ptprm-visit-grid">';

        // Address card (left)
        echo '<div class="ptprm-visit-card">';
        $has_line = false;
        if ( ! empty( $o['visit_show_address'] ) ) {
            if ( $o['address'] !== '' ) {
                echo '<div class="ptprm-visit-line">' . ptprm_icon_svg( 'pin' ); // phpcs:ignore
                echo '<span><strong>Alamat:</strong><br>' . esc_html( $o['address'] ) . '</span></div>';
                $has_line = true;
            } else {
                echo '<div class="ptprm-visit-line">' . ptprm_icon_svg( 'pin' ); // phpcs:ignore
                echo '<span><strong>Alamat:</strong><br><span class="ptprm-visit-empty-msg">Isi alamat di Premium Plugin → Umum → Kontak &amp; Alamat.</span></span></div>';
                $has_line = true;
            }
        }
        if ( ! empty( $o['visit_show_hours'] ) && $o['hours'] !== '' ) {
            echo '<div class="ptprm-visit-line">' . ptprm_icon_svg( 'clock' ); // phpcs:ignore
            echo '<span><strong>Jam Operasional:</strong><br>' . esc_html( $o['hours'] ) . '</span></div>';
            $has_line = true;
        }
        if ( $wa ) {
            echo '<div class="ptprm-visit-line">' . ptprm_icon_svg( 'whatsapp' ); // phpcs:ignore
            echo '<span><strong>WhatsApp:</strong><br><a href="' . esc_url( $wa ) . '" target="_blank" rel="noopener">Hubungi kami</a></span></div>';
            $has_line = true;
        }
        if ( ! $has_line ) {
            echo '<p class="ptprm-visit-empty-msg">Aktifkan informasi (alamat / jam / WA) di Premium Plugin → Beranda → Kunjungi Kami.</p>';
        }
        echo '</div>';

        // Map box (right) — otomatis dari alamat tab Umum, atau URL embed manual.
        $map_markup = ptprm_visit_map_markup( $o );
        $maps_link  = ptprm_visit_map_external_url( $o );
        echo '<div class="ptprm-visit-map">';
        if ( $map_markup !== '' ) {
            echo $map_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitized in helper
        } else {
            echo '<div class="ptprm-visit-map-empty ptprm-media-placeholder">';
            echo ptprm_icon_svg( 'pin' ); // phpcs:ignore
            echo '<p>';
            esc_html_e( 'Isi alamat di tab Umum, atau tempel kode embed Google Maps di panel Peta di bawah.', 'ptsbi-premium' );
            if ( $maps_link !== '' ) {
                echo ' <a href="' . esc_url( $maps_link ) . '" target="_blank" rel="noopener noreferrer">';
                esc_html_e( 'Buka di Google Maps', 'ptsbi-premium' );
                echo '</a>';
            }
            echo '</p></div>';
        }
        echo '</div>';

        echo '</div>'; // visit-grid
        echo '</div></section>';
    }

    /* -------- TEAM -------- */

    public static function team( $o ) {
        $items  = ptprm_get_team_items( $o );
        $items  = array_slice( $items, 0, 5 );
        $cols   = max( 2, min( 5, (int) ( $o['team_columns'] ?: 5 ) ) );
        $layout = ( $o['team_layout'] ?? 'grid' ) === 'grouped' ? 'grouped' : 'grid';
        $compact = ( $o['team_photo_style'] ?? 'compact' ) !== 'card';

        echo '<section class="ptprm-section ptprm-team' . ( $compact ? ' ptprm-team-compact' : '' ) . '">';
        echo '<div class="ptprm-container ptprm-text-center">';
        if ( $o['team_eyebrow'] !== '' ) {
            echo '<span class="ptprm-eyebrow">' . esc_html( $o['team_eyebrow'] ) . '</span>';
        }
        if ( $o['team_title'] !== '' ) {
            echo '<h2 class="ptprm-h2">' . esc_html( $o['team_title'] ) . '</h2>';
        }
        if ( $o['team_subtitle'] !== '' ) {
            echo '<p class="ptprm-lead">' . esc_html( $o['team_subtitle'] ) . '</p>';
        }

        if ( ! $items ) {
            echo '<p class="ptprm-empty">Belum ada data pengurus. Isi di Premium Plugin → Section Tambahan → Pengurus.</p>';
        } elseif ( $layout === 'grouped' && ! $compact ) {
            $groups = [];
            foreach ( $items as $member ) {
                $g = trim( (string) ( $member['group'] ?? '' ) );
                $g = $g !== '' ? $g : __( 'Pengurus', 'ptsbi-premium' );
                $groups[ $g ][] = $member;
            }
            echo '<div class="ptprm-team-grouped">';
            foreach ( $groups as $label => $members ) {
                echo '<div class="ptprm-team-group">';
                echo '<h3 class="ptprm-team-group-title">' . esc_html( $label ) . '</h3>';
                echo '<div class="ptprm-team-grid" style="--ptprm-team-cols:' . (int) $cols . ';">';
                foreach ( $members as $member ) {
                    self::team_card( $member, false );
                }
                echo '</div></div>';
            }
            echo '</div>';
        } else {
            echo '<div class="ptprm-team-grid' . ( $compact ? ' ptprm-team-grid-compact' : '' ) . '" style="--ptprm-team-cols:' . (int) $cols . ';">';
            foreach ( $items as $member ) {
                self::team_card( $member, $compact );
            }
            echo '</div>';
        }

        if ( ! empty( $o['wilayah_show'] ) ) {
            self::team_wilayah_box( $o );
        }

        if ( $o['team_more_label'] !== '' && $o['team_more_url'] !== '' ) {
            echo '<div class="ptprm-section-cta">';
            echo '<a class="ptprm-cta ptprm-cta-outline ptprm-cta-size-medium" href="' . esc_url( ptprm_resolve_url( $o['team_more_url'] ) ) . '">';
            echo '<span class="ptprm-cta-label">' . esc_html( $o['team_more_label'] ) . '</span>';
            echo ptprm_icon_svg( 'arrow' ); // phpcs:ignore
            echo '</a></div>';
        }

        echo '</div></section>';
    }

    /**
     * @param array{image?:string,name?:string,role?:string,url?:string,group?:string} $member
     */
    private static function team_card( array $member, bool $compact = false ): void {
        $name = $member['name'] ?? '';
        $img  = ptprm_image_url( $member['image'] ?? '' );
        $role = $member['role'] ?? '';
        $url  = $member['url'] ?? '';

        echo '<article class="ptprm-team-card' . ( $compact ? ' ptprm-team-card-compact' : '' ) . '">';
        if ( $compact ) {
            echo '<div class="ptprm-team-compact-inner">';
            echo '<div class="ptprm-team-photo ptprm-team-photo-compact">';
            if ( $img ) {
                echo '<img src="' . esc_url( $img ) . '" alt="' . esc_attr( $name ) . '" loading="lazy" decoding="async">';
            } else {
                echo '<div class="ptprm-team-placeholder ptprm-media-placeholder" aria-hidden="true">' . ptprm_icon_svg( 'users' ) . '</div>'; // phpcs:ignore
            }
            echo '</div>';
            echo '<div class="ptprm-team-body ptprm-team-body-compact">';
            echo '<h3 class="ptprm-team-name">' . esc_html( $name ) . '</h3>';
            if ( $role !== '' ) {
                echo '<p class="ptprm-team-role">' . esc_html( $role ) . '</p>';
            }
            echo '</div></div>';
            echo '</article>';
            return;
        }

        echo '<div class="ptprm-team-photo">';
        if ( $img ) {
            echo '<img src="' . esc_url( $img ) . '" alt="' . esc_attr( $name ) . '" loading="lazy" decoding="async">';
        } else {
            echo '<div class="ptprm-team-placeholder ptprm-media-placeholder" aria-hidden="true">' . ptprm_icon_svg( 'users' ) . '</div>'; // phpcs:ignore
        }
        echo '</div>';
        echo '<div class="ptprm-team-body">';
        echo '<h3 class="ptprm-team-name">' . esc_html( $name ) . '</h3>';
        if ( $role !== '' ) {
            echo '<p class="ptprm-team-role">' . esc_html( $role ) . '</p>';
        }
        if ( $url !== '' ) {
            echo '<a class="ptprm-team-link" href="' . esc_url( ptprm_resolve_url( $url ) ) . '" target="_blank" rel="noopener">';
            echo esc_html__( 'Profil', 'ptsbi-premium' ) . ' ' . ptprm_icon_svg( 'arrow' ); // phpcs:ignore
            echo '</a>';
        }
        echo '</div></article>';
    }

    /**
     * @param array<string,mixed> $o
     */
    private static function team_wilayah_box( array $o ): void {
        $items = ptprm_get_wilayah_items( $o );
        if ( ! $items ) {
            return;
        }
        $title = trim( (string) ( $o['wilayah_title'] ?? '' ) );
        $intro = trim( (string) ( $o['wilayah_intro'] ?? '' ) );

        echo '<div class="ptprm-wilayah-box">';
        if ( $title !== '' ) {
            echo '<h3 class="ptprm-wilayah-title">' . esc_html( $title ) . '</h3>';
        }
        if ( $intro !== '' ) {
            echo '<p class="ptprm-wilayah-intro">' . esc_html( $intro ) . '</p>';
        }
        echo '<ul class="ptprm-wilayah-links">';
        foreach ( $items as $item ) {
            $label = (string) ( $item['label'] ?? '' );
            $url   = (string) ( $item['url'] ?? '' );
            if ( $label === '' ) {
                continue;
            }
            echo '<li>';
            if ( $url !== '' ) {
                echo '<a class="ptprm-wilayah-link" href="' . esc_url( ptprm_resolve_url( $url ) ) . '">' . esc_html( $label ) . '</a>';
            } else {
                echo '<span class="ptprm-wilayah-link ptprm-wilayah-link-static">' . esc_html( $label ) . '</span>';
            }
            echo '</li>';
        }
        echo '</ul></div>';
    }

    /* -------- GALLERY -------- */

    public static function gallery( $o ) {
        $ids  = array_filter( array_map( 'intval', explode( ',', (string) $o['gallery_ids'] ) ) );
        $cols = max( 2, min( 6, (int) ( $o['gallery_columns'] ?: 4 ) ) );
        $mode = in_array( $o['gallery_layout'] ?? 'grid', [ 'grid', 'slider', 'marquee' ], true ) ? $o['gallery_layout'] : 'grid';

        echo '<section class="ptprm-section ptprm-gallery ptprm-gallery-mode-' . esc_attr( $mode ) . '">';
        echo '<div class="ptprm-container ptprm-text-center">';
        if ( $o['gallery_eyebrow'] !== '' )  echo '<span class="ptprm-eyebrow">' . esc_html( $o['gallery_eyebrow'] ) . '</span>';
        if ( $o['gallery_title'] !== '' )    echo '<h2 class="ptprm-h2">' . esc_html( $o['gallery_title'] ) . '</h2>';
        if ( $o['gallery_subtitle'] !== '' ) echo '<p class="ptprm-lead">' . esc_html( $o['gallery_subtitle'] ) . '</p>';

        if ( ! $ids ) {
            echo '<p class="ptprm-empty">Belum ada foto. Tambahkan di Premium Plugin → Section Tambahan → Galeri Foto.</p>';
            echo '</div></section>';
            return;
        }

        $lightbox = ! empty( $o['gallery_lightbox'] );

        if ( $mode === 'grid' ) {
            echo '<div class="ptprm-gallery-grid" style="--ptprm-gallery-cols:' . (int) $cols . ';">';
            foreach ( $ids as $id ) {
                self::gallery_item( $id, $lightbox );
            }
            echo '</div>';
        } elseif ( $mode === 'slider' ) {
            $pause = ! empty( $o['gallery_pause'] ) ? '1' : '0';
            $arrows = ! empty( $o['gallery_arrows'] );
            $dots   = ! empty( $o['gallery_dots'] );
            echo '<div class="ptprm-gallery-slider" data-ptprm-slider data-per-view="' . (int) $cols . '" data-autoplay="' . (int) $o['gallery_autoplay'] . '" data-pause="' . esc_attr( $pause ) . '">';
            echo '<div class="ptprm-slider-track">';
            foreach ( $ids as $id ) {
                self::gallery_item( $id, $lightbox, 'ptprm-slider-slide' );
            }
            echo '</div>';
            if ( $arrows ) {
                echo '<button type="button" class="ptprm-slider-nav prev" aria-label="Sebelumnya">&#8592;</button>';
                echo '<button type="button" class="ptprm-slider-nav next" aria-label="Berikutnya">&#8594;</button>';
            }
            if ( $dots ) {
                echo '<div class="ptprm-slider-dots" aria-hidden="true"></div>';
            }
            echo '</div>';
        } else { // marquee
            $pause = ! empty( $o['gallery_pause'] );
            $marquee_cols = max( 3, $cols );
            echo '<div class="ptprm-gallery-marquee' . ( $pause ? ' has-pause' : '' ) . '" style="--ptprm-gallery-cols:' . (int) $marquee_cols . ';">';
            echo '<div class="ptprm-marquee-track">';
            // Duplicate slides to enable seamless looping
            foreach ( [ 1, 2 ] as $pass ) {
                foreach ( $ids as $id ) {
                    self::gallery_item( $id, $lightbox, 'ptprm-marquee-item' );
                }
            }
            echo '</div></div>';
        }

        echo '</div></section>';
    }

    private static function gallery_item( $id, $lightbox = true, $extra_class = '' ) {
        $thumb = wp_get_attachment_image_url( $id, 'large' );
        $full  = wp_get_attachment_image_url( $id, 'full' );
        if ( ! $thumb ) return;
        $alt   = get_post_meta( $id, '_wp_attachment_image_alt', true );
        $class = 'ptprm-gallery-item' . ( $extra_class ? ' ' . $extra_class : '' );

        if ( $lightbox ) {
            echo '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $full ) . '" data-ptprm-lightbox>';
        } else {
            echo '<div class="' . esc_attr( $class ) . '">';
        }
        echo '<img src="' . esc_url( $thumb ) . '" alt="' . esc_attr( $alt ) . '" loading="lazy" decoding="async">';
        echo $lightbox ? '</a>' : '</div>';
    }

    /* -------- POP-UP IKLAN -------- */

    public static function popup( $o ) {
        if ( empty( $o['popup_enabled'] ) ) return;

        $slides = [];
        foreach ( [
            [ 'popup_image',   'popup_link_1' ],
            [ 'popup_image_2', 'popup_link_2' ],
            [ 'popup_image_3', 'popup_link_3' ],
        ] as $pair ) {
            $u = ptprm_image_url( $o[ $pair[0] ] ?? '' );
            if ( $u !== '' ) {
                $slides[] = [
                    'img'  => $u,
                    'link' => trim( (string) ( $o[ $pair[1] ] ?? '' ) ),
                ];
            }
        }

        $orientation = ( $o['popup_orientation'] === 'landscape' ) ? 'landscape' : 'portrait';
        $interval    = max( 1500, (int) ( $o['popup_slide_interval'] ?? 4500 ) );
        $text_blob   = (string) ( $o['popup_eyebrow'] ?? '' ) . (string) ( $o['popup_title'] ?? '' ) . (string) ( $o['popup_body'] ?? '' );
        $text_len    = strlen( preg_replace( '/\s+/', '', wp_strip_all_tags( $text_blob ) ) );
        $density     = 'ptprm-popup-density-short';
        if ( $text_len > 280 ) {
            $density = 'ptprm-popup-density-long';
        } elseif ( $text_len > 120 ) {
            $density = 'ptprm-popup-density-medium';
        }

        echo '<div class="ptprm-popup ptprm-popup-' . esc_attr( $orientation ) . ' ' . esc_attr( $density ) . '" data-ptprm-popup data-delay="' . (int) $o['popup_delay'] . '" data-frequency="' . esc_attr( $o['popup_frequency'] ) . '" data-interval="' . (int) $interval . '" hidden>';
        echo '<div class="ptprm-popup-overlay" data-ptprm-popup-close></div>';
        echo '<div class="ptprm-popup-card ' . esc_attr( $density ) . '" role="dialog" aria-modal="true" aria-labelledby="ptprm-popup-title">';

        echo '<button type="button" class="ptprm-popup-x" aria-label="Tutup" data-ptprm-popup-close>&times;</button>';

        if ( $slides ) {
            $multi = count( $slides ) > 1;
            echo '<div class="ptprm-popup-media' . ( $multi ? ' has-slides' : '' ) . '" data-ptprm-popup-slides>';
            foreach ( $slides as $i => $s ) {
                $tag_open  = '<div class="ptprm-popup-slide' . ( $i === 0 ? ' is-active' : '' ) . '">';
                $tag_close = '</div>';
                if ( $s['link'] !== '' ) {
                    $tag_open = '<a class="ptprm-popup-slide' . ( $i === 0 ? ' is-active' : '' ) . '" href="' . esc_url( ptprm_resolve_url( $s['link'] ) ) . '" target="_blank" rel="noopener">';
                    $tag_close = '</a>';
                }
                echo $tag_open;
                echo '<img src="' . esc_url( $s['img'] ) . '" alt="" loading="lazy" decoding="async">';
                echo $tag_close;
            }

            if ( $multi ) {
                echo '<div class="ptprm-popup-dots" aria-hidden="true">';
                foreach ( $slides as $i => $s ) {
                    echo '<button type="button" class="ptprm-popup-dot' . ( $i === 0 ? ' is-active' : '' ) . '" data-ptprm-popup-goto="' . (int) $i . '" aria-label="Foto ' . ( $i + 1 ) . '"></button>';
                }
                echo '</div>';
            }
            echo '</div>';
        }

        echo '<div class="ptprm-popup-body">';
        if ( $o['popup_eyebrow'] !== '' ) echo '<span class="ptprm-eyebrow">' . esc_html( $o['popup_eyebrow'] ) . '</span>';
        if ( $o['popup_title']   !== '' ) echo '<h3 class="ptprm-popup-title" id="ptprm-popup-title">' . esc_html( $o['popup_title'] ) . '</h3>';
        if ( $o['popup_body']    !== '' ) echo '<p class="ptprm-popup-text">' . nl2br( esc_html( $o['popup_body'] ) ) . '</p>';

        echo '<div class="ptprm-popup-actions">';
        if ( $o['popup_cta_label'] !== '' ) {
            $url    = $o['popup_cta_url'] ? ptprm_resolve_url( $o['popup_cta_url'] ) : '#';
            $target = ( $o['popup_cta_target'] === '_blank' ) ? '_blank' : '_self';
            $rel    = ( $target === '_blank' ) ? ' rel="noopener"' : '';
            echo '<a class="ptprm-cta ptprm-cta-1 ptprm-cta-solid ptprm-cta-size-medium" href="' . esc_url( $url ) . '" target="' . esc_attr( $target ) . '"' . $rel . '>';
            echo '<span class="ptprm-cta-label">' . esc_html( $o['popup_cta_label'] ) . '</span>';
            echo ptprm_icon_svg( 'arrow' ); // phpcs:ignore
            echo '</a>';
        }
        if ( ! empty( $o['popup_show_close'] ) && $o['popup_close_text'] !== '' ) {
            echo '<button type="button" class="ptprm-popup-decline" data-ptprm-popup-close>' . esc_html( $o['popup_close_text'] ) . '</button>';
        }
        echo '</div>';

        echo '</div>'; // body
        echo '</div>'; // card
        echo '</div>'; // popup
    }

    /* -------- BANNER CTA -------- */

    public static function banner( $o ) {
        $img = ptprm_image_url( $o['banner_image'] );
        $overlay = max( 0, min( 100, (int) $o['banner_overlay'] ) ) / 100;

        echo '<section class="ptprm-section ptprm-banner-cta"' . ( $img ? ' style="background-image:url(' . esc_url( $img ) . ');"' : '' ) . '>';
        echo '<div class="ptprm-banner-overlay" aria-hidden="true" style="opacity:' . esc_attr( $overlay ) . '"></div>';
        echo '<div class="ptprm-container ptprm-banner-inner">';
        if ( $o['banner_eyebrow'] !== '' )  echo '<span class="ptprm-eyebrow ptprm-eyebrow-on-dark">' . esc_html( $o['banner_eyebrow'] ) . '</span>';
        if ( $o['banner_title'] !== '' )    echo '<h2 class="ptprm-h2 ptprm-on-dark">' . esc_html( $o['banner_title'] ) . '</h2>';
        if ( $o['banner_subtitle'] !== '' ) echo '<p class="ptprm-lead ptprm-on-dark">' . esc_html( $o['banner_subtitle'] ) . '</p>';

        echo '<div class="ptprm-cta-row" style="justify-content:center;">';
        if ( $o['banner_cta_label'] !== '' ) {
            $url = $o['banner_cta_url'] ? ptprm_resolve_url( $o['banner_cta_url'] ) : '#';
            echo '<a class="ptprm-cta ptprm-cta-1 ptprm-cta-solid ptprm-cta-size-large" href="' . esc_url( $url ) . '">';
            echo '<span class="ptprm-cta-label">' . esc_html( $o['banner_cta_label'] ) . '</span>';
            echo ptprm_icon_svg( 'arrow' ); // phpcs:ignore
            echo '</a>';
        }
        if ( ! empty( $o['banner_cta2_show'] ) && $o['banner_cta2_label'] !== '' ) {
            $url2 = $o['banner_cta2_url'] ? ptprm_resolve_url( $o['banner_cta2_url'] ) : ( $o['whatsapp'] ? ptprm_wa_link( $o['whatsapp'] ) : '#' );
            echo '<a class="ptprm-cta ptprm-cta-2 ptprm-cta-ghost ptprm-cta-size-large" href="' . esc_url( $url2 ) . '" target="_blank" rel="noopener">';
            echo ptprm_icon_svg( 'whatsapp' ); // phpcs:ignore
            echo '<span class="ptprm-cta-label">' . esc_html( $o['banner_cta2_label'] ) . '</span>';
            echo '</a>';
        }
        echo '</div>';
        echo '</div></section>';
    }

    /* -------- TAROMBO DIGITAL (Preview iframe) -------- */
    public static function tarombo_digital( $o ): void {
        $preview_url = trim( (string) ( $o['tarombo_digital_preview_url'] ?? '' ) );
        if ( $preview_url === '' ) {
            $preview_url = 'https://tarombo.ptsbi.org';
        }

        $height = (int) ( $o['tarombo_digital_iframe_height'] ?? 560 );
        $height = max( 240, min( 900, $height ) );

        $eyebrow  = (string) ( $o['tarombo_digital_eyebrow'] ?? 'Fitur Unggulan' );
        $title    = (string) ( $o['tarombo_digital_title'] ?? 'Kebanggaan Keluarga Besar Kami' );
        $subtitle = (string) ( $o['tarombo_digital_subtitle'] ?? 'Tarombo Digital' );

        $tab_name = trim( (string) ( $o['tarombo_digital_new_tab_name'] ?? '' ) );
        if ( $tab_name === '' ) {
            $tab_name = __( 'Tarombo', 'ptsbi-premium' );
        }

        echo '<section class="ptprm-section ptprm-tarombo-digital">';
        echo '<div class="ptprm-container ptprm-tarombo-digital-inner">';
        echo '<div class="ptprm-text-center">';

        if ( $eyebrow !== '' ) {
            echo '<span class="ptprm-eyebrow ptprm-eyebrow-on-dark">' . esc_html( $eyebrow ) . '</span>';
        }
        if ( $title !== '' ) {
            echo '<h2 class="ptprm-h2 ptprm-on-dark">' . esc_html( $title ) . '</h2>';
        }
        if ( $subtitle !== '' ) {
            echo '<p class="ptprm-lead ptprm-on-dark" style="margin-bottom:24px;">' . esc_html( $subtitle ) . '</p>';
        }

        echo '</div>';

        echo '<div class="ptprm-tarombo-preview" role="region" aria-label="Preview Tarombo Digital">';
        echo '<iframe'
            . ' src="' . esc_url( $preview_url ) . '"'
            . ' loading="lazy"'
            . ' referrerpolicy="no-referrer-when-downgrade"'
            . ' title="Tarombo Digital Preview"'
            . ' style="width:100%;border:0;display:block;height:' . esc_attr( (string) $height ) . 'px;"'
            . '></iframe>';
        echo '</div>';

        echo '<div class="ptprm-text-center ptprm-tarombo-actions">';
        echo '<a class="ptprm-link-arrow ptprm-link-arrow-on-dark" href="' . esc_url( $preview_url ) . '" target="_blank" rel="noopener noreferrer">';
        printf(
            /* translators: %s: destination name in the link, e.g. Tarombo. */
            esc_html__( 'Buka di %s Tab baru', 'ptsbi-premium' ),
            esc_html( $tab_name )
        );
        echo ' ' . ptprm_icon_svg( 'arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '</a>';
        echo '</div>';

        echo '</div></section>';
    }

    /* -------- FAQ -------- */

    public static function faq( $o ) {
        echo '<section class="ptprm-section ptprm-faq">';
        echo '<div class="ptprm-container">';
        echo '<div class="ptprm-text-center" style="max-width:760px;margin:0 auto 40px;">';
        if ( $o['faq_eyebrow'] !== '' )  echo '<span class="ptprm-eyebrow">' . esc_html( $o['faq_eyebrow'] ) . '</span>';
        if ( $o['faq_title'] !== '' )    echo '<h2 class="ptprm-h2">' . esc_html( $o['faq_title'] ) . '</h2>';
        if ( $o['faq_subtitle'] !== '' ) echo '<p class="ptprm-lead">' . esc_html( $o['faq_subtitle'] ) . '</p>';
        echo '</div>';

        echo '<div class="ptprm-faq-list">';
        $count = 0;
        for ( $i = 1; $i <= 10; $i++ ) {
            $q = $o[ "faq_{$i}_q" ] ?? '';
            $a = $o[ "faq_{$i}_a" ] ?? '';
            if ( $q === '' ) continue;
            $count++;
            echo '<details class="ptprm-faq-item"' . ( $count === 1 ? ' open' : '' ) . '>';
            echo '<summary class="ptprm-faq-q">';
            echo '<span>' . esc_html( $q ) . '</span>';
            echo '<span class="ptprm-faq-icon" aria-hidden="true">+</span>';
            echo '</summary>';
            echo '<div class="ptprm-faq-a">' . wpautop( wp_kses_post( $a ) ) . '</div>';
            echo '</details>';
        }
        if ( $count === 0 ) {
            echo '<p class="ptprm-empty">Belum ada pertanyaan. Isi di Premium Plugin → Beranda → FAQ.</p>';
        }
        echo '</div>';

        echo '</div></section>';
    }

    /* ===================================================================== */
    /* FOOTER                                                                */
    /* ===================================================================== */

    public static function footer() {
        $o = ptprm_options();
        if ( empty( $o['footer_show'] ) ) return;

        $year = (int) gmdate( 'Y' );
        $brand_name = ptprm_footer_brand_name();
        $brand_desc = ptprm_footer_brand_description( $o );

        $copy = str_replace(
            [ '{year}', '{org}' ],
            [ $year, $brand_name ],
            (string) $o['footer_copyright']
        );

        echo '<footer class="ptprm-footer" role="contentinfo">';
        echo '<div class="ptprm-container ptprm-footer-grid">';

        echo '<div class="ptprm-footer-brand">';
        echo '<h4>' . esc_html( $brand_name ) . '</h4>';
        if ( $brand_desc !== '' ) {
            echo '<p>' . esc_html( $brand_desc ) . '</p>';
        }
        if ( ! empty( $o['footer_show_social'] ) ) {
            echo '<div class="ptprm-footer-social">';
            $networks = [
                'facebook'  => $o['fb_url'],
                'instagram' => $o['ig_url'],
                'youtube'   => $o['yt_url'],
                'tiktok'    => $o['tt_url'],
            ];
            foreach ( $networks as $name => $url ) {
                if ( ! $url ) continue;
                echo '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener" aria-label="' . esc_attr( ucfirst( $name ) ) . '">';
                echo ptprm_icon_svg( $name ); // phpcs:ignore
                echo '</a>';
            }
            if ( $o['whatsapp'] ) {
                echo '<a href="' . esc_url( ptprm_wa_link( $o['whatsapp'] ) ) . '" target="_blank" rel="noopener" aria-label="WhatsApp">';
                echo ptprm_icon_svg( 'whatsapp' ); // phpcs:ignore
                echo '</a>';
            }
            echo '</div>';
        }
        echo '</div>';

        self::footer_column( $o['footer_col1_label'], $o['footer_col1_links'] );
        self::footer_column( $o['footer_col2_label'], $o['footer_col2_links'] );

        echo '<div class="ptprm-footer-contact"><h4>Kontak</h4>';
        if ( $o['address'] !== '' ) echo '<p>' . esc_html( $o['address'] ) . '</p>';
        if ( $o['hours'] !== '' )   echo '<p>' . esc_html( $o['hours'] ) . '</p>';
        echo '</div>';

        echo '</div>';
        echo '<div class="ptprm-footer-bottom">' . esc_html( $copy ) . '</div>';
        echo '</footer>';
    }

    private static function footer_column( $label, $links_text ) {
        $links = ptprm_parse_links( $links_text );
        if ( ! $links && $label === '' ) return;

        echo '<div class="ptprm-footer-col">';
        if ( $label !== '' ) echo '<h4>' . esc_html( $label ) . '</h4>';
        if ( $links ) {
            echo '<ul>';
            foreach ( $links as $l ) {
                $url = $l['url'];
                if ( $url && $url[0] === '/' ) {
                    $url = home_url( $url );
                }
                echo '<li><a href="' . esc_url( $url ) . '">' . esc_html( $l['label'] ) . '</a></li>';
            }
            echo '</ul>';
        }
        echo '</div>';
    }

    /* ===================================================================== */
    /* SUB-PAGE HERO                                                         */
    /* ===================================================================== */

    public static function subpage_hero( $args = [] ) {
        $o = ptprm_options();
        if ( apply_filters( 'ptprm_subpage_hero_skip', false ) ) {
            return;
        }
        if ( empty( $o['subpage_hero_show'] ) ) return;

        $defaults = [
            'eyebrow' => '',
            'title'   => '',
            'intro'   => '',
            'image'   => '',
        ];
        $a = wp_parse_args( $args, $defaults );

        if ( $a['title'] === '' ) {
            $a['title'] = (string) get_the_title();
        }
        if ( $a['eyebrow'] === '' && ! empty( $o['subpage_eyebrow_auto'] ) ) {
            $a['eyebrow'] = mb_strtoupper( $a['title'] );
        }

        $img = ptprm_image_url( $a['image'] );
        if ( ! $img && has_post_thumbnail() ) {
            $img = get_the_post_thumbnail_url( null, 'full' );
        }

        $classes = [ 'ptprm-subhero' ];
        $classes[] = $img ? 'ptprm-subhero-photo' : 'ptprm-subhero-pattern';
        if ( ! empty( $o['subpage_gorga'] ) && ptprm_pattern_enabled( $o ) ) {
            $classes[] = 'ptprm-subhero-has-pattern';
        }

        echo '<section class="' . esc_attr( implode( ' ', $classes ) ) . '"' . ( $img ? ' style="background-image:url(' . esc_url( $img ) . ')"' : '' ) . '>';
        echo '<div class="ptprm-subhero-overlay" aria-hidden="true"></div>';
        echo '<div class="ptprm-container ptprm-subhero-inner">';

        if ( ! empty( $o['subpage_breadcrumb'] ) ) {
            echo '<nav class="ptprm-breadcrumb" aria-label="Breadcrumb">';
            echo '<a href="' . esc_url( home_url( '/' ) ) . '">Beranda</a><span class="sep">/</span>';
            echo '<span>' . esc_html( $a['title'] ) . '</span></nav>';
        }
        if ( $a['eyebrow'] !== '' ) {
            echo '<span class="ptprm-subhero-eyebrow">' . esc_html( $a['eyebrow'] ) . '</span>';
        }
        echo '<h1 class="ptprm-subhero-title">' . esc_html( $a['title'] ) . '</h1>';

        $intro = $a['intro'] !== '' ? $a['intro'] : (string) $o['subpage_default_intro'];
        if ( $intro !== '' ) {
            echo '<p class="ptprm-subhero-sub">' . esc_html( $intro ) . '</p>';
        }
        if ( ! empty( $o['subpage_ornament'] ) ) {
            echo '<div class="ptprm-subhero-ornament" aria-hidden="true">' . ptprm_ornament_svg() . '</div>'; // phpcs:ignore
        }

        echo '</div></section>';
    }
}
