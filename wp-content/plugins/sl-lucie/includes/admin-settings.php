<?php
/**
 * Page d'administration de Lucie : cles API, base de connaissances, reglages.
 * Accessible aux administrateurs WP et aux editeurs (edit_others_posts).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function sl_lucie_can_manage() {
    return current_user_can( 'manage_options' ) || current_user_can( 'edit_others_posts' );
}

/* ============================================================
   INTERRUPTEUR RAPIDE ON/OFF (page + barre d'admin)
   ============================================================ */
add_action( 'admin_init', function () {
    if ( ! isset( $_GET['sl_lucie_toggle'] ) ) return;
    if ( ! sl_lucie_can_manage() ) return;
    if ( ! wp_verify_nonce( $_GET['_slnonce'] ?? '', 'sl_lucie_toggle' ) ) return;
    update_option( 'sl_lucie_enabled', get_option( 'sl_lucie_enabled', '1' ) === '1' ? '0' : '1' );
    wp_safe_redirect( remove_query_arg( [ 'sl_lucie_toggle', '_slnonce' ], wp_get_referer() ?: admin_url( 'admin.php?page=sl-lucie' ) ) );
    exit;
} );

/** Lien (avec nonce) qui bascule l'etat. */
function sl_lucie_toggle_url() {
    return wp_nonce_url( add_query_arg( 'sl_lucie_toggle', '1' ), 'sl_lucie_toggle', '_slnonce' );
}

/** Raccourci ON/OFF dans la barre d'admin (toutes pages). */
add_action( 'admin_bar_menu', function ( $bar ) {
    if ( ! sl_lucie_can_manage() ) return;
    $on = get_option( 'sl_lucie_enabled', '1' ) === '1';
    $bar->add_node( [
        'id'    => 'sl-lucie-toggle',
        'title' => 'Lucie : ' . ( $on ? '🟢 ON' : '🔴 OFF' ),
        'href'  => esc_url( sl_lucie_toggle_url() ),
        'meta'  => [ 'title' => $on ? 'Cliquer pour desactiver Lucie' : 'Cliquer pour activer Lucie' ],
    ] );
}, 100 );

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
        update_option( 'sl_lucie_provider', ( ( $_POST['provider'] ?? '' ) === 'google' ) ? 'google' : 'anthropic' );
        update_option( 'sl_lucie_nom', sanitize_text_field( wp_unslash( $_POST['nom'] ?? 'Lucie' ) ) );
        update_option( 'sl_lucie_message_accueil', sanitize_textarea_field( wp_unslash( $_POST['accueil'] ?? '' ) ) );
        // Planning horaire
        update_option( 'sl_lucie_schedule_enabled', isset( $_POST['schedule_enabled'] ) ? '1' : '0' );
        $st = preg_match( '/^\d{2}:\d{2}$/', $_POST['schedule_start'] ?? '' ) ? $_POST['schedule_start'] : '08:00';
        $en = preg_match( '/^\d{2}:\d{2}$/', $_POST['schedule_end'] ?? '' )   ? $_POST['schedule_end']   : '20:00';
        update_option( 'sl_lucie_schedule_start', $st );
        update_option( 'sl_lucie_schedule_end', $en );
        $days = array_values( array_intersect( array_map( 'strval', (array) ( $_POST['schedule_days'] ?? [] ) ), [ '0','1','2','3','4','5','6' ] ) );
        if ( empty( $days ) ) $days = [ '0','1','2','3','4','5','6' ];
        update_option( 'sl_lucie_schedule_days', $days );
        update_option( 'sl_lucie_offline_message', sanitize_textarea_field( wp_unslash( $_POST['offline_message'] ?? '' ) ) );
        // Numero WhatsApp du call center (chiffres uniquement, format international)
        update_option( 'sl_lucie_whatsapp', preg_replace( '/\D/', '', (string) ( $_POST['whatsapp'] ?? '' ) ) );
        $msg = 'Reglages enregistres.';
    }

    /* ---- Sauvegarde des cles Google (Gemini) ---- */
    if ( isset( $_POST['sl_lucie_save_gkeys'] ) ) {
        check_admin_referer( 'sl_lucie_gkeys' );
        $raw  = (string) ( $_POST['google_keys'] ?? '' );
        $list = array_filter( array_map( 'trim', preg_split( '/[\r\n,]+/', $raw ) ) );
        update_option( 'sl_lucie_google_keys', array_values( $list ), false );
        update_option( 'sl_lucie_google_model', sanitize_text_field( $_POST['google_model'] ?? 'gemini-2.5-flash' ) );
        update_option( 'sl_lucie_gkey_cursor', 0, false );
        $msg = count( $list ) . ' cle(s) Google enregistree(s).';
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

    /* ---- Base de connaissances : edition directe du contenu complet ---- */
    if ( isset( $_POST['sl_lucie_save_kb_full'] ) ) {
        check_admin_referer( 'sl_lucie_kb' );
        sl_lucie_kb_set( sanitize_textarea_field( (string) wp_unslash( $_POST['kb_full'] ?? '' ) ) );
        $msg = 'Base de connaissances mise a jour.';
    }

    $keys     = sl_lucie_get_keys();
    $gkeys    = sl_lucie_google_get_keys();
    $kb       = sl_lucie_kb_get();
    $const    = defined( 'SL_LUCIE_API_KEYS' );
    $gconst   = defined( 'SL_LUCIE_GOOGLE_KEYS' );
    $provider = sl_lucie_provider();
    ?>
    <div class="wrap">
        <style>
            /* La classe WP .card impose max-width:520px et casse les cartes pleine largeur. */
            .wrap .card { max-width: none; box-sizing: border-box; }
            .wrap .card textarea, .wrap .card input[type=text] { max-width: 100%; box-sizing: border-box; }
        </style>
        <h1><span class="dashicons dashicons-format-chat" style="font-size:28px;"></span> Lucie — Assistant IA</h1>

        <?php $on = get_option( 'sl_lucie_enabled', '1' ) === '1'; ?>
        <div style="display:flex;align-items:center;gap:16px;background:#fff;border:1px solid #e5e7eb;border-left:6px solid <?php echo $on ? '#2e7d32' : '#b32d2e'; ?>;border-radius:10px;padding:14px 18px;margin:14px 0;max-width:1100px;">
            <div style="font-size:16px;">
                <strong>Lucie est <?php echo $on ? 'ACTIVÉE 🟢' : 'DÉSACTIVÉE 🔴'; ?></strong>
                <?php if ( $on && ! sl_lucie_is_active_now() ) : ?>
                    <br><span style="color:#b35900;font-size:13px;">⏰ Hors des horaires programmés : la bulle est masquée pour le moment.</span>
                <?php endif; ?>
            </div>
            <a href="<?php echo esc_url( sl_lucie_toggle_url() ); ?>" class="button button-hero <?php echo $on ? '' : 'button-primary'; ?>" style="margin-left:auto;<?php echo $on ? 'background:#b32d2e;border-color:#a02222;color:#fff;' : ''; ?>">
                <?php echo $on ? 'Désactiver Lucie' : 'Activer Lucie'; ?>
            </a>
        </div>
        <?php if ( $msg ) : ?><div class="notice notice-success is-dismissible"><p><?php echo esc_html( $msg ); ?></p></div><?php endif; ?>
        <?php if ( $err ) : ?><div class="notice notice-error is-dismissible"><p><?php echo esc_html( $err ); ?></p></div><?php endif; ?>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(440px,1fr));gap:24px;max-width:1100px;align-items:start;">

            <!-- Reglages -->
            <div class="card" style="padding:18px;">
                <h2 style="margin-top:0;">Reglages</h2>
                <form method="post">
                    <?php wp_nonce_field( 'sl_lucie_settings' ); ?>
                    <p><label><input type="checkbox" name="enabled" <?php checked( get_option( 'sl_lucie_enabled', '1' ), '1' ); ?>> <strong>Activer Lucie sur le site</strong></label></p>
                    <p><label><input type="checkbox" name="scope_guard" <?php checked( get_option( 'sl_lucie_scope_guard', '1' ), '1' ); ?>> Garde de perimetre (ne jamais repondre hors-sujet) — <em>recommande</em></label></p>
                    <p><strong>Fournisseur d'IA</strong><br>
                       <label style="margin-right:16px;"><input type="radio" name="provider" value="anthropic" <?php checked( $provider, 'anthropic' ); ?>> Anthropic (Claude)</label>
                       <label><input type="radio" name="provider" value="google" <?php checked( $provider, 'google' ); ?>> Google (Gemini)</label>
                    </p>
                    <p><label><strong>Nom de l'assistante</strong><br><input type="text" name="nom" class="regular-text" value="<?php echo esc_attr( get_option( 'sl_lucie_nom', 'Lucie' ) ); ?>"></label></p>
                    <p><label><strong>Message d'accueil</strong><br><textarea name="accueil" rows="3" class="large-text"><?php echo esc_textarea( get_option( 'sl_lucie_message_accueil', '' ) ); ?></textarea></label></p>
                    <p><label><strong>WhatsApp du call center</strong><br><input type="text" name="whatsapp" class="regular-text" value="<?php echo esc_attr( get_option( 'sl_lucie_whatsapp', '' ) ); ?>" placeholder="237674152010"><br><span class="description">Chiffres uniquement, format international (indicatif pays compris). Utilise pour orienter vers un humain : https://wa.me/&lt;numero&gt;.</span></label></p>

                    <hr>
                    <p><label><input type="checkbox" name="schedule_enabled" <?php checked( get_option( 'sl_lucie_schedule_enabled', '0' ), '1' ); ?>> <strong>Programmer les horaires de disponibilite</strong></label></p>
                    <?php $sched_days = (array) get_option( 'sl_lucie_schedule_days', [ '0','1','2','3','4','5','6' ] ); ?>
                    <p>
                        De <input type="time" name="schedule_start" value="<?php echo esc_attr( get_option( 'sl_lucie_schedule_start', '08:00' ) ); ?>">
                        a <input type="time" name="schedule_end" value="<?php echo esc_attr( get_option( 'sl_lucie_schedule_end', '20:00' ) ); ?>">
                        <span style="color:#888;font-size:12px;">(fuseau du site ; fin avant debut = passe minuit)</span>
                    </p>
                    <p><strong>Jours</strong> :
                        <?php foreach ( [ '1'=>'Lun','2'=>'Mar','3'=>'Mer','4'=>'Jeu','5'=>'Ven','6'=>'Sam','0'=>'Dim' ] as $dv => $dl ) : ?>
                        <label style="margin-right:10px;"><input type="checkbox" name="schedule_days[]" value="<?php echo $dv; ?>" <?php checked( in_array( $dv, array_map( 'strval', $sched_days ), true ) ); ?>> <?php echo $dl; ?></label>
                        <?php endforeach; ?>
                    </p>
                    <p><label><strong>Message hors horaires</strong><br><textarea name="offline_message" rows="2" class="large-text" placeholder="Je ne suis pas disponible pour le moment..."><?php echo esc_textarea( get_option( 'sl_lucie_offline_message', '' ) ); ?></textarea></label></p>
                    <p style="color:#555;font-size:13px;">Etat actuel : <strong><?php echo sl_lucie_is_active_now() ? '🟢 Lucie est active maintenant' : '🔴 Lucie est hors service maintenant'; ?></strong></p>

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
                <p><?php echo sl_lucie_has_key() ? '✅ Au moins une cle Anthropic active.' : 'Aucune cle Anthropic.'; ?></p>
            </div>

            <!-- Cles Google (Gemini) -->
            <div class="card" style="padding:18px;">
                <h2 style="margin-top:0;">Cles API Google (Gemini)</h2>
                <?php if ( $gconst ) : ?>
                    <p><em>Definies dans <code>wp-config.php</code> (<code>SL_LUCIE_GOOGLE_KEYS</code>). Edition desactivee ici.</em></p>
                    <p><strong><?php echo count( $gkeys ); ?></strong> cle(s) chargee(s).</p>
                <?php else : ?>
                    <p style="color:#555;">Une cle par ligne. Basculement automatique en cas d'erreur, comme pour Anthropic.</p>
                    <form method="post">
                        <?php wp_nonce_field( 'sl_lucie_gkeys' ); ?>
                        <textarea name="google_keys" rows="4" class="large-text" placeholder="AIza...&#10;AIza..."><?php echo esc_textarea( implode( "\n", $gkeys ) ); ?></textarea>
                        <p><label><strong>Modele Gemini</strong> <input type="text" name="google_model" class="regular-text" value="<?php echo esc_attr( get_option( 'sl_lucie_google_model', 'gemini-2.5-flash' ) ); ?>"></label></p>
                        <p style="color:#888;font-size:12px;">Securite : possible aussi via <code>define('SL_LUCIE_GOOGLE_KEYS', 'cle1,cle2');</code> dans wp-config.php.</p>
                        <p><button class="button button-primary" name="sl_lucie_save_gkeys">Enregistrer les cles Google</button></p>
                    </form>
                <?php endif; ?>
                <p><strong>Fournisseur actif : <?php echo $provider === 'google' ? 'Google (Gemini)' : 'Anthropic (Claude)'; ?></strong> — <?php echo sl_lucie_provider_has_key() ? '✅ pret' : '⚠️ aucune cle pour ce fournisseur'; ?></p>
            </div>

            <!-- Base de connaissances -->
            <div class="card" style="padding:18px;grid-column:1 / -1;">
                <h2 style="margin-top:0;">Base de connaissances</h2>
                <p style="color:#555;">Ajoutez ici les <strong>faits stables</strong> que Lucie doit connaitre (histoire, recrutement, produits phares/maison, horaires, FAQ). Les menus et promotions, eux, sont lus en direct — inutile de les mettre ici.</p>
                <p>Contenu actuel : <strong><?php echo number_format( strlen( $kb ) ); ?></strong> caracteres <?php if ( strlen( $kb ) > 80000 ) echo '<span style="color:#b32d2e;">(volumineux : pensez a alleger)</span>'; ?>.</p>

                <form method="post" style="margin:0 0 22px;">
                    <?php wp_nonce_field( 'sl_lucie_kb' ); ?>
                    <h3 style="margin:0 0 6px;">📝 Contenu actuel — modifiable directement</h3>
                    <p style="color:#777;margin:0 0 6px;">Lisez, corrigez ou completez ici tout ce que Lucie sait. Ecrivez en clair (ex. « Recrutement : envoyez votre CV a ... », « Politique de retour : ... », « Quand on demande X, oriente vers Y »). Enregistrez pour appliquer immediatement.</p>
                    <textarea name="kb_full" rows="14" class="large-text" style="font-family:monospace;font-size:13px;"><?php echo esc_textarea( $kb ); ?></textarea>
                    <p><button class="button button-primary" name="sl_lucie_save_kb_full">💾 Enregistrer le contenu</button></p>
                </form>

                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:18px;">
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
