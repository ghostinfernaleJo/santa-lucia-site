<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'admin_menu', 'sl_cwoo_add_ai_settings_menu', 40 );
function sl_cwoo_add_ai_settings_menu() {
    if ( current_user_can( 'manage_options' ) ) {
        add_submenu_page(
            'edit.php?post_type=sl_campagne_woo',
            'Réglages IA',
            'Réglages IA',
            'manage_options',
            'sl-cwoo-settings-ai',
            'sl_cwoo_render_ai_settings'
        );
    }
}

function sl_cwoo_render_ai_settings() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    if ( ! function_exists( 'sl_ai_providers' ) ) {
        echo '<div class="wrap"><h1>Réglages IA</h1><div class="notice notice-error"><p>Couche IA (ai-providers.php) introuvable.</p></div></div>';
        return;
    }

    $providers = sl_ai_providers();
    $notice    = null;

    if ( isset( $_POST['sl_cwoo_ai_action'] ) && check_admin_referer( 'sl_cwoo_ai_settings' ) ) {
        $action = sanitize_text_field( wp_unslash( $_POST['sl_cwoo_ai_action'] ) );
        $prov   = isset( $_POST['sl_provider'] ) ? sanitize_text_field( wp_unslash( $_POST['sl_provider'] ) ) : '';
        $valid  = isset( $providers[ $prov ] );

        if ( $action === 'set_provider' && $valid ) {
            update_option( 'sl_ai_provider', $prov );
            $notice = [ 'type' => 'success', 'msg' => 'Fournisseur actif : ' . $providers[ $prov ]['label'] . '.' ];

        } elseif ( $action === 'delete_key' && $valid ) {
            delete_option( $providers[ $prov ]['key_option'] );
            $notice = [ 'type' => 'success', 'msg' => 'Clé ' . $providers[ $prov ]['label'] . ' supprimée.' ];

        } elseif ( $action === 'test_key' && $valid ) {
            $res = sl_ai_test_key( $prov, sl_ai_get_key( $prov ) );
            $notice = [ 'type' => $res['ok'] ? 'success' : 'error', 'msg' => $providers[ $prov ]['label'] . ' : ' . ( $res['ok'] ? '✓ ' . $res['msg'] : 'échec — ' . $res['msg'] ) ];

        } elseif ( $action === 'save_key' && $valid ) {
            $new = isset( $_POST['sl_api_key_new'] ) ? trim( (string) wp_unslash( $_POST['sl_api_key_new'] ) ) : '';
            if ( $new === '' ) {
                $notice = [ 'type' => 'error', 'msg' => 'Veuillez saisir une clé.' ];
            } else {
                $res = sl_ai_test_key( $prov, $new );
                update_option( $providers[ $prov ]['key_option'], $new );
                $notice = $res['ok']
                    ? [ 'type' => 'success', 'msg' => 'Clé ' . $providers[ $prov ]['label'] . ' enregistrée et validée. ' . $res['msg'] ]
                    : [ 'type' => 'warning', 'msg' => 'Clé ' . $providers[ $prov ]['label'] . ' enregistrée, mais le test a échoué : ' . $res['msg'] ];
            }
        }
    }

    $active = sl_ai_get_provider();
    ?>
    <div class="wrap">
        <h1>Configuration de l'Intelligence Artificielle</h1>
        <p class="description" style="max-width:720px;">L'« Import Magique » peut utiliser plusieurs fournisseurs d'IA. Choisissez le fournisseur actif et renseignez la clé correspondante. Pour la sécurité, les clés existantes ne sont jamais ré-affichées.</p>

        <?php if ( $notice ) : ?>
            <div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible"><p><?php echo esc_html( $notice['msg'] ); ?></p></div>
        <?php endif; ?>

        <!-- Fournisseur actif -->
        <div style="background:#fff; padding:20px; border:1px solid #ccd0d4; border-radius:6px; max-width:680px; margin-top:18px;">
            <h2 style="margin-top:0; font-size:15px;">Fournisseur actif</h2>
            <form method="post" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                <?php wp_nonce_field( 'sl_cwoo_ai_settings' ); ?>
                <input type="hidden" name="sl_cwoo_ai_action" value="set_provider">
                <select name="sl_provider">
                    <?php foreach ( $providers as $pid => $p ) : ?>
                        <option value="<?php echo esc_attr( $pid ); ?>" <?php selected( $pid, $active ); ?>>
                            <?php echo esc_html( $p['label'] ); ?><?php echo sl_ai_get_key( $pid ) ? '' : ' (clé manquante)'; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="button button-primary">Définir comme actif</button>
            </form>
        </div>

        <!-- Clé par fournisseur -->
        <?php foreach ( $providers as $pid => $p ) :
            $has = ( sl_ai_get_key( $pid ) !== '' );
            $is_active = ( $pid === $active );
        ?>
        <div style="background:#fff; padding:20px; border:1px solid <?php echo $is_active ? '#2271b1' : '#ccd0d4'; ?>; border-radius:6px; max-width:680px; margin-top:16px;">
            <h2 style="margin-top:0; font-size:15px;">
                <?php echo esc_html( $p['label'] ); ?>
                <?php if ( $is_active ) : ?><span style="font-size:11px; background:#2271b1; color:#fff; padding:2px 8px; border-radius:10px; vertical-align:middle;">ACTIF</span><?php endif; ?>
            </h2>
            <p style="padding:8px 12px; border-radius:6px; background:<?php echo $has ? '#edfaef' : '#fcf0f1'; ?>; margin:0 0 12px;">
                <?php echo $has ? '✅ Clé enregistrée.' : '⚠️ Aucune clé.'; ?>
                &nbsp;<small>Modèle : <code><?php echo esc_html( sl_ai_get_model( $pid ) ); ?></code></small>
            </p>
            <form method="post" style="margin:0 0 8px;">
                <?php wp_nonce_field( 'sl_cwoo_ai_settings' ); ?>
                <input type="hidden" name="sl_cwoo_ai_action" value="save_key">
                <input type="hidden" name="sl_provider" value="<?php echo esc_attr( $pid ); ?>">
                <input type="password" name="sl_api_key_new" value="" autocomplete="off"
                       placeholder="<?php echo $has ? 'Nouvelle clé (laisser vide = conserver)…' : 'Collez votre clé ici…'; ?>"
                       style="width:100%; max-width:420px;">
                <button type="submit" class="button button-primary"><?php echo $has ? 'Changer la clé' : 'Enregistrer la clé'; ?></button>
                <a href="<?php echo esc_url( $p['key_help'] ); ?>" target="_blank" rel="noopener" class="button">Obtenir une clé ↗</a>
            </form>
            <?php if ( $has ) : ?>
            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                <form method="post" style="margin:0;">
                    <?php wp_nonce_field( 'sl_cwoo_ai_settings' ); ?>
                    <input type="hidden" name="sl_cwoo_ai_action" value="test_key">
                    <input type="hidden" name="sl_provider" value="<?php echo esc_attr( $pid ); ?>">
                    <button type="submit" class="button">🔌 Tester</button>
                </form>
                <form method="post" style="margin:0;" onsubmit="return confirm('Supprimer la clé <?php echo esc_js( $p['label'] ); ?> ?');">
                    <?php wp_nonce_field( 'sl_cwoo_ai_settings' ); ?>
                    <input type="hidden" name="sl_cwoo_ai_action" value="delete_key">
                    <input type="hidden" name="sl_provider" value="<?php echo esc_attr( $pid ); ?>">
                    <button type="submit" class="button button-link-delete" style="color:#b32d2e;">🗑️ Supprimer</button>
                </form>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php
}
