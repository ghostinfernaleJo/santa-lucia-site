<?php
/**
 * PWA — manifeste, icones, balises et bouton d'installation.
 *
 * Les fichiers racine (manifest.json, sw.js, offline.html) sont versionnes
 * dans le depot et deployes a la racine web par le workflow FTP. Ce module
 * fournit le reste :
 *  - icones carrees generees via GD depuis le logo du site (le logo est
 *    horizontal 284x68 : ecrase dans un carre il serait illisible -> on le
 *    compose sur un fond degrade aux couleurs de la charte, comme le bandeau
 *    des PDF), mises en cache dans uploads/slpwa/ ;
 *  - balises <head> (manifest, theme-color, apple-touch-icon...) ;
 *  - bouton « Installer l'application » : natif sur Android/Chrome
 *    (beforeinstallprompt), mode d'emploi sur iPhone (Apple n'expose aucun
 *    declencheur programmatique).
 *
 * @package SL_Collect
 */

defined( 'ABSPATH' ) || exit;

/**
 * Genere (une fois) les icones PWA dans uploads/slpwa/.
 * @return string URL du dossier, ou '' si generation impossible.
 */
function slpwa_icons_dir_url() {
    $up  = wp_get_upload_dir();
    $dir = path_join( $up['basedir'], 'slpwa' );
    $url = $up['baseurl'] . '/slpwa';

    if ( get_option( 'slpwa_icons_ver' ) === '1' && file_exists( $dir . '/icon-512.png' ) ) {
        return $url;
    }
    if ( ! function_exists( 'imagecreatetruecolor' ) ) {
        return '';
    }

    // Meme source que les PDF : logo du customizer, sinon le chemin connu.
    $candidates = [];
    $logo_id = (int) get_theme_mod( 'custom_logo' );
    if ( $logo_id ) {
        $f = get_attached_file( $logo_id );
        if ( $f ) {
            $candidates[] = $f;
        }
    }
    $candidates[] = path_join( $up['basedir'], '2024/06/logo-santa-1.png' );
    $src = '';
    foreach ( $candidates as $c ) {
        if ( $c && file_exists( $c ) ) { $src = $c; break; }
    }
    if ( ! $src || ! wp_mkdir_p( $dir ) ) {
        return '';
    }
    $logo = @imagecreatefrompng( $src );
    if ( ! $logo ) {
        return '';
    }
    $lw = imagesx( $logo );
    $lh = imagesy( $logo );

    // [nom, taille, largeur du logo en fraction du carre]
    // maskable : zone sure = 60 % central (Android decoupe les bords).
    $specs = [
        [ 'icon-192.png',     192, 0.78 ],
        [ 'icon-512.png',     512, 0.78 ],
        [ 'apple-180.png',    180, 0.78 ],
        [ 'maskable-512.png', 512, 0.58 ],
    ];
    foreach ( $specs as $spec ) {
        list( $name, $size, $frac ) = $spec;
        $img = imagecreatetruecolor( $size, $size );
        // Degrade horizontal bleu Santa Lucia -> magenta (charte des PDF).
        for ( $x = 0; $x < $size; $x++ ) {
            $t = $x / max( 1, $size - 1 );
            $col = imagecolorallocate( $img,
                (int) round( 29 + ( 233 - 29 ) * $t ),
                (int) round( 84 + ( 30 - 84 ) * $t ),
                (int) round( 160 + ( 99 - 160 ) * $t )
            );
            imageline( $img, $x, 0, $x, $size, $col );
        }
        // Cartouche blanc arrondi derriere le logo : la partie texte du logo
        // est magenta et se fondrait dans le cote magenta du degrade (constate
        // au premier essai). Coins arrondis = rectangle + 4 disques.
        $tw  = (int) round( $size * $frac );
        $th  = (int) round( $tw * $lh / $lw );
        $pad = (int) round( $size * 0.055 );
        $cw  = $tw + 2 * $pad;
        $ch  = $th + 2 * $pad;
        $cx  = (int) ( ( $size - $cw ) / 2 );
        $cy  = (int) ( ( $size - $ch ) / 2 );
        $r   = (int) round( $size * 0.055 );
        $blanc = imagecolorallocate( $img, 255, 255, 255 );
        imagefilledrectangle( $img, $cx + $r, $cy, $cx + $cw - $r, $cy + $ch, $blanc );
        imagefilledrectangle( $img, $cx, $cy + $r, $cx + $cw, $cy + $ch - $r, $blanc );
        foreach ( [ [ $cx + $r, $cy + $r ], [ $cx + $cw - $r, $cy + $r ], [ $cx + $r, $cy + $ch - $r ], [ $cx + $cw - $r, $cy + $ch - $r ] ] as $c ) {
            imagefilledellipse( $img, $c[0], $c[1], 2 * $r, 2 * $r, $blanc );
        }

        // Logo centre sur le cartouche, alpha respecte (blending actif par defaut).
        imagecopyresampled( $img, $logo,
            (int) ( ( $size - $tw ) / 2 ), (int) ( ( $size - $th ) / 2 ),
            0, 0, $tw, $th, $lw, $lh );
        imagepng( $img, $dir . '/' . $name, 9 );
        imagedestroy( $img );
    }
    imagedestroy( $logo );
    update_option( 'slpwa_icons_ver', '1', false );
    return $url;
}

// Generation paresseuse : premiere visite (front ou admin) apres deploiement.
add_action( 'init', function () {
    if ( get_option( 'slpwa_icons_ver' ) !== '1' ) {
        slpwa_icons_dir_url();
    }
}, 99 );

/** Balises <head> du front : manifeste + couleurs + iPhone. */
add_action( 'wp_head', 'slpwa_head_tags', 5 );
function slpwa_head_tags() {
    $icons = slpwa_icons_dir_url();
    echo "\n" . '<link rel="manifest" href="' . esc_url( home_url( '/manifest.json' ) ) . '">' . "\n";
    echo '<meta name="theme-color" content="#1d54a0">' . "\n";
    // iPhone : sans ces balises, « Sur l\'ecran d\'accueil » donne une icone
    // capture d\'ecran et un onglet Safari au lieu d\'une app plein ecran.
    echo '<meta name="apple-mobile-web-app-capable" content="yes">' . "\n";
    echo '<meta name="apple-mobile-web-app-status-bar-style" content="default">' . "\n";
    echo '<meta name="apple-mobile-web-app-title" content="Santa Lucia">' . "\n";
    if ( $icons ) {
        echo '<link rel="apple-touch-icon" sizes="180x180" href="' . esc_url( $icons . '/apple-180.png' ) . '">' . "\n";
    }
}

/** Bouton « Installer l'application » (front). */
add_action( 'wp_footer', 'slpwa_install_button', 96 );
function slpwa_install_button() {
    if ( is_admin() ) {
        return;
    }
    ?>
    <script>
    (function(){
        // Deja installee (mode standalone) ou refus memorise : rien.
        if ( window.matchMedia && window.matchMedia('(display-mode: standalone)').matches ) return;
        if ( navigator.standalone ) return; // iOS installe
        if ( localStorage.getItem('slpwaDismiss') ) return;

        var deferred = null;
        var isIOS = /iPhone|iPad|iPod/.test(navigator.userAgent);

        function show(){
            if ( document.getElementById('slpwa-install') ) return;
            var box = document.createElement('div');
            box.id = 'slpwa-install';
            box.innerHTML = '<button type="button" class="slpwa-cta">📲 Installer l\'application</button>'
                          + '<button type="button" class="slpwa-x" aria-label="Fermer">×</button>';
            document.body.appendChild(box);
            var css = document.createElement('style');
            /* bottom 128px : au-dessus de la cloche push (76px) et de la navbar mobile */
            css.textContent = '#slpwa-install{position:fixed;right:14px;bottom:128px;z-index:1000005;display:flex;align-items:center;gap:4px;}'
                + '#slpwa-install .slpwa-cta{border:none;border-radius:22px;background:#1d54a0;color:#fff;font-weight:600;font-size:13px;padding:10px 16px;cursor:pointer;box-shadow:0 6px 18px rgba(0,0,0,.25);}'
                + '#slpwa-install .slpwa-x{border:none;background:rgba(0,0,0,.45);color:#fff;border-radius:50%;width:22px;height:22px;line-height:1;cursor:pointer;font-size:13px;}'
                + '#slpwa-ios{position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:1000010;display:flex;align-items:center;justify-content:center;padding:20px;}'
                + '#slpwa-ios .p{background:#fff;border-radius:14px;max-width:360px;padding:22px 24px;font-size:14.5px;line-height:1.6;color:#1d2327;}'
                + '#slpwa-ios .p b{color:#1d54a0;}'
                + '#slpwa-ios button{margin-top:12px;border:none;border-radius:8px;background:#e91e63;color:#fff;font-weight:600;padding:10px 18px;cursor:pointer;}';
            document.head.appendChild(css);

            box.querySelector('.slpwa-x').addEventListener('click', function(){
                localStorage.setItem('slpwaDismiss','1');
                box.remove();
            });
            box.querySelector('.slpwa-cta').addEventListener('click', function(){
                if ( deferred ) {
                    deferred.prompt();
                    deferred.userChoice.then(function(){ box.remove(); });
                    deferred = null;
                    return;
                }
                // iPhone : pas d'installation programmatique -> mode d'emploi.
                var o = document.createElement('div');
                o.id = 'slpwa-ios';
                o.innerHTML = '<div class="p"><b>Installer Santa Lucia sur votre iPhone</b><br><br>'
                    + '1. Touchez le bouton <b>Partager</b> ⬆️ en bas de Safari<br>'
                    + '2. Choisissez <b>« Sur l\'écran d\'accueil »</b><br>'
                    + '3. Touchez <b>Ajouter</b><br><br>'
                    + 'L\'application apparaîtra avec les autres, en plein écran.'
                    + '<br><button type="button">Compris</button></div>';
                document.body.appendChild(o);
                o.querySelector('button').addEventListener('click', function(){ o.remove(); });
                o.addEventListener('click', function(e){ if ( e.target === o ) o.remove(); });
            });
        }

        window.addEventListener('beforeinstallprompt', function(e){
            e.preventDefault(); // pas de mini-bandeau navigateur sauvage
            deferred = e;
            show();
        });
        // iPhone n'emet jamais beforeinstallprompt : bouton affiche directement.
        if ( isIOS ) { show(); }
    })();
    </script>
    <?php
}
