<?php
/** Widget Elementor « Menu du jour — Carrousel ». */

if ( ! defined( 'ABSPATH' ) ) exit;

use Elementor\Controls_Manager;
use Elementor\Widget_Base;

class SL_Menu_Du_Jour_Carousel_Widget extends Widget_Base {

    public function get_name()       { return 'sl_menu_du_jour_carousel'; }
    public function get_title()      { return __( 'Menu du jour — Carrousel', 'sl-agences' ); }
    public function get_icon()       { return 'eicon-food-menu'; }
    public function get_categories() { return [ 'santa-lucia' ]; }
    public function get_keywords()   { return [ 'fast food', 'menu', 'repas', 'jour', 'agence', 'carousel' ]; }

    private function agency_options() {
        $options = [ '' => __( 'Automatique (première agence disponible)', 'sl-agences' ) ];
        foreach ( sl_mdt_get_agencies() as $agency ) {
            $options[ $agency->slug ] = sl_mdt_agency_name( $agency->slug ) ?: $agency->name;
        }
        return $options;
    }

    protected function register_controls() {
        $this->start_controls_section( 'content_section', [
            'label' => __( '🍽️ Menu du jour', 'sl-agences' ),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ] );

        $this->add_control( 'title', [
            'label'   => __( 'Titre', 'sl-agences' ),
            'type'    => Controls_Manager::TEXT,
            'default' => __( 'Le menu du jour', 'sl-agences' ),
        ] );
        $this->add_control( 'subtitle', [
            'label'   => __( 'Sous-titre', 'sl-agences' ),
            'type'    => Controls_Manager::TEXT,
            'default' => __( 'Choisissez votre agence et commandez vos plats préférés.', 'sl-agences' ),
        ] );
        $this->add_control( 'default_agency', [
            'label'       => __( 'Agence au chargement', 'sl-agences' ),
            'type'        => Controls_Manager::SELECT,
            'options'     => $this->agency_options(),
            'default'     => '',
            'description' => __( 'Automatique choisit une agence ayant un menu disponible aujourd’hui.', 'sl-agences' ),
        ] );
        $this->add_control( 'limit', [
            'label'   => __( 'Nombre maximal de repas', 'sl-agences' ),
            'type'    => Controls_Manager::NUMBER,
            'min'     => 2,
            'max'     => 24,
            'default' => 12,
        ] );
        $this->add_control( 'show_order_button', [
            'label'        => __( 'Afficher « Commander »', 'sl-agences' ),
            'type'         => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default'      => 'yes',
        ] );
        $this->end_controls_section();

        $this->start_controls_section( 'carousel_section', [
            'label' => __( '⚙️ Carrousel', 'sl-agences' ),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ] );
        $this->add_responsive_control( 'columns', [
            'label'          => __( 'Cartes visibles', 'sl-agences' ),
            'type'           => Controls_Manager::NUMBER,
            'min'            => 1,
            'max'            => 6,
            'step'           => 0.1,
            'default'        => 4,
            'tablet_default' => 2,
            'mobile_default' => 1.15,
        ] );
        $this->add_control( 'gap', [
            'label'   => __( 'Espacement entre les cartes (px)', 'sl-agences' ),
            'type'    => Controls_Manager::NUMBER,
            'min'     => 0,
            'max'     => 40,
            'default' => 18,
        ] );
        $this->add_control( 'show_arrows', [
            'label'        => __( 'Afficher les flèches', 'sl-agences' ),
            'type'         => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default'      => 'yes',
        ] );
        $this->add_control( 'autoplay', [
            'label'        => __( 'Défilement automatique', 'sl-agences' ),
            'type'         => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default'      => 'yes',
            'description'  => __( 'Fait défiler les repas automatiquement. La lecture est mise en pause pendant une interaction.', 'sl-agences' ),
        ] );
        $this->add_control( 'autoplay_delay', [
            'label'      => __( 'Délai entre les défilements (secondes)', 'sl-agences' ),
            'type'       => Controls_Manager::NUMBER,
            'min'        => 2,
            'max'        => 15,
            'step'       => 1,
            'default'    => 5,
            'condition'  => [ 'autoplay' => 'yes' ],
        ] );
        $this->end_controls_section();

        $this->start_controls_section( 'style_section', [
            'label' => __( '🎨 Style', 'sl-agences' ),
            'tab'   => Controls_Manager::TAB_STYLE,
        ] );
        $this->add_control( 'accent', [
            'label'   => __( 'Couleur accent', 'sl-agences' ),
            'type'    => Controls_Manager::COLOR,
            'default' => '#e91e63',
        ] );
        $this->add_control( 'surface', [
            'label'   => __( 'Fond de la section', 'sl-agences' ),
            'type'    => Controls_Manager::COLOR,
            'default' => '#fff7f9',
        ] );
        $this->add_control( 'text_color', [
            'label'   => __( 'Couleur du texte', 'sl-agences' ),
            'type'    => Controls_Manager::COLOR,
            'default' => '#202126',
        ] );
        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $agencies = sl_mdt_get_agencies();
        $is_edit  = class_exists( '\\Elementor\\Plugin' ) && \Elementor\Plugin::$instance->editor->is_edit_mode();

        if ( empty( $agencies ) || ! post_type_exists( 'sl_repas' ) ) {
            if ( $is_edit ) {
                echo '<div class="sl-mdt-editor-notice">Le module Fast Food ou ses agences ne sont pas encore disponibles.</div>';
            }
            return;
        }

        $configured_agency = sanitize_title( (string) ( $settings['default_agency'] ?? '' ) );
        $agency = sl_mdt_get_agency( $configured_agency )
            ? $configured_agency
            : sl_mdt_find_first_available_agency( $agencies );
        if ( ! $agency ) return;

        $limit       = max( 2, min( 24, (int) ( $settings['limit'] ?? 12 ) ) );
        $show_order  = ( $settings['show_order_button'] ?? 'yes' ) === 'yes';
        $show_arrows = ( $settings['show_arrows'] ?? 'yes' ) === 'yes';
        $autoplay    = ( $settings['autoplay'] ?? 'yes' ) === 'yes';
        $autoplay_delay = max( 2, min( 15, (int) ( $settings['autoplay_delay'] ?? 5 ) ) ) * 1000;
        $payload     = sl_mdt_menu_payload( $agency, $limit, $show_order );
        $agency_name = sl_mdt_agency_name( $agency );
        $uid         = 'sl-mdt-' . $this->get_id();

        $columns_desktop = max( 1, min( 6, (float) ( $settings['columns'] ?? 4 ) ) );
        $columns_tablet  = max( 1, min( 6, (float) ( ( $settings['columns_tablet'] ?? '' ) !== '' ? $settings['columns_tablet'] : 2 ) ) );
        $columns_mobile  = max( 1, min( 3, (float) ( ( $settings['columns_mobile'] ?? '' ) !== '' ? $settings['columns_mobile'] : 1.15 ) ) );
        $gap             = max( 0, min( 40, (int) ( $settings['gap'] ?? 18 ) ) );
        $accent          = sanitize_hex_color( $settings['accent'] ?? '' ) ?: '#e91e63';
        $surface         = sanitize_hex_color( $settings['surface'] ?? '' ) ?: '#fff7f9';
        $text_color      = sanitize_hex_color( $settings['text_color'] ?? '' ) ?: '#202126';
        $date            = date_i18n( 'l d F', current_time( 'timestamp' ) );
        $style           = sprintf(
            '--sl-mdt-accent:%1$s;--sl-mdt-surface:%2$s;--sl-mdt-text:%3$s;--sl-mdt-cols:%4$s;--sl-mdt-cols-tablet:%5$s;--sl-mdt-cols-mobile:%6$s;--sl-mdt-gap:%7$dpx;',
            $accent,
            $surface,
            $text_color,
            $columns_desktop,
            $columns_tablet,
            $columns_mobile,
            $gap
        );
        ?>
        <section id="<?php echo esc_attr( $uid ); ?>" class="sl-mdt<?php echo $payload['count'] ? '' : ' is-empty'; ?>"
                 style="<?php echo esc_attr( $style ); ?>"
                 data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
                 data-nonce="<?php echo esc_attr( wp_create_nonce( 'sl_mdt_load_menu' ) ); ?>"
                 data-limit="<?php echo (int) $limit; ?>"
                 data-show-order-button="<?php echo $show_order ? '1' : '0'; ?>"
                 data-autoplay="<?php echo $autoplay ? '1' : '0'; ?>"
                 data-autoplay-delay="<?php echo (int) $autoplay_delay; ?>">
            <div class="sl-mdt-header">
                <div class="sl-mdt-heading">
                    <p class="sl-mdt-eyebrow">Fast Food · <?php echo esc_html( ucfirst( $date ) ); ?></p>
                    <?php if ( ! empty( $settings['title'] ) ) : ?><h2><?php echo esc_html( $settings['title'] ); ?></h2><?php endif; ?>
                    <?php if ( ! empty( $settings['subtitle'] ) ) : ?><p class="sl-mdt-subtitle"><?php echo esc_html( $settings['subtitle'] ); ?></p><?php endif; ?>
                </div>
                <label class="sl-mdt-agency-picker">
                    <span>Mon agence</span>
                    <select class="sl-mdt-agency-select" aria-label="Choisir une agence">
                        <?php foreach ( $agencies as $item ) :
                            $item_name = sl_mdt_agency_name( $item->slug ) ?: $item->name;
                        ?>
                            <option value="<?php echo esc_attr( $item->slug ); ?>"<?php selected( $agency, $item->slug ); ?>><?php echo esc_html( $item_name ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>

            <p class="sl-mdt-availability" aria-live="polite">
                <?php echo $payload['count']
                    ? esc_html( sprintf( _n( '%d repas disponible aujourd’hui à %s', '%d repas disponibles aujourd’hui à %s', $payload['count'], 'sl-agences' ), $payload['count'], $agency_name ) )
                    : esc_html( sprintf( 'Aucun repas disponible aujourd’hui à %s', $agency_name ) ); ?>
            </p>

            <div class="sl-mdt-carousel">
                <?php if ( $show_arrows ) : ?><button type="button" class="sl-mdt-arrow sl-mdt-prev" aria-label="Repas précédents">‹</button><?php endif; ?>
                <div class="sl-mdt-stage"><?php echo $payload['html']; // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
                <?php if ( $show_arrows ) : ?><button type="button" class="sl-mdt-arrow sl-mdt-next" aria-label="Repas suivants">›</button><?php endif; ?>
            </div>
        </section>
        <?php
    }
}
