<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PTSBI_PE_Widget_Footer extends \Elementor\Widget_Base {

    public function get_name() {
        return 'studio_footer';
    }

    public function get_title() {
        return __( 'Footer Premium', 'ptsbi-premium-enhancer' );
    }

    public function get_icon() {
        return 'eicon-footer';
    }

    public function get_categories() {
        return [ 'section-studio' ];
    }

    public function get_keywords() {
        return [ 'section', 'studio', 'footer' ];
    }

    public function get_style_depends() {
        return [ 'studio-frontend' ];
    }

    protected function register_controls() {
        $this->start_controls_section( 'content', [
            'label' => __( 'Konten', 'ptsbi-premium-enhancer' ),
        ] );

        $this->add_control( 'brand_name', [
            'label'   => __( 'Nama organisasi', 'ptsbi-premium-enhancer' ),
            'type'    => \Elementor\Controls_Manager::TEXT,
            'default' => 'PTSBI',
        ] );

        $this->add_control( 'tagline', [
            'label'   => __( 'Tagline', 'ptsbi-premium-enhancer' ),
            'type'    => \Elementor\Controls_Manager::TEXTAREA,
            'default' => 'Wadah kebersamaan keluarga besar untuk mempererat persaudaraan, melestarikan budaya, dan saling mendukung.',
        ] );

        $this->add_control( 'address', [
            'label'   => __( 'Alamat (kolom kontak)', 'ptsbi-premium-enhancer' ),
            'type'    => \Elementor\Controls_Manager::TEXTAREA,
            'default' => '',
        ] );

        $this->add_control( 'hours', [
            'label'   => __( 'Jam operasional', 'ptsbi-premium-enhancer' ),
            'type'    => \Elementor\Controls_Manager::TEXT,
            'default' => 'Senin–Jumat, 09.00–17.00 WIB',
        ] );

        $this->add_control( 'copyright', [
            'label'   => __( 'Baris copyright', 'ptsbi-premium-enhancer' ),
            'type'    => \Elementor\Controls_Manager::TEXT,
            'default' => '',
        ] );

        $this->add_control( 'use_global_social', [
            'label'        => __( 'Sosial media dari Section Studio → Global', 'ptsbi-premium-enhancer' ),
            'type'         => \Elementor\Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default'      => 'yes',
        ] );

        $this->end_controls_section();

        $this->start_controls_section( 'links', [
            'label' => __( 'Tautan', 'ptsbi-premium-enhancer' ),
        ] );

        $rep = new \Elementor\Repeater();
        $rep->add_control( 'text', [
            'label' => __( 'Label', 'ptsbi-premium-enhancer' ),
            'type'  => \Elementor\Controls_Manager::TEXT,
        ] );
        $rep->add_control( 'url', [
            'label' => __( 'URL', 'ptsbi-premium-enhancer' ),
            'type'  => \Elementor\Controls_Manager::URL,
        ] );

        $this->add_control( 'links_quick', [
            'label'       => __( 'Tautan cepat', 'ptsbi-premium-enhancer' ),
            'type'        => \Elementor\Controls_Manager::REPEATER,
            'fields'      => $rep->get_controls(),
            'default'     => [],
            'title_field' => '{{{ text }}}',
        ] );

        $this->add_control( 'links_org', [
            'label'       => __( 'Organisasi', 'ptsbi-premium-enhancer' ),
            'type'        => \Elementor\Controls_Manager::REPEATER,
            'fields'      => $rep->get_controls(),
            'default'     => [],
            'title_field' => '{{{ text }}}',
        ] );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        if ( empty( $settings['address'] ) ) {
            $settings['address'] = ptsbi_pe_get( 'address', '' );
        }
        PTSBI_PE_Render::footer( $settings );
    }
}
