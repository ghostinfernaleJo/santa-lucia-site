<?php
if ( ! defined( 'ABSPATH' ) ) exit;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Repeater;

if ( ! function_exists( 'sl_pm_svg_placeholder' ) ) {
    function sl_pm_svg_placeholder( $title, $subtitle = 'Santa Lucia', $accent = '#E44B97' ) {
        $title = htmlspecialchars( $title, ENT_QUOTES, 'UTF-8' );
        $subtitle = htmlspecialchars( $subtitle, ENT_QUOTES, 'UTF-8' );
        $accent = htmlspecialchars( $accent, ENT_QUOTES, 'UTF-8' );
        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 720 480" role="img">
  <defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#071832"/><stop offset=".65" stop-color="#10284A"/><stop offset="1" stop-color="{$accent}"/></linearGradient></defs>
  <rect width="720" height="480" fill="url(#g)"/><circle cx="606" cy="96" r="130" fill="#F6C445" opacity=".22"/><circle cx="92" cy="392" r="150" fill="#E44B97" opacity=".18"/>
  <rect x="92" y="76" width="536" height="280" fill="none" stroke="#fff" stroke-opacity=".12"/>
  <rect x="250" y="110" width="220" height="210" rx="24" fill="#fff"/><rect x="250" y="110" width="220" height="68" rx="24" fill="{$accent}"/>
  <text x="360" y="153" text-anchor="middle" font-family="Arial,sans-serif" font-size="23" font-weight="900" fill="#fff">SANTA LUCIA</text>
  <text x="360" y="242" text-anchor="middle" font-family="Arial,sans-serif" font-size="22" font-weight="900" fill="#071832">MAISON</text>
  <text x="54" y="404" font-family="Arial,sans-serif" font-size="38" font-weight="900" fill="#fff">{$title}</text>
  <text x="54" y="438" font-family="Arial,sans-serif" font-size="17" font-weight="800" letter-spacing="3" fill="#F6C445">{$subtitle}</text>
</svg>
SVG;
        return 'data:image/svg+xml;charset=UTF-8,' . rawurlencode( $svg );
    }
}

if ( ! function_exists( 'sl_pm_upload_url' ) ) {
    function sl_pm_upload_url( $relative_path ) {
        return content_url( '/uploads/' . ltrim( $relative_path, '/' ) );
    }
}

abstract class SL_PM_Base_Page_Widget extends Widget_Base {
    protected function image_url( $image, $fallback ) {
        if ( ! is_array( $image ) ) {
            return $fallback;
        }

        if ( ! empty( $image['id'] ) ) {
            $url = wp_get_attachment_image_url( (int) $image['id'], 'large' );
            if ( $url ) return $url;
        }
        return ! empty( $image['url'] ) ? $image['url'] : $fallback;
    }

    protected function print_base_styles() {
        ?>
        <style>
        .sl-el-page{--sl-pink:#E63F93;--sl-yellow:#F8B91E;--sl-ink:#071832;--sl-blue:#10284A;--sl-muted:#5E6876;--sl-line:#E7EAF0;--sl-soft:#F7F8FB;font-family:var(--theme-body-font,var(--bs-body-font-family,Arial,sans-serif));color:var(--sl-ink);background:#fff;overflow:hidden}.sl-el-page *{box-sizing:border-box}.sl-el-container{max-width:1180px;margin:0 auto;padding:0 48px;position:relative;z-index:1}.sl-el-label{display:block;margin:0 0 14px;color:var(--sl-pink);font-size:10px;font-weight:800;letter-spacing:2px;text-transform:uppercase}.sl-el-label:before{content:'';display:inline-block;width:34px;height:2px;margin-right:12px;vertical-align:middle;background:var(--sl-yellow)}.sl-el-title{font-family:var(--theme-heading-font,var(--theme-body-font,Arial,sans-serif));font-size:clamp(34px,4.1vw,56px);font-weight:800;line-height:1.02;letter-spacing:0;margin:0 0 22px}.sl-el-title em{font-style:normal;color:var(--sl-pink)}.sl-el-copy{font-size:16px;line-height:1.85;color:var(--sl-muted);max-width:760px}.sl-el-copy p{margin:0 0 16px}
        .sl-ref-hero{min-height:470px;display:flex;align-items:center;text-align:center;color:#fff;background-size:cover;background-position:center;position:relative}.sl-ref-hero:before{content:'';position:absolute;inset:0;background:rgba(7,24,50,.42)}.sl-ref-hero h1{max-width:900px;margin:0 auto 18px;font-size:clamp(42px,5.2vw,72px);line-height:1.02;font-weight:800;color:#fff}.sl-ref-hero p{max-width:640px;margin:0 auto;color:rgba(255,255,255,.92);font-size:16px;line-height:1.7}.sl-ref-band{padding:34px 0;background:#fff}.sl-ref-kicker{font-size:15px;color:var(--sl-muted);margin:0}.sl-ref-kicker strong{font-size:24px;color:var(--sl-ink);font-weight:800}.sl-ref-full-image{width:100%;height:min(58vw,760px);object-fit:cover;object-position:center;display:block}.sl-ref-image-section{background:#fff;overflow:hidden}.sl-ref-text-section{padding:52px 0 58px;background:#fff}.sl-ref-text-section.alt{background:#f7f8fb}.sl-ref-editorial{display:grid;grid-template-columns:92px minmax(0,1fr);gap:28px;align-items:start}.sl-ref-num{font-size:26px;font-weight:800;color:#071832;line-height:1}.sl-ref-editorial h2{font-size:28px;line-height:1.12;margin:0 0 16px;font-weight:800}.sl-ref-editorial .sl-el-copy{max-width:none;font-size:15px;line-height:1.8}.sl-ref-list{columns:2;list-style:disc;margin:18px 0 0 18px;color:var(--sl-muted);font-size:15px;line-height:1.8}.sl-ref-values{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:26px;margin-top:34px}.sl-ref-values h3{font-size:24px;margin:0 0 12px;font-weight:800}
        .sl-el-hero{min-height:430px;display:flex;align-items:center;color:#fff;padding:86px 0;position:relative;background-size:cover;background-position:center}.sl-el-hero:before{content:'';position:absolute;inset:0;background:linear-gradient(90deg,rgba(7,24,50,.86),rgba(7,24,50,.58) 45%,rgba(230,63,147,.20))}.sl-el-hero:after{content:'';position:absolute;left:0;right:0;bottom:0;height:7px;background:linear-gradient(90deg,var(--sl-pink),var(--sl-yellow),#39B5D8)}.sl-el-hero-grid{display:block;max-width:720px}.sl-el-hero h1{font-family:var(--theme-heading-font,var(--theme-body-font,Arial,sans-serif));font-size:clamp(44px,6vw,76px);font-weight:800;line-height:1.02;letter-spacing:0;margin:0 0 18px}.sl-el-hero h1 em{font-style:normal;color:var(--sl-yellow)}.sl-el-hero p{font-size:17px;line-height:1.75;color:rgba(255,255,255,.88);max-width:650px;margin:0}.sl-el-hero .sl-el-media{display:none}
        .sl-el-stats{display:flex;gap:0;margin-top:32px;background:#fff;color:var(--sl-ink);box-shadow:0 22px 45px rgba(7,24,50,.18);max-width:560px}.sl-el-stat{flex:1;padding:22px 24px;border-right:1px solid var(--sl-line)}.sl-el-stat:last-child{border-right:none}.sl-el-stat strong{display:block;color:var(--sl-pink);font-size:34px;font-weight:800;line-height:1}.sl-el-stat span{display:block;margin-top:7px;color:var(--sl-muted);font-size:10px;font-weight:800;letter-spacing:1.4px;text-transform:uppercase}
        .sl-el-section{padding:96px 0;background:#fff;position:relative}.sl-el-section:nth-of-type(2n+1):not(.sl-el-dark){background:var(--sl-soft)}.sl-el-media{background:#f1f3f6;overflow:hidden;min-height:260px;box-shadow:none}.sl-el-media img{width:100%;height:100%;object-fit:cover;display:block}.sl-el-split{display:grid;grid-template-columns:minmax(0,.88fr) minmax(420px,1fr);gap:72px;align-items:center}.sl-el-split>div:first-child{padding-left:32px;border-left:3px solid var(--sl-pink)}.sl-el-split .sl-el-media{aspect-ratio:4/3}
        .sl-el-editorial{display:grid;grid-template-columns:150px minmax(0,1fr);gap:56px;align-items:start}.sl-el-section-num{font-family:var(--theme-heading-font,var(--theme-body-font,Arial,sans-serif));font-size:52px;font-weight:800;line-height:1;color:var(--sl-pink);position:sticky;top:30px}.sl-el-section-num span{display:block;margin-top:10px;width:54px;height:3px;background:var(--sl-yellow)}.sl-el-editorial-body{max-width:920px}.sl-el-editorial-media{margin:38px 0 0;aspect-ratio:16/7;min-height:360px}.sl-el-editorial-grid{display:grid;grid-template-columns:1fr 1fr;gap:32px;margin-top:40px}.sl-el-text-panel{background:#fff;border:1px solid var(--sl-line);padding:34px}.sl-el-text-panel h3{font-size:27px;font-weight:800;margin:0 0 15px}.sl-el-text-panel .sl-el-copy{font-size:15px;line-height:1.8}
        .sl-el-grid-3{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:22px;margin-top:40px}.sl-el-card{display:block;background:#fff;border:1px solid var(--sl-line);padding:28px;color:inherit;text-decoration:none;box-shadow:0 10px 28px rgba(7,24,50,.045);transition:transform .22s,box-shadow .22s,border-color .22s}.sl-el-card:hover{transform:translateY(-4px);box-shadow:0 18px 36px rgba(7,24,50,.10);border-color:rgba(230,63,147,.28)}.sl-el-card-img{aspect-ratio:16/10;margin:-28px -28px 22px;background:#f1f3f6;overflow:hidden}.sl-el-card-img img{width:100%;height:100%;object-fit:cover;display:block}.sl-el-card h3{font-family:var(--theme-heading-font,var(--theme-body-font,Arial,sans-serif));font-size:21px;font-weight:800;line-height:1.18;margin:0 0 10px}.sl-el-card p{font-size:14px;line-height:1.7;color:var(--sl-muted);margin:0}.sl-el-tag{display:inline-block;margin-top:20px;background:var(--sl-yellow);color:var(--sl-ink);padding:7px 11px;font-size:10px;font-weight:800;letter-spacing:1.2px;text-transform:uppercase}
        .sl-el-dark{background:var(--sl-ink);color:#fff}.sl-el-dark .sl-el-title{color:#fff}.sl-el-dark .sl-el-card{background:#132642;border-color:rgba(255,255,255,.10);box-shadow:none}.sl-el-dark .sl-el-card p{color:rgba(255,255,255,.68)}.sl-el-dark .sl-el-card h3{color:#fff}.sl-el-dark .sl-el-tag{background:var(--sl-yellow);color:var(--sl-ink)}
        .sl-el-gallery{display:grid;grid-template-columns:1.4fr 1fr 1fr;grid-template-rows:250px 250px;gap:18px;margin-top:42px}.sl-el-gallery .sl-el-media:first-child{grid-row:1/3}.sl-el-gallery .sl-el-media{min-height:auto}.sl-el-commit{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:20px;margin-top:42px}.sl-el-commit .sl-el-card{display:flex;gap:20px;align-items:flex-start}.sl-el-num{min-width:52px;color:var(--sl-pink);font-size:35px;font-weight:800;line-height:1}.sl-el-network{display:grid;grid-template-columns:1fr 1fr;gap:28px;margin-top:42px}.sl-el-city{background:#fff;border:1px solid var(--sl-line);box-shadow:0 10px 28px rgba(7,24,50,.045)}.sl-el-city h3{margin:0;padding:18px 24px;background:var(--sl-ink);color:#fff;font-size:22px;font-weight:800}.sl-el-city ul{list-style:none;margin:0;padding:16px 24px}.sl-el-city li{padding:10px 0;border-bottom:1px solid var(--sl-line);font-size:14px;color:var(--sl-muted)}.sl-el-city li:before{content:'+';color:var(--sl-pink);font-weight:800;margin-right:10px}.sl-el-city li:last-child{border-bottom:none}
        .sl-el-cta{background:var(--sl-pink);color:#fff;text-align:center;padding:76px 0}.sl-el-cta .sl-el-title{color:#fff}.sl-el-cta p{max-width:680px;margin:0 auto 24px;color:rgba(255,255,255,.88);line-height:1.8}.sl-el-btn{display:inline-flex;align-items:center;justify-content:center;background:#fff;color:var(--sl-pink);padding:15px 30px;text-decoration:none;font-size:11px;font-weight:800;letter-spacing:2px;text-transform:uppercase}.sl-el-btn:hover{background:var(--sl-yellow);color:var(--sl-ink)}
        @media(max-width:960px){.sl-el-container{padding:0 26px}.sl-el-split,.sl-el-grid-3,.sl-el-network{grid-template-columns:1fr 1fr}.sl-el-split{grid-template-columns:1fr;gap:40px}.sl-el-gallery{grid-template-columns:1fr 1fr;grid-template-rows:auto}.sl-el-gallery .sl-el-media:first-child{grid-row:auto}.sl-el-editorial{grid-template-columns:1fr;gap:18px}.sl-el-section-num{position:static;font-size:34px}.sl-el-editorial-grid{grid-template-columns:1fr}.sl-el-editorial-media{min-height:280px}.sl-ref-values{grid-template-columns:1fr}.sl-ref-editorial{grid-template-columns:1fr}.sl-ref-list{columns:1}}@media(max-width:640px){.sl-el-container{padding:0 18px}.sl-el-hero{min-height:430px;padding:60px 0}.sl-el-section{padding:64px 0}.sl-el-grid-3,.sl-el-network,.sl-el-commit{grid-template-columns:1fr}.sl-el-hero h1{font-size:38px}.sl-el-title{font-size:31px}.sl-el-stats{max-width:none}.sl-el-stat{padding:15px 10px}.sl-el-stat strong{font-size:28px}.sl-el-stat span{font-size:8px;letter-spacing:.7px}.sl-el-split>div:first-child{padding-left:20px}.sl-el-gallery{grid-template-columns:1fr}.sl-el-gallery .sl-el-media{min-height:230px}.sl-el-commit .sl-el-card{display:block}.sl-el-num{margin-bottom:12px}.sl-el-btn{width:100%}.sl-el-editorial-media{aspect-ratio:4/3;min-height:220px}.sl-el-text-panel{padding:24px}.sl-ref-hero{min-height:360px}.sl-ref-hero h1{font-size:36px}.sl-ref-text-section{padding:42px 0}.sl-ref-editorial h2{font-size:24px}}
        </style>
        <?php
    }
}

class SL_Apropos_Complete_Widget extends SL_PM_Base_Page_Widget {
    public function get_name(){ return 'sl_apropos_complete'; }
    public function get_title(){ return 'Page À propos complète'; }
    public function get_icon(){ return 'eicon-site-identity'; }
    public function get_categories(){ return [ 'santa-lucia' ]; }

    protected function register_controls() {
        $this->start_controls_section('hero', ['label'=>'Hero', 'tab'=>Controls_Manager::TAB_CONTENT]);
        $this->add_control('hero_title', ['label'=>'Titre', 'type'=>Controls_Manager::TEXTAREA, 'default'=>'Voulez-vous nous connaître ?']);
        $this->add_control('hero_text', ['label'=>'Texte', 'type'=>Controls_Manager::TEXTAREA, 'default'=>"Laissez-nous vous présenter le Complexe Santa Lucia, acteur majeur de la distribution au Cameroun, offrant des produits et services diversifiés depuis 2006."]);
        $this->add_control('hero_image', ['label'=>'Image hero', 'type'=>Controls_Manager::MEDIA]);
        $this->end_controls_section();

        $stats = new Repeater();
        $stats->add_control('value', ['label'=>'Valeur', 'type'=>Controls_Manager::TEXT, 'default'=>'18']);
        $stats->add_control('label', ['label'=>'Label', 'type'=>Controls_Manager::TEXT, 'default'=>'Agences']);
        $this->start_controls_section('stats', ['label'=>'Statistiques', 'tab'=>Controls_Manager::TAB_CONTENT]);
        $this->add_control('stats_items', ['label'=>'Stats', 'type'=>Controls_Manager::REPEATER, 'fields'=>$stats->get_controls(), 'title_field'=>'{{{ value }}}', 'default'=>[
            ['value'=>'18','label'=>'Agences'],
            ['value'=>'2006','label'=>'Fondé à Yaoundé'],
            ['value'=>'24/7','label'=>'Ouvert pour vous'],
        ]]);
        $this->end_controls_section();

        $this->start_controls_section('intro', ['label'=>'Notre histoire', 'tab'=>Controls_Manager::TAB_CONTENT]);
        $this->add_control('intro_title', ['label'=>'Titre', 'type'=>Controls_Manager::TEXTAREA, 'default'=>'Historique']);
        $this->add_control('intro_text', ['label'=>'Texte', 'type'=>Controls_Manager::WYSIWYG, 'default'=>'<p>Le Complexe Santa Lucia ouvre ses portes en <strong>mai 2006</strong> dans le quartier Kondengui, à Yaoundé. Dès le premier jour, la promesse est claire : offrir à chaque client des produits de qualité, des rayons toujours achalandés, et des prix accessibles à toutes les bourses.</p><p>En moins de vingt ans, Santa Lucia est devenu bien plus qu’un supermarché. C’est un espace de vie pensé pour <strong>toute la famille, à toute heure.</strong></p><p>Aujourd’hui, avec <strong>18 agences</strong> implantées à Douala et Yaoundé, ouvertes 24h/24 et 7j/7, Santa Lucia est la référence de la grande distribution au Cameroun.</p>']);
        $this->add_control('intro_image', ['label'=>'Image histoire', 'type'=>Controls_Manager::MEDIA]);
        $this->end_controls_section();

        $this->start_controls_section('mission', ['label'=>'Missions et objectifs', 'tab'=>Controls_Manager::TAB_CONTENT]);
        $this->add_control('mission_title', ['label'=>'Titre', 'type'=>Controls_Manager::TEXT, 'default'=>'Missions et Objectifs']);
        $this->add_control('mission_text', ['label'=>'Texte', 'type'=>Controls_Manager::WYSIWYG, 'default'=>'<p>Notre mission est de mettre à la disposition de notre clientèle des produits manufacturés par des entreprises issues des secteurs formel et informel. Nous visons à créer de la valeur ajoutée par la production et la vente de biens, tout en jouant un rôle majeur dans l’économie locale.</p><p>Notre objectif est de construire un réseau de distribution leader dans l’Afrique centrale, en intégrant verticalement des unités industrielles et agro-industrielles pour renforcer notre modèle économique.</p>']);
        $this->add_control('mission_image', ['label'=>'Image mission', 'type'=>Controls_Manager::MEDIA]);
        $this->end_controls_section();

        $this->start_controls_section('activities_block', ['label'=>'Activités / Valeurs', 'tab'=>Controls_Manager::TAB_CONTENT]);
        $this->add_control('activities_title', ['label'=>'Titre activités', 'type'=>Controls_Manager::TEXT, 'default'=>'Activités']);
        $this->add_control('activities_text', ['label'=>'Texte activités', 'type'=>Controls_Manager::WYSIWYG, 'default'=>'<p>Le cœur de notre modèle économique repose sur la boulangerie, qui est notre activité principale. Grâce à l’expertise de notre PDG, issue de nombreuses formations et d’une solide expérience dans le domaine, nous avons pu développer une offre diversifiée.</p><p>Cette expertise nous permet de proposer des activités complémentaires : hôtel, fast-food, glaces, boulangerie, pâtisserie, shawarma, rôtisserie et manège.</p>']);
        $this->add_control('activities_image', ['label'=>'Image activités', 'type'=>Controls_Manager::MEDIA]);
        $this->add_control('values_title', ['label'=>'Titre valeurs', 'type'=>Controls_Manager::TEXT, 'default'=>'Valeurs']);
        $this->add_control('values_text', ['label'=>'Texte valeurs', 'type'=>Controls_Manager::WYSIWYG, 'default'=>'<p>Chez Santa Lucia, nos valeurs fondamentales guident toutes nos actions. Nous nous engageons à offrir des produits de qualité, à respecter les normes commerciales, et à agir avec intégrité et transparence.</p><p>Nous valorisons également la satisfaction de nos clients, l’innovation dans nos services, et le développement durable pour contribuer positivement à la société et à l’économie nationale.</p>']);
        $this->add_control('team_title', ['label'=>'Titre équipe', 'type'=>Controls_Manager::TEXT, 'default'=>'Équipe']);
        $this->add_control('team_text', ['label'=>'Texte équipe', 'type'=>Controls_Manager::WYSIWYG, 'default'=>'<p>Notre succès repose sur une équipe dédiée et passionnée. Conduits par notre PDG, dont l’expertise et l’expérience proviennent de multiples formations, chaque membre de notre équipe joue un rôle crucial dans l’atteinte de nos objectifs.</p>']);
        $this->add_control('community_title', ['label'=>'Titre engagement', 'type'=>Controls_Manager::TEXT, 'default'=>'Engagement Communautaire']);
        $this->add_control('community_text', ['label'=>'Texte engagement', 'type'=>Controls_Manager::WYSIWYG, 'default'=>'<p>Nous nous engageons à contribuer positivement à la communauté et à l’économie nationale. Notre PDG, dans un élan de patriotisme, place la nation au cœur de nos processus économiques.</p>']);
        $this->end_controls_section();

        $rep = new Repeater();
        $rep->add_control('title', ['label'=>'Titre', 'type'=>Controls_Manager::TEXT, 'default'=>'Supermarché']);
        $rep->add_control('desc', ['label'=>'Description', 'type'=>Controls_Manager::TEXTAREA, 'default'=>'Description du service.']);
        $rep->add_control('tag', ['label'=>'Label', 'type'=>Controls_Manager::TEXT, 'default'=>'Ouvert 24h/24']);
        $this->start_controls_section('services', ['label'=>'Services', 'tab'=>Controls_Manager::TAB_CONTENT]);
        $this->add_control('services_items', ['label'=>'Services', 'type'=>Controls_Manager::REPEATER, 'fields'=>$rep->get_controls(), 'title_field'=>'{{{ title }}}', 'default'=>[
            ['title'=>'Supermarché','desc'=>'Des rayons complets pour les produits frais, l’épicerie et les besoins du quotidien.','tag'=>'Ouvert 24h/24'],
            ['title'=>'Boulangerie & Pâtisserie','desc'=>'Du pain artisanal, des viennoiseries et des gâteaux préparés sur place.','tag'=>'Fait maison'],
            ['title'=>'Restauration','desc'=>'Rôtisserie, shawarma, fast-food, glacier et bar à jus naturels.','tag'=>'Cuisine fraîche'],
            ['title'=>'Loisirs & Famille','desc'=>'Des espaces pensés pour les sorties en famille et les moments de détente.','tag'=>'Pour tous'],
            ['title'=>'Hôtellerie','desc'=>'Des chambres de standing dans certaines agences pour accueillir voyageurs et professionnels.','tag'=>'Confort'],
            ['title'=>'Livraison à domicile','desc'=>'Commandez et recevez vos achats directement chez vous à Douala et Yaoundé.','tag'=>'Express'],
        ]]);
        $this->end_controls_section();

        $cat = new Repeater();
        $cat->add_control('title', ['label'=>'Catégorie', 'type'=>Controls_Manager::TEXT, 'default'=>'Spaghettis']);
        $cat->add_control('desc', ['label'=>'Description', 'type'=>Controls_Manager::TEXTAREA, 'default'=>'Description de la catégorie.']);
        $cat->add_control('count', ['label'=>'Nombre', 'type'=>Controls_Manager::TEXT, 'default'=>'4 produits']);
        $cat->add_control('link', ['label'=>'Lien ancre', 'type'=>Controls_Manager::TEXT, 'default'=>'/produits-maison/#spaghettis']);
        $cat->add_control('image', ['label'=>'Image', 'type'=>Controls_Manager::MEDIA]);
        $this->start_controls_section('categories', ['label'=>'Catégories produits', 'tab'=>Controls_Manager::TAB_CONTENT]);
        $this->add_control('product_categories', ['label'=>'Catégories', 'type'=>Controls_Manager::REPEATER, 'fields'=>$cat->get_controls(), 'title_field'=>'{{{ title }}}', 'default'=>[
            ['title'=>'Spaghettis','desc'=>'Des formats pratiques pour les repas du quotidien.','count'=>'4 produits','link'=>'/produits-maison/#spaghettis'],
            ['title'=>'Farines','desc'=>'Farines tout usage et grands formats pour la maison.','count'=>'7 produits','link'=>'/produits-maison/#farines'],
            ['title'=>'Chips & Apéro','desc'=>'Chips de banane, pommes de terre et pop-corn.','count'=>'8 produits','link'=>'/produits-maison/#chips-apero'],
            ['title'=>'Glaces','desc'=>'Des parfums La Fiesta en formats individuels.','count'=>'4 produits','link'=>'/produits-maison/#glaces'],
            ['title'=>'Pâtes à tartiner Chocojoy','desc'=>'Une gamme chocolatée du petit pot au grand format.','count'=>'7 produits','link'=>'/produits-maison/#pates-a-tartiner-chocojoy'],
            ['title'=>'Autres produits Chocojoy','desc'=>'Des sachets déjeuner lacté chocolatés.','count'=>'2 produits','link'=>'/produits-maison/#autres'],
        ]]);
        $this->end_controls_section();

        $gallery = new Repeater();
        $gallery->add_control('title', ['label'=>'Titre', 'type'=>Controls_Manager::TEXT, 'default'=>'Vue d’ensemble']);
        $gallery->add_control('image', ['label'=>'Image', 'type'=>Controls_Manager::MEDIA]);
        $this->start_controls_section('gallery', ['label'=>'Galerie', 'tab'=>Controls_Manager::TAB_CONTENT]);
        $this->add_control('gallery_items', ['label'=>'Images', 'type'=>Controls_Manager::REPEATER, 'fields'=>$gallery->get_controls(), 'title_field'=>'{{{ title }}}', 'default'=>[
            ['title'=>'Façade & entrée principale'],
            ['title'=>'Rayon boulangerie'],
            ['title'=>'Rayon boucherie'],
            ['title'=>'Glacier & restauration'],
            ['title'=>'Rayons épicerie'],
        ]]);
        $this->end_controls_section();

        $commit = new Repeater();
        $commit->add_control('title', ['label'=>'Titre', 'type'=>Controls_Manager::TEXT, 'default'=>'Des prix accessibles à tous']);
        $commit->add_control('desc', ['label'=>'Description', 'type'=>Controls_Manager::TEXTAREA, 'default'=>'Notre engagement au service des clients.']);
        $this->start_controls_section('commitments', ['label'=>'Engagements', 'tab'=>Controls_Manager::TAB_CONTENT]);
        $this->add_control('commitment_items', ['label'=>'Engagements', 'type'=>Controls_Manager::REPEATER, 'fields'=>$commit->get_controls(), 'title_field'=>'{{{ title }}}', 'default'=>[
            ['title'=>'Des prix accessibles à tous','desc'=>'Le budget ne doit jamais être un obstacle à la qualité.'],
            ['title'=>'Produits frais & locaux','desc'=>'Nous privilégions les produits frais et les approvisionnements locaux.'],
            ['title'=>'Disponibilité 24h/24, 7j/7','desc'=>'Nos agences restent ouvertes à toute heure pour répondre aux besoins.'],
            ['title'=>'Recrutement éthique & transparent','desc'=>'Aucun frais n’est demandé lors de nos recrutements.'],
        ]]);
        $this->end_controls_section();

        $city = new Repeater();
        $city->add_control('city', ['label'=>'Ville', 'type'=>Controls_Manager::TEXT, 'default'=>'Yaoundé']);
        $city->add_control('agencies', ['label'=>'Agences', 'type'=>Controls_Manager::TEXTAREA, 'default'=>"Mokolo\nKondengui\nNgousso"]);
        $this->start_controls_section('network', ['label'=>'Implantations', 'tab'=>Controls_Manager::TAB_CONTENT]);
        $this->add_control('city_items', ['label'=>'Villes', 'type'=>Controls_Manager::REPEATER, 'fields'=>$city->get_controls(), 'title_field'=>'{{{ city }}}', 'default'=>[
            ['city'=>'Yaoundé','agencies'=>"Mokolo\nKondengui\nNgousso\nNkoabang\nMélen\nEssos\nAhala\nOdza\nMvan\nSimbock"],
            ['city'=>'Douala','agencies'=>"Akwa Nord\nBonabéri\nBonamoussadi\nNkolbong\nCité des Palmiers\nCité Cicam\nAkwa\nBercy"],
        ]]);
        $this->end_controls_section();

        $this->start_controls_section('cta', ['label'=>'CTA', 'tab'=>Controls_Manager::TAB_CONTENT]);
        $this->add_control('cta_title', ['label'=>'Titre', 'type'=>Controls_Manager::TEXTAREA, 'default'=>'Venez nous rendre visite,<br><em>vous êtes chez vous.</em>']);
        $this->add_control('cta_text', ['label'=>'Texte', 'type'=>Controls_Manager::TEXTAREA, 'default'=>"Trouvez l'agence la plus proche et découvrez tout ce que Santa Lucia a à vous offrir."]);
        $this->add_control('cta_link', ['label'=>'Lien bouton', 'type'=>Controls_Manager::TEXT, 'default'=>'/nos-agences/']);
        $this->end_controls_section();
    }

    protected function render() {
        $s = $this->get_settings_for_display();
        $this->print_base_styles();
        $hero = $this->image_url($s['hero_image'], sl_pm_upload_url('2026/04/vue-aerienne-nkolbong.jpeg'));
        $intro = $this->image_url($s['intro_image'], sl_pm_upload_url('2026/04/vue-aerienne-nkolbong.jpeg'));
        $mission = $this->image_url($s['mission_image'], sl_pm_upload_url('2026/04/vue-bonaberie.jpg'));
        $activities = $this->image_url($s['activities_image'], sl_pm_upload_url('2024/06/Plan-de-travail-1-1.png'));
        echo '<main class="sl-el-page">';
        echo '<section class="sl-ref-hero" style="background-image:url('.esc_url($hero).')"><div class="sl-el-container"><h1>'.wp_kses_post($s['hero_title']).'</h1><p>'.esc_html($s['hero_text']).'</p></div></section>';
        echo '<section class="sl-ref-band"><div class="sl-el-container"><p class="sl-ref-kicker"><strong>'.wp_kses_post($s['intro_title']).'</strong> &nbsp;'.esc_html__('Un aperçu de notre parcours, notre présence et notre engagement.', 'sl-agences-elementor').'</p></div></section>';
        echo '<section class="sl-ref-image-section"><img class="sl-ref-full-image" src="'.esc_attr($intro).'" alt=""></section>';
        echo '<section class="sl-ref-text-section"><div class="sl-el-container sl-ref-editorial"><div class="sl-ref-num">01.</div><div><h2>'.esc_html($s['mission_title']).'</h2><div class="sl-el-copy">'.wp_kses_post($s['mission_text']).'</div></div></div></section>';
        echo '<section class="sl-ref-image-section"><img class="sl-ref-full-image" src="'.esc_attr($mission).'" alt=""></section>';
        echo '<section class="sl-ref-text-section alt"><div class="sl-el-container sl-ref-editorial"><div class="sl-ref-num">02.</div><div><h2>'.esc_html($s['activities_title']).'</h2><div class="sl-el-copy">'.wp_kses_post($s['activities_text']).'</div><ul class="sl-ref-list"><li>Hôtel</li><li>Fast food</li><li>Glaces</li><li>Boulangerie</li><li>Pâtisserie</li><li>Shawarma</li><li>Rôtisserie</li><li>Manège</li></ul></div></div></section>';
        echo '<section class="sl-ref-image-section"><img class="sl-ref-full-image" src="'.esc_attr($activities).'" alt=""></section>';
        echo '<section class="sl-ref-text-section"><div class="sl-el-container"><div class="sl-ref-values"><article><h3>'.esc_html($s['values_title']).'</h3><div class="sl-el-copy">'.wp_kses_post($s['values_text']).'</div></article><article><h3>'.esc_html($s['team_title']).'</h3><div class="sl-el-copy">'.wp_kses_post($s['team_text']).'</div></article><article><h3>'.esc_html($s['community_title']).'</h3><div class="sl-el-copy">'.wp_kses_post($s['community_text']).'</div></article></div></div></section>';
        echo '</main>';
        return;
        echo '<section class="sl-el-section"><div class="sl-el-container"><div class="sl-el-label">Nos services</div><h2 class="sl-el-title">Plus qu’un supermarché</h2><div class="sl-el-grid-3">';
        foreach ( $s['services_items'] as $item ) echo '<article class="sl-el-card"><h3>'.esc_html($item['title']).'</h3><p>'.esc_html($item['desc']).'</p><span class="sl-el-tag">'.esc_html($item['tag']).'</span></article>';
        echo '</div></div></section>';
        echo '<section class="sl-el-section sl-el-dark"><div class="sl-el-container"><div class="sl-el-label">Nos produits maison</div><h2 class="sl-el-title">Fabriqués pour vous,<br>au <em>juste prix.</em></h2><div class="sl-el-grid-3">';
        $product_fallbacks = [
            sl_pm_upload_url('2024/07/hp-produit-mis-en-avant.png'),
            sl_pm_upload_url('2024/07/hp-produit-patisserie-1.png'),
            sl_pm_upload_url('2024/07/hp-produit-patisserie-2.png'),
            sl_pm_upload_url('2024/07/hp-promo-banner.png'),
            sl_pm_upload_url('2026/02/hp-banner-sous-section.png'),
            sl_pm_upload_url('2024/06/arriere-plan-patisserie-complexe-santa-lucia.webp'),
        ];
        $product_index = 0;
        foreach ( $s['product_categories'] as $item ) {
            $img = $this->image_url($item['image'], $product_fallbacks[ $product_index % count( $product_fallbacks ) ]);
            echo '<a class="sl-el-card" href="'.esc_url(home_url($item['link'])).'"><div class="sl-el-card-img"><img src="'.esc_attr($img).'" alt="'.esc_attr($item['title']).'"></div><h3>'.esc_html($item['title']).'</h3><p>'.esc_html($item['desc']).'</p><span class="sl-el-tag">'.esc_html($item['count']).'</span></a>';
            $product_index++;
        }
        echo '</div></div></section>';
        echo '<section class="sl-el-section"><div class="sl-el-container"><div class="sl-el-label">Nos espaces</div><h2 class="sl-el-title">Une ambiance <em>chaleureuse</em><br>à chaque visite.</h2><div class="sl-el-gallery">';
        $gallery_index = 0;
        foreach ( $s['gallery_items'] as $item ) {
            $fallbacks = [
                sl_pm_upload_url('2023/11/about-image-01.jpg'),
                sl_pm_upload_url('2023/11/about-image-02.jpg'),
                sl_pm_upload_url('2023/11/about-image-03.jpg'),
                sl_pm_upload_url('2024/06/arriere-plan-patisserie-complexe-santa-lucia.webp'),
            ];
            $fallback = $fallbacks[ $gallery_index % count( $fallbacks ) ];
            $img = $this->image_url($item['image'], $fallback);
            echo '<div class="sl-el-media"><img src="'.esc_attr($img).'" alt="'.esc_attr($item['title']).'"></div>';
            $gallery_index++;
        }
        echo '</div></div></section>';
        echo '<section class="sl-el-section"><div class="sl-el-container"><div class="sl-el-label">Nos engagements</div><h2 class="sl-el-title">Des valeurs au service<br>de <em>chaque client.</em></h2><div class="sl-el-commit">';
        $n = 1;
        foreach ( $s['commitment_items'] as $item ) {
            echo '<article class="sl-el-card"><div class="sl-el-num">'.esc_html(str_pad((string) $n, 2, '0', STR_PAD_LEFT)).'</div><div><h3>'.esc_html($item['title']).'</h3><p>'.esc_html($item['desc']).'</p></div></article>';
            $n++;
        }
        echo '</div></div></section>';
        echo '<section class="sl-el-section"><div class="sl-el-container"><div class="sl-el-label">Nos implantations</div><h2 class="sl-el-title">Toujours près de <em>chez vous.</em></h2><div class="sl-el-network">';
        foreach ( $s['city_items'] as $item ) {
            echo '<div class="sl-el-city"><h3>'.esc_html($item['city']).'</h3><ul>';
            foreach ( preg_split('/\r\n|\r|\n/', (string) $item['agencies']) as $agency ) {
                if ( trim($agency) !== '' ) echo '<li>'.esc_html(trim($agency)).'</li>';
            }
            echo '</ul></div>';
        }
        echo '</div></div></section>';
        echo '<section class="sl-el-cta"><div class="sl-el-container"><h2 class="sl-el-title">'.wp_kses_post($s['cta_title']).'</h2><p>'.esc_html($s['cta_text']).'</p><a class="sl-el-btn" href="'.esc_url(home_url($s['cta_link'])).'">Nos agences</a></div></section>';
        echo '</main>';
    }
}

class SL_Produits_Maison_Complete_Widget extends SL_PM_Base_Page_Widget {
    public function get_name(){ return 'sl_produits_maison_complete'; }
    public function get_title(){ return 'Page Produits maison complète'; }
    public function get_icon(){ return 'eicon-products'; }
    public function get_categories(){ return [ 'santa-lucia' ]; }

    protected function register_controls() {
        $this->start_controls_section('hero', ['label'=>'Hero', 'tab'=>Controls_Manager::TAB_CONTENT]);
        $this->add_control('hero_title', ['label'=>'Titre', 'type'=>Controls_Manager::TEXT, 'default'=>'Toutes nos références Santa Lucia']);
        $this->add_control('hero_text', ['label'=>'Texte', 'type'=>Controls_Manager::TEXTAREA, 'default'=>'Retrouvez les produits maison par famille : pâtes, farines, snacks, glaces et gammes Chocojoy.']);
        $this->end_controls_section();

        $rep = new Repeater();
        $rep->add_control('category', ['label'=>'Catégorie', 'type'=>Controls_Manager::TEXT, 'default'=>'Spaghettis']);
        $rep->add_control('anchor', ['label'=>'Ancre', 'type'=>Controls_Manager::TEXT, 'default'=>'spaghettis']);
        $rep->add_control('title', ['label'=>'Produit', 'type'=>Controls_Manager::TEXT, 'default'=>'Spaghetti Santa Lucia 500g']);
        $rep->add_control('desc', ['label'=>'Description', 'type'=>Controls_Manager::TEXTAREA, 'default'=>'Description courte du produit.']);
        $rep->add_control('image', ['label'=>'Image', 'type'=>Controls_Manager::MEDIA]);

        $rep->add_control('bg_size', [
            'label'   => __( 'Taille de l\'image', 'sl-agences' ),
            'type'    => Controls_Manager::SELECT,
            'default' => 'cover',
            'options' => [
                'cover'   => 'Cover (remplit la carte)',
                'contain' => 'Contain (image entière visible)',
                'auto'    => 'Auto (taille naturelle)',
                '50%'     => '50%',
                '75%'     => '75%',
                '100%'    => '100%',
            ],
        ]);

        $rep->add_control('bg_position', [
            'label'   => __( 'Position', 'sl-agences' ),
            'type'    => Controls_Manager::SELECT,
            'default' => 'center center',
            'options' => [
                'center center' => 'Centre',
                'top center'    => 'Haut — Centre',
                'top left'      => 'Haut — Gauche',
                'top right'     => 'Haut — Droite',
                'center left'   => 'Milieu — Gauche',
                'center right'  => 'Milieu — Droite',
                'bottom center' => 'Bas — Centre',
                'bottom left'   => 'Bas — Gauche',
                'bottom right'  => 'Bas — Droite',
            ],
        ]);

        $rep->add_control('bg_repeat', [
            'label'   => __( 'Répétition', 'sl-agences' ),
            'type'    => Controls_Manager::SELECT,
            'default' => 'no-repeat',
            'options' => [
                'no-repeat' => 'Aucune répétition',
                'repeat'    => 'Répéter (X & Y)',
                'repeat-x'  => 'Répéter horizontalement',
                'repeat-y'  => 'Répéter verticalement',
            ],
        ]);

        $rep->add_control('bg_attachment', [
            'label'   => __( 'Comportement au scroll', 'sl-agences' ),
            'type'    => Controls_Manager::SELECT,
            'default' => 'scroll',
            'options' => [
                'scroll' => 'Scroll (normal)',
                'fixed'  => 'Fixed (parallax)',
            ],
        ]);
        $this->start_controls_section('products', ['label'=>'Produits', 'tab'=>Controls_Manager::TAB_CONTENT]);
        $this->add_control('products_items', ['label'=>'Produits', 'type'=>Controls_Manager::REPEATER, 'fields'=>$rep->get_controls(), 'title_field'=>'{{{ title }}}', 'default'=>[
            ['category'=>'Spaghettis','anchor'=>'spaghettis','title'=>'Spaghetti Santa Lucia 250g','desc'=>'Format pratique pour les repas rapides et les petites préparations.'],
            ['category'=>'Spaghettis','anchor'=>'spaghettis','title'=>'Spaghetti Santa Lucia 500g','desc'=>'Format familial pour les plats de pâtes du quotidien.'],
            ['category'=>'Spaghettis','anchor'=>'spaghettis','title'=>'Spaghetti Omit 250g','desc'=>'Portion simple pour cuisiner vite avec une texture régulière.'],
            ['category'=>'Spaghettis','anchor'=>'spaghettis','title'=>'Spaghetti Omit 500g','desc'=>'Format généreux pensé pour les repas partagés.'],
            ['category'=>'Farines','anchor'=>'farines','title'=>'Farine Amira tout usage 1kg','desc'=>'Farine polyvalente pour pâtisseries, beignets et préparations maison.'],
            ['category'=>'Farines','anchor'=>'farines','title'=>'Farine Amira tout usage 2kg','desc'=>'Format économique pour les cuisines familiales et professionnelles.'],
            ['category'=>'Farines','anchor'=>'farines','title'=>'Farine La fleur Blanche','desc'=>'Farine fine pour des recettes souples, légères et régulières.'],
            ['category'=>'Farines','anchor'=>'farines','title'=>'Farine Mami Lou 1kg','desc'=>'Format pratique pour les recettes courantes de la maison.'],
            ['category'=>'Farines','anchor'=>'farines','title'=>'Farine Mami Lou 2kg','desc'=>'Format familial pour garder une réserve utile en cuisine.'],
            ['category'=>'Farines','anchor'=>'farines','title'=>'Farine Mami Lou 5kg','desc'=>'Conditionnement adapté aux besoins fréquents et aux grandes préparations.'],
            ['category'=>'Farines','anchor'=>'farines','title'=>'Farine Mami Lou 25kg','desc'=>'Grand format pour boulangeries, points de vente et usages intensifs.'],
            ['category'=>'Chips & Apéro','anchor'=>'chips-apero','title'=>'Apéro Chips Nature','desc'=>'Chips croustillantes au goût nature pour accompagner les pauses.'],
            ['category'=>'Chips & Apéro','anchor'=>'chips-apero','title'=>'Apéro Chips Sucré','desc'=>'Snack sucré et croquant pour un moment gourmand.'],
            ['category'=>'Chips & Apéro','anchor'=>'chips-apero','title'=>'Fiesta Chips Banane Sucré','desc'=>'Chips de banane sucrées, idéales pour les envies gourmandes.'],
            ['category'=>'Chips & Apéro','anchor'=>'chips-apero','title'=>'Fiesta Chips Banane Salé','desc'=>'Chips de banane salées pour l’apéritif et les pauses rapides.'],
            ['category'=>'Chips & Apéro','anchor'=>'chips-apero','title'=>'Fiesta Chips Pommes Nature','desc'=>'Chips de pomme de terre au goût simple et croustillant.'],
            ['category'=>'Chips & Apéro','anchor'=>'chips-apero','title'=>'Fiesta Chips Pommes Poulet Braisé','desc'=>'Saveur poulet braisé pour un apéritif plus relevé.'],
            ['category'=>'Chips & Apéro','anchor'=>'chips-apero','title'=>'Fiesta Chips Pommes Poulet Epicé','desc'=>'Goût poulet épicé pour les amateurs de snacks intenses.'],
            ['category'=>'Chips & Apéro','anchor'=>'chips-apero','title'=>'Pop Corn Apéro 120G','desc'=>'Pop-corn prêt à partager pendant les pauses et moments détente.'],
            ['category'=>'Glaces','anchor'=>'glaces','title'=>'Glace La Fiesta Coconut 200ML','desc'=>'Glace coco en format individuel, fraîche et gourmande.'],
            ['category'=>'Glaces','anchor'=>'glaces','title'=>'Glace La Fiesta Vanille 200ML','desc'=>'Classique vanille en format pratique pour une pause fraîche.'],
            ['category'=>'Glaces','anchor'=>'glaces','title'=>'Glace La Fiesta Choco 200ML','desc'=>'Glace chocolatée pour les envies sucrées et rafraîchissantes.'],
            ['category'=>'Glaces','anchor'=>'glaces','title'=>'Glace La Fiesta Fraise 200ML','desc'=>'Saveur fraise douce et fruitée en format individuel.'],
            ['category'=>'Pâtes à tartiner Chocojoy','anchor'=>'pates-a-tartiner-chocojoy','title'=>'Pâte à tartiner Chocojoy 200G','desc'=>'Petit format chocolaté pour tartines, crêpes et goûters.'],
            ['category'=>'Pâtes à tartiner Chocojoy','anchor'=>'pates-a-tartiner-chocojoy','title'=>'Pâte à tartiner Chocojoy 450G','desc'=>'Format familial pour les petits déjeuners et moments gourmands.'],
            ['category'=>'Pâtes à tartiner Chocojoy','anchor'=>'pates-a-tartiner-chocojoy','title'=>'Pâte à tartiner Chocojoy 800G','desc'=>'Pot généreux pour accompagner plusieurs usages à la maison.'],
            ['category'=>'Pâtes à tartiner Chocojoy','anchor'=>'pates-a-tartiner-chocojoy','title'=>'Pâte à tartiner Chocojoy 1kg','desc'=>'Format pratique pour familles, snacks et petites restaurations.'],
            ['category'=>'Pâtes à tartiner Chocojoy','anchor'=>'pates-a-tartiner-chocojoy','title'=>'Pâte à tartiner Chocojoy 2.8kg','desc'=>'Conditionnement adapté aux usages fréquents et professionnels.'],
            ['category'=>'Pâtes à tartiner Chocojoy','anchor'=>'pates-a-tartiner-chocojoy','title'=>'Pâte à tartiner Chocojoy 4.5kg','desc'=>'Grand format pour ateliers, points de vente et préparations en volume.'],
            ['category'=>'Pâtes à tartiner Chocojoy','anchor'=>'pates-a-tartiner-chocojoy','title'=>'Pâte à tartiner Chocojoy 10kg','desc'=>'Très grand format destiné aux besoins intensifs et professionnels.'],
            ['category'=>'Autres','anchor'=>'autres','title'=>'Sachet Déjeuner Lacté Chocojoy 20G','desc'=>'Sachet chocolaté pour préparer une boisson lactée rapide.'],
            ['category'=>'Autres','anchor'=>'autres','title'=>'Sachet Déjeuner Lacté Chocojoy 35G','desc'=>'Format sachet plus généreux pour boisson lactée chocolatée.'],
        ]]);
        $this->end_controls_section();
    }

    public function get_style_depends() { return [ 'sl-produits-maison-page' ]; }

    protected function render() {
        $s = $this->get_settings_for_display();

        /* Grouper par catégorie en conservant l'ordre */
        $groups = [];
        foreach ( $s['products_items'] as $item ) {
            $cat = $item['category'] ?: 'Produits';
            $groups[ $cat ][] = $item;
        }

        $total_produits = count( $s['products_items'] );
        $total_cats     = count( $groups );

        /* ── HERO ─────────────────────────────────────────────── */
        ?>
        <style>
        @media (max-width: 860px) {
          .slpm-grid,.slpm-grid-2col,.slpm-grid-3col,.slpm-grid-4col{grid-template-columns:repeat(2,1fr)!important;}
          .slpm-nav-link{padding:14px 12px;font-size:10px;letter-spacing:1px;}
        }
        @media (max-width: 768px) {
          .slpm-pw{padding:0 16px!important;}
          .slpm-cat-section{padding:52px 0 40px!important;}
          .slpm-cat-head{flex-direction:column;align-items:flex-start;gap:12px;margin-bottom:32px!important;}
          .slpm-cat-count-badge{display:none!important;}
        }
        @media (max-width: 540px) {
          .slpm-grid,.slpm-grid-2col,.slpm-grid-3col,.slpm-grid-4col{grid-template-columns:1fr!important;}
          .slpm-hero-titre{font-size:clamp(28px,8vw,42px)!important;}
          .slpm-hero{padding:60px 0 48px!important;}
          .slpm-hero-desc{font-size:15px;margin-bottom:32px!important;}
          .slpm-hero-stats{flex-direction:column!important;}
          .slpm-hero-stat{border-right:none!important;border-bottom:1px solid rgba(255,255,255,.1);}
          .slpm-hero-stat:last-child{border-bottom:none!important;}
          .slpm-cat-section{padding:40px 0 32px!important;}
          .slpm-cat-titre{font-size:clamp(20px,6vw,28px)!important;}
          .slpm-card-body{padding:14px 16px 16px!important;}
          .slpm-card-nom{font-size:13px!important;}
          .slpm-card-desc{font-size:12px!important;}
          .slpm-nav-link{padding:10px 10px;font-size:9px!important;letter-spacing:.5px;}
          .slpm-cta{padding:60px 0!important;}
          .slpm-cta-btn{width:100%!important;justify-content:center!important;}
          .slpm-cta-titre{font-size:clamp(22px,7vw,34px)!important;}
        }
        </style>
        <div class="slpm-page">

        <section class="slpm-hero">
          <div class="slpm-hero-grid-bg"></div>
          <div class="slpm-pw" style="position:relative;z-index:2">
            <div class="slpm-hero-badge">
              <span class="slpm-hero-badge-dot"></span>
              <span class="slpm-hero-badge-text">Produits Maison</span>
            </div>
            <h1 class="slpm-hero-titre">
              <span><?php echo esc_html( $s['hero_title'] ); ?></span>
            </h1>
            <p class="slpm-hero-desc"><?php echo esc_html( $s['hero_text'] ); ?></p>
            <div class="slpm-hero-stats">
              <div class="slpm-hero-stat">
                <strong class="slpm-hero-stat-val"><?php echo $total_cats; ?></strong>
                <span class="slpm-hero-stat-lbl">Familles</span>
              </div>
              <div class="slpm-hero-stat">
                <strong class="slpm-hero-stat-val"><?php echo $total_produits; ?>+</strong>
                <span class="slpm-hero-stat-lbl">Références</span>
              </div>
              <div class="slpm-hero-stat">
                <strong class="slpm-hero-stat-val">🇨🇲</strong>
                <span class="slpm-hero-stat-lbl">Made in Cameroun</span>
              </div>
            </div>
          </div>
        </section>

        <?php /* ── NAV CATÉGORIES ── */ ?>
        <nav class="slpm-nav" aria-label="Catégories">
          <div class="slpm-pw">
            <div class="slpm-nav-inner">
              <?php $i = 1; foreach ( $groups as $cat => $items ) :
                $anchor = sanitize_title( $items[0]['anchor'] ?: $cat );
              ?>
              <a href="#<?php echo esc_attr( $anchor ); ?>" class="slpm-nav-link">
                <?php echo esc_html( $cat ); ?>
                <span class="slpm-nav-count"><?php echo count( $items ); ?></span>
              </a>
              <?php $i++; endforeach; ?>
            </div>
          </div>
        </nav>

        <?php /* ── SECTIONS PRODUITS ── */
        $num = 1;
        foreach ( $groups as $cat => $items ) :
            $anchor  = sanitize_title( $items[0]['anchor'] ?: $cat );
            $nb      = count( $items );
            $num_str = str_pad( (string) $num, 2, '0', STR_PAD_LEFT );
        ?>
        <section id="<?php echo esc_attr( $anchor ); ?>" class="slpm-cat-section">
          <div class="slpm-pw">

            <!-- En-tête catégorie -->
            <div class="slpm-cat-head">
              <div class="slpm-cat-head-left">
                <div class="slpm-cat-meta">
                  <span class="slpm-cat-num"><?php echo esc_html( $num_str ); ?></span>
                  <span class="slpm-cat-dot-sep"></span>
                  <span class="slpm-cat-tag">Produits Santa Lucia</span>
                </div>
                <h2 class="slpm-cat-titre"><?php echo esc_html( $cat ); ?></h2>
              </div>
              <div class="slpm-cat-count-badge"><?php echo $nb; ?></div>
            </div>

            <!-- Grille produits -->
            <div class="slpm-grid">
              <?php foreach ( $items as $item ) :
                $img           = $this->image_url( $item['image'], sl_pm_svg_placeholder( $item['title'], $cat, '#e85499' ) );
                $bg_size       = ! empty( $item['bg_size'] )       ? $item['bg_size']       : 'cover';
                $bg_position   = ! empty( $item['bg_position'] )   ? $item['bg_position']   : 'center center';
                $bg_repeat     = ! empty( $item['bg_repeat'] )     ? $item['bg_repeat']     : 'no-repeat';
                $bg_attachment = ! empty( $item['bg_attachment'] ) ? $item['bg_attachment'] : 'scroll';
                /* esc_url() bloque les data: URIs — on utilise esc_attr() pour le style inline */
                $img_escaped = strpos( $img, 'data:' ) === 0 ? esc_attr( $img ) : esc_url( $img );
                $bg_style = 'background-image:url(' . $img_escaped . ');'
                    . 'background-size:' . esc_attr( $bg_size ) . ';'
                    . 'background-position:' . esc_attr( $bg_position ) . ';'
                    . 'background-repeat:' . esc_attr( $bg_repeat ) . ';'
                    . 'background-attachment:' . esc_attr( $bg_attachment ) . ';';
              ?>
              <article class="slpm-card">
                <div class="slpm-card-img slpm-card-img-bg" style="<?php echo $bg_style; ?>"></div>
                <div class="slpm-card-body">
                  <h3 class="slpm-card-nom"><?php echo esc_html( $item['title'] ); ?></h3>
                  <p class="slpm-card-desc"><?php echo esc_html( $item['desc'] ); ?></p>
                  <span class="slpm-card-tag">Santa Lucia Maison</span>
                </div>
              </article>
              <?php endforeach; ?>
            </div>

          </div>
        </section>
        <?php $num++; endforeach; ?>

        <?php /* ── CTA FINAL ── */ ?>
        <section class="slpm-cta">
          <div class="slpm-pw slpm-cta-inner">
            <h2 class="slpm-cta-titre">
              Trouvez nos produits<br>
              <em>dans votre agence.</em>
            </h2>
            <p class="slpm-cta-desc">
              Disponibles dans les 18 agences Santa Lucia à Douala et Yaoundé — ouverts 24h/24, 7j/7.
            </p>
            <a href="/nos-agences/" class="slpm-cta-btn">
              Trouver une agence
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>
              </svg>
            </a>
          </div>
        </section>

        </div><!-- .slpm-page -->
        <?php
    }
}
