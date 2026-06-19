<?php
/**
 * Page d'administration de Lucie : cles API, base de connaissances, reglages.
 * Accessible aux administrateurs WP et aux editeurs (edit_others_posts).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function sl_lucie_can_manage() {
    return current_user_can( 'manage_options' ) || current_user_can( 'edit_others_posts' );
}

add_action( 'admin_menu', function () {
    if ( ! sl_lucie_can_manage() ) return;
    add_menu_page( 'Lucie (IA)', 'Lucie (IA)', 'edit_others_posts', 'sl-lucie', 'sl_lucie_admin_page', 'dashicons-format-chat', 27 );
} );

function sl_lucie_admin_page() {
    if ( ! sl_lucie_can_manage() ) wp_die( 'Acces refuse.' );
    $msg = '';
    $err = '';

    /* ---- Sauvegarde des reglages generaux ---- */
    if ( isset( $_POST['sl_lucie_save_settings'] ) ) {
        check_admin_referer( 'sl_lucie_settings' );
        update_option( 'sl_lucie_enabled', isset( $_POST['enabled'] ) ? '1' : '0' );
        update_option( 'sl_lucie_scope_guard', isset( $_POST['scope_guard'] ) ? '1' : '0' );
        update_option( 'sl_lucie_nom', sanitize_text_field( $_POST['nom'] ?? 'Lucie' ) );
        update_option( 'sl_lucie_message_accueil', sanitize_textarea_field( $_POST['accueil'] ?? '' ) );
        $msg = 'Reglages enregistres.';
    }

    /* ---- Sauvegarde des cles API ---- */
    if ( isset( $_POST['sl_lucie_save_keys'] ) ) {
        check_admin_referer( 'sl_lucie_keys' );
        $raw  = (string) ( $_POST['api_keys'] ?? '' );
        $list = array_filter( array_map( 'trim', preg_split( '/[\r\n,]+/', $raw ) ) );
        update_option( 'sl_lucie_api_keys', array_values( $list ), false );
        update_option( 'sl_lucie_key_cursor', 0, false );
        $msg = count( $list ) . ' cle(s) API enregistree(s).';
    }

    /* ---- Base de connaissances : ajout de texte ---- */
    if ( isset( $_POST['sl_lucie_add_text'] ) ) {
        check_admin_referer( 'sl_lucie_kb' );
        $titre = sanitize_text_field( $_POST['kb_titre'] ?? 'Document' );
        $texte = (string) wp_unslash( $_POST['kb_texte'] ?? '' );
        if ( trim( $texte ) === '' ) { $err = 'Le texte est vide.'; }
        else { sl_lucie_kb_append( $titre, sanitize_textarea_field( $texte ) ); $msg = 'Texte ajoute a la base de connaissances.'; }
    }

    /* ---- Base de connaissances : import PDF ---- */
    if ( isset( $_POST['sl_lucie_add_pdf'] ) ) {
        check_admin_referer( 'sl_lucie_kb' );
        if ( empty( $_FILES['kb_pdf']['tmp_name'] ) ) {
            $err = 'Aucun PDF selectionne.';
        } else {
            $name = sanitize_file_name( $_FILES['kb_pdf']['name'] ?? 'document.pdf' );
            $ext  = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
            if ( $ext !== 'pdf' ) {
                $err = 'Merci de fournir un fichier PDF.';
            } else {
                $r = sl_lucie_kb_extract_pdf( $_FILES['kb_pdf']['tmp_name'], $name );
                if ( $r['ok'] ) { sl_lucie_kb_append( $name, $r['text'] ); $msg = 'PDF importe : texte extrait et ajoute (' . number_format( strlen( $r['text'] ) ) . ' caracteres).'; }
                else { $err = $r['error']; }
            }
        }
    }

    /* ---- Base de connaissances : vider ---- */
    if ( isset( $_POST['sl_lucie_clear_kb'] ) ) {
        check_admin_referer( 'sl_lucie_kb' );
        sl_lucie_kb_set( '' );
        $msg = 'Base de connaissances videe.';
    }

    $keys    = sl_lucie_get_keys();
    $kb      = sl_lucie_kb_get();
    $const   = defined( 'SL_LUCIE_API_KEYS' );
    ?>
    <div class="wrap">
        <h1><span class="dashicons dashicons-format-chat" style="font-size:28px;"></span> Lucie — Assistant IA</h1>
        <?php if ( $msg ) : ?><div class="notice notice-success is-dismissible"><p><?php echo esc_html( $msg ); ?></p></div><?php endif; ?>
        <?php if ( $err ) : ?><div class="notice notice-error is-dismissible"><p><?php echo esc_html( $err ); ?></p></div><?php endif; ?>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;max-width:1100px;align-items:start;">

            <!-- Reglages -->
            <div class="card" style="padding:18px;">
                <h2 style="margin-top:0;">Reglages</h2>
                <form method="post">
                    <?php wp_nonce_field( 'sl_lucie_settings' ); ?>
                    <p><label><input type="checkbox" name="enabled" <?php checked( get_option( 'sl_lucie_enabled', '1' ), '1' ); ?>> <strong>Activer Lucie sur le site</strong></label></p>
                    <p><label><input type="checkbox" name="scope_guard" <?php checked( get_option( 'sl_lucie_scope_guard', '1' ), '1' ); ?>> Garde de perimetre (ne jamais repondre hors-sujet) — <em>recommande</em></label></p>
                    <p><label><strong>Nom de l'assistante</strong><br><input type="text" name="nom" class="regular-text" value="<?php echo esc_attr( get_option( 'sl_lucie_nom', 'Lucie' ) ); ?>"></label></p>
                    <p><label><strong>Message d'accueil</strong><br><textarea name="accueil" rows="3" class="large-text"><?php echo esc_textarea( get_option( 'sl_lucie_message_accueil', '' ) ); ?></textarea></label></p>
                    <p><button class="button button-primary" name="sl_lucie_save_settings">Enregistrer</button></p>
                </form>
            </div>

            <!-- Cles API -->
            <div class="card" style="padding:18px;">
                <h2 style="margin-top:0;">Cles API Anthropic</h2>
                <?php if ( $const ) : ?>
                    <p><em>Les cles sont definies dans <code>wp-config.php</code> (constante <code>SL_LUCIE_API_KEYS</code>). Edition desactivee ici pour plus de securite.</em></p>
                    <p><strong><?php echo count( $keys ); ?></strong> cle(s) chargee(s).</p>
                <?php else : ?>
                    <p style="color:#555;">Une cle par ligne. En cas de limite ou d'erreur sur une cle, Lucie bascule automatiquement sur la suivante.</p>
                    <form method="post">
                        <?php wp_nonce_field( 'sl_lucie_keys' ); ?>
                        <textarea name="api_keys" rows="4" class="large-text" placeholder="sk-ant-...&#10;sk-ant-..."><?php echo esc_textarea( implode( "\n", $keys ) ); ?></textarea>
                        <p style="color:#888;font-size:12px;">Astuce securite : vous pouvez aussi les definir dans wp-config.php via <code>define('SL_LUCIE_API_KEYS', 'cle1,cle2');</code></p>
                        <p><button class="button button-primary" name="sl_lucie_save_keys">Enregistrer les cles</button></p>
                    </form>
                <?php endif; ?>
                <p><?php echo sl_lucie_has_key() ? '✅ Au moins une cle est active.' : '⚠️ Aucune cle : Lucie ne peut pas repondre.'; ?></p>
            </div>

            <!-- Base de connaissances -->
            <div class="card" style="padding:18px;grid-column:1 / -1;">
                <h2 style="margin-top:0;">Base de connaissances</h2>
                <p style="color:#555;">Ajoutez ici les <strong>faits stables</strong> que Lucie doit connaitre (histoire, recrutement, produits phares/maison, horaires, FAQ). Les menus et promotions, eux, sont lus en direct — inutile de les mettre ici.</p>
                <p>Contenu actuel : <strong><?php echo number_format( strlen( $kb ) ); ?></strong> caracteres <?php if ( strlen( $kb ) > 80000 ) echo '<span style="color:#b32d2e;">(volumineux : pensez a alleger)</span>'; ?>.</p>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;">
                    <form method="post">
                        <?php wp_nonce_field( 'sl_lucie_kb' ); ?>
                        <h3>Ajouter du texte</h3>
                        <p><input type="text" name="kb_titre" class="regular-text" placeholder="Titre (ex: Recrutement)"></p>
                        <p><textarea name="kb_texte" rows="6" class="large-text" placeholder="Collez le texte..."></textarea></p>
                        <p><button class="button button-primary" name="sl_lucie_add_text">Ajouter le texte</button></p>
                    </form>

                    <form method="post" enctype="multipart/form-data">
                        <?php wp_nonce_field( 'sl_lucie_kb' ); ?>
                        <h3>Importer un PDF</h3>
                        <p style="color:#888;font-size:12px;">PDF avec du vrai texte (pas un scan image). Le texte est extrait automatiquement.</p>
                        <p><input type="file" name="kb_pdf" accept="application/pdf"></p>
                        <p><button class="button button-primary" name="sl_lucie_add_pdf">Importer le PDF</button></p>
                    </form>
                </div>

                <?php if ( trim( $kb ) !== '' ) : ?>
                <hr>
                <details>
                    <summary style="cursor:pointer;font-weight:600;">Voir / verifier le contenu actuel</summary>
                    <pre style="white-space:pre-wrap;max-height:300px;overflow:auto;background:#f6f7f7;padding:12px;border-radius:6px;"><?php echo esc_html( mb_substr( $kb, 0, 8000 ) ); ?><?php echo strlen( $kb ) > 8000 ? "\n[...]" : ''; ?></pre>
                </details>
                <form method="post" onsubmit="return confirm('Vider toute la base de connaissances ?');" style="margin-top:10px;">
                    <?php wp_nonce_field( 'sl_lucie_kb' ); ?>
                    <button class="button button-link-delete" name="sl_lucie_clear_kb">Vider la base de connaissances</button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}
