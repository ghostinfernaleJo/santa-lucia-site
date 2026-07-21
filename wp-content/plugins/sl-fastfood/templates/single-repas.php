<?php
/**
 * Fiche "Commander ce repas" — page virtuelle par (repas, agence).
 * Variables reçues de sl_ff_maybe_render_order_page() : $repas (WP_Post), $agence (slug).
 */
if ( ! defined( 'ABSPATH' ) ) exit;

get_header();

$today_jour  = sl_ff_today_jour();
$agence_term = get_term_by( 'slug', $agence, 'sl_agence_promo' );
$agence_nom  = ( $agence_term && ! is_wp_error( $agence_term ) ) ? sl_ff_agency_name( $agence_term->name ) : $agence;

$cats     = wp_get_post_terms( $repas->ID, 'sl_repas_cat' );
$cat_name = ( ! empty( $cats ) && ! is_wp_error( $cats ) ) ? sl_ff_cat_display( $cats[0]->name ) : '';

$thumb = function_exists( 'sl_ff_item_image_url' ) ? sl_ff_item_image_url( $repas->ID, 'large' ) : get_the_post_thumbnail_url( $repas->ID, 'large' );
$desc  = wp_trim_words( $repas->post_content, 30 );

$promo   = sl_ff_get_promo_info( $repas->ID, $agence );
$dispo   = sl_ff_is_repas_available_for_agence( $repas->ID, $agence, $today_jour );
$ff_pid  = sl_ff_product_id_for( $repas->ID, $agence );

$order_url = sl_ff_order_url( $repas->ID, $agence );
?>
<div class="slff-single-wrapper" style="max-width:1000px;margin:40px auto;padding:0 20px;font-family:'Inter',sans-serif;">

    <div style="margin-bottom:20px;">
        <a href="<?php echo esc_url( home_url( '/menu-fast-food/?agence=' . rawurlencode( $agence ) ) ); ?>" style="display:inline-flex;align-items:center;gap:8px;color:#555;text-decoration:none;font-weight:600;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
            Retour au menu
        </a>
    </div>

    <div class="slff-single-layout" style="display:grid;grid-template-columns:1fr 1fr;gap:40px;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.06);border:1px solid #eee;">

        <div class="slff-single-img" style="position:relative;background:#f9f9f9;display:flex;align-items:center;justify-content:center;min-height:360px;">
            <?php if ( $thumb ) : ?>
                <img src="<?php echo esc_url( $thumb ); ?>" alt="<?php echo esc_attr( $repas->post_title ); ?>" style="width:100%;height:auto;display:block;object-fit:contain;max-height:460px;">
            <?php else : ?>
                <div style="font-size:5rem;color:#ccc;">&#127869;</div>
            <?php endif; ?>
            <?php if ( $promo['est_promo'] && $promo['pct_reduction'] > 0 ) : ?>
                <div style="position:absolute;top:20px;left:20px;background:#E91E63;color:#fff;width:56px;height:56px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:15px;font-weight:800;box-shadow:0 4px 10px rgba(233,30,99,0.3);">
                    -<?php echo (int) $promo['pct_reduction']; ?>%
                </div>
            <?php endif; ?>
        </div>

        <div class="slff-single-content" style="padding:40px 40px 40px 0;display:flex;flex-direction:column;justify-content:center;">

            <div style="display:flex;gap:10px;margin-bottom:15px;flex-wrap:wrap;">
                <span style="background:#f0f0f0;color:#555;padding:4px 10px;border-radius:4px;font-size:12px;font-weight:600;">&#128205; <?php echo esc_html( $agence_nom ); ?></span>
                <?php if ( $cat_name ) : ?>
                <span style="background:#fff0f5;color:#E91E63;padding:4px 10px;border-radius:4px;font-size:12px;font-weight:600;">&#127991; <?php echo esc_html( $cat_name ); ?></span>
                <?php endif; ?>
            </div>

            <h1 style="margin:0 0 20px;font-size:2rem;font-weight:800;color:#222;line-height:1.2;">
                <?php echo esc_html( $repas->post_title ); ?>
            </h1>

            <?php if ( $desc ) : ?>
            <p style="color:#666;font-size:14px;line-height:1.6;margin:0 0 20px;"><?php echo esc_html( $desc ); ?></p>
            <?php endif; ?>

            <?php if ( $promo['prix'] > 0 || $promo['prix_promo'] > 0 ) : ?>
            <div style="display:flex;align-items:baseline;gap:15px;margin-bottom:25px;">
                <?php if ( $promo['est_promo'] && $promo['prix_promo'] > 0 ) : ?>
                    <span style="font-size:1.8rem;font-weight:800;color:#E91E63;"><?php echo esc_html( sl_ff_format_prix( $promo['prix_promo'] ) ); ?></span>
                    <?php if ( $promo['prix'] > 0 ) : ?>
                    <span style="font-size:1.1rem;color:#999;text-decoration:line-through;font-weight:500;"><?php echo esc_html( sl_ff_format_prix( $promo['prix'] ) ); ?></span>
                    <?php endif; ?>
                <?php else : ?>
                    <span style="font-size:1.8rem;font-weight:800;color:#1d2327;"><?php echo esc_html( sl_ff_format_prix( $promo['prix'] ) ); ?></span>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if ( ! $dispo || ! $ff_pid ) : ?>
                <div style="padding:15px;background:#fdecea;border-left:4px solid #e74c3c;border-radius:4px;color:#7a1f1a;font-weight:600;">
                    Ce repas n'est plus disponible aujourd'hui pour l'agence <?php echo esc_html( $agence_nom ); ?>.
                    <a href="<?php echo esc_url( home_url( '/menu-fast-food/?agence=' . rawurlencode( $agence ) ) ); ?>" style="color:#7a1f1a;">Voir le menu du jour</a>.
                </div>
            <?php else : ?>
                <div style="padding-top:20px;border-top:1px solid #eee;">
                    <div style="display:flex;gap:10px;align-items:center;margin-bottom:14px;">
                        <label for="slff-qty" style="font-weight:600;font-size:14px;color:#555;">Quantité</label>
                        <div style="display:flex;align-items:center;border:1px solid #ddd;border-radius:6px;overflow:hidden;">
                            <button type="button" id="slff-qty-minus" style="width:34px;height:34px;border:none;background:#f5f5f5;cursor:pointer;font-size:16px;font-weight:700;">−</button>
                            <input type="number" id="slff-qty" value="1" min="1" step="1" style="width:50px;height:34px;border:none;border-left:1px solid #ddd;border-right:1px solid #ddd;text-align:center;font-size:14px;">
                            <button type="button" id="slff-qty-plus" style="width:34px;height:34px;border:none;background:#f5f5f5;cursor:pointer;font-size:16px;font-weight:700;">+</button>
                        </div>
                    </div>

                    <div style="display:flex;gap:10px;flex-wrap:wrap;">
                        <button type="button" id="slff-add-cart" data-pid="<?php echo esc_attr( $ff_pid ); ?>"
                                style="flex:1;min-width:180px;display:flex;align-items:center;justify-content:center;gap:8px;background:#E91E63;color:#fff;border:none;cursor:pointer;padding:13px;border-radius:6px;font-weight:700;font-size:15px;font-family:inherit;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                            <span id="slff-add-cart-label">Ajouter au panier</span>
                        </button>

                        <a href="<?php echo esc_attr( sl_ff_whatsapp_url(
                            $repas->post_title,
                            ( $promo['est_promo'] && $promo['prix_promo'] > 0 ) ? sl_ff_format_prix( $promo['prix_promo'] ) : sl_ff_format_prix( $promo['prix'] ),
                            $agence_nom,
                            $order_url
                        ) ); // esc_attr, pas esc_url : esc_url() supprime %0a/%0A (retours a la ligne encodes), voir fastfood-cart.php ?>" target="_blank" rel="noopener"
                           style="flex:1;min-width:180px;display:flex;align-items:center;justify-content:center;gap:8px;background:#25D366;color:#fff;text-decoration:none;padding:13px;border-radius:6px;font-weight:700;font-size:15px;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 0 0-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413Z"/></svg>
                            Commander sur WhatsApp
                        </a>
                    </div>
                    <p id="slff-add-cart-msg" style="margin:10px 0 0;font-size:13px;color:#666;"></p>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<script>
(function(){
    var qtyInput = document.getElementById('slff-qty');
    var minus    = document.getElementById('slff-qty-minus');
    var plus     = document.getElementById('slff-qty-plus');
    var btn      = document.getElementById('slff-add-cart');
    var label    = document.getElementById('slff-add-cart-label');
    var msg      = document.getElementById('slff-add-cart-msg');
    if ( ! btn ) return;

    if ( minus ) minus.addEventListener('click', function(){
        var v = Math.max(1, (parseInt(qtyInput.value, 10) || 1) - 1);
        qtyInput.value = v;
    });
    if ( plus ) plus.addEventListener('click', function(){
        var v = Math.max(1, (parseInt(qtyInput.value, 10) || 1) + 1);
        qtyInput.value = v;
    });

    var ENDPOINT = '<?php echo esc_js( class_exists( 'WC_AJAX' ) ? WC_AJAX::get_endpoint( 'sl_bp_add' ) : '' ); ?>';

    btn.addEventListener('click', function(){
        if ( ! ENDPOINT || btn.disabled ) return;
        var qty = Math.max(1, parseInt(qtyInput.value, 10) || 1);
        btn.disabled = true;
        label.textContent = 'Ajout…';
        msg.textContent = '';
        msg.style.color = '#666';

        var body = 'product_id=' + encodeURIComponent(btn.getAttribute('data-pid')) + '&qty=' + encodeURIComponent(qty);
        fetch(ENDPOINT, { method: 'POST', credentials: 'same-origin', headers: {'Content-Type':'application/x-www-form-urlencoded'}, body: body })
            .then(function(r){ return r.json(); })
            .then(function(res){
                btn.disabled = false;
                if ( ! res || ! res.ok ) {
                    label.textContent = 'Ajouter au panier';
                    msg.style.color = '#b32d2e';
                    msg.textContent = ( res && res.msg ) ? res.msg : "Impossible d'ajouter au panier.";
                    return;
                }
                label.textContent = 'Ajouter au panier';
                msg.style.color = '#16a34a';
                msg.textContent = '✓ ' + qty + ' ajouté(s) au panier.';
                if ( window.jQuery && res.fragments ) {
                    jQuery(document.body).trigger('added_to_cart', [res.fragments, res.cart_hash, jQuery(btn)]);
                }
            })
            .catch(function(){
                btn.disabled = false;
                label.textContent = 'Ajouter au panier';
                msg.style.color = '#b32d2e';
                msg.textContent = 'Erreur réseau, réessayez.';
            });
    });
})();
</script>

<style>
@media (max-width: 768px) {
    .slff-single-layout { grid-template-columns: 1fr !important; gap: 0 !important; }
    .slff-single-content { padding: 30px 20px !important; }
    .slff-single-img { min-height: 260px !important; }
}
</style>
<?php
get_footer();
