=== MMGate for WooCommerce ===
Contributors: complexesantalucia
Tags: woocommerce, payment gateway, mobile money, mtn momo, orange money, cameroun
Requires at least: 5.8
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Encaissez MTN Mobile Money et Orange Money sur WooCommerce via MMGate. Une seule intégration pour les deux opérateurs.

== Description ==

Ce plugin ajoute une passerelle de paiement WooCommerce branchée sur l'API [MMGate](https://www.mmgate.org). Le client saisit son numéro Mobile Money, valide le débit sur son téléphone, et la commande est encaissée automatiquement dès confirmation.

= Fonctionnement =

MMGate **n'appelle pas votre site en retour** : il n'existe pas de webhook. Le plugin interroge donc l'API de suivi (ETATO) jusqu'à un statut définitif, par deux chemins complémentaires :

* un écran d'attente qui interroge le serveur toutes les 3 secondes tant que le client regarde la page ;
* une tâche planifiée qui rattrape les commandes dont le client a fermé l'onglet.

Une commande n'est encaissée **que** sur confirmation effective (ETATO 400). Aucune livraison n'est déclenchée sur une simple initiation.

= Sécurité =

L'API MMGate fait transiter le code partenaire, l'utilisateur et le mot de passe **dans le chemin de l'URL**. C'est leur conception. En conséquence, ce plugin :

* ne journalise **jamais** une URL complète (les identifiants sont systématiquement masqués) ;
* permet de définir les identifiants dans `wp-config.php` plutôt qu'en base de données.

Pour le stockage recommandé, ajoutez à votre `wp-config.php` :

`define( 'MMGATE_CDPRT', 'votre_code_partenaire' );`
`define( 'MMGATE_USR',   'votre_utilisateur_api' );`
`define( 'MMGATE_PWD',   'votre_mot_de_passe_api' );`
`define( 'MMGATE_TOKEN', 'votre_token_partenaire' );`

Ces constantes prennent le dessus sur les champs de l'écran de réglages, ne s'affichent nulle part dans l'administration, et ne partent pas dans un export de base de données.

= Fonction SMS =

Le même compte partenaire permet d'envoyer des SMS (5 FCFA l'unité, débités du solde partenaire). Le plugin expose une fonction publique :

`mmgate_send_sms( '6XXXXXXXX', 'Votre commande est prete' );`

Le texte est translittéré en ASCII avant envoi : certains téléphones forcent un encodage GSM 7 bits qui abîme les accents.

== Installation ==

1. Installez et activez le plugin.
2. Rendez-vous dans WooCommerce → Réglages → Paiements → Mobile Money (MMGate).
3. Renseignez vos identifiants partenaire (ou définissez-les dans `wp-config.php`).
4. Cliquez sur **Tester la connexion** : cette vérification consulte votre solde et ne déplace aucun argent.
5. Activez la passerelle, puis réalisez une transaction réelle de faible montant avant d'ouvrir à vos clients.

== Frequently Asked Questions ==

= Le test de connexion réussit mais le paiement échoue =

Le test de connexion valide CDPRT, USR et PWD via l'endpoint SOLDE, qui n'utilise pas le token. Les paiements, eux, exigent en plus un `X-Partner-Token` valide. Un token absent ou périmé se manifeste exactement ainsi.

= Qu'est-ce que le réglage « Endpoint d'encaissement » ? =

La documentation MMGate se contredit sur le sens du flux : l'onglet PAIEMENTP annonce un envoi *vers* le numéro du client, alors que le guide décrit ce numéro comme « bénéficiaire du débit ». `PAIEMENTP` est le bon choix pour encaisser — c'est aussi le seul des deux endpoints qui n'expose pas d'erreur « solde partenaire insuffisant », ce qui n'aurait aucun sens pour une opération qui dépense votre solde. Ne modifiez ce réglage que si MMGate vous le demande, et **validez toujours par une transaction réelle de faible montant.**

= Pourquoi une commande reste-t-elle « en attente » ? =

Parce que le client n'a pas encore validé sur son téléphone. Au-delà de 15 minutes sans confirmation, la commande passe en échec. Les tâches planifiées WordPress étant déclenchées par le trafic, un site sans visiteur peut retarder ce basculement ; un vrai cron système est recommandé en production.

= Le plugin gère-t-il les remboursements ? =

Non. L'endpoint de décaissement (DEPOTP) existe côté MMGate mais n'est pas branché sur le flux de remboursement WooCommerce dans cette version.

== Changelog ==

= 1.0.0 =
* Version initiale : passerelle d'encaissement MTN MoMo et Orange Money.
* Suivi par interrogation (écran d'attente + tâche planifiée de rattrapage), MMGate n'exposant pas de webhook.
* Gestion de l'anti-doublon MMGate (ETAT 600) avec rejeu confirmé.
* Identifiants définissables via constantes `wp-config.php`.
* Test de connexion sans mouvement d'argent.
* Compatible HPOS.
* Fonction d'envoi de SMS.
