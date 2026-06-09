<?php
/**
 * Couche d'abstraction multi-fournisseurs IA pour l'Import Magique.
 * Fournisseurs supportés : Google Gemini, OpenAI, Anthropic Claude.
 * Tous : analyse d'images (vision) + extraction structurée JSON.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/** Définition des fournisseurs : libellé, option de clé, modèle par défaut. */
function sl_ai_providers() {
    return [
        'gemini' => [
            'label'       => 'Google Gemini',
            'key_option'  => 'sl_gemini_api_key',
            'default_model' => 'gemini-2.5-flash',
            'key_help'    => 'https://aistudio.google.com/app/apikey',
        ],
        'openai' => [
            'label'       => 'OpenAI (GPT-4o)',
            'key_option'  => 'sl_openai_api_key',
            'default_model' => 'gpt-4o-mini',
            'key_help'    => 'https://platform.openai.com/api-keys',
        ],
        'anthropic' => [
            'label'       => 'Anthropic Claude',
            'key_option'  => 'sl_anthropic_api_key',
            'default_model' => 'claude-3-5-sonnet-latest',
            'key_help'    => 'https://console.anthropic.com/settings/keys',
        ],
    ];
}

/** Fournisseur actif (par défaut gemini). */
function sl_ai_get_provider() {
    $p = (string) get_option( 'sl_ai_provider', 'gemini' );
    $all = sl_ai_providers();
    return isset( $all[ $p ] ) ? $p : 'gemini';
}

/** Clé enregistrée pour un fournisseur. */
function sl_ai_get_key( $provider ) {
    $all = sl_ai_providers();
    if ( ! isset( $all[ $provider ] ) ) return '';
    return trim( (string) get_option( $all[ $provider ]['key_option'] ) );
}

/** Modèle utilisé pour un fournisseur (filtrable). */
function sl_ai_get_model( $provider ) {
    $all = sl_ai_providers();
    $default = isset( $all[ $provider ] ) ? $all[ $provider ]['default_model'] : '';
    $opt = trim( (string) get_option( 'sl_ai_model_' . $provider ) );
    $model = $opt !== '' ? $opt : $default;
    return apply_filters( 'sl_ai_model', $model, $provider );
}

/* ============================================================
   TEST DE CLÉ (lecture seule, ne consomme pas de quota de génération)
   ============================================================ */
function sl_ai_test_key( $provider, $key ) {
    $key = trim( (string) $key );
    if ( $key === '' ) return [ 'ok' => false, 'msg' => 'Aucune clé fournie.' ];

    if ( $provider === 'gemini' ) {
        $resp = wp_remote_get( 'https://generativelanguage.googleapis.com/v1beta/models?key=' . rawurlencode( $key ), [ 'timeout' => 20 ] );
    } elseif ( $provider === 'openai' ) {
        $resp = wp_remote_get( 'https://api.openai.com/v1/models', [ 'timeout' => 20, 'headers' => [ 'Authorization' => 'Bearer ' . $key ] ] );
    } elseif ( $provider === 'anthropic' ) {
        $resp = wp_remote_get( 'https://api.anthropic.com/v1/models', [ 'timeout' => 20, 'headers' => [ 'x-api-key' => $key, 'anthropic-version' => '2023-06-01' ] ] );
    } else {
        return [ 'ok' => false, 'msg' => 'Fournisseur inconnu.' ];
    }

    if ( is_wp_error( $resp ) ) return [ 'ok' => false, 'msg' => $resp->get_error_message() ];
    $code = (int) wp_remote_retrieve_response_code( $resp );
    $body = json_decode( wp_remote_retrieve_body( $resp ), true );
    if ( $code === 200 ) {
        $n = isset( $body['models'] ) ? count( $body['models'] ) : ( isset( $body['data'] ) ? count( $body['data'] ) : 0 );
        return [ 'ok' => true, 'msg' => 'Clé valide' . ( $n ? ' — ' . $n . ' modèles accessibles.' : '.' ) ];
    }
    $msg = isset( $body['error']['message'] ) ? $body['error']['message'] : ( 'Réponse inattendue (HTTP ' . $code . ').' );
    return [ 'ok' => false, 'msg' => $msg ];
}

/* ============================================================
   PARSING NORMALISÉ : extraire la liste de produits du texte JSON
   ============================================================ */
function sl_ai_parse_products( $text ) {
    $text = trim( (string) $text );
    // Retirer d'éventuelles balises de code markdown.
    $text = preg_replace( '/^```[a-zA-Z]*\s*|\s*```$/m', '', $text );
    $parsed = json_decode( $text, true );
    if ( ! is_array( $parsed ) ) return [];

    // Si objet { "products": [...] } ou tout autre objet enveloppant une liste.
    if ( ! isset( $parsed[0] ) ) {
        if ( isset( $parsed['products'] ) && is_array( $parsed['products'] ) ) {
            $parsed = $parsed['products'];
        } else {
            foreach ( $parsed as $v ) {
                if ( is_array( $v ) && isset( $v[0] ) ) { $parsed = $v; break; }
            }
        }
    }
    if ( ! is_array( $parsed ) ) return [];

    return array_values( array_filter( $parsed, function( $item ) {
        return is_array( $item )
            && ! empty( $item['titre'] )
            && ! empty( $item['prix_avant'] )
            && ! empty( $item['prix_apres'] );
    } ) );
}

/* ============================================================
   APPEL UNIFIÉ : extrait les produits via le fournisseur actif
   $images = [ ['mime'=>'image/jpeg','b64'=>'...'], ... ]
   Retour : [ 'ok'=>bool, 'products'=>[...], 'error'=>string, 'provider'=>string ]
   ============================================================ */
function sl_ai_extract_products( $prompt_text, $images = [] ) {
    $provider = sl_ai_get_provider();
    $key      = sl_ai_get_key( $provider );
    $model    = sl_ai_get_model( $provider );
    $label    = sl_ai_providers()[ $provider ]['label'];

    if ( $key === '' ) {
        return [ 'ok' => false, 'products' => [], 'error' => 'Aucune clé configurée pour ' . $label . '.', 'provider' => $provider ];
    }

    if ( $provider === 'gemini' ) {
        $parts = [ [ 'text' => $prompt_text ] ];
        foreach ( $images as $img ) {
            if ( ! empty( $img['name'] ) ) $parts[] = [ 'text' => 'Image filename: ' . $img['name'] ];
            $parts[] = [ 'inline_data' => [ 'mime_type' => $img['mime'], 'data' => $img['b64'] ] ];
        }
        $resp = wp_remote_post( 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode( $model ) . ':generateContent?key=' . $key, [
            'timeout' => 90,
            'headers' => [ 'Content-Type' => 'application/json' ],
            'body'    => wp_json_encode( [
                'contents'         => [ [ 'role' => 'user', 'parts' => $parts ] ],
                'generationConfig' => [ 'responseMimeType' => 'application/json' ],
            ] ),
        ] );
        return sl_ai__finish( $resp, $provider, function( $body ) {
            return $body['candidates'][0]['content']['parts'][0]['text'] ?? '';
        } );
    }

    if ( $provider === 'openai' ) {
        $content = [ [ 'type' => 'text', 'text' => $prompt_text ] ];
        foreach ( $images as $img ) {
            if ( ! empty( $img['name'] ) ) $content[] = [ 'type' => 'text', 'text' => 'Image filename: ' . $img['name'] ];
            $content[] = [ 'type' => 'image_url', 'image_url' => [ 'url' => 'data:' . $img['mime'] . ';base64,' . $img['b64'] ] ];
        }
        $resp = wp_remote_post( 'https://api.openai.com/v1/chat/completions', [
            'timeout' => 90,
            'headers' => [ 'Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $key ],
            'body'    => wp_json_encode( [
                'model'           => $model,
                'messages'        => [ [ 'role' => 'user', 'content' => $content ] ],
                'response_format' => [ 'type' => 'json_object' ],
                'max_tokens'      => 4000,
            ] ),
        ] );
        return sl_ai__finish( $resp, $provider, function( $body ) {
            return $body['choices'][0]['message']['content'] ?? '';
        } );
    }

    if ( $provider === 'anthropic' ) {
        $content = [ [ 'type' => 'text', 'text' => $prompt_text ] ];
        foreach ( $images as $img ) {
            if ( ! empty( $img['name'] ) ) $content[] = [ 'type' => 'text', 'text' => 'Image filename: ' . $img['name'] ];
            $content[] = [ 'type' => 'image', 'source' => [ 'type' => 'base64', 'media_type' => $img['mime'], 'data' => $img['b64'] ] ];
        }
        $resp = wp_remote_post( 'https://api.anthropic.com/v1/messages', [
            'timeout' => 90,
            'headers' => [ 'Content-Type' => 'application/json', 'x-api-key' => $key, 'anthropic-version' => '2023-06-01' ],
            'body'    => wp_json_encode( [
                'model'      => $model,
                'max_tokens' => 4000,
                'messages'   => [ [ 'role' => 'user', 'content' => $content ] ],
            ] ),
        ] );
        return sl_ai__finish( $resp, $provider, function( $body ) {
            return $body['content'][0]['text'] ?? '';
        } );
    }

    return [ 'ok' => false, 'products' => [], 'error' => 'Fournisseur inconnu.', 'provider' => $provider ];
}

/** Traite une réponse wp_remote_* : gère les erreurs HTTP puis parse les produits. */
function sl_ai__finish( $resp, $provider, $extract_text ) {
    if ( is_wp_error( $resp ) ) {
        return [ 'ok' => false, 'products' => [], 'error' => $resp->get_error_message(), 'provider' => $provider ];
    }
    $code = (int) wp_remote_retrieve_response_code( $resp );
    $body = json_decode( wp_remote_retrieve_body( $resp ), true );
    if ( $code !== 200 || isset( $body['error'] ) ) {
        $msg = $body['error']['message'] ?? ( 'Réponse API inattendue (HTTP ' . $code . ').' );
        return [ 'ok' => false, 'products' => [], 'error' => $msg, 'provider' => $provider ];
    }
    $text = call_user_func( $extract_text, $body );
    $products = sl_ai_parse_products( $text );
    return [ 'ok' => true, 'products' => $products, 'error' => '', 'provider' => $provider ];
}
