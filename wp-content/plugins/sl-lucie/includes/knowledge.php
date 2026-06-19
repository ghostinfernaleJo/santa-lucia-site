<?php
/**
 * Base de connaissances de Lucie.
 * Stockee comme texte dans l'option 'sl_lucie_kb' (concatenation des documents).
 * A l'ajout d'un PDF, le texte est extrait UNE fois via Claude (support PDF natif)
 * puis stocke en clair -> aucune librairie PDF a embarquer, et cout nul a l'usage.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

const SL_LUCIE_KB_MAX = 120000; // garde-fou : ~120k caracteres max stockes

/** Retourne la base de connaissances complete (texte). */
function sl_lucie_kb_get() {
    return (string) get_option( 'sl_lucie_kb', '' );
}

/** Remplace entierement la base de connaissances. */
function sl_lucie_kb_set( $text ) {
    $text = (string) $text;
    if ( strlen( $text ) > SL_LUCIE_KB_MAX ) {
        $text = substr( $text, 0, SL_LUCIE_KB_MAX );
    }
    update_option( 'sl_lucie_kb', $text, false );
}

/** Ajoute un bloc de texte (titre + contenu) a la base existante. */
function sl_lucie_kb_append( $titre, $contenu ) {
    $contenu = trim( (string) $contenu );
    if ( $contenu === '' ) return;
    $bloc = "\n\n===== " . trim( (string) $titre ) . " =====\n" . $contenu;
    sl_lucie_kb_set( sl_lucie_kb_get() . $bloc );
}

/**
 * Extrait le texte d'un PDF via Claude (bloc document, support natif).
 * @return array [ 'ok' => bool, 'text' => string, 'error' => string|null ]
 */
function sl_lucie_kb_extract_pdf( $tmp_path, $filename ) {
    if ( ! sl_lucie_has_key() ) {
        return [ 'ok' => false, 'text' => '', 'error' => 'Configurez d\'abord une cle API, puis re-televersez le PDF (ou collez le texte directement).' ];
    }
    if ( ! file_exists( $tmp_path ) ) {
        return [ 'ok' => false, 'text' => '', 'error' => 'Fichier introuvable.' ];
    }
    $bytes = file_get_contents( $tmp_path );
    if ( $bytes === false || $bytes === '' ) {
        return [ 'ok' => false, 'text' => '', 'error' => 'Lecture du PDF impossible.' ];
    }
    // Garde-fou taille (les tres gros PDF coutent cher et risquent le timeout)
    if ( strlen( $bytes ) > 9 * 1024 * 1024 ) {
        return [ 'ok' => false, 'text' => '', 'error' => 'PDF trop volumineux (max ~9 Mo). Decoupez-le ou collez le texte.' ];
    }

    $payload = [
        'model'      => 'claude-opus-4-8',
        'max_tokens' => 16000,
        'messages'   => [ [
            'role' => 'user',
            'content' => [
                [ 'type' => 'document', 'source' => [
                    'type' => 'base64', 'media_type' => 'application/pdf',
                    'data' => base64_encode( $bytes ),
                ] ],
                [ 'type' => 'text', 'text' =>
                    "Extrais tout le texte utile de ce document en clair (titres, paragraphes, listes), "
                    . "sans aucun commentaire ni introduction de ta part. Si le document est une image scannee "
                    . "sans texte, reponds exactement : [AUCUN_TEXTE]." ],
            ],
        ] ],
    ];

    $res = sl_lucie_call_claude( $payload );
    if ( ! $res['ok'] ) {
        return [ 'ok' => false, 'text' => '', 'error' => $res['error'] ];
    }
    $text = sl_lucie_extract_text( $res['data'] );
    if ( $text === '' || strpos( $text, '[AUCUN_TEXTE]' ) !== false ) {
        return [ 'ok' => false, 'text' => '', 'error' => 'Aucun texte exploitable (PDF scanne ?). Collez le texte manuellement.' ];
    }
    return [ 'ok' => true, 'text' => $text, 'error' => null ];
}
