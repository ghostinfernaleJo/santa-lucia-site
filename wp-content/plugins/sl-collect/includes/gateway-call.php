<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ============================================================
   MOYEN DE PAIEMENT « Confirmer par téléphone puis payer en ligne »
   La commande est creee EN ATTENTE ; le client appelle l'agence pour
   confirmer la disponibilite, puis paie en ligne via le lien « Payer »
   de son compte (mecanisme natif WooCommerce order-pay).
   ============================================================ */
if ( class_exists( 'WC_Payment_Gateway' ) ) {

    class SLC_Gateway_Call extends WC_Payment_Gateway {

        public function __construct() {
            $this->id                 = 'slc_call';
            $this->icon               = '';
            $this->has_fields         = false;
            $this->method_title       = 'Drop & Collect — Confirmation téléphonique';
            $this->method_description = 'Le client commande sans payer, appelle l\'agence pour confirmer la disponibilité, puis paie en ligne depuis son compte.';

            $this->init_form_fields();
            $this->init_settings();

            $this->title       = $this->get_option( 'title', 'Confirmer par téléphone puis payer en ligne' );
            $this->description = $this->get_option( 'description', 'Votre commande sera enregistrée en attente. Appelez-nous pour confirmer la disponibilité, puis payez en ligne depuis votre compte.' );

            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );
        }

        public function init_form_fields() {
            $this->form_fields = [
                'enabled' => [
                    'title'   => 'Activer',
                    'type'    => 'checkbox',
                    'label'   => 'Activer la confirmation téléphonique',
                    'default' => 'yes',
                ],
                'title' => [
                    'title'   => 'Intitulé affiché',
                    'type'    => 'text',
                    'default' => 'Confirmer par téléphone puis payer en ligne',
                ],
                'description' => [
                    'title'   => 'Description affichée',
                    'type'    => 'textarea',
                    'default' => 'Votre commande sera enregistrée en attente. Appelez-nous pour confirmer la disponibilité, puis payez en ligne depuis votre compte.',
                ],
            ];
        }

        /**
         * Ne jamais se proposer sur la page de paiement d'une commande existante.
         *
         * Cette passerelle n'est pas un moyen de paiement : c'est le choix
         * « je paierai plus tard, apres avoir appele l'agence ». Sur order-pay,
         * le client vient justement PAYER — s'y proposer le renvoie vers une
         * commande toujours en attente, en boucle. C'est ce qui se passait tant
         * qu'aucun vrai moyen de paiement n'etait installe : le bouton « Payer »
         * ne menait nulle part.
         */
        public function is_available() {
            if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-pay' ) ) {
                return false;
            }
            return parent::is_available();
        }

        public function process_payment( $order_id ) {
            $order = wc_get_order( $order_id );
            // Reste « pending » = en attente de paiement ; le client garde le
            // bouton « Payer » dans Mon compte (order-pay natif WooCommerce).
            $order->update_status( 'pending', 'Drop & Collect — en attente : confirmation téléphonique puis paiement en ligne par le client.' );
            // Pas de reduction de stock ici : la commande n'est pas payee.

            if ( function_exists( 'WC' ) && WC()->cart ) {
                WC()->cart->empty_cart();
            }
            return [
                'result'   => 'success',
                'redirect' => $this->get_return_url( $order ),
            ];
        }
    }

    add_filter( 'woocommerce_payment_gateways', function ( $gateways ) {
        $gateways[] = 'SLC_Gateway_Call';
        return $gateways;
    } );

    /**
     * Alerte : cette passerelle promet au client « puis payez en ligne depuis
     * votre compte ». Si AUCUN vrai moyen de paiement n'est disponible, cette
     * promesse ne peut pas etre tenue — le client se retrouve avec une commande
     * en attente qu'il ne pourra jamais regler. Le probleme est invisible depuis
     * l'admin : on le rend explicite.
     */
    add_action( 'admin_notices', 'slc_call_warn_no_real_gateway' );
    function slc_call_warn_no_real_gateway() {
        if ( ! current_user_can( 'manage_woocommerce' ) || ! function_exists( 'WC' ) ) {
            return;
        }
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen || 'woocommerce_page_wc-settings' !== $screen->id ) {
            return;
        }

        $gws = WC()->payment_gateways() ? WC()->payment_gateways()->payment_gateways() : [];
        if ( empty( $gws['slc_call'] ) || 'yes' !== $gws['slc_call']->enabled ) {
            return;
        }

        // On s'exclut nous-meme : sans ca, is_available() rappellerait cette
        // passerelle et l'alerte ne se declencherait jamais.
        foreach ( $gws as $id => $gw ) {
            if ( 'slc_call' === $id ) {
                continue;
            }
            if ( $gw->is_available() ) {
                return; // un vrai moyen de paiement existe : promesse tenable.
            }
        }

        echo '<div class="notice notice-warning"><p><strong>Drop &amp; Collect — Confirmation téléphonique :</strong> '
            . 'cette méthode annonce au client « <em>puis payez en ligne depuis votre compte</em> », mais '
            . '<strong>aucun moyen de paiement en ligne n\'est actuellement disponible</strong>. '
            . 'Les commandes resteront en attente sans que le client puisse jamais les régler. '
            . 'Activez et configurez une passerelle de paiement (Mobile Money), ou désactivez cette méthode.</p></div>';
    }
}
