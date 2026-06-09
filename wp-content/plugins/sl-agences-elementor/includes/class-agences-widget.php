<?php
/**
 * Widget Elementor "Nos Agences" - Santa Lucia v3
 * Rendu côté serveur (PHP) pour aperçu temps réel dans l'éditeur Elementor
 */

if ( ! defined( 'ABSPATH' ) ) exit;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Repeater;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Box_Shadow;

class SL_Agences_Widget extends Widget_Base {

    public function get_name() {
        return 'sl_agences';
    }

    public function get_title() {
        return __( 'Nos Agences', 'sl-agences' );
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
                'label' => __( '🏪 Liste des Agences', 'sl-agences' ),
                'tab'   => Controls_Manager::TAB_CONTENT,
            ]
        );

        $repeater = new Repeater();

        $repeater->add_control(
            'agence_nom',
            [
                'label'       => __( 'Nom de l\'agence', 'sl-agences' ),
                'type'        => Controls_Manager::TEXT,
                'default'     => __( 'Essos', 'sl-agences' ),
                'label_block' => true,
            ]
        );

        $repeater->add_control(
            'agence_ville',
            [
                'label'       => __( 'Ville / Catégorie (pour filtre)', 'sl-agences' ),
                'type'        => Controls_Manager::TEXT,
                'default'     => 'Douala',
                'description' => __( 'Sert aussi de filtre de catégorie. Ex: Douala, Yaoundé', 'sl-agences' ),
            ]
        );

        $repeater->add_control(
            'agence_adresse',
            [
                'label'       => __( 'Adresse complète', 'sl-agences' ),
                'type'        => Controls_Manager::TEXT,
                'default'     => 'Douala, DOUALA IV,',
                'label_block' => true,
            ]
        );

        $repeater->add_control(
            'agence_telephone',
            [
                'label'   => __( 'Téléphone', 'sl-agences' ),
                'type'    => Controls_Manager::TEXT,
                'default' => '+237672703795',
            ]
        );

        $repeater->add_control(
            'agence_lien',
            [
                'label'         => __( '🔗 Lien de l\'agence (URL)', 'sl-agences' ),
                'type'          => Controls_Manager::URL,
                'placeholder'   => 'https://votresite.com/agence/',
                'show_external' => true,
                'default'       => [ 'url' => '#' ],
                'description'   => __( 'La flèche rose redirigera vers cette URL.', 'sl-agences' ),
            ]
        );

        $repeater->add_control(
            'agence_banniere',
            [
                'label'   => __( '🖼️ Image de Couverture (Bannière)', 'sl-agences' ),
                'type'    => Controls_Manager::MEDIA,
                'default' => [ 'url' => '' ],
            ]
        );

        $repeater->add_control(
            'agence_est_vedette',
            [
                'label'        => __( '⭐ Agence Vedette / Featured', 'sl-agences' ),
                'type'         => Controls_Manager::SWITCHER,
                'label_on'     => __( 'Oui', 'sl-agences' ),
                'label_off'    => __( 'Non', 'sl-agences' ),
                'return_value' => 'yes',
                'default'      => '',
            ]
        );

        $repeater->add_control(
            'agence_statut',
            [
                'label'   => __( '🟢 Statut de l\'agence', 'sl-agences' ),
                'type'    => Controls_Manager::SELECT,
                'default' => 'ouvert',
                'options' => [
                    'ouvert' => __( 'Ouverte', 'sl-agences' ),
                    'ferme'  => __( 'Fermée', 'sl-agences' ),
                    'auto'   => __( '🕐 Automatique (selon horaires)', 'sl-agences' ),
                    'none'   => __( 'Ne pas afficher', 'sl-agences' ),
                ],
            ]
        );

        $repeater->add_control(
            'agence_heure_ouverture',
            [
                'label'       => __( '⏰ Heure d\'ouverture (mode auto)', 'sl-agences' ),
                'type'        => Controls_Manager::TEXT,
                'default'     => '08:00',
                'placeholder' => '08:00',
                'description' => __( 'Format 24h : 08:00', 'sl-agences' ),
                'condition'   => [ 'agence_statut' => 'auto' ],
            ]
        );

        $repeater->add_control(
            'agence_heure_fermeture',
            [
                'label'       => __( '⏰ Heure de fermeture (mode auto)', 'sl-agences' ),
                'type'        => Controls_Manager::TEXT,
                'default'     => '20:00',
                'placeholder' => '20:00',
                'description' => __( 'Format 24h : 20:00', 'sl-agences' ),
                'condition'   => [ 'agence_statut' => 'auto' ],
            ]
        );

        $this->add_control(
            'agences',
            [
                'label'       => __( 'Agences', 'sl-agences' ),
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
                'label' => __( '⚙️ Paramètres d\'affichage', 'sl-agences' ),
                'tab'   => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'titre_section',
            [
                'label'   => __( 'Titre de la section', 'sl-agences' ),
                'type'    => Controls_Manager::TEXT,
                'default' => 'Nos Agences',
            ]
        );

        $this->add_control(
            'afficher_titre',
            [
                'label'        => __( 'Afficher le titre', 'sl-agences' ),
                'type'         => Controls_Manager::SWITCHER,
                'label_on'     => __( 'Oui', 'sl-agences' ),
                'label_off'    => __( 'Non', 'sl-agences' ),
                'return_value' => 'yes',
                'default'      => 'yes',
            ]
        );

        $this->add_control(
            'agences_par_page',
            [
                'label'   => __( 'Agences par page', 'sl-agences' ),
                'type'    => Controls_Manager::NUMBER,
                'min'     => 1,
                'max'     => 50,
                'step'    => 1,
                'default' => 9,
            ]
        );

        $this->add_control(
            'colonnes',
            [
                'label'   => __( 'Colonnes (mode grille)', 'sl-agences' ),
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
                'label'   => __( 'Vue par défaut', 'sl-agences' ),
                'type'    => Controls_Manager::SELECT,
                'default' => 'grid',
                'options' => [
                    'grid' => __( 'Grille', 'sl-agences' ),
                    'list' => __( 'Liste', 'sl-agences' ),
                ],
            ]
        );

        $this->add_control(
            'afficher_compteur',
            [
                'label'        => __( 'Afficher le compteur d\'agences', 'sl-agences' ),
                'type'         => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default'      => 'yes',
            ]
        );

        $this->add_control(
            'afficher_recherche',
            [
                'label'        => __( '🔍 Afficher la barre de recherche', 'sl-agences' ),
                'type'         => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default'      => 'yes',
            ]
        );

        $this->add_control(
            'placeholder_recherche',
            [
                'label'     => __( 'Placeholder recherche', 'sl-agences' ),
                'type'      => Controls_Manager::TEXT,
                'default'   => 'Rechercher une agence...',
                'condition' => [ 'afficher_recherche' => 'yes' ],
            ]
        );

        $this->add_control(
            'afficher_filtre',
            [
                'label'        => __( 'Afficher le bouton Filtre ville', 'sl-agences' ),
                'type'         => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default'      => 'yes',
            ]
        );

        $this->add_control(
            'afficher_filtre_statut',
            [
                'label'        => __( '🟢 Afficher le filtre Statut (Ouvert/Fermé)', 'sl-agences' ),
                'type'         => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default'      => 'yes',
            ]
        );

        $this->add_control(
            'afficher_tri',
            [
                'label'        => __( 'Afficher le sélecteur de tri', 'sl-agences' ),
                'type'         => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default'      => 'yes',
            ]
        );

        $this->add_control(
            'afficher_vue_switch',
            [
                'label'        => __( 'Afficher le Switch Grille/Liste', 'sl-agences' ),
                'type'         => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default'      => 'yes',
            ]
        );

        $this->add_control(
            'texte_bouton',
            [
                'label'   => __( 'Texte du bouton Filtre', 'sl-agences' ),
                'type'    => Controls_Manager::TEXT,
                'default' => 'Filtrer',
            ]
        );

        $this->add_control(
            'texte_compteur',
            [
                'label'   => __( 'Texte du compteur', 'sl-agences' ),
                'type'    => Controls_Manager::TEXT,
                'default' => 'Agences affichées',
            ]
        );

        $this->add_control(
            'texte_bouton_agence',
            [
                'label'   => __( 'Texte du bouton d\'action', 'sl-agences' ),
                'type'    => Controls_Manager::TEXT,
                'default' => 'Voir l\'agence',
                'description' => __( 'Texte affiché sur le bouton rose de chaque carte.', 'sl-agences' ),
            ]
        );

        $this->end_controls_section();

        /* ============================================================
         *  SECTION : COULEURS & STYLE (Onglet Style)
         * ============================================================ */
        $this->start_controls_section(
            'section_style_general',
            [
                'label' => __( '🎨 Couleurs Générales', 'sl-agences' ),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'couleur_principale',
            [
                'label'   => __( 'Couleur principale (boutons, flèches)', 'sl-agences' ),
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
                'label'   => __( 'Couleur de fond bannière (si pas d\'image)', 'sl-agences' ),
                'type'    => Controls_Manager::COLOR,
                'default' => '#888888',
                'selectors' => [
                    '{{WRAPPER}} .sl-card-banner' => 'background-color: {{VALUE}};',
                    '{{WRAPPER}} .sl-list-thumb'  => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'couleur_titre_carte',
            [
                'label'   => __( 'Couleur du titre (vue grille)', 'sl-agences' ),
                'type'    => Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => [
                    '{{WRAPPER}} .grid-view .sl-card-nom' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'couleur_texte_carte',
            [
                'label'   => __( 'Couleur du texte (vue grille)', 'sl-agences' ),
                'type'    => Controls_Manager::COLOR,
                'default' => '#e0e0e0',
                'selectors' => [
                    '{{WRAPPER}} .grid-view .sl-card-adresse' => 'color: {{VALUE}};',
                    '{{WRAPPER}} .grid-view .sl-card-tel'     => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();

        /* Style Titre Section */
        $this->start_controls_section(
            'section_style_titre',
            [
                'label'     => __( '✏️ Titre de la section', 'sl-agences' ),
                'tab'       => Controls_Manager::TAB_STYLE,
                'condition' => [ 'afficher_titre' => 'yes' ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => 'typographie_titre',
                'label'    => __( 'Typographie', 'sl-agences' ),
                'selector' => '{{WRAPPER}} .sl-section-titre',
            ]
        );

        $this->add_control(
            'couleur_titre_section',
            [
                'label'     => __( 'Couleur', 'sl-agences' ),
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
                'label' => __( '🃏 Style des Cartes', 'sl-agences' ),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'hauteur_banniere',
            [
                'label'      => __( 'Hauteur de la bannière (grille)', 'sl-agences' ),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => [ 'px' ],
                'range'      => [ 'px' => [ 'min' => 100, 'max' => 400 ] ],
                'default'    => [ 'unit' => 'px', 'size' => 180 ],
                'selectors'  => [ '{{WRAPPER}} .grid-view .sl-card-banner' => 'height: {{SIZE}}{{UNIT}};' ],
            ]
        );

        $this->add_control(
            'rayon_carte',
            [
                'label'      => __( 'Arrondi des coins', 'sl-agences' ),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => [ 'px' ],
                'range'      => [ 'px' => [ 'min' => 0, 'max' => 30 ] ],
                'default'    => [ 'unit' => 'px', 'size' => 8 ],
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
                'label'      => __( 'Espacement entre les cartes', 'sl-agences' ),
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
                'label' => __( '🎛️ Barre de contrôle', 'sl-agences' ),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'couleur_fond_barre',
            [
                'label'     => __( 'Fond de la barre', 'sl-agences' ),
                'type'      => Controls_Manager::COLOR,
                'default'   => '#ffffff',
                'selectors' => [ '{{WRAPPER}} .sl-toolbar' => 'background-color: {{VALUE}};' ],
            ]
        );

        $this->add_control(
            'couleur_bord_barre',
            [
                'label'     => __( 'Couleur de la bordure', 'sl-agences' ),
                'type'      => Controls_Manager::COLOR,
                'default'   => '#e4e4e4',
                'selectors' => [ '{{WRAPPER}} .sl-toolbar' => 'border-color: {{VALUE}};' ],
            ]
        );

        $this->end_controls_section();
    }

    /**
     * Génère le HTML d'une carte en mode GRILLE
     */
    private function render_card_grid( $a, $btn_text = 'Voir l\'agence' ) {
        $lien   = isset($a['agence_lien']['url']) ? esc_url($a['agence_lien']['url']) : '#';
        $target = (!empty($a['agence_lien']['is_external'])) ? '_blank' : '_self';
        $banniere_url = $a['agence_banniere']['url'] ?? '';
        $banniere_style = $banniere_url ? 'background-image: url(' . esc_url($banniere_url) . ');' : '';

        $statut = $a['agence_statut'] ?? 'none';
        $h_ouv  = esc_attr($a['agence_heure_ouverture'] ?? '08:00');
        $h_ferm = esc_attr($a['agence_heure_fermeture'] ?? '20:00');

        $vedette = ($a['agence_est_vedette'] === 'yes') ? '<div class="sl-featured-badge">★ Vedette</div>' : '';
        $statut_html = '';
        if ($statut === 'ouvert') {
            $statut_html = '<span class="sl-statut sl-ouvert">Ouverte</span>';
        } elseif ($statut === 'ferme') {
            $statut_html = '<span class="sl-statut sl-ferme">Fermée</span>';
        } elseif ($statut === 'auto') {
            $statut_html = '<span class="sl-statut sl-statut-auto" data-ouv="' . $h_ouv . '" data-ferm="' . $h_ferm . '"></span>';
        }

        $tel = $a['agence_telephone'] ? '<p class="sl-card-tel"><svg class="sl-icon-phone" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/></svg> ' . esc_html($a['agence_telephone']) . '</p>' : '';
        $adresse = $a['agence_adresse'] ? '<p class="sl-card-adresse">' . esc_html($a['agence_adresse']) . '</p>' : '';

        $data_statut = ($statut === 'auto') ? 'auto' : ($statut === 'ouvert' ? 'ouvert' : 'ferme');

        return '<div class="sl-agence-card"
            data-cat="' . esc_attr($a['agence_ville']) . '"
            data-nom="' . esc_attr(strtolower($a['agence_nom'])) . ' ' . esc_attr(strtolower($a['agence_ville'])) . '"
            data-statut="' . esc_attr($data_statut) . '"
            data-h-ouv="' . $h_ouv . '"
            data-h-ferm="' . $h_ferm . '">
            <a href="' . $lien . '" target="' . $target . '" class="sl-card-banner-link">
                <div class="sl-card-banner" style="' . $banniere_style . '">
                    ' . $vedette . $statut_html . '
                    <div class="sl-card-info">
                        <h3 class="sl-card-nom">' . esc_html($a['agence_nom']) . '</h3>
                        ' . $adresse . $tel . '
                    </div>
                </div>
            </a>
            <div class="sl-card-footer">
                <a href="' . $lien . '" target="' . $target . '" class="sl-btn-arrow" aria-label="Voir ' . esc_attr($a['agence_nom']) . '">
                    <span class="sl-btn-text">' . esc_html($btn_text) . '</span>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                </a>
            </div>
        </div>';
    }

    /**
     * Génère le HTML d'une carte en mode LISTE
     */
    private function render_card_list( $a, $btn_text = 'Voir l\'agence' ) {
        $lien   = isset($a['agence_lien']['url']) ? esc_url($a['agence_lien']['url']) : '#';
        $target = (!empty($a['agence_lien']['is_external'])) ? '_blank' : '_self';
        $banniere_url = $a['agence_banniere']['url'] ?? '';
        $banniere_style = $banniere_url ? 'background-image: url(' . esc_url($banniere_url) . ');' : '';
        $adresse = $a['agence_adresse'] ? '<p class="sl-list-adresse">' . esc_html($a['agence_adresse']) . '</p>' : '';

        $statut = $a['agence_statut'] ?? 'none';
        $h_ouv  = esc_attr($a['agence_heure_ouverture'] ?? '08:00');
        $h_ferm = esc_attr($a['agence_heure_fermeture'] ?? '20:00');
        $data_statut = ($statut === 'auto') ? 'auto' : ($statut === 'ouvert' ? 'ouvert' : 'ferme');

        $statut_badge = '';
        if ($statut === 'ouvert') {
            $statut_badge = '<span class="sl-list-statut sl-ouvert">Ouverte</span>';
        } elseif ($statut === 'ferme') {
            $statut_badge = '<span class="sl-list-statut sl-ferme">Fermée</span>';
        } elseif ($statut === 'auto') {
            $statut_badge = '<span class="sl-list-statut sl-statut-auto" data-ouv="' . $h_ouv . '" data-ferm="' . $h_ferm . '"></span>';
        }

        return '<div class="sl-agence-card sl-list-card"
            data-cat="' . esc_attr($a['agence_ville']) . '"
            data-nom="' . esc_attr(strtolower($a['agence_nom'] . ' ' . $a['agence_ville'])) . '"
            data-statut="' . esc_attr($data_statut) . '"
            data-h-ouv="' . $h_ouv . '"
            data-h-ferm="' . $h_ferm . '">
            <a href="' . $lien . '" target="' . $target . '" class="sl-list-thumb-link">
                <div class="sl-list-thumb" style="' . $banniere_style . '"></div>
            </a>
            <div class="sl-list-body">
                <div class="sl-list-info">
                    <h3 class="sl-list-nom">' . esc_html($a['agence_nom']) . '</h3>
                    ' . $adresse . $statut_badge . '
                </div>
                <a href="' . $lien . '" target="' . $target . '" class="sl-btn-arrow" aria-label="Voir ' . esc_attr($a['agence_nom']) . '">
                    <span class="sl-btn-text">' . esc_html($btn_text) . '</span>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                </a>
            </div>
        </div>';
    }

    protected function render() {
        $settings   = $this->get_settings_for_display();
        $agences    = $settings['agences'];
        $par_page   = (int) $settings['agences_par_page'];
        $colonnes   = $settings['colonnes'];
        $vue_defaut = $settings['vue_defaut'];
        $widget_id  = $this->get_id();
        $total      = count( $agences );
        $is_list    = ($vue_defaut === 'list');
        $btn_text   = $settings['texte_bouton_agence'] ?? 'Voir l\'agence';

        // Catégories uniques pour le filtre
        $categories = [];
        foreach ( $agences as $a ) {
            $ville = trim( $a['agence_ville'] );
            if ( $ville && ! in_array( $ville, $categories ) ) {
                $categories[] = $ville;
            }
        }
        ?>

        <div class="sl-agences-wrapper" 
             id="sl-agences-<?php echo esc_attr($widget_id); ?>"
             data-par-page="<?php echo esc_attr($par_page); ?>"
             data-colonnes="<?php echo esc_attr($colonnes); ?>"
             data-vue="<?php echo esc_attr($vue_defaut); ?>">

            <?php if ( $settings['afficher_titre'] === 'yes' && $settings['titre_section'] ) : ?>
                <h2 class="sl-section-titre"><?php echo esc_html($settings['titre_section']); ?></h2>
            <?php endif; ?>

            <!-- TOOLBAR -->
            <div class="sl-toolbar">
                <div class="sl-toolbar-left">
                    <?php if ( $settings['afficher_compteur'] === 'yes' ) : ?>
                        <span class="sl-compteur">
                            <?php echo esc_html($settings['texte_compteur']); ?> : <strong class="sl-compteur-num"><?php echo $total; ?></strong>
                        </span>
                    <?php endif; ?>
                </div>
                <div class="sl-toolbar-right">

                    <?php if ( ($settings['afficher_recherche'] ?? 'yes') === 'yes' ) : ?>
                        <div class="sl-search-wrap">
                            <svg class="sl-search-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                            <input type="text" class="sl-search-input" id="sl-search-<?php echo esc_attr($widget_id); ?>" placeholder="<?php echo esc_attr($settings['placeholder_recherche'] ?? 'Rechercher une agence...'); ?>" autocomplete="off"/>
                            <button class="sl-search-clear" id="sl-search-clear-<?php echo esc_attr($widget_id); ?>" style="display:none" aria-label="Effacer">&times;</button>
                        </div>
                    <?php endif; ?>

                    <?php if ( $settings['afficher_filtre'] === 'yes' && count($categories) > 0 ) : ?>
                        <div class="sl-filter-wrap">
                            <button class="sl-filtre-btn" id="sl-filtre-btn-<?php echo esc_attr($widget_id); ?>">
                                <svg class="sl-filtre-svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon></svg>
                                <?php echo esc_html($settings['texte_bouton']); ?>
                            </button>
                            <div class="sl-filter-dropdown" id="sl-filter-dd-<?php echo esc_attr($widget_id); ?>">
                                <button class="sl-filter-cat active" data-cat="all">Toutes les villes</button>
                                <?php foreach ($categories as $cat) : ?>
                                    <button class="sl-filter-cat" data-cat="<?php echo esc_attr($cat); ?>"><?php echo esc_html($cat); ?></button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ( ($settings['afficher_filtre_statut'] ?? 'yes') === 'yes' ) : ?>
                        <div class="sl-statut-filter">
                            <button class="sl-statut-btn active" data-statut="all" title="Toutes">
                                <span class="sl-dot sl-dot-all"></span> Toutes
                            </button>
                            <button class="sl-statut-btn" data-statut="ouvert" title="Ouvertes">
                                <span class="sl-dot sl-dot-ouvert"></span> Ouvertes
                            </button>
                            <button class="sl-statut-btn" data-statut="ferme" title="Fermées">
                                <span class="sl-dot sl-dot-ferme"></span> Fermées
                            </button>
                        </div>
                    <?php endif; ?>

                    <?php if ( $settings['afficher_tri'] === 'yes' ) : ?>
                        <div class="sl-sort-wrap">
                            <label class="sl-sort-label">Trier par :</label>
                            <select class="sl-sort-select" id="sl-sort-<?php echo esc_attr($widget_id); ?>">
                                <option value="recent">Plus récentes</option>
                                <option value="alpha">A → Z</option>
                                <option value="alpha_desc">Z → A</option>
                            </select>
                        </div>
                    <?php endif; ?>

                    <?php if ( $settings['afficher_vue_switch'] === 'yes' ) : ?>
                        <div class="sl-vue-switch">
                            <button class="sl-vue-btn <?php echo !$is_list ? 'active' : ''; ?>" data-vue="grid" title="Vue Grille">
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><rect x="0" y="0" width="7" height="7" rx="1.5"/><rect x="9" y="0" width="7" height="7" rx="1.5"/><rect x="0" y="9" width="7" height="7" rx="1.5"/><rect x="9" y="9" width="7" height="7" rx="1.5"/></svg>
                            </button>
                            <button class="sl-vue-btn <?php echo $is_list ? 'active' : ''; ?>" data-vue="list" title="Vue Liste">
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><rect x="0" y="0.5" width="16" height="3" rx="1.5"/><rect x="0" y="6.5" width="16" height="3" rx="1.5"/><rect x="0" y="12.5" width="16" height="3" rx="1.5"/></svg>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- CONTENEUR CACHÉ : TOUTES LES CARTES (source pour le JS) -->
            <div class="sl-all-cards" id="sl-all-cards-<?php echo esc_attr($widget_id); ?>" style="display:none !important;">
                <?php if ( ! empty($agences) ) :
                    foreach ( $agences as $a ) :
                        // Rend les deux versions (grille + liste) pour chaque agence
                        echo $this->render_card_grid($a, $btn_text);
                        echo $this->render_card_list($a, $btn_text);
                    endforeach;
                endif; ?>
            </div>

            <!-- GRILLE VISIBLE (remplie par le JS) -->
            <div class="sl-agences-grid <?php echo esc_attr($vue_defaut); ?>-view sl-cols-<?php echo esc_attr($colonnes); ?>" 
                 id="sl-grid-<?php echo esc_attr($widget_id); ?>">
                <!-- Rempli par JavaScript -->
            </div>

            <!-- PAGINATION -->
            <div class="sl-pagination-wrap" id="sl-pagination-<?php echo esc_attr($widget_id); ?>"></div>

        </div>
        <?php
    }
}

