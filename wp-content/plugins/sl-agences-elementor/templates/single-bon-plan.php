<?php
/**
 * Modèle pour afficher un Bon Plan (Single Page)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

get_header();

// Récupération des données du bon plan
$p_id        = get_the_ID();
$prix_av     = (float) get_post_meta( $p_id, '_sl_bp_prix_avant', true );
$prix_ap     = (float) get_post_meta( $p_id, '_sl_bp_prix_apres', true );
$reduc       = (int)   get_post_meta( $p_id, '_sl_bp_reduction_pct', true );
$badge       = get_post_meta( $p_id, '_sl_bp_badge_type', true );
$badge_labels = [
    'flash'     => 'Flash',
    'nouveau'   => 'Nouveau',
    'top-vente' => 'Top Vente',
    'exclusif'  => 'Exclusif',
];
$badge_label = $badge_labels[ $badge ] ?? ucfirst( str_replace( '-', ' ', $badge ) );
$date_fin    = get_post_meta( $p_id, '_sl_bp_date_fin', true );
$img_url     = get_the_post_thumbnail_url( $p_id, 'large' );

$c_terms     = wp_get_object_terms( $p_id, 'sl_categorie_promo' );
$cat_name    = ! empty( $c_terms ) && ! is_wp_error( $c_terms ) ? $c_terms[0]->name : '';

$a_terms     = wp_get_object_terms( $p_id, 'sl_agence_promo' );
$agence_name = ! empty( $a_terms ) && ! is_wp_error( $a_terms ) ? $a_terms[0]->name : '';

// Lien actuel pour le partage
$current_url = get_permalink();
$phone = '237674152010';
$text_wa = "Bonjour, je suis intéressé par ce Bon Plan :\n" . get_the_title() . "\n";
$text_wa .= "Prix : " . number_format( $prix_ap, 0, ',', ' ' ) . " FCFA\n";
$text_wa .= "Lien : " . $current_url;
$whatsapp_url = "https://wa.me/{$phone}?text=" . urlencode($text_wa);

?>
<div class="slbp-single-wrapper" style="max-width: 1000px; margin: 40px auto; padding: 0 20px; font-family: 'Inter', sans-serif;">
    
    <!-- Bouton retour -->
    <div style="margin-bottom: 20px;">
        <a href="javascript:history.back()" style="display: inline-flex; align-items: center; gap: 8px; color: #555; text-decoration: none; font-weight: 600;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
            Retour aux offres
        </a>
    </div>

    <div class="slbp-single-layout" style="display: grid; grid-template-columns: 1fr 1fr; gap: 40px; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.06); border: 1px solid #eee;">
        
        <!-- Image -->
        <div class="slbp-single-img" style="position: relative; background: #f9f9f9; display: flex; align-items: center; justify-content: center; min-height: 400px;">
            <?php if ( $img_url ) : ?>
                <img src="<?php echo esc_url( $img_url ); ?>" alt="<?php the_title_attribute(); ?>" style="width: 100%; height: auto; display: block; object-fit: contain; max-height: 500px;">
            <?php else : ?>
                <div style="font-size: 5rem; color: #ccc;">🛒</div>
            <?php endif; ?>
            
            <?php if ( $reduc > 0 ) : ?>
                <div style="position: absolute; top: 20px; left: 20px; background: #E91E63; color: #fff; width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 16px; font-weight: 800; box-shadow: 0 4px 10px rgba(233,30,99,0.3);">
                    -<?php echo $reduc; ?>%
                </div>
            <?php endif; ?>

            <?php if ( $badge ) : ?>
                <div style="position: absolute; top: 20px; right: 20px; background: #222; color: #fff; padding: 8px 14px; border-radius: 999px; font-size: 12px; font-weight: 800; text-transform: uppercase; box-shadow: 0 4px 10px rgba(0,0,0,0.18);">
                    <?php echo esc_html( $badge_label ); ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Détails -->
        <div class="slbp-single-content" style="padding: 40px 40px 40px 0; display: flex; flex-direction: column; justify-content: center;">
            
            <div style="display: flex; gap: 10px; margin-bottom: 15px;">
                <?php if ( $agence_name ) : ?>
                    <span style="background: #f0f0f0; color: #555; padding: 4px 10px; border-radius: 4px; font-size: 12px; font-weight: 600;">📍 <?php echo esc_html( $agence_name ); ?></span>
                <?php endif; ?>
                <?php if ( $cat_name ) : ?>
                    <span style="background: #fff0f5; color: #E91E63; padding: 4px 10px; border-radius: 4px; font-size: 12px; font-weight: 600;">🏷️ <?php echo esc_html( $cat_name ); ?></span>
                <?php endif; ?>
            </div>

            <h1 style="margin: 0 0 20px; font-size: 2.2rem; font-weight: 800; color: #222; line-height: 1.2;">
                <?php the_title(); ?>
            </h1>

            <div style="display: flex; align-items: baseline; gap: 15px; margin-bottom: 25px;">
                <span style="font-size: 2rem; font-weight: 800; color: #E91E63;">
                    <?php echo number_format( $prix_ap, 0, ',', ' ' ); ?> FCFA
                </span>
                <?php if ( $prix_av > 0 ) : ?>
                    <span style="font-size: 1.2rem; color: #999; text-decoration: line-through; font-weight: 500;">
                        <?php echo number_format( $prix_av, 0, ',', ' ' ); ?> FCFA
                    </span>
                <?php endif; ?>
            </div>

            <?php if ( $date_fin ) : ?>
                <div style="margin-bottom: 30px; padding: 15px; background: #fff9e6; border-left: 4px solid #ffc107; border-radius: 4px; color: #856404; font-weight: 600;">
                    ⏳ Offre valable jusqu'au <?php echo date_i18n( 'd F Y', strtotime( $date_fin ) ); ?>
                </div>
            <?php endif; ?>

            <div style="margin-top: auto; padding-top: 30px; border-top: 1px solid #eee;">
                <p style="margin: 0 0 15px; font-weight: 600; font-size: 14px; color: #555;">Partager ce bon plan :</p>
                <div style="display: flex; gap: 10px;">
                    
                    <!-- WhatsApp -->
                    <a href="<?php echo esc_url( $whatsapp_url ); ?>" target="_blank" rel="noopener" style="flex: 1; display: flex; align-items: center; justify-content: center; gap: 8px; background: #25D366; color: #fff; text-decoration: none; padding: 12px; border-radius: 6px; font-weight: 700; font-size: 15px; transition: opacity 0.2s;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 0 0-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413Z"/></svg>
                        Commander sur WhatsApp
                    </a>
                    
                    <!-- Partager (Natif Web Share) -->
                    <button type="button" onclick="slbpShareProduct()" style="flex: 1; display: flex; align-items: center; justify-content: center; gap: 8px; background: #222; color: #fff; border: none; cursor: pointer; padding: 12px; border-radius: 6px; font-weight: 700; font-size: 15px; font-family: inherit; transition: opacity 0.2s;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"></circle><circle cx="6" cy="12" r="3"></circle><circle cx="18" cy="19" r="3"></circle><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line></svg>
                        Partager
                    </button>

                </div>
            </div>
            
        </div>
    </div>
</div>

<script>
function slbpShareProduct() {
    if (navigator.share) {
        navigator.share({
            title: '<?php echo esc_js( get_the_title() ); ?>',
            text: 'Découvrez ce bon plan exclusif chez Complexe Santa Lucia : <?php echo esc_js( get_the_title() ); ?>',
            url: '<?php echo esc_url( $current_url ); ?>'
        }).catch(function(error) {
            console.log('Erreur de partage', error);
        });
    } else {
        // Fallback: copie du lien
        navigator.clipboard.writeText('<?php echo esc_url( $current_url ); ?>').then(function() {
            alert('Lien copié dans le presse-papier !');
        });
    }
}
</script>

<style>
/* Responsive Single Page */
@media (max-width: 768px) {
    .slbp-single-layout {
        grid-template-columns: 1fr !important;
        gap: 0 !important;
    }
    .slbp-single-content {
        padding: 30px 20px !important;
    }
    .slbp-single-img {
        min-height: 300px !important;
    }
}
.slbp-single-wrapper a:hover { opacity: 0.85; }
</style>
<?php
get_footer();
