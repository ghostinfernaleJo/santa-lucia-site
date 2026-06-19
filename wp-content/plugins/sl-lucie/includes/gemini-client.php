<?php
/**
 * Client Google Gemini — alternative a Claude.
 * Endpoint : generativelanguage.googleapis.com (generateContent).
 * Multi-cles avec basculement automatique, comme pour Anthropic.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function sl_lucie_google_get_keys() {
    if ( defined( 'SL_LUCIE_GOOGLE_KEYS' ) && SL_LUCIE_GOOGLE_KEYS ) {
        $raw = is_array( SL_LUCIE_GOOGLE_KEYS ) ? SL_LUCIE_GOOGLE_KEYS : preg_split( '/[\s,]+/', (string) SL_LUCIE_GOOGLE_KEYS );
    } else {
        $raw = (array) get_option( 'sl_lucie_google_keys', [] );
    }
    $keys = [];
    foreach ( (array) $raw as $k ) { $k = trim( (string) $k ); if ( $k !== '' ) $keys[] = $k; }
    return array_values( array_unique( $keys ) );
}

function sl_lucie_google_has_key() {
    return ! empty( sl_lucie_google_get_keys() );
}

function sl_lucie_google_model() {
    $m = trim( (string) get_option( 'sl_lucie_google_model', '' ) );
    return $m !== '' ? $m : 'gemini-2.5-flash';
}

/** Appel generateContent avec basculement multi-cles. Retourne {ok,data,error}. */
function sl_lucie_call_gemini( $model, array $body ) {
    $keys = sl_lucie_google_get_keys();
    if ( empty( $keys ) ) return [ 'ok' => false, 'data' => null, 'error' => 'Aucune cle Google configuree.' ];

    $start = (int) get_option( 'sl_lucie_gkey_cursor', 0 );
    $n = count( $keys );
    $last = 'Echec inconnu.';

    for ( $i = 0; $i < $n; $i++ ) {
        $idx = ( $start + $i ) % $n;
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode( $model ) . ':generateContent?key=' . rawurlencode( $keys[ $idx ] );

        $resp = wp_remote_post( $url, [
            'timeout' => 60,
            'headers' => [ 'content-type' => 'application/json' ],
            'body'    => wp_json_encode( $body ),
        ] );

        if ( is_wp_error( $resp ) ) { $last = 'Reseau : ' . $resp->get_error_message(); continue; }
        $code = (int) wp_remote_retrieve_response_code( $resp );
        $data = json_decode( wp_remote_retrieve_body( $resp ), true );

        if ( $code === 200 && is_array( $data ) ) {
            update_option( 'sl_lucie_gkey_cursor', $idx, false );
            return [ 'ok' => true, 'data' => $data, 'error' => null, 'key_index' => $idx ];
        }
        $msg = is_array( $data ) && isset( $data['error']['message'] ) ? $data['error']['message'] : ( 'HTTP ' . $code );
        $last = 'Cle Google #' . ( $idx + 1 ) . ' : ' . $msg;
        if ( $code === 400 ) return [ 'ok' => false, 'data' => $data, 'error' => $last ];
    }
    return [ 'ok' => false, 'data' => null, 'error' => 'Toutes les cles Google ont echoue. ' . $last ];
}

/** Concatene le texte des parts d'une reponse Gemini. */
function sl_lucie_gemini_text( $data ) {
    $out = '';
    $parts = $data['candidates'][0]['content']['parts'] ?? [];
    foreach ( (array) $parts as $p ) { if ( isset( $p['text'] ) ) $out .= $p['text']; }
    return trim( $out );
}

/** Convertit les definitions d'outils internes au format Gemini (function_declarations). */
function sl_lucie_gemini_tools( $tools ) {
    $decl = [];
    foreach ( $tools as $t ) {
        $d = [ 'name' => $t['name'], 'description' => $t['description'] ];
        $schema = $t['input_schema'] ?? null;
        if ( is_array( $schema ) && ! empty( $schema['properties'] ) && ! ( $schema['properties'] instanceof stdClass ) ) {
            $d['parameters'] = $schema; // type/properties/required compatibles OpenAPI
        }
        $decl[] = $d;
    }
    return $decl;
}

/** Garde de perimetre via Gemini. true = en scope. */
function sl_lucie_gemini_classify( $message ) {
    $res = sl_lucie_call_gemini( sl_lucie_google_model(), [
        'system_instruction' => [ 'parts' => [ [ 'text' => 'Tu es un classificateur. La question porte-t-elle sur le Complexe Santa Lucia (produits, agences, menus, promotions, bons plans, recrutement, horaires, infos pratiques) ? Reponds UNIQUEMENT par OUI ou NON.' ] ] ],
        'contents' => [ [ 'role' => 'user', 'parts' => [ [ 'text' => mb_substr( (string) $message, 0, 1000 ) ] ] ] ],
        // thinkingBudget:0 sinon Gemini 2.5 consomme tout le budget en "thinking"
        // et ne renvoie pas OUI/NON ; +tokens pour une reponse fiable.
        'generationConfig' => [ 'maxOutputTokens' => 10, 'temperature' => 0, 'thinkingConfig' => [ 'thinkingBudget' => 0 ] ],
    ] );
    if ( ! $res['ok'] ) return true;
    $txt = strtoupper( sl_lucie_gemini_text( $res['data'] ) );
    if ( $txt === '' ) return true;                 // pas de reponse claire -> on laisse passer
    return strpos( $txt, 'NON' ) === false;         // hors-sujet seulement si "NON" explicite
}

/** Reponse complete : tente avec outils, et bascule sans outils si echec. */
function sl_lucie_gemini_answer( $system_text, $messages, $tools ) {
    $r = sl_lucie_gemini_run( $system_text, $messages, $tools );      // avec outils (donnees live)
    if ( $r !== null && trim( $r ) !== '' ) return $r;
    // Repli : sans outils, Lucie repond au moins depuis la base de connaissances
    return sl_lucie_gemini_run( $system_text, $messages, [] );
}

/** Boucle d'outils Gemini. Retourne string|null (null = aucune sortie exploitable). */
function sl_lucie_gemini_run( $system_text, $messages, $tools ) {
    $contents = [];
    foreach ( $messages as $m ) {
        $role = ( ( $m['role'] ?? '' ) === 'assistant' ) ? 'model' : 'user';
        $contents[] = [ 'role' => $role, 'parts' => [ [ 'text' => (string) ( $m['content'] ?? '' ) ] ] ];
    }
    $decl  = sl_lucie_gemini_tools( $tools );
    $model = sl_lucie_google_model();
    $use_tools = ! empty( $decl );

    for ( $turn = 0; $turn < 4; $turn++ ) {
        $body = [
            'system_instruction' => [ 'parts' => [ [ 'text' => $system_text ] ] ],
            'contents'           => $contents,
            // thinkingBudget:0 -> desactive le "thinking", principal correctif du
            // bug MALFORMED_FUNCTION_CALL de Gemini 2.5 + reponses plus rapides.
            'generationConfig'   => [ 'maxOutputTokens' => 1200, 'temperature' => 0.2, 'thinkingConfig' => [ 'thinkingBudget' => 0 ] ],
        ];
        if ( $use_tools ) {
            $body['tools'] = [ [ 'function_declarations' => $decl ] ];
        }

        $res = sl_lucie_call_gemini( $model, $body );
        if ( ! $res['ok'] ) return null;

        $cand   = $res['data']['candidates'][0] ?? [];
        $finish = $cand['finishReason'] ?? '';
        $parts  = $cand['content']['parts'] ?? [];
        $calls  = [];
        $text   = '';
        foreach ( (array) $parts as $p ) {
            if ( isset( $p['functionCall'] ) ) $calls[] = $p['functionCall'];
            elseif ( isset( $p['text'] ) )     $text  .= $p['text'];
        }

        if ( ! empty( $calls ) ) {
            $contents[] = [ 'role' => 'model', 'parts' => $parts ];
            $resp_parts = [];
            foreach ( $calls as $c ) {
                $out = sl_lucie_run_tool( $c['name'] ?? '', $c['args'] ?? [] );
                $resp_parts[] = [ 'functionResponse' => [ 'name' => $c['name'] ?? '', 'response' => [ 'result' => $out ] ] ];
            }
            $contents[] = [ 'role' => 'user', 'parts' => $resp_parts ];
            continue;
        }

        if ( trim( $text ) !== '' ) return trim( $text );

        // Ni texte ni appel exploitable (ex: MALFORMED_FUNCTION_CALL) -> echec de ce chemin
        return null;
    }
    return null;
}

/** Extraction de texte d'un PDF via Gemini (inline_data). Retourne array {ok,text,error}. */
function sl_lucie_gemini_extract_pdf( $bytes ) {
    $res = sl_lucie_call_gemini( sl_lucie_google_model(), [
        'contents' => [ [ 'role' => 'user', 'parts' => [
            [ 'inline_data' => [ 'mime_type' => 'application/pdf', 'data' => base64_encode( $bytes ) ] ],
            [ 'text' => 'Extrais tout le texte utile de ce document en clair, sans commentaire. Si c\'est une image scannee sans texte, reponds exactement : [AUCUN_TEXTE].' ],
        ] ] ],
        'generationConfig' => [ 'maxOutputTokens' => 16000, 'temperature' => 0 ],
    ] );
    if ( ! $res['ok'] ) return [ 'ok' => false, 'text' => '', 'error' => $res['error'] ];
    $text = sl_lucie_gemini_text( $res['data'] );
    if ( $text === '' || strpos( $text, '[AUCUN_TEXTE]' ) !== false ) {
        return [ 'ok' => false, 'text' => '', 'error' => 'Aucun texte exploitable (PDF scanne ?). Collez le texte manuellement.' ];
    }
    return [ 'ok' => true, 'text' => $text, 'error' => null ];
}
