jQuery(function ($) {
    'use strict';

    var saveTimers = {};

    /* ================================================================
       PLANNING — Auto-sauvegarde quand une case est cochee/decochee
    ================================================================ */
    $(document).on('change', '.sl-ff-day-cb', function () {
        var $row  = $(this).closest('tr.sl-ff-meal-row');
        var id    = $row.data('id');
        var agence = $row.attr('data-agence') || '';
        var $icon = $row.find('.sl-ff-save-icon');

        var jours = [];
        $row.find('.sl-ff-day-cb:checked').each(function () {
            jours.push($(this).val());
        });

        clearTimeout(saveTimers[id]);
        $icon.attr('class', 'sl-ff-save-icon sl-ff-saving').html('&#8987;');

        saveTimers[id] = setTimeout(function () {
            $.post(slFF.ajaxurl, {
                action:  'sl_ff_save_planning',
                nonce:   slFF.nonce,
                post_id: id,
                agence:  agence,
                jours:   jours
            })
            .done(function (res) {
                if (res.success) {
                    $icon.attr('class', 'sl-ff-save-icon sl-ff-saved').html('&#10003;');
                    var isToday = slFF.todayJour && jours.indexOf(slFF.todayJour) !== -1;
                    $row.toggleClass('sl-ff-meal-dispo', !!isToday);
                    // Maj des attributs pour que le filtre Disponibilite reste juste
                    $row.attr('data-checked', jours.length ? '1' : '0');
                    $row.attr('data-today', isToday ? '1' : '0');
                    setTimeout(function () { $icon.html('').attr('class', 'sl-ff-save-icon'); }, 2000);
                } else {
                    $icon.attr('class', 'sl-ff-save-icon sl-ff-error').html('&#10007;');
                }
            })
            .fail(function () {
                $icon.attr('class', 'sl-ff-save-icon sl-ff-error').html('&#10007;');
            });
        }, 350);
    });

    /* ================================================================
       PROMOTIONS — Auto-sauvegarde quand un champ change
    ================================================================ */
    $(document).on('change', '.sl-ff-promo-pct, .sl-ff-promo-debut, .sl-ff-promo-fin, .sl-ff-promo-prix, .sl-ff-promo-prix-promo', function () {
        var $row  = $(this).closest('tr.sl-ff-meal-row');
        var id    = $row.data('id');
        var $icon = $row.find('.sl-ff-save-icon');
        var key   = 'promo_' + id;

        clearTimeout(saveTimers[key]);
        $icon.attr('class', 'sl-ff-save-icon sl-ff-saving').html('&#8987;');

        saveTimers[key] = setTimeout(function () {
            $.post(slFF.ajaxurl, {
                action:      'sl_ff_save_promo',
                nonce:       slFF.nonce,
                post_id:     id,
                promo_pct:   $row.find('.sl-ff-promo-pct').val()        || 0,
                prix:        $row.find('.sl-ff-promo-prix').val()       || 0,
                prix_promo:  $row.find('.sl-ff-promo-prix-promo').val() || 0,
                promo_debut: $row.find('.sl-ff-promo-debut').val()      || '',
                promo_fin:   $row.find('.sl-ff-promo-fin').val()        || ''
            })
            .done(function (res) {
                if (res.success) {
                    $icon.attr('class', 'sl-ff-save-icon sl-ff-saved').html('&#10003;');
                    // Mettre a jour la classe promo active
                    if (res.data && res.data.est_promo) {
                        $row.addClass('sl-ff-promo-active');
                    } else {
                        $row.removeClass('sl-ff-promo-active');
                    }
                    setTimeout(function () { $icon.html('').attr('class', 'sl-ff-save-icon'); }, 2000);
                } else {
                    $icon.attr('class', 'sl-ff-save-icon sl-ff-error').html('&#10007;');
                }
            })
            .fail(function () {
                $icon.attr('class', 'sl-ff-save-icon sl-ff-error').html('&#10007;');
            });
        }, 600);
    });

    /* ================================================================
       FILTRES PLANNING (recherche + agence + categorie + disponibilite)
       Filtrage par attributs data-* precalcules : pas de lecture du DOM
       texte ligne par ligne -> rapide meme avec des milliers de lignes.
    ================================================================ */
    function slFfNorm(s) {
        s = String(s || '').toLowerCase();
        // retire les accents pour matcher data-search (deja sans accents cote PHP)
        if (s.normalize) { s = s.normalize('NFD').replace(/[\u0300-\u036f]/g, ''); }
        return s.replace(/\s+/g, ' ').trim();
    }

    var $rows    = null;  // cache des lignes (rempli au 1er filtrage)
    var $catRows = null;

    function slFfApplyPlanningFilters() {
        if (!$rows) {
            $rows    = $('tr.sl-ff-meal-row');
            $catRows = $('tr.sl-ff-cat-row');
        }
        var agence = String($('#sl-ff-agence-filter').val() || '').toLowerCase().trim();
        var search = slFfNorm($('#sl-ff-meal-search').val());
        var cat    = String($('#sl-ff-cat-filter').val() || '');
        var dispo  = String($('#sl-ff-dispo-filter').val() || '');
        var shown  = 0;

        $rows.each(function () {
            var r = this;
            var ok = true;

            if (agence) {
                var rowAg = ' ' + String(r.getAttribute('data-agence') || '').toLowerCase().trim() + ' ';
                if (rowAg.indexOf(' ' + agence + ' ') === -1) ok = false;
            }
            if (ok && cat && r.getAttribute('data-cat') !== cat) ok = false;
            if (ok && search && (r.getAttribute('data-search') || '').indexOf(search) === -1) ok = false;
            if (ok && dispo) {
                if (dispo === 'today')     ok = r.getAttribute('data-today')   === '1';
                else if (dispo === 'checked')   ok = r.getAttribute('data-checked') === '1';
                else if (dispo === 'unchecked') ok = r.getAttribute('data-checked') === '0';
            }

            r.style.display = ok ? '' : 'none';
            if (ok) shown++;
        });

        // Masquer les en-tetes de categorie sans ligne visible
        $catRows.each(function () {
            var visible = false, n = this.nextElementSibling;
            while (n && n.className.indexOf('sl-ff-cat-row') === -1) {
                if (n.className.indexOf('sl-ff-meal-row') !== -1 && n.style.display !== 'none') { visible = true; break; }
                n = n.nextElementSibling;
            }
            this.style.display = visible ? '' : 'none';
        });

        var $count = $('#sl-ff-filter-count');
        if ($count.length) {
            var total = $rows.length;
            $count.text(shown === total ? (total + ' ligne(s)') : (shown + ' / ' + total + ' ligne(s)'));
        }
    }

    var searchTimer = null;
    $('#sl-ff-meal-search').on('input', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(slFfApplyPlanningFilters, 200);
    });
    $('#sl-ff-agence-filter, #sl-ff-cat-filter, #sl-ff-dispo-filter').on('change', slFfApplyPlanningFilters);
    if ($('#sl-ff-planning-table').length) { slFfApplyPlanningFilters(); }

    /* ================================================================
       IMAGE REPAS — selection simple depuis la mediatheque WP
    ================================================================ */
    var imageFrame = null;

    $(document).on('click', '.sl-ff-image-select', function (e) {
        e.preventDefault();

        if (typeof wp === 'undefined' || !wp.media) {
            return;
        }

        imageFrame = wp.media({
            title: 'Choisir une image pour le repas',
            button: { text: 'Utiliser cette image' },
            library: { type: 'image' },
            multiple: false
        });

        imageFrame.on('select', function () {
            var attachment = imageFrame.state().get('selection').first().toJSON();
            var url = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;

            $('#sl_ff_thumbnail_id').val(attachment.id);
            $('#sl_ff_image_preview')
                .addClass('has-image')
                .html('<img src="' + url + '" alt="">');
            $('.sl-ff-image-select').text("Remplacer l'image");
            $('.sl-ff-image-remove').prop('disabled', false);
        });

        imageFrame.open();
    });

    $(document).on('click', '.sl-ff-image-remove', function (e) {
        e.preventDefault();
        $('#sl_ff_thumbnail_id').val('0');
        $('#sl_ff_image_preview')
            .removeClass('has-image')
            .html('<span class="dashicons dashicons-format-image"></span><strong>Aucune image</strong>');
        $('.sl-ff-image-select').text('Ajouter une image');
        $(this).prop('disabled', true);
    });
});
