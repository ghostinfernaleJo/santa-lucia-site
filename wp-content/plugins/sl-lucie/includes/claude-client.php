<?php
/**
 * Client Claude — appels HTTP a l'API Anthropic via l'API HTTP de WordPress.
 * Gestion de PLUSIEURS cles API avec basculement automatique (failover).
 *
 * Note d'implementation : on utilise wp_remote_post (et non le SDK PHP) car le
 * plugin est deploye sans Composer ; c'est l'approche standard des plugins WP.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

const SL_LUCIE_API_URL = 'https://api.anthropic.com/v1/messages';
const SL_LUCIE_API_VER = '2023-06-01';

/** Retourne la liste des cles API (constante wp-config prioritaire, sinon option). */
function sl_lucie_get_keys() {
    if ( defined( 'SL_LUCIE_API_KEYS' ) && SL_LUCIE_API_KEYS ) {
        $raw = is_array( SL_LUCIE_API_KEYS ) ? SL_LUCIE_API_KEYS : preg_split( '/[\s,]+/', (string) SL_LUCIE_API_KEYS );
    } else {
        $raw = (array) get_option( 'sl_lucie_api_keys', [] );
    }
    $keys = [];
    foreach ( (array) $raw as $k ) {
        $k = trim( (string) $k );
        if ( $k !== '' ) $keys[] = $k;
    }
    return array_values( array_unique( $keys ) );
}

/** Y a-t-il au moins une cle configuree ? */
function sl_lucie_has_key() {
    return ! empty( sl_lucie_get_keys() );
}

/**
 * Appelle l'API Claude avec basculement multi-cles.
 * Demarre sur la derniere cle qui a fonctionne (rotation douce), puis bascule
 * sur les suivantes en cas de 429 / 401 / 403 / 5xx.
 *
 * @param array $payload  Corps JSON de /v1/messages (model, max_tokens, system, messages, tools, ...).
 * @return array [ 'ok' => bool, 'data' => array|null, 'error' => string|null, 'key_index' => int ]
 */
function sl_lucie_call_claude( array $payload ) {
    $keys = sl_lucie_get_keys();
    if ( empty( $keys ) ) {
        return [ 'ok' => false, 'data' => null, 'error' => 'Aucune cle API configuree.' ];
    }

    // Commence par la derniere cle connue comme fonctionnelle (round-robin doux)
    $start = (int) get_option( 'sl_lucie_key_cursor', 0 );
    $n     = count( $keys );
    $last_error = 'Echec inconnu.';

    for ( $i = 0; $i < $n; $i++ ) {
        $idx = ( $start + $i ) % $n;
        $key = $keys[ $idx ];

        $resp = wp_remote_post( SL_LUCIE_API_URL, [
            'timeout' => 60,
            'headers' => [
                'x-api-key'         => $key,
                'anthropic-version' => SL_LUCIE_API_VER,
                'content-type'      => 'application/json',
            ],
            'body' => wp_json_encode( $payload ),
        ] );

        if ( is_wp_error( $resp ) ) {
            $last_error = 'Reseau : ' . $resp->get_error_message();
            continue; // bascule sur la cle suivante
        }

        $code = (int) wp_remote_retrieve_response_code( $resp );
        $body = json_decode( wp_remote_retrieve_body( $resp ), true );

        if ( $code === 200 && is_array( $body ) ) {
            update_option( 'sl_lucie_key_cursor', $idx, false ); // memorise la cle qui marche
            return [ 'ok' => true, 'data' => $body, 'error' => null, 'key_index' => $idx ];
        }

        // 429 (debit), 401/403 (cle invalide/quota), 5xx, 529 (surcharge) -> on bascule
        $msg = is_array( $body ) && isset( $body['error']['message'] ) ? $body['error']['message'] : ( 'HTTP ' . $code );
        $last_error = 'Cle #' . ( $idx + 1 ) . ' : ' . $msg;

        // Erreurs non liees a la cle (ex : 400 mauvaise requete) -> inutile de basculer
        if ( $code === 400 ) {
            return [ 'ok' => false, 'data' => $body, 'error' => $last_error ];
        }
    }

    return [ 'ok' => false, 'data' => null, 'error' => 'Toutes les cles ont echoue. Derniere erreur : ' . $last_error ];
}

/** Extrait le texte concatene des blocs "text" d'une reponse Claude. */
function sl_lucie_extract_text( $data ) {
    $out = '';
    if ( ! empty( $data['content'] ) && is_array( $data['content'] ) ) {
        foreach ( $data['content'] as $block ) {
            if ( ( $block['type'] ?? '' ) === 'text' ) {
                $out .= $block['text'];
            }
        }
    }
    return trim( $out );
}

/* ============================================================
   COUCHE ANTHROPIC (Claude) — classify / answer / extract PDF
   ============================================================ */

/** Garde de perimetre via Haiku. Retourne true (en scope) / false (hors-sujet). */
function sl_lucie_anthropic_classify( $message ) {
    $res = sl_lucie_call_claude( [
        'model'      => 'claude-haiku-4-5',
        'max_tokens' => 5,
        'system'     => 'Le complexe Santa Lucia est une enseigne camerounaise (supermarches, boulangerie, patisserie, fast food, services). Reponds OUI si la question peut raisonnablement concerner Santa Lucia : produits, prix, agences ou magasins, villes/quartiers, menus, fast food, promotions, bons plans, recrutement, horaires, contact, services, livraison. Reponds NON UNIQUEMENT si la question n a clairement AUCUN rapport (meteo, politique, calculs, autre marque, code informatique, culture generale). En cas de doute, reponds OUI. Reponds uniquement par OUI ou NON.',
        'messages'   => [ [ 'role' => 'user', 'content' => mb_substr( (string) $message, 0, 1000 ) ] ],
    ] );
    if ( ! $res['ok'] ) return true; // panne du garde -> on laisse passer (le prompt principal cadre aussi)
    return strpos( strtoupper( sl_lucie_extract_text( $res['data'] ) ), 'NON' ) === false;
}

/** Reponse complete avec boucle d'outils (Opus 4.8). Retourne string|null. */
function sl_lucie_anthropic_answer( $system_text, $messages, $tools ) {
    $system = [ [ 'type' => 'text', 'text' => $system_text, 'cache_control' => [ 'type' => 'ephemeral' ] ] ];
    $msgs = $messages;
    for ( $turn = 0; $turn < 6; $turn++ ) {
        $res = sl_lucie_call_claude( [
            'model'         => 'claude-opus-4-8',
            'max_tokens'    => 2000,
            'system'        => $system,
            'tools'         => $tools,
            'messages'      => $msgs,
            // Adaptive thinking : fiabilise la decision d'appeler un outil et
            // l'ancrage factuel (sinon le modele repond de memoire -> hallucine).
            'thinking'      => [ 'type' => 'adaptive' ],
            // effort 'medium' (au lieu de 'low') : 'low' reduit le recours aux
            // outils. medium = bon equilibre qualite/cout pour un chatbot ancre.
            'output_config' => [ 'effort' => 'medium' ],
        ] );
        if ( ! $res['ok'] ) return null;
        $data = $res['data'];
        if ( ( $data['stop_reason'] ?? '' ) === 'tool_use' ) {
            $msgs[] = [ 'role' => 'assistant', 'content' => $data['content'] ];
            $tr = [];
            foreach ( (array) $data['content'] as $b ) {
                if ( ( $b['type'] ?? '' ) === 'tool_use' ) {
                    $tr[] = [
                        'type'        => 'tool_result',
                        'tool_use_id' => $b['id'] ?? '',
                        'content'     => sl_lucie_run_tool( $b['name'] ?? '', $b['input'] ?? [] ),
                    ];
                }
            }
            $msgs[] = [ 'role' => 'user', 'content' => $tr ];
            continue;
        }
        return sl_lucie_extract_text( $data );
    }
    return null;
}

/** Extraction de texte d'un PDF via Claude (bloc document). Retourne array {ok,text,error}. */
function sl_lucie_anthropic_extract_pdf( $bytes ) {
    $res = sl_lucie_call_claude( [
        'model'      => 'claude-opus-4-8',
        'max_tokens' => 16000,
        'messages'   => [ [
            'role' => 'user',
            'content' => [
                [ 'type' => 'document', 'source' => [ 'type' => 'base64', 'media_type' => 'application/pdf', 'data' => base64_encode( $bytes ) ] ],
                [ 'type' => 'text', 'text' => 'Extrais tout le texte utile de ce document en clair, sans commentaire. Si c\'est une image scannee sans texte, reponds exactement : [AUCUN_TEXTE].' ],
            ],
        ] ],
    ] );
    if ( ! $res['ok'] ) return [ 'ok' => false, 'text' => '', 'error' => $res['error'] ];
    $text = sl_lucie_extract_text( $res['data'] );
    if ( $text === '' || strpos( $text, '[AUCUN_TEXTE]' ) !== false ) {
        return [ 'ok' => false, 'text' => '', 'error' => 'Aucun texte exploitable (PDF scanne ?). Collez le texte manuellement.' ];
    }
    return [ 'ok' => true, 'text' => $text, 'error' => null ];
}
