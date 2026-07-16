<?php
/**
 * Widget Elementor "Bons Plans — Carrousel" — Santa Lucia
 * Carrousel horizontal (swipe + fleches + points + autoplay) reutilisant la
 * carte .slbp-card du widget Bons Plans (style identique via 'sl-bons-plans').
 * Alimente automatiquement par les bons plans ACTIFS (non expires).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

class SL_Bons_Plans_Carousel_Widget extends Widget_Base {

    public function get_name()           { return 'sl_bons_plans_carousel'; }
    public function get_title()          { return __( 'Bons Plans — Carrousel', 'sl-agences' ); }
    public function get_icon()           { return 'eicon-media-carousel'; }
    public function get_categories()     { return [ 'santa-lucia' ]; }
    public function get_keywords()       { return [ 'bons plans', 'carrousel', 'carousel', 'promo', 'slider', 'offres' ]; }
    public function get_style_depends()  { return [ 'sl-bons-plans' ]; }

    private function sl_term_options( $tax ) {
        $out   = [];
        $terms = get_terms( [ 'taxonomy' => $tax, 'hide_empty' => false, 'orderby' => 'name' ] );
        if ( ! is_wp_error( $terms ) ) {
            foreach ( $terms as $t ) { $out[ $t->slug ] = $t->name; }
        }
        return $out;
    }

    protected function register_controls() {

        /* ── CONTENU ── */
        $this->start_controls_section( 'sec_contenu', [
            'label' => __( '🎠 Carrousel Bons Plans', 'sl-agences' ),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ] );

        $this->add_control( 'mode_campagne', [
            'label'        => __( '🔥 Priorité campagne (accueil)', 'sl-agences' ),
            'type'         => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'default'      => 'no',
            'description'  => 'Quand une campagne est active, le carrousel affiche automatiquement les produits de la campagne. Sinon il affiche les Bons Plans ci-dessous.',
        ] );

        $this->add_control( 'titre', [
            'label'   => __( 'Titre (au-dessus)', 'sl-agences' ),
            'type'    => Controls_Manager::TEXT,
            'default' => 'Nos Bons Plans',
        ] );

        $this->add_control( 'sous_titre', [
            'label'   => __( 'Sous-titre', 'sl-agences' ),
            'type'    => Controls_Manager::TEXT,
            'default' => 'Des offres à ne pas manquer, dans la limite des stocks.',
        ] );

        $this->add_control( 'nombre', [
            'label'   => __( 'Nombre max d\'offres', 'sl-agences' ),
            'type'    => Controls_Manager::NUMBER,
            'min'     => 2, 'max' => 40, 'default' => 12,
        ] );

        $this->add_control( 'tri', [
            'label'   => __( 'Tri', 'sl-agences' ),
            'type'    => Controls_Manager::SELECT,
            'default' => 'recent',
            'options' => [
                'recent'    => 'Plus récent',
                'reduc'     => 'Plus grosse réduction',
                'prix_asc'  => 'Prix croissant',
                'prix_desc' => 'Prix décroissant',
            ],
        ] );

        $this->add_control( 'agence', [
            'label'       => __( 'Filtrer par agence', 'sl-agences' ),
            'type'        => Controls_Manager::SELECT2,
            'multiple'    => true,
            'label_block' => true,
            'options'     => $this->sl_term_options( 'sl_agence_promo' ),
            'default'     => [],
            'description' => 'Vide = toutes les agences.',
        ] );

        $this->add_control( 'categorie', [
            'label'       => __( 'Filtrer par catégorie', 'sl-agences' ),
            'type'        => Controls_Manager::SELECT2,
            'multiple'    => true,
            'label_block' => true,
            'options'     => $this->sl_term_options( 'sl_categorie_promo' ),
            'default'     => [],
            'description' => 'Vide = toutes les catégories.',
        ] );

        $this->add_control( 'badge', [
            'label'       => __( 'Filtrer par badge', 'sl-agences' ),
            'type'        => Controls_Manager::SELECT2,
            'multiple'    => true,
            'label_block' => true,
            'options'     => [
                'flash'     => 'Flash',
                'top-vente' => 'Top Vente',
                'exclusif'  => 'Exclusif',
                'nouveau'   => 'Nouveau',
            ],
            'default'     => [],
            'description' => 'Vide = tous les badges. Ex : « Top Vente » uniquement.',
        ] );

        $this->end_controls_section();

        /* ── REGLAGES CARROUSEL ── */
        $this->start_controls_section( 'sec_carousel', [
            'label' => __( '⚙️ Réglages carrousel', 'sl-agences' ),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ] );

        $this->add_responsive_control( 'cols', [
            'label'          => __( 'Cartes visibles', 'sl-agences' ),
            'type'           => Controls_Manager::NUMBER,
            'min'            => 1, 'max' => 6,
            'default'        => 4,
            'tablet_default' => 2,
            'mobile_default' => 1,
        ] );

        $this->add_control( 'gap', [
            'label'   => __( 'Espacement entre cartes (px)', 'sl-agences' ),
            'type'    => Controls_Manager::NUMBER,
            'min'     => 0, 'max' => 40, 'default' => 16,
        ] );

        $this->add_control( 'fleches', [
            'label' => __( 'Flèches', 'sl-agences' ),
            'type'  => Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'yes',
        ] );

        $this->add_control( 'points', [
            'label' => __( 'Points (dots)', 'sl-agences' ),
            'type'  => Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'yes',
        ] );

        $this->add_control( 'boucle', [
            'label' => __( 'Boucle (revient au début)', 'sl-agences' ),
            'type'  => Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'yes',
        ] );

        $this->add_control( 'autoplay', [
            'label' => __( 'Défilement automatique', 'sl-agences' ),
            'type'  => Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'yes',
        ] );

        $this->add_control( 'vitesse', [
            'label'     => __( 'Vitesse auto (ms)', 'sl-agences' ),
            'type'      => Controls_Manager::NUMBER,
            'min'       => 1500, 'max' => 10000, 'step' => 500, 'default' => 4000,
            'condition' => [ 'autoplay' => 'yes' ],
        ] );

        $this->end_controls_section();

        /* ── STYLE ── */
        $this->start_controls_section( 'sec_style', [
            'label' => __( '🎨 Style', 'sl-agences' ),
            'tab'   => Controls_Manager::TAB_STYLE,
        ] );

        $this->add_control( 'accent', [
            'label'     => __( 'Couleur accent', 'sl-agences' ),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#E91E63',
            'selectors' => [
                '{{WRAPPER}} .slbp-prix-apres'      => 'color: {{VALUE}};',
                '{{WRAPPER}} .slbp-badge-reduc'     => 'background: {{VALUE}};',
                '{{WRAPPER}} .slbpc-arrow:hover'    => 'background: {{VALUE}}; border-color: {{VALUE}}; color: #fff;',
                '{{WRAPPER}} .slbpc-dot.is-active'  => 'background: {{VALUE}};',
                '{{WRAPPER}} .slbpc-head h2:after'  => 'background: {{VALUE}};',
            ],
        ] );

        $this->end_controls_section();
    }

    /* Rendu d'une carte (données unifiées : bon plan OU produit de campagne). */
    private function sl_render_card( $c ) {
        $img   = $c['img']   ?? '';
        $title = $c['title'] ?? '';
        ?>
        <a class="slbp-card" href="<?php echo esc_url( $c['url'] ?? '#' ); ?>">
            <div class="slbp-card-img-wrap">
                <?php if ( $img ) : ?>
                    <img src="<?php echo esc_url( $img ); ?>" alt="<?php echo esc_attr( $title ); ?>" loading="lazy">
                <?php else : ?>
                    <div class="slbp-no-img">🛒</div>
                <?php endif; ?>
                <?php if ( ! empty( $c['reduc'] ) && $c['reduc'] > 0 ) : ?>
                    <span class="slbp-badge-reduc">-<?php echo (int) $c['reduc']; ?>%</span>
                <?php endif; ?>
                <?php if ( ! empty( $c['badge'] ) ) : ?>
                    <span class="slbp-badge-type slbp-badge-<?php echo esc_attr( $c['badge'] ); ?>"><?php echo esc_html( $c['badge_label'] ?? '' ); ?></span>
                <?php endif; ?>
                <div class="slbp-eye-btn" title="Voir l'offre">👁</div>
            </div>
            <div class="slbp-card-body">
                <div class="slbp-card-meta">
                    <?php if ( ! empty( $c['agence'] ) ) : ?><span class="slbp-agence-tag"><?php echo esc_html( $c['agence'] ); ?></span><?php endif; ?>
                    <?php if ( ! empty( $c['cat'] ) ) : ?><span class="slbp-cat-tag"><?php echo esc_html( $c['cat'] ); ?></span><?php endif; ?>
                </div>
                <h3 class="slbp-titre"><?php echo esc_html( $title ); ?></h3>
                <div class="slbp-prix-wrap">
                    <?php if ( ! empty( $c['prix_ap'] ) && $c['prix_ap'] > 0 ) : ?><span class="slbp-prix-apres"><?php echo number_format( (float) $c['prix_ap'], 0, ',', ' ' ); ?> FCFA</span><?php endif; ?>
                    <?php if ( ! empty( $c['prix_av'] ) && $c['prix_av'] > 0 ) : ?><span class="slbp-prix-avant"><?php echo number_format( (float) $c['prix_av'], 0, ',', ' ' ); ?> FCFA</span><?php endif; ?>
                </div>
                <?php if ( ! empty( $c['date_fin'] ) ) : ?><p class="slbp-date-fin">Valable jusqu'au <?php echo date_i18n( 'd M Y', strtotime( $c['date_fin'] ) ); ?></p><?php endif; ?>
                <?php if ( ! empty( $c['stock'] ) ) : ?><p class="slbp-stock-mention">Dans la limite des stocks disponibles</p><?php endif; ?>
            </div>
            <?php if ( function_exists( 'sl_bp_cart_button_html' ) && ! empty( $c['cart_pid'] ) ) echo sl_bp_cart_button_html( 0, (int) $c['cart_pid'] ); ?>
        </a>
        <?php
    }

    /* ══════════════════════════════════════════ RENDER ══════════════════════════════════════════ */
    protected function render() {
        $s     = $this->get_settings_for_display();
        $today = current_time( 'Y-m-d' );
        $uid   = 'slbpc-' . $this->get_id();

        // Bons plans actifs (non expirés).
        $date_clause = [
            'relation' => 'OR',
            [ 'key' => '_sl_bp_date_fin', 'value' => $today, 'compare' => '>=', 'type' => 'DATE' ],
            [ 'key' => '_sl_bp_date_fin', 'value' => '', 'compare' => '=' ],
            [ 'key' => '_sl_bp_date_fin', 'compare' => 'NOT EXISTS' ],
        ];
        $meta_query = [ $date_clause ];

        // Filtre par badge (Top Vente / Exclusif / Flash / Nouveau).
        if ( ! empty( $s['badge'] ) ) {
            $meta_query[] = [ 'key' => '_sl_bp_badge_type', 'value' => (array) $s['badge'], 'compare' => 'IN' ];
        }
        if ( count( $meta_query ) > 1 ) { $meta_query['relation'] = 'AND'; }

        // ── Source des cartes : produits de la campagne active (mode « Priorité
        //    campagne » sur l'accueil), sinon bons plans actifs configurés. ──
        $limit        = max( 2, (int) $s['nombre'] );
        $use_campaign = ( isset( $s['mode_campagne'] ) && $s['mode_campagne'] === 'yes' && function_exists( 'sl_cwoo_get_active_campaign_product_ids' ) );
        $campaign_ids = $use_campaign ? array_values( (array) sl_cwoo_get_active_campaign_product_ids() ) : [];
        $is_campaign  = ! empty( $campaign_ids );

        $cards = [];

        if ( $is_campaign && function_exists( 'wc_get_product' ) ) {
            // Produits de la/les campagne(s) active(s).
            foreach ( array_slice( $campaign_ids, 0, $limit ) as $pid ) {
                $prod = wc_get_product( $pid );
                if ( ! $prod || $prod->get_status() !== 'publish' ) continue;
                $reg      = (float) $prod->get_regular_price();
                $sale     = (float) $prod->get_sale_price();
                $has_sale = ( $reg > 0 && $sale > 0 && $sale < $reg );
                $catn = '';
                $ct = get_the_terms( $pid, 'product_cat' );
                if ( $ct && ! is_wp_error( $ct ) ) {
                    foreach ( $ct as $tt ) { if ( ! get_term_meta( $tt->term_id, '_sl_cwoo_campaign_id', true ) ) { $catn = $tt->name; break; } }
                    if ( $catn === '' ) { $catn = $ct[0]->name; }
                }
                $cards[] = [
                    'url'     => get_permalink( $pid ),
                    'img'     => get_the_post_thumbnail_url( $pid, 'medium' ),
                    'title'   => $prod->get_name(),
                    'prix_ap' => $has_sale ? $sale : $reg,
                    'prix_av' => $has_sale ? $reg : 0,
                    'reduc'   => $has_sale ? (int) round( ( ( $reg - $sale ) / $reg ) * 100 ) : 0,
                    'badge'   => '', 'badge_label' => '', 'date_fin' => '', 'stock' => false,
                    'agence'  => '', 'cat' => $catn,
                    'cart_pid' => (int) $pid,
                ];
            }
        } else {
            // Bons plans actifs configurés (filtres agence / catégorie / badge + tri).
            $args = [
                'post_type'      => 'sl_bon_plan',
                'post_status'    => 'publish',
                'posts_per_page' => $limit,
                'meta_query'     => $meta_query,
            ];
            $tax_query = [];
            if ( ! empty( $s['agence'] ) )    { $tax_query[] = [ 'taxonomy' => 'sl_agence_promo', 'field' => 'slug', 'terms' => (array) $s['agence'] ]; }
            if ( ! empty( $s['categorie'] ) ) { $tax_query[] = [ 'taxonomy' => 'sl_categorie_promo', 'field' => 'slug', 'terms' => (array) $s['categorie'] ]; }
            if ( $tax_query ) { $tax_query['relation'] = 'AND'; $args['tax_query'] = $tax_query; }
            switch ( $s['tri'] ) {
                case 'reduc':     $args['meta_key'] = '_sl_bp_reduction_pct'; $args['orderby'] = 'meta_value_num'; $args['order'] = 'DESC'; break;
                case 'prix_asc':  $args['meta_key'] = '_sl_bp_prix_apres';    $args['orderby'] = 'meta_value_num'; $args['order'] = 'ASC';  break;
                case 'prix_desc': $args['meta_key'] = '_sl_bp_prix_apres';    $args['orderby'] = 'meta_value_num'; $args['order'] = 'DESC'; break;
                default:          $args['orderby'] = 'date'; $args['order'] = 'DESC';
            }
            $badge_labels = [ 'flash' => 'Flash', 'nouveau' => 'Nouveau', 'top-vente' => 'Top Vente', 'exclusif' => 'Exclusif' ];
            foreach ( get_posts( $args ) as $p ) {
                $stock_actif = get_post_meta( $p->ID, '_sl_bp_stock_actif', true );
                $stock_qty   = get_post_meta( $p->ID, '_sl_bp_stock_qty', true );
                if ( $stock_actif === '1' && $stock_qty !== '' && (int) $stock_qty <= 0 ) { continue; }
                $badge = get_post_meta( $p->ID, '_sl_bp_badge_type', true );
                $ctb = wp_get_object_terms( $p->ID, 'sl_categorie_promo' ); if ( is_wp_error( $ctb ) ) $ctb = [];
                $atb = wp_get_object_terms( $p->ID, 'sl_agence_promo' );    if ( is_wp_error( $atb ) ) $atb = [];
                $cards[] = [
                    'url'         => get_permalink( $p->ID ),
                    'img'         => get_the_post_thumbnail_url( $p->ID, 'medium' ),
                    'title'       => $p->post_title,
                    'prix_ap'     => (float) get_post_meta( $p->ID, '_sl_bp_prix_apres', true ),
                    'prix_av'     => (float) get_post_meta( $p->ID, '_sl_bp_prix_avant', true ),
                    'reduc'       => (int) get_post_meta( $p->ID, '_sl_bp_reduction_pct', true ),
                    'badge'       => $badge,
                    'badge_label' => $badge_labels[ $badge ] ?? ucfirst( str_replace( '-', ' ', (string) $badge ) ),
                    'date_fin'    => get_post_meta( $p->ID, '_sl_bp_date_fin', true ),
                    'stock'       => ( $stock_actif === '1' ),
                    'agence'      => ! empty( $atb ) ? $atb[0]->name : '',
                    'cat'         => ! empty( $ctb ) ? $ctb[0]->name : '',
                    'cart_pid'    => function_exists( 'sl_bp_product_id_for' ) ? sl_bp_product_id_for( $p->ID ) : 0,
                ];
            }
        }

        $is_edit = class_exists( '\Elementor\Plugin' ) && \Elementor\Plugin::$instance->editor->is_edit_mode();
        if ( empty( $cards ) ) {
            if ( $is_edit ) {
                echo '<div style="padding:26px;text-align:center;color:#888;border:1px dashed #ddd;border-radius:10px;">🎠 Carrousel : aucune offre à afficher pour l\'instant (ni campagne active, ni bon plan). Elles apparaîtront ici automatiquement.</div>';
            }
            return;
        }

        $c_d = max( 1, (int) ( $s['cols']        !== '' ? $s['cols']        : 4 ) );
        $c_t = max( 1, (int) ( isset($s['cols_tablet']) && $s['cols_tablet'] !== '' ? $s['cols_tablet'] : 2 ) );
        $c_m = max( 1, (int) ( isset($s['cols_mobile']) && $s['cols_mobile'] !== '' ? $s['cols_mobile'] : 1 ) );
        $gap = max( 0, (int) $s['gap'] );
        ?>
        <style>
        #<?php echo $uid; ?>{--cols:<?php echo $c_d; ?>;--gap:<?php echo $gap; ?>px;position:relative;}
        @media (max-width:1024px){#<?php echo $uid; ?>{--cols:<?php echo $c_t; ?>;}}
        @media (max-width:600px){#<?php echo $uid; ?>{--cols:<?php echo $c_m; ?>;}}
        #<?php echo $uid; ?> .slbpc-head{text-align:center;margin:0 0 14px;}
        #<?php echo $uid; ?> .slbpc-head h2{font-size:26px;font-weight:800;margin:0 0 6px;color:#1d2327;display:inline-block;position:relative;padding-bottom:8px;}
        #<?php echo $uid; ?> .slbpc-head h2:after{content:"";position:absolute;left:50%;transform:translateX(-50%);bottom:0;width:52px;height:3px;border-radius:2px;background:#E91E63;}
        #<?php echo $uid; ?> .slbpc-head p{margin:0;color:#6b7178;font-size:14px;}
        #<?php echo $uid; ?> .slbpc-viewport{position:relative;}
        #<?php echo $uid; ?> .slbpc-track{display:flex;gap:var(--gap);overflow-x:auto;scroll-snap-type:x mandatory;scroll-behavior:smooth;-webkit-overflow-scrolling:touch;padding:6px 2px 14px;scrollbar-width:none;}
        #<?php echo $uid; ?> .slbpc-track::-webkit-scrollbar{display:none;}
        #<?php echo $uid; ?> .slbpc-track>.slbp-card{flex:0 0 calc((100% - (var(--cols) - 1) * var(--gap)) / var(--cols));scroll-snap-align:start;min-width:0;}
        #<?php echo $uid; ?> .slbpc-arrow{position:absolute;top:42%;transform:translateY(-50%);z-index:6;width:42px;height:42px;border-radius:50%;border:1px solid #e7e7e7;background:#fff;box-shadow:0 5px 16px rgba(0,0,0,.14);cursor:pointer;font-size:20px;line-height:1;color:#333;display:flex;align-items:center;justify-content:center;transition:.2s;}
        #<?php echo $uid; ?> .slbpc-prev{left:-6px;}
        #<?php echo $uid; ?> .slbpc-next{right:-6px;}
        #<?php echo $uid; ?> .slbpc-arrow:disabled{opacity:.32;cursor:default;box-shadow:none;}
        #<?php echo $uid; ?> .slbpc-dots{display:flex;gap:6px;justify-content:center;margin-top:6px;flex-wrap:wrap;}
        #<?php echo $uid; ?> .slbpc-dot{width:8px;height:8px;border-radius:50%;border:none;background:#dcdcdc;cursor:pointer;padding:0;transition:.2s;}
        #<?php echo $uid; ?> .slbpc-dot.is-active{background:#E91E63;width:22px;border-radius:4px;}
        @media (max-width:600px){#<?php echo $uid; ?> .slbpc-arrow{display:none;}}
        </style>

        <div class="slbpc" id="<?php echo esc_attr( $uid ); ?>"
             data-autoplay="<?php echo $s['autoplay'] === 'yes' ? '1' : '0'; ?>"
             data-speed="<?php echo (int) $s['vitesse']; ?>"
             data-loop="<?php echo $s['boucle'] === 'yes' ? '1' : '0'; ?>">

            <?php if ( ! empty( $s['titre'] ) || ! empty( $s['sous_titre'] ) ) : ?>
            <div class="slbpc-head">
                <?php if ( ! empty( $s['titre'] ) ) : ?><h2><?php echo esc_html( $s['titre'] ); ?></h2><?php endif; ?>
                <?php if ( ! empty( $s['sous_titre'] ) ) : ?><p><?php echo esc_html( $s['sous_titre'] ); ?></p><?php endif; ?>
            </div>
            <?php endif; ?>

            <div class="slbpc-viewport">
                <?php if ( $s['fleches'] === 'yes' ) : ?>
                <button type="button" class="slbpc-arrow slbpc-prev" aria-label="Précédent">&#8249;</button>
                <?php endif; ?>

                <div class="slbpc-track">
                    <?php foreach ( $cards as $card ) { $this->sl_render_card( $card ); } ?>
                </div>

                <?php if ( $s['fleches'] === 'yes' ) : ?>
                <button type="button" class="slbpc-arrow slbpc-next" aria-label="Suivant">&#8250;</button>
                <?php endif; ?>
            </div>

            <?php if ( $s['points'] === 'yes' ) : ?>
            <div class="slbpc-dots" aria-hidden="true"></div>
            <?php endif; ?>
        </div>

        <script>
        (function(){
            var root = document.getElementById('<?php echo esc_js( $uid ); ?>');
            if ( ! root || root.dataset.slbpcInit ) return;
            root.dataset.slbpcInit = '1';
            var track = root.querySelector('.slbpc-track');
            if ( ! track ) return;
            var prev  = root.querySelector('.slbpc-prev');
            var next  = root.querySelector('.slbpc-next');
            var dots  = root.querySelector('.slbpc-dots');
            var cards = Array.prototype.slice.call(track.querySelectorAll('.slbp-card'));

            function step(){ var c = cards[0]; if(!c) return track.clientWidth; var w = c.getBoundingClientRect().width; var g = parseFloat(getComputedStyle(track).gap)||0; return w+g; }
            function atEnd(){ return track.scrollLeft >= (track.scrollWidth - track.clientWidth - 2); }
            function atStart(){ return track.scrollLeft <= 2; }

            function go(dir){
                if ( dir > 0 && atEnd() ) { if ( root.dataset.loop==='1' ) { track.scrollTo({left:0,behavior:'smooth'}); return; } }
                if ( dir < 0 && atStart() ) { if ( root.dataset.loop==='1' ) { track.scrollTo({left:track.scrollWidth,behavior:'smooth'}); return; } }
                track.scrollBy({ left: dir * step() * Math.max(1, Math.floor(track.clientWidth/step())), behavior:'smooth' });
            }
            if (next) next.addEventListener('click', function(){ go(1); });
            if (prev) prev.addEventListener('click', function(){ go(-1); });

            // Points : un par carte
            var dotEls = [];
            if ( dots ) {
                cards.forEach(function(c, i){
                    var b = document.createElement('button');
                    b.className = 'slbpc-dot'; b.type = 'button'; b.setAttribute('aria-label','Aller à l\'offre '+(i+1));
                    b.addEventListener('click', function(){ track.scrollTo({ left: i*step(), behavior:'smooth' }); });
                    dots.appendChild(b); dotEls.push(b);
                });
            }
            function update(){
                if (prev) prev.disabled = atStart() && root.dataset.loop!=='1';
                if (next) next.disabled = atEnd()   && root.dataset.loop!=='1';
                if (dotEls.length){
                    var idx = Math.round(track.scrollLeft / step());
                    dotEls.forEach(function(d,i){ d.classList.toggle('is-active', i===idx); });
                }
            }
            var raf; track.addEventListener('scroll', function(){ cancelAnimationFrame(raf); raf = requestAnimationFrame(update); });
            window.addEventListener('resize', update);
            update();

            // Autoplay (pause au survol / interaction)
            if ( root.dataset.autoplay === '1' ){
                var speed = parseInt(root.dataset.speed,10) || 4000, timer = null;
                function play(){ stop(); timer = setInterval(function(){ go(1); }, speed); }
                function stop(){ if (timer){ clearInterval(timer); timer = null; } }
                root.addEventListener('mouseenter', stop);
                root.addEventListener('mouseleave', play);
                track.addEventListener('touchstart', stop, {passive:true});
                play();
            }
        })();
        </script>
        <?php
    }
}
