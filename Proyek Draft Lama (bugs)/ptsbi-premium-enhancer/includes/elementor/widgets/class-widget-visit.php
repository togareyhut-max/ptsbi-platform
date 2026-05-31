<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PTSBI_PE_Widget_Visit extends \Elementor\Widget_Base {

    public function get_name() {
        return 'studio_visit';
    }

    public function get_title() {
        return __( 'Kunjungi Kami', 'ptsbi-premium-enhancer' );
    }

    public function get_icon() {
        return 'eicon-map-pin';
    }

    public function get_categories() {
        return [ 'section-studio' ];
    }

    public function get_keywords() {
        return [ 'section', 'studio', 'kunjungi', 'alamat', 'kontak' ];
    }

    public function get_style_depends() {
        return [ 'studio-frontend' ];
    }

    protected function register_controls() {
        $opts = ptsbi_pe_get_options();

        $this->start_controls_section( 'content', [
            'label' => __( 'Konten', 'ptsbi-premium-enhancer' ),
        ] );

        $this->add_control( 'eyebrow', [
            'label'   => __( 'Label atas', 'ptsbi-premium-enhancer' ),
            'type'    => \Elementor\Controls_Manager::TEXT,
            'default' => 'KUNJUNGI KAMI',
        ] );

        $this->add_control( 'title', [
            'label'   => __( 'Judul', 'ptsbi-premium-enhancer' ),
            'type'    => \Elementor\Controls_Manager::TEXT,
            'default' => 'Mari Terhubung & Bertemu Bersama Keluarga Besar',
        ] );

        $this->add_control( 'intro', [
            'label'   => __( 'Paragraf', 'ptsbi-premium-enhancer' ),
            'type'    => \Elementor\Controls_Manager::TEXTAREA,
            'default' => 'Kami terbuka untuk silaturahmi, koordinasi kegiatan, dan masukan dari seluruh anggota.',
        ] );

        $this->add_control( 'address', [
            'label'       => __( 'Alamat', 'ptsbi-premium-enhancer' ),
            'type'        => \Elementor\Controls_Manager::TEXTAREA,
            'default'     => $opts['address'] ?? '',
            'description' => __( 'Kosongkan untuk pakai alamat dari Section Studio → Global.', 'ptsbi-premium-enhancer' ),
        ] );

        $this->add_control( 'hours', [
            'label'   => __( 'Jam operasional', 'ptsbi-premium-enhancer' ),
            'type'    => \Elementor\Controls_Manager::TEXT,
            'default' => 'Senin–Jumat, 09.00–17.00 WIB',
        ] );

        $this->add_control( 'show_whatsapp', [
            'label'        => __( 'Tampilkan WhatsApp', 'ptsbi-premium-enhancer' ),
            'type'         => \Elementor\Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default'      => 'yes',
        ] );

        $this->add_control( 'whatsapp_url', [
            'label'       => __( 'URL WhatsApp (opsional)', 'ptsbi-premium-enhancer' ),
            'type'        => \Elementor\Controls_Manager::URL,
            'placeholder' => 'https://wa.me/62...',
            'condition'   => [ 'show_whatsapp' => 'yes' ],
        ] );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        if ( ! empty( $settings['whatsapp_url']['url'] ) ) {
            $settings['whatsapp_url'] = $settings['whatsapp_url']['url'];
        } else {
            $settings['whatsapp_url'] = '';
        }
        if ( empty( $settings['address'] ) ) {
            $settings['address'] = ptsbi_pe_get( 'address', '' );
        }
        PTSBI_PE_Render::visit( $settings );
    }
}
