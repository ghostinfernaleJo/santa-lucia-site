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
}
