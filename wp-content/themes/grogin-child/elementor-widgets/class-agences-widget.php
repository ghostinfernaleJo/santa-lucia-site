<?php
/**
 * Widget Elementor "Nos Agences" - Santa lucia
 * Contrôle total depuis Elementor : images, liens, filtres, pagination, grille/liste
 */

if ( ! defined( 'ABSPATH' ) ) exit;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Repeater;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Border;

class SL_Agences_Widget extends Widget_Base {

    public function get_name() {
        return 'sl_agences';
    }

    public function get_title() {
        return __( 'Nos Agences', 'child' );
    }

    public function get_icon() {
        return 'eicon-posts-grid';
    }

    public function get_categories() {
        return [ 'santa-lucia' ];
    }

    public function get_keywords() {
        return [ 'agences', 'magasins', 'boutiques', 'stores', 'liste' ];
    }

    public function get_script_depends() {
        return [ 'sl-agences-widget' ];
    }

    public function get_style_depends() {
        return [ 'sl-agences-widget' ];
    }

    protected function register_controls() {

        /* ============================================================
         *  SECTION : CONTENU DES AGENCES (Répéteur)
         * ============================================================ */
        $this->start_controls_section(
            'section_agences_list',
            [
                'label' => __( '🏪 Liste des Agences', 'child' ),
                'tab'   => Controls_Manager::TAB_CONTENT,
            ]
        );

        $repeater = new Repeater();

        $repeater->add_control(
            'agence_nom',
            [
                'label'       => __( 'Nom de l\'agence', 'child' ),
                'type'        => Controls_Manager::TEXT,
                'default'     => __( 'Essos', 'child' ),
                'label_block' => true,
            ]
        );

        $repeater->add_control(
            'agence_ville',
            [
                'label'       => __( 'Ville / Catégorie (pour filtre)', 'child' ),
                'type'        => Controls_Manager::TEXT,
                'default'     => 'Douala',
                'description' => __( 'Sert aussi de filtre de catégorie. Ex: Douala, Yaoundé', 'child' ),
            ]
        );

        $repeater->add_control(
            'agence_adresse',
            [
                'label'       => __( 'Adresse complète', 'child' ),
                'type'        => Controls_Manager::TEXT,
                'default'     => 'Douala, DOUALA IV,',
                'label_block' => true,
            ]
        );

        $repeater->add_control(
            'agence_telephone',
            [
                'label'   => __( 'Téléphone', 'child' ),
                'type'    => Controls_Manager::TEXT,
                'default' => '+237672703795',
            ]
        );

        $repeater->add_control(
            'agence_lien',
            [
                'label'         => __( '🔗 Lien de l\'agence (URL)', 'child' ),
                'type'          => Controls_Manager::URL,
                'placeholder'   => 'https://votresite.com/agence/',
                'show_external' => true,
                'default'       => [ 'url' => '#' ],
                'description'   => __( 'La flèche rose redirigera vers cette URL.', 'child' ),
            ]
        );

        $repeater->add_control(
            'agence_banniere',
            [
                'label'   => __( '🖼️ Image de Couverture (Bannière)', 'child' ),
                'type'    => Controls_Manager::MEDIA,
                'default' => [ 'url' => '' ],
            ]
        );

        $repeater->add_control(
            'agence_avatar',
            [
                'label'       => __( '🔵 Logo / Avatar (cercle rond)', 'child' ),
                'type'        => Controls_Manager::MEDIA,
                'default'     => [ 'url' => '' ],
                'description' => __( 'Apparaît dans le cercle blanc en bas à droite de la bannière.', 'child' ),
            ]
        );

        $repeater->add_control(
            'agence_est_vedette',
            [
                'label'        => __( '⭐ Agence Vedette / Featured', 'child' ),
                'type'         => Controls_Manager::SWITCHER,
                'label_on'     => __( 'Oui', 'child' ),
                'label_off'    => __( 'Non', 'child' ),
                'return_value' => 'yes',
                'default'      => '',
            ]
        );

        $repeater->add_control(
            'agence_statut',
            [
                'label'   => __( '🟢 Statut de l\'agence', 'child' ),
                'type'    => Controls_Manager::SELECT,
                'default' => 'ouvert',
                'options' => [
                    'ouvert' => __( 'Ouverte', 'child' ),
                    'ferme'  => __( 'Fermée', 'child' ),
                    'none'   => __( 'Ne pas afficher', 'child' ),
                ],
            ]
        );

        $this->add_control(
            'agences',
            [
                'label'       => __( 'Agences', 'child' ),
                'type'        => Controls_Manager::REPEATER,
                'fields'      => $repeater->get_controls(),
                'default'     => [
                    [ 'agence_nom' => 'Essos',      'agence_ville' => 'Douala', 'agence_adresse' => 'Douala, DOUALA IV,', 'agence_telephone' => '+237672703795', 'agence_lien' => ['url'=>'#'] ],
                    [ 'agence_nom' => 'Ngousso',    'agence_ville' => 'Douala', 'agence_adresse' => 'Douala, DOUALA IV,', 'agence_telephone' => '+237672703795', 'agence_lien' => ['url'=>'#'] ],
                    [ 'agence_nom' => 'Nkoabang',   'agence_ville' => 'Douala', 'agence_adresse' => 'Douala, DOUALA IV,', 'agence_telephone' => '+237672703795', 'agence_lien' => ['url'=>'#'] ],
                    [ 'agence_nom' => 'Ahala',      'agence_ville' => 'Yaoundé','agence_adresse' => 'Yaoundé, Centre,',  'agence_telephone' => '+237672703795', 'agence_lien' => ['url'=>'#'] ],
                    [ 'agence_nom' => 'Nkodengui',  'agence_ville' => 'Yaoundé','agence_adresse' => 'Yaoundé, Centre,',  'agence_telephone' => '+237672703795', 'agence_lien' => ['url'=>'#'] ],
                    [ 'agence_nom' => 'Mvan',       'agence_ville' => 'Yaoundé','agence_adresse' => 'Yaoundé, Cameroun', 'agence_telephone' => '+237672703795', 'agence_lien' => ['url'=>'#'] ],
                ],
                'title_field' => '{{{ agence_nom }}} — {{{ agence_ville }}}',
            ]
        );

        $this->end_controls_section();

        /* ============================================================
         *  SECTION : PARAMÈTRES D'AFFICHAGE
         * ============================================================ */
        $this->start_controls_section(
            'section_settings',
            [
                'label' => __( '⚙️ Paramètres d\'affichage', 'child' ),
                'tab'   => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'titre_section',
            [
                'label'   => __( 'Titre de la section', 'child' ),
                'type'    => Controls_Manager::TEXT,
                'default' => 'Nos Agences',
            ]
        );

        $this->add_control(
            'afficher_titre',
            [
                'label'        => __( 'Afficher le titre', 'child' ),
                'type'         => Controls_Manager::SWITCHER,
                'label_on'     => __( 'Oui', 'child' ),
                'label_off'    => __( 'Non', 'child' ),
                'return_value' => 'yes',
                'default'      => 'yes',
            ]
        );

        $this->add_control(
            'agences_par_page',
            [
                'label'   => __( 'Agences par page', 'child' ),
                'type'    => Controls_Manager::NUMBER,
                'min'     => 3,
                'max'     => 50,
                'step'    => 3,
                'default' => 9,
            ]
        );

        $this->add_control(
            'colonnes',
            [
                'label'   => __( 'Colonnes (mode grille)', 'child' ),
                'type'    => Controls_Manager::SELECT,
                'default' => '3',
                'options' => [
                    '2' => '2 colonnes',
                    '3' => '3 colonnes',
                    '4' => '4 colonnes',
                ],
            ]
        );

        $this->add_control(
            'vue_defaut',
            [
                'label'   => __( 'Vue par défaut', 'child' ),
                'type'    => Controls_Manager::SELECT,
                'default' => 'grid',
                'options' => [
                    'grid' => __( 'Grille', 'child' ),
                    'list' => __( 'Liste', 'child' ),
                ],
            ]
        );

        $this->add_control(
            'afficher_compteur',
            [
                'label'        => __( 'Afficher le compteur de magasins', 'child' ),
                'type'         => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default'      => 'yes',
            ]
        );

        $this->add_control(
            'afficher_filtre',
            [
                'label'        => __( 'Afficher le bouton Filtre', 'child' ),
                'type'         => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default'      => 'yes',
            ]
        );

        $this->add_control(
            'afficher_tri',
            [
                'label'        => __( 'Afficher le sélecteur de tri', 'child' ),
                'type'         => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default'      => 'yes',
            ]
        );

        $this->add_control(
            'afficher_vue_switch',
            [
                'label'        => __( 'Afficher le Switch Grille/Liste', 'child' ),
                'type'         => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default'      => 'yes',
            ]
        );

        $this->add_control(
            'texte_bouton',
            [
                'label'   => __( 'Texte du bouton Filtre', 'child' ),
                'type'    => Controls_Manager::TEXT,
                'default' => 'Filtre',
            ]
        );

        $this->add_control(
            'texte_compteur',
            [
                'label'   => __( 'Texte du compteur', 'child' ),
                'type'    => Controls_Manager::TEXT,
                'default' => 'Total stores showing',
            ]
        );

        $this->end_controls_section();

        /* ============================================================
         *  SECTION : COULEURS & STYLE (Onglet Style)
         * ============================================================ */
        $this->start_controls_section(
            'section_style_general',
            [
                'label' => __( '🎨 Couleurs Générales', 'child' ),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'couleur_principale',
            [
                'label'   => __( 'Couleur principale (boutons, flèches)', 'child' ),
                'type'    => Controls_Manager::COLOR,
                'default' => '#EA4E8B',
                'selectors' => [
                    '{{WRAPPER}} .sl-btn-arrow'       => 'background-color: {{VALUE}};',
                    '{{WRAPPER}} .sl-filtre-btn'      => 'background-color: {{VALUE}};',
                    '{{WRAPPER}} .sl-pagination a.current' => 'background-color: {{VALUE}}; color: #fff; border-color: {{VALUE}};',
                    '{{WRAPPER}} .sl-pagination-next' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'couleur_banniere_defaut',
            [
                'label'   => __( 'Couleur de fond bannière (si pas d\'image)', 'child' ),
                'type'    => Controls_Manager::COLOR,
                'default' => '#888888',
                'selectors' => [
                    '{{WRAPPER}} .sl-card-banner' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'couleur_titre_carte',
            [
                'label'   => __( 'Couleur du titre de l\'agence', 'child' ),
                'type'    => Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => [
                    '{{WRAPPER}} .sl-card-nom' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'couleur_texte_carte',
            [
                'label'   => __( 'Couleur du texte (adresse, tél)', 'child' ),
                'type'    => Controls_Manager::COLOR,
                'default' => '#e0e0e0',
                'selectors' => [
                    '{{WRAPPER}} .sl-card-adresse',
                    '{{WRAPPER}} .sl-card-tel' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();

        /* Style Titre Section */
        $this->start_controls_section(
            'section_style_titre',
            [
                'label'     => __( '✏️ Titre de la section', 'child' ),
                'tab'       => Controls_Manager::TAB_STYLE,
                'condition' => [ 'afficher_titre' => 'yes' ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => 'typographie_titre',
                'label'    => __( 'Typographie', 'child' ),
                'selector' => '{{WRAPPER}} .sl-section-titre',
            ]
        );

        $this->add_control(
            'couleur_titre_section',
            [
                'label'     => __( 'Couleur', 'child' ),
                'type'      => Controls_Manager::COLOR,
                'default'   => '#1a1a1a',
                'selectors' => [ '{{WRAPPER}} .sl-section-titre' => 'color: {{VALUE}};' ],
            ]
        );

        $this->end_controls_section();

        /* Style Carte */
        $this->start_controls_section(
            'section_style_carte',
            [
                'label' => __( '🃏 Style des Cartes', 'child' ),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'hauteur_banniere',
            [
                'label'      => __( 'Hauteur de la bannière', 'child' ),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => [ 'px' ],
                'range'      => [ 'px' => [ 'min' => 100, 'max' => 400 ] ],
                'default'    => [ 'unit' => 'px', 'size' => 180 ],
                'selectors'  => [ '{{WRAPPER}} .sl-card-banner' => 'height: {{SIZE}}{{UNIT}};' ],
            ]
        );

        $this->add_control(
            'rayon_carte',
            [
                'label'      => __( 'Arrondi des coins', 'child' ),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => [ 'px' ],
                'range'      => [ 'px' => [ 'min' => 0, 'max' => 30 ] ],
                'default'    => [ 'unit' => 'px', 'size' => 6 ],
                'selectors'  => [ '{{WRAPPER}} .sl-agence-card' => 'border-radius: {{SIZE}}{{UNIT}};' ],
            ]
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            [
                'name'     => 'ombre_carte',
                'selector' => '{{WRAPPER}} .sl-agence-card',
            ]
        );

        $this->add_control(
            'espacement_cartes',
            [
                'label'      => __( 'Espacement entre les cartes', 'child' ),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => [ 'px' ],
                'range'      => [ 'px' => [ 'min' => 0, 'max' => 50 ] ],
                'default'    => [ 'unit' => 'px', 'size' => 20 ],
                'selectors'  => [ '{{WRAPPER}} .sl-agences-grid' => 'gap: {{SIZE}}{{UNIT}};' ],
            ]
        );

        $this->end_controls_section();

        /* Style Barre de contrôle */
        $this->start_controls_section(
            'section_style_barre',
            [
                'label' => __( '🎛️ Barre de contrôle (filtre, tri)', 'child' ),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'couleur_fond_barre',
            [
                'label'     => __( 'Fond de la barre', 'child' ),
                'type'      => Controls_Manager::COLOR,
                'default'   => '#ffffff',
                'selectors' => [ '{{WRAPPER}} .sl-toolbar' => 'background-color: {{VALUE}};' ],
            ]
        );

        $this->add_control(
            'couleur_bord_barre',
            [
                'label'     => __( 'Couleur de la bordure', 'child' ),
                'type'      => Controls_Manager::COLOR,
                'default'   => '#e4e4e4',
                'selectors' => [ '{{WRAPPER}} .sl-toolbar' => 'border-color: {{VALUE}};' ],
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings        = $this->get_settings_for_display();
        $agences         = $settings['agences'];
        $par_page        = (int) $settings['agences_par_page'];
        $colonnes        = $settings['colonnes'];
        $vue_defaut      = $settings['vue_defaut'];
        $widget_id       = $this->get_id();
        $total           = count( $agences );

        // Catégories uniques pour le filtre
        $categories = [];
        foreach ( $agences as $a ) {
            $ville = trim( $a['agence_ville'] );
            if ( $ville && ! in_array( $ville, $categories ) ) {
                $categories[] = $ville;
            }
        }

        // Données encodées pour le JS
        $js_data = [];
        foreach ( $agences as $idx => $a ) {
            $lien = isset($a['agence_lien']['url']) ? $a['agence_lien']['url'] : '#';
            $target = (isset($a['agence_lien']['is_external']) && $a['agence_lien']['is_external']) ? '_blank' : '_self';
            $js_data[] = [
                'id'        => $idx,
                'nom'       => esc_html($a['agence_nom']),
                'ville'     => esc_html($a['agence_ville']),
                'adresse'   => esc_html($a['agence_adresse']),
                'tel'       => esc_html($a['agence_telephone']),
                'lien'      => esc_url($lien),
                'target'    => $target,
                'banniere'  => esc_url($a['agence_banniere']['url'] ?? ''),
                'avatar'    => esc_url($a['agence_avatar']['url'] ?? ''),
                'vedette'   => $a['agence_est_vedette'] === 'yes',
                'statut'    => $a['agence_statut'],
                'categorie' => esc_html($a['agence_ville']),
            ];
        }
        ?>

        <div class="sl-agences-wrapper" 
             id="sl-agences-<?php echo esc_attr($widget_id); ?>"
             data-par-page="<?php echo esc_attr($par_page); ?>"
             data-colonnes="<?php echo esc_attr($colonnes); ?>"
             data-vue="<?php echo esc_attr($vue_defaut); ?>"
             data-agences="<?php echo esc_attr(json_encode($js_data)); ?>">

            <?php if ( $settings['afficher_titre'] === 'yes' && $settings['titre_section'] ) : ?>
                <h2 class="sl-section-titre"><?php echo esc_html($settings['titre_section']); ?></h2>
            <?php endif; ?>

            <!-- TOOLBAR -->
            <div class="sl-toolbar">
                <div class="sl-toolbar-left">
                    <?php if ( $settings['afficher_compteur'] === 'yes' ) : ?>
                        <span class="sl-compteur">
                            <?php echo esc_html($settings['texte_compteur']); ?>: <strong class="sl-compteur-num"><?php echo $total; ?></strong>
                        </span>
                    <?php endif; ?>
                </div>
                <div class="sl-toolbar-right">
                    <?php if ( $settings['afficher_filtre'] === 'yes' && count($categories) > 0 ) : ?>
                        <div class="sl-filter-wrap">
                            <button class="sl-filtre-btn" id="sl-filtre-btn-<?php echo esc_attr($widget_id); ?>">
                                <span class="sl-filtre-icon">&#9776;</span>
                                <?php echo esc_html($settings['texte_bouton']); ?>
                            </button>
                            <div class="sl-filter-dropdown" id="sl-filter-dd-<?php echo esc_attr($widget_id); ?>">
                                <button class="sl-filter-cat active" data-cat="all">Toutes</button>
                                <?php foreach ($categories as $cat) : ?>
                                    <button class="sl-filter-cat" data-cat="<?php echo esc_attr($cat); ?>"><?php echo esc_html($cat); ?></button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ( $settings['afficher_tri'] === 'yes' ) : ?>
                        <div class="sl-sort-wrap">
                            <label class="sl-sort-label">Sort by:</label>
                            <select class="sl-sort-select" id="sl-sort-<?php echo esc_attr($widget_id); ?>">
                                <option value="recent">Most Recent</option>
                                <option value="alpha">A → Z</option>
                                <option value="alpha_desc">Z → A</option>
                            </select>
                        </div>
                    <?php endif; ?>

                    <?php if ( $settings['afficher_vue_switch'] === 'yes' ) : ?>
                        <div class="sl-vue-switch">
                            <button class="sl-vue-btn <?php echo $vue_defaut === 'grid' ? 'active' : ''; ?>" data-vue="grid" title="Vue Grille">
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><rect x="0" y="0" width="7" height="7"/><rect x="9" y="0" width="7" height="7"/><rect x="0" y="9" width="7" height="7"/><rect x="9" y="9" width="7" height="7"/></svg>
                            </button>
                            <button class="sl-vue-btn <?php echo $vue_defaut === 'list' ? 'active' : ''; ?>" data-vue="list" title="Vue Liste">
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><rect x="0" y="1" width="16" height="2"/><rect x="0" y="7" width="16" height="2"/><rect x="0" y="13" width="16" height="2"/></svg>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- GRILLE DES AGENCES -->
            <div class="sl-agences-grid <?php echo esc_attr($vue_defaut); ?>-view sl-cols-<?php echo esc_attr($colonnes); ?>" 
                 id="sl-grid-<?php echo esc_attr($widget_id); ?>">
                <!-- Les cartes sont rendues par JavaScript -->
            </div>

            <!-- PAGINATION -->
            <div class="sl-pagination-wrap" id="sl-pagination-<?php echo esc_attr($widget_id); ?>"></div>

        </div>
        <?php
    }
}
