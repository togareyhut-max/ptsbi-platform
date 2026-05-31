<?php
/**
 * Elementor integration: category + draggable widgets (safe load).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PTSBI_PE_Elementor {

    /** @var bool */
    private static $widgets_registered = false;

    public function __construct() {
        add_action( 'elementor/loaded', [ $this, 'init' ] );
        add_action( 'admin_notices', [ $this, 'missing_elementor_notice' ] );
    }

    public function init() {
        if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
            return;
        }

        require_once PTSBI_PE_DIR . 'includes/elementor/class-icons.php';
        require_once PTSBI_PE_DIR . 'includes/elementor/class-render.php';
        require_once PTSBI_PE_DIR . 'includes/elementor/widgets/class-widget-values.php';
        require_once PTSBI_PE_DIR . 'includes/elementor/widgets/class-widget-visit.php';
        require_once PTSBI_PE_DIR . 'includes/elementor/widgets/class-widget-footer.php';

        add_action( 'elementor/elements/categories_registered', [ $this, 'register_category' ] );

        // Elementor 3.5+
        add_action( 'elementor/widgets/register', [ $this, 'register_widgets' ] );
        // Elementor 3.0–3.4
        add_action( 'elementor/widgets/widgets_registered', [ $this, 'register_widgets' ] );

        add_action( 'elementor/editor/after_enqueue_styles', [ $this, 'enqueue_editor_styles' ] );
        add_action( 'elementor/frontend/after_enqueue_styles', [ $this, 'enqueue_editor_styles' ] );
    }

    public function enqueue_editor_styles() {
        if ( wp_style_is( 'studio-frontend', 'registered' ) && ! wp_style_is( 'studio-frontend', 'enqueued' ) ) {
            wp_enqueue_style( 'studio-frontend' );
        }
    }

    public function missing_elementor_notice() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        if ( defined( 'ELEMENTOR_VERSION' ) ) {
            return;
        }
        if ( ptsbi_pe_layout_mode() !== 'elementor' ) {
            return;
        }
        echo '<div class="notice notice-warning"><p>';
        echo esc_html__( 'Section Studio: mode Elementor aktif, tetapi plugin Elementor belum terpasang. Pasang Elementor agar widget bisa drag & drop.', 'ptsbi-premium-enhancer' );
        echo '</p></div>';
    }

    /**
     * @param \Elementor\Elements_Manager $elements_manager
     */
    public function register_category( $elements_manager ) {
        $elements_manager->add_category( 'section-studio', [
            'title' => __( 'Section Studio', 'ptsbi-premium-enhancer' ),
            'icon'  => 'fa fa-plug',
        ] );
    }

    /**
     * @param \Elementor\Widgets_Manager $widgets_manager
     */
    public function register_widgets( $widgets_manager ) {
        if ( self::$widgets_registered ) {
            return;
        }

        $list = [
            'PTSBI_PE_Widget_Values',
            'PTSBI_PE_Widget_Visit',
            'PTSBI_PE_Widget_Footer',
        ];

        foreach ( $list as $class ) {
            if ( ! class_exists( $class ) ) {
                continue;
            }
            $this->register_one( $widgets_manager, new $class() );
        }

        self::$widgets_registered = true;
    }

    /**
     * @param \Elementor\Widgets_Manager $widgets_manager
     * @param \Elementor\Widget_Base     $widget
     */
    private function register_one( $widgets_manager, $widget ) {
        if ( method_exists( $widgets_manager, 'register' ) ) {
            $widgets_manager->register( $widget );
        } elseif ( method_exists( $widgets_manager, 'register_widget_type' ) ) {
            $widgets_manager->register_widget_type( $widget );
        }
    }
}
