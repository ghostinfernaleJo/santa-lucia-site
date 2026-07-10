<?php
/**
 * Widget Elementor "Fiche Agence" v2 - Santa Lucia
 * Hero, Infos, Horaires 24/7, Galerie multi-images par catégorie personnalisée,
 * Support vidéo, Layout Masonry/Grille, Lightbox
 */

if ( ! defined( 'ABSPATH' ) ) exit;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Repeater;
use Elementor\Utils;

class SL_Fiche_Agence_Widget extends Widget_Base {

    public function get_name() {
        return 'sl_fiche_agence';
    }

    public function get_title() {
        return __( 'Fiche Agence', 'sl-agences' );
    }

    public function get_icon() {
        return 'eicon-single-page';
    }

    public function get_categories() {
        return [ 'santa-lucia' ];
    }

    public function get_keywords() {
        return [ 'agence', 'fiche', 'galerie', 'horaires', 'détail', 'masonry' ];
    }

    public function get_script_depends() {
        return [ 'sl-fiche-agence' ];
    }

    public function get_style_depends() {
        return [ 'sl-fiche-agence' ];
    }

    protected function register_controls() {

        /* ============================================================
         *  SECTION 1 : HERO / INFOS PRINCIPALES
         * ============================================================ */
        $this->start_controls_section(
            'section_hero',
            [
                'label' => __( '🏬 Informations de l\'agence', 'sl-agences' ),
                'tab'   => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'nom_agence',
            [
                'label'       => __( 'Nom de l\'agence', 'sl-agences' ),
                'type'        => Controls_Manager::TEXT,
                'default'     => 'Santa Lucia — Essos',
                'label_block' => true,
            ]
        );

        $this->add_control(
            'image_hero',
            [
                'label'   => __( '🖼️ Image de couverture (Hero)', 'sl-agences' ),
                'type'    => Controls_Manager::MEDIA,
                'default' => [ 'url' => '' ],
            ]
        );

        $this->add_control(
            'adresse_agence',
            [
                'label'       => __( '📍 Adresse', 'sl-agences' ),
                'type'        => Controls_Manager::TEXT,
                'default'     => 'Douala, DOUALA IV, Cameroun',
                'label_block' => true,
            ]
        );

        $this->add_control(
            'telephone_agence',
            [
                'label'   => __( '📞 Téléphone', 'sl-agences' ),
                'type'    => Controls_Manager::TEXT,
                'default' => '+237 672 703 795',
            ]
        );

        $this->add_control(
            'lien_itineraire',
            [
                'label'         => __( '🗺️ Lien Itinéraire (Google Maps)', 'sl-agences' ),
                'type'          => Controls_Manager::URL,
                'placeholder'   => 'https://maps.google.com/...',
                'show_external' => true,
                'default'       => [ 'url' => '#' ],
            ]
        );

        $this->add_control(
            'texte_itineraire',
            [
                'label'   => __( 'Texte du bouton itinéraire', 'sl-agences' ),
                'type'    => Controls_Manager::TEXT,
                'default' => 'Itinéraire',
            ]
        );

        $this->end_controls_section();

        /* ============================================================
         *  SECTION 2 : HORAIRES
         * ============================================================ */
        $this->start_controls_section(
            'section_horaires',
            [
                'label' => __( '🕐 Horaires d\'ouverture', 'sl-agences' ),
                'tab'   => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'titre_horaires',
            [
                'label'   => __( 'Titre de la section', 'sl-agences' ),
                'type'    => Controls_Manager::TEXT,
                'default' => 'Horaires d\'ouverture',
            ]
        );

        $this->add_control(
            'horaires_mode',
            [
                'label'   => __( 'Type d\'horaires', 'sl-agences' ),
                'type'    => Controls_Manager::SELECT,
                'default' => '24_7',
                'options' => [
                    '24_7'        => __( 'Ouvert 24h/24 — 7j/7', 'sl-agences' ),
                    'personnalise' => __( 'Horaires personnalisés', 'sl-agences' ),
                ],
            ]
        );

        $this->add_control(
            'texte_24_7',
            [
                'label'   => __( 'Texte affiché', 'sl-agences' ),
                'type'    => Controls_Manager::TEXT,
                'default' => 'Ouvert 24h/24 — 7 jours sur 7',
                'condition' => [ 'horaires_mode' => '24_7' ],
            ]
        );

        $repeater_h = new Repeater();

        $repeater_h->add_control(
            'jour',
            [
                'label'   => __( 'Jour', 'sl-agences' ),
                'type'    => Controls_Manager::TEXT,
                'default' => 'Lundi',
            ]
        );

        $repeater_h->add_control(
            'heures',
            [
                'label'   => __( 'Horaires', 'sl-agences' ),
                'type'    => Controls_Manager::TEXT,
                'default' => '08h00 - 20h00',
            ]
        );

        $repeater_h->add_control(
            'est_ferme',
            [
                'label'        => __( 'Fermé ce jour', 'sl-agences' ),
                'type'         => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'default'      => '',
            ]
        );

        $this->add_control(
            'horaires',
            [
                'label'       => __( 'Jours et horaires', 'sl-agences' ),
                'type'        => Controls_Manager::REPEATER,
                'fields'      => $repeater_h->get_controls(),
                'default'     => [
                    [ 'jour' => 'Lundi',    'heures' => '08h00 - 20h00' ],
                    [ 'jour' => 'Mardi',    'heures' => '08h00 - 20h00' ],
                    [ 'jour' => 'Mercredi', 'heures' => '08h00 - 20h00' ],
                    [ 'jour' => 'Jeudi',    'heures' => '08h00 - 20h00' ],
                    [ 'jour' => 'Vendredi', 'heures' => '08h00 - 20h00' ],
                    [ 'jour' => 'Samedi',   'heures' => '08h00 - 18h00' ],
                    [ 'jour' => 'Dimanche', 'heures' => '', 'est_ferme' => 'yes' ],
                ],
                'title_field' => '{{{ jour }}} — {{{ est_ferme === "yes" ? "Fermé" : heures }}}',
                'condition'   => [ 'horaires_mode' => 'personnalise' ],
            ]
        );

        $this->end_controls_section();

        /* ============================================================
         *  SECTION 3 : GALERIE MÉDIAS (images multiples + vidéos par catégorie)
         * ============================================================ */
        $this->start_controls_section(
            'section_galerie',
            [
                'label' => __( '📸 Galerie Médias', 'sl-agences' ),
                'tab'   => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'galerie_active',
            [
                'label'        => __( 'Afficher la galerie', 'sl-agences' ),
                'type'         => Controls_Manager::SWITCHER,
                'label_on'     => __( 'Oui', 'sl-agences' ),
                'label_off'    => __( 'Non', 'sl-agences' ),
                'return_value' => 'yes',
                'default'      => 'yes',
                'description'  => __( 'Désactivé, l\'onglet Galerie disparaît de la fiche (les catégories et médias sont conservés).', 'sl-agences' ),
            ]
        );

        $this->add_control(
            'titre_galerie',
            [
                'label'   => __( 'Titre de la galerie', 'sl-agences' ),
                'type'    => Controls_Manager::TEXT,
                'default' => 'Galerie de l\'agence',
            ]
        );

        $this->add_control(
            'layout_galerie',
            [
                'label'   => __( '🧱 Type de mise en page', 'sl-agences' ),
                'type'    => Controls_Manager::SELECT,
                'default' => 'masonry',
                'options' => [
                    'grid'    => __( 'Grille régulière', 'sl-agences' ),
                    'masonry' => __( 'Masonry (Pinterest)', 'sl-agences' ),
                ],
            ]
        );

        $this->add_control(
            'colonnes_galerie',
            [
                'label'   => __( 'Colonnes', 'sl-agences' ),
                'type'    => Controls_Manager::SELECT,
                'default' => '4',
                'options' => [
                    '2' => '2',
                    '3' => '3',
                    '4' => '4',
                    '5' => '5',
                ],
            ]
        );

        $this->add_control(
            'medias_par_page',
            [
                'label'   => __( 'Médias par page', 'sl-agences' ),
                'type'    => Controls_Manager::NUMBER,
                'min'     => 4,
                'max'     => 48,
                'step'    => 1,
                'default' => 8,
            ]
        );

        $this->add_control(
            'heading_categories_info',
            [
                'label' => __( 'Catégories de la galerie', 'sl-agences' ),
                'type'  => Controls_Manager::HEADING,
                'separator' => 'before',
                'description' => __( 'Chaque élément ci-dessous est une catégorie. Vous pouvez créer autant de catégories que vous souhaitez et importer plusieurs images à la fois dans chacune.', 'sl-agences' ),
            ]
        );

        $repeater_g = new Repeater();

        $repeater_g->add_control(
            'nom_categorie',
            [
                'label'       => __( '🏷️ Nom de la catégorie', 'sl-agences' ),
                'type'        => Controls_Manager::TEXT,
                'default'     => 'Rayons',
                'label_block' => true,
                'description' => __( 'Ex : Rayons, Caisses, Accueil, Fast Food...', 'sl-agences' ),
            ]
        );

        $repeater_g->add_control(
            'images_categorie',
            [
                'label'       => __( '📷 Images (sélection multiple)', 'sl-agences' ),
                'type'        => Controls_Manager::GALLERY,
                'default'     => [],
                'description' => __( 'Sélectionnez plusieurs images en une seule fois.', 'sl-agences' ),
            ]
        );

        $repeater_g->add_control(
            'heading_videos',
            [
                'label'     => __( '🎬 Vidéos (optionnel)', 'sl-agences' ),
                'type'      => Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $repeater_g->add_control(
            'videos_urls',
            [
                'label'       => __( 'URLs de vidéos (une par ligne)', 'sl-agences' ),
                'type'        => Controls_Manager::TEXTAREA,
                'default'     => '',
                'placeholder' => "https://www.youtube.com/watch?v=...\nhttps://vimeo.com/...",
                'description' => __( 'YouTube et Vimeo supportés. Collez une URL par ligne.', 'sl-agences' ),
                'rows'        => 4,
            ]
        );

        $this->add_control(
            'categories_galerie',
            [
                'label'       => __( 'Catégories', 'sl-agences' ),
                'type'        => Controls_Manager::REPEATER,
                'fields'      => $repeater_g->get_controls(),
                'default'     => [
                    [ 'nom_categorie' => 'Rayons' ],
                    [ 'nom_categorie' => 'Caisses' ],
                    [ 'nom_categorie' => 'Accueil' ],
                    [ 'nom_categorie' => 'Fast Food' ],
                    [ 'nom_categorie' => 'Parking' ],
                    [ 'nom_categorie' => 'Promotions' ],
                ],
                'title_field' => '📁 {{{ nom_categorie }}}',
            ]
        );

        $this->end_controls_section();

        /* ============================================================
         *  SECTION 4 : MENU FAST FOOD DU JOUR
         * ============================================================ */
        $this->start_controls_section(
            'section_fast_food',
            [
                'label' => __( '🍽️ Menu Fast Food du jour', 'sl-agences' ),
                'tab'   => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'fast_food_actif',
            [
                'label'        => __( 'Afficher l\'onglet menu du jour', 'sl-agences' ),
                'type'         => Controls_Manager::SWITCHER,
                'label_on'     => __( 'Oui', 'sl-agences' ),
                'label_off'    => __( 'Non', 'sl-agences' ),
                'return_value' => 'yes',
                'default'      => 'yes',
            ]
        );

        $this->add_control(
            'titre_fast_food',
            [
                'label'   => __( 'Titre', 'sl-agences' ),
                'type'    => Controls_Manager::TEXT,
                'default' => 'Menu Fast Food du jour',
            ]
        );

        $this->add_control(
            'texte_fast_food_vide',
            [
                'label'   => __( 'Message si aucun menu aujourd\'hui', 'sl-agences' ),
                'type'    => Controls_Manager::TEXT,
                'default' => 'Aucun menu fast food n\'est renseigné pour aujourd\'hui.',
            ]
        );

        $repeater_menu = new Repeater();

        $repeater_menu->add_control(
            'jour_menu',
            [
                'label'   => __( 'Jour', 'sl-agences' ),
                'type'    => Controls_Manager::SELECT,
                'default' => 'all',
                'options' => [
                    'all'       => __( 'Tous les jours', 'sl-agences' ),
                    'monday'    => __( 'Lundi', 'sl-agences' ),
                    'tuesday'   => __( 'Mardi', 'sl-agences' ),
                    'wednesday' => __( 'Mercredi', 'sl-agences' ),
                    'thursday'  => __( 'Jeudi', 'sl-agences' ),
                    'friday'    => __( 'Vendredi', 'sl-agences' ),
                    'saturday'  => __( 'Samedi', 'sl-agences' ),
                    'sunday'    => __( 'Dimanche', 'sl-agences' ),
                ],
            ]
        );

        $repeater_menu->add_control(
            'nom_plat',
            [
                'label'       => __( 'Nom du plat/menu', 'sl-agences' ),
                'type'        => Controls_Manager::TEXT,
                'default'     => 'Menu du jour',
                'label_block' => true,
            ]
        );

        $repeater_menu->add_control(
            'description_plat',
            [
                'label' => __( 'Description', 'sl-agences' ),
                'type'  => Controls_Manager::TEXTAREA,
                'rows'  => 3,
            ]
        );

        $repeater_menu->add_control(
            'prix_plat',
            [
                'label' => __( 'Prix (FCFA)', 'sl-agences' ),
                'type'  => Controls_Manager::NUMBER,
                'min'   => 0,
                'step'  => 1,
            ]
        );

        $repeater_menu->add_control(
            'image_plat',
            [
                'label' => __( 'Image', 'sl-agences' ),
                'type'  => Controls_Manager::MEDIA,
            ]
        );

        $this->add_control(
            'menus_fast_food',
            [
                'label'       => __( 'Menus par jour', 'sl-agences' ),
                'type'        => Controls_Manager::REPEATER,
                'fields'      => $repeater_menu->get_controls(),
                'default'     => [],
                'title_field' => '{{{ nom_plat }}} — {{{ jour_menu }}}',
                'condition'   => [ 'fast_food_actif' => 'yes' ],
            ]
        );

        $this->end_controls_section();

        /* ============================================================
         *  STYLE : COULEURS
         * ============================================================ */
        $this->start_controls_section(
            'section_style_fiche',
            [
                'label' => __( '🎨 Couleurs & Style', 'sl-agences' ),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'couleur_accent',
            [
                'label'   => __( 'Couleur d\'accent', 'sl-agences' ),
                'type'    => Controls_Manager::COLOR,
                'default' => '#e91e8c',
                'selectors' => [
                    '{{WRAPPER}} .slf-btn-itineraire'   => 'background-color: {{VALUE}};',
                    '{{WRAPPER}} .slf-tab.active'       => 'background-color: {{VALUE}}; border-color: {{VALUE}};',
                    '{{WRAPPER}} .slf-main-tab.active'  => 'background-color: {{VALUE}}; border-color: {{VALUE}};',
                    '{{WRAPPER}} .slf-pag a.current'    => 'background-color: {{VALUE}}; border-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'couleur_fond_horaires',
            [
                'label'   => __( 'Fond section horaires', 'sl-agences' ),
                'type'    => Controls_Manager::COLOR,
                'default' => '#f8f8fa',
                'selectors' => [
                    '{{WRAPPER}} .slf-horaires' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();

        /* ============================================================
         *  STYLE : BOUTON CTA FLOTTANT
         * ============================================================ */
        $this->start_controls_section(
            'section_style_cta',
            [
                'label' => __( '📱 Bouton flottant mobile (Appeler)', 'sl-agences' ),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'cta_actif',
            [
                'label'        => __( 'Activer le bouton flottant', 'sl-agences' ),
                'type'         => Controls_Manager::SWITCHER,
                'label_on'     => __( 'Oui', 'sl-agences' ),
                'label_off'    => __( 'Non', 'sl-agences' ),
                'return_value' => 'yes',
                'default'      => 'yes',
                'description'  => __( 'S\'affiche uniquement sur mobile, visible après le scroll.', 'sl-agences' ),
            ]
        );

        $this->add_control(
            'cta_texte',
            [
                'label'     => __( 'Texte du bouton', 'sl-agences' ),
                'type'      => Controls_Manager::TEXT,
                'default'   => 'Appeler',
                'condition' => [ 'cta_actif' => 'yes' ],
            ]
        );

        $this->add_control(
            'cta_couleur_fond',
            [
                'label'     => __( 'Couleur de fond', 'sl-agences' ),
                'type'      => Controls_Manager::COLOR,
                'default'   => '#e91e8c',
                'selectors' => [
                    '{{WRAPPER}} .slf-cta-flottant' => 'background: {{VALUE}}; background-image: none;',
                ],
                'condition' => [ 'cta_actif' => 'yes' ],
            ]
        );

        $this->add_control(
            'cta_couleur_texte',
            [
                'label'     => __( 'Couleur du texte', 'sl-agences' ),
                'type'      => Controls_Manager::COLOR,
                'default'   => '#ffffff',
                'selectors' => [
                    '{{WRAPPER}} .slf-cta-flottant' => 'color: {{VALUE}};',
                ],
                'condition' => [ 'cta_actif' => 'yes' ],
            ]
        );

        $this->end_controls_section();
    }

    /**
     * Convertit une URL YouTube/Vimeo en URL d'embed
     */
    private function get_embed_url( $url ) {
        $url = trim($url);
        if (empty($url)) return '';

        // YouTube
        if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([\w-]+)/', $url, $m)) {
            return 'https://www.youtube.com/embed/' . $m[1];
        }
        // Vimeo
        if (preg_match('/vimeo\.com\/(\d+)/', $url, $m)) {
            return 'https://player.vimeo.com/video/' . $m[1];
        }
        // Fallback : URL directe
        return $url;
    }

    /**
     * Détecte la miniature YouTube
     */
    private function get_video_thumb( $url ) {
        $url = trim($url);
        if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([\w-]+)/', $url, $m)) {
            return 'https://img.youtube.com/vi/' . $m[1] . '/hqdefault.jpg';
        }
        return '';
    }

    private function normalize_agence_name( $value ) {
        $value = remove_accents( (string) $value );
        $value = strtolower( $value );
        return preg_replace( '/[^a-z0-9]+/', '', $value );
    }

    private function resolve_agence_term( $nom_agence ) {
        $term = get_term_by( 'slug', sanitize_title( $nom_agence ), 'sl_agence_promo' );
        if ( $term && ! is_wp_error( $term ) ) return $term;

        $term = get_term_by( 'name', $nom_agence, 'sl_agence_promo' );
        if ( $term && ! is_wp_error( $term ) ) return $term;

        $page_slug = '';
        $queried = get_queried_object();
        if ( $queried && ! empty( $queried->post_name ) ) {
            $page_slug = $queried->post_name;
            $term = get_term_by( 'slug', sanitize_title( $page_slug ), 'sl_agence_promo' );
            if ( $term && ! is_wp_error( $term ) ) return $term;
        }

        $official_agences = [
            'Nkondengui',
            'Ngousso',
            'Nkoabang',
            'Mokolo',
            'Mélen',
            'Essos',
            'Ahala',
            'Odza',
            'Mvan',
            'Simbock',
            'Cité cicam',
            'Akwa-nord',
            'Bonaberi',
            'Bonamoussadi',
            'Akwa',
            'Nkolbong',
            'BERCY',
            'Cité des Palmiers',
        ];

        $haystack = $this->normalize_agence_name( $nom_agence . ' ' . $page_slug );
        foreach ( $official_agences as $agence ) {
            $needle = $this->normalize_agence_name( $agence );
            if ( $needle && strpos( $haystack, $needle ) !== false ) {
                $term = get_term_by( 'slug', sanitize_title( $agence ), 'sl_agence_promo' );
                if ( ! $term || is_wp_error( $term ) ) {
                    $term = get_term_by( 'name', $agence, 'sl_agence_promo' );
                }
                if ( $term && ! is_wp_error( $term ) ) return $term;
            }
        }

        // Alias : la page « Lycée Bilingue » correspond a l'agence Essos
        // (pas de terme « lycée bilingue » dans la taxonomie).
        if ( strpos( $haystack, 'lyceebilingue' ) !== false ) {
            $term = get_term_by( 'slug', 'essos', 'sl_agence_promo' );
            if ( $term && ! is_wp_error( $term ) ) return $term;
        }

        return null;
    }

    private function render_bons_plans_section( $nom_agence ) {
        $term = $this->resolve_agence_term( $nom_agence );
        if ( ! $term ) {
            echo '<div class="slf-empty-state">Aucune agence correspondante n\'a été trouvée pour afficher les bons plans.</div>';
            return;
        }

        $today = current_time( 'Y-m-d' );
        $query = new WP_Query( [
            'post_type'      => 'sl_bon_plan',
            'post_status'    => 'publish',
            'posts_per_page' => 12,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'tax_query'      => [
                [
                    'taxonomy' => 'sl_agence_promo',
                    'field'    => 'term_id',
                    'terms'    => [ (int) $term->term_id ],
                ],
            ],
            'meta_query'     => [
                'relation' => 'OR',
                [
                    'key'     => '_sl_bp_date_fin',
                    'value'   => $today,
                    'compare' => '>=',
                    'type'    => 'DATE',
                ],
                [
                    'key'     => '_sl_bp_date_fin',
                    'value'   => '',
                    'compare' => '=',
                ],
                [
                    'key'     => '_sl_bp_date_fin',
                    'compare' => 'NOT EXISTS',
                ],
            ],
        ] );

        if ( ! $query->have_posts() ) {
            echo '<div class="slf-empty-state">Aucun bon plan actif pour cette agence pour le moment.</div>';
            wp_reset_postdata();
            return;
        }

        $badge_labels = [
            'flash'     => 'Flash',
            'nouveau'   => 'Nouveau',
            'top-vente' => 'Top Vente',
            'exclusif'  => 'Exclusif',
        ];
        $bp_embed_id = 'slf-bp-embed-' . $this->get_id();
        ?>
        <div class="slf-bp-embed" id="<?php echo esc_attr( $bp_embed_id ); ?>">
        <div class="slf-ff-toolbar" style="display:flex;flex-wrap:wrap;align-items:center;gap:12px;margin-bottom:14px;">
            <div class="slf-ff-search" style="position:relative;flex:1 1 220px;max-width:340px;">
                <svg style="position:absolute;left:12px;top:50%;transform:translateY(-50%);pointer-events:none;color:#999;" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="search" class="slf-bp-search-input" placeholder="Rechercher un bon plan..."
                       autocomplete="off" aria-label="Rechercher un bon plan"
                       style="width:100%;padding:9px 12px 9px 36px;border:1px solid #e3e3e3;border-radius:24px;font-size:14px;outline:none;">
            </div>
            <span class="slf-bp-search-count" style="display:none;font-size:13px;color:#777;"></span>
        </div>
        <div class="slf-bp-badge-filters" role="group" aria-label="Filtrer par type d'offre"
             style="display:flex;flex-wrap:wrap;gap:8px;margin:-4px 0 14px;">
            <button type="button" class="slf-bp-badge-btn active" data-badge="all">Tous</button>
            <button type="button" class="slf-bp-badge-btn" data-badge="flash">🔥 Flash</button>
            <button type="button" class="slf-bp-badge-btn" data-badge="nouveau">🟢 Nouveau</button>
            <button type="button" class="slf-bp-badge-btn" data-badge="top-vente">👑 Top Vente</button>
            <button type="button" class="slf-bp-badge-btn" data-badge="exclusif">💎 Exclusif</button>
        </div>
        <style>
        .slf-bp-badge-btn{border:1px solid #e3e3e3;background:#fff;border-radius:20px;padding:6px 14px;font-size:13px;cursor:pointer;transition:all .15s;color:#555;}
        .slf-bp-badge-btn:hover{border-color:#e91e8c;color:#e91e8c;}
        .slf-bp-badge-btn.active{background:#e91e8c;border-color:#e91e8c;color:#fff;font-weight:600;}
        </style>
        <div class="slf-deals-grid">
            <?php while ( $query->have_posts() ) : $query->the_post();
                $post_id  = get_the_ID();
                $prix_av  = (float) get_post_meta( $post_id, '_sl_bp_prix_avant', true );
                $prix_ap  = (float) get_post_meta( $post_id, '_sl_bp_prix_apres', true );
                $reduc    = (int) get_post_meta( $post_id, '_sl_bp_reduction_pct', true );
                $badge    = get_post_meta( $post_id, '_sl_bp_badge_type', true );
                $date_fin = get_post_meta( $post_id, '_sl_bp_date_fin', true );
                $img_url  = get_the_post_thumbnail_url( $post_id, 'medium' );
                $badge_label = $badge_labels[ $badge ] ?? ucfirst( str_replace( '-', ' ', $badge ) );
                ?>
                <a class="slf-deal-card" href="<?php the_permalink(); ?>" data-badge="<?php echo esc_attr( $badge ?: '' ); ?>">
                    <span class="slf-deal-img">
                        <?php if ( $img_url ) : ?>
                            <img src="<?php echo esc_url( $img_url ); ?>" alt="<?php echo esc_attr( get_the_title() ); ?>" loading="lazy">
                        <?php else : ?>
                            <span class="slf-deal-no-img">Bon plan</span>
                        <?php endif; ?>
                        <?php if ( $reduc > 0 ) : ?>
                            <span class="slf-deal-reduc">-<?php echo esc_html( $reduc ); ?>%</span>
                        <?php endif; ?>
                        <?php if ( $badge ) : ?>
                            <span class="slf-deal-badge slf-deal-badge-<?php echo esc_attr( $badge ); ?>"><?php echo esc_html( $badge_label ); ?></span>
                        <?php endif; ?>
                    </span>
                    <span class="slf-deal-body">
                        <strong class="slf-deal-title"><?php the_title(); ?></strong>
                        <span class="slf-deal-prices">
                            <span class="slf-deal-price"><?php echo number_format( $prix_ap, 0, ',', ' ' ); ?> FCFA</span>
                            <?php if ( $prix_av > 0 ) : ?>
                                <span class="slf-deal-old-price"><?php echo number_format( $prix_av, 0, ',', ' ' ); ?> FCFA</span>
                            <?php endif; ?>
                        </span>
                        <?php if ( $date_fin ) : ?>
                            <span class="slf-deal-date">Valable jusqu'au <?php echo esc_html( date_i18n( 'd M Y', strtotime( $date_fin ) ) ); ?></span>
                        <?php endif; ?>
                    </span>
                </a>
            <?php endwhile; ?>
        </div>
        <div class="slf-bp-no-result" style="display:none;text-align:center;padding:28px 10px;color:#888;">
            Aucun bon plan ne correspond &agrave; cette recherche.
        </div>
        </div>
        <script>
        (function(){
            var root = document.getElementById('<?php echo esc_js( $bp_embed_id ); ?>');
            if (!root) return;
            var input   = root.querySelector('.slf-bp-search-input');
            var counter = root.querySelector('.slf-bp-search-count');
            var noRes   = root.querySelector('.slf-bp-no-result');
            var grid    = root.querySelector('.slf-deals-grid');
            if (!input || !grid) return;
            var norm = function(s){
                s = (s || '').toLowerCase();
                if (s.normalize) s = s.normalize('NFD').replace(/[̀-ͯ]/g, '');
                return s.replace(/\s+/g, ' ').trim();
            };
            var cards = Array.prototype.slice.call(grid.querySelectorAll('.slf-deal-card'));
            cards.forEach(function(c){ c._slfSearch = norm(c.textContent); });

            /* Filtres par type d'offre (Flash / Nouveau / Top Vente / Exclusif) */
            var badgeBtns = Array.prototype.slice.call(root.querySelectorAll('.slf-bp-badge-btn'));
            var curBadge = 'all';
            // masquer les filtres sans aucune offre correspondante
            badgeBtns.forEach(function(b){
                var v = b.dataset.badge;
                if (v === 'all') return;
                var has = cards.some(function(c){ return (c.dataset.badge || '') === v; });
                if (!has) b.style.display = 'none';
            });

            var timer = null;
            var run = function(){
                var q = norm(input.value);
                var shown = 0;
                cards.forEach(function(c){
                    var okText  = !q || c._slfSearch.indexOf(q) !== -1;
                    var okBadge = curBadge === 'all' || (c.dataset.badge || '') === curBadge;
                    var ok = okText && okBadge;
                    c.style.display = ok ? '' : 'none';
                    if (ok) shown++;
                });
                var filtering = q || curBadge !== 'all';
                noRes.style.display = (filtering && shown === 0) ? '' : 'none';
                if (filtering) {
                    counter.textContent = shown + ' bon(s) plan(s) trouvé(s)';
                    counter.style.display = '';
                } else {
                    counter.style.display = 'none';
                }
            };
            badgeBtns.forEach(function(b){
                b.addEventListener('click', function(){
                    curBadge = b.dataset.badge;
                    badgeBtns.forEach(function(x){ x.classList.toggle('active', x === b); });
                    run();
                });
            });
            input.addEventListener('input', function(){
                clearTimeout(timer);
                timer = setTimeout(run, 120);
            });
        })();
        </script>
        <?php
        wp_reset_postdata();
    }

    private function get_today_menu_key() {
        $days = [
            1 => 'monday',
            2 => 'tuesday',
            3 => 'wednesday',
            4 => 'thursday',
            5 => 'friday',
            6 => 'saturday',
            7 => 'sunday',
        ];
        return $days[ (int) wp_date( 'N' ) ] ?? 'monday';
    }

    private function render_fast_food_section( $settings, $agence_term = null ) {
        // Source privilegiee : le plugin Fast Food (menu JOURNALIER par agence,
        // gere par les responsables dans wp-admin). Le repeater manuel du widget
        // ne sert plus que de repli si le plugin est absent ou l'agence inconnue.
        if ( $agence_term && function_exists( 'sl_ff_render_menu_html' ) ) {
            $embed_id = 'slf-ff-embed-' . $this->get_id();
            ?>
            <div class="slf-ff-embed" id="<?php echo esc_attr( $embed_id ); ?>">
                <div class="slf-ff-toolbar" style="display:flex;flex-wrap:wrap;align-items:center;gap:12px;margin-bottom:14px;">
                    <div class="slf-ff-search" style="position:relative;flex:1 1 220px;max-width:340px;">
                        <svg style="position:absolute;left:12px;top:50%;transform:translateY(-50%);pointer-events:none;color:#999;" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        <input type="search" class="slf-ff-search-input" placeholder="Rechercher un repas..."
                               autocomplete="off" aria-label="Rechercher un repas"
                               style="width:100%;padding:9px 12px 9px 36px;border:1px solid #e3e3e3;border-radius:24px;font-size:14px;outline:none;">
                    </div>
                    <span class="slf-ff-search-count" style="display:none;font-size:13px;color:#777;"></span>
                    <div class="sl-ff-view-toggle" role="group" aria-label="Vue du menu" style="margin-left:auto;">
                        <button type="button" class="sl-ff-btn-view" data-view="cards" aria-pressed="false" title="Vue cartes">Cartes</button>
                        <button type="button" class="sl-ff-btn-view active" data-view="list" aria-pressed="true" title="Vue liste">Liste</button>
                    </div>
                </div>
                <div class="sl-ff-content sl-ff-view-list"><?php echo sl_ff_render_menu_html( $agence_term->slug ); ?></div>
                <div class="slf-ff-no-result" style="display:none;text-align:center;padding:28px 10px;color:#888;">
                    Aucun repas ne correspond &agrave; cette recherche.
                </div>
            </div>
            <script>
            (function(){
                var root = document.getElementById('<?php echo esc_js( $embed_id ); ?>');
                if (!root) return;
                var content = root.querySelector('.sl-ff-content');
                var btns = root.querySelectorAll('.sl-ff-btn-view');
                var KEY = 'sl_ff_fiche_view'; // preference propre aux fiches agence (defaut : liste)
                function apply(v){
                    content.classList.remove('sl-ff-view-cards','sl-ff-view-list');
                    content.classList.add('sl-ff-view-' + v);
                    btns.forEach(function(b){
                        var on = b.dataset.view === v;
                        b.classList.toggle('active', on);
                        b.setAttribute('aria-pressed', on ? 'true' : 'false');
                    });
                }
                var v = 'list';
                try { v = localStorage.getItem(KEY) || 'list'; } catch(e){}
                if (v !== 'cards' && v !== 'list') v = 'list';
                apply(v);
                btns.forEach(function(b){
                    b.addEventListener('click', function(){
                        apply(b.dataset.view);
                        try { localStorage.setItem(KEY, b.dataset.view); } catch(e){}
                    });
                });

                /* ---- Recherche d'un repas (filtre instantane, sans accents) ---- */
                var input   = root.querySelector('.slf-ff-search-input');
                var counter = root.querySelector('.slf-ff-search-count');
                var noRes   = root.querySelector('.slf-ff-no-result');
                if (input) {
                    var norm = function(s){
                        s = (s || '').toLowerCase();
                        if (s.normalize) s = s.normalize('NFD').replace(/[̀-ͯ]/g, '');
                        return s.replace(/\s+/g, ' ').trim();
                    };
                    var items = Array.prototype.slice.call(content.querySelectorAll('.sl-ff-item'));
                    items.forEach(function(it){
                        it._slfSearch = norm(it.textContent);
                    });
                    var timer = null;
                    var run = function(){
                        var q = norm(input.value);
                        var shown = 0;
                        items.forEach(function(it){
                            var ok = !q || it._slfSearch.indexOf(q) !== -1;
                            it.style.display = ok ? '' : 'none';
                            if (ok) shown++;
                        });
                        // masquer les categories sans repas visible
                        content.querySelectorAll('.sl-ff-cat-section').forEach(function(sec){
                            var has = Array.prototype.some.call(sec.querySelectorAll('.sl-ff-item'), function(it){
                                return it.style.display !== 'none';
                            });
                            sec.style.display = has ? '' : 'none';
                        });
                        noRes.style.display = (q && shown === 0) ? '' : 'none';
                        if (q) {
                            counter.textContent = shown + ' repas trouvé(s)';
                            counter.style.display = '';
                        } else {
                            counter.style.display = 'none';
                        }
                    };
                    input.addEventListener('input', function(){
                        clearTimeout(timer);
                        timer = setTimeout(run, 120);
                    });
                }
            })();
            </script>
            <?php
            return;
        }

        $menus = $settings['menus_fast_food'] ?? [];
        $today_key = $this->get_today_menu_key();
        $today_items = [];

        foreach ( $menus as $item ) {
            $jour = $item['jour_menu'] ?? 'all';
            if ( $jour === 'all' || $jour === $today_key ) {
                $today_items[] = $item;
            }
        }

        if ( empty( $today_items ) ) {
            $message = $settings['texte_fast_food_vide'] ?? 'Aucun menu fast food n\'est renseigné pour aujourd\'hui.';
            echo '<div class="slf-empty-state">' . esc_html( $message ) . '</div>';
            return;
        }
        ?>
        <div class="slf-menu-grid">
            <?php foreach ( $today_items as $item ) :
                $image = $item['image_plat']['url'] ?? '';
                $prix = isset( $item['prix_plat'] ) ? (float) $item['prix_plat'] : 0;
                ?>
                <article class="slf-menu-card">
                    <?php if ( $image ) : ?>
                        <img class="slf-menu-img" src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( $item['nom_plat'] ?? '' ); ?>" loading="lazy">
                    <?php endif; ?>
                    <div class="slf-menu-body">
                        <h3 class="slf-menu-title"><?php echo esc_html( $item['nom_plat'] ?? 'Menu du jour' ); ?></h3>
                        <?php if ( ! empty( $item['description_plat'] ) ) : ?>
                            <p class="slf-menu-desc"><?php echo esc_html( $item['description_plat'] ); ?></p>
                        <?php endif; ?>
                        <?php if ( $prix > 0 ) : ?>
                            <p class="slf-menu-price"><?php echo number_format( $prix, 0, ',', ' ' ); ?> FCFA</p>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        <?php
    }

    protected function render() {
        $s = $this->get_settings_for_display();
        $wid = $this->get_id();

        $hero_url = $s['image_hero']['url'] ?? '';
        $hero_style = $hero_url ? 'background-image:url(' . esc_url($hero_url) . ');' : '';
        $itin_url = $s['lien_itineraire']['url'] ?? '#';
        $itin_target = (!empty($s['lien_itineraire']['is_external'])) ? '_blank' : '_self';

        $layout = $s['layout_galerie'] ?? 'masonry';
        $colonnes = $s['colonnes_galerie'] ?? '4';

        $categories = $s['categories_galerie'] ?? [];
        $fast_food_actif = ( $s['fast_food_actif'] ?? 'yes' ) === 'yes';
        $galerie_active  = ( $s['galerie_active'] ?? 'yes' ) === 'yes';
        $has_galerie = $galerie_active && ! empty( $categories );
        ?>

        <div class="slf-wrapper" id="slf-<?php echo esc_attr($wid); ?>"
             data-medias-par-page="<?php echo esc_attr($s['medias_par_page']); ?>"
             data-layout="<?php echo esc_attr($layout); ?>"
             data-colonnes="<?php echo esc_attr($colonnes); ?>">

            <!-- ========== HERO ========== -->
            <div class="slf-hero" style="<?php echo $hero_style; ?>">
                <div class="slf-hero-overlay"></div>
                <div class="slf-hero-content">
                    <h1 class="slf-hero-titre"><?php echo esc_html($s['nom_agence']); ?></h1>
                    <div class="slf-hero-infos">
                        <?php if ($s['adresse_agence']) : ?>
                            <span class="slf-info-item">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
                                <?php echo esc_html($s['adresse_agence']); ?>
                            </span>
                        <?php endif; ?>
                        <?php if ($s['telephone_agence']) : ?>
                            <span class="slf-info-item">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6A19.79 19.79 0 012.12 4.18 2 2 0 014.11 2h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/></svg>
                                <a href="tel:<?php echo esc_attr(preg_replace('/\s+/', '', $s['telephone_agence'])); ?>" class="slf-tel-link"><?php echo esc_html($s['telephone_agence']); ?></a>
                            </span>
                        <?php endif; ?>
                    </div>
                    <?php if ($itin_url && $itin_url !== '#') : ?>
                        <a href="<?php echo esc_url($itin_url); ?>" target="<?php echo $itin_target; ?>" class="slf-btn-itineraire">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="3 11 22 2 13 21 11 13 3 11"/></svg>
                            <?php echo esc_html($s['texte_itineraire']); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ========== HORAIRES ========== -->
            <div class="slf-horaires">
                <?php if ($s['titre_horaires']) : ?>
                    <h2 class="slf-section-titre"><?php echo esc_html($s['titre_horaires']); ?></h2>
                <?php endif; ?>

                <?php if ($s['horaires_mode'] === '24_7') : ?>
                    <div class="slf-h24-badge">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        <span><?php echo esc_html($s['texte_24_7']); ?></span>
                    </div>
                <?php else :
                    $horaires = $s['horaires'] ?? [];
                    if (!empty($horaires)) : ?>
                    <div class="slf-horaires-grid">
                        <?php foreach ($horaires as $h) :
                            $ferme = ($h['est_ferme'] === 'yes');
                        ?>
                            <div class="slf-horaire-row <?php echo $ferme ? 'slf-ferme' : ''; ?>">
                                <span class="slf-jour"><?php echo esc_html($h['jour']); ?></span>
                                <span class="slf-heures"><?php echo $ferme ? 'Fermé' : esc_html($h['heures']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif;
                endif; ?>
            </div>

            <!-- ========== ONGLET AGENCE : GALERIE / BONS PLANS / MENU ========== -->
            <div class="slf-content-tabs" role="tablist" aria-label="Contenu de l'agence">
                <?php if ( $has_galerie ) : ?>
                    <button class="slf-main-tab active" type="button" data-panel="galerie">Galerie</button>
                <?php endif; ?>
                <button class="slf-main-tab <?php echo $has_galerie ? '' : 'active'; ?>" type="button" data-panel="bons-plans">Bons plans</button>
                <?php if ( $fast_food_actif ) : ?>
                    <button class="slf-main-tab" type="button" data-panel="fast-food">Menu du jour</button>
                <?php endif; ?>
            </div>

            <?php if ( $has_galerie ) : ?>
            <div class="slf-main-panel active" data-panel="galerie">
                <div class="slf-galerie-section">
                    <?php if ($s['titre_galerie']) : ?>
                        <h2 class="slf-section-titre"><?php echo esc_html($s['titre_galerie']); ?></h2>
                    <?php endif; ?>

                    <!-- Tabs -->
                    <div class="slf-tabs" id="slf-tabs-<?php echo esc_attr($wid); ?>">
                        <button class="slf-tab active" data-cat="all">Tout</button>
                        <?php foreach ($categories as $cat) :
                            $slug = sanitize_title($cat['nom_categorie']);
                        ?>
                            <button class="slf-tab" data-cat="<?php echo esc_attr($slug); ?>"><?php echo esc_html($cat['nom_categorie']); ?></button>
                        <?php endforeach; ?>
                    </div>

                    <!-- Conteneur caché avec TOUS les médias -->
                    <div class="slf-all-medias" id="slf-all-medias-<?php echo esc_attr($wid); ?>" style="display:none !important;">
                        <?php foreach ($categories as $cat) :
                            $slug = sanitize_title($cat['nom_categorie']);
                            $images = $cat['images_categorie'] ?? [];
                            $videos_raw = $cat['videos_urls'] ?? '';
                            $videos = array_filter(array_map('trim', explode("\n", $videos_raw)));

                            // Images
                            foreach ($images as $img) :
                                $img_url = $img['url'] ?? '';
                                if (!$img_url) continue;
                            ?>
                                <div class="slf-media-item" data-cat="<?php echo esc_attr($slug); ?>" data-type="image">
                                    <img src="<?php echo esc_url($img_url); ?>" alt="<?php echo esc_attr($cat['nom_categorie']); ?>" loading="lazy"/>
                                </div>
                            <?php endforeach;

                            // Vidéos
                            foreach ($videos as $vurl) :
                                $embed = $this->get_embed_url($vurl);
                                $thumb = $this->get_video_thumb($vurl);
                                if (!$embed) continue;
                            ?>
                                <div class="slf-media-item slf-video-item" data-cat="<?php echo esc_attr($slug); ?>" data-type="video" data-embed="<?php echo esc_attr($embed); ?>">
                                    <?php if ($thumb) : ?>
                                        <img src="<?php echo esc_url($thumb); ?>" alt="Vidéo — <?php echo esc_attr($cat['nom_categorie']); ?>" loading="lazy"/>
                                    <?php else : ?>
                                        <div class="slf-video-placeholder"></div>
                                    <?php endif; ?>
                                    <div class="slf-play-icon">
                                        <svg width="48" height="48" viewBox="0 0 24 24" fill="white"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                                    </div>
                                </div>
                            <?php endforeach;

                        endforeach; ?>
                    </div>

                    <!-- Grille visible -->
                    <div class="slf-galerie-grid slf-layout-<?php echo esc_attr($layout); ?> slf-cols-<?php echo esc_attr($colonnes); ?>"
                         id="slf-grid-<?php echo esc_attr($wid); ?>"></div>

                    <!-- Pagination -->
                    <div class="slf-pag-wrap" id="slf-pag-<?php echo esc_attr($wid); ?>"></div>
                </div>
            </div>
            <?php endif; ?>

            <div class="slf-main-panel <?php echo $has_galerie ? '' : 'active'; ?>" data-panel="bons-plans">
                <section class="slf-bons-plans-section">
                    <h2 class="slf-section-titre">Bons plans de cette agence</h2>
                    <?php $this->render_bons_plans_section( $s['nom_agence'] ?? '' ); ?>
                </section>
            </div>

            <?php if ( $fast_food_actif ) : ?>
            <div class="slf-main-panel" data-panel="fast-food">
                <section class="slf-fast-food-section">
                    <h2 class="slf-section-titre"><?php echo esc_html( $s['titre_fast_food'] ?? 'Menu Fast Food du jour' ); ?></h2>
                    <?php $this->render_fast_food_section( $s, $this->resolve_agence_term( $s['nom_agence'] ?? '' ) ); ?>
                </section>
            </div>
            <?php endif; ?>

            <!-- ========== LIGHTBOX ========== -->
            <div class="slf-lightbox" id="slf-lightbox-<?php echo esc_attr($wid); ?>">
                <div class="slf-lb-toolbar">
                    <span class="slf-lb-counter">1 / 1</span>
                    <button class="slf-lb-close" aria-label="Fermer">&times;</button>
                </div>
                <button class="slf-lb-prev" aria-label="Précédent">&#10094;</button>
                <button class="slf-lb-next" aria-label="Suivant">&#10095;</button>
                <div class="slf-lb-content">
                    <img src="" alt="" class="slf-lb-img"/>
                    <iframe class="slf-lb-video" src="" frameborder="0" allowfullscreen allow="autoplay" style="display:none;"></iframe>
                </div>
            </div>

            <!-- ========== BOUTON FLOTTANT APPELER (mobile) ========== -->
            <?php
            $cta_actif = $s['cta_actif'] ?? 'yes';
            $cta_texte = $s['cta_texte'] ?? 'Appeler';
            if ( $cta_actif === 'yes' && $s['telephone_agence'] ) : ?>
                <a href="tel:<?php echo esc_attr(preg_replace('/\s+/', '', $s['telephone_agence'])); ?>"
                   class="slf-cta-flottant"
                   aria-label="Appeler <?php echo esc_attr($s['nom_agence']); ?>">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6A19.79 19.79 0 012.12 4.18 2 2 0 014.11 2h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/></svg>
                    <span class="slf-cta-label"><?php echo esc_html($cta_texte); ?></span>
                </a>
            <?php endif; ?>

        </div>
        <?php
    }
}
