<?php
/**
 * Aiguillage du fournisseur d'IA : Anthropic (Claude) ou Google (Gemini).
 * Reglage : option 'sl_lucie_provider' = 'anthropic' | 'google'.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function sl_lucie_provider() {
    return get_option( 'sl_lucie_provider', 'anthropic' ) === 'google' ? 'google' : 'anthropic';
}

/** Le fournisseur actif a-t-il au moins une cle ? */
function sl_lucie_provider_has_key() {
    return sl_lucie_provider() === 'google' ? sl_lucie_google_has_key() : sl_lucie_has_key();
}

/** Garde de perimetre (true = en scope). */
function sl_lucie_llm_classify( $message ) {
    return sl_lucie_provider() === 'google'
        ? sl_lucie_gemini_classify( $message )
        : sl_lucie_anthropic_classify( $message );
}

/** Reponse complete avec outils. Retourne string|null. */
function sl_lucie_llm_answer( $system_text, $messages, $tools ) {
    return sl_lucie_provider() === 'google'
        ? sl_lucie_gemini_answer( $system_text, $messages, $tools )
        : sl_lucie_anthropic_answer( $system_text, $messages, $tools );
}

/** Extraction PDF -> {ok,text,error}. */
function sl_lucie_llm_extract_pdf( $bytes ) {
    return sl_lucie_provider() === 'google'
        ? sl_lucie_gemini_extract_pdf( $bytes )
        : sl_lucie_anthropic_extract_pdf( $bytes );
}
