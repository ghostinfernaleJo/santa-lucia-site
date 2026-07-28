<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SL_VMail_Frontend {
    public static function init() {
        add_action( 'init', array( __CLASS__, 'register_route' ) );
        add_action( 'template_redirect', array( __CLASS__, 'maybe_render_standalone' ) );
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
        add_shortcode( 'sl_virtual_mail', array( __CLASS__, 'shortcode' ) );
    }

    public static function maybe_start_session() {
        if ( ! session_id() && ! headers_sent() ) {
            session_start();
        }
    }

    public static function register_route() {
        add_rewrite_rule( '^virtumail-ai/?$', 'index.php?sl_vmail_app=1', 'top' );
        add_filter(
            'query_vars',
            static function ( $vars ) {
                $vars[] = 'sl_vmail_app';
                return $vars;
            }
        );
    }

    public static function assets() {
        wp_enqueue_style( 'sl-vmail-front', SL_VMAIL_URL . 'assets/css/front.css', array(), SL_VMAIL_VERSION );
    }

    public static function maybe_render_standalone() {
        $is_app = '1' === get_query_var( 'sl_vmail_app' ) || isset( $_GET['sl_vmail_app'] );
        if ( ! $is_app ) {
            return;
        }

        self::maybe_start_session();
        self::handle_post();
        nocache_headers();
        status_header( 200 );
        ?><!doctype html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo( 'charset' ); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>VirtuMail AI</title>
            <link rel="stylesheet" href="<?php echo esc_url( SL_VMAIL_URL . 'assets/css/front.css?ver=' . SL_VMAIL_VERSION ); ?>">
            <style><?php echo self::critical_css(); ?></style>
        </head>
        <body class="sl-vmail-standalone-body">
            <?php echo self::render_app( true ); ?>
        </body>
        </html><?php
        exit;
    }

    public static function shortcode() {
        self::maybe_start_session();
        self::handle_post();
        return self::render_app( false );
    }

    private static function handle_post() {
        if ( empty( $_POST['sl_vmail_action'] ) ) {
            return;
        }

        $action = sanitize_key( $_POST['sl_vmail_action'] );

        if ( 'login' === $action && check_admin_referer( 'sl_vmail_login', 'sl_vmail_nonce' ) ) {
            $username = isset( $_POST['username'] ) ? sanitize_user( wp_unslash( $_POST['username'] ) ) : '';
            $password = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : '';
            $user     = SL_VMail_Database::find_user_by_username( $username );

            if ( $user && password_verify( $password, $user['password_hash'] ) ) {
                $_SESSION['sl_vmail_user_id'] = (int) $user['id'];
                self::notice( 'success', 'Connexion reussie.' );
                self::redirect_to_app();
            }

            self::notice( 'error', 'Identifiants invalides.' );
        }

        if ( 'register' === $action && check_admin_referer( 'sl_vmail_register', 'sl_vmail_nonce' ) ) {
            $result = SL_VMail_Database::create_virtual_user(
                isset( $_POST['username'] ) ? wp_unslash( $_POST['username'] ) : '',
                isset( $_POST['virtual_email'] ) ? wp_unslash( $_POST['virtual_email'] ) : '',
                isset( $_POST['password'] ) ? wp_unslash( $_POST['password'] ) : ''
            );

            if ( is_wp_error( $result ) ) {
                self::notice( 'error', $result->get_error_message() );
                self::redirect_to_app( array( 'sl_vmail_auth' => 'register' ) );
            }

            self::notice( 'success', 'Compte virtuel cree. Vous pouvez maintenant vous connecter.' );
            self::redirect_to_app( array( 'sl_vmail_auth' => 'login' ) );
        }

        if ( 'logout' === $action && check_admin_referer( 'sl_vmail_logout', 'sl_vmail_nonce' ) ) {
            unset( $_SESSION['sl_vmail_user_id'] );
            self::redirect_to_app();
        }

        if ( 'send' === $action && check_admin_referer( 'sl_vmail_send', 'sl_vmail_nonce' ) ) {
            $user = self::current_user();
            if ( $user ) {
                $result = SL_VMail_SMTP_Mailer::send(
                    $user,
                    isset( $_POST['to'] ) ? wp_unslash( $_POST['to'] ) : '',
                    isset( $_POST['subject'] ) ? wp_unslash( $_POST['subject'] ) : '',
                    isset( $_POST['body'] ) ? wp_unslash( $_POST['body'] ) : ''
                );
                self::notice( is_wp_error( $result ) ? 'error' : 'success', is_wp_error( $result ) ? $result->get_error_message() : 'Message envoye et archive dans les envoyes.' );
                self::redirect_to_app( array( 'sl_vmail_tab' => 'sent' ) );
            }
        }

        if ( 'delete_email' === $action && check_admin_referer( 'sl_vmail_delete_email', 'sl_vmail_nonce' ) ) {
            $user = self::current_user();
            if ( $user ) {
                SL_VMail_Database::delete_user_email( isset( $_POST['email_id'] ) ? absint( $_POST['email_id'] ) : 0, $user['id'] );
                self::notice( 'success', 'Message supprime.' );
                self::redirect_to_app();
            }
        }

        if ( 'simulate_email' === $action && check_admin_referer( 'sl_vmail_simulate_email', 'sl_vmail_nonce' ) ) {
            $user = self::current_user();
            if ( $user ) {
                $raw = self::build_raw_email(
                    isset( $_POST['from'] ) ? wp_unslash( $_POST['from'] ) : '',
                    isset( $_POST['to'] ) ? wp_unslash( $_POST['to'] ) : '',
                    isset( $_POST['subject'] ) ? wp_unslash( $_POST['subject'] ) : '',
                    isset( $_POST['body'] ) ? wp_unslash( $_POST['body'] ) : ''
                );
                $result = SL_VMail_Pipe_Handler::process_raw_email( $raw );
                self::notice( is_wp_error( $result ) ? 'error' : 'success', is_wp_error( $result ) ? $result->get_error_message() : 'Simulation recue et analysee.' );
                self::redirect_to_app( array( 'sl_vmail_tab' => 'tools' ) );
            }
        }

        if ( 'sync_inbound_mailbox' === $action && check_admin_referer( 'sl_vmail_sync_inbound_mailbox', 'sl_vmail_nonce' ) ) {
            $result = SL_VMail_IMAP_Sync::sync();
            if ( is_wp_error( $result ) ) {
                self::notice( 'error', $result->get_error_message() );
            } else {
                self::notice( 'success', sprintf( 'Synchronisation terminée : %d importé(s), %d ignoré(s).', (int) $result['imported'], (int) $result['skipped'] ) );
            }
            self::redirect_to_app( array( 'sl_vmail_tab' => 'tools' ) );
        }

        if ( 'create_user' === $action && current_user_can( 'manage_options' ) && check_admin_referer( 'sl_vmail_create_user', 'sl_vmail_nonce' ) ) {
            $result = SL_VMail_Database::create_virtual_user(
                isset( $_POST['username'] ) ? wp_unslash( $_POST['username'] ) : '',
                isset( $_POST['virtual_email'] ) ? wp_unslash( $_POST['virtual_email'] ) : '',
                isset( $_POST['password'] ) ? wp_unslash( $_POST['password'] ) : ''
            );
            self::notice( is_wp_error( $result ) ? 'error' : 'success', is_wp_error( $result ) ? $result->get_error_message() : 'Utilisateur virtuel cree.' );
            self::redirect_to_app( array( 'sl_vmail_tab' => 'tools' ) );
        }
    }

    private static function render_app( $standalone ) {
        $user = self::current_user();
        ob_start();
        echo '<div class="sl-vmail sl-vmail-app' . ( $standalone ? ' is-standalone' : '' ) . '">';

        if ( ! $user ) {
            echo 'register' === self::auth_screen() ? self::render_register() : self::render_login();
        } else {
            $tab = self::active_tab();
            echo self::render_sidebar( $user, $tab );
            echo '<main class="sl-vmail-stage">';
            echo self::render_topbar( $user, $tab );
            echo '<div class="sl-vmail-main">';
            echo self::render_notice();

            if ( 'sent' === $tab ) {
                echo self::render_sent( $user );
            } elseif ( 'compose' === $tab ) {
                echo self::render_compose_page( $user );
            } elseif ( 'tools' === $tab ) {
                echo self::render_tools( $user );
            } else {
                echo self::render_dashboard( $user );
            }

            echo '</div></main>';
        }

        echo '</div>';
        return ob_get_clean();
    }

    private static function active_tab() {
        $tab = isset( $_GET['sl_vmail_tab'] ) ? sanitize_key( wp_unslash( $_GET['sl_vmail_tab'] ) ) : 'inbox';
        return in_array( $tab, array( 'inbox', 'sent', 'compose', 'tools' ), true ) ? $tab : 'inbox';
    }

    private static function auth_screen() {
        $screen = isset( $_GET['sl_vmail_auth'] ) ? sanitize_key( wp_unslash( $_GET['sl_vmail_auth'] ) ) : 'login';
        return 'register' === $screen ? 'register' : 'login';
    }

    private static function critical_css() {
        $css_file = SL_VMAIL_PATH . 'assets/css/front.css';
        if ( is_readable( $css_file ) ) {
            return (string) file_get_contents( $css_file );
        }

        return '.sl-vmail-app{min-height:100vh;background:#09090b;color:#f8fafc;display:flex}.sl-vmail-login-screen{min-height:100vh;width:100%;display:grid;place-items:center;background:#09090b}.sl-vmail-login-card{background:#121214;border:1px solid rgba(255,255,255,.08);border-radius:16px;padding:28px;color:#f8fafc}';
    }

    private static function current_user() {
        if ( empty( $_SESSION['sl_vmail_user_id'] ) ) {
            return null;
        }

        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM ' . SL_VMail_Database::users_table() . ' WHERE id = %d LIMIT 1',
                absint( $_SESSION['sl_vmail_user_id'] )
            ),
            ARRAY_A
        );
    }

    private static function render_login() {
        ob_start();
        ?>
        <section class="sl-vmail-login-screen">
            <div class="sl-vmail-login-brand">
                <div class="sl-vmail-logo">V</div>
                <h1>VirtuMail AI</h1>
                <p>Messagerie virtuelle illimitee, analyse IA et passerelle SMTP dediee.</p>
            </div>
            <form method="post" class="sl-vmail-login-card">
                <div class="sl-vmail-tabs-lite">
                    <span class="is-active">Se connecter</span>
                    <span>Boite virtuelle</span>
                </div>
                <?php echo self::render_notice(); ?>
                <?php wp_nonce_field( 'sl_vmail_login', 'sl_vmail_nonce' ); ?>
                <input type="hidden" name="sl_vmail_action" value="login">
                <label>Identifiant
                    <input type="text" name="username" autocomplete="username" placeholder="ex: alice" required>
                </label>
                <label>Mot de passe
                    <input type="password" name="password" autocomplete="current-password" placeholder="Votre mot de passe" required>
                </label>
                <button type="submit" class="sl-vmail-primary">Se connecter</button>
                <div class="sl-vmail-help">Les comptes virtuels sont crees par un administrateur depuis l'onglet Outils.</div>
                <a class="sl-vmail-auth-link" href="<?php echo esc_url( self::app_url( array( 'sl_vmail_auth' => 'register' ) ) ); ?>">Créer un nouveau compte virtuel</a>
            </form>
        </section>
        <?php
        return ob_get_clean();
    }

    private static function render_register() {
        ob_start();
        ?>
        <section class="sl-vmail-login-screen">
            <div class="sl-vmail-login-brand">
                <div class="sl-vmail-logo">V</div>
                <h1>Créer un compte</h1>
                <p>Reserve votre adresse virtuelle et accedez a votre boite VirtuMail AI.</p>
            </div>
            <form method="post" class="sl-vmail-login-card">
                <div class="sl-vmail-tabs-lite">
                    <a href="<?php echo esc_url( self::app_url( array( 'sl_vmail_auth' => 'login' ) ) ); ?>">Se connecter</a>
                    <span class="is-active">Créer un compte</span>
                </div>
                <?php echo self::render_notice(); ?>
                <?php wp_nonce_field( 'sl_vmail_register', 'sl_vmail_nonce' ); ?>
                <input type="hidden" name="sl_vmail_action" value="register">
                <label>Identifiant<input type="text" name="username" placeholder="ex: direction" required></label>
                <label>Adresse e-mail virtuelle<input type="email" name="virtual_email" placeholder="direction@complexesantalucia.com" required></label>
                <label>Mot de passe<input type="password" name="password" minlength="10" required></label>
                <button type="submit" class="sl-vmail-primary">Créer mon compte virtuel</button>
                <div class="sl-vmail-help">Mot de passe minimum : 10 caractères. L'adresse doit être unique.</div>
                <a class="sl-vmail-auth-link" href="<?php echo esc_url( self::app_url( array( 'sl_vmail_auth' => 'login' ) ) ); ?>">J’ai déjà un compte</a>
            </form>
        </section>
        <?php
        return ob_get_clean();
    }

    private static function render_admin_first_user_card() {
        ob_start();
        ?>
        <form method="post" class="sl-vmail-login-card sl-vmail-admin-first-card">
            <div class="sl-vmail-tabs-lite"><span class="is-active">Admin</span><span>Premier compte</span></div>
            <p class="sl-vmail-admin-note">Vous etes connecte a WordPress comme administrateur. Creez ici une adresse virtuelle, puis connectez-vous avec ce compte dans VirtuMail AI.</p>
            <?php wp_nonce_field( 'sl_vmail_create_user', 'sl_vmail_nonce' ); ?>
            <input type="hidden" name="sl_vmail_action" value="create_user">
            <label>Identifiant<input type="text" name="username" placeholder="ex: direction" required></label>
            <label>E-mail virtuel<input type="email" name="virtual_email" placeholder="direction@complexesantalucia.com" required></label>
            <label>Mot de passe<input type="password" name="password" minlength="10" required></label>
            <button type="submit" class="sl-vmail-primary">Creer le compte virtuel</button>
        </form>
        <?php
        return ob_get_clean();
    }

    private static function render_sidebar( array $user, $tab ) {
        $stats = SL_VMail_Database::get_stats( $user['id'] );
        $items = array(
            'inbox'   => array( 'Boite de reception', 'MAIL', $stats['unread'] ),
            'sent'    => array( 'Messages envoyes', 'SEND', 0 ),
            'compose' => array( 'Nouveau message', 'EDIT', 0 ),
            'tools'   => array( 'Outils cPanel & IA', 'TERM', 0 ),
        );

        ob_start();
        ?>
        <aside class="sl-vmail-sidebar">
            <div class="sl-vmail-side-head">
                <div class="sl-vmail-logo">V</div>
                <div><strong>VirtuMail AI</strong><span>Passerelle IA</span></div>
            </div>
            <div class="sl-vmail-user-card">
                <div class="sl-vmail-avatar"><?php echo esc_html( strtoupper( substr( $user['username'], 0, 1 ) ) ); ?></div>
                <div><strong><?php echo esc_html( $user['username'] ); ?></strong><span><?php echo esc_html( $user['virtual_email'] ); ?></span></div>
            </div>
            <nav class="sl-vmail-nav">
                <?php foreach ( $items as $key => $item ) : ?>
                    <a class="<?php echo $tab === $key ? 'is-active' : ''; ?>" href="<?php echo esc_url( self::app_url( array( 'sl_vmail_tab' => $key ) ) ); ?>">
                        <span class="sl-vmail-nav-icon"><?php echo esc_html( $item[1] ); ?></span>
                        <span><?php echo esc_html( $item[0] ); ?></span>
                        <?php if ( $item[2] > 0 ) : ?><em><?php echo esc_html( $item[2] ); ?></em><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </nav>
            <div class="sl-vmail-status-card">
                <strong>Statut du piping</strong>
                <span><i></i> Script PHP pret</span>
                <code>mail-pipe.php</code>
            </div>
            <form method="post" class="sl-vmail-logout">
                <?php wp_nonce_field( 'sl_vmail_logout', 'sl_vmail_nonce' ); ?>
                <input type="hidden" name="sl_vmail_action" value="logout">
                <button type="submit">Se deconnecter</button>
            </form>
        </aside>
        <?php
        return ob_get_clean();
    }

    private static function render_topbar( array $user, $tab ) {
        $titles = array(
            'inbox'   => 'Boite de reception',
            'sent'    => 'Messages envoyes',
            'compose' => 'Nouveau message',
            'tools'   => 'Console cPanel & IA',
        );
        $stats = SL_VMail_Database::get_stats( $user['id'] );

        ob_start();
        ?>
        <header class="sl-vmail-topbar">
            <div>
                <span>Tableau de bord</span>
                <strong><?php echo esc_html( $titles[ $tab ] ); ?></strong>
            </div>
            <div class="sl-vmail-metrics">
                <span><?php echo esc_html( $stats['received'] ); ?> recus</span>
                <span><?php echo esc_html( $stats['sent'] ); ?> envoyes</span>
                <span class="is-live">Actif</span>
            </div>
        </header>
        <?php
        return ob_get_clean();
    }

    private static function render_dashboard( array $user ) {
        $search = isset( $_GET['sl_vmail_search'] ) ? sanitize_text_field( wp_unslash( $_GET['sl_vmail_search'] ) ) : '';
        $tone   = isset( $_GET['sl_vmail_tone'] ) ? sanitize_text_field( wp_unslash( $_GET['sl_vmail_tone'] ) ) : 'all';
        $emails = SL_VMail_Database::get_user_emails( $user['id'], array( 'search' => $search, 'tone' => $tone ) );
        $selected_id = isset( $_GET['sl_vmail_message'] ) ? absint( $_GET['sl_vmail_message'] ) : 0;
        $selected = $selected_id ? SL_VMail_Database::get_user_email( $selected_id, $user['id'] ) : ( isset( $emails[0] ) ? $emails[0] : null );

        ob_start();
        ?>
        <div class="sl-vmail-dashboard">
            <section class="sl-vmail-list-pane">
                <div class="sl-vmail-pane-head">
                    <div class="sl-vmail-segment"><span class="is-active">Recus (<?php echo esc_html( count( $emails ) ); ?>)</span><a href="<?php echo esc_url( self::app_url( array( 'sl_vmail_tab' => 'sent' ) ) ); ?>">Envoyes</a></div>
                    <a class="sl-vmail-small-button" href="<?php echo esc_url( self::app_url( array( 'sl_vmail_tab' => 'compose' ) ) ); ?>">Ecrire</a>
                </div>
                <form method="get" class="sl-vmail-filter">
                    <?php echo self::hidden_context_inputs(); ?>
                    <input type="hidden" name="sl_vmail_tab" value="inbox">
                    <input type="search" name="sl_vmail_search" value="<?php echo esc_attr( $search ); ?>" placeholder="Rechercher expediteur, objet, contenu...">
                    <div class="sl-vmail-tone-row">
                        <?php foreach ( array( 'all' => 'Tous', 'Important' => 'Important', 'Urgent' => 'Urgent', 'Facture' => 'Facture', 'Support' => 'Support', 'Spam' => 'Spam' ) as $value => $label ) : ?>
                            <button type="submit" name="sl_vmail_tone" value="<?php echo esc_attr( $value ); ?>" class="<?php echo strtolower( $tone ) === strtolower( $value ) ? 'is-active' : ''; ?>"><?php echo esc_html( $label ); ?></button>
                        <?php endforeach; ?>
                    </div>
                </form>
                <div class="sl-vmail-mail-list">
                    <?php if ( empty( $emails ) ) : ?>
                        <div class="sl-vmail-empty">Aucun e-mail dans cette selection.</div>
                    <?php else : ?>
                        <?php foreach ( $emails as $email ) : ?>
                            <a class="sl-vmail-mail-item <?php echo (int) $email['id'] === (int) ( $selected['id'] ?? 0 ) ? 'is-selected' : ''; ?> <?php echo $email['is_read'] ? '' : 'is-unread'; ?>" href="<?php echo esc_url( self::app_url( array( 'sl_vmail_tab' => 'inbox', 'sl_vmail_message' => (int) $email['id'], 'sl_vmail_search' => $search, 'sl_vmail_tone' => $tone ) ) ); ?>">
                                <span><strong><?php echo esc_html( self::sender_label( $email['sender'] ) ); ?></strong><small><?php echo esc_html( mysql2date( 'd M H:i', $email['received_at'] ) ); ?></small></span>
                                <b><?php echo esc_html( $email['subject'] ?: '(sans objet)' ); ?></b>
                                <p><?php echo esc_html( wp_trim_words( $email['body_text'], 14 ) ); ?></p>
                                <em class="<?php echo esc_attr( self::tone_class( $email['ai_tone'] ) ); ?>"><?php echo esc_html( $email['ai_tone'] ?: 'Important' ); ?></em>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>
            <section class="sl-vmail-detail-pane">
                <?php echo $selected ? self::render_received_detail( $user, $selected ) : self::render_blank_state(); ?>
            </section>
        </div>
        <?php
        return ob_get_clean();
    }

    private static function render_received_detail( array $user, array $email ) {
        ob_start();
        ?>
        <div class="sl-vmail-message-head">
            <div>
                <h1><?php echo esc_html( $email['subject'] ?: '(sans objet)' ); ?></h1>
                <p>De <strong><?php echo esc_html( $email['sender'] ); ?></strong> pour <strong><?php echo esc_html( $user['virtual_email'] ); ?></strong></p>
            </div>
            <div class="sl-vmail-actions">
                <a href="<?php echo esc_url( self::app_url( array( 'sl_vmail_tab' => 'compose', 'reply_to' => (int) $email['id'] ) ) ); ?>">Repondre</a>
                <form method="post">
                    <?php wp_nonce_field( 'sl_vmail_delete_email', 'sl_vmail_nonce' ); ?>
                    <input type="hidden" name="sl_vmail_action" value="delete_email">
                    <input type="hidden" name="email_id" value="<?php echo esc_attr( $email['id'] ); ?>">
                    <button type="submit">Supprimer</button>
                </form>
            </div>
        </div>
        <div class="sl-vmail-bento">
            <article class="sl-vmail-card sl-vmail-body-card">
                <header><span>Contenu de l'e-mail</span><time><?php echo esc_html( mysql2date( 'd/m/Y H:i', $email['received_at'] ) ); ?></time></header>
                <div class="sl-vmail-email-body">
                    <?php echo ! empty( $email['body_html'] ) ? wp_kses_post( $email['body_html'] ) : wpautop( esc_html( $email['body_text'] ) ); ?>
                </div>
            </article>
            <aside class="sl-vmail-card sl-vmail-ai-card">
                <strong>Resume IA</strong>
                <p><?php echo esc_html( $email['ai_summary'] ?: 'Aucun resume disponible.' ); ?></p>
                <small>Gemini JSON</small>
            </aside>
            <aside class="sl-vmail-card sl-vmail-tone-card">
                <span>Sentiment / categorie</span>
                <strong class="<?php echo esc_attr( self::tone_class( $email['ai_tone'] ) ); ?>"><?php echo esc_html( $email['ai_tone'] ?: 'Important' ); ?></strong>
                <small>Priorite <?php echo 'Urgent' === $email['ai_tone'] ? 'haute' : 'normale'; ?></small>
            </aside>
            <aside class="sl-vmail-card sl-vmail-draft-card">
                <div><strong>Reponse suggeree</strong><a href="<?php echo esc_url( self::app_url( array( 'sl_vmail_tab' => 'compose', 'reply_to' => (int) $email['id'] ) ) ); ?>">Modifier le brouillon</a></div>
                <p><?php echo esc_html( $email['ai_suggested_reply'] ?: 'Aucune suggestion de reponse.' ); ?></p>
            </aside>
        </div>
        <?php
        return ob_get_clean();
    }

    private static function render_sent( array $user ) {
        $emails = SL_VMail_Database::get_user_sent_emails( $user['id'] );
        $selected_id = isset( $_GET['sl_vmail_sent'] ) ? absint( $_GET['sl_vmail_sent'] ) : 0;
        $selected = $selected_id ? SL_VMail_Database::get_user_sent_email( $selected_id, $user['id'] ) : ( isset( $emails[0] ) ? $emails[0] : null );

        ob_start();
        ?>
        <div class="sl-vmail-dashboard">
            <section class="sl-vmail-list-pane">
                <div class="sl-vmail-pane-head"><div class="sl-vmail-segment"><a href="<?php echo esc_url( self::app_url() ); ?>">Recus</a><span class="is-active">Envoyes (<?php echo esc_html( count( $emails ) ); ?>)</span></div></div>
                <div class="sl-vmail-mail-list">
                    <?php if ( empty( $emails ) ) : ?>
                        <div class="sl-vmail-empty">Aucun message envoye.</div>
                    <?php else : foreach ( $emails as $email ) : ?>
                        <a class="sl-vmail-mail-item <?php echo (int) $email['id'] === (int) ( $selected['id'] ?? 0 ) ? 'is-selected' : ''; ?>" href="<?php echo esc_url( self::app_url( array( 'sl_vmail_tab' => 'sent', 'sl_vmail_sent' => (int) $email['id'] ) ) ); ?>">
                            <span><strong>A: <?php echo esc_html( $email['recipient'] ); ?></strong><small><?php echo esc_html( mysql2date( 'd M H:i', $email['sent_at'] ) ); ?></small></span>
                            <b><?php echo esc_html( $email['subject'] ); ?></b>
                            <p><?php echo esc_html( wp_trim_words( $email['body'], 14 ) ); ?></p>
                        </a>
                    <?php endforeach; endif; ?>
                </div>
            </section>
            <section class="sl-vmail-detail-pane">
                <?php echo $selected ? self::render_sent_detail( $user, $selected ) : self::render_blank_state(); ?>
            </section>
        </div>
        <?php
        return ob_get_clean();
    }

    private static function render_sent_detail( array $user, array $email ) {
        $headers = json_decode( $email['headers_used'], true );
        ob_start();
        ?>
        <div class="sl-vmail-message-head"><div><h1><?php echo esc_html( $email['subject'] ); ?></h1><p>Message envoye a <strong><?php echo esc_html( $email['recipient'] ); ?></strong></p></div></div>
        <div class="sl-vmail-card sl-vmail-sent-detail">
            <dl><dt>From</dt><dd><?php echo esc_html( $headers['From'] ?? $user['virtual_email'] ); ?></dd><dt>Reply-To</dt><dd><?php echo esc_html( $headers['Reply-To'] ?? $user['virtual_email'] ); ?></dd><dt>Relais SMTP</dt><dd><?php echo esc_html( $headers['SMTP-User'] ?? 'gateway@complexesantalucia.com' ); ?></dd></dl>
            <div class="sl-vmail-email-body"><?php echo wpautop( esc_html( $email['body'] ) ); ?></div>
        </div>
        <?php
        return ob_get_clean();
    }

    private static function render_compose_page( array $user ) {
        $reply = null;
        if ( isset( $_GET['reply_to'] ) ) {
            $reply = SL_VMail_Database::get_user_email( absint( $_GET['reply_to'] ), $user['id'] );
        }
        $to      = $reply ? $reply['sender'] : '';
        $subject = $reply ? ( 0 === stripos( $reply['subject'], 'Re:' ) ? $reply['subject'] : 'Re: ' . $reply['subject'] ) : '';
        $body    = $reply ? $reply['ai_suggested_reply'] : '';

        ob_start();
        ?>
        <section class="sl-vmail-compose-screen">
            <div class="sl-vmail-card sl-vmail-compose-card">
                <header><a href="<?php echo esc_url( self::app_url() ); ?>">Retour a la boite</a><strong><?php echo $reply ? 'Repondre au message' : 'Nouveau message'; ?></strong></header>
                <div class="sl-vmail-gateway">
                    <strong>Passerelle cPanel active</strong>
                    <span>From: <?php echo esc_html( SL_VMail_Env::get( 'SL_VMAIL_FROM_NAME', 'VirtuMail AI' ) ); ?> &lt;<?php echo esc_html( SL_VMail_Env::get( 'SL_VMAIL_SMTP_USER', 'main@complexesantalucia.com' ) ); ?>&gt;</span>
                    <span>Reply-To: <?php echo esc_html( $user['virtual_email'] ); ?></span>
                </div>
                <form method="post">
                    <?php wp_nonce_field( 'sl_vmail_send', 'sl_vmail_nonce' ); ?>
                    <input type="hidden" name="sl_vmail_action" value="send">
                    <label>Destinataire<input type="email" name="to" value="<?php echo esc_attr( $to ); ?>" required></label>
                    <label>Objet<input type="text" name="subject" value="<?php echo esc_attr( $subject ); ?>" required></label>
                    <?php if ( $reply && $reply['ai_suggested_reply'] ) : ?><div class="sl-vmail-ai-inline">Brouillon IA insere automatiquement. Vous pouvez le modifier avant envoi.</div><?php endif; ?>
                    <label>Message<textarea name="body" rows="12" required><?php echo esc_textarea( $body ); ?></textarea></label>
                    <div class="sl-vmail-form-actions"><a href="<?php echo esc_url( self::app_url() ); ?>">Annuler</a><button type="submit">Envoyer le message</button></div>
                </form>
            </div>
        </section>
        <?php
        return ob_get_clean();
    }

    private static function render_tools( array $user ) {
        $users = current_user_can( 'manage_options' ) ? SL_VMail_Database::get_all_virtual_users() : array();
        ob_start();
        ?>
        <div class="sl-vmail-tools">
            <section class="sl-vmail-card">
                <h2>Simulateur Mail Piping</h2>
                <p>Injectez un faux e-mail dans le meme traitement que cPanel: parsing, routage, analyse IA, insertion SQL.</p>
                <form method="post" class="sl-vmail-grid-form">
                    <?php wp_nonce_field( 'sl_vmail_simulate_email', 'sl_vmail_nonce' ); ?>
                    <input type="hidden" name="sl_vmail_action" value="simulate_email">
                    <label>De<input type="email" name="from" value="client@example.com" required></label>
                    <label>A<input type="email" name="to" value="<?php echo esc_attr( $user['virtual_email'] ); ?>" required></label>
                    <label class="is-wide">Objet<input type="text" name="subject" value="Demande urgente de devis" required></label>
                    <label class="is-wide">Corps<textarea name="body" rows="5" required>Bonjour, pouvez-vous me repondre rapidement avec une proposition professionnelle ?</textarea></label>
                    <button type="submit">Simuler la reception</button>
                </form>
            </section>

            <section class="sl-vmail-card">
                <h2>Synchroniser la boîte réelle</h2>
                <p>Importe les nouveaux messages non lus de <code><?php echo esc_html( SL_VMail_Env::get( 'SL_VMAIL_INBOUND_USER', 'ngoufo@complexesantalucia.com' ) ); ?></code> vers la boîte VirtuMail correspondante.</p>
                <form method="post">
                    <?php wp_nonce_field( 'sl_vmail_sync_inbound_mailbox', 'sl_vmail_nonce' ); ?>
                    <input type="hidden" name="sl_vmail_action" value="sync_inbound_mailbox">
                    <button type="submit" class="sl-vmail-primary">Synchroniser maintenant</button>
                </form>
            </section>

            <?php if ( current_user_can( 'manage_options' ) ) : ?>
                <section class="sl-vmail-card">
                    <h2>Creer un utilisateur virtuel</h2>
                    <form method="post" class="sl-vmail-grid-form">
                        <?php wp_nonce_field( 'sl_vmail_create_user', 'sl_vmail_nonce' ); ?>
                        <input type="hidden" name="sl_vmail_action" value="create_user">
                        <label>Identifiant<input type="text" name="username" required></label>
                        <label>E-mail virtuel<input type="email" name="virtual_email" placeholder="nom@complexesantalucia.com" required></label>
                        <label class="is-wide">Mot de passe<input type="password" name="password" minlength="10" required></label>
                        <button type="submit">Creer le compte</button>
                    </form>
                    <div class="sl-vmail-user-table">
                        <?php foreach ( $users as $virtual_user ) : ?>
                            <span><?php echo esc_html( $virtual_user['username'] ); ?></span><code><?php echo esc_html( $virtual_user['virtual_email'] ); ?></code>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <section class="sl-vmail-card sl-vmail-doc">
                <h2>Guide cPanel</h2>
                <ol>
                    <li>Activez le plugin pour creer les tables SQL.</li>
                    <li>Renseignez le fichier <code>.env</code> avec Gemini, SMTP et <code>SL_VMAIL_PIPE_SECRET</code>.</li>
                    <li>Dans cPanel, ouvrez <code>Default Address</code> / <code>Adresse par defaut</code> du domaine.</li>
                    <li>Choisissez <code>Pipe to a Program</code> pour activer le catch-all.</li>
                    <li>Commande: <code><?php echo esc_html( self::pipe_command() ); ?></code></li>
                </ol>
            </section>
        </div>
        <?php
        return ob_get_clean();
    }

    private static function render_blank_state() {
        return '<div class="sl-vmail-blank"><strong>Aucun message selectionne</strong><p>Choisissez un message dans la liste pour afficher son contenu, son analyse IA et les actions disponibles.</p></div>';
    }

    private static function render_notice() {
        if ( empty( $_SESSION['sl_vmail_notice'] ) ) {
            return '';
        }

        list( $type, $message ) = $_SESSION['sl_vmail_notice'];
        unset( $_SESSION['sl_vmail_notice'] );

        return '<div class="sl-vmail-notice sl-vmail-' . esc_attr( $type ) . '">' . esc_html( $message ) . '</div>';
    }

    private static function notice( $type, $message ) {
        $_SESSION['sl_vmail_notice'] = array( $type, $message );
    }

    private static function build_raw_email( $from, $to, $subject, $body ) {
        return 'From: <' . sanitize_email( $from ) . ">\n"
            . 'To: <' . sanitize_email( $to ) . ">\n"
            . 'Subject: ' . sanitize_text_field( $subject ) . "\n"
            . "Content-Type: text/plain; charset=UTF-8\n\n"
            . sanitize_textarea_field( $body );
    }

    private static function tone_class( $tone ) {
        $tone = strtolower( (string) $tone );
        if ( in_array( $tone, array( 'urgent', 'facture', 'support', 'spam' ), true ) ) {
            return 'tone-' . $tone;
        }
        return 'tone-important';
    }

    private static function sender_label( $sender ) {
        if ( preg_match( '/"?([^"<]+)"?\s*<[^>]+>/', $sender, $matches ) ) {
            return trim( $matches[1] );
        }
        return $sender;
    }

    private static function app_url( $args = array() ) {
        $base = get_query_var( 'sl_vmail_app' ) || isset( $_GET['sl_vmail_app'] ) ? home_url( '/?sl_vmail_app=1' ) : remove_query_arg( array( 'sl_vmail_message', 'sl_vmail_sent', 'reply_to', 'sl_vmail_search', 'sl_vmail_tone', 'sl_vmail_tab' ) );
        return add_query_arg( $args, $base );
    }

    private static function hidden_context_inputs() {
        return isset( $_GET['sl_vmail_app'] ) ? '<input type="hidden" name="sl_vmail_app" value="1">' : '';
    }

    private static function pipe_command() {
        $secret = SL_VMail_Env::get( 'SL_VMAIL_PIPE_SECRET', 'VOTRE_SECRET' );
        return '/usr/local/bin/php /home/USER/public_html/wp-content/plugins/sl-virtual-mail-ai/bin/mail-pipe.php ' . $secret;
    }

    private static function redirect_to_app( $args = array() ) {
        wp_safe_redirect( self::app_url( $args ) );
        exit;
    }
}
