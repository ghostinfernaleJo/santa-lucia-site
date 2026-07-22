<?php
/**
 * Page de resultats de recherche unifiee.
 * La requete principale ($wp_query) a deja ete elargie par sl_search_broaden_query()
 * a : product + sl_bon_plan + sl_repas + post (proxys synchronises exclus).
 */
if ( ! defined( 'ABSPATH' ) ) exit;

get_header();

$terme = get_search_query();

// Repartition des resultats par type.
$buckets = [ 'product' => [], 'sl_bon_plan' => [], 'sl_repas' => [], 'post' => [] ];
if ( have_posts() ) {
    while ( have_posts() ) {
        the_post();
        $pt = get_post_type();
        if ( isset( $buckets[ $pt ] ) ) {
            $buckets[ $pt ][] = get_the_ID();
        }
    }
}
wp_reset_postdata();

$total = array_sum( array_map( 'count', $buckets ) );

/** Vignette d'un post (repli placeholder). */
$img_of = function ( $id, $is_repas = false ) {
    if ( $is_repas && function_exists( 'sl_ff_item_image_url' ) ) {
        $u = sl_ff_item_image_url( $id, 'medium' );
        if ( $u ) return $u;
    }
    $u = get_the_post_thumbnail_url( $id, 'medium' );
    return $u ?: '';
};

/** Rend une grille de cartes. $items = [ [url,img,title,meta,price], ... ] */
$render_grid = function ( $items ) {
    echo '<div class="slsr-grid">';
    foreach ( $items as $it ) {
        echo '<a class="slsr-card" href="' . esc_url( $it['url'] ) . '">';
        if ( $it['img'] ) {
            echo '<span class="slsr-card-img" style="background-image:url(\'' . esc_url( $it['img'] ) . '\')"></span>';
        } else {
            echo '<span class="slsr-card-img slsr-card-img--empty">🛒</span>';
        }
        echo '<span class="slsr-card-body">';
        if ( ! empty( $it['meta'] ) ) {
            echo '<span class="slsr-card-meta">' . esc_html( $it['meta'] ) . '</span>';
        }
        echo '<span class="slsr-card-title">' . esc_html( $it['title'] ) . '</span>';
        if ( ! empty( $it['price'] ) ) {
            echo '<span class="slsr-card-price">' . wp_kses_post( $it['price'] ) . '</span>';
        }
        echo '</span></a>';
    }
    echo '</div>';
};
?>
<style>
.slsr-wrap{max-width:1180px;margin:40px auto;padding:0 20px;font-family:'Inter',sans-serif;}
.slsr-head{margin-bottom:26px;}
.slsr-head h1{font-size:1.9rem;font-weight:800;color:#222;margin:0 0 6px;}
.slsr-head p{color:#777;margin:0;font-size:15px;}
.slsr-section{margin:0 0 40px;}
.slsr-section-title{display:flex;align-items:center;gap:10px;font-size:1.15rem;font-weight:800;color:#E91E63;margin:0 0 16px;padding-bottom:8px;border-bottom:2px solid #f2e2ea;}
.slsr-section-title .slsr-count{font-size:13px;font-weight:600;color:#999;background:#f6f6f6;border-radius:999px;padding:2px 10px;}
.slsr-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:16px;}
.slsr-card{display:flex;flex-direction:column;background:#fff;border:1px solid #eee;border-radius:12px;overflow:hidden;text-decoration:none;color:inherit;transition:.15s;box-shadow:0 2px 8px rgba(0,0,0,.03);}
.slsr-card:hover{transform:translateY(-2px);box-shadow:0 8px 20px rgba(0,0,0,.08);border-color:#f0cfe0;}
.slsr-card-img{display:block;height:150px;background-size:cover;background-position:center;background-color:#f7f7f7;}
.slsr-card-img--empty{display:flex;align-items:center;justify-content:center;font-size:2.4rem;color:#ccc;}
.slsr-card-body{display:flex;flex-direction:column;gap:4px;padding:12px 14px 14px;}
.slsr-card-meta{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.03em;color:#999;}
.slsr-card-title{font-size:14px;font-weight:700;color:#222;line-height:1.3;}
.slsr-card-price{font-size:14px;font-weight:800;color:#E91E63;margin-top:2px;}
.slsr-card-price del{color:#aaa;font-weight:500;font-size:12px;margin-left:6px;}
.slsr-empty{text-align:center;padding:60px 20px;color:#666;}
.slsr-empty a{color:#E91E63;font-weight:700;}
@media(max-width:600px){.slsr-grid{grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:12px;}.slsr-card-img{height:120px;}}
</style>

<div class="slsr-wrap">
    <div class="slsr-head">
        <h1>Résultats de recherche</h1>
        <?php if ( $terme !== '' ) : ?>
            <p><strong><?php echo (int) $total; ?></strong> résultat<?php echo $total > 1 ? 's' : ''; ?> pour « <strong><?php echo esc_html( $terme ); ?></strong> »</p>
        <?php endif; ?>
    </div>

    <?php if ( $total === 0 ) : ?>
        <div class="slsr-empty">
            <p style="font-size:18px;margin-bottom:8px;">Aucun résultat pour « <?php echo esc_html( $terme ); ?> ».</p>
            <p>Parcourez plutôt nos <a href="<?php echo esc_url( home_url( '/bon-plans/' ) ); ?>">Bons Plans</a>
               ou notre <a href="<?php echo esc_url( home_url( '/menu-fast-food/' ) ); ?>">Menu Fast Food</a>.</p>
        </div>
    <?php else : ?>

        <?php
        /* ---- PRODUITS ---- */
        if ( ! empty( $buckets['product'] ) && function_exists( 'wc_get_product' ) ) :
            $items = [];
            foreach ( $buckets['product'] as $id ) {
                $p = wc_get_product( $id );
                if ( ! $p ) continue;
                $items[] = [
                    'url'   => get_permalink( $id ),
                    'img'   => $img_of( $id ),
                    'meta'  => 'Produit',
                    'title' => $p->get_name(),
                    'price' => $p->get_price_html(),
                ];
            }
            if ( $items ) : ?>
                <div class="slsr-section">
                    <div class="slsr-section-title">🛒 Produits <span class="slsr-count"><?php echo count( $items ); ?></span></div>
                    <?php $render_grid( $items ); ?>
                </div>
            <?php endif;
        endif;

        /* ---- BONS PLANS ---- */
        if ( ! empty( $buckets['sl_bon_plan'] ) ) :
            $items = [];
            foreach ( $buckets['sl_bon_plan'] as $id ) {
                $avant = (float) get_post_meta( $id, '_sl_bp_prix_avant', true );
                $apres = (float) get_post_meta( $id, '_sl_bp_prix_apres', true );
                $price = '';
                if ( $apres > 0 ) {
                    $price = '<strong>' . esc_html( number_format( $apres, 0, ',', ' ' ) ) . ' FCFA</strong>';
                    if ( $avant > 0 ) {
                        $price .= '<del>' . esc_html( number_format( $avant, 0, ',', ' ' ) ) . ' FCFA</del>';
                    }
                }
                $a_terms = wp_get_object_terms( $id, 'sl_agence_promo' );
                $agence  = ( ! empty( $a_terms ) && ! is_wp_error( $a_terms ) ) ? $a_terms[0]->name : 'Bon Plan';
                $items[] = [
                    'url'   => get_permalink( $id ),
                    'img'   => $img_of( $id ),
                    'meta'  => $agence,
                    'title' => get_the_title( $id ),
                    'price' => $price,
                ];
            }
            if ( $items ) : ?>
                <div class="slsr-section">
                    <div class="slsr-section-title">🏷️ Bons Plans <span class="slsr-count"><?php echo count( $items ); ?></span></div>
                    <?php $render_grid( $items ); ?>
                </div>
            <?php endif;
        endif;

        /* ---- MENU FAST FOOD ---- */
        if ( ! empty( $buckets['sl_repas'] ) ) :
            $items = [];
            foreach ( $buckets['sl_repas'] as $id ) {
                $price = function_exists( 'sl_search_repas_price_html' ) ? sl_search_repas_price_html( $id ) : '';
                $items[] = [
                    'url'   => function_exists( 'sl_search_repas_link' ) ? sl_search_repas_link( $id ) : home_url( '/menu-fast-food/' ),
                    'img'   => $img_of( $id, true ),
                    'meta'  => 'Menu du jour',
                    'title' => get_the_title( $id ),
                    'price' => $price ? '<strong>' . esc_html( $price ) . '</strong>' : '',
                ];
            }
            if ( $items ) : ?>
                <div class="slsr-section">
                    <div class="slsr-section-title">🍽️ Menu Fast Food <span class="slsr-count"><?php echo count( $items ); ?></span></div>
                    <?php $render_grid( $items ); ?>
                </div>
            <?php endif;
        endif;

        /* ---- ACTUALITES ---- */
        if ( ! empty( $buckets['post'] ) ) :
            $items = [];
            foreach ( $buckets['post'] as $id ) {
                $items[] = [
                    'url'   => get_permalink( $id ),
                    'img'   => $img_of( $id ),
                    'meta'  => 'Actualité',
                    'title' => get_the_title( $id ),
                    'price' => '',
                ];
            }
            if ( $items ) : ?>
                <div class="slsr-section">
                    <div class="slsr-section-title">📰 Actualités <span class="slsr-count"><?php echo count( $items ); ?></span></div>
                    <?php $render_grid( $items ); ?>
                </div>
            <?php endif;
        endif;
        ?>

    <?php endif; ?>
</div>

<?php
get_footer();
