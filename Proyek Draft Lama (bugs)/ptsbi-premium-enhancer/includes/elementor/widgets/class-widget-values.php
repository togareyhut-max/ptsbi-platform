<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PTSBI_PE_Widget_Values extends \Elementor\Widget_Base {

    public function get_name() {
        return 'studio_values';
    }

    public function get_title() {
        return __( 'Nilai-Nilai Kami', 'ptsbi-premium-enhancer' );
    }

    public function get_icon() {
        return 'eicon-gallery-grid';
    }

    public function get_categories() {
        return [ 'section-studio' ];
    }

    public function get_keywords() {
        return [ 'section', 'studio', 'nilai', 'values', 'ptsbi' ];
    }

    public function get_style_depends() {
        return [ 'studio-frontend' ];
    }

    protected function register_controls() {
        $this->start_controls_section( 'content', [
            'label' => __( 'Konten', 'ptsbi-premium-enhancer' ),
        ] );

        $this->add_control( 'eyebrow', [
            'label'   => __( 'Label atas', 'ptsbi-premium-enhancer' ),
            'type'    => \Elementor\Controls_Manager::TEXT,
            'default' => 'NILAI-NILAI KAMI',
        ] );

        $this->add_control( 'title', [
            'label'   => __( 'Judul', 'ptsbi-premium-enhancer' ),
            'type'    => \Elementor\Controls_Manager::TEXT,
            'default' => 'Fondasi yang Kami Pegang Bersama',
        ] );

        $this->add_control( 'subtitle', [
            'label'   => __( 'Subjudul', 'ptsbi-premium-enhancer' ),
            'type'    => \Elementor\Controls_Manager::TEXTAREA,
            'default' => 'Empat nilai inti yang menuntun setiap langkah organisasi dalam menjaga kebersamaan keluarga besar.',
        ] );

        $this->add_control( 'teaser', [
            'label'   => __( 'Teks petunjuk hover', 'ptsbi-premium-enhancer' ),
            'type'    => \Elementor\Controls_Manager::TEXT,
            'default' => __( 'Arahkan kursor untuk penjelasan', 'ptsbi-premium-enhancer' ),
        ] );

        $repeater = new \Elementor\Repeater();

        $repeater->add_control( 'icon', [
            'label'   => __( 'Ikon', 'ptsbi-premium-enhancer' ),
            'type'    => \Elementor\Controls_Manager::SELECT,
            'default' => 'users',
            'options' => PTSBI_PE_Icons::icon_choices(),
        ] );

        $repeater->add_control( 'title', [
            'label'   => __( 'Judul nilai', 'ptsbi-premium-enhancer' ),
            'type'    => \Elementor\Controls_Manager::TEXT,
            'default' => '',
        ] );

        $repeater->add_control( 'desc', [
            'label'   => __( 'Penjelasan (tooltip)', 'ptsbi-premium-enhancer' ),
            'type'    => \Elementor\Controls_Manager::TEXTAREA,
            'default' => '',
        ] );

        $defaults = PTSBI_PE_Render::default_values_items();

        $this->add_control( 'items', [
            'label'       => __( 'Daftar nilai', 'ptsbi-premium-enhancer' ),
            'type'        => \Elementor\Controls_Manager::REPEATER,
            'fields'      => $repeater->get_controls(),
            'default'     => $defaults,
            'title_field' => '{{{ title }}}',
        ] );

        $this->end_controls_section();
    }

    protected function render() {
        PTSBI_PE_Render::values( $this->get_settings_for_display() );
    }
}
